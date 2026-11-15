<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Catégorie de document (Kbis, attestation de vigilance, RC Pro...). */
class DocumentType extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'name', 'description', 'is_mandatory', 'has_expiration_date', 'default_validity_months',
        'reminder_days', 'allowed_extensions', 'max_file_size_mb', 'message_template', 'is_active', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'has_expiration_date' => 'boolean',
            'is_active' => 'boolean',
            'default_validity_months' => 'integer',
            'max_file_size_mb' => 'integer',
            'reminder_days' => 'array',
            'allowed_extensions' => 'array',
            'archived_at' => 'datetime',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function templateItems(): HasMany
    {
        return $this->hasMany(DocumentTemplateItem::class);
    }

    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(DocumentTemplate::class, 'document_template_items', 'document_type_id', 'template_id');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereNull('archived_at')->where('is_active', true);
    }
}
