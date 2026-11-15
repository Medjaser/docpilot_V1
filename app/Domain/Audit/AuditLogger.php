<?php

namespace App\Domain\Audit;

use App\Models\AuditLog;
use App\Models\Company;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Point d'entrée unique pour tracer les actions importantes (§9.22).
 * Usage : app(AuditLogger::class)->log('document.validated', $document, ['version' => 2]);
 * Ne jamais mettre de données sensibles (contenu de document, mot de passe...) dans $metadata.
 */
class AuditLogger
{
    public function __construct(private CurrentCompany $current) {}

    /** @param string $userType user, subcontractor ou system */
    public function log(
        string $action,
        ?Model $entity = null,
        array $metadata = [],
        string $userType = 'user',
        ?int $userId = null,
    ): AuditLog {
        return AuditLog::create([
            'company_id' => $this->companyIdOf($entity) ?? $this->current->id(),
            'user_type' => $userType,
            'user_id' => $userId ?? ($userType === 'user' ? auth()->id() : null),
            'action' => $action,
            'entity_type' => $entity?->getMorphClass(),
            'entity_id' => $entity?->getKey(),
            'metadata' => $metadata ?: null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    public function record(
        string $action,
        ?Model $entity = null,
        array $metadata = [],
        string $userType = 'user',
        ?int $userId = null,
    ): AuditLog {
        return $this->log($action, $entity, $metadata, $userType, $userId);
    }

    /** getAttributes() plutôt que ->company_id : le mode strict refuse un attribut absent (ex. Company). */
    private function companyIdOf(?Model $entity): ?int
    {
        return match (true) {
            $entity === null => null,
            $entity instanceof Company => $entity->getKey(),
            default => $entity->getAttributes()['company_id'] ?? null,
        };
    }
}
