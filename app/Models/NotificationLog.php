<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Historique des envois (e-mails, relances) + clé anti-doublon. */
class NotificationLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id', 'channel', 'type', 'recipient', 'subject_type', 'subject_id', 'dedup_key', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
