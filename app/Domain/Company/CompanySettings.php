<?php

namespace App\Domain\Company;

use App\Models\Company;

/**
 * Paramètres d'une entreprise (colonne companies.settings).
 * Point d'entrée unique en lecture comme en écriture : les valeurs stockées sont
 * toujours fusionnées avec les valeurs par défaut, un nouvel événement apparaît donc
 * automatiquement pour les entreprises existantes.
 */
final class CompanySettings
{
    /** Événements notifiables, dans l'ordre d'affichage. */
    public const NOTIFICATION_EVENTS = [
        'new_upload' => 'Nouveau dépôt de document par un sous-traitant',
        'doc_validated' => 'Document validé',
        'doc_rejected' => 'Document rejeté',
        'doc_expiring' => 'Document bientôt expiré',
        'doc_expired' => 'Document expiré',
        'doc_missing' => 'Document manquant',
        'link_expired' => 'Lien de dépôt expiré',
        'reminder_sent' => 'Relance envoyée',
        'user_invited' => 'Nouvel utilisateur invité',
    ];

    public const RECIPIENTS = [
        'owner' => 'Responsable du sous-traitant',
        'managers' => 'Gestionnaires et administrateurs',
        'admins' => 'Administrateurs',
    ];

    private array $values;

    private function __construct(private readonly Company $company)
    {
        $this->values = self::merge(self::defaults(), $company->settings ?? []);
    }

    public static function for(Company $company): self
    {
        return new self($company);
    }

    public static function defaults(): array
    {
        return [
            'notifications' => [
                'new_upload' => ['email' => true, 'app' => true, 'recipients' => 'owner'],
                'doc_validated' => ['email' => false, 'app' => true, 'recipients' => 'owner'],
                'doc_rejected' => ['email' => true, 'app' => true, 'recipients' => 'owner'],
                'doc_expiring' => ['email' => true, 'app' => true, 'recipients' => 'owner'],
                'doc_expired' => ['email' => true, 'app' => true, 'recipients' => 'managers'],
                'doc_missing' => ['email' => false, 'app' => true, 'recipients' => 'owner'],
                'link_expired' => ['email' => false, 'app' => true, 'recipients' => 'owner'],
                'reminder_sent' => ['email' => false, 'app' => true, 'recipients' => 'owner'],
                'user_invited' => ['email' => true, 'app' => true, 'recipients' => 'admins'],
            ],
            'expiring_soon_days' => (int) config('docpilot.documents.expiring_soon_days', 30),
            'reminder_days' => config('docpilot.documents.expiration_alert_days', [60, 30, 15, 7, 0]),
            'request_email' => [
                'subject' => 'Documents administratifs requis pour {nom_entreprise}',
                'body' => "Bonjour {nom_contact},\n\nDans le cadre de nos interventions sur chantier, nous vous prions de bien vouloir mettre à jour les documents suivants :\n\n{documents_manquants}\n\nLien de dépôt sécurisé : {lien_depot}\nDate limite : {date_limite}\n\nCordialement,\n{coordonnees_gestionnaire}",
            ],
        ];
    }

    public function all(): array
    {
        return $this->values;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->values, $key, $default);
    }

    public function set(string $key, mixed $value): self
    {
        data_set($this->values, $key, $value);

        return $this;
    }

    /** @return array<string, array{email: bool, app: bool, recipients: string}> */
    public function notifications(): array
    {
        $stored = $this->values['notifications'];

        return array_map(
            fn (string $event) => $stored[$event],
            array_combine(array_keys(self::NOTIFICATION_EVENTS), array_keys(self::NOTIFICATION_EVENTS)),
        );
    }

    /** Seuls les événements et destinataires connus sont conservés. */
    public function setNotifications(array $preferences): self
    {
        $current = $this->notifications();

        foreach ($current as $event => $values) {
            $submitted = $preferences[$event] ?? [];
            $recipients = $submitted['recipients'] ?? $values['recipients'];

            $current[$event] = [
                'email' => (bool) ($submitted['email'] ?? $values['email']),
                'app' => (bool) ($submitted['app'] ?? $values['app']),
                'recipients' => array_key_exists($recipients, self::RECIPIENTS) ? $recipients : $values['recipients'],
            ];
        }

        $this->values['notifications'] = $current;

        return $this;
    }

    public function save(): void
    {
        $this->company->update(['settings' => $this->values]);
    }

    /** Fusion récursive des tableaux associatifs ; une liste stockée remplace la liste par défaut. */
    private static function merge(array $defaults, array $stored): array
    {
        foreach ($stored as $key => $value) {
            $defaults[$key] = is_array($value) && is_array($defaults[$key] ?? null) && ! array_is_list($defaults[$key])
                ? self::merge($defaults[$key], $value)
                : $value;
        }

        return $defaults;
    }
}
