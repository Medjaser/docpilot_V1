<?php

namespace App\Domain\Company;

use App\Domain\Audit\AuditLogger;
use App\Enums\CompanyStatus;
use App\Models\Company;
use InvalidArgumentException;

/**
 * Seul point de changement du statut d'une entreprise : transitions contrôlées par
 * CompanyStatus::canTransitionTo(), motif obligatoire, audit « company.status_changed ».
 */
class CompanyStatusManager
{
    public function __construct(private AuditLogger $audit) {}

    /** @param string $userType user ou system (tâche planifiée) */
    public function transition(
        Company $company,
        CompanyStatus $to,
        string $reason,
        string $userType = 'user',
        ?int $userId = null,
    ): Company {
        $reason = trim($reason);
        $from = $company->status;

        if ($reason === '') {
            throw new InvalidArgumentException('Le motif du changement de statut est obligatoire.');
        }

        if (! $from->canTransitionTo($to)) {
            throw InvalidCompanyTransition::between($from, $to);
        }

        $attributes = ['status' => $to, 'status_reason' => $reason];

        match ($to) {
            CompanyStatus::Active => $attributes += ['suspended_at' => null, 'terminated_at' => null],
            CompanyStatus::Suspended => $attributes['suspended_at'] = now(),
            CompanyStatus::Terminated => $attributes['terminated_at'] = now(),
            CompanyStatus::Archived => $attributes['archived_at'] = now(),
            CompanyStatus::Trial => null,
        };

        $company->update($attributes);

        $this->audit->log(
            action: 'company.status_changed',
            entity: $company,
            metadata: ['from' => $from->value, 'to' => $to->value, 'reason' => $reason],
            userType: $userType,
            userId: $userId,
        );

        return $company;
    }
}
