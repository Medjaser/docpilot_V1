<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Catégorie de document (Kbis, attestation de vigilance, RC Pro...). */
class DocumentType extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'name', 'description', 'is_mandatory', 'has_expiration_date', 'default_validity_months',
        'reminder_days', 'allowed_extensions', 'max_file_size_kb', 'message_template', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'has_expiration_date' => 'boolean',
            'is_active' => 'boolean',
            'reminder_days' => 'array',
            'allowed_extensions' => 'array',
            'archived_at' => 'datetime',
        ];
    }
}
