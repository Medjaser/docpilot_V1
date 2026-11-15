<?php

namespace App\Livewire\App\Documents;

use App\Domain\Audit\AuditLogger;
use App\Domain\Documents\DocumentStatusResolver;
use App\Domain\Documents\SubcontractorCompletenessUpdater;
use App\Domain\Notifications\CompanyNotificationDispatcher;
use App\Enums\DocumentStatus;
use App\Enums\RejectionReason;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\Subcontractor;
use App\Models\User;
use DateTimeImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Examen du document - DocPilot')]
class Review extends Component
{
    #[Locked]
    public int $id = 1;

    public string $filename = 'Assurance décennale 2026.pdf';

    public string $status = 'pending';

    public string $subcontractor_name = 'ÉlecPro Services';

    #[Locked]
    public int $subcontractor_id = 1;

    public string $category = 'Assurance Décennale';

    public string $issue_date = '';

    public string $expiry_date = '';

    public string $submitted_at = '';

    public string $version = 'v1';

    public string $submitted_by = '';

    public string $submitter_comment = '';

    public ?string $rejection_reason_display = null;

    public ?string $rejection_comment_display = null;

    public bool $is_archived = false;

    public string $internal_comment = '';

    public string $subcontractor_comment = '';

    public array $versionsList = [];

    public array $commentsList = [];

    // Rejection Modal properties
    public bool $showRejectModal = false;

    public string $rejection_reason = '';

    public string $rejection_message = '';

    public ?string $toastMessage = null;

    public function mount(int $id = 1): void
    {
        $document = Document::findOrFail($id);
        $this->authorize('view', $document);
        $this->id = $document->id;
        $this->subcontractor_id = $document->subcontractor_id;
        $this->loadDocumentData($document);
    }

    public function saveDates(
        DocumentStatusResolver $statusResolver,
        SubcontractorCompletenessUpdater $completenessUpdater,
        AuditLogger $audit,
    ): void {
        $document = $this->authorizeDocumentAction('validate');

        $validated = $this->validate([
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
        ]);

        $oldDates = [
            'issue_date' => $document->issue_date?->toDateString(),
            'expiration_date' => $document->expiration_date?->toDateString(),
        ];

        $document->issue_date = $validated['issue_date'] ?: null;
        $document->expiration_date = $validated['expiry_date'] ?: null;

        if (in_array($document->status, [DocumentStatus::Valid, DocumentStatus::ExpiringSoon, DocumentStatus::Expired], true)) {
            $expDate = $document->expiration_date
                ? DateTimeImmutable::createFromInterface($document->expiration_date)
                : null;
            $document->status = $statusResolver->resolve(
                DocumentStatus::Valid,
                $expDate,
                new DateTimeImmutable('today')
            );
        }

        $document->save();

        if ($document->subcontractor) {
            $completenessUpdater->refresh($document->subcontractor);
        }

        $audit->log('document.dates_updated', $document, [
            'old' => $oldDates,
            'new' => [
                'issue_date' => $document->issue_date?->toDateString(),
                'expiration_date' => $document->expiration_date?->toDateString(),
            ],
        ]);

        $this->loadDocumentData($document->fresh());
        $this->toastMessage = 'Les dates du document ont été mises à jour.';
    }

    public function saveComments(AuditLogger $audit, CompanyNotificationDispatcher $dispatcher): void
    {
        $document = $this->authorizeDocumentAction('validate');

        $added = false;

        if (trim($this->internal_comment) !== '') {
            DocumentComment::create([
                'document_id' => $document->id,
                'author_type' => 'user',
                'author_id' => auth()->id(),
                'body' => trim($this->internal_comment),
                'visibility' => DocumentComment::VISIBILITY_INTERNAL,
            ]);
            $this->internal_comment = '';
            $added = true;
        }

        if (trim($this->subcontractor_comment) !== '') {
            $externalBody = trim($this->subcontractor_comment);
            DocumentComment::create([
                'document_id' => $document->id,
                'author_type' => 'user',
                'author_id' => auth()->id(),
                'body' => $externalBody,
                'visibility' => DocumentComment::VISIBILITY_EXTERNAL,
            ]);

            if ($document->subcontractor?->contact_email) {
                $dispatcher->sendEmailToSubcontractor(
                    auth()->user()->company,
                    $document->subcontractor,
                    'document.comment_external',
                    [
                        'nom_document' => $document->documentType?->name ?? $this->filename,
                        'commentaire' => $externalBody,
                    ]
                );
            }

            $this->subcontractor_comment = '';
            $added = true;
        }

        if ($added) {
            $audit->log('document.commented', $document);
            $this->loadDocumentData($document->fresh());
            $this->toastMessage = 'Vos commentaires ont été enregistrés.';
        }
    }

    public function validateDocument(
        DocumentStatusResolver $statusResolver,
        SubcontractorCompletenessUpdater $completenessUpdater,
        CompanyNotificationDispatcher $dispatcher,
        AuditLogger $audit,
    ): void {
        $document = $this->authorizeDocumentAction('validate');

        if ($this->issue_date !== '') {
            $document->issue_date = $this->issue_date;
        }
        if ($this->expiry_date !== '') {
            $document->expiration_date = $this->expiry_date;
        }

        $docType = $document->documentType;
        $expDate = $document->expiration_date
            ? DateTimeImmutable::createFromInterface(\Carbon\Carbon::parse($document->expiration_date))
            : null;

        $computedStatus = $statusResolver->resolve(
            DocumentStatus::Valid,
            $expDate,
            new DateTimeImmutable('today')
        );

        $document->update([
            'issue_date' => $document->issue_date,
            'expiration_date' => $document->expiration_date,
            'status' => $computedStatus,
            'validated_at' => now(),
            'validated_by' => auth()->id(),
            'rejection_reason_code' => null,
            'rejection_comment' => null,
        ]);

        if ($document->currentVersion) {
            $document->currentVersion->update(['status' => DocumentStatus::Valid]);
        }

        $this->persistPendingComments($document);

        if ($document->subcontractor) {
            $completenessUpdater->refresh($document->subcontractor);
        }

        $audit->log('document.validated', $document, [
            'document_type' => $docType?->name,
            'subcontractor' => $document->subcontractor?->legal_name,
            'status' => $computedStatus->value,
        ]);

        if ($document->subcontractor?->contact_email) {
            $dispatcher->sendEmailToSubcontractor(
                auth()->user()->company,
                $document->subcontractor,
                'document.validated',
                [
                    'nom_document' => $docType?->name ?? $this->filename,
                    'date_expiration' => $document->expiration_date?->format('d/m/Y') ?? '-',
                ]
            );
        }

        $this->loadDocumentData($document->fresh());
        $this->status = 'valid';
        $this->toastMessage = "Le document {$this->filename} a été validé avec succès !";
    }

    public function openRejectModal(): void
    {
        $this->authorizeDocumentAction('reject');

        $this->showRejectModal = true;
    }

    public function confirmReject(
        SubcontractorCompletenessUpdater $completenessUpdater,
        CompanyNotificationDispatcher $dispatcher,
        AuditLogger $audit,
    ): void {
        $document = $this->authorizeDocumentAction('reject');

        $this->validate([
            'rejection_reason' => 'required|string',
        ], [
            'rejection_reason.required' => 'Le motif de rejet est OBLIGATOIRE.',
        ]);

        $reasonEnum = $this->resolveRejectionReason($this->rejection_reason);

        $document->update([
            'status' => DocumentStatus::Rejected,
            'rejection_reason_code' => $reasonEnum,
            'rejection_comment' => $this->rejection_message ?: null,
            'validated_at' => null,
            'validated_by' => null,
        ]);

        if ($document->currentVersion) {
            $document->currentVersion->update(['status' => DocumentStatus::Rejected]);
        }

        if (trim($this->rejection_message) !== '') {
            DocumentComment::create([
                'document_id' => $document->id,
                'author_type' => 'user',
                'author_id' => auth()->id(),
                'body' => 'Motif de rejet ('.$reasonEnum->label().') : '.trim($this->rejection_message),
                'visibility' => DocumentComment::VISIBILITY_EXTERNAL,
            ]);
        }

        $this->persistPendingComments($document);

        if ($document->subcontractor) {
            $completenessUpdater->refresh($document->subcontractor);
        }

        $company = auth()->user()->company;
        if ($company && $document->subcontractor) {
            $dispatcher->sendEmailToSubcontractor(
                $company,
                $document->subcontractor,
                'document.rejected',
                [
                    'nom_document' => $document->documentType?->name ?? $this->filename,
                    'motif_rejet' => $reasonEnum->label().($this->rejection_message ? ' - '.$this->rejection_message : ''),
                ]
            );

            $dispatcher->notifyCompanyUsers(
                $company,
                'document.rejected',
                'Document rejeté — '.$document->subcontractor->legal_name,
                "Le document « {$this->category} » a été rejeté ({$reasonEnum->label()}).",
                ['document_id' => $document->id, 'subcontractor_id' => $document->subcontractor_id],
                'rejected'
            );
        }

        $audit->log('document.rejected', $document, [
            'reason_code' => $reasonEnum->value,
            'reason_label' => $reasonEnum->label(),
            'comment' => $this->rejection_message,
        ]);

        $this->loadDocumentData($document->fresh());
        $this->status = 'rejected';
        $this->showRejectModal = false;
        $this->toastMessage = "Le document {$this->filename} a été rejeté (Motif: {$this->rejection_reason}).";
    }

    public function requestNewVersion(
        SubcontractorCompletenessUpdater $completenessUpdater,
        CompanyNotificationDispatcher $dispatcher,
        AuditLogger $audit,
    ): void {
        $document = $this->authorizeDocumentAction('requestNewVersion');

        $document->update([
            'status' => DocumentStatus::Requested,
        ]);

        $this->persistPendingComments($document);

        if ($document->subcontractor) {
            $completenessUpdater->refresh($document->subcontractor);
        }

        $company = auth()->user()->company;
        if ($company && $document->subcontractor) {
            $dispatcher->sendEmailToSubcontractor(
                $company,
                $document->subcontractor,
                'document.new_version_requested',
                [
                    'nom_document' => $document->documentType?->name ?? $this->filename,
                ]
            );
        }

        $audit->log('document.new_version_requested', $document, [
            'document_type' => $document->documentType?->name,
            'subcontractor' => $document->subcontractor?->legal_name,
        ]);

        $this->loadDocumentData($document->fresh());
        $this->status = 'requested';
        $this->toastMessage = "Une demande de nouvelle version a été envoyée pour {$this->filename}.";
    }

    public function toggleArchiveDocument(
        SubcontractorCompletenessUpdater $completenessUpdater,
        AuditLogger $audit,
    ): void {
        $document = $this->authorizeDocumentAction('validate');

        if ($document->archived_at) {
            $document->update(['archived_at' => null]);
            $audit->log('document.restored', $document);
            $this->toastMessage = "Le document {$this->filename} a été restauré.";
        } else {
            $document->update(['archived_at' => now()]);
            $audit->log('document.archived', $document);
            $this->toastMessage = "Le document {$this->filename} a été archivé.";
        }

        if ($document->subcontractor) {
            $completenessUpdater->refresh($document->subcontractor);
        }

        $this->loadDocumentData($document->fresh());
    }

    public function nextDocument(): void
    {
        $document = Document::where('id', '>', $this->id)->orderBy('id')->firstOrFail();
        $this->authorize('view', $document);
        $this->id = $document->id;
        $this->subcontractor_id = $document->subcontractor_id;
        $this->loadDocumentData($document);
        $this->toastMessage = null;
    }

    public function previousDocument(): void
    {
        if ($this->id > 1) {
            $document = Document::where('id', '<', $this->id)->orderByDesc('id')->firstOrFail();
            $this->authorize('view', $document);
            $this->id = $document->id;
            $this->subcontractor_id = $document->subcontractor_id;
            $this->loadDocumentData($document);
        }
        $this->toastMessage = null;
    }

    public function render()
    {
        return view('livewire.app.documents.review');
    }

    private function loadDocumentData(Document $document): void
    {
        $document->loadMissing(['subcontractor', 'documentType', 'currentVersion.uploadedBy', 'versions.uploadedBy', 'comments.author']);

        $currentVersion = $document->currentVersion;
        $this->category = $document->documentType?->name ?? 'Document';
        $this->filename = $currentVersion?->original_name
            ?: ($currentVersion?->file_name ?: ($this->category.'.pdf'));
        $this->status = $document->status->value;
        $this->subcontractor_name = $document->subcontractor?->legal_name ?? 'Sous-traitant';
        $this->subcontractor_id = $document->subcontractor_id;
        $this->issue_date = $document->issue_date?->format('Y-m-d') ?? '';
        $this->expiry_date = $document->expiration_date?->format('Y-m-d') ?? '';
        $this->submitted_at = ($currentVersion?->created_at ?? $document->updated_at)?->format('d/m/Y \à H:i') ?? '-';
        $this->version = $currentVersion ? 'v'.$currentVersion->version_number : 'v1';
        $this->submitted_by = $this->resolveAuthorName($currentVersion?->uploadedBy, $document->subcontractor);
        $this->submitter_comment = $currentVersion?->comment ?? '';
        $this->rejection_reason_display = $document->rejection_reason_code?->label();
        $this->rejection_comment_display = $document->rejection_comment;
        $this->is_archived = $document->archived_at !== null;

        $this->versionsList = $document->versions->map(fn ($v) => [
            'id' => $v->id,
            'version_number' => 'v'.$v->version_number,
            'original_name' => $v->original_name ?: $v->file_name,
            'file_size' => round(($v->file_size ?? 0) / 1024, 1).' Ko',
            'status' => $v->status->value,
            'uploaded_at' => $v->created_at?->format('d/m/Y H:i') ?? '-',
            'uploaded_by' => $this->resolveAuthorName($v->uploadedBy, $document->subcontractor),
            'comment' => $v->comment,
        ])->all();

        $this->commentsList = $document->comments->map(fn (DocumentComment $c) => [
            'id' => $c->id,
            'body' => $c->body,
            'visibility' => $c->visibility,
            'author' => $this->resolveAuthorName($c->author, $document->subcontractor),
            'created_at' => $c->created_at?->format('d/m/Y H:i') ?? '-',
        ])->all();
    }

    private function resolveAuthorName(mixed $author, ?Subcontractor $fallbackSub = null): string
    {
        if ($author instanceof User) {
            return $author->full_name;
        }
        if ($author instanceof Subcontractor) {
            return $author->contact_name ?: $author->legal_name;
        }

        return $fallbackSub?->contact_name ?: ($fallbackSub?->legal_name ?? 'Équipe');
    }

    private function persistPendingComments(Document $document): void
    {
        if (trim($this->internal_comment) !== '') {
            DocumentComment::create([
                'document_id' => $document->id,
                'author_type' => 'user',
                'author_id' => auth()->id(),
                'body' => trim($this->internal_comment),
                'visibility' => DocumentComment::VISIBILITY_INTERNAL,
            ]);
            $this->internal_comment = '';
        }

        if (trim($this->subcontractor_comment) !== '') {
            DocumentComment::create([
                'document_id' => $document->id,
                'author_type' => 'user',
                'author_id' => auth()->id(),
                'body' => trim($this->subcontractor_comment),
                'visibility' => DocumentComment::VISIBILITY_EXTERNAL,
            ]);
            $this->subcontractor_comment = '';
        }
    }

    private function resolveRejectionReason(string $input): RejectionReason
    {
        $byValue = RejectionReason::tryFrom($input);
        if ($byValue) {
            return $byValue;
        }

        foreach (RejectionReason::cases() as $case) {
            if (mb_strtolower($case->label()) === mb_strtolower(trim($input))) {
                return $case;
            }
        }

        return RejectionReason::Other;
    }

    private function authorizeDocumentAction(string $ability): Document
    {
        $document = Document::findOrFail($this->id);
        $this->authorize('view', $document);
        abort_unless(auth()->user()->can($ability, $document), 403);

        return $document;
    }
}
