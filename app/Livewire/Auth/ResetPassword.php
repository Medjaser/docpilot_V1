<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Nouveau mot de passe - DocPilot')]
class ResetPassword extends Component
{
    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $resetSuccess = false;

    public function mount(string $token = ''): void
    {
        $this->token = $token;
        $this->email = request()->query('email', '');
    }

    public function resetPassword(): void
    {
        $this->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|same:password_confirmation',
        ], [
            'password.same' => 'Les mots de passe ne correspondent pas.',
        ]);

        $status = Password::reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            $this->resetSuccess = true;
        } elseif (app()->environment('local')) {
            // Mode Dev Local : permet de réinitialiser directement même en cas de token désynchronisé
            $user = User::where('email', $this->email)->first();
            if ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                DB::table('password_reset_tokens')->where('email', $this->email)->delete();

                event(new PasswordReset($user));
                $this->resetSuccess = true;
                return;
            }

            $this->addError('email', __($status));
        } else {
            $this->addError('email', __($status));
        }
    }

    public function render()
    {
        return view('livewire.auth.reset-password');
    }
}
