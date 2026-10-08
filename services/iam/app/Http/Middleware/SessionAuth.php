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
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->attributes->set('auth', $auth);

        return $next($request);
    }
}
