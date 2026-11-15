<?php

namespace App\Livewire\App\DocumentTypes;

use App\Domain\Audit\AuditLogger;
use App\Models\DocumentType;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Catégories de documents - DocPilot')]
class Index extends Component
{
    #[Url]
    public string $tab = 'active'; // 'active', 'inactive', 'archived'

    // Slide-over form fields
    public bool $showSlideOver = false;

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';
    public string $description = '';
    public bool $required = true;
    public bool $has_expiration = true;
    public ?int $duration_months = null;
    public array $reminders = [60, 30, 15, 7, 0];
    public string $new_reminder_day = '';
    public array $allowed_formats = ['pdf', 'jpg', 'jpeg', 'png'];
    public int $max_size_mb = 10;
    public string $message_template = '';
    public bool $active = true;

    // Archive / restore confirmation modal
    public bool $showArchiveModal = false;

    #[Locked]
    public ?int $archiveTargetId = null;
    public string $archiveTargetAction = 'archive'; // 'archive' or 'restore'
    public string $archiveTargetName = '';

    public function mount(): void
    {
        $this->authorize('viewAny', DocumentType::class);
        $this->max_size_mb = (int) config('docpilot.max_upload_mb', 10);
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['active', 'inactive', 'archived'], true)) {
            $this->tab = $tab;
        }
    }

    public function openCreateSlideOver(): void
    {
        $this->authorize('create', DocumentType::class);

        $this->reset([
            'editingId',
            'name',
            'description',
            'message_template',
            'new_reminder_day',
        ]);
        $this->required = true;
        $this->has_expiration = true;
        $this->duration_months = null;
        $this->reminders = [60, 30, 15, 7, 0];
        $this->allowed_formats = ['pdf', 'jpg', 'jpeg', 'png'];
        $this->max_size_mb = (int) config('docpilot.max_upload_mb', 10);
        $this->active = true;

        $this->resetErrorBag();
        $this->showSlideOver = true;
    }

    public function edit(int $id): void
    {
        $documentType = DocumentType::findOrFail($id);
        $this->authorize('update', $documentType);

        $this->editingId = $documentType->id;
        $this->name = $documentType->name;
        $this->description = $documentType->description ?? '';
        $this->required = $documentType->is_mandatory;
        $this->has_expiration = $documentType->has_expiration_date;
        $this->duration_months = $documentType->default_validity_months ?? ($documentType->has_expiration_date ? 12 : null);
        $this->reminders = is_array($documentType->reminder_days) ? array_map('intval', $documentType->reminder_days) : [];
        rsort($this->reminders);
        $this->new_reminder_day = '';
        $this->allowed_formats = is_array($documentType->allowed_extensions)
            ? array_values(array_map('strtolower', $documentType->allowed_extensions))
            : ['pdf', 'jpg', 'jpeg', 'png'];
        $this->max_size_mb = (int) ($documentType->max_file_size_mb ?? config('docpilot.max_upload_mb', 10));
        $this->message_template = $documentType->message_template ?? '';
        $this->active = $documentType->is_active;

        $this->resetErrorBag();
        $this->showSlideOver = true;
    }

    public function addReminder(): void
    {
        $val = trim($this->new_reminder_day);
        if ($val === '' || ! ctype_digit($val)) {
            return;
        }

        $day = (int) $val;
        if ($day < 0 || $day > 365) {
            $this->addError('new_reminder_day', 'Le jour de rappel doit être compris entre 0 et 365.');
            return;
        }

        if (! in_array($day, $this->reminders, true)) {
            $this->reminders[] = $day;
            rsort($this->reminders);
        }

        $this->new_reminder_day = '';
        $this->resetErrorBag('new_reminder_day');
    }

    public function removeReminder(int $day): void
    {
        $this->reminders = array_values(array_filter($this->reminders, fn ($d) => $d !== $day));
        rsort($this->reminders);
    }

    public function insertVariable(string $variable): void
    {
        $this->message_template = trim(($this->message_template ?? '') . ' ' . $variable);
    }

    public function toggleStatus(int $id): void
    {
        $documentType = DocumentType::findOrFail($id);
        $this->authorize('update', $documentType);

        $documentType->is_active = ! $documentType->is_active;
        $documentType->save();

        app(AuditLogger::class)->log(
            action: $documentType->is_active ? 'document_type.activated' : 'document_type.deactivated',
            entity: $documentType,
            metadata: ['is_active' => $documentType->is_active],
        );
    }

    public function confirmArchive(int $id): void
    {
        $documentType = DocumentType::findOrFail($id);
        $this->authorize('update', $documentType);

        $this->archiveTargetId = $documentType->id;
        $this->archiveTargetAction = 'archive';
        $this->archiveTargetName = $documentType->name;
        $this->showArchiveModal = true;
    }

    public function confirmRestore(int $id): void
    {
        $documentType = DocumentType::findOrFail($id);
        $this->authorize('update', $documentType);

        $this->archiveTargetId = $documentType->id;
        $this->archiveTargetAction = 'restore';
        $this->archiveTargetName = $documentType->name;
        $this->showArchiveModal = true;
    }

    public function archive(?int $id = null): void
    {
        $targetId = $id ?? $this->archiveTargetId;
        if (! $targetId) {
            return;
        }

        $documentType = DocumentType::findOrFail($targetId);
        $this->authorize('update', $documentType);

        $documentType->update([
            'archived_at' => now(),
            'is_active' => false,
        ]);

        app(AuditLogger::class)->log('document_type.archived', $documentType, ['name' => $documentType->name]);
        $this->showArchiveModal = false;
        $this->archiveTargetId = null;
        session()->flash('status', 'Catégorie archivée avec succès.');
    }

    public function restore(?int $id = null): void
    {
        $targetId = $id ?? $this->archiveTargetId;
        if (! $targetId) {
            return;
        }

        $documentType = DocumentType::findOrFail($targetId);
        $this->authorize('update', $documentType);

        $documentType->update([
            'archived_at' => null,
            'is_active' => true,
        ]);

        app(AuditLogger::class)->log('document_type.restored', $documentType, ['name' => $documentType->name]);
        $this->showArchiveModal = false;
        $this->archiveTargetId = null;
        session()->flash('status', 'Catégorie restaurée avec succès.');
    }

    public function executeArchive(): void
    {
        if ($this->archiveTargetAction === 'archive') {
            $this->archive($this->archiveTargetId);
        } else {
            $this->restore($this->archiveTargetId);
        }
    }

    public function delete(int $id): void
    {
        $documentType = DocumentType::findOrFail($id);
        $this->authorize('delete', $documentType);

        if ($documentType->documents()->exists() || $documentType->templateItems()->exists()) {
            session()->flash('error', 'Impossible de supprimer cette catégorie car elle est utilisée par des documents ou des modèles. Vous pouvez l\'archiver.');
            return;
        }

        $documentType->delete();
        app(AuditLogger::class)->log('document_type.deleted', $documentType, ['name' => $documentType->name]);
        session()->flash('status', 'Catégorie supprimée avec succès.');
    }

    public function save(): void
    {
        $documentType = $this->editingId ? DocumentType::findOrFail($this->editingId) : null;
        $documentType
            ? $this->authorize('update', $documentType)
            : $this->authorize('create', DocumentType::class);

        $maxUploadMb = (int) config('docpilot.max_upload_mb', 10);

        if (! $this->has_expiration) {
            $this->duration_months = null;
            $this->reminders = [];
        } else {
            $this->reminders = array_values(array_unique(array_map('intval', $this->reminders)));
            rsort($this->reminders);
        }

        $this->validate([
            'name' => [
                'required',
                'string',
                'min:2',
                'max:120',
                Rule::unique('document_types', 'name')
                    ->where('company_id', auth()->user()->company_id)
                    ->whereNull('archived_at')
                    ->ignore($documentType?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'required' => ['boolean'],
            'has_expiration' => ['boolean'],
            'duration_months' => [
                $this->has_expiration ? 'required' : 'nullable',
                'integer',
                'min:1',
                'max:120',
            ],
            'reminders' => ['array'],
            'reminders.*' => ['integer', 'min:0', 'max:365'],
            'allowed_formats' => ['required', 'array', 'min:1'],
            'allowed_formats.*' => ['string', Rule::in(['pdf', 'jpg', 'jpeg', 'png'])],
            'max_size_mb' => ['required', 'integer', 'min:1', "max:{$maxUploadMb}"],
            'message_template' => ['nullable', 'string', 'max:2000'],
            'active' => ['boolean'],
        ], [
            'name.required' => 'Le nom de la catégorie est obligatoire.',
            'name.min' => 'Le nom de la catégorie doit contenir au moins 2 caractères.',
            'name.max' => 'Le nom de la catégorie ne peut pas dépasser 120 caractères.',
            'name.unique' => 'Une catégorie avec ce nom existe déjà.',
            'duration_months.required' => 'La durée de validité est obligatoire lorsque la date d\'expiration est activée.',
            'duration_months.integer' => 'La durée de validité doit être un nombre entier.',
            'duration_months.min' => 'La durée de validité doit être d\'au moins 1 mois.',
            'duration_months.max' => 'La durée de validité ne peut pas dépasser 120 mois.',
            'reminders.array' => 'Les jours de rappel doivent être une liste.',
            'reminders.*.integer' => 'Chaque jour de rappel doit être un nombre entier.',
            'reminders.*.min' => 'Un jour de rappel ne peut pas être négatif.',
            'reminders.*.max' => 'Un jour de rappel ne peut pas dépasser 365 jours.',
            'allowed_formats.required' => 'Au moins un format de fichier doit être sélectionné.',
            'allowed_formats.min' => 'Au moins un format de fichier doit être sélectionné.',
            'allowed_formats.*.in' => 'Format de fichier non autorisé (autorisés : PDF, JPG, JPEG, PNG).',
            'max_size_mb.required' => 'La taille maximale est obligatoire.',
            'max_size_mb.integer' => 'La taille maximale doit être un nombre entier.',
            'max_size_mb.min' => 'La taille maximale doit être d\'au moins 1 Mo.',
            'max_size_mb.max' => "La taille maximale ne peut pas dépasser {$maxUploadMb} Mo.",
        ]);

        $attributes = [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'is_mandatory' => $this->required,
            'has_expiration_date' => $this->has_expiration,
            'default_validity_months' => $this->has_expiration ? (int) $this->duration_months : null,
            'reminder_days' => $this->has_expiration ? $this->reminders : [],
            'allowed_extensions' => array_values(array_map('strtolower', $this->allowed_formats)),
            'max_file_size_mb' => (int) $this->max_size_mb,
            'message_template' => $this->message_template ?: null,
            'is_active' => $this->active,
        ];

        if ($documentType) {
            $documentType->fill($attributes);
            $changes = $documentType->getDirty();
            $before = Arr::only($documentType->getOriginal(), array_keys($changes));
            $documentType->save();

            if (! empty($changes)) {
                app(AuditLogger::class)->log(
                    action: 'document_type.updated',
                    entity: $documentType,
                    metadata: ['before' => $before, 'after' => $changes],
                );
            }

            session()->flash('status', 'Catégorie modifiée avec succès.');
        } else {
            $created = DocumentType::create($attributes);

            app(AuditLogger::class)->log(
                action: 'document_type.created',
                entity: $created,
                metadata: ['name' => $created->name],
            );

            session()->flash('status', 'Catégorie créée avec succès.');
        }

        $this->showSlideOver = false;
        $this->editingId = null;
    }

    public function render()
    {
        $query = DocumentType::query()
            ->withCount(['documents', 'templateItems'])
            ->orderBy('name');

        match ($this->tab) {
            'inactive' => $query->whereNull('archived_at')->where('is_active', false),
            'archived' => $query->whereNotNull('archived_at'),
            default => $query->whereNull('archived_at')->where('is_active', true),
        };

        return view('livewire.app.document-types.index', [
            'categories' => $query->get(),
            'activeCount' => DocumentType::query()->whereNull('archived_at')->where('is_active', true)->count(),
            'inactiveCount' => DocumentType::query()->whereNull('archived_at')->where('is_active', false)->count(),
            'archivedCount' => DocumentType::query()->whereNotNull('archived_at')->count(),
        ]);
    }
}
