<?php

namespace App\Livewire\App\Users;

use App\Console\Commands\LanInfoCommand;
use App\Domain\Audit\AuditLogger;
use App\Domain\Notifications\CompanyNotificationDispatcher;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Utilisateurs & Rôles - DocPilot')]
class Index extends Component
{
    public bool $showInviteModal = false;
    public string $invite_email = '';
    public string $invite_role = 'manager';

    public ?string $toastMessage = null;
    public ?string $lastInviteUrl = null;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function sendInvite(AuditLogger $audit, CompanyNotificationDispatcher $notifications): void
    {
        $this->authorize('create', User::class);

        $company = auth()->user()->company;
        $plan = config("docpilot.plans.{$company->subscription_plan}");
        $maxUsers = (int) ($company->setting('limits.max_users') ?? $plan['max_users'] ?? 5);

        $currentCount = User::where('company_id', $company->id)->where('status', UserStatus::Active)->count()
            + UserInvitation::whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->count();

        if ($currentCount >= $maxUsers) {
            $this->addError('invite_email', "La limite de {$maxUsers} utilisateurs de votre offre est atteinte.");

            return;
        }

        $roleValue = match ($this->invite_role) {
            'administrateur', 'admin' => Role::Admin->value,
            'lecteur', 'viewer' => Role::Viewer->value,
            default => Role::Manager->value,
        };

        $this->validate([
            'invite_email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->where('company_id', $company->id),
            ],
        ], [
            'invite_email.required' => 'L\'adresse e-mail est obligatoire.',
            'invite_email.email' => 'L\'adresse e-mail n\'est pas valide.',
            'invite_email.unique' => 'Cet utilisateur fait déjà partie de votre entreprise.',
        ]);

        // Révoque les anciennes invitations en attente pour ce même e-mail
        UserInvitation::query()
            ->where('email', strtolower(trim($this->invite_email)))
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $invitation = new UserInvitation([
            'email' => strtolower(trim($this->invite_email)),
            'role' => Role::from($roleValue),
            'invited_by' => auth()->id(),
            'expires_at' => now()->addDays(7),
        ]);
        $plainToken = $invitation->issueToken();
        $invitation->save();

        $this->lastInviteUrl = LanInfoCommand::publicBaseUrl().route('invitation.accept', ['token' => $plainToken], false);

        $audit->log('user.invited', $invitation, [
            'email' => $invitation->email,
            'role' => $invitation->role->value,
        ]);

        $notifications->logExternalMail($company, 'user_invited', $invitation->email, $invitation);
        $notifications->notifyInternal(
            company: $company,
            eventKey: 'user_invited',
            title: 'Nouvel utilisateur invité',
            text: "{$invitation->email} a été invité en tant que {$invitation->role->label()}.",
            icon: 'user',
            color: 'blue',
            subject: $invitation,
        );

        $this->showInviteModal = false;
        $this->toastMessage = "Invitation envoyée à {$invitation->email}.";
        $this->reset(['invite_email']);
    }

    public function updateRole(int $userId, string $newRole): void
    {
        $targetUser = User::query()
            ->where('company_id', auth()->user()->company_id)
            ->findOrFail($userId);
        $this->authorize('update', $targetUser);

        $normalizedRole = match ($newRole) {
            'administrateur', 'admin' => Role::Admin->value,
            'gestionnaire', 'manager' => Role::Manager->value,
            'lecteur', 'viewer' => Role::Viewer->value,
            default => $newRole,
        };

        if ($targetUser->role === Role::Admin && $normalizedRole !== Role::Admin->value) {
            $this->authorize('demote', $targetUser);
            $adminCount = User::where('company_id', auth()->user()->company_id)
                ->where('role', Role::Admin)
                ->where('status', UserStatus::Active)
                ->count();

            if ($adminCount <= 1) {
                abort(403, 'Le dernier administrateur de l\'entreprise ne peut pas être rétrogradé.');
            }
        }

        $oldRole = $targetUser->role->value;
        $targetUser->update(['role' => $normalizedRole]);

        app(AuditLogger::class)->log('user.role_updated', $targetUser, [
            'from' => $oldRole,
            'to' => $normalizedRole,
        ]);

        $this->toastMessage = "Rôle de {$targetUser->full_name} mis à jour.";
    }

    public function toggleUserStatus(int $userId, AuditLogger $audit): void
    {
        $targetUser = User::query()
            ->where('company_id', auth()->user()->company_id)
            ->findOrFail($userId);
        $this->authorize('update', $targetUser);

        if ($targetUser->status === UserStatus::Active) {
            if ($targetUser->role === Role::Admin) {
                $adminCount = User::where('company_id', auth()->user()->company_id)
                    ->where('role', Role::Admin)
                    ->where('status', UserStatus::Active)
                    ->count();

                if ($adminCount <= 1) {
                    abort(403, 'Le dernier administrateur actif ne peut pas être désactivé.');
                }
            }

            $targetUser->update([
                'status' => UserStatus::Deactivated,
                'deactivated_at' => now(),
            ]);
            $audit->log('user.deactivated', $targetUser);
            $this->toastMessage = "Compte de {$targetUser->full_name} désactivé.";
        } else {
            $targetUser->update([
                'status' => UserStatus::Active,
                'deactivated_at' => null,
            ]);
            $audit->log('user.reactivated', $targetUser);
            $this->toastMessage = "Compte de {$targetUser->full_name} réactivé.";
        }
    }

    public function resendInvitation(int $invitationId, AuditLogger $audit, CompanyNotificationDispatcher $notifications): void
    {
        $this->authorize('create', User::class);

        $invitation = UserInvitation::findOrFail($invitationId);
        $plainToken = $invitation->issueToken();
        $invitation->expires_at = now()->addDays(7);
        $invitation->revoked_at = null;
        $invitation->save();

        $this->lastInviteUrl = LanInfoCommand::publicBaseUrl().route('invitation.accept', ['token' => $plainToken], false);
        $notifications->logExternalMail(auth()->user()->company, 'user_invitation_resent', $invitation->email, $invitation);
        $audit->log('user.invitation_resent', $invitation, ['email' => $invitation->email]);

        $this->toastMessage = "Invitation renvoyée à {$invitation->email}.";
    }

    public function revokeInvitation(int $invitationId, AuditLogger $audit): void
    {
        $this->authorize('create', User::class);

        $invitation = UserInvitation::findOrFail($invitationId);
        $invitation->update(['revoked_at' => now()]);
        $audit->log('user.invitation_revoked', $invitation, ['email' => $invitation->email]);

        $this->toastMessage = "Invitation pour {$invitation->email} supprimée.";
    }

    public function deleteUser(int $userId, AuditLogger $audit): void
    {
        $targetUser = User::query()
            ->where('company_id', auth()->user()->company_id)
            ->findOrFail($userId);
        $this->authorize('delete', $targetUser);

        if ($targetUser->role === Role::Admin) {
            $adminCount = User::where('company_id', auth()->user()->company_id)
                ->where('role', Role::Admin)
                ->count();

            if ($adminCount <= 1) {
                abort(403, 'Le dernier administrateur de l\'entreprise ne peut pas être supprimé.');
            }
        }

        $name = $targetUser->full_name;
        $audit->log('user.deleted', $targetUser, ['email' => $targetUser->email]);
        $targetUser->delete();

        $this->toastMessage = "Utilisateur {$name} supprimé.";
    }

    public function exportCsv()
    {
        $this->authorize('viewAny', User::class);

        $rows = $this->buildUserRows();
        $filename = 'utilisateurs-'.Str::slug(auth()->user()->company->name).'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Nom', 'E-mail', 'Rôle', 'Statut', 'Dernière connexion']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['name'], $row['email'], $row['role'], $row['status'], $row['last_login']]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render()
    {
        return view('livewire.app.users.index', [
            'users' => $this->buildUserRows(),
        ]);
    }

    private function buildUserRows(): array
    {
        $companyId = auth()->user()->company_id;

        $dbUsers = User::query()
            ->where('company_id', $companyId)
            ->orderBy('id')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'type' => 'user',
                'name' => $u->full_name ?: $u->email,
                'email' => $u->email,
                'role' => $u->role?->label() ?? 'Gestionnaire',
                'role_value' => $u->role?->value ?? 'manager',
                'status' => $u->status === UserStatus::Active && $u->deactivated_at === null ? 'Actif' : 'Désactivé',
                'is_active' => $u->status === UserStatus::Active && $u->deactivated_at === null,
                'last_login' => $u->last_login_at ? $u->last_login_at->diffForHumans() : 'Jamais connecté',
                'avatar' => strtoupper(mb_substr($u->first_name ?: $u->email, 0, 1).mb_substr($u->last_name ?: '', 0, 1)) ?: 'U',
            ]);

        $invitations = UserInvitation::query()
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (UserInvitation $inv) => [
                'id' => $inv->id,
                'type' => 'invitation',
                'name' => Str::before($inv->email, '@').' (Invité)',
                'email' => $inv->email,
                'role' => $inv->role?->label() ?? 'Gestionnaire',
                'role_value' => $inv->role?->value ?? 'manager',
                'status' => $inv->expires_at->isPast() ? 'Invitation expirée' : 'Invitation en attente',
                'is_active' => false,
                'last_login' => '-',
                'avatar' => strtoupper(mb_substr($inv->email, 0, 2)),
            ]);

        return $dbUsers->concat($invitations)->values()->all();
    }
}
