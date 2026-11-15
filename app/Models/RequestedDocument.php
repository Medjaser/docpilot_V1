<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ligne d'une demande de documents : une catégorie demandée. */
class RequestedDocument extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['document_request_id', 'document_type_id', 'document_id', 'status', 'due_date'];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'due_date' => 'date',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class, 'document_request_id');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
