<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\RejectionReason;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Document attendu pour un sous-traitant (sous-traitant × catégorie). */
class Document extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'subcontractor_id', 'document_type_id', 'current_version_id', 'status',
        'issue_date', 'expiration_date', 'validated_at', 'validated_by',
        'rejection_reason_code', 'rejection_comment', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'rejection_reason_code' => RejectionReason::class,
            'issue_date' => 'date',
            'expiration_date' => 'date',
            'validated_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Subcontractor::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version_number');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'current_version_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(DocumentComment::class)->latest();
    }

    public function requestedDocuments(): HasMany
    {
        return $this->hasMany(RequestedDocument::class);
    }
}
