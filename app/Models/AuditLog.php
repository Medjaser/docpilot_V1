<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Journal d'audit (§9.22). Lecture seule après écriture. */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id', 'user_id', 'actor_type', 'action', 'entity_type', 'entity_id', 'metadata', 'ip_address',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
