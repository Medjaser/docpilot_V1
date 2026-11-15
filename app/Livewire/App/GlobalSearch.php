<?php

namespace App\Livewire\App;

use App\Models\Document;
use App\Models\Subcontractor;
use Livewire\Component;

/**
 * Recherche globale de la barre du haut. Passe uniquement par les modèles BelongsToCompany :
 * sur les requêtes AJAX, SetCurrentCompany est rejoué par le middleware persistant de Livewire.
 */
class GlobalSearch extends Component
{
    private const LIMIT = 5;

    public string $query = '';

    public function render()
    {
        $term = trim($this->query);
        $subcontractors = collect();
        $documents = collect();

        if (mb_strlen($term) >= 2 && auth()->user()?->can('viewAny', Subcontractor::class)) {
            $like = '%'.addcslashes($term, '\\%_').'%';

            $subcontractors = Subcontractor::query()
                ->where(fn ($q) => $q
                    ->where('legal_name', 'ilike', $like)
                    ->orWhere('trade_name', 'ilike', $like)
                    ->orWhere('contact_email', 'ilike', $like)
                    ->orWhere('city', 'ilike', $like))
                ->orderBy('legal_name')
                ->limit(self::LIMIT)
                ->get(['id', 'legal_name', 'city']);

            if (auth()->user()->can('viewAny', Document::class)) {
                $documents = Document::query()
                    ->with(['documentType', 'subcontractor'])
                    ->where(fn ($q) => $q
                        ->whereHas('documentType', fn ($q) => $q->where('name', 'ilike', $like))
                        ->orWhereHas('subcontractor', fn ($q) => $q->where('legal_name', 'ilike', $like)))
                    ->orderByDesc('updated_at')
                    ->limit(self::LIMIT)
                    ->get();
            }
        }

        return view('livewire.app.global-search', [
            'term' => $term,
            'subcontractors' => $subcontractors,
            'documents' => $documents,
        ]);
    }
}
