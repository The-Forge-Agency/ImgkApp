<?php

use App\Support\ImgkException;
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
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Les erreurs métier du proxy sortent en JSON clair, jamais en page HTML.
        $exceptions->render(function (ImgkException $e, Request $request) {
            return response()->json(
                ['error' => $e->getMessage()],
                $e->status,
                ['Cache-Control' => 'no-store', 'Access-Control-Allow-Origin' => '*'],
            );
        });
    })->create();
