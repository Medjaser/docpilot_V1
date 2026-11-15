<?php

namespace App\Livewire\App;

use App\Domain\Subcontractors\SubcontractorPresenter;
use App\Enums\DocumentStatus;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Subcontractor;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Tableau de bord : tous les chiffres viennent des modèles BelongsToCompany (entreprise courante). */
#[Layout('layouts.app')]
#[Title('Tableau de bord - DocPilot')]
class Dashboard extends Component
{
    private const DONUT = [
        'valid' => ['label' => 'Valides', 'color' => '#10B981', 'dot' => 'bg-emerald-500', 'statuses' => [DocumentStatus::Valid]],
        'pending' => ['label' => 'À valider', 'color' => '#A855F7', 'dot' => 'bg-purple-500', 'statuses' => [DocumentStatus::Pending, DocumentStatus::Uploaded]],
        'expiring_soon' => ['label' => 'Bientôt expirés', 'color' => '#F59E0B', 'dot' => 'bg-amber-500', 'statuses' => [DocumentStatus::ExpiringSoon]],
        'expired' => ['label' => 'Expirés', 'color' => '#EF4444', 'dot' => 'bg-rose-500', 'statuses' => [DocumentStatus::Expired]],
        'missing' => ['label' => 'Manquants', 'color' => '#94A3B8', 'dot' => 'bg-slate-400', 'statuses' => [DocumentStatus::Missing, DocumentStatus::Requested, DocumentStatus::Rejected]],
    ];

    public string $period = '2026-01-01 - 2026-12-31';
    public ?string $toastMessage = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Subcontractor::class);
    }

    public function triggerReminder(int $subcontractorId): void
    {
        $subcontractor = Subcontractor::findOrFail($subcontractorId);
        $this->authorize('remind', $subcontractor);

        // TODO: envoyer la relance (job en file d'attente)
        $this->toastMessage = "Relance envoyée avec succès à {$subcontractor->legal_name} !";
    }

    public function closeToast(): void
    {
        $this->toastMessage = null;
    }

    public function render()
    {
        $active = Subcontractor::query()->whereNull('archived_at');
        $total = (clone $active)->count();
        $byBadge = collect(SubcontractorPresenter::BADGE_STATUSES)
            ->map(fn ($statuses) => (clone $active)->whereIn('status', $statuses)->count());

        $documentCounts = Document::query()
            ->whereNull('archived_at')
            ->pluck('status')
            ->countBy(fn (DocumentStatus $status) => $status->value);

        $donut = collect(self::DONUT)->map(fn ($slice) => $slice + [
            'count' => collect($slice['statuses'])->sum(fn ($status) => (int) ($documentCounts[$status->value] ?? 0)),
        ]);
        $documentsTotal = max(1, $donut->sum('count'));

        $offset = 0;
        $donut = $donut->map(function ($slice) use ($documentsTotal, &$offset) {
            $percent = (int) round($slice['count'] * 100 / $documentsTotal);
            $slice += ['percent' => $percent, 'offset' => -$offset];
            $offset += $percent;

            return $slice;
        });

        $deadlines = Document::query()
            ->with(['subcontractor', 'documentType'])
            ->whereNull('archived_at')
            ->whereNotNull('expiration_date')
            ->whereIn('status', SubcontractorPresenter::TRACKED_DOCUMENT_STATUSES)
            ->orderBy('expiration_date')
            ->limit(5)
            ->get()
            ->map(fn (Document $document) => [
                'subcontractor_id' => $document->subcontractor_id,
                'subcontractor' => $document->subcontractor->legal_name,
                'document' => $document->documentType->name,
                'due_date' => $document->expiration_date->translatedFormat('d M Y'),
                'status' => $document->status->value,
            ]);

        $activities = AuditLog::query()
            ->latest('created_at')
            ->limit(4)
            ->get()
            ->map(fn (AuditLog $log) => [
                'icon' => 'document',
                'text' => $log->action,
                'time' => $log->created_at?->diffForHumans(),
            ]);

        return view('livewire.app.dashboard', [
            'firstName' => auth()->user()->first_name,
            'companyName' => auth()->user()->company->name,
            'total' => $total,
            'newThisMonth' => (clone $active)->where('created_at', '>=', now()->startOfMonth())->count(),
            'byBadge' => $byBadge,
            'percentOf' => fn (int $count) => $total > 0 ? (int) round($count * 100 / $total) : 0,
            'donut' => $donut,
            'documentsTotal' => $donut->sum('count'),
            'deadlines' => $deadlines,
            'activities' => $activities,
        ]);
    }
}
