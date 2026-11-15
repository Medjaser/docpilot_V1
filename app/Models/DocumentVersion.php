<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Fichier déposé. Les versions ne sont jamais modifiées ni supprimées immédiatement (§9.11). */
class DocumentVersion extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'document_id', 'version_number', 'file_name', 'original_name', 'file_path', 'disk',
        'mime_type', 'file_size', 'sha256', 'status', 'uploaded_by_type', 'uploaded_by_id',
        'comment', 'replaced_at', 'replaced_by', 'replacement_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'version_number' => 'integer',
            'file_size' => 'integer',
            'replaced_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** User ou Subcontractor (morph map : 'user' / 'subcontractor'). */
    public function uploadedBy(): MorphTo
    {
        return $this->morphTo();
    }

    /** Version qui a remplacé celle-ci. */
    public function replacement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by');
    }
}
