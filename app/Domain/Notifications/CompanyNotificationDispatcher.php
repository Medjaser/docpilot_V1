<?php

namespace App\Domain\Notifications;

use App\Domain\Company\CompanySettings;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\NotificationLog;
use App\Models\Subcontractor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Envoie les notifications (dans l'application et par e-mail tracé) selon les préférences
 * de l'entreprise et garantit l'absence de doublons via notification_logs.dedup_key (§9.15, §9.16).
 */
final class CompanyNotificationDispatcher
{
    /**
     * Notifie les utilisateurs internes de l'entreprise selon la matrice de préférences.
     */
    public function notifyInternal(
        Company $company,
        string $eventKey,
        string $title,
        string $text,
        string $icon = 'bell',
        string $color = 'blue',
        ?Subcontractor $subcontractor = null,
        ?Model $subject = null,
        ?string $dedupKey = null,
    ): bool {
        if ($dedupKey !== null && NotificationLog::acrossAllCompanies()->where('dedup_key', $dedupKey)->exists()) {
            return false;
        }

        $preferences = CompanySettings::for($company)->notifications()[$eventKey] ?? [
            'email' => true,
            'app' => true,
            'recipients' => 'owner',
        ];

        $recipients = $this->resolveRecipients($company, $preferences['recipients'] ?? 'owner', $subcontractor);

        if ($recipients->isEmpty()) {
            return false;
        }

        if (! empty($preferences['app'])) {
            foreach ($recipients as $user) {
                $user->notifications()->create([
                    'id' => (string) Str::uuid(),
                    'company_id' => $company->id,
                    'type' => $eventKey,
                    'data' => [
                        'title' => $title,
                        'body' => $text,
                        'text' => $text,
                        'icon' => $icon,
                        'color' => $color,
                    ],
                ]);
            }
        }

        $effectiveDedupKey = $dedupKey
            ? Str::limit($dedupKey, 190, '')
            : Str::limit("{$eventKey}:c{$company->id}:".Str::uuid(), 190, '');

        if (! empty($preferences['email'])) {
            NotificationLog::create([
                'company_id' => $company->id,
                'channel' => 'mail',
                'type' => $eventKey,
                'recipient' => Str::limit($recipients->pluck('email')->implode(','), 250, ''),
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'dedup_key' => $effectiveDedupKey,
                'sent_at' => now(),
            ]);
        } elseif ($dedupKey !== null) {
            NotificationLog::create([
                'company_id' => $company->id,
                'channel' => 'database',
                'type' => $eventKey,
                'recipient' => Str::limit($recipients->pluck('email')->implode(','), 250, ''),
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'dedup_key' => $effectiveDedupKey,
                'sent_at' => now(),
            ]);
        }

        return true;
    }

    /**
     * Alias compatible pour notifier les utilisateurs de l'entreprise (in-app + log e-mail).
     */
    public function notifyCompanyUsers(
        Company $company,
        string $type,
        string $title,
        string $body,
        array $context = [],
        string $preferenceKey = 'uploaded',
        ?string $dedupKey = null,
    ): bool {
        $icon = match ($preferenceKey) {
            'uploaded' => 'document',
            'expiring', 'expired' => 'exclamation-triangle',
            'rejected' => 'x-circle',
            default => 'bell',
        };
        $color = match ($preferenceKey) {
            'uploaded' => 'purple',
            'expiring' => 'amber',
            'expired', 'rejected' => 'red',
            default => 'blue',
        };

        $subcontractor = isset($context['subcontractor_id'])
            ? Subcontractor::acrossAllCompanies()->find($context['subcontractor_id'])
            : null;

        return $this->notifyInternal(
            company: $company,
            eventKey: $preferenceKey,
            title: $title,
            text: $body,
            icon: $icon,
            color: $color,
            subcontractor: $subcontractor,
            subject: $subcontractor,
            dedupKey: $dedupKey,
        );
    }

    /**
     * Enregistre l'envoi d'un e-mail externe (au sous-traitant ou collaborateur invité) avec anti-doublon.
     */
    public function logExternalMail(
        Company $company,
        string $type,
        string $recipientEmail,
        ?Model $subject = null,
        ?string $dedupKey = null,
    ): bool {
        if ($dedupKey !== null && NotificationLog::acrossAllCompanies()->where('dedup_key', $dedupKey)->exists()) {
            return false;
        }

        $effectiveDedupKey = $dedupKey
            ? Str::limit($dedupKey, 190, '')
            : Str::limit("{$type}:c{$company->id}:".($subject?->getKey() ?? '0').':'.Str::uuid(), 190, '');

        NotificationLog::create([
            'company_id' => $company->id,
            'channel' => 'mail',
            'type' => $type,
            'recipient' => Str::limit($recipientEmail, 250, ''),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'dedup_key' => $effectiveDedupKey,
            'sent_at' => now(),
        ]);

        return true;
    }

    /**
     * Envoie/trace un e-mail adressé à un sous-traitant avec variables dynamiques et anti-doublon.
     */
    public function sendEmailToSubcontractor(
        Company $company,
        Subcontractor $subcontractor,
        string $type,
        array $variables = [],
        ?string $dedupKey = null,
    ): bool {
        if (! $subcontractor->contact_email) {
            return false;
        }

        return $this->logExternalMail(
            company: $company,
            type: $type,
            recipientEmail: $subcontractor->contact_email,
            subject: $subcontractor,
            dedupKey: $dedupKey,
        );
    }

    /**
     * Remplace les variables dynamiques dans le modèle d'e-mail de l'entreprise (§9.16).
     *
     * @param  list<string>  $missingDocuments
     */
    public function renderRequestEmail(
        Company $company,
        Subcontractor $subcontractor,
        array $missingDocuments,
        string $depositUrl,
        string $dueDateFormatted,
        ?User $manager = null,
    ): array {
        $settings = CompanySettings::for($company);
        $subjectTemplate = (string) $settings->get('request_email.subject', 'Documents administratifs requis pour {nom_entreprise}');
        $bodyTemplate = (string) $settings->get('request_email.body', '');

        $managerInfo = $manager
            ? "{$manager->full_name} ({$manager->email})"
            : ($company->name.' ('.$company->email.')');

        $replacements = [
            '{nom_entreprise}' => $company->trade_name ?: $company->name,
            '{nom_sous_traitant}' => $subcontractor->legal_name,
            '{nom_contact}' => $subcontractor->contact_name ?: $subcontractor->legal_name,
            '{documents_manquants}' => $missingDocuments !== [] ? '- '.implode("\n- ", $missingDocuments) : '- Dossier administratif complet',
            '{date_limite}' => $dueDateFormatted,
            '{lien_depot}' => $depositUrl,
            '{coordonnees_gestionnaire}' => $managerInfo,
        ];

        return [
            'subject' => strtr($subjectTemplate, $replacements),
            'body' => strtr($bodyTemplate, $replacements),
        ];
    }

    /** @return Collection<int, User> */
    private function resolveRecipients(Company $company, string $recipientGroup, ?Subcontractor $subcontractor): Collection
    {
        $baseQuery = User::query()
            ->where('company_id', $company->id)
            ->where('status', UserStatus::Active);

        if ($recipientGroup === 'owner' && $subcontractor?->internal_owner_id) {
            $owner = (clone $baseQuery)->whereKey($subcontractor->internal_owner_id)->get();
            if ($owner->isNotEmpty()) {
                return $owner;
            }
        }

        if ($recipientGroup === 'admins') {
            return (clone $baseQuery)->where('role', Role::Admin)->get();
        }

        return (clone $baseQuery)->whereIn('role', [Role::Admin, Role::Manager])->get();
    }
}
