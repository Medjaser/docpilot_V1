<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Mot de passe oublié - DocPilot')]
class ForgotPassword extends Component
{
    public string $email = '';
    public bool $sent = false;
    public ?string $devResetUrl = null;

    public function sendResetLink(): void
    {
        $this->validate([
            'email' => 'required|email',
        ]);

        $this->resetErrorBag();
        $this->devResetUrl = null;

        try {
            $status = Password::sendResetLink(['email' => $this->email], function ($user, $token) {
                if (app()->environment('local', 'testing')) {
                    $this->devResetUrl = route('password.reset', ['token' => $token, 'email' => $this->email]);
                }

                $user->sendPasswordResetNotification($token);

                return Password::RESET_LINK_SENT;
            });

            if ($status === Password::RESET_LINK_SENT) {
                $this->sent = true;
            } elseif ($status === Password::RESET_THROTTLED) {
                $this->addError('email', __('passwords.throttled'));
            } else {
                $this->addError('email', __($status));
            }
        } catch (\Throwable $e) {
            // Nettoyer le jeton pour ne pas bloquer l'utilisateur avec le throttle en cas d'échec d'envoi
            DB::table('password_reset_tokens')
                ->where('email', $this->email)
                ->delete();

            logger()->error('Impossible d\'envoyer l\'e-mail de réinitialisation de mot de passe : '.$e->getMessage(), [
                'email' => $this->email,
                'exception' => $e,
            ]);

            $this->addError('email', 'Échec de l\'envoi de l\'e-mail (erreur SMTP). Veuillez vérifier la configuration de messagerie.');
        }
    }

    public function resetDirectly()
    {
        if (! app()->environment('local', 'testing')) {
            abort(403);
        }

        $this->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $this->email)->first();

        if (! $user) {
            $this->addError('email', __('passwords.user'));
            return;
        }

        // Créer un jeton valide directement
        $token = Password::broker()->createToken($user);

        return redirect()->route('password.reset', [
            'token' => $token,
            'email' => $this->email,
        ]);
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}
