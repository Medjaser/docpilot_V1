<?php

namespace App\Domain\Audit;

use App\Models\AuditLog;
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

    public function log(string $action, ?Model $entity = null, array $metadata = [], string $actorType = 'user'): AuditLog
    {
        return AuditLog::create([
            'company_id' => $entity?->company_id ?? $this->current->id(),
            'user_id' => auth()->id(),
            'actor_type' => $actorType,
            'action' => $action,
            'entity_type' => $entity ? class_basename($entity) : null,
            'entity_id' => $entity?->getKey(),
            'metadata' => $metadata ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }
}
