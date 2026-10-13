<?php

use App\Http\Middleware\ReadOnlyCalendar;
use App\Http\Middleware\StandaloneModulePreview;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Run before CSRF so calendar write attempts are consistently forbidden.
        $middleware->prepend(ReadOnlyCalendar::class);
        $middleware->prependToPriorityList(
            AuthenticatesRequests::class,
            StandaloneModulePreview::class,
        );
        $middleware->redirectGuestsTo('/login'); // Owned by the main system after integration.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
