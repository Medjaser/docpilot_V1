<?php

namespace App\Providers;

use App\Console\Commands\LanInfoCommand;
use App\Domain\Documents\DocumentStatusResolver;
use App\Domain\Documents\ExpirationAlertSchedule;
use App\Http\Middleware\EnsureCompanyIsActive;
use App\Http\Middleware\SetCurrentCompany;
use App\Models\Document;
use App\Models\DocumentRequest;
use App\Models\Subcontractor;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use App\Support\UploadLimitsChecker;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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

        // Valeurs stockées dans les colonnes polymorphes (uploaded_by_type, author_type, notifiable_type...)
        Relation::morphMap([
            'user' => User::class,
            'subcontractor' => Subcontractor::class,
            'document' => Document::class,
            'document_request' => DocumentRequest::class,
            'document_type' => DocumentType::class,
        ]);

        // Les requêtes Livewire (/livewire/update) ne passent pas par le groupe /app :
        // on y rejoue le middleware pour que les composants restent filtrés par entreprise.
        Livewire::addPersistentMiddleware([SetCurrentCompany::class, EnsureCompanyIsActive::class]);

        // Protection contre les tentatives de connexion répétées (§9.1)
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });

        // Portail externe de dépôt : limite de 30 requêtes/minute par jeton et par IP
        RateLimiter::for('portal', function (Request $request) {
            $token = (string) ($request->route('token') ?? $request->input('token', 'portal'));

            return Limit::perMinute(30)->by($token.'|'.$request->ip());
        });

        // En local, si APP_URL en mémoire pointe encore vers localhost/127.0.0.1 (ex: php artisan serve non redémarré),
        // on synchronise automatiquement config('app.url') sur l'IP LAN (.env / Wi-Fi) pour que les liens fonctionnent sur mobile.
        if (! $this->app->runningUnitTests()) {
            config(['app.url' => LanInfoCommand::publicBaseUrl()]);
        }

        // Limite d'upload temporaire Livewire alignée sur docpilot.max_upload_mb (10 Mo)
        $maxUploadKb = (int) config('docpilot.max_upload_mb', 10) * 1024;
        config(['livewire.temporary_file_upload.rules' => ['required', 'file', 'max:'.$maxUploadKb]]);

        // Vérification de upload_max_filesize et post_max_size
        $limits = UploadLimitsChecker::inspect();
        if (! $limits['ok'] && $limits['warning']) {
            Log::warning($limits['warning']);
        }

        // Personnalisation des e-mails en français
        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            $name = trim(($notifiable->first_name ?? '') . ' ' . ($notifiable->last_name ?? ''));
            $greeting = $name !== '' ? "Bonjour {$name}," : 'Bonjour,';

            return (new MailMessage)
                ->subject('Vérifiez votre adresse e-mail - DocPilot')
                ->greeting($greeting)
                ->line('Bienvenue sur DocPilot ! Merci d\'avoir créé votre compte.')
                ->line('Veuillez cliquer sur le bouton ci-dessous pour confirmer votre adresse e-mail et activer l\'accès à votre espace :')
                ->action('Confirmer mon adresse e-mail', $url)
                ->line('Ce lien de vérification expirera dans 60 minutes.')
                ->line('Si vous n\'avez pas créé de compte sur DocPilot, aucune action n\'est requise de votre part.')
                ->salutation('L\'équipe DocPilot');
        });

        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $name = trim(($notifiable->first_name ?? '') . ' ' . ($notifiable->last_name ?? ''));
            $greeting = $name !== '' ? "Bonjour {$name}," : 'Bonjour,';
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));
            $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

            return (new MailMessage)
                ->subject('Réinitialisation de votre mot de passe - DocPilot')
                ->greeting($greeting)
                ->line('Vous recevez cet e-mail car une demande de réinitialisation de mot de passe a été effectuée pour votre compte DocPilot.')
                ->action('Réinitialiser mon mot de passe', $url)
                ->line("Ce lien de réinitialisation expirera dans {$expire} minutes.")
                ->line('Si vous n\'avez pas demandé cette réinitialisation, vous pouvez ignorer cet e-mail en toute sécurité.')
                ->salutation('L\'équipe DocPilot');
        });
    }
}
