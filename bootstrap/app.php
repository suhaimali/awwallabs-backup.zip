<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\ClearFinanceLocks::class,
        ]);
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Database server unreachable: show a friendly "temporarily unavailable" page (or JSON for AJAX)
        // instead of a stack trace. Browser-side "no internet" is handled by public/sw.js + offline.html.
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if (!($e instanceof \Illuminate\Database\QueryException) && !($e instanceof \PDOException)) {
                return null;
            }
            $message = $e->getMessage();
            $connectionLost = in_array((int) ($e->errorInfo[1] ?? $e->getCode()), [2002, 2003, 2006, 2013], true)
                || str_contains($message, 'Connection refused')
                || str_contains($message, 'actively refused it')
                || str_contains($message, 'server has gone away')
                || str_contains($message, 'No such file or directory');
            if (!$connectionLost) {
                return null;
            }

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'The lab server is temporarily unavailable. Please try again in a moment.',
                ], 503, ['Retry-After' => '15']);
            }

            return response()->view('errors.offline', [], 503, ['Retry-After' => '15']);
        });
    })->create();
