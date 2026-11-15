<?php

namespace App\Enums;

/**
 * Statut d'une entreprise cliente (§9.2).
 */
enum CompanyStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Suspended = 'suspended';
    case Terminated = 'terminated';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Essai',
            self::Active => 'Active',
            self::Suspended => 'Suspendue',
            self::Terminated => 'Résiliée',
            self::Archived => 'Archivée',
        };
    }

    /** Classes Tailwind du badge de statut. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Trial => 'bg-blue-100 text-blue-800',
            self::Active => 'bg-emerald-100 text-emerald-800',
            self::Suspended => 'bg-rose-100 text-rose-800',
            self::Terminated => 'bg-slate-200 text-slate-700',
            self::Archived => 'bg-slate-100 text-slate-500',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Trial => [self::Active, self::Suspended, self::Terminated],
            self::Active => [self::Suspended, self::Terminated],
            self::Suspended => [self::Active, self::Terminated],
            self::Terminated => [self::Active, self::Archived],
            self::Archived => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /** Création, modification, envoi de demandes autorisés. */
    public function isWritable(): bool
    {
        return in_array($this, [self::Trial, self::Active], true);
    }

    /** Accès à l'espace /app (en lecture seule si suspendue). */
    public function canAccessApp(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::Suspended], true);
    }
}
