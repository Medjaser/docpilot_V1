<?php

namespace App\Livewire\Auth;

use App\Domain\Audit\AuditLogger;
use App\Enums\UserStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Connexion à votre espace - DocPilot')]
class Login extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;
    public bool $failed = false;
    public ?string $errorMessage = null;

    protected function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required|string',
        ];
    }

    public function login()
    {
        $this->validate();
        $this->failed = false;
        $this->errorMessage = null;

        $throttleKey = Str::transliterate(Str::lower($this->email)).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->errorMessage = "Trop de tentatives de connexion. Veuillez réessayer dans {$seconds} secondes.";
            $this->addError('email', $this->errorMessage);
            return;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey, 60);

            app(AuditLogger::class)->log(
                action: 'login_failed',
                entity: null,
                metadata: ['email' => $this->email, 'reason' => 'invalid_credentials'],
                userType: 'user'
            );

            $this->failed = true;
            $this->errorMessage = 'Identifiants incorrects.';
            $this->addError('email', $this->errorMessage);
            return;
        }

        $user = Auth::user();

        if ($user->status === UserStatus::Deactivated || $user->deactivated_at !== null) {
            Auth::logout();
            RateLimiter::hit($throttleKey, 60);

            app(AuditLogger::class)->log(
                action: 'login_failed',
                entity: $user,
                metadata: ['email' => $this->email, 'reason' => 'user_deactivated'],
                userType: 'user',
                userId: $user->id
            );

            $this->failed = true;
            $this->errorMessage = 'Votre compte a été désactivé.';
            $this->addError('email', $this->errorMessage);
            return;
        }

        if (! $user->isPlatformAdmin() && $user->company) {
            $company = $user->company;

            // Une entreprise suspendue garde un accès en lecture seule
            if (! $company->status->canAccessApp()) {
                Auth::logout();
                RateLimiter::hit($throttleKey, 60);

                app(AuditLogger::class)->log(
                    action: 'login_failed',
                    entity: $company,
                    metadata: ['email' => $this->email, 'reason' => 'company_inactive', 'status' => $company->status->value],
                    userType: 'user',
                    userId: $user->id
                );

                $this->failed = true;
                $this->errorMessage = 'Le compte de votre entreprise est résilié.';
                $this->addError('email', $this->errorMessage);
                return;
            }
        }

        RateLimiter::clear($throttleKey);
        if (request()->hasSession()) {
            request()->session()->regenerate();
        }
        $user->update(['last_login_at' => now()]);

        app(AuditLogger::class)->log(
            action: 'login',
            entity: $user,
            userType: 'user',
            userId: $user->id
        );

        if ($user->isPlatformAdmin()) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return redirect()->intended(route('dashboard'));
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
