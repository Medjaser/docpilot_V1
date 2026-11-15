<?php

use App\Http\Middleware\EnsureCompanyIsActive;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\SetCurrentCompany;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Isolation multi-tenant : appliqué au groupe de routes /app (routes/web.php)
        $middleware->alias([
            'company' => SetCurrentCompany::class,
            'company.active' => EnsureCompanyIsActive::class,
            'platform.admin' => EnsurePlatformAdmin::class,
        ]);

        // L'entreprise doit être connue AVANT la liaison implicite des modèles dans l'URL,
        // sinon {subcontractor} serait résolu sans le filtre CompanyScope.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: SetCurrentCompany::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
