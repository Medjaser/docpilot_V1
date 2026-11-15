<?php

namespace App\Models;

use App\Enums\SubcontractorStatus;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subcontractor extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'legal_name', 'trade_name', 'type', 'activity',
        'contact_first_name', 'contact_last_name', 'contact_email', 'contact_phone',
        'address', 'postal_code', 'city', 'country', 'registration_number',
        'status', 'internal_owner_id', 'start_date', 'end_date', 'notes', 'tags',
        'completeness_score', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubcontractorStatus::class,
            'tags' => 'array',
            'completeness_score' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function internalOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'internal_owner_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'internal_owner_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function documentRequests(): HasMany
    {
        return $this->hasMany(DocumentRequest::class);
    }

    public function getNameAttribute(): string
    {
        return (string) ($this->trade_name ?: $this->legal_name);
    }

    public function getContactNameAttribute(): string
    {
        return trim($this->contact_first_name.' '.$this->contact_last_name);
    }
}
