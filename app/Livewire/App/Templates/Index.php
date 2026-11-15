<?php

namespace App\Livewire\App\Templates;

use App\Domain\Audit\AuditLogger;
use App\Domain\Documents\SubcontractorCompletenessUpdater;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Subcontractor;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Modèles de dossiers - DocPilot')]
class Index extends Component
{
    public bool $isEditing = false;

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';
    public string $description = '';
    public array $selected_type_ids = [];
    public array $required_type_ids = [];

    // Modale d'application à un sous-traitant
    public bool $showApplyModal = false;
    #[Locked]
    public ?int $applyTemplateId = null;
    public ?int $targetSubcontractorId = null;

    public ?string $toastMessage = null;

    public function mount(): void
    {
        $this->authorize('viewAny', DocumentTemplate::class);
    }

    public function createTemplate(): void
    {
        $this->authorize('create', DocumentTemplate::class);

        $this->editingId = null;
        $this->name = '';
        $this->description = '';

        $activeTypes = DocumentType::available()->orderBy('name')->get();
        $this->selected_type_ids = $activeTypes->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->required_type_ids = $activeTypes->where('is_mandatory', true)->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $this->isEditing = true;
    }

    public function editTemplate(int $id): void
    {
        if ($id === 0) {
            $this->createTemplate();

            return;
        }

        $template = DocumentTemplate::with('items')->findOrFail($id);
        $this->authorize('update', $template);

        $this->editingId = $template->id;
        $this->name = $template->name;
        $this->description = (string) $template->description;
        $this->selected_type_ids = $template->items->pluck('document_type_id')->map(fn ($v) => (int) $v)->all();
        $this->required_type_ids = $template->items->where('is_required', true)->pluck('document_type_id')->map(fn ($v) => (int) $v)->values()->all();
        $this->isEditing = true;
    }

    public function saveTemplate(AuditLogger $audit): void
    {
        $template = $this->editingId ? DocumentTemplate::findOrFail($this->editingId) : null;
        $template
            ? $this->authorize('update', $template)
            : $this->authorize('create', DocumentTemplate::class);

        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'selected_type_ids' => ['required', 'array', 'min:1'],
        ], [
            'name.required' => 'Le nom du modèle est obligatoire.',
            'selected_type_ids.required' => 'Sélectionnez au moins une catégorie de document.',
            'selected_type_ids.min' => 'Sélectionnez au moins une catégorie de document.',
        ]);

        $validTypeIds = DocumentType::query()
            ->whereIn('id', array_map('intval', $this->selected_type_ids))
            ->pluck('id')
            ->all();

        if ($template) {
            $template->update([
                'name' => trim($this->name),
                'description' => trim($this->description) ?: null,
            ]);
            $template->items()->delete();
            $action = 'template.updated';
        } else {
            $template = DocumentTemplate::create([
                'name' => trim($this->name),
                'description' => trim($this->description) ?: null,
                'is_active' => true,
            ]);
            $action = 'template.created';
        }

        $requiredIds = array_map('intval', $this->required_type_ids);
        foreach (array_values($validTypeIds) as $pos => $typeId) {
            $template->items()->create([
                'document_type_id' => $typeId,
                'is_required' => in_array((int) $typeId, $requiredIds, true),
                'position' => $pos,
            ]);
        }

        $audit->log($action, $template, ['items_count' => count($validTypeIds)]);
        $this->isEditing = false;
        $this->editingId = null;
        $this->toastMessage = 'Modèle de dossier enregistré avec succès.';
    }

    public function duplicateTemplate(int $id, AuditLogger $audit): void
    {
        $source = DocumentTemplate::with('items')->findOrFail($id);
        $this->authorize('create', DocumentTemplate::class);

        $copy = DocumentTemplate::create([
            'name' => $source->name.' (Copie)',
            'description' => $source->description,
            'is_active' => true,
        ]);

        foreach ($source->items as $item) {
            $copy->items()->create([
                'document_type_id' => $item->document_type_id,
                'is_required' => $item->is_required,
                'position' => $item->position,
            ]);
        }

        $audit->log('template.duplicated', $copy, ['source_id' => $source->id]);
        $this->toastMessage = "Modèle « {$copy->name} » créé par duplication.";
    }

    public function deleteTemplate(int $id, AuditLogger $audit): void
    {
        $template = DocumentTemplate::findOrFail($id);
        $this->authorize('delete', $template);

        $name = $template->name;
        $audit->log('template.deleted', $template, ['name' => $name]);
        $template->items()->delete();
        $template->delete();

        $this->toastMessage = "Modèle « {$name} » supprimé.";
    }

    public function openApplyModal(int $id): void
    {
        $template = DocumentTemplate::findOrFail($id);
        $this->authorize('view', $template);

        $this->applyTemplateId = $template->id;
        $this->targetSubcontractorId = Subcontractor::query()->whereNull('archived_at')->orderBy('legal_name')->value('id');
        $this->showApplyModal = true;
    }

    public function applyToSubcontractor(AuditLogger $audit, SubcontractorCompletenessUpdater $completeness): void
    {
        $template = DocumentTemplate::with('documentTypes')->findOrFail($this->applyTemplateId);
        $subcontractor = Subcontractor::findOrFail($this->targetSubcontractorId);
        $this->authorize('update', $subcontractor);

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
            'template_name' => $template->name,
            'documents_added' => $added,
        ]);

        $this->showApplyModal = false;
        $this->toastMessage = "Modèle « {$template->name} » appliqué à {$subcontractor->legal_name} ({$added} pièce(s) ajoutée(s)).";
    }

    public function closeEdit(): void
    {
        $this->isEditing = false;
        $this->editingId = null;
    }

    public function render()
    {
        $templates = DocumentTemplate::query()
            ->withCount('items')
            ->with('items.documentType')
            ->orderBy('id')
            ->get()
            ->map(function (DocumentTemplate $tpl) {
                $typeIds = $tpl->items->pluck('document_type_id')->all();
                $subcontractorsCount = $typeIds === []
                    ? 0
                    : Subcontractor::query()
                        ->whereNull('archived_at')
                        ->whereHas('documents', fn ($q) => $q->whereIn('document_type_id', $typeIds))
                        ->count();

                return [
                    'id' => $tpl->id,
                    'name' => $tpl->name,
                    'description' => $tpl->description ?? 'Aucune description.',
                    'docs_count' => $tpl->items_count,
                    'subcontractors_count' => $subcontractorsCount,
                    'document_names' => $tpl->items->map(fn ($item) => $item->documentType?->name)->filter()->values()->all(),
                ];
            });

        return view('livewire.app.templates.index', [
            'templates' => $templates,
            'documentTypes' => DocumentType::available()->orderBy('name')->get(),
            'subcontractors' => Subcontractor::query()->whereNull('archived_at')->orderBy('legal_name')->get(['id', 'legal_name']),
        ]);
    }
}
