<div
    class="w-full max-w-full overflow-x-hidden"
    x-data="portalUploadState($wire)"
    x-on:livewire-upload-start="uploading = true; progress = 5; networkError = null"
    x-on:livewire-upload-progress="progress = $event.detail.progress"
    x-on:livewire-upload-finish="uploading = false; progress = 100"
    x-on:livewire-upload-error="uploading = false; progress = 0; networkError = 'L\'envoi a échoué, vérifiez votre connexion et réessayez'"
>
    @if ($portalDisabled)
        <!-- Entreprise suspendue / résiliée / archivée -->
        <div role="alert" class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-md text-center space-y-4 my-6">
            <div class="w-16 h-16 bg-slate-100 text-slate-500 rounded-2xl flex items-center justify-center mx-auto">
                <x-ui.icon name="exclamation-triangle" class="w-9 h-9" />
            </div>

            <div class="space-y-1">
                <h1 class="text-lg sm:text-xl font-extrabold text-slate-900">Portail de dépôt indisponible</h1>
                <p class="text-xs text-slate-500 max-w-sm mx-auto leading-relaxed">
                    Cet espace de dépôt est temporairement désactivé. Aucun document ne peut être transmis pour le moment. Merci de contacter directement votre donneur d'ordre.
                </p>
            </div>
        </div>

    @elseif ($invalidToken)
        <!-- Lien expiré ou révoqué : aucune information sur l'entreprise -->
        <div role="alert" class="bg-white rounded-2xl border border-rose-200 p-6 sm:p-8 shadow-md text-center space-y-5 my-6">
            <div class="w-16 h-16 bg-rose-100 text-rose-600 rounded-2xl flex items-center justify-center mx-auto">
                <x-ui.icon name="exclamation-triangle" class="w-9 h-9" />
            </div>

            <div class="space-y-2">
                <h1 class="text-lg sm:text-xl font-extrabold text-slate-900">Ce lien n'est plus valide</h1>
                <p class="text-xs sm:text-sm text-slate-600 max-w-sm mx-auto leading-relaxed">
                    Ce lien de dépôt a expiré ou a été désactivé. Pour déposer vos documents, veuillez demander un nouveau lien à votre interlocuteur.
                </p>
            </div>
        </div>

    @elseif ($submittedSuccess)
        <!-- Écran de succès après confirmation + retour vers l'état des documents -->
        <div class="bg-white rounded-2xl border border-emerald-200 p-6 sm:p-8 shadow-md text-center space-y-6 my-4">
            <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto">
                <x-ui.icon name="check-circle" class="w-10 h-10" />
            </div>

            <div class="space-y-1">
                <h1 class="text-lg sm:text-xl font-extrabold text-slate-900">Documents transmis avec succès !</h1>
                <p class="text-xs sm:text-sm text-slate-500 max-w-sm mx-auto">
                    Merci ! L'équipe de <strong>{{ $company_name }}</strong> a bien reçu vos pièces et va procéder à leur vérification.
                </p>
            </div>

            <div class="bg-slate-50 rounded-xl p-4 text-left border border-slate-200 space-y-2 text-xs">
                <p class="font-bold text-slate-700">Récapitulatif des pièces transmises :</p>
                <ul class="space-y-1.5 text-slate-600">
                    @forelse ($transmittedNames as $docName)
                        <li class="flex items-center gap-2 text-emerald-700 font-medium">
                            <x-ui.icon name="check" class="w-4 h-4 text-emerald-600 shrink-0" />
                            <span class="break-words">{{ $docName }}</span>
                        </li>
                    @empty
                        <li class="flex items-center gap-2 text-emerald-700 font-medium">
                            <x-ui.icon name="check" class="w-4 h-4 text-emerald-600 shrink-0" />
                            <span>Mise à jour enregistrée</span>
                        </li>
                    @endforelse
                </ul>
            </div>

            <div class="pt-2">
                <button
                    type="button"
                    wire:click="backToDocuments"
                    class="w-full sm:w-auto min-h-[44px] px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs sm:text-sm inline-flex items-center justify-center gap-2 transition-colors shadow-xs"
                >
                    <x-ui.icon name="eye" class="w-4 h-4" />
                    <span>Voir l'état de mes documents</span>
                </button>
            </div>
        </div>

    @else
        <!-- Vue principale de dépôt mobile-first -->
        <div class="space-y-5">
            <!-- Message d'erreur réseau (coupure pendant l'upload) -->
            <div x-show="networkError" x-cloak role="alert" class="bg-rose-50 border border-rose-300 text-rose-900 rounded-xl p-4 text-xs sm:text-sm flex items-start gap-3">
                <x-ui.icon name="exclamation-triangle" class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" />
                <div class="flex-1 min-w-0">
                    <p class="font-bold" x-text="networkError"></p>
                    <p class="text-xs text-rose-700 mt-0.5">Vos autres documents déjà ajoutés sont conservés.</p>
                </div>
            </div>

            @if ($globalError)
                <div role="alert" class="bg-rose-50 border border-rose-300 text-rose-900 rounded-xl p-4 text-xs sm:text-sm font-semibold">
                    {{ $globalError }}
                </div>
            @endif

            <!-- Barre de progression pendant l'upload d'un fichier -->
            <div x-show="uploading" x-cloak class="bg-blue-50 border border-blue-200 rounded-xl p-3.5 space-y-2">
                <div class="flex items-center justify-between text-xs font-bold text-blue-900">
                    <span>Envoi du fichier en cours...</span>
                    <span x-text="progress + '%'"></span>
                </div>
                <div class="w-full h-2 bg-blue-100 rounded-full overflow-hidden">
                    <div class="h-full bg-blue-600 transition-all duration-150 rounded-full" :style="'width: ' + progress + '%'"></div>
                </div>
            </div>

            <!-- En-tête de la demande & progression -->
            <div class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 shadow-2xs space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
                    <div class="min-w-0">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600 block">Demande officielle</span>
                        <h1 class="text-base sm:text-lg font-extrabold text-slate-900 break-words">Mise à jour de votre dossier administratif</h1>
                    </div>
                    <div class="bg-amber-50 border border-amber-200 px-3 py-1.5 rounded-xl text-left sm:text-right shrink-0">
                        <span class="text-[10px] text-amber-700 font-semibold block uppercase">Date limite</span>
                        <span class="text-xs font-bold text-amber-900">{{ $deadline }}</span>
                    </div>
                </div>

                <!-- Message du gestionnaire -->
                <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200/80 text-xs text-slate-600 space-y-1">
                    <span class="font-bold text-slate-800 block">Message de {{ $manager_name }} ({{ $company_name }}) :</span>
                    <p class="italic leading-relaxed break-words">"{{ $manager_message }}"</p>
                </div>

                <!-- Barre de progression globale -->
                @php
                    $uploadedDocs = collect($documents)->where('uploaded', true)->count();
                    $totalDocs = max(1, count($documents));
                    $progressPct = round(($uploadedDocs / $totalDocs) * 100);
                @endphp
                <div class="space-y-1.5 pt-1">
                    <div class="flex justify-between text-xs font-bold gap-2">
                        <span class="text-slate-700">Progression globale</span>
                        <span class="text-blue-600 shrink-0">{{ $uploadedDocs }} / {{ count($documents) }} transmis</span>
                    </div>
                    <x-ui.progress :percentage="$progressPct" size="md" />
                </div>
            </div>

            <!-- Liste des pièces à fournir -->
            <div class="space-y-3.5">
                <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider px-1">Liste des pièces à fournir</h2>

                @foreach ($documents as $index => $doc)
                    @php
                        $isOpenByDefault = in_array($doc['status'], ['rejected', 'missing', 'requested'], true);
                    @endphp
                    <div wire:key="portal-doc-{{ $doc['id'] }}" x-data="{ open: {{ $isOpenByDefault ? 'true' : 'false' }} }" class="bg-white rounded-2xl border transition-all shadow-2xs {{ $doc['status'] === 'rejected' ? 'border-rose-300 ring-2 ring-rose-50' : 'border-slate-200' }}">
                        <!-- En-tête accordéon (zone tactile >= 44px) -->
                        <button
                            type="button"
                            @click="open = !open"
                            class="w-full min-h-[52px] p-3.5 sm:p-4 flex items-center justify-between gap-2 text-left cursor-pointer select-none"
                        >
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 {{ $doc['status'] === 'rejected' ? 'bg-rose-100 text-rose-600' : ($doc['status'] === 'valid' ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-500') }}">
                                    <x-ui.icon name="document" class="w-5 h-5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-bold text-slate-900 text-xs sm:text-sm break-words leading-snug">{{ $doc['name'] }}</h3>
                                    <p class="text-[11px] text-slate-400 mt-0.5">{{ $doc['formats'] ?? 'PDF, JPG, PNG' }} • Max {{ $doc['max_mb'] ?? 10 }} Mo</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <x-ui.badge :status="$doc['status']" size="sm" />
                                <x-ui.icon name="chevron-down" class="w-4 h-4 text-slate-400 transition-transform duration-200" ::class="{ 'rotate-180': open }" />
                            </div>
                        </button>

                        <!-- Corps de la carte -->
                        <div x-show="open" x-transition class="p-3.5 sm:p-4 border-t border-slate-100 space-y-4 bg-slate-50/50 rounded-b-2xl" style="display: none;">
                            <!-- Encadré rouge en cas de rejet -->
                            @if ($doc['status'] === 'rejected' && ($doc['rejection_reason'] || $doc['rejection_comment']))
                                <div class="bg-rose-50 border border-rose-200 p-3 rounded-xl text-xs text-rose-900 space-y-1">
                                    <div class="flex items-center gap-2 font-bold text-rose-700">
                                        <x-ui.icon name="x-circle" class="w-4 h-4 text-rose-600 shrink-0" />
                                        <span class="break-words">Motif du rejet : {{ $doc['rejection_reason'] ?? 'À corriger' }}</span>
                                    </div>
                                    @if ($doc['rejection_comment'])
                                        <p class="text-rose-800 leading-relaxed pl-6 break-words">"{{ $doc['rejection_comment'] }}"</p>
                                    @endif
                                </div>
                            @endif

                            <!-- Message d'erreur simple en français sur ce document -->
                            @if (!empty($fileErrors[$index]))
                                <div role="alert" class="bg-rose-50 border border-rose-300 text-rose-800 p-3 rounded-xl text-xs font-semibold flex items-start gap-2">
                                    <x-ui.icon name="exclamation-triangle" class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" />
                                    <span class="break-words">{{ $fileErrors[$index] }}</span>
                                </div>
                            @endif

                            <!-- Aperçu miniature photo ou icône PDF après sélection + bouton Supprimer -->
                            @if (!empty($doc['file_name']))
                                <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 flex items-center justify-between gap-3 text-xs">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        @if (!empty($doc['preview_url']))
                                            <img src="{{ $doc['preview_url'] }}" alt="Aperçu {{ $doc['file_name'] }}" class="w-12 h-12 rounded-lg object-cover border border-emerald-300 bg-white shrink-0" />
                                        @elseif (!empty($doc['is_image']))
                                            <div class="w-11 h-11 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                                <x-ui.icon name="camera" class="w-5 h-5" />
                                            </div>
                                        @else
                                            <div class="w-11 h-11 rounded-lg bg-rose-100 text-rose-700 flex flex-col items-center justify-center shrink-0 font-bold text-[10px]">
                                                <x-ui.icon name="document" class="w-4 h-4" />
                                                <span>PDF</span>
                                            </div>
                                        @endif

                                        <div class="min-w-0 flex-1">
                                            <p class="font-bold text-emerald-950 truncate">{{ $doc['file_name'] }}</p>
                                            <p class="text-[11px] text-emerald-700">
                                                {{ $doc['file_size_label'] ?? 'Prêt à l\'envoi' }}
                                            </p>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        wire:click="removeFile({{ $index }})"
                                        :disabled="uploading"
                                        class="min-h-[44px] px-3 py-2 rounded-lg bg-white border border-rose-200 text-rose-600 hover:bg-rose-50 font-semibold text-xs shrink-0 transition-colors"
                                    >
                                        Supprimer
                                    </button>
                                </div>
                            @endif

                            <!-- 2 Boutons d'action : Prendre une photo & Choisir un fichier (zones >= 44px) -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                <!-- Bouton 1 : Prendre une photo (accept="image/*" capture="environment") -->
                                <div
                                    style="position: relative; overflow: hidden;"
                                    class="min-h-[48px] flex items-center justify-center gap-2.5 p-3 rounded-xl border-2 border-dashed border-blue-300 bg-blue-50/50 hover:bg-blue-50 text-blue-700 cursor-pointer transition-colors"
                                >
                                    <x-ui.icon name="camera" class="w-5 h-5 text-blue-600 shrink-0" />
                                    <span class="text-xs font-bold">Prendre une photo</span>
                                    <input
                                        id="camera-input-{{ $index }}"
                                        type="file"
                                        accept="image/*"
                                        capture="environment"
                                        ontouchstart="if(/Android|HuaweiBrowser|HUAWEI|HONOR|wv/i.test(navigator.userAgent||''))this.removeAttribute('capture')"
                                        onpointerdown="if(/Android|HuaweiBrowser|HUAWEI|HONOR|wv/i.test(navigator.userAgent||''))this.removeAttribute('capture')"
                                        x-init="initCameraInput($el)"
                                        x-on:change="handlePortalFile($event, {{ $index }})"
                                        style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 20;"
                                    />
                                </div>

                                <!-- Bouton 2 : Choisir un fichier (galerie ou PDF, sans capture) -->
                                <div
                                    style="position: relative; overflow: hidden;"
                                    class="min-h-[48px] flex items-center justify-center gap-2.5 p-3 rounded-xl border-2 border-dashed border-slate-300 bg-white hover:border-blue-500 text-slate-700 cursor-pointer transition-colors"
                                >
                                    <x-ui.icon name="upload" class="w-5 h-5 text-slate-500 shrink-0" />
                                    <span class="text-xs font-bold">Choisir un fichier</span>
                                    <input
                                        id="file-input-{{ $index }}"
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png,image/jpeg,image/png,application/pdf"
                                        x-on:change="handlePortalFile($event, {{ $index }})"
                                        style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 20;"
                                    />
                                </div>
                            </div>

                            <!-- Dates (type="date" natif mobile, min-h-[44px]) & Commentaire -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Date d'émission (optionnel)</label>
                                    <input
                                        type="date"
                                        wire:model="documents.{{ $index }}.issue_date"
                                        class="w-full min-h-[44px] px-3 py-2 text-xs sm:text-sm rounded-xl border border-slate-300 bg-white text-slate-800 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Date d'expiration (optionnel)</label>
                                    <input
                                        type="date"
                                        wire:model="documents.{{ $index }}.expiry_date"
                                        class="w-full min-h-[44px] px-3 py-2 text-xs sm:text-sm rounded-xl border border-slate-300 bg-white text-slate-800 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Ajouter un commentaire (optionnel)</label>
                                <input
                                    type="text"
                                    wire:model="documents.{{ $index }}.comment"
                                    placeholder="Précision utile pour la vérification..."
                                    class="w-full min-h-[44px] px-3 py-2 text-xs sm:text-sm rounded-xl border border-slate-300 bg-white text-slate-800 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600"
                                />
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Barre fixe en bas de l'écran avec safe-area-inset-bottom -->
            <div
                class="fixed bottom-0 inset-x-0 bg-white/95 backdrop-blur-xs border-t border-slate-200 px-4 pt-3 shadow-2xl z-40"
                style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));"
            >
                <div class="max-w-2xl mx-auto">
                    <button
                        type="button"
                        wire:click="submitAll"
                        wire:loading.attr="disabled"
                        :disabled="uploading"
                        class="w-full min-h-[48px] px-5 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold text-sm flex items-center justify-center gap-2 shadow-md transition-all"
                    >
                        <span wire:loading.remove wire:target="submitAll">Confirmer l'envoi des documents →</span>
                        <span wire:loading wire:target="submitAll">Envoi en cours, veuillez patienter...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
