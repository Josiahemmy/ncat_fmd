<?php

use App\Exceptions\DomainRefusal;
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
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\EnsurePasswordChanged::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Business-rule refusals are not faults: a clerk closing a draft order
        // or issuing more than is on hand has hit a rule the engine is meant to
        // enforce. Without this they surfaced as HTTP 500 with nothing on
        // screen, so the refusal read as the app being broken. Line-specific
        // refusals throw ValidationException instead and render against their
        // field; these are document-level and flash to a toast.
        $exceptions->render(function (DomainRefusal $e, Request $request) {
            if ($request->header('X-Inertia')) {
                return back()->with('error', $e->getMessage());
            }

            return response($e->getMessage(), 422);
        });
    })->create();
