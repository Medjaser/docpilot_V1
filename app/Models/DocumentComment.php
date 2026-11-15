<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Commentaire sur un document. Les commentaires "internal" ne sont jamais montrés au sous-traitant. */
class DocumentComment extends Model
{
    use BelongsToCompany, HasFactory;

    public const VISIBILITY_INTERNAL = 'internal';

    public const VISIBILITY_EXTERNAL = 'external';

    protected $fillable = ['document_id', 'author_type', 'author_id', 'body', 'visibility'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** User ou Subcontractor (morph map : 'user' / 'subcontractor'). */
    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeExternal(Builder $query): void
    {
        $query->where('visibility', self::VISIBILITY_EXTERNAL);
    }
}
