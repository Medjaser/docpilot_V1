<?php

namespace App\Livewire\Auth;

use App\Domain\Audit\AuditLogger;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Rejoindre l\'espace entreprise - DocPilot')]
class AcceptInvitation extends Component
{
    #[Locked]
    public string $token = '';

    #[Url]
    public bool $expired = false;

    public string $company_name = 'BatiNova Rénovation';
    public string $inviter_name = 'Sophie Martin';
    public string $role_label = 'Gestionnaire';
    public string $email = 'marc.dubois@batinova.fr';

    public string $first_name = '';
    public string $last_name = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(string $token = ''): void
    {
        $this->token = $token;
        if ($token === 'expired') {
            $this->expired = true;

            return;
        }

        $invitation = UserInvitation::findByToken($token);
        if ($invitation) {
            $company = Company::find($invitation->company_id);
            $inviter = $invitation->inviter;

            $this->company_name = $company?->name ?? $this->company_name;
            $this->inviter_name = $inviter?->full_name ?? $this->inviter_name;
            $this->role_label = $invitation->role?->label() ?? $this->role_label;
            $this->email = $invitation->email;
            $this->expired = ! $invitation->isPending();
        }
    }

    public function accept(AuditLogger $audit)
    {
        if ($this->expired) {
            return;
        }

        $this->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'password' => 'required|string|min:8|same:password_confirmation',
        ], [
            'password.same' => 'Les mots de passe ne correspondent pas.',
        ]);

        $invitation = UserInvitation::findByToken($this->token);

        if ($invitation) {
            if (! $invitation->isPending()) {
                $this->expired = true;

                return;
            }

            $user = null;
            DB::transaction(function () use ($invitation, &$user) {
                $user = User::create([
                    'company_id' => $invitation->company_id,
                    'first_name' => trim($this->first_name),
                    'last_name' => trim($this->last_name),
                    'email' => $invitation->email,
                    'password' => $this->password,
                    'role' => $invitation->role ?? Role::Manager,
                    'status' => UserStatus::Active,
                    'last_login_at' => now(),
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();

                $invitation->update(['accepted_at' => now()]);
            });

            Auth::login($user);
            $audit->log('user.invitation_accepted', $user, ['invitation_id' => $invitation->id], 'user', $user->id);
        }

        return redirect()->route('dashboard');
    }

    public function render()
    {
        return view('livewire.auth.accept-invitation');
    }
}
