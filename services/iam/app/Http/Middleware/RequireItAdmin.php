<?php

namespace App\Http\Middleware;

use App\Services\PlatformAdministratorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireItAdmin
{
    public function __construct(
        private PlatformAdministratorService $platformAdministrators,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $auth = $request->attributes->get('auth');

        if (! is_array($auth) || empty($auth['user_id'])) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $this->platformAdministrators->isDesignated((string) $auth['user_id'])) {
            return response()->json([
                'message' => 'Forbidden. Concord Administrator access required.',
            ], 403);
        }

        return $next($request);
    }
}