<div class="space-y-6">
    @if ($toastMessage)
        <div class="fixed bottom-5 right-5 z-50">
            <x-ui.alert type="success" title="Fiche sous-traitant" dismissible>
                {{ $toastMessage }}
            </x-ui.alert>
        </div>
    @endif

    <!-- Include Modal Component -->
    @can('create', \App\Models\DocumentRequest::class)
        <livewire:app.requests.send-request-modal :subcontractor-id="$id" />
    @endcan

    <!-- Top Return Link -->
    <div>
        <a href="{{ route('subcontractors.index') }}" class="text-xs font-semibold text-blue-600 hover:underline flex items-center gap-1">
            <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" />
            <span>← Retour à la liste des sous-traitants</span>
        </a>
    </div>

    <!-- Header Card -->
    <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-2xs flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div class="flex items-start gap-4">
            <div class="w-14 h-14 rounded-2xl bg-blue-600 text-white font-bold text-xl flex items-center justify-center shrink-0 shadow-md">
                {{ $subcontractor['initials'] }}
            </div>
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">{{ $subcontractor['name'] }}</h1>
                    <x-ui.badge :status="$subcontractor['status']" />
                    @if ($subcontractor['is_archived'])
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-200 text-slate-700">Archivé</span>
                    @endif
                </div>
                <p class="text-xs text-slate-500 mt-1">
                    {{ $subcontractor['activity'] }} • {{ $subcontractor['city'] }} • <span class="font-mono">SIRET {{ $subcontractor['siret'] }}</span>
                </p>
                <p class="text-xs text-slate-400 mt-0.5">
                    Contact : <strong>{{ $subcontractor['contact_name'] }}</strong> ({{ $subcontractor['contact_email'] }}) — Responsable : {{ $subcontractor['manager'] }}
                </p>
            </div>
        </div>

        <!-- Right Side: Completeness Ring + Action Buttons -->
        <div class="flex items-center gap-6 self-start lg:self-center flex-wrap">
            <div class="flex items-center gap-3 border-r border-slate-200 pr-6">
                <x-ui.progress-ring :percentage="$subcontractor['completeness']" :size="64" />
                <div>
                    <span class="text-xs font-bold text-slate-700 block">Complétude</span>
                    <span class="text-[11px] text-slate-400">{{ $validCount }} sur {{ $documents->count() }} documents</span>
                </div>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <x-ui.button variant="primary" @click="$dispatch('open-modal', { name: 'send-request' })" icon="paper-airplane">
                    Envoyer une demande
                </x-ui.button>

                <x-ui.button variant="secondary" wire:click="triggerReminder" icon="arrow-path">
                    Relancer
                </x-ui.button>

                <!-- Exporter Dropdown -->
                <div x-data="{ open: false }" class="relative">
                    <x-ui.button variant="secondary" @click="open = !open" icon="chevron-down" iconPosition="right">
                        Exporter ▾
                    </x-ui.button>
                    <div 
                        x-show="open" 
                        @click.away="open = false" 
                        x-transition 
                        class="absolute right-0 mt-1 w-44 bg-white rounded-xl shadow-lg border border-slate-200 py-1 z-20 text-xs"
                        style="display: none;"
                    >
                        <button type="button" @click="open = false" wire:click="exportZip" class="w-full flex items-center gap-2 px-3 py-2 text-slate-700 hover:bg-slate-50 text-left">
                            <x-ui.icon name="document" class="w-4 h-4 text-slate-400" />
                            <span>Archive ZIP (.zip)</span>
                        </button>
                        <button type="button" @click="open = false" wire:click="exportPdf" class="w-full flex items-center gap-2 px-3 py-2 text-slate-700 hover:bg-slate-50 text-left">
                            <x-ui.icon name="document" class="w-4 h-4 text-slate-400" />
                            <span>Synthèse PDF</span>
                        </button>
                    </div>
                </div>

                <x-ui.button variant="ghost" href="{{ route('subcontractors.edit', $id) }}" icon="pencil">
                    Modifier
                </x-ui.button>

                @if ($canArchive)
                    @if ($subcontractor['is_archived'])
                        <x-ui.button variant="secondary" size="sm" wire:click="restoreSubcontractor">
                            Restaurer
                        </x-ui.button>
                    @else
                        <x-ui.button variant="danger" size="sm" wire:click="archiveSubcontractor">
                            Archiver
                        </x-ui.button>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="border-b border-slate-200">
        <nav class="-mb-px flex space-x-6">
            @php
                $tabs = [
                    'documents' => 'Documents ('.$documents->count().')',
                    'info' => 'Informations',
                    'deadlines' => 'Échéances',
                    'history' => 'Historique',
                    'comments' => 'Commentaires internes',
                ];
            @endphp
            @foreach ($tabs as $key => $label)
                <button 
                    type="button" 
                    wire:click="$set('tab', '{{ $key }}')" 
                    class="py-3 px-1 border-b-2 font-medium text-sm transition-colors {{ $tab === $key ? 'border-blue-600 text-blue-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    <!-- Tab 1: Documents (Priority Tab) -->
    @if ($tab === 'documents')
        <div class="space-y-6">
            <!-- Active or Draft Deposit Link Banner -->
            @if ($subcontractor['deposit_link_active'] || $subcontractor['has_draft_request'])
                <div class="bg-blue-50/80 border border-blue-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center shrink-0 mt-0.5">
                            <x-ui.icon name="link" class="w-4 h-4" />
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-blue-900">
                                {{ $subcontractor['deposit_link_active'] ? 'Lien de dépôt actif' : 'Demande enregistrée en brouillon' }}
                            </h4>
                            <p class="text-xs text-blue-700 mt-0.5">
                                @if ($subcontractor['deposit_link_active'])
                                    Lien envoyé le {{ $subcontractor['deposit_link_sent_at'] }}, expire le {{ $subcontractor['deposit_link_expires_at'] }}.
                                @else
                                    Cliquez sur « Renvoyer / Activer le lien » pour transmettre la demande au sous-traitant.
                                @endif
                            </p>
                            @if ($lastGeneratedLink)
                                <div class="mt-2 p-2.5 bg-white/90 border border-blue-200 rounded-lg space-y-1" x-data="{ copied: false }">
                                    <div class="text-[11px] font-semibold text-amber-700">Lien complet (affiché une seule fois — jeton haché en base) :</div>
                                    <div class="flex items-center gap-2">
                                        <a href="{{ $lastGeneratedLink }}" target="_blank" class="text-xs font-mono text-blue-800 underline break-all">{{ $lastGeneratedLink }}</a>
                                        <button type="button" @click="navigator.clipboard.writeText('{{ $lastGeneratedLink }}'); copied = true; setTimeout(() => copied = false, 2000)" class="px-2.5 py-1 bg-blue-600 hover:bg-blue-700 rounded text-[11px] font-semibold text-white shrink-0">
                                            <span x-text="copied ? 'Copié !' : 'Copier'"></span>
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-2 self-end sm:self-center">
                        @if ($canEdit)
                            <x-ui.button variant="secondary" size="sm" wire:click="resendDepositLink">
                                {{ $subcontractor['deposit_link_active'] ? 'Renvoyer / Copier le lien' : 'Envoyer le brouillon' }}
                            </x-ui.button>
                            @if ($subcontractor['deposit_link_active'])
                                <x-ui.button variant="danger" size="sm" wire:click="disableDepositLink">
                                    Désactiver
                                </x-ui.button>
                            @endif
                        @endif
                    </div>
                </div>
            @endif

            <!-- Toolbar: Apply Template & Toggle Archived Documents -->
            @if ($canEdit)
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-3.5 rounded-xl border border-slate-200 text-xs">
                    <div class="flex items-center gap-2">
                        <select wire:model="selected_template_id" class="h-8 px-2.5 rounded-lg border border-slate-300 bg-white text-xs">
                            <option value="">-- Appliquer un modèle de dossier --</option>
                            @foreach ($templates as $tpl)
                                <option value="{{ $tpl->id }}">{{ $tpl->name }}</option>
                            @endforeach
                        </select>
                        <x-ui.button variant="secondary" size="sm" wire:click="applyTemplate">
                            Appliquer
                        </x-ui.button>
                    </div>

                    <label class="flex items-center gap-2 text-slate-600 cursor-pointer">
                        <input type="checkbox" wire:model.live="showArchivedDocuments" class="rounded border-slate-300 text-blue-600" />
                        <span>Afficher les documents archivés</span>
                    </label>
                </div>
            @endif

            <!-- Documents Table -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-50 text-slate-500 font-semibold border-b border-slate-200 uppercase tracking-wider">
                        <tr>
                            <th class="p-4">Document</th>
                            <th class="p-4">Exigence</th>
                            <th class="p-4">Statut</th>
                            <th class="p-4">Date d'expiration</th>
                            <th class="p-4">Version</th>
                            <th class="p-4">Dernière mise à jour</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($documents as $doc)
                            <tr wire:key="document-{{ $doc['id'] }}" class="hover:bg-slate-50/80 transition-colors">
                                <td class="p-4 font-bold text-slate-900 text-sm flex items-center gap-2">
                                    <x-ui.icon name="document" class="w-4 h-4 text-blue-600 shrink-0" />
                                    <span>{{ $doc['name'] }}</span>
                                </td>
                                <td class="p-4">
                                    @if ($doc['required'])
                                        <span class="px-2 py-0.5 text-[10px] font-bold bg-slate-100 text-slate-700 rounded-full">Obligatoire</span>
                                    @else
                                        <span class="px-2 py-0.5 text-[10px] font-medium bg-slate-50 text-slate-400 rounded-full">Optionnel</span>
                                    @endif
                                </td>
                                <td class="p-4">
                                    <x-ui.badge :status="$doc['status']" size="sm" />
                                </td>
                                <td class="p-4 text-slate-600 font-medium">{{ $doc['expires_at'] }}</td>
                                <td class="p-4 text-slate-500">{{ $doc['version'] }}</td>
                                <td class="p-4 text-slate-500">{{ $doc['updated_at'] }}</td>
                                <td class="p-4 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        @if ($canEdit && !$doc['is_archived'])
                                            <button type="button" wire:click="openUploadModal({{ $doc['id'] }})" class="px-2 py-1 text-[11px] font-semibold text-blue-600 hover:bg-blue-50 rounded-lg" title="Déposer un fichier en interne">
                                                Déposer
                                            </button>
                                        @endif
                                        @if ($doc['has_file'])
                                            <a href="{{ route('documents.download', $doc['id']) }}" target="_blank" class="p-1.5 text-slate-400 hover:text-emerald-600 rounded-lg hover:bg-slate-100" title="Télécharger">
                                                <x-ui.icon name="download" class="w-4 h-4" />
                                            </a>
                                        @endif
                                        <a href="{{ route('documents.review', $doc['id']) }}" class="p-1.5 text-slate-400 hover:text-blue-600 rounded-lg hover:bg-slate-100" title="Examiner">
                                            <x-ui.icon name="eye" class="w-4 h-4" />
                                        </a>
                                        @if ($canEdit)
                                            @if ($doc['is_archived'])
                                                <button type="button" wire:click="restoreDocument({{ $doc['id'] }})" class="px-2 py-1 text-[11px] font-semibold text-emerald-600 hover:bg-emerald-50 rounded-lg">
                                                    Restaurer
                                                </button>
                                            @else
                                                <button type="button" wire:click="archiveDocument({{ $doc['id'] }})" class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50" title="Archiver ce document">
                                                    <x-ui.icon name="x-mark" class="w-4 h-4" />
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-6 text-center text-slate-400">Aucun document attendu pour ce sous-traitant.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Tab 2: Informations -->
    @if ($tab === 'info')
        <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-2xs space-y-6">
            <h3 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">Informations générales</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 text-xs">
                <div>
                    <span class="text-slate-400 uppercase tracking-wider text-[10px] font-bold block mb-1">Raison sociale</span>
                    <p class="font-semibold text-slate-900 text-sm">{{ $subcontractor['name'] }}</p>
                </div>
                <div>
                    <span class="text-slate-400 uppercase tracking-wider text-[10px] font-bold block mb-1">SIRET</span>
                    <p class="font-mono font-semibold text-slate-900">{{ $subcontractor['siret'] }}</p>
                </div>
                <div>
                    <span class="text-slate-400 uppercase tracking-wider text-[10px] font-bold block mb-1">Activité</span>
                    <p class="font-semibold text-slate-900">{{ $subcontractor['activity'] }}</p>
                </div>
                <div>
                    <span class="text-slate-400 uppercase tracking-wider text-[10px] font-bold block mb-1">Contact référent</span>
                    <p class="font-semibold text-slate-900">{{ $subcontractor['contact_name'] }}</p>
                    <p class="text-slate-500">{{ $subcontractor['contact_email'] }} • {{ $subcontractor['contact_phone'] }}</p>
                </div>
                <div>
                    <span class="text-slate-400 uppercase tracking-wider text-[10px] font-bold block mb-1">Adresse du siège</span>
                    <p class="font-semibold text-slate-900">{{ $subcontractor['address'] }}</p>
                </div>
                <div>
                    <span class="text-slate-400 uppercase tracking-wider text-[10px] font-bold block mb-1">Responsable interne</span>
                    <p class="font-semibold text-slate-900">{{ $subcontractor['manager'] }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Tab 3: Échéances -->
    @if ($tab === 'deadlines')
        <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-2xs">
            <h3 class="text-base font-bold text-slate-900 mb-4">Échéances des documents</h3>
            <ul class="divide-y divide-slate-100 text-xs">
                @forelse ($deadlines as $document)
                    <li wire:key="deadline-{{ $document->id }}" class="py-3 flex items-center justify-between">
                        <div>
                            <p class="font-bold text-slate-900">{{ $document->documentType->name }}</p>
                            <p class="text-slate-500">Date d'expiration : {{ $document->expiration_date->translatedFormat('j M Y') }}</p>
                        </div>
                        <x-ui.badge :status="$document->status->value" />
                    </li>
                @empty
                    <li class="py-3 text-slate-400">Aucune échéance pour ce sous-traitant.</li>
                @endforelse
            </ul>
        </div>
    @endif

    <!-- Tab 4: Historique -->
    @if ($tab === 'history')
        <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-2xs">
            <h3 class="text-base font-bold text-slate-900 mb-4">Historique des actions</h3>
            <div class="space-y-4 text-xs">
                @forelse ($history as $entry)
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center shrink-0">
                            <x-ui.icon name="document" class="w-3.5 h-3.5" />
                        </div>
                        <div>
                            <p class="font-semibold text-slate-900">{{ $entry['action'] }}</p>
                            <p class="text-slate-500 text-[11px]">Par {{ $entry['author'] }} le {{ $entry['date'] }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-slate-400">Aucune action enregistrée.</p>
                @endforelse
            </div>
        </div>
    @endif

    <!-- Tab 5: Commentaires internes -->
    @if ($tab === 'comments')
        <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-2xs space-y-6">
            <h3 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">Commentaires internes</h3>

            <div class="space-y-4">
                @if ($subcontractor['notes'])
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/60 space-y-1">
                        <span class="text-xs font-bold text-slate-900">Notes sur le sous-traitant</span>
                        <p class="text-xs text-slate-700 leading-relaxed whitespace-pre-line">{{ $subcontractor['notes'] }}</p>
                    </div>
                @endif
                @foreach ($comments as $c)
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/60 space-y-1">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-slate-900">{{ $c['author'] }}@if ($c['document']) <span class="font-normal text-slate-500">— {{ $c['document'] }}</span>@endif</span>
                            <span class="text-slate-400 text-[11px]">{{ $c['date'] }}</span>
                        </div>
                        <p class="text-xs text-slate-700 leading-relaxed">{{ $c['text'] }}</p>
                    </div>
                @endforeach
                @if (! $subcontractor['notes'] && $comments->isEmpty())
                    <p class="text-xs text-slate-400">Aucun commentaire interne.</p>
                @endif
            </div>

            @if ($canEdit)
                <!-- Form Add Comment -->
                <form wire:submit="addComment" class="space-y-3 pt-2">
                    <x-ui.textarea label="Ajouter un commentaire interne" wire:model="new_comment" placeholder="Saisissez une note confidentielle pour l'équipe..." rows="2" />
                    <x-ui.button variant="primary" type="submit" size="sm">
                        Ajouter la note
                    </x-ui.button>
                </form>
            @endif
        </div>
    @endif

    <!-- Internal Document Upload Modal (§9.10) -->
    @if ($showUploadModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 border border-slate-100 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">Dépôt interne : {{ $uploadDocumentName }}</h3>
                    <button type="button" wire:click="$set('showUploadModal', false)" class="text-slate-400 hover:text-slate-600">
                        <x-ui.icon name="x-mark" class="w-5 h-5" />
                    </button>
                </div>

                <form wire:submit="uploadInternalDocument" class="space-y-4 text-xs">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Fichier (PDF, JPG, PNG)</label>
                        <input type="file" wire:model="internal_file" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700" required />
                        @error('internal_file')
                            <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.input label="Date d'émission" type="date" wire:model="internal_issue_date" />
                        <x-ui.input label="Date d'expiration" type="date" wire:model="internal_expiry_date" />
                    </div>
                    @error('internal_expiry_date')
                        <p class="text-xs text-rose-600 font-semibold">{{ $message }}</p>
                    @enderror

                    <x-ui.textarea label="Commentaire (optionnel)" wire:model="internal_upload_comment" rows="2" />

                    <div class="flex justify-end gap-3 pt-3 border-t border-slate-100">
                        <x-ui.button variant="ghost" type="button" wire:click="$set('showUploadModal', false)">Annuler</x-ui.button>
                        <x-ui.button variant="primary" type="submit" loading>Déposer et valider</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
