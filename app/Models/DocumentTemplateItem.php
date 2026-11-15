<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTemplateItem extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['template_id', 'document_type_id', 'is_required', 'position'];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }
}
