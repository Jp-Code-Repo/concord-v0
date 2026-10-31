<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\User;
use App\Services\AccessService;
use App\Services\SessionService;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /** Valid bcrypt hash, so unknown usernames cost the same time as wrong passwords. */
    private const DUMMY_HASH = '$2y$12$Jgr/PuTRueOHquXLYSXu6uQSyt3kpSEGRXP9oKNbJ6iUiHNkTjYhK';

    public function login(Request $request, SessionService $sessions, AccessService $access): JsonResponse
    {
        $data = $request->validate([
            'username' => 'required|string|max:100',
            'password' => 'required|string|max:200',
        ]);

        $user = User::where('username', $data['username'])->first();

        // Email is identity data, not an account key. Accept it for login only
        // when it identifies exactly one person with a linked account.
        if (! $user) {
            $people = Person::query()
                ->where('email', strtolower(trim($data['username'])))
                ->limit(2)
                ->get(['id']);

            if ($people->count() === 1) {
                $user = User::where('person_id', $people->first()->id)->first();
            }
        }

        $passwordOk = Hash::check($data['password'], $user?->password ?? self::DUMMY_HASH);

        // Same answer for unknown user, wrong password and disabled account.
        if (! $user || ! $passwordOk || $user->status !== 'active') {
            Audit::log('auth.login_failed', $user?->id, $request, meta: [
                'username' => mb_substr($data['username'], 0, 100),
                'reason' => ! $user ? 'unknown_user' : ($user->status !== 'active' ? $user->status : 'bad_password'),
            ]);

            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        [$token, $session] = $sessions->create($user, $request);
        Audit::log('auth.login', $user->id, $request, entityType: 'auth_session', entityId: $session->id);

        // Same payload as GET /me (user + access), so the frontend does not have to
        // make a second request - and pay a second Laravel boot - right after login.
        $user->load(['person.types', 'person.student']);

        return response()
            ->json([
                'user' => $this->userPayload($user),
                'access' => $user->must_change_password
                    ? null
                    : $access->forUser($user->id, $user->is_it_admin),
            ])
            ->withCookie(cookie(
                name: SessionService::COOKIE,
                value: $token,
                minutes: (int) config('concord.session_hours', 8) * 60,
                path: '/',
                domain: null,
                secure: (bool) config('concord.cookie_secure'),
                httpOnly: true,
                raw: false,
                sameSite: 'lax',
            ));
    }

    public function logout(Request $request, SessionService $sessions): JsonResponse
    {
        $auth = $request->attributes->get('auth');
        $sessions->revoke($auth['session_id'], 'logout', $request->cookie(SessionService::COOKIE));
        Audit::log('auth.logout', $auth['user_id'], $request);

        return response()
            ->json(null, 204)
            ->withCookie(cookie()->forget(SessionService::COOKIE));
    }

    public function me(Request $request, AccessService $access): JsonResponse
    {
        $auth = $request->attributes->get('auth');
        $user = User::with(['person.types', 'person.student'])->findOrFail($auth['user_id']);

        return response()->json([
            'user' => $this->userPayload($user),
            'access' => $user->must_change_password
                ? null
                : $access->forUser($user->id, $user->is_it_admin),
        ]);
    }

    public function changePassword(Request $request, SessionService $sessions): JsonResponse
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:12|confirmed',
        ]);

        $auth = $request->attributes->get('auth');
        $user = User::findOrFail($auth['user_id']);

        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'errors' => [
                    'current_password' => [
                        'The current password is incorrect.',
                    ],
                ],
            ], 422);
        }

        DB::transaction(function () use ($data, $user, $auth, $request, $sessions) {
            // The User model's hashed cast handles password hashing.
            $user->password = $data['new_password'];
            $user->must_change_password = false;
            $user->save();

            // Keep this device signed in. Revoke every other active session.
            $sessions->revokeOtherSessionsForUser(
                $user->id,
                $auth['session_id'],
                'password_changed',
            );

            Audit::log('auth.password_changed', $user->id, $request, meta: [
                'other_sessions_revoked' => true,
            ]);
        });

        // SessionAuth caches the session payload. Clear this device's cached
        // payload so its next request sees must_change_password = false.
        $sessions->forgetCacheForToken(
            $request->cookie(SessionService::COOKIE),
        );

        return response()->json([
            'message' => 'Password changed successfully.',
            'must_change_password' => false,
        ]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'preferences' => 'required|array',
        ]);

        $auth = $request->attributes->get('auth');
        $user = User::with('person')->findOrFail($auth['user_id']);
        abort_unless($user->person, 409, 'This account has no linked person identity.');

        $person = $user->person;
        $person->preferences = array_merge(
            $person->preferences ?? [],
            $data['preferences'],
        );
        $person->save();

        return response()->json([
            'preferences' => $person->fresh()->preferences ?? [],
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => 'required|array:session_warning',
            'settings.session_warning' => 'sometimes|boolean',
        ]);

        $auth = $request->attributes->get('auth');
        $user = User::findOrFail($auth['user_id']);
        $user->settings = array_merge($user->settings ?? [], $data['settings']);
        $user->save();

        return response()->json(['settings' => $user->fresh()->settings ?? []]);
    }

    /** Edge auth_request target: 2xx + identity headers, or 401. */
    public function verify(Request $request): JsonResponse
    {
        $auth = $request->attributes->get('auth');

        return response()->json(null, 204)->withHeaders([
            'X-User-Id' => $auth['user_id'],
            'X-Session-Id' => $auth['session_id'],
        ]);
    }

    private function userPayload(User $user): array
    {
        $person = $user->person;

        return [
            'id' => $user->id,
            'person_id' => $user->person_id,
            'username' => $user->username,
            'is_it_admin' => $user->is_it_admin,
            'status' => $user->status,
            'must_change_password' => (bool) $user->must_change_password,
            'settings' => $user->settings ?? [],
            'person' => $person ? [
                'id' => $person->id,
                'first_name' => $person->first_name,
                'middle_name' => $person->middle_name,
                'last_name' => $person->last_name,
                'name_suffix' => $person->name_suffix,
                'gender' => $person->gender,
                'email' => $person->email,
                'display_name' => $person->display_name,
                'preferences' => $person->preferences ?? [],
                'types' => $person->types->pluck('type')->all(),
                'student_number' => $person->student?->student_number,
            ] : null,
        ];
    }
}