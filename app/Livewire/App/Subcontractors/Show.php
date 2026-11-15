<?php

namespace App\Livewire\App\Subcontractors;

use App\Console\Commands\LanInfoCommand;
use App\Domain\Audit\AuditLogger;
use App\Domain\Documents\DocumentStatusResolver;
use App\Domain\Documents\SimplePdfGenerator;
use App\Domain\Documents\SubcontractorCompletenessUpdater;
use App\Domain\Notifications\CompanyNotificationDispatcher;
use App\Domain\Subcontractors\SubcontractorPresenter;
use App\Enums\DocumentStatus;
use App\Enums\RequestStatus;
use App\Enums\SubcontractorStatus;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\DocumentTemplate;
use App\Models\DocumentVersion;
use App\Models\Subcontractor;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use ZipArchive;

#[Layout('layouts.app')]
class Show extends Component
{
    use WithFileUploads;

    private const TABS = ['documents', 'info', 'deadlines', 'history', 'comments'];

    #[Locked]
    public int $id;

    #[Url]
    public string $tab = 'documents';

    public string $new_comment = '';
    public ?string $toastMessage = null;
    public ?string $lastGeneratedLink = null;
    public bool $showArchivedDocuments = false;

    // Modale de dépôt interne de document (§9.10)
    public bool $showUploadModal = false;
    #[Locked]
    public ?int $uploadDocumentId = null;
    public string $uploadDocumentName = '';
    public $internal_file = null;
    public string $internal_issue_date = '';
    public string $internal_expiry_date = '';
    public string $internal_upload_comment = '';

    // Application d'un modèle
    public ?int $selected_template_id = null;

    public function mount(int $id): void
    {
        // Le CompanyScope filtre déjà par entreprise : un ID étranger donne 404
        $subcontractor = Subcontractor::findOrFail($id);
        $this->authorize('view', $subcontractor);

        $this->id = $subcontractor->id;
        $this->tab = in_array($this->tab, self::TABS, true) ? $this->tab : 'documents';

        $activeReq = $this->activeRequest($subcontractor);
        if ($activeReq && session()->has("portal_token.{$activeReq->id}")) {
            $token = (string) session()->pull("portal_token.{$activeReq->id}");
            $this->lastGeneratedLink = rtrim((string) config('app.url'), '/').route('portal.upload', ['token' => $token], false);
        }
    }

    #[On('request-updated')]
    public function onRequestUpdated(?string $link = null): void
    {
        if ($link) {
            $this->lastGeneratedLink = LanInfoCommand::normalizeUrl($link);
        }
        $this->toastMessage = 'Demande de documents mise à jour.';
    }

    public function addComment(AuditLogger $audit): void
    {
        $subcontractor = $this->subcontractor();
        $this->authorize('update', $subcontractor);

        $this->validate(['new_comment' => ['required', 'string', 'max:2000']], [
            'new_comment.required' => 'Le commentaire est vide.',
        ]);

        $entry = sprintf('[%s] %s : %s', now()->format('d/m/Y H:i'), auth()->user()->full_name, trim($this->new_comment));
        $subcontractor->update(['notes' => trim(($subcontractor->notes ? $subcontractor->notes."\n\n" : '').$entry)]);
        $audit->log('subcontractor.note_added', $subcontractor);

        $this->reset('new_comment');
        $this->toastMessage = 'Note interne ajoutée.';
    }

    public function disableDepositLink(AuditLogger $audit): void
    {
        $subcontractor = $this->subcontractor();
        $this->authorize('update', $subcontractor);

        $request = $this->activeRequest($subcontractor);

        if ($request) {
            $request->update(['status' => RequestStatus::Revoked, 'revoked_at' => now()]);
            $audit->log('document_request.revoked', $request);
            $this->lastGeneratedLink = null;
            $this->toastMessage = 'Le lien de dépôt a été désactivé.';
        }
    }

    public function resendDepositLink(AuditLogger $audit, CompanyNotificationDispatcher $notifications): void
    {
        $subcontractor = $this->subcontractor();
        $this->authorize('update', $subcontractor);

        $request = $this->activeRequest($subcontractor) ?? $this->draftRequest($subcontractor);

        if (! $request) {
            return;
        }

        $plainToken = $request->issueToken();
        $request->status = RequestStatus::Sent;
        $request->sent_at = $request->sent_at ?? now();
        $request->last_reminder_at = now();
        $request->revoked_at = null;
        $request->expires_at = now()->addDays((int) config('docpilot.portal.link_days', 30))->endOfDay();
        $request->save();

        $this->lastGeneratedLink = LanInfoCommand::publicBaseUrl().route('portal.upload', ['token' => $plainToken], false);

        if ($subcontractor->contact_email) {
            $notifications->logExternalMail(auth()->user()->company, 'document_request.resent', $subcontractor->contact_email, $request);
        }

        $audit->log('document_request.resent', $request);
        $this->toastMessage = "Lien de dépôt régénéré et renvoyé à {$subcontractor->contact_email}.";
    }

    public function triggerReminder(AuditLogger $audit, CompanyNotificationDispatcher $notifications): void
    {
        $subcontractor = $this->subcontractor();
        $this->authorize('remind', $subcontractor);

        $activeRequest = $this->activeRequest($subcontractor);
        $activeRequest?->update(['last_reminder_at' => now()]);

        if ($subcontractor->contact_email) {
            $notifications->logExternalMail(auth()->user()->company, 'reminder_sent', $subcontractor->contact_email, $subcontractor);
        }

        $notifications->notifyInternal(
            company: auth()->user()->company,
            eventKey: 'reminder_sent',
            title: 'Relance envoyée',
            text: "Une relance a été envoyée à {$subcontractor->legal_name}.",
            icon: 'paper-airplane',
            color: 'blue',
            subcontractor: $subcontractor,
            subject: $subcontractor,
        );

        $audit->log('subcontractor.reminded', $subcontractor);
        $this->toastMessage = "Relance envoyée à {$subcontractor->legal_name} ({$subcontractor->contact_email}).";
    }

    public function archiveSubcontractor(AuditLogger $audit): void
    {
        $subcontractor = $this->subcontractor();
        $this->authorize('archive', $subcontractor);

        $subcontractor->update([
            'archived_at' => now(),
            'status' => SubcontractorStatus::Archived,
        ]);

        $audit->log('subcontractor.archived', $subcontractor);
        $this->toastMessage = 'Sous-traitant archivé.';
    }

    public function restoreSubcontractor(AuditLogger $audit, SubcontractorCompletenessUpdater $completeness): void
    {
        $subcontractor = $this->subcontractor();
        $this->authorize('archive', $subcontractor);

        $subcontractor->update([
            'archived_at' => null,
            'status' => SubcontractorStatus::Incomplete,
        ]);
        $completeness->refresh($subcontractor);

        $audit->log('subcontractor.restored', $subcontractor);
        $this->toastMessage = 'Sous-traitant restauré.';
    }

    public function applyTemplate(AuditLogger $audit, SubcontractorCompletenessUpdater $completeness): void
    {
        if (! $this->selected_template_id) {
            return;
        }

        $subcontractor = $this->subcontractor();
        $this->authorize('update', $subcontractor);

        $template = DocumentTemplate::with('documentTypes')->findOrFail($this->selected_template_id);
        $added = 0;

        foreach ($template->documentTypes as $type) {
            $exists = Document::query()
                ->where('subcontractor_id', $subcontractor->id)
                ->where('document_type_id', $type->id)
                ->exists();

            if (! $exists) {
                Document::create([
                    'subcontractor_id' => $subcontractor->id,
                    'document_type_id' => $type->id,
                    'status' => DocumentStatus::Missing,
                ]);
                $added++;
            }
        }

        $completeness->refresh($subcontractor);
        $audit->log('template.applied', $subcontractor, [
            'template_id' => $template->id,
            'documents_added' => $added,
        ]);

        $this->selected_template_id = null;
        $this->toastMessage = "Modèle « {$template->name} » appliqué ({$added} document(s) ajouté(s)).";
    }

    public function openUploadModal(int $documentId): void
    {
        $document = Document::with('documentType')->where('subcontractor_id', $this->id)->findOrFail($documentId);
        $this->authorize('create', Document::class);

        $this->uploadDocumentId = $document->id;
        $this->uploadDocumentName = $document->documentType?->name ?? 'Document';
        $this->internal_file = null;
        $this->internal_issue_date = $document->issue_date?->format('Y-m-d') ?? now()->format('Y-m-d');
        $this->internal_expiry_date = $document->expiration_date?->format('Y-m-d')
            ?? ($document->documentType?->default_validity_months ? now()->addMonths($document->documentType->default_validity_months)->format('Y-m-d') : '');
        $this->internal_upload_comment = '';
        $this->resetErrorBag();
        $this->showUploadModal = true;
    }

    public function uploadInternalDocument(
        AuditLogger $audit,
        DocumentStatusResolver $resolver,
        SubcontractorCompletenessUpdater $completeness,
    ): void {
        $document = Document::with(['documentType', 'currentVersion'])
            ->where('subcontractor_id', $this->id)
            ->findOrFail($this->uploadDocumentId);
        $this->authorize('create', Document::class);

        $maxKb = (int) (($document->documentType?->max_file_size_mb ?? config('docpilot.max_upload_mb', 10)) * 1024);
        $exts = $document->documentType?->allowed_extensions ?: ['pdf', 'jpg', 'jpeg', 'png'];

        $this->validate([
            'internal_file' => ['required', 'file', 'mimes:'.implode(',', $exts), "max:{$maxKb}"],
            'internal_issue_date' => ['nullable', 'date'],
            'internal_expiry_date' => [$document->documentType?->has_expiration_date ? 'required' : 'nullable', 'date'],
            'internal_upload_comment' => ['nullable', 'string', 'max:1000'],
        ], [
            'internal_file.required' => 'Veuillez sélectionner un fichier.',
            'internal_file.mimes' => 'Format non autorisé (formats acceptés : '.strtoupper(implode(', ', $exts)).').',
            'internal_expiry_date.required' => 'La date d\'expiration est obligatoire pour cette catégorie.',
        ]);

        $disk = config('docpilot.uploads.disk', 'documents');
        $previousVersion = $document->currentVersion;
        $nextNumber = ((int) $document->versions()->max('version_number')) + 1;
        $ext = strtolower($this->internal_file->getClientOriginalExtension() ?: 'pdf');
        $fileName = "v{$nextNumber}-".Str::uuid().".{$ext}";
        $dir = "companies/{$document->company_id}/documents/{$document->id}";
        $storedPath = $this->internal_file->storeAs($dir, $fileName, $disk);

        $expiration = $this->internal_expiry_date ? new DateTimeImmutable($this->internal_expiry_date) : null;
        $resolvedStatus = $resolver->resolve(DocumentStatus::Valid, $expiration, new DateTimeImmutable('today'));

        $version = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => $nextNumber,
            'file_name' => $fileName,
            'original_name' => $this->internal_file->getClientOriginalName(),
            'file_path' => $storedPath,
            'disk' => $disk,
            'mime_type' => $this->internal_file->getMimeType() ?? 'application/pdf',
            'file_size' => $this->internal_file->getSize() ?: 1024,
            'sha256' => @hash_file('sha256', $this->internal_file->getRealPath()) ?: hash('sha256', $storedPath),
            'status' => $resolvedStatus,
            'uploaded_by_type' => 'user',
            'uploaded_by_id' => auth()->id(),
            'comment' => trim($this->internal_upload_comment) ?: null,
        ]);

        if ($previousVersion) {
            $previousVersion->update([
                'status' => DocumentStatus::Replaced,
                'replaced_at' => now(),
                'replaced_by' => $version->id,
                'replacement_reason' => 'Dépôt interne d\'une nouvelle version',
            ]);
        }

        $document->update([
            'current_version_id' => $version->id,
            'status' => $resolvedStatus,
            'issue_date' => $this->internal_issue_date ?: null,
            'expiration_date' => $this->internal_expiry_date ?: null,
            'validated_at' => now(),
            'validated_by' => auth()->id(),
            'rejection_reason_code' => null,
            'rejection_comment' => null,
        ]);

        $completeness->refresh($this->subcontractor());
        $audit->log('document.uploaded_internally', $document, [
            'version' => $nextNumber,
            'file' => $version->original_name,
        ]);

        $this->showUploadModal = false;
        $this->internal_file = null;
        $this->toastMessage = "Document « {$this->uploadDocumentName} » (v{$nextNumber}) déposé et validé.";
    }

    public function archiveDocument(int $documentId, AuditLogger $audit, SubcontractorCompletenessUpdater $completeness): void
    {
        $document = Document::where('subcontractor_id', $this->id)->findOrFail($documentId);
        $this->authorize('archive', $document);

        $document->update([
            'archived_at' => now(),
            'status' => DocumentStatus::Archived,
        ]);

        $completeness->refresh($this->subcontractor());
        $audit->log('document.archived', $document);
        $this->toastMessage = 'Document archivé.';
    }

    public function restoreDocument(int $documentId, AuditLogger $audit, SubcontractorCompletenessUpdater $completeness): void
    {
        $document = Document::where('subcontractor_id', $this->id)->findOrFail($documentId);
        $this->authorize('archive', $document);

        $document->update([
            'archived_at' => null,
            'status' => $document->current_version_id ? DocumentStatus::Valid : DocumentStatus::Missing,
        ]);

        $completeness->refresh($this->subcontractor());
        $audit->log('document.restored', $document);
        $this->toastMessage = 'Document restauré.';
    }

    public function exportPdf(AuditLogger $audit)
    {
        $subcontractor = $this->subcontractor()->load(['internalOwner', 'documents.documentType', 'documents.currentVersion']);
        $this->authorize('view', $subcontractor);

        $lines = [
            'Entreprise : '.auth()->user()->company->name,
            'Date d\'edition : '.now()->format('d/m/Y H:i'),
            '------------------------------------------------------------------------',
            'SOUS-TRAITANT : '.$subcontractor->legal_name,
            'SIRET : '.($subcontractor->registration_number ?? '-').' | Activite : '.($subcontractor->activity ?? '-'),
            'Contact : '.($subcontractor->contact_name ?: '-').' ('.$subcontractor->contact_email.')',
            'Responsable interne : '.($subcontractor->internalOwner?->full_name ?? '-'),
            'Score de completude : '.$subcontractor->completeness_score.'% | Statut : '.$subcontractor->status->label(),
            '------------------------------------------------------------------------',
            'LISTE DES DOCUMENTS DU DOSSIER :',
        ];

        foreach ($subcontractor->documents as $doc) {
            $typeName = $doc->documentType?->name ?? 'Document';
            $req = $doc->documentType?->is_mandatory ? 'Obligatoire' : 'Optionnel';
            $exp = $doc->expiration_date ? $doc->expiration_date->format('d/m/Y') : 'Sans echeance';
            $ver = $doc->currentVersion ? 'v'.$doc->currentVersion->version_number : '-';
            $lines[] = sprintf('- %s [%s] : %s | Exp: %s | %s', $typeName, $req, $doc->status->label(), $exp, $ver);
        }

        $pdfBytes = SimplePdfGenerator::fromLines('SYNTHESE DE CONFORMITE - '.$subcontractor->legal_name, $lines);
        $filename = 'synthese-'.Str::slug($subcontractor->legal_name).'.pdf';

        $audit->log('subcontractor.exported_pdf', $subcontractor);

        return response()->streamDownload(function () use ($pdfBytes) {
            echo $pdfBytes;
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    public function exportZip(AuditLogger $audit)
    {
        $subcontractor = $this->subcontractor()->load(['documents.documentType', 'documents.currentVersion']);
        $this->authorize('view', $subcontractor);

        $tmpFile = tempnam(sys_get_temp_dir(), 'docpilot_zip_');
        $zip = new ZipArchive;
        $zip->open($tmpFile, ZipArchive::OVERWRITE);

        $summaryLines = [
            "DOSSIER SOUS-TRAITANT : {$subcontractor->legal_name}",
            "SIRET : {$subcontractor->registration_number}",
            "Complétude : {$subcontractor->completeness_score}%",
            "Généré le : ".now()->format('d/m/Y H:i'),
            "",
            "PIÈCES DU DOSSIER :",
        ];

        foreach ($subcontractor->documents as $doc) {
            $typeName = $doc->documentType?->name ?? 'Document';
            $summaryLines[] = "- {$typeName} : {$doc->status->label()}";

            if ($version = $doc->currentVersion) {
                $disk = $version->disk ?: config('docpilot.uploads.disk', 'documents');
                $safeName = Str::slug($typeName).'-v'.$version->version_number.'.pdf';

                if (Storage::disk($disk)->exists($version->file_path)) {
                    $zip->addFromString($safeName, Storage::disk($disk)->get($version->file_path));
                } else {
                    $pdfContent = SimplePdfGenerator::fromLines($typeName, [
                        'Sous-traitant : '.$subcontractor->legal_name,
                        'Document : '.$typeName,
                        'Version : v'.$version->version_number,
                        'Statut : '.$doc->status->label(),
                        'Expiration : '.($doc->expiration_date?->format('d/m/Y') ?? '-'),
                    ]);
                    $zip->addFromString($safeName, $pdfContent);
                }
            }
        }

        $zip->addFromString('synthese-dossier.txt', implode("\n", $summaryLines));
        $zip->close();

        $audit->log('subcontractor.exported_zip', $subcontractor);
        $filename = 'dossier-'.Str::slug($subcontractor->legal_name).'.zip';

        return response()->streamDownload(function () use ($tmpFile) {
            readfile($tmpFile);
            @unlink($tmpFile);
        }, $filename, ['Content-Type' => 'application/zip']);
    }

    public function render()
    {
        if ($this->lastGeneratedLink) {
            $this->lastGeneratedLink = LanInfoCommand::normalizeUrl($this->lastGeneratedLink);
        }

        $subcontractor = $this->subcontractor()->load([
            'internalOwner',
            'documents' => fn ($q) => $q
                ->when(! $this->showArchivedDocuments, fn ($sq) => $sq->whereNull('archived_at'))
                ->with(['documentType', 'currentVersion'])
                ->orderBy('id'),
        ]);
        $documents = $subcontractor->documents;
        $request = $this->activeRequest($subcontractor);
        $draftRequest = $this->draftRequest($subcontractor);

        return view('livewire.app.subcontractors.show', [
            'subcontractor' => [
                'name' => $subcontractor->legal_name,
                'initials' => SubcontractorPresenter::initials($subcontractor->legal_name),
                'status' => SubcontractorPresenter::badgeStatus($subcontractor->status),
                'is_archived' => $subcontractor->archived_at !== null,
                'activity' => $subcontractor->activity ?? '—',
                'city' => $subcontractor->city ?? '—',
                'siret' => $subcontractor->registration_number ?? '—',
                'completeness' => (int) $subcontractor->completeness_score,
                'contact_name' => $subcontractor->contact_name ?: '—',
                'contact_email' => $subcontractor->contact_email,
                'contact_phone' => $subcontractor->contact_phone ?? '—',
                'address' => trim(implode(' ', array_filter([$subcontractor->address, $subcontractor->postal_code, $subcontractor->city]))) ?: '—',
                'manager' => $subcontractor->internalOwner?->full_name ?? '—',
                'notes' => $subcontractor->notes,
                'deposit_link_active' => $request !== null,
                'deposit_link_sent_at' => $request?->sent_at?->translatedFormat('j F Y') ?? '—',
                'deposit_link_expires_at' => $request?->expires_at?->translatedFormat('j F Y') ?? '—',
                'has_draft_request' => $draftRequest !== null,
            ],
            'documents' => $documents->map(fn (Document $document) => [
                'id' => $document->id,
                'name' => $document->documentType?->name ?? 'Document',
                'required' => (bool) $document->documentType?->is_mandatory,
                'status' => $document->status->value,
                'is_archived' => $document->archived_at !== null,
                'has_file' => $document->current_version_id !== null,
                'expires_at' => $document->expiration_date?->format('d/m/Y') ?? '—',
                'version' => $document->currentVersion ? 'v'.$document->currentVersion->version_number : '—',
                'updated_at' => $document->updated_at?->format('d/m/Y') ?? '—',
            ]),
            'canEdit' => auth()->user()->can('update', $subcontractor),
            'canArchive' => auth()->user()->can('archive', $subcontractor),
            'validCount' => $documents->whereIn('status', [DocumentStatus::Valid, DocumentStatus::ExpiringSoon])->count(),
            'deadlines' => $documents->whereNotNull('expiration_date')->sortBy('expiration_date')->values(),
            'history' => $this->history($subcontractor, $documents->modelKeys()),
            'comments' => $this->internalComments($documents->modelKeys()),
            'templates' => DocumentTemplate::orderBy('name')->get(['id', 'name']),
        ])->title("Fiche sous-traitant - {$subcontractor->legal_name}");
    }

    private function subcontractor(): Subcontractor
    {
        return Subcontractor::findOrFail($this->id);
    }

    private function activeRequest(Subcontractor $subcontractor)
    {
        return $subcontractor->documentRequests()
            ->where('status', RequestStatus::Sent)
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('sent_at')
            ->first();
    }

    private function draftRequest(Subcontractor $subcontractor)
    {
        return $subcontractor->documentRequests()
            ->where('status', RequestStatus::Draft)
            ->whereNull('revoked_at')
            ->latest('created_at')
            ->first();
    }

    private function history(Subcontractor $subcontractor, array $documentIds)
    {
        $logs = AuditLog::query()
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('entity_type', 'subcontractor')->where('entity_id', $subcontractor->id))
                ->orWhere(fn ($q) => $q->where('entity_type', 'document')->whereIn('entity_id', $documentIds ?: [0])))
            ->latest('created_at')
            ->limit(25)
            ->get();

        $users = $this->companyUsers($logs->where('user_type', 'user')->pluck('user_id')->filter()->all());

        return $logs->map(fn (AuditLog $log) => [
            'action' => $log->action,
            'author' => $log->user_type === 'user' ? ($users[$log->user_id] ?? 'Utilisateur') : ($log->user_type === 'system' ? 'Système' : 'Sous-traitant'),
            'date' => $log->created_at?->format('d/m/Y à H:i'),
        ]);
    }

    private function internalComments(array $documentIds)
    {
        $comments = DocumentComment::query()
            ->with('document.documentType')
            ->whereIn('document_id', $documentIds ?: [0])
            ->where('visibility', DocumentComment::VISIBILITY_INTERNAL)
            ->latest()
            ->get();

        $users = $this->companyUsers($comments->where('author_type', 'user')->pluck('author_id')->filter()->all());

        return $comments->map(fn (DocumentComment $comment) => [
            'author' => $users[$comment->author_id] ?? 'Sous-traitant',
            'date' => $comment->created_at?->format('d/m/Y à H:i'),
            'text' => $comment->body,
            'document' => $comment->document?->documentType?->name,
        ]);
    }

    /** Users n'a pas de CompanyScope : filtre explicite sur l'entreprise courante. */
    private function companyUsers(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return User::query()
            ->where('company_id', auth()->user()->company_id)
            ->whereIn('id', $ids)
            ->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(fn (User $user) => [$user->id => $user->full_name])
            ->all();
    }
}
