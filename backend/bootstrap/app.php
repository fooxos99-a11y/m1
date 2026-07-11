<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

$isApiRequest = static fn (Request $request): bool => $request->is('api/*')
    || $request->is('*/api/*')
    || str_contains($request->getRequestUri(), '/api/');

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) use ($isApiRequest): void {
        $middleware->redirectGuestsTo(function (Request $request) use ($isApiRequest): ?string {
            if ($isApiRequest($request)) {
                return null;
            }

            $basePath = trim($request->getBasePath(), '/');

            return $basePath === '' ? '/login' : "/{$basePath}/login";
        });

        $middleware->alias([
            'dashboard.access' => \App\Http\Middleware\EnsureDashboardAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) use ($isApiRequest): void {
        $exceptions->render(function (AuthenticationException $exception, Request $request) use ($isApiRequest) {
            if ($isApiRequest($request) || $request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return null;
        });
    })->create();
