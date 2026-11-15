<?php

namespace App\Livewire\App\Subcontractors;

use App\Domain\Subcontractors\SubcontractorPresenter;
use App\Models\Subcontractor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Liste des sous-traitants de l'entreprise courante.
 * Toutes les requêtes passent par Subcontractor (CompanyScope) : listes, compteurs, filtres et export.
 */
#[Layout('layouts.app')]
#[Title('Liste des sous-traitants - DocPilot')]
class Index extends Component
{
    use WithPagination;

    private const PER_PAGE = 15;

    private const SORTABLE = [
        'name' => 'legal_name',
        'completeness' => 'completeness_score',
        'next_deadline' => 'next_deadline',
    ];

    private const FILTERS = ['search', 'status', 'activity', 'city', 'manager', 'tag', 'tab'];

    #[Url]
    public string $tab = 'active'; // active, archived

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $activity = '';

    #[Url]
    public string $city = '';

    /** Identifiant du responsable interne (filtre ; validé contre les utilisateurs de l'entreprise). */
    #[Url]
    public string $manager = '';

    #[Url]
    public string $tag = '';

    #[Url]
    public string $sortColumn = 'name';

    #[Url]
    public string $sortDirection = 'asc';

    public array $selected = [];

    public function mount(): void
    {
        $this->authorize('viewAny', Subcontractor::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, self::FILTERS, true)) {
            $this->resetPage();
        }
    }

    public function sortBy(string $column): void
    {
        if (! array_key_exists($column, self::SORTABLE)) {
            return;
        }

        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'activity', 'city', 'manager', 'tag']);
        $this->resetPage();
    }

    public function toggleArchive(int $id): void
    {
        $subcontractor = Subcontractor::findOrFail($id);
        $this->authorize('archive', $subcontractor);

        if ($subcontractor->archived_at) {
            $subcontractor->update([
                'archived_at' => null,
                'status' => \App\Enums\SubcontractorStatus::Incomplete,
            ]);
            app(\App\Domain\Documents\SubcontractorCompletenessUpdater::class)->refresh($subcontractor);
            app(\App\Domain\Audit\AuditLogger::class)->log('subcontractor.restored', $subcontractor);
        } else {
            $subcontractor->update([
                'archived_at' => now(),
                'status' => \App\Enums\SubcontractorStatus::Archived,
            ]);
            app(\App\Domain\Audit\AuditLogger::class)->log('subcontractor.archived', $subcontractor);
        }
    }

    public function exportCsv()
    {
        $this->authorize('viewAny', Subcontractor::class);

        $rows = $this->filteredQuery()->get()->map(fn (Subcontractor $s) => SubcontractorPresenter::row($s));
        $filename = 'sous-traitants-'.Str::slug(auth()->user()->company->name).'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Raison sociale', 'Activité', 'Contact', 'Ville', 'Complétude', 'Statut']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['id'], $row['name'], $row['activity'], $row['contact_name'], $row['city'], $row['completeness'].'%', $row['status']]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render()
    {
        $subcontractors = $this->filteredQuery()
            ->paginate(self::PER_PAGE)
            ->through(fn (Subcontractor $s) => SubcontractorPresenter::row($s));

        return view('livewire.app.subcontractors.index', [
            'subcontractors' => $subcontractors,
            'activeCount' => Subcontractor::query()->whereNull('archived_at')->count(),
            'archivedCount' => Subcontractor::query()->whereNotNull('archived_at')->count(),
            'activities' => $this->distinctValues('activity'),
            'cities' => $this->distinctValues('city'),
            'managers' => $this->companyUsers(),
        ]);
    }

    private function filteredQuery(): Builder
    {
        $query = SubcontractorPresenter::withNextDeadline(Subcontractor::query()->with('internalOwner'))
            ->when($this->tab === 'archived', fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'));

        if (($term = trim($this->search)) !== '') {
            $like = '%'.addcslashes($term, '\\%_').'%';
            $query->where(fn ($q) => $q
                ->where('legal_name', 'ilike', $like)
                ->orWhere('trade_name', 'ilike', $like)
                ->orWhere('contact_first_name', 'ilike', $like)
                ->orWhere('contact_last_name', 'ilike', $like)
                ->orWhere('contact_email', 'ilike', $like)
                ->orWhere('city', 'ilike', $like));
        }

        if ($statuses = SubcontractorPresenter::BADGE_STATUSES[$this->status] ?? null) {
            $query->whereIn('status', $statuses);
        }

        $query->when($this->activity !== '', fn ($q) => $q->where('activity', $this->activity))
            ->when($this->city !== '', fn ($q) => $q->where('city', $this->city))
            ->when($this->tag !== '', fn ($q) => $q->whereJsonContains('tags', $this->tag));

        // Le responsable doit appartenir à l'entreprise : un ID étranger ne filtre rien et ne révèle rien
        if ($this->manager !== '' && $this->companyUsers()->has((int) $this->manager)) {
            $query->where('internal_owner_id', (int) $this->manager);
        }

        $column = self::SORTABLE[$this->sortColumn] ?? 'legal_name';
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($column, $direction)->orderBy('id');
    }

    private function distinctValues(string $column): array
    {
        return Subcontractor::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->all();
    }

    /** Users n'a pas de CompanyScope (authentification) : filtre explicite sur l'entreprise. */
    private function companyUsers()
    {
        return User::query()
            ->where('company_id', auth()->user()->company_id)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(fn (User $user) => [$user->id => $user->full_name]);
    }
}
