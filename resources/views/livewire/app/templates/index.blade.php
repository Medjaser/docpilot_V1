<div class="space-y-6">
    @if ($toastMessage)
        <div class="fixed bottom-5 right-5 z-50">
            <x-ui.alert type="success" title="Modèle de dossier" dismissible>
                {{ $toastMessage }}
            </x-ui.alert>
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Modèles de dossiers</h1>
            <p class="text-sm text-slate-500 mt-0.5">Créez des modèles types de documents à réclamer selon les métiers et appliquez-les à vos sous-traitants</p>
        </div>

        @if (!$isEditing)
            <x-ui.button variant="primary" wire:click="createTemplate" icon="plus">
                Créer un modèle
            </x-ui.button>
        @endif
    </div>

    @if ($isEditing)
        <!-- Edit / Create Template Form View -->
        <form wire:submit="saveTemplate" class="bg-white rounded-xl border border-slate-200 p-6 shadow-2xs space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-lg font-bold text-slate-900">
                    {{ $editingId ? 'Modifier le modèle de dossier' : 'Nouveau modèle de dossier' }}
                </h3>
                <x-ui.button variant="ghost" size="sm" type="button" wire:click="closeEdit" icon="x-mark">Fermer</x-ui.button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <x-ui.input label="Nom du modèle" wire:model="name" required placeholder="ex. Sous-traitant Électricité" />
                    @error('name')
                        <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <x-ui.input label="Description" wire:model="description" placeholder="Modèle complet pour les lots techniques..." />
            </div>

            <div class="space-y-3 pt-2">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Composition des documents</h4>
                    @error('selected_type_ids')
                        <span class="text-xs text-rose-600 font-semibold">{{ $message }}</span>
                    @enderror
                </div>

                <div class="space-y-2">
                    @foreach ($documentTypes as $index => $docType)
                        <div wire:key="tpl-type-{{ $docType->id }}" class="flex items-center justify-between p-3 rounded-lg bg-slate-50 border border-slate-200">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" wire:model="selected_type_ids" value="{{ $docType->id }}" class="rounded border-slate-300 text-blue-600" />
                                <span class="text-xs font-bold text-slate-900">{{ $index + 1 }}. {{ $docType->name }}</span>
                            </label>
                            <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                                <input type="checkbox" wire:model="required_type_ids" value="{{ $docType->id }}" class="rounded border-slate-300 text-blue-600" />
                                <span>Obligatoire</span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                <x-ui.button variant="ghost" type="button" wire:click="closeEdit">Annuler</x-ui.button>
                <x-ui.button variant="primary" type="submit" loading>Enregistrer le modèle</x-ui.button>
            </div>
        </form>
    @else
        <!-- Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse ($templates as $tpl)
                <div wire:key="tpl-card-{{ $tpl['id'] }}" class="bg-white rounded-xl border border-slate-200 p-6 shadow-2xs flex flex-col justify-between space-y-4 hover:shadow-md transition-shadow">
                    <div>
                        <div class="flex items-start justify-between">
                            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                                <x-ui.icon name="folder" class="w-5 h-5" />
                            </div>

                            <div x-data="{ menu: false }" class="relative">
                                <button @click="menu = !menu" type="button" class="p-1 text-slate-400 hover:text-slate-600 rounded">
                                    <x-ui.icon name="ellipsis-vertical" class="w-4 h-4" />
                                </button>
                                <div x-show="menu" @click.away="menu = false" style="display: none;" class="absolute right-0 mt-1 w-48 bg-white rounded-xl shadow-lg border border-slate-200 py-1 z-20 text-xs">
                                    <button type="button" @click="menu = false" wire:click="editTemplate({{ $tpl['id'] }})" class="w-full text-left px-3 py-1.5 hover:bg-slate-50">Modifier</button>
                                    <button type="button" @click="menu = false" wire:click="openApplyModal({{ $tpl['id'] }})" class="w-full text-left px-3 py-1.5 text-blue-600 hover:bg-blue-50">Appliquer à un sous-traitant</button>
                                    <button type="button" @click="menu = false" wire:click="duplicateTemplate({{ $tpl['id'] }})" class="w-full text-left px-3 py-1.5 hover:bg-slate-50">Dupliquer</button>
                                    <button type="button" @click="menu = false" wire:click="deleteTemplate({{ $tpl['id'] }})" class="w-full text-left px-3 py-1.5 text-rose-600 hover:bg-rose-50">Supprimer</button>
                                </div>
                            </div>
                        </div>

                        <h3 class="font-bold text-slate-900 text-base mt-3">{{ $tpl['name'] }}</h3>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">{{ $tpl['description'] }}</p>

                        @if (!empty($tpl['document_names']))
                            <div class="flex flex-wrap gap-1 mt-3">
                                @foreach (array_slice($tpl['document_names'], 0, 4) as $docName)
                                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-[10px] font-medium">{{ $docName }}</span>
                                @endforeach
                                @if (count($tpl['document_names']) > 4)
                                    <span class="px-2 py-0.5 bg-blue-50 text-blue-600 rounded text-[10px] font-semibold">+{{ count($tpl['document_names']) - 4 }}</span>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                        <span><strong>{{ $tpl['docs_count'] }}</strong> documents</span>
                        <button type="button" wire:click="openApplyModal({{ $tpl['id'] }})" class="text-blue-600 font-semibold hover:underline">
                            Appliquer →
                        </button>
                    </div>
                </div>
            @empty
                <div class="col-span-full">
                    <x-ui.empty-state icon="folder" title="Aucun modèle" description="Créez votre premier modèle de dossier pour standardiser vos demandes." />
                </div>
            @endforelse
        </div>
    @endif

    @if ($showApplyModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 border border-slate-100 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">Appliquer ce modèle à un sous-traitant</h3>
                    <button type="button" wire:click="$set('showApplyModal', false)" class="text-slate-400 hover:text-slate-600">
                        <x-ui.icon name="x-mark" class="w-5 h-5" />
                    </button>
                </div>

                <form wire:submit="applyToSubcontractor" class="space-y-4 text-xs">
                    <p class="text-slate-600">Les catégories du modèle qui ne figurent pas encore dans le dossier du sous-traitant seront ajoutées automatiquement.</p>

                    <x-ui.select label="Choisir le sous-traitant" wire:model="targetSubcontractorId" required>
                        @foreach ($subcontractors as $sub)
                            <option value="{{ $sub->id }}">{{ $sub->legal_name }}</option>
                        @endforeach
                    </x-ui.select>

                    <div class="flex justify-end gap-3 pt-3 border-t border-slate-100">
                        <x-ui.button variant="ghost" type="button" wire:click="$set('showApplyModal', false)">Annuler</x-ui.button>
                        <x-ui.button variant="primary" type="submit" loading>Appliquer le modèle</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
