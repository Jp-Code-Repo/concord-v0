<?php

namespace App\Services;

use App\Models\AuthSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Opaque server-side sessions. The browser holds a random token in an HTTP-only
 * cookie; only its SHA-256 hash is stored. Redis caches lookups for speed, and
 * every revoke deletes the cache entry so revocation is immediate.
 */
class SessionService
{
    public const COOKIE = 'concord_session';

    private const CACHE_TTL = 300;

    /** @return array{0: string, 1: AuthSession} [plain token, session row] */
    public function create(User $user, Request $request): array
    {
        $plain = bin2hex(random_bytes(32));

        $session = AuthSession::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'expires_at' => now()->addHours((int) config('concord.session_hours', 8)),
        ]);

        return [$plain, $session];
    }

    /**
     * @return array{
     *     session_id: string,
     *     user_id: string,
     *     is_it_admin: bool,
     *     must_change_password: bool,
     *     expires_at: int
     * }|null
     */
    public function resolve(?string $plain): ?array
    {
        if (! $plain || ! preg_match('/^[a-f0-9]{64}$/', $plain)) {
            return null;
        }

        $key = 'sess:'.hash('sha256', $plain);
        $payload = Cache::get($key);

        // Discard older cached payloads created before the password-change
        // requirement was added.
        if ($payload !== null && ! array_key_exists('must_change_password', $payload)) {
            Cache::forget($key);
            $payload = null;
        }

        if ($payload === null) {
            $row = DB::table('auth_sessions')
                ->join('users', 'users.id', '=', 'auth_sessions.user_id')
                ->where('auth_sessions.token_hash', hash('sha256', $plain))
                ->whereNull('auth_sessions.revoked_at')
                ->where('auth_sessions.expires_at', '>', now())
                ->where('users.status', 'active')
                ->first([
                    'auth_sessions.id as session_id',
                    'users.id as user_id',
                    'users.is_it_admin',
                    'users.must_change_password',
                    'auth_sessions.expires_at',
                ]);

            if (! $row) {
                return null;
            }

            $payload = [
                'session_id' => $row->session_id,
                'user_id' => $row->user_id,
                'is_it_admin' => (bool) $row->is_it_admin,
                'must_change_password' => (bool) $row->must_change_password,
                'expires_at' => Carbon::parse($row->expires_at)->timestamp,
            ];

            Cache::put($key, $payload, self::CACHE_TTL);
        }

        if ($payload['expires_at'] <= time()) {
            Cache::forget($key);

            return null;
        }

        return $payload;
    }

    /**
     * Revoke one session.
     *
     * Logout already holds the plain token (from the cookie), so it passes it in and
     * the hash is computed directly - no lookup query. The token hash is part of the
     * UPDATE's WHERE clause, so a mismatched token can never revoke the wrong row;
     * in that case (or when no token is given) the original lookup path runs.
     */
    public function revoke(string $sessionId, string $reason, ?string $plainToken = null): void
    {
        if ($plainToken !== null && preg_match('/^[a-f0-9]{64}$/', $plainToken)) {
            $tokenHash = hash('sha256', $plainToken);

            $updated = DB::table('auth_sessions')
                ->where('id', $sessionId)
                ->where('token_hash', $tokenHash)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'revoked_reason' => $reason]);

            Cache::forget('sess:'.$tokenHash);

            if ($updated > 0) {
                return;
            }
        }

        $row = DB::table('auth_sessions')
            ->where('id', $sessionId)
            ->whereNull('revoked_at')
            ->first(['token_hash']);

        if (! $row) {
            return;
        }

        DB::table('auth_sessions')->where('id', $sessionId)
            ->update(['revoked_at' => now(), 'revoked_reason' => $reason]);

        Cache::forget('sess:'.$row->token_hash);
    }

    /** Used when an account is disabled: every session dies immediately. */
    public function revokeAllForUser(string $userId, string $reason): void
    {
        $rows = DB::table('auth_sessions')
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->get(['id', 'token_hash']);

        if ($rows->isEmpty()) {
            return;
        }

        DB::table('auth_sessions')->whereIn('id', $rows->pluck('id'))
            ->update(['revoked_at' => now(), 'revoked_reason' => $reason]);

        foreach ($rows as $row) {
            Cache::forget('sess:'.$row->token_hash);
        }
    }

    /**
     * Forget the cached payload for a session token.
     *
     * Used after a password change so the current session reloads its
     * updated authentication state on the next request.
     */
    public function forgetCacheForToken(?string $plainToken): void
    {
        if (! $plainToken || ! preg_match('/^[a-f0-9]{64}$/', $plainToken)) {
            return;
        }

        Cache::forget('sess:'.hash('sha256', $plainToken));
    }

    /**
     * Revoke every active session for a user except the current session.
     * Used after a successful password change so the current device stays signed in.
     */
    public function revokeOtherSessionsForUser(
        string $userId,
        string $currentSessionId,
        string $reason
    ): void {
        $rows = DB::table('auth_sessions')
            ->where('user_id', $userId)
            ->where('id', '!=', $currentSessionId)
            ->whereNull('revoked_at')
            ->get(['id', 'token_hash']);

        if ($rows->isEmpty()) {
            return;
        }

        DB::table('auth_sessions')
            ->whereIn('id', $rows->pluck('id'))
            ->update([
                'revoked_at' => now(),
                'revoked_reason' => $reason,
            ]);

        foreach ($rows as $row) {
            Cache::forget('sess:'.$row->token_hash);
        }
    }
}