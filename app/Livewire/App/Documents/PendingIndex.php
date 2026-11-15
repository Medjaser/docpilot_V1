<?php

namespace App\Livewire\App\Documents;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Subcontractor;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Documents à valider - DocPilot')]
class PendingIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $category = '';

    public string $subcontractor = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Document::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategory(): void
    {
        $this->resetPage();
    }

    public function updatingSubcontractor(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'category', 'subcontractor']);
        $this->resetPage();
    }

    public function exportCsv(): StreamedResponse
    {
        $this->authorize('viewAny', Document::class);

        $rows = $this->buildQuery()->get();
        $filename = 'documents-a-valider-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Sous-traitant', 'Catégorie de document', 'Statut', 'Date de dépôt', 'Date d\'expiration', 'Déposé par'], ';');

            foreach ($rows as $doc) {
                $submittedBy = $this->resolveSubmittedBy($doc);
                $submittedAt = $doc->currentVersion?->created_at?->format('d/m/Y H:i') ?? $doc->updated_at?->format('d/m/Y H:i') ?? '-';
                fputcsv($out, [
                    $doc->subcontractor?->name ?? '-',
                    $doc->documentType?->name ?? '-',
                    $doc->status->label(),
                    $submittedAt,
                    $doc->expiration_date?->format('d/m/Y') ?? '-',
                    $submittedBy,
                ], ';');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render()
    {
        $query = $this->buildQuery();
        $totalCount = (clone $query)->count();
        $paginator = $query->paginate(15);

        $documents = $paginator->getCollection()->map(function (Document $doc): array {
            $submittedAt = $doc->currentVersion?->created_at ?? $doc->updated_at;

            return [
                'id' => $doc->id,
                'subcontractor_id' => $doc->subcontractor_id,
                'subcontractor_name' => $doc->subcontractor?->name ?? 'Sous-traitant',
                'document_type' => $doc->documentType?->name ?? 'Document',
                'submitted_at' => $submittedAt ? $submittedAt->format('d/m/Y \à H:i') : '-',
                'expires_at' => $doc->expiration_date?->format('d/m/Y') ?? '-',
                'submitted_by' => $this->resolveSubmittedBy($doc),
                'version' => $doc->currentVersion ? 'v'.$doc->currentVersion->version_number : 'v1',
            ];
        });

        $subcontractorsList = Subcontractor::whereNull('archived_at')
            ->orderBy('legal_name')
            ->pluck('legal_name')
            ->all();

        $categoriesList = DocumentType::whereNull('archived_at')
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return view('livewire.app.documents.pending-index', [
            'documents' => $documents,
            'paginator' => $paginator,
            'totalCount' => $totalCount,
            'subcontractorsList' => $subcontractorsList,
            'categoriesList' => $categoriesList,
        ]);
    }

    private function buildQuery()
    {
        $query = Document::with(['subcontractor', 'documentType', 'currentVersion.uploadedBy'])
            ->whereNull('archived_at')
            ->whereIn('status', [DocumentStatus::Pending->value, DocumentStatus::Uploaded->value])
            ->whereHas('subcontractor', fn ($q) => $q->whereNull('archived_at'));

        if ($this->search !== '') {
            $needle = '%'.mb_strtolower(trim($this->search)).'%';
            $query->where(function ($sub) use ($needle): void {
                $sub->whereHas('documentType', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', [$needle]))
                    ->orWhereHas('subcontractor', fn ($q) => $q->whereRaw('LOWER(legal_name) LIKE ?', [$needle]));
            });
        }

        if ($this->subcontractor !== '') {
            $query->whereHas('subcontractor', fn ($q) => $q->where('legal_name', $this->subcontractor));
        }

        if ($this->category !== '') {
            $query->whereHas('documentType', fn ($q) => $q->where('name', 'like', '%'.$this->category.'%'));
        }

        return $query->orderByDesc('updated_at');
    }

    private function resolveSubmittedBy(Document $doc): string
    {
        $uploader = $doc->currentVersion?->uploadedBy;
        if ($uploader instanceof User) {
            return $uploader->full_name ?: $uploader->name;
        }
        if ($uploader instanceof Subcontractor) {
            return $uploader->contact_name ?: $uploader->name;
        }

        return $doc->subcontractor?->contact_name ?: ($doc->subcontractor?->name ?? '-');
    }
}
