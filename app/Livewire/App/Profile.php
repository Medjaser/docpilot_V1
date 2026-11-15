<?php

namespace App\Livewire\App;

use App\Domain\Audit\AuditLogger;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Mon profil & Sécurité - DocPilot')]
class Profile extends Component
{
    public string $first_name = '';
    public string $last_name = '';
    public string $email = '';

    // Modification du mot de passe (§9.1)
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    // Confirmation pour révoquer les autres sessions
    public string $session_password = '';

    // Désactivation de compte
    public bool $showDeactivateModal = false;
    public string $deactivate_password = '';

    public ?string $toastMessage = null;

    public function mount(): void
    {
        $user = Auth::user();
        $this->first_name = (string) $user->first_name;
        $this->last_name = (string) $user->last_name;
        $this->email = (string) $user->email;
    }

    public function updateProfile(AuditLogger $audit): void
    {
        $user = Auth::user();
        abort_if($user->company?->isReadOnly(), 403);

        $this->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
        ]);

        $user->update([
            'first_name' => trim($this->first_name),
            'last_name' => trim($this->last_name),
        ]);

        $audit->log('user.profile_updated', $user);
        $this->toastMessage = 'Vos informations personnelles ont été mises à jour.';
    }

    public function updatePassword(AuditLogger $audit): void
    {
        $user = Auth::user();
        abort_if($user->company?->isReadOnly(), 403);

        $this->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'same:password_confirmation'],
        ], [
            'current_password.required' => 'Le mot de passe actuel est obligatoire.',
            'password.min' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.',
            'password.same' => 'Les mots de passe ne correspondent pas.',
        ]);

        if (! Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'Le mot de passe actuel est incorrect.');

            return;
        }

        $user->update([
            'password' => $this->password,
        ]);

        $audit->log('user.password_changed', $user);
        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->toastMessage = 'Votre mot de passe a été modifié avec succès.';
    }

    public function logoutOtherSessions(AuditLogger $audit): void
    {
        $user = Auth::user();

        if ($this->session_password !== '' && ! Hash::check($this->session_password, $user->password)) {
            $this->addError('session_password', 'Mot de passe incorrect.');

            return;
        }

        try {
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $user->id)
                    ->where('id', '!=', request()->session()->getId())
                    ->delete();
            }
        } catch (\Throwable) {
            // Table sessions absente selon l'environnement
        }

        $audit->log('user.sessions_revoked', $user);
        $this->reset('session_password');
        $this->toastMessage = 'Toutes vos autres sessions ont été déconnectées.';
    }

    public function deactivateOwnAccount(AuditLogger $audit)
    {
        $user = Auth::user();
        abort_if($user->company?->isReadOnly(), 403);

        if ($user->role === Role::Admin) {
            $activeAdmins = User::where('company_id', $user->company_id)
                ->where('role', Role::Admin)
                ->where('status', UserStatus::Active)
                ->count();

            if ($activeAdmins <= 1) {
                $this->addError('deactivate_password', 'Impossible de désactiver le dernier administrateur actif de l\'entreprise.');

                return;
            }
        }

        if (! Hash::check($this->deactivate_password, $user->password)) {
            $this->addError('deactivate_password', 'Mot de passe incorrect.');

            return;
        }

        $user->update([
            'status' => UserStatus::Deactivated,
            'deactivated_at' => now(),
        ]);

        $audit->log('user.self_deactivated', $user);

        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function render()
    {
        $sessions = collect();
        $user = Auth::user();

        try {
            if (config('session.driver') === 'database') {
                $sessions = DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $user->id)
                    ->orderByDesc('last_activity')
                    ->get()
                    ->map(fn ($s) => [
                        'id' => $s->id,
                        'ip_address' => $s->ip_address ?? '127.0.0.1',
                        'user_agent' => $s->user_agent ?? 'Navigateur Web',
                        'is_current' => $s->id === request()->session()->getId(),
                        'last_active' => \Carbon\Carbon::createFromTimestamp($s->last_activity)->diffForHumans(),
                    ]);
            }
        } catch (\Throwable) {
            // Fallback
        }

        if ($sessions->isEmpty()) {
            $sessions = collect([[
                'id' => request()->hasSession() ? request()->session()->getId() : 'current',
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'Session courante',
                'is_current' => true,
                'last_active' => 'Actif maintenant',
            ]]);
        }

        return view('livewire.app.profile', [
            'sessions' => $sessions,
        ]);
    }
}

