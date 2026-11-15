<div class="space-y-6" x-data="{ zoom: 100 }">
    <!-- Toast notification -->
    @if ($toastMessage)
        <div class="bg-emerald-500 text-white p-4 rounded-xl shadow-lg flex items-center justify-between animate-fade-in">
            <div class="flex items-center gap-2 text-sm font-semibold">
                <x-ui.icon name="check-circle" class="w-5 h-5 text-white" />
                <span>{{ $toastMessage }}</span>
            </div>
            <x-ui.button variant="ghost" class="text-white hover:bg-white/20" wire:click="nextDocument">
                Passer au document suivant →
            </x-ui.button>
        </div>
    @endif

    <!-- Top Bar Navigation & Info -->
    <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('documents.pending') }}" class="p-2 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100">
                <x-ui.icon name="arrow-left" class="w-5 h-5" />
            </a>
            <div class="flex items-center gap-3">
                <x-ui.icon name="document" class="w-6 h-6 text-blue-600" />
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-lg font-bold text-slate-900 leading-tight">{{ $filename }}</h1>
                        @if ($is_archived)
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-200 text-slate-700 uppercase">Archivé</span>
                        @endif
                    </div>
                    <p class="text-xs text-slate-500">Sous-traitant : <a href="{{ route('subcontractors.show', $subcontractor_id) }}" class="text-blue-600 font-semibold hover:underline">{{ $subcontractor_name }}</a></p>
                </div>
                <x-ui.badge :status="$status" />
            </div>
        </div>

        <div class="flex items-center gap-2">
            <x-ui.button variant="secondary" size="sm" wire:click="toggleArchiveDocument">
                {{ $is_archived ? 'Restaurer' : 'Archiver' }}
            </x-ui.button>
            <x-ui.button variant="ghost" size="sm" wire:click="previousDocument" icon="chevron-left">
                Document précédent
            </x-ui.button>
            <x-ui.button variant="ghost" size="sm" wire:click="nextDocument" icon="chevron-right" iconPosition="right">
                Document suivant
            </x-ui.button>
        </div>
    </div>

    <!-- Main 2-Columns Layout (Desktop 60% / 40%, Mobile Stacked) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Left Column: Viewer PDF (~60%) -->
        <div class="lg:col-span-7 bg-slate-900 rounded-xl overflow-hidden shadow-xl flex flex-col min-h-[550px]">
            <!-- Toolbar -->
            <div class="bg-slate-800 px-4 py-2.5 flex items-center justify-between text-white text-xs border-b border-slate-700">
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-slate-300">Aperçu sécurisé ({{ $version }})</span>
                    <span class="text-slate-500">|</span>
                    <span class="text-slate-400">Déposé le {{ $submitted_at }}</span>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" @click="zoom = Math.max(50, zoom - 10)" class="p-1 text-slate-300 hover:text-white rounded hover:bg-slate-700" title="Zoom arrière">
                        <x-ui.icon name="zoom-out" class="w-4 h-4" />
                    </button>
                    <span class="text-[11px] text-slate-300 font-mono w-10 text-center" x-text="zoom + '%'"></span>
                    <button type="button" @click="zoom = Math.min(200, zoom + 10)" class="p-1 text-slate-300 hover:text-white rounded hover:bg-slate-700" title="Zoom avant">
                        <x-ui.icon name="zoom-in" class="w-4 h-4" />
                    </button>
                    <a href="{{ route('documents.download', $id) }}" class="p-1 text-slate-300 hover:text-white rounded hover:bg-slate-700 ml-2 flex items-center gap-1" title="Télécharger le fichier">
                        <x-ui.icon name="download" class="w-4 h-4" />
                        <span class="hidden sm:inline">Télécharger</span>
                    </a>
                </div>
            </div>

            <!-- PDF Viewer Iframe -->
            <div class="flex-1 bg-slate-800 p-4 flex items-center justify-center overflow-auto">
                <div class="bg-white shadow-2xl transition-transform duration-200 origin-top" :style="'transform: scale(' + (zoom/100) + ')'">
                    <iframe src="{{ route('documents.download', ['id' => $id, 'inline' => 1]) }}" class="w-[500px] h-[650px] border-0"></iframe>
                </div>
            </div>
        </div>

        <!-- Right Column: Document Details & Validation Panel (~40%) -->
        <div class="lg:col-span-5 space-y-6">
            @if ($rejection_reason_display)
                <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 text-xs text-rose-800 space-y-1">
                    <div class="font-bold flex items-center gap-1.5 text-rose-700">
                        <x-ui.icon name="x-circle" class="w-4 h-4" />
                        <span>Motif de rejet : {{ $rejection_reason_display }}</span>
                    </div>
                    @if ($rejection_comment_display)
                        <p class="text-rose-600">{{ $rejection_comment_display }}</p>
                    @endif
                </div>
            @endif

            <!-- Card Informations du document -->
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-2xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">Informations du document</h3>
                    <button type="button" wire:click="saveDates" class="text-xs font-semibold text-blue-600 hover:underline">
                        Enregistrer les dates
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <span class="text-slate-400 block font-semibold text-[10px] uppercase">Catégorie</span>
                            <span class="font-semibold text-slate-800">{{ $category }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block font-semibold text-[10px] uppercase">Version / Déposé par</span>
                            <span class="font-semibold text-slate-800">{{ $version }} ({{ $submitted_by }})</span>
                        </div>
                    </div>

                    <!-- Editable dates -->
                    <div class="grid grid-cols-2 gap-3 pt-2">
                        <x-ui.input label="Date d'émission" type="date" wire:model="issue_date" />
                        <x-ui.input label="Date d'expiration" type="date" wire:model="expiry_date" />
                    </div>

                    @if ($submitter_comment)
                        <div class="bg-slate-50 p-3 rounded-lg border border-slate-100 mt-2">
                            <span class="text-slate-400 block font-semibold text-[10px] uppercase mb-0.5">Commentaire du déposant</span>
                            <p class="text-slate-700 italic">"{{ $submitter_comment }}"</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-2xs space-y-3">
                <x-ui.button variant="success" class="w-full" wire:click="validateDocument" icon="check-circle" loading>
                    Valider le document
                </x-ui.button>

                <div class="grid grid-cols-2 gap-3">
                    <x-ui.button variant="danger" wire:click="openRejectModal" icon="x-circle">
                        Rejeter
                    </x-ui.button>

                    <x-ui.button variant="secondary" wire:click="requestNewVersion" icon="arrow-path">
                        Demander nouvelle version
                    </x-ui.button>
                </div>
            </div>

            <!-- Two Side-by-Side Textareas -->
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-2xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">Remarques & Commentaires</h3>
                    <button type="button" wire:click="saveComments" class="text-xs font-semibold text-blue-600 hover:underline">
                        Ajouter commentaire
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.textarea 
                        label="Commentaire interne" 
                        wire:model="internal_comment" 
                        placeholder="Visible uniquement par l'équipe..." 
                        rows="3" 
                    />
                    <x-ui.textarea 
                        label="Commentaire pour le sous-traitant" 
                        wire:model="subcontractor_comment" 
                        placeholder="Transmis au sous-traitant dans l'e-mail..." 
                        rows="3" 
                    />
                </div>

                @if (!empty($commentsList))
                    <div class="pt-3 border-t border-slate-100 space-y-2 max-h-48 overflow-y-auto">
                        @foreach ($commentsList as $c)
                            <div class="p-2.5 rounded-lg text-xs {{ $c['visibility'] === 'internal' ? 'bg-amber-50/70 border border-amber-200' : 'bg-blue-50/70 border border-blue-200' }}">
                                <div class="flex items-center justify-between text-[10px] font-bold mb-1">
                                    <span class="{{ $c['visibility'] === 'internal' ? 'text-amber-800' : 'text-blue-800' }}">
                                        {{ $c['author'] }} ({{ $c['visibility'] === 'internal' ? 'Interne' : 'Externe' }})
                                    </span>
                                    <span class="text-slate-400">{{ $c['created_at'] }}</span>
                                </div>
                                <p class="text-slate-700">{{ $c['body'] }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Historique des versions -->
            @if (!empty($versionsList))
                <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-2xs space-y-3">
                    <h3 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2">Historique des versions ({{ count($versionsList) }})</h3>
                    <div class="divide-y divide-slate-100 text-xs">
                        @foreach ($versionsList as $v)
                            <div class="py-2 flex items-center justify-between gap-2">
                                <div>
                                    <div class="font-semibold text-slate-800">
                                        <span class="font-mono bg-slate-100 px-1.5 py-0.5 rounded text-[11px] mr-1">{{ $v['version_number'] }}</span>
                                        {{ $v['original_name'] }}
                                    </div>
                                    <div class="text-[11px] text-slate-400">
                                        {{ $v['uploaded_at'] }} • {{ $v['uploaded_by'] }} • {{ $v['file_size'] }}
                                    </div>
                                </div>
                                <a href="{{ route('documents.download', ['id' => $id, 'version' => $v['id']]) }}" class="text-blue-600 hover:underline font-semibold text-[11px]">
                                    Télécharger
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Rejection Modal -->
    @if ($showRejectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 border border-slate-100 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2 text-rose-600">
                        <x-ui.icon name="x-circle" class="w-5 h-5" />
                        <span>Rejeter le document</span>
                    </h3>
                    <button type="button" wire:click="$set('showRejectModal', false)" class="text-slate-400 hover:text-slate-600">
                        <x-ui.icon name="x-mark" class="w-5 h-5" />
                    </button>
                </div>

                <div class="space-y-4">
                    <x-ui.select label="Motif du rejet (OBLIGATOIRE)" wire:model="rejection_reason" required>
                        <option value="">-- Choisir le motif obligatoirement --</option>
                        <option value="Document illisible">Document illisible</option>
                        <option value="Document incomplet">Document incomplet</option>
                        <option value="Document expiré">Document expiré</option>
                        <option value="Document incorrect">Document incorrect</option>
                        <option value="Mauvaise entreprise">Mauvaise entreprise</option>
                        <option value="Date absente">Date absente</option>
                        <option value="Document non signé">Document non signé</option>
                        <option value="Autre motif">Autre motif</option>
                    </x-ui.select>
                    @error('rejection_reason')
                        <p class="text-xs text-rose-600 font-bold">{{ $message }}</p>
                    @enderror

                    <x-ui.textarea label="Message explicatif pour le sous-traitant" wire:model="rejection_message" placeholder="Expliquez ce qui doit être corrigé..." rows="3" />
                </div>

                <div class="flex justify-end gap-3 pt-3 border-t border-slate-100">
                    <x-ui.button variant="ghost" wire:click="$set('showRejectModal', false)">
                        Annuler
                    </x-ui.button>
                    <x-ui.button variant="danger" wire:click="confirmReject">
                        Confirmer le rejet
                    </x-ui.button>
                </div>
            </div>
        </div>
    @endif
</div>
