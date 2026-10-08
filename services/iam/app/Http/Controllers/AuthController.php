<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccessService;
use App\Services\SessionService;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /** Valid bcrypt hash, so unknown usernames cost the same time as wrong passwords. */
    private const DUMMY_HASH = '$2y$12$Jgr/PuTRueOHquXLYSXu6uQSyt3kpSEGRXP9oKNbJ6iUiHNkTjYhK';

    public function login(Request $request, SessionService $sessions): JsonResponse
    {
        $data = $request->validate([
            'username' => 'required|string|max:100',
            'password' => 'required|string|max:200',
        ]);

        $user = User::where('username', $data['username'])
            ->orWhere('email', $data['username'])
            ->first();

        $passwordOk = Hash::check($data['password'], $user?->password ?? self::DUMMY_HASH);

        // Same answer for unknown user, wrong password and disabled account.
        if (! $user || ! $passwordOk || $user->status !== 'active') {
            Audit::log('auth.login_failed', $user?->id, $request, meta: [
                'username' => mb_substr($data['username'], 0, 100),
                'reason' => ! $user ? 'unknown_user' : ($user->status !== 'active' ? 'disabled' : 'bad_password'),
            ]);

            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        [$token, $session] = $sessions->create($user, $request);
        Audit::log('auth.login', $user->id, $request, entityType: 'auth_session', entityId: $session->id);

        return response()
            ->json(['user' => $this->userPayload($user)])
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
        $sessions->revoke($auth['session_id'], 'logout');
        Audit::log('auth.logout', $auth['user_id'], $request);

        return response()
            ->json(null, 204)
            ->withCookie(cookie()->forget(SessionService::COOKIE));
    }

    public function me(Request $request, AccessService $access): JsonResponse
    {
        $auth = $request->attributes->get('auth');
        $user = User::findOrFail($auth['user_id']);

        return response()->json([
            'user' => $this->userPayload($user),
            'access' => $access->forUser($user->id, $user->is_it_admin),
        ]);
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
        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'display_name' => $user->display_name,
            'is_it_admin' => $user->is_it_admin,
        ];
    }
}
