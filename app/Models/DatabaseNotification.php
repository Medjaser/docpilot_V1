<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\DatabaseNotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\DatabaseNotification as BaseDatabaseNotification;

/** Notification applicative isolée par entreprise. */
class DatabaseNotification extends BaseDatabaseNotification
{
    /** @use HasFactory<DatabaseNotificationFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'id', 'company_id', 'type', 'data', 'read_at',
    ];
}
