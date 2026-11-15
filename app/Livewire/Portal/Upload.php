<?php

namespace App\Livewire\Portal;

use App\Domain\Audit\AuditLogger;
use App\Domain\Documents\PortalFileProcessor;
use App\Domain\Documents\SubcontractorCompletenessUpdater;
use App\Domain\Notifications\CompanyNotificationDispatcher;
use App\Enums\DocumentStatus;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\DocumentRequest;
use App\Models\DocumentVersion;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.portal')]
#[Title('Dépôt de vos documents - DocPilot')]
class Upload extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $token = '';

    public bool $invalidToken = false;

    public bool $submittedSuccess = false;

    /** Entreprise suspendue, résiliée ou archivée : plus aucun dépôt possible. */
    #[Locked]
    public bool $portalDisabled = false;

    public string $company_name = '';

    public string $deadline = '';

    public string $manager_name = '';

    public string $manager_message = '';

    public array $documents = [];

    /** Fichiers téléversés par index de document avant confirmation. */
    public array $files = [];

    /** Métadonnées des fichiers déjà validés et retraités (protège contre les coupures réseau). */
    public array $stagedFiles = [];

    /** Messages d'erreur en français par index de document. */
    public array $fileErrors = [];

    public ?string $globalError = null;

    public array $transmittedNames = [];

    public function mount(string $token = ''): void
    {
        $this->token = $token;
        if ($token === '' || $token === 'invalid' || $token === 'expired') {
            $this->markTokenAsInvalid();

            return;
        }

        $request = DocumentRequest::findByToken($this->token);
        $company = $request?->company;

        if (! $request || ! $company) {
            $this->markTokenAsInvalid();

            return;
        }

        app(CurrentCompany::class)->set($company->id);
        $this->portalDisabled = $company->isReadOnly();

        if (! $this->portalDisabled && ! $request->isUsable()) {
            $this->markTokenAsInvalid();

            return;
        }

        $this->company_name = $company->trade_name ?: $company->name;
        $this->loadRequestDocuments($request);

        // Lu par layouts.portal (logo de l'entreprise dans l'en-tête uniquement si le lien est valide)
        view()->share('portalCompany', $company);
        view()->share('portalInvalidToken', false);
    }

    public function updatedFiles($file, $docIndex): void
    {
        $docIndex = (int) $docIndex;
        $this->globalError = null;
        unset($this->fileErrors[$docIndex]);
        $this->resetErrorBag();

        if (! $this->checkRateLimit()) {
            unset($this->files[$docIndex]);

            return;
        }

        if (! isset($this->documents[$docIndex]) || ! $file instanceof UploadedFile) {
            return;
        }

        $this->stageUploadedFile($docIndex, $file);
    }

    /**
     * Réception directe d'une photo prise au téléphone ou d'un fichier encodé en base64.
     * Passe par une requête JSON Livewire standard (contourne la limite upload_max_filesize=2M de php.ini)
     * tout en appliquant exactement les mêmes contrôles PortalFileProcessor (finfo, magic bytes, EXIF, HEIC).
     */
    public function uploadFromMobile(int $docIndex, string $dataUrl, string $originalName): void
    {
        $this->globalError = null;
        unset($this->fileErrors[$docIndex]);
        $this->resetErrorBag();

        if (! $this->checkRateLimit() || ! isset($this->documents[$docIndex])) {
            return;
        }

        $base64 = (string) preg_replace('#^data:[^;]+;base64,#i', '', $dataUrl);
        $binary = base64_decode($base64, true);

        if ($binary === false || $binary === '') {
            $this->reportClientError($docIndex, PortalFileProcessor::ERROR_INVALID_FILE);

            return;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'dp_mob_');
        if ($tmpPath === false) {
            $this->reportClientError($docIndex, 'Erreur temporaire lors de la réception du fichier.');

            return;
        }

        try {
            file_put_contents($tmpPath, $binary);
            $uploadedFile = new UploadedFile(
                path: $tmpPath,
                originalName: $originalName !== '' ? $originalName : 'photo-document.jpg',
                mimeType: null,
                error: \UPLOAD_ERR_OK,
                test: true,
            );

            $this->stageUploadedFile($docIndex, $uploadedFile);
        } finally {
            if (is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    public function reportClientError(int $docIndex, string $message): void
    {
        unset($this->files[$docIndex]);
        $this->fileErrors[$docIndex] = $message;
        $this->addError("files.{$docIndex}", $message);
        $this->addError('files', $message);
    }

    private function stageUploadedFile(int $docIndex, UploadedFile $file): void
    {
        $docMeta = $this->documents[$docIndex];
        $maxMb = (int) min(
            (int) config('docpilot.max_upload_mb', 10),
            (int) ($docMeta['max_mb'] ?? 10)
        );
        $allowedExt = $docMeta['allowed_extensions'] ?? ['pdf', 'jpg', 'jpeg', 'png'];

        try {
            $processed = app(PortalFileProcessor::class)->processUploadedFile(
                file: $file,
                maxMb: $maxMb,
                allowedExtensions: $allowedExt,
            );
        } catch (InvalidArgumentException $e) {
            unset($this->files[$docIndex]);
            $this->fileErrors[$docIndex] = $e->getMessage();
            $this->addError("files.{$docIndex}", $e->getMessage());
            $this->addError('files', $e->getMessage());

            return;
        }

        $disk = config('docpilot.uploads.disk', 'local');
        $stagedPath = 'staged/'.Str::uuid().'.'.$processed['extension'];
        Storage::disk($disk)->put($stagedPath, $processed['contents']);

        $previewUrl = null;
        if (str_starts_with($processed['mime_type'], 'image/') && strlen($processed['contents']) <= 2 * 1024 * 1024) {
            $previewUrl = 'data:'.$processed['mime_type'].';base64,'.base64_encode($processed['contents']);
        }

        $this->stagedFiles[$docIndex] = [
            'staged_path' => $stagedPath,
            'disk' => $disk,
            'mime_type' => $processed['mime_type'],
            'extension' => $processed['extension'],
            'original_name' => $processed['original_name'],
            'size' => $processed['size'],
            'sha256' => $processed['sha256'],
        ];

        $this->documents[$docIndex]['uploaded'] = true;
        $this->documents[$docIndex]['file_name'] = $processed['original_name'];
        $this->documents[$docIndex]['file_size_label'] = $this->formatBytes($processed['size']);
        $this->documents[$docIndex]['is_image'] = str_starts_with($processed['mime_type'], 'image/');
        $this->documents[$docIndex]['preview_url'] = $previewUrl;
    }

    public function removeFile(int $docIndex): void
    {
        if (isset($this->stagedFiles[$docIndex])) {
            $staged = $this->stagedFiles[$docIndex];
            Storage::disk($staged['disk'])->delete($staged['staged_path']);
            unset($this->stagedFiles[$docIndex]);
        }

        unset($this->files[$docIndex], $this->fileErrors[$docIndex]);

        if (isset($this->documents[$docIndex])) {
            $this->documents[$docIndex]['uploaded'] = false;
            $this->documents[$docIndex]['file_name'] = null;
            $this->documents[$docIndex]['file_size_label'] = null;
            $this->documents[$docIndex]['is_image'] = false;
            $this->documents[$docIndex]['preview_url'] = null;
        }
    }

    public function backToDocuments(): void
    {
        $request = DocumentRequest::findByToken($this->token);
        $company = $request?->company;

        if (! $request || ! $company || (! $company->isReadOnly() && ! $request->isUsable())) {
            $this->markTokenAsInvalid();

            return;
        }

        app(CurrentCompany::class)->set($company->id);
        $this->loadRequestDocuments($request);
        $this->submittedSuccess = false;
    }

    public function submitAll(
        AuditLogger $audit,
        CompanyNotificationDispatcher $notifications,
        PortalFileProcessor $processor,
        SubcontractorCompletenessUpdater $completenessUpdater,
    ): void {
        $request = DocumentRequest::findByToken($this->token);
        $company = $request?->company;

        abort_if($this->portalDisabled || $company?->isReadOnly(), 403, 'Ce portail de dépôt est désactivé.');

        if (! $request || ! $company || ! $request->isUsable()) {
            $this->markTokenAsInvalid();

            return;
        }

        if (! $this->checkRateLimit()) {
            return;
        }

        app(CurrentCompany::class)->set($company->id);
        $subcontractor = $request->subcontractor;
        $disk = config('docpilot.uploads.disk', 'local');
        $this->transmittedNames = [];
        $this->globalError = null;

        foreach ($this->documents as $index => $docData) {
            $staged = $this->stagedFiles[$index] ?? null;
            $rawFile = $this->files[$index] ?? null;

            $processedPayload = null;

            if ($staged && Storage::disk($staged['disk'])->exists($staged['staged_path'])) {
                $processedPayload = [
                    'contents' => (string) Storage::disk($staged['disk'])->get($staged['staged_path']),
                    'mime_type' => $staged['mime_type'],
                    'extension' => $staged['extension'],
                    'original_name' => $staged['original_name'],
                    'size' => $staged['size'],
                    'sha256' => $staged['sha256'],
                ];
            } elseif ($rawFile instanceof UploadedFile) {
                $maxMb = (int) min(
                    (int) config('docpilot.max_upload_mb', 10),
                    (int) ($docData['max_mb'] ?? 10)
                );
                $allowedExt = $docData['allowed_extensions'] ?? ['pdf', 'jpg', 'jpeg', 'png'];

                try {
                    $processedPayload = $processor->processUploadedFile($rawFile, $maxMb, $allowedExt);
                } catch (InvalidArgumentException $e) {
                    $this->fileErrors[$index] = $e->getMessage();
                    $this->addError("files.{$index}", $e->getMessage());
                    $this->addError('files', $e->getMessage());

                    return;
                }
            }

            $hasChanges = $processedPayload !== null || ! empty($docData['comment']) || ! empty($docData['expiry_date']);
            if (! $hasChanges && empty($docData['uploaded'])) {
                continue;
            }

            $document = Document::with(['documentType', 'currentVersion'])
                ->where('subcontractor_id', $request->subcontractor_id)
                ->find($docData['id']);

            if (! $document) {
                continue;
            }

            if ($processedPayload !== null) {
                $previous = $document->currentVersion;

                // Protection contre la double confirmation : si la même empreinte SHA-256 vient d'être déposée
                if (
                    $previous
                    && $previous->sha256 === $processedPayload['sha256']
                    && in_array($document->status, [DocumentStatus::Pending, DocumentStatus::Uploaded], true)
                ) {
                    $this->transmittedNames[] = $document->documentType?->name ?? $docData['name'];
                    continue;
                }

                $nextVersion = ((int) $document->versions()->max('version_number')) + 1;
                $ext = $processedPayload['extension'];
                $fileName = "v{$nextVersion}-".Str::uuid().".{$ext}";
                $storedPath = "companies/{$company->id}/documents/{$document->id}/{$fileName}";

                Storage::disk($disk)->put($storedPath, $processedPayload['contents']);

                if ($staged) {
                    Storage::disk($staged['disk'])->delete($staged['staged_path']);
                }

                $version = DocumentVersion::create([
                    'document_id' => $document->id,
                    'version_number' => $nextVersion,
                    'file_name' => $fileName,
                    'original_name' => $processedPayload['original_name'],
                    'file_path' => $storedPath,
                    'disk' => $disk,
                    'mime_type' => $processedPayload['mime_type'],
                    'file_size' => $processedPayload['size'],
                    'sha256' => $processedPayload['sha256'],
                    'status' => DocumentStatus::Pending,
                    'uploaded_by_type' => 'subcontractor',
                    'uploaded_by_id' => $subcontractor?->id,
                    'comment' => trim((string) ($docData['comment'] ?? '')) ?: null,
                ]);

                if ($previous) {
                    $previous->update([
                        'status' => DocumentStatus::Replaced,
                        'replaced_at' => now(),
                        'replaced_by' => $version->id,
                        'replacement_reason' => 'Nouveau dépôt via le portail sous-traitant',
                    ]);
                }

                $document->update([
                    'current_version_id' => $version->id,
                    'status' => DocumentStatus::Pending,
                    'issue_date' => ! empty($docData['issue_date']) ? $docData['issue_date'] : $document->issue_date,
                    'expiration_date' => ! empty($docData['expiry_date']) ? $docData['expiry_date'] : $document->expiration_date,
                    'rejection_reason_code' => null,
                    'rejection_comment' => null,
                ]);

                $request->requestedDocuments()
                    ->where('document_id', $document->id)
                    ->update(['status' => DocumentStatus::Pending]);

                $this->transmittedNames[] = $document->documentType?->name ?? $docData['name'];

                $audit->log(
                    action: 'document.uploaded',
                    entity: $document,
                    metadata: ['file' => $version->original_name, 'version' => $nextVersion],
                    userType: 'subcontractor',
                    userId: $subcontractor?->id,
                );

                if ($subcontractor) {
                    $notifications->notifyInternal(
                        company: $company,
                        eventKey: 'new_upload',
                        title: 'Nouveau document déposé',
                        text: "{$subcontractor->legal_name} a déposé « {$docData['name']} ».",
                        icon: 'document',
                        color: 'purple',
                        subcontractor: $subcontractor,
                        subject: $document,
                    );
                }
            } elseif (! empty($docData['issue_date']) || ! empty($docData['expiry_date'])) {
                $document->update([
                    'issue_date' => ! empty($docData['issue_date']) ? $docData['issue_date'] : $document->issue_date,
                    'expiration_date' => ! empty($docData['expiry_date']) ? $docData['expiry_date'] : $document->expiration_date,
                ]);
            }

            if (! empty($docData['comment']) && $processedPayload === null) {
                DocumentComment::create([
                    'document_id' => $document->id,
                    'author_type' => 'subcontractor',
                    'author_id' => $subcontractor?->id ?? 0,
                    'body' => trim((string) $docData['comment']),
                    'visibility' => DocumentComment::VISIBILITY_EXTERNAL,
                ]);
            }
        }

        $request->update(['confirmed_at' => now()]);
        if ($subcontractor) {
            $completenessUpdater->refresh($subcontractor);
        }

        if ($this->transmittedNames === []) {
            $this->transmittedNames = collect($this->documents)
                ->where('uploaded', true)
                ->pluck('name')
                ->all();
        }

        $this->files = [];
        $this->stagedFiles = [];
        $this->loadRequestDocuments($request->fresh());
        $this->submittedSuccess = true;
    }

    public function render()
    {
        $errorBag = $this->getErrorBag();
        foreach ($this->documents as $idx => $_doc) {
            if (! isset($this->fileErrors[$idx]) && $errorBag->has("files.{$idx}")) {
                $this->fileErrors[$idx] = PortalFileProcessor::ERROR_TOO_LARGE;
            }
        }

        return view('livewire.portal.upload');
    }

    private function markTokenAsInvalid(): void
    {
        $this->invalidToken = true;
        $this->company_name = '';
        $this->deadline = '';
        $this->manager_name = '';
        $this->manager_message = '';
        $this->documents = [];
        view()->share('portalCompany', null);
        view()->share('portalInvalidToken', true);
    }

    private function checkRateLimit(): bool
    {
        $key = 'portal-deposit:'.($this->token ?: 'anon').':'.(request()?->ip() ?? '127.0.0.1');

        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->globalError = 'Trop de tentatives en peu de temps. Veuillez patienter une minute avant de réessayer.';
            $this->addError('rate_limit', $this->globalError);

            return false;
        }

        RateLimiter::hit($key, 60);

        return true;
    }

    private function loadRequestDocuments(DocumentRequest $request): void
    {
        $creator = $request->creator;
        $this->manager_name = $creator?->full_name ?: $this->company_name;
        $this->manager_message = $request->message ?: 'Merci de nous transmettre vos pièces administratives à jour.';
        $this->deadline = $request->due_date ? $request->due_date->translatedFormat('j F Y') : '-';

        $docs = Document::query()
            ->with(['documentType', 'currentVersion'])
            ->where('subcontractor_id', $request->subcontractor_id)
            ->whereNull('archived_at')
            ->orderBy('id')
            ->get();

        if ($docs->isEmpty()) {
            return;
        }

        $this->documents = $docs->map(function (Document $doc): array {
            $allowedExt = array_values(array_unique(array_merge(
                $doc->documentType?->allowed_extensions ?: [],
                ['pdf', 'jpg', 'jpeg', 'png']
            )));
            $version = $doc->currentVersion;

            return [
                'id' => $doc->id,
                'name' => $doc->documentType?->name ?? 'Document',
                'status' => $doc->status->value,
                'rejection_reason' => $doc->rejection_reason_code?->label(),
                'rejection_comment' => $doc->rejection_comment,
                'uploaded' => in_array($doc->status, [DocumentStatus::Valid, DocumentStatus::ExpiringSoon, DocumentStatus::Pending, DocumentStatus::Uploaded], true),
                'file_name' => $version?->original_name,
                'file_size_label' => $version?->file_size ? $this->formatBytes((int) $version->file_size) : null,
                'is_image' => $version && str_starts_with((string) $version->mime_type, 'image/'),
                'preview_url' => null,
                'issue_date' => $doc->issue_date?->format('Y-m-d') ?? '',
                'expiry_date' => $doc->expiration_date?->format('Y-m-d') ?? '',
                'comment' => $version?->comment ?? '',
                'allowed_extensions' => $allowedExt,
                'formats' => strtoupper(implode(', ', $allowedExt)),
                'max_mb' => min((int) config('docpilot.max_upload_mb', 10), (int) ($doc->documentType?->max_file_size_mb ?? 10)),
            ];
        })->values()->all();
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1, ',', ' ').' Mo';
        }

        return max(1, (int) round($bytes / 1024)).' Ko';
    }
}
