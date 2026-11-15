<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Vérification de l\'e-mail - DocPilot')]
class VerifyEmail extends Component
{
    public string $email = '';
    public bool $resent = false;
    public ?string $verificationUrl = null;
    public ?string $feedbackMessage = null;
    public string $feedbackType = 'success';
    public ?string $feedbackTitle = null;

    public function mount()
    {
        $user = Auth::user();
        if ($user) {
            if ($user->hasVerifiedEmail()) {
                return redirect()->route('dashboard');
            }

            $this->email = $user->email;
            if (app()->environment('local')) {
                $this->verificationUrl = URL::temporarySignedRoute(
                    'verification.verify',
                    now()->addMinutes(60),
                    ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())]
                );
            }
        }
    }

    public function verifyDirectly()
    {
        if (! app()->environment('local')) {
            abort(403);
        }

        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new \Illuminate\Auth\Events\Verified($user));
        }

        return redirect()->route('dashboard');
    }

    public function resend(): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        if ($user->hasVerifiedEmail()) {
            $this->redirect(route('dashboard'));
            return;
        }

        $throttleKey = 'verify-email-resend:'.$user->id;

        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($throttleKey, 1)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($throttleKey);
            $this->feedbackType = 'warning';
            $this->feedbackTitle = 'Veuillez patienter';
            $this->feedbackMessage = "Veuillez patienter encore {$seconds} seconde(s) avant de demander un nouvel envoi.";
            $this->resent = false;
            return;
        }

        try {
            $user->sendEmailVerificationNotification();
            \Illuminate\Support\Facades\RateLimiter::hit($throttleKey, 60);

            $this->feedbackType = 'success';
            $this->feedbackTitle = 'Nouveau lien envoyé';
            $this->feedbackMessage = 'Un nouveau lien de vérification a été transmis à votre adresse e-mail.';
            $this->resent = true;
        } catch (\Throwable $e) {
            logger()->error('Impossible d\'envoyer l\'e-mail de vérification : '.$e->getMessage(), [
                'user_id' => $user->id,
                'email' => $user->email,
                'exception' => $e,
            ]);

            $this->feedbackType = 'error';
            $this->feedbackTitle = 'Échec de l\'envoi';
            $this->feedbackMessage = 'Une erreur est survenue lors de l\'envoi de l\'e-mail via le serveur SMTP. Veuillez réessayer dans quelques instants.';
            $this->resent = false;
        }
    }

    public function logout()
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function render()
    {
        return view('livewire.auth.verify-email');
    }
}
