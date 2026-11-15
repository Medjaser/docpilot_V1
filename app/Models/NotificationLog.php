<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Historique des envois (e-mails, relances) + clé anti-doublon. */
class NotificationLog extends Model
{
    use BelongsToCompany, HasFactory;

    public const UPDATED_AT = null;

    // company_id assignable : écrit par les tâches planifiées, qui traitent toutes les entreprises
    protected $fillable = [
        'company_id', 'channel', 'type', 'recipient', 'subject_type', 'subject_id', 'dedup_key', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
