<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Modèle de dossier, ex. « Sous-traitant électricité » (§9.7). */
class DocumentTemplate extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(DocumentTemplateItem::class)->orderBy('position');
    }
}
