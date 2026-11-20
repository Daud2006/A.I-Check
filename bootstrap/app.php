<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsNotBanned;
use App\Http\Middleware\RecordVisitor;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('auth'));
        $middleware->validateCsrfTokens(except: ['payments/midtrans/notifications']);
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'not-banned' => EnsureUserIsNotBanned::class,
        ]);
        $middleware->append(RecordVisitor::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
