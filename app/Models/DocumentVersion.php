<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Fichier déposé. Les versions ne sont jamais modifiées ni supprimées immédiatement (§9.11). */
class DocumentVersion extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    protected $fillable = [
        'document_id', 'version_number', 'original_file_name', 'file_path', 'mime_type', 'file_size',
        'checksum_sha256', 'status', 'issue_date', 'expiration_date',
        'uploaded_by_type', 'uploaded_by_id', 'comment', 'replacement_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'issue_date' => 'date',
            'expiration_date' => 'date',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
