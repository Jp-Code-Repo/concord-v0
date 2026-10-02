<?php

use App\Http\Middleware\SessionAuth;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['session.auth' => SessionAuth::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // IAM is an API: always answer in JSON.
        $exceptions->shouldRenderJsonWhen(fn ($request, $e) => true);
    })->create();
