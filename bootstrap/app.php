<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.token' => \App\Http\Middleware\VerifyApiToken::class,
            'query.optimization' => \App\Http\Middleware\QueryOptimizationMiddleware::class,
        ]);
        
        // 在API路由组中应用查询优化中间件
        $middleware->group('api', [
            \App\Http\Middleware\QueryOptimizationMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
