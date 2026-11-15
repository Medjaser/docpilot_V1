<?php

namespace App\Livewire\App\Requests;

use App\Console\Commands\LanInfoCommand;
use App\Domain\Audit\AuditLogger;
use App\Domain\Notifications\CompanyNotificationDispatcher;
use App\Enums\DocumentStatus;
use App\Enums\RequestStatus;
use App\Enums\SubcontractorStatus;
use App\Models\DocumentRequest;
use App\Models\Subcontractor;
use Livewire\Attributes\Locked;
use Livewire\Component;

class SendRequestModal extends Component
{
    #[Locked]
    public ?int $subcontractorId = null;

    public string $contact_name = '';

    public string $company_name = '';

    /** [nom de la catégorie => statut] des documents du sous-traitant. */
    #[Locked]
    public array $available_docs = [];

    public array $requested_docs = [];

    public string $deadline_date = '';

    public string $link_expiry_date = '';

    public bool $auto_reminders = true;

    public string $message = 'Veuillez mettre à jour votre dossier administratif pour maintenir notre collaboration sur nos chantiers en cours.';

    public bool $sent = false;

    public bool $savedAsDraft = false;

    public string $generated_link = '';

    public function mount(?int $subcontractorId = null): void
    {
        $this->authorize('create', DocumentRequest::class);

        $this->deadline_date = now()->addDays(14)->toDateString();
        $this->link_expiry_date = now()->addDays((int) config('docpilot.portal.link_days', 30))->toDateString();

        if ($subcontractorId === null) {
            return;
        }

        // CompanyScope : un sous-traitant d'une autre entreprise donne 404
        $subcontractor = Subcontractor::with('documents.documentType')->findOrFail($subcontractorId);
        $this->authorize('view', $subcontractor);

        $this->subcontractorId = $subcontractor->id;
        $this->contact_name = $subcontractor->contact_name ?: $subcontractor->contact_email;
        $this->company_name = $subcontractor->legal_name;
        $activeDocs = $subcontractor->documents->filter(function ($document) {
            return $document->documentType
                && $document->documentType->is_active
                && $document->documentType->archived_at === null
                && $document->archived_at === null;
        });

        $this->available_docs = $activeDocs
            ->mapWithKeys(fn ($document) => [$document->documentType->name => $document->status->value])
            ->all();
        $this->requested_docs = $activeDocs
            ->whereIn('status', [
                DocumentStatus::Missing,
                DocumentStatus::Requested,
                DocumentStatus::Rejected,
                DocumentStatus::Expired,
                DocumentStatus::ExpiringSoon,
            ])
            ->map(fn ($document) => $document->documentType->name)
            ->values()
            ->all();
    }

    public function saveDraft(): void
    {
        $this->persistRequest(asDraft: true);
    }

    public function send(): void
    {
        $this->persistRequest(asDraft: false);
    }

    private function persistRequest(bool $asDraft): void
    {
        $this->authorize('create', DocumentRequest::class);

        $subcontractor = null;
        if ($this->subcontractorId !== null) {
            $subcontractor = Subcontractor::with('documents.documentType')->findOrFail($this->subcontractorId);
            $this->authorize('view', $subcontractor);
        }

        // Seules les catégories du sous-traitant peuvent être demandées
        $this->requested_docs = array_values(array_intersect($this->requested_docs, array_keys($this->available_docs)));

        if ($this->requested_docs === [] && $this->available_docs !== []) {
            $this->requested_docs = array_keys(array_filter(
                $this->available_docs,
                fn (string $status) => $status !== DocumentStatus::Valid->value
            ));
            if ($this->requested_docs === []) {
                $this->requested_docs = array_keys($this->available_docs);
            }
        }

        if ($subcontractor) {
            $request = new DocumentRequest([
                'subcontractor_id' => $subcontractor->id,
                'message' => trim($this->message),
                'due_date' => $this->deadline_date ?: now()->addDays(14)->toDateString(),
                'expires_at' => ($this->link_expiry_date ? \Carbon\Carbon::parse($this->link_expiry_date) : now()->addDays(30))->endOfDay(),
                'status' => $asDraft ? RequestStatus::Draft : RequestStatus::Sent,
                'auto_reminders' => $this->auto_reminders,
                'sent_at' => $asDraft ? null : now(),
                'created_by' => auth()->id(),
            ]);

            $plainToken = $request->issueToken();
            $request->save();

            $this->generated_link = LanInfoCommand::publicBaseUrl().route('portal.upload', ['token' => $plainToken], false);
            session()->flash("portal_token.{$request->id}", $plainToken);

            $matchingDocs = $subcontractor->documents->filter(
                fn ($doc) => $doc->documentType && in_array($doc->documentType->name, $this->requested_docs, true)
            );

            foreach ($matchingDocs as $doc) {
                $request->requestedDocuments()->create([
                    'document_type_id' => $doc->document_type_id,
                    'document_id' => $doc->id,
                    'status' => $asDraft ? $doc->status : DocumentStatus::Requested,
                    'due_date' => $request->due_date,
                ]);

                if (! $asDraft && in_array($doc->status, [DocumentStatus::Missing, DocumentStatus::Rejected], true)) {
                    $doc->update(['status' => DocumentStatus::Requested]);
                }
            }

            if (! $asDraft && $subcontractor->status === SubcontractorStatus::Draft) {
                $subcontractor->update(['status' => SubcontractorStatus::WaitingDocuments]);
            }

            app(AuditLogger::class)->log(
                action: $asDraft ? 'document_request.draft_saved' : 'document_request.sent',
                entity: $request,
                metadata: ['documents_count' => count($this->requested_docs)],
            );

            if (! $asDraft && $subcontractor->contact_email) {
                app(CompanyNotificationDispatcher::class)->logExternalMail(
                    company: auth()->user()->company,
                    type: 'document_request.sent',
                    recipientEmail: $subcontractor->contact_email,
                    subject: $request,
                );
            }

            $this->dispatch('request-updated', link: $this->generated_link);
        }

        $this->savedAsDraft = $asDraft;
        $this->sent = true;
    }

    public function resetModal(): void
    {
        $this->sent = false;
        $this->savedAsDraft = false;
    }

    public function render()
    {
        return view('livewire.app.requests.send-request-modal');
    }
}
