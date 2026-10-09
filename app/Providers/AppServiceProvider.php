<?php

namespace App\Providers;

use App\Domain\Documents\DocumentStatusResolver;
use App\Domain\Documents\ExpirationAlertSchedule;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Une instance par requête / job
        $this->app->scoped(CurrentCompany::class);

        // Règles métier configurées dans config/docpilot.php
        $this->app->bind(DocumentStatusResolver::class, fn () => new DocumentStatusResolver(
            (int) config('docpilot.documents.expiring_soon_days'),
        ));
        $this->app->bind(ExpirationAlertSchedule::class, fn () => new ExpirationAlertSchedule(
            config('docpilot.documents.expiration_alert_days'),
        ));
    }

    public function boot(): void
    {
        // En développement : erreur si on oublie un eager loading ou un $fillable
        Model::shouldBeStrict(! $this->app->isProduction());

        // Protection contre les tentatives de connexion répétées (§9.1)
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });

        // Portail externe de dépôt : limite par IP
        RateLimiter::for('portal', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });
    }
}
