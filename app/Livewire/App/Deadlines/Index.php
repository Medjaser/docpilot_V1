<?php

namespace App\Livewire\App\Deadlines;

use App\Domain\Audit\AuditLogger;
use App\Domain\Notifications\CompanyNotificationDispatcher;
use App\Enums\DocumentStatus;
use App\Enums\UserStatus;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\NotificationLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Échéances des documents - DocPilot')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'expired'; // expired, 7_days, 30_days, 60_days, no_date

    #[Url]
    public string $category = '';

    #[Url]
    public string $manager = '';

    public array $selected = [];

    public ?string $toastMessage = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Document::class);
    }

    public function updatingTab(): void
    {
        $this->selected = [];
        $this->resetPage();
    }

    public function updatingCategory(): void
    {
        $this->resetPage();
    }

    public function updatingManager(): void
    {
        $this->resetPage();
    }

    public function triggerReminder(
        int $id,
        CompanyNotificationDispatcher $dispatcher,
        AuditLogger $audit,
    ): void {
        $document = Document::with(['subcontractor', 'documentType'])->findOrFail($id);
        $this->authorize('remind', $document);

        $this->sendDocumentReminder($document, $dispatcher, $audit);

        $this->toastMessage = 'Relance envoyée avec succès pour le document sélectionné !';
    }

    public function triggerBulkReminder(
        CompanyNotificationDispatcher $dispatcher,
        AuditLogger $audit,
    ): void {
        $documents = Document::with(['subcontractor', 'documentType'])->whereKey($this->selected)->get();

        foreach ($documents as $document) {
            $this->authorize('remind', $document);
        }

        foreach ($documents as $document) {
            $this->sendDocumentReminder($document, $dispatcher, $audit);
        }

        $count = count($this->selected);
        $this->toastMessage = "Relance envoyée avec succès pour les {$count} documents sélectionnés !";
        $this->selected = [];
    }

    public function exportCsv(): StreamedResponse
    {
        $this->authorize('viewAny', Document::class);

        $today = CarbonImmutable::today();
        $rows = $this->buildTabQuery($this->tab, $today)->get();
        $filename = 'echeances-'.$this->tab.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows, $today): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Sous-traitant', 'Document', 'Date d\'expiration', 'Jours restants', 'Statut', 'Responsable'], ';');

            foreach ($rows as $doc) {
                $days = $doc->expiration_date ? (int) $today->diffInDays($doc->expiration_date, false) : null;
                fputcsv($out, [
                    $doc->subcontractor?->name ?? '-',
                    $doc->documentType?->name ?? '-',
                    $doc->expiration_date?->format('d/m/Y') ?? 'Sans date',
                    $days !== null ? (string) $days : '-',
                    $doc->status->label(),
                    $doc->subcontractor?->assignedUser?->full_name ?? '-',
                ], ';');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render()
    {
        $today = CarbonImmutable::today();

        $tabCounts = [
            'expired' => $this->buildTabQuery('expired', $today)->count(),
            '7_days' => $this->buildTabQuery('7_days', $today)->count(),
            '30_days' => $this->buildTabQuery('30_days', $today)->count(),
            '60_days' => $this->buildTabQuery('60_days', $today)->count(),
            'no_date' => $this->buildTabQuery('no_date', $today)->count(),
        ];

        $docs = $this->buildTabQuery($this->tab, $today)->get();

        $lastReminderByRecipient = NotificationLog::query()
            ->where('type', 'like', 'reminder.%')
            ->orderByDesc('sent_at')
            ->get()
            ->groupBy('recipient')
            ->map(fn ($items) => $items->first()?->sent_at);

        $deadlines = $docs->map(function (Document $doc) use ($today, $lastReminderByRecipient): array {
            $daysRemaining = $doc->expiration_date
                ? (int) $today->diffInDays($doc->expiration_date, false)
                : null;

            $recipient = $doc->subcontractor?->contact_email;
            $lastSent = $recipient ? ($lastReminderByRecipient[$recipient] ?? null) : null;

            return [
                'id' => $doc->id,
                'subcontractor_id' => $doc->subcontractor_id,
                'subcontractor' => $doc->subcontractor?->name ?? 'Sous-traitant',
                'document' => $doc->documentType?->name ?? 'Document',
                'expires_at' => $doc->expiration_date?->format('d/m/Y') ?? 'Sans date',
                'days_remaining' => $daysRemaining,
                'status' => $doc->status->value,
                'last_reminder' => $lastSent ? $lastSent->diffForHumans() : 'Non relancé',
                'tab' => $this->tab,
            ];
        });

        $categoriesList = DocumentType::whereNull('archived_at')
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $managersList = User::where('company_id', auth()->user()->company_id)
            ->where('status', UserStatus::Active)
            ->orderBy('first_name')
            ->get()
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->full_name])
            ->all();

        return view('livewire.app.deadlines.index', [
            'deadlines' => $deadlines,
            'tabCounts' => $tabCounts,
            'categoriesList' => $categoriesList,
            'managersList' => $managersList,
        ]);
    }

    private function buildTabQuery(string $tab, CarbonImmutable $today)
    {
        $query = Document::with(['subcontractor.assignedUser', 'documentType'])
            ->whereNull('archived_at')
            ->whereHas('subcontractor', fn ($q) => $q->whereNull('archived_at'));

        if ($this->category !== '') {
            $query->whereHas('documentType', fn ($q) => $q->where('name', 'like', '%'.$this->category.'%'));
        }

        if ($this->manager !== '') {
            $managerFilter = $this->manager;
            $query->whereHas('subcontractor.assignedUser', function ($q) use ($managerFilter): void {
                if (is_numeric($managerFilter)) {
                    $q->where('id', (int) $managerFilter);
                } else {
                    $q->whereRaw("LOWER(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) LIKE ?", ['%'.mb_strtolower($managerFilter).'%']);
                }
            });
        }

        switch ($tab) {
            case 'expired':
                $query->where(function ($sub) use ($today): void {
                    $sub->where('status', DocumentStatus::Expired->value)
                        ->orWhere(function ($q) use ($today): void {
                            $q->whereNotNull('expiration_date')
                                ->whereDate('expiration_date', '<', $today->toDateString());
                        });
                });
                break;

            case '7_days':
                $query->whereNotNull('expiration_date')
                    ->whereDate('expiration_date', '>=', $today->toDateString())
                    ->whereDate('expiration_date', '<=', $today->addDays(7)->toDateString());
                break;

            case '30_days':
                $query->whereNotNull('expiration_date')
                    ->whereDate('expiration_date', '>=', $today->toDateString())
                    ->whereDate('expiration_date', '<=', $today->addDays(30)->toDateString());
                break;

            case '60_days':
                $query->whereNotNull('expiration_date')
                    ->whereDate('expiration_date', '>=', $today->toDateString())
                    ->whereDate('expiration_date', '<=', $today->addDays(60)->toDateString());
                break;

            case 'no_date':
                $query->whereNull('expiration_date');
                break;
        }

        return $query->orderBy('expiration_date');
    }

    private function sendDocumentReminder(
        Document $document,
        CompanyNotificationDispatcher $dispatcher,
        AuditLogger $audit,
    ): void {
        $company = auth()->user()->company;
        $subcontractor = $document->subcontractor;

        if ($company && $subcontractor) {
            $isExpired = $document->status === DocumentStatus::Expired
                || ($document->expiration_date && $document->expiration_date->isPast());

            $dispatcher->sendEmailToSubcontractor(
                $company,
                $subcontractor,
                $isExpired ? 'reminder.expired' : 'reminder.expiring',
                [
                    'nom_document' => $document->documentType?->name ?? 'Document',
                    'date_expiration' => $document->expiration_date?->format('d/m/Y') ?? 'Sans date',
                ]
            );
        }

        $audit->record('document.reminder_sent', $document, [
            'subcontractor' => $subcontractor?->name,
            'document_type' => $document->documentType?->name,
        ]);
    }
}
