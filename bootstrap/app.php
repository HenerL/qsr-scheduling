<?php

use App\Helpers\QueryResultHelperV2;
use Illuminate\Auth\AuthenticationException;
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
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn () => null);
        $middleware->alias([
            'manager' => \App\Http\Middleware\EnsureManager::class,
            'schedule.board' => \App\Http\Middleware\EnsureScheduleBoardAccess::class,
        ]);
        $middleware->appendToGroup('api', \App\Http\Middleware\RefreshTokenOnActivity::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(
            fn ($request) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Request failed.',
                ], $e->getStatusCode());
            }

            return null;
        });

        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return QueryResultHelperV2::onUnauthorized('Your session has expired. Please log in again.');
            }

            return null;
        });
    })->create();
