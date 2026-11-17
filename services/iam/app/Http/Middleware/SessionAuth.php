<?php

namespace App\Http\Middleware;

use App\Services\SessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SessionAuth
{
    public function __construct(private SessionService $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $auth = $this->sessions->resolve($request->cookie(SessionService::COOKIE));

        if (! $auth) {
            $revocationReason = $this->sessions->lifecycleRevocationReason(
                $request->cookie(SessionService::COOKIE)
            );

            if ($revocationReason === 'account_disabled') {
                return response()->json([
                    'message' => 'Your account has been disabled. Please contact your system administrator.',
                    'code' => 'ACCOUNT_DISABLED',
                ], 401);
            }

            if ($revocationReason === 'account_deactivated') {
                return response()->json([
                    'message' => 'Your account has been deactivated. Please contact your system administrator.',
                    'code' => 'ACCOUNT_DEACTIVATED',
                ], 401);
            }

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->attributes->set('auth', $auth);

        if ($auth['must_change_password'] ?? false) {
            $allowedRequests = (
                $request->isMethod('post')
                && (
                    $request->is('api/iam/me/password')
                    || $request->is('api/iam/logout')
                )
            );

            if (! $allowedRequests) {
                return response()->json([
                    'message' => 'You must change your password before accessing this resource.',
                    'code' => 'PASSWORD_CHANGE_REQUIRED',
                ], 403);
            }
        }

        return $next($request);
    }
}