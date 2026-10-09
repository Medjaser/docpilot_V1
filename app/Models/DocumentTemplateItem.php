<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTemplateItem extends Model
{
    protected $fillable = ['document_template_id', 'document_type_id', 'is_required', 'position'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }
}
