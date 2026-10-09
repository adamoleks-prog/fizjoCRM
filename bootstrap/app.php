<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Services\Monitoring\ErrorTracker;
use App\Services\Monitoring\SecurityResponses;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Real faults (not 404s or validation) — kept in the panel and e-mailed.
        $exceptions->report(fn (Throwable $e) => app(ErrorTracker::class)->capture($e));

        // Refused requests — scanners, someone else's records, admin pages, limits.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            app(SecurityResponses::class)->inspect($response, $e, $request);

            return $response;
        });
    })->create();
