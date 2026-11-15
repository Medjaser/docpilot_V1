<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Journal d'audit (§9.22). Lecture seule après écriture. */
class AuditLog extends Model
{
    use BelongsToCompany, HasFactory;

    public const UPDATED_AT = null;

    // company_id assignable : le journal est écrit par AuditLogger, jamais depuis un formulaire
    protected $fillable = [
        'company_id', 'user_type', 'user_id', 'action', 'entity_type', 'entity_id',
        'metadata', 'ip_address', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
