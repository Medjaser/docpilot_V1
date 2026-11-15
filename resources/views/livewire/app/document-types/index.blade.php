<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Catégories de documents</h1>
            <p class="text-sm text-slate-500 mt-0.5">Configurez les types de documents réclamés et leurs règles de validité</p>
        </div>

        <x-ui.button variant="primary" wire:click="openCreateSlideOver" icon="plus">
            Ajouter une catégorie
        </x-ui.button>
    </div>

    @if (session('status'))
        <x-ui.alert type="success">
            {{ session('status') }}
        </x-ui.alert>
    @endif

    @if (session('error'))
        <x-ui.alert type="danger">
            {{ session('error') }}
        </x-ui.alert>
    @endif

    <!-- Tabs -->
    <div class="border-b border-slate-200">
        <nav class="-mb-px flex space-x-6">
            <button 
                type="button" 
                wire:click="setTab('active')" 
                class="py-3 px-1 border-b-2 font-medium text-sm transition-colors flex items-center gap-2 {{ $tab === 'active' ? 'border-blue-600 text-blue-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700' }}"
            >
                <span>Actives</span>
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $tab === 'active' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600' }}">
                    {{ $activeCount }}
                </span>
            </button>

            <button 
                type="button" 
                wire:click="setTab('inactive')" 
                class="py-3 px-1 border-b-2 font-medium text-sm transition-colors flex items-center gap-2 {{ $tab === 'inactive' ? 'border-blue-600 text-blue-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700' }}"
            >
                <span>Inactives</span>
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $tab === 'inactive' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600' }}">
                    {{ $inactiveCount }}
                </span>
            </button>

            <button 
                type="button" 
                wire:click="setTab('archived')" 
                class="py-3 px-1 border-b-2 font-medium text-sm transition-colors flex items-center gap-2 {{ $tab === 'archived' ? 'border-blue-600 text-blue-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700' }}"
            >
                <span>Archivées</span>
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $tab === 'archived' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600' }}">
                    {{ $archivedCount }}
                </span>
            </button>
        </nav>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
        <table class="w-full text-left text-xs text-slate-600">
            <thead class="bg-slate-50 text-slate-500 font-semibold border-b border-slate-200 uppercase tracking-wider">
                <tr>
                    <th class="p-4">Nom de la catégorie</th>
                    <th class="p-4">Obligatoire</th>
                    <th class="p-4">Expiration</th>
                    <th class="p-4">Durée</th>
                    <th class="p-4">Rappels</th>
                    <th class="p-4">Formats</th>
                    <th class="p-4">Taille max</th>
                    <th class="p-4">Utilisée par</th>
                    <th class="p-4 text-right">Statut / Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($categories as $cat)
                    <tr wire:key="cat-{{ $cat->id }}" class="hover:bg-slate-50/80 transition-colors">
                        <td class="p-4 font-bold text-slate-900 text-sm flex items-center gap-2 cursor-pointer" wire:click="edit({{ $cat->id }})">
                            <x-ui.icon name="document" class="w-4 h-4 text-blue-600 shrink-0" />
                            <span>{{ $cat->name }}</span>
                        </td>
                        <td class="p-4">
                            @if ($cat->is_mandatory)
                                <span class="px-2 py-0.5 text-[10px] font-bold bg-slate-100 text-slate-700 rounded-full">Oui</span>
                            @else
                                <span class="text-slate-400">Non</span>
                            @endif
                        </td>
                        <td class="p-4">
                            {{ $cat->has_expiration_date ? 'Oui' : 'Non' }}
                        </td>
                        <td class="p-4">
                            {{ $cat->default_validity_months ? $cat->default_validity_months . ' mois' : '—' }}
                        </td>
                        <td class="p-4">
                            <div class="flex flex-wrap items-center gap-1">
                                @forelse ($cat->reminder_days ?? [] as $r)
                                    <span class="px-2 py-0.5 text-[10px] font-medium bg-blue-50 text-blue-700 rounded-md">J-{{ $r }}</span>
                                @empty
                                    <span class="text-slate-400">—</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="p-4 text-slate-500 font-mono uppercase text-[11px]">
                            {{ is_array($cat->allowed_extensions) ? implode(', ', $cat->allowed_extensions) : '—' }}
                        </td>
                        <td class="p-4 text-slate-600 font-medium">{{ $cat->max_file_size_mb }} Mo</td>
                        <td class="p-4 text-slate-600 text-xs">
                            <span class="font-medium text-slate-700">{{ $cat->template_items_count }}</span> {{ \Illuminate\Support\Str::plural('modèle', $cat->template_items_count) }} · 
                            <span class="font-medium text-slate-700">{{ $cat->documents_count }}</span> {{ \Illuminate\Support\Str::plural('doc', $cat->documents_count) }}
                        </td>
                        <td class="p-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" wire:click="toggleStatus({{ $cat->id }})" class="focus:outline-none" title="Basculer le statut actif/inactif">
                                    <x-ui.badge :status="$cat->is_active ? 'active' : 'disabled'" size="sm" />
                                </button>

                                <button type="button" wire:click="edit({{ $cat->id }})" class="p-1 text-slate-400 hover:text-blue-600 rounded-md hover:bg-slate-100" title="Modifier">
                                    <x-ui.icon name="pencil" class="w-4 h-4" />
                                </button>

                                @if ($cat->archived_at)
                                    <button type="button" wire:click="confirmRestore({{ $cat->id }})" class="p-1 text-slate-400 hover:text-emerald-600 rounded-md hover:bg-slate-100" title="Restaurer">
                                        <x-ui.icon name="arrow-path" class="w-4 h-4" />
                                    </button>
                                @else
                                    <button type="button" wire:click="confirmArchive({{ $cat->id }})" class="p-1 text-slate-400 hover:text-amber-600 rounded-md hover:bg-slate-100" title="Archiver">
                                        <x-ui.icon name="archive-box" class="w-4 h-4" />
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="p-8">
                            <x-ui.empty-state 
                                icon="folder" 
                                :title="$tab === 'archived' ? 'Aucune catégorie archivée' : ($tab === 'inactive' ? 'Aucune catégorie inactive' : 'Aucune catégorie configurée')"
                                :description="$tab === 'archived' ? 'Aucune catégorie n\'est actuellement archivée.' : ($tab === 'inactive' ? 'Toutes vos catégories de documents sont actives.' : 'Créez votre première catégorie de document pour commencer.')"
                            >
                                @if ($tab === 'active')
                                    <x-ui.button variant="primary" size="sm" wire:click="openCreateSlideOver" icon="plus">
                                        Ajouter une catégorie
                                    </x-ui.button>
                                @endif
                            </x-ui.empty-state>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Modal confirmation archivage / restauration -->
    @if ($showArchiveModal)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 flex items-center justify-center">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" wire:click="$set('showArchiveModal', false)"></div>
            <div class="bg-white rounded-2xl overflow-hidden shadow-2xl transform transition-all w-full max-w-md z-50 border border-slate-100 p-6 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl {{ $archiveTargetAction === 'archive' ? 'bg-amber-100 text-amber-600' : 'bg-blue-100 text-blue-600' }} flex items-center justify-center shrink-0">
                        <x-ui.icon :name="$archiveTargetAction === 'archive' ? 'archive-box' : 'arrow-path'" class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            {{ $archiveTargetAction === 'archive' ? 'Archiver la catégorie ?' : 'Restaurer la catégorie ?' }}
                        </h3>
                        <p class="text-xs text-slate-500">
                            {{ $archiveTargetName }}
                        </p>
                    </div>
                </div>

                <p class="text-xs text-slate-600 leading-relaxed">
                    @if ($archiveTargetAction === 'archive')
                        La catégorie sera désactivée et n'apparaîtra plus dans les nouveaux modèles ni les nouvelles demandes. <strong>Aucun document existant ne sera supprimé.</strong>
                    @else
                        La catégorie sera réactivée et pourra à nouveau être sélectionnée dans les modèles et demandes.
                    @endif
                </p>

                <div class="flex justify-end gap-3 pt-3 border-t border-slate-100">
                    <x-ui.button variant="ghost" type="button" wire:click="$set('showArchiveModal', false)">
                        Annuler
                    </x-ui.button>

                    <x-ui.button 
                        :variant="$archiveTargetAction === 'archive' ? 'danger' : 'primary'" 
                        type="button" 
                        wire:click="executeArchive"
                    >
                        {{ $archiveTargetAction === 'archive' ? 'Confirmer l\'archivage' : 'Confirmer la restauration' }}
                    </x-ui.button>
                </div>
            </div>
        </div>
    @endif

    <!-- Slide-over Form Overlay -->
    @if ($showSlideOver)
        <div class="fixed inset-0 z-50 overflow-hidden">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" wire:click="$set('showSlideOver', false)"></div>

            <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
                <div class="w-screen max-w-md bg-white shadow-2xl flex flex-col justify-between p-6 overflow-y-auto space-y-6">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <h3 class="text-lg font-bold text-slate-900">
                            {{ $editingId ? 'Modifier la catégorie' : 'Nouvelle catégorie de document' }}
                        </h3>
                        <button type="button" wire:click="$set('showSlideOver', false)" class="text-slate-400 hover:text-slate-600">
                            <x-ui.icon name="x-mark" class="w-5 h-5" />
                        </button>
                    </div>

                    <form wire:submit="save" class="space-y-4 text-xs">
                        <x-ui.input 
                            label="Nom de la catégorie" 
                            wire:model="name" 
                            required 
                            maxlength="120"
                            placeholder="ex. Attestation d'assurance décennale" 
                        />

                        <x-ui.textarea 
                            label="Description" 
                            wire:model="description" 
                            placeholder="Instructions destinées au sous-traitant lors du dépôt..." 
                            rows="2" 
                        />

                        <x-ui.toggle label="Document obligatoire" wire:model="required" />

                        <x-ui.toggle label="A une date d'expiration" wire:model.live="has_expiration" />

                        @if ($has_expiration)
                            <div class="space-y-3 p-3 bg-slate-50 rounded-xl border border-slate-200">
                                <x-ui.input 
                                    label="Durée de validité (en mois)" 
                                    type="number" 
                                    min="1" 
                                    max="120"
                                    wire:model="duration_months" 
                                    required 
                                />

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Jours de rappel avant expiration</label>
                                    <div class="flex flex-wrap items-center gap-1.5 mb-2">
                                        @forelse ($reminders as $r)
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                                <span>J-{{ $r }}</span>
                                                <button type="button" wire:click="removeReminder({{ $r }})" class="hover:text-blue-900 focus:outline-none">
                                                    <x-ui.icon name="x-mark" class="w-3.5 h-3.5" />
                                                </button>
                                            </span>
                                        @empty
                                            <span class="text-xs text-slate-400 italic">Aucun rappel configuré</span>
                                        @endforelse
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="relative flex-1">
                                            <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-xs text-slate-400 font-medium">J-</span>
                                            <input 
                                                type="number" 
                                                min="0" 
                                                max="365"
                                                wire:model="new_reminder_day" 
                                                wire:keydown.enter.prevent="addReminder" 
                                                placeholder="ex. 15"
                                                class="w-full h-8 pl-7 pr-3 text-xs rounded-lg border border-slate-300 bg-white text-slate-900 focus:ring-2 focus:ring-blue-500"
                                            />
                                        </div>
                                        <x-ui.button variant="secondary" size="sm" type="button" wire:click="addReminder">
                                            Ajouter
                                        </x-ui.button>
                                    </div>
                                    @error('new_reminder_day')
                                        <span class="text-[11px] text-rose-600 mt-1 block">{{ $message }}</span>
                                    @enderror
                                    @error('reminders')
                                        <span class="text-[11px] text-rose-600 mt-1 block">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        @endif

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Formats autorisés</label>
                            <div class="flex flex-wrap items-center gap-4">
                                @foreach (['pdf' => 'PDF', 'jpg' => 'JPG', 'jpeg' => 'JPEG', 'png' => 'PNG'] as $ext => $label)
                                    <label class="flex items-center gap-1.5 cursor-pointer text-xs text-slate-700 font-medium">
                                        <input 
                                            type="checkbox" 
                                            value="{{ $ext }}" 
                                            wire:model="allowed_formats" 
                                            class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" 
                                        />
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('allowed_formats')
                                <span class="text-[11px] text-rose-600 mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>

                        <x-ui.input 
                            label="Taille maximale du fichier (Mo)" 
                            type="number" 
                            min="1" 
                            max="{{ config('docpilot.max_upload_mb', 10) }}"
                            wire:model="max_size_mb" 
                            required 
                        />

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Modèle de message pour la demande</label>
                            <p class="text-[11px] text-slate-500 mb-2">Message envoyé au sous-traitant lorsqu'on lui réclame ce document.</p>
                            <div class="flex flex-wrap gap-1.5 mb-2">
                                @foreach (['{nom_entreprise}', '{nom_sous_traitant}', '{nom_contact}', '{date_limite}', '{lien_depot}'] as $var)
                                    <button 
                                        type="button" 
                                        wire:click="insertVariable('{{ $var }}')" 
                                        class="px-2 py-0.5 text-[11px] font-mono font-medium bg-slate-100 hover:bg-blue-50 hover:text-blue-700 hover:border-blue-200 text-slate-700 rounded border border-slate-200 transition-colors"
                                    >
                                        + {{ $var }}
                                    </button>
                                @endforeach
                            </div>
                            <x-ui.textarea 
                                wire:model="message_template" 
                                placeholder="Bonjour {nom_contact}, merci de nous faire parvenir ce document avant le {date_limite}..." 
                                rows="3" 
                            />
                        </div>

                        <x-ui.toggle label="Actif immédiatement" wire:model="active" />

                        <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                            <x-ui.button variant="ghost" type="button" wire:click="$set('showSlideOver', false)">
                                Annuler
                            </x-ui.button>

                            <x-ui.button variant="primary" type="submit" loading>
                                {{ $editingId ? 'Enregistrer les modifications' : 'Enregistrer la catégorie' }}
                            </x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
