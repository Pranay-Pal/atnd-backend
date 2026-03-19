<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Return JSON for API routes that throw unhandled exceptions
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                $status = method_exists($e, 'getStatusCode')
                    ? (int) $e->getStatusCode()
                    : 500;
                $isServerError = $status >= 500;
                $message = ($isServerError && !config('app.debug'))
                    ? 'Internal server error.'
                    : $e->getMessage();

                return response()->json([
                    'message' => $message,
                ], $status);
            }
        });
    })->create();
