<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireItAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $auth = $request->attributes->get('auth');

        if (! is_array($auth) || empty($auth['user_id'])) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = User::query()->find($auth['user_id']);

        if (! $user || ! $user->is_it_admin || $user->status !== 'active') {
            return response()->json([
                'message' => 'Forbidden. Concord Administrator access required.',
            ], 403);
        }

        return $next($request);
    }
}