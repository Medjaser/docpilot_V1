<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('subcontractors.index') }}" class="text-xs font-semibold text-blue-600 hover:underline flex items-center gap-1 mb-1">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" />
                <span>Retour à la liste</span>
            </a>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">
                {{ $subcontractorId ? 'Modifier le sous-traitant' : 'Ajouter un sous-traitant' }}
            </h1>
            <p class="text-sm text-slate-500 mt-0.5">Complétez la fiche entreprise et assignez un modèle de dossier administratif</p>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
        <!-- 3 Columns Card Grid on Desktop -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Card 1: Entreprise -->
            <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-2xs space-y-4">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-3">
                    <div class="w-7 h-7 rounded-lg bg-blue-600 text-white font-bold text-xs flex items-center justify-center">1</div>
                    <h3 class="font-bold text-slate-900 text-base">Entreprise</h3>
                </div>

                <x-ui.input label="Raison sociale" wire:model="legal_name" required placeholder="ex. ÉlecPro Services" />
                <x-ui.input label="Nom commercial" wire:model="trade_name" placeholder="ex. ÉlecPro" />
                <x-ui.input label="Numéro SIRET (14 chiffres)" wire:model="siret" required placeholder="12345678900012" help="Ex: 12345678900012" />
                
                <x-ui.select label="Type de prestataire" wire:model="provider_type">
                    <option value="Sous-traitant direct">Sous-traitant direct</option>
                    <option value="Sous-traitant de rang 2">Sous-traitant de rang 2</option>
                    <option value="Prestataire ponctuel">Prestataire ponctuel</option>
                </x-ui.select>

                <x-ui.input label="Secteur / Activité principale" wire:model="activity" required placeholder="ex. Électricité générale" />
            </div>

            <!-- Card 2: Contact -->
            <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-2xs space-y-4">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-3">
                    <div class="w-7 h-7 rounded-lg bg-blue-600 text-white font-bold text-xs flex items-center justify-center">2</div>
                    <h3 class="font-bold text-slate-900 text-base">Contact référent</h3>
                </div>

                <x-ui.input label="Prénom" wire:model="first_name" required placeholder="Karim" />
                <x-ui.input label="Nom" wire:model="last_name" required placeholder="Benali" />
                <x-ui.input label="E-mail professionnel" type="email" wire:model="email" required icon="envelope" placeholder="k.benali@elecpro.fr" />
                <x-ui.input label="Téléphone" wire:model="phone" icon="phone" placeholder="06 12 34 56 78" />
            </div>

            <!-- Card 3: Adresse -->
            <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-2xs space-y-4">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-3">
                    <div class="w-7 h-7 rounded-lg bg-blue-600 text-white font-bold text-xs flex items-center justify-center">3</div>
                    <h3 class="font-bold text-slate-900 text-base">Adresse du siège</h3>
                </div>

                <x-ui.input label="Adresse" wire:model="address" placeholder="14 rue de l'Industrie" />
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.input label="Code postal" wire:model="postal_code" required placeholder="69007" />
                    <x-ui.input label="Ville" wire:model="city" required placeholder="Lyon" />
                </div>
                <x-ui.select label="Pays" wire:model="country">
                    <option value="FR">France</option>
                    <option value="BE">Belgique</option>
                    <option value="CH">Suisse</option>
                    <option value="LU">Luxembourg</option>
                </x-ui.select>
            </div>
        </div>

        <!-- Full Width Cards: 4 & 5 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Card 4: Suivi interne -->
            <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-2xs space-y-4">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-3">
                    <div class="w-7 h-7 rounded-lg bg-blue-600 text-white font-bold text-xs flex items-center justify-center">4</div>
                    <h3 class="font-bold text-slate-900 text-base">Suivi interne</h3>
                </div>

                <x-ui.select label="Responsable interne" wire:model="internal_owner_id">
                    <option value="">Sélectionner un responsable...</option>
                    @foreach ($managers as $manager)
                        <option value="{{ $manager->id }}">{{ $manager->full_name }}</option>
                    @endforeach
                </x-ui.select>

                <div class="grid grid-cols-2 gap-4">
                    <x-ui.input label="Date de début d'intervention" type="date" wire:model="start_date" />
                    <x-ui.input label="Date de fin d'intervention" type="date" wire:model="end_date" />
                </div>

                <!-- Tags Pill System -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Tags & Étiquettes</label>
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        @foreach ($tags as $t)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                <span>{{ $t }}</span>
                                <button type="button" wire:click="removeTag('{{ $t }}')" class="hover:text-blue-900">
                                    <x-ui.icon name="x-mark" class="w-3.5 h-3.5" />
                                </button>
                            </span>
                        @endforeach
                    </div>
                    <div class="flex items-center gap-2">
                        <input 
                            type="text" 
                            wire:model="new_tag" 
                            wire:keydown.enter.prevent="addTag"
                            placeholder="Ajouter un tag (ex. Urgent)..." 
                            class="h-8 px-3 text-xs rounded-lg border border-slate-300 flex-1"
                        />
                        <x-ui.button variant="secondary" size="sm" type="button" wire:click="addTag">Ajouter</x-ui.button>
                    </div>
                </div>

                <x-ui.textarea label="Commentaires internes" wire:model="internal_comments" placeholder="Notes visibles uniquement par l'équipe interne..." rows="2" />
            </div>

            <!-- Card 5: Modèle de dossier -->
            <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-2xs space-y-4">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-3">
                    <div class="w-7 h-7 rounded-lg bg-blue-600 text-white font-bold text-xs flex items-center justify-center">5</div>
                    <h3 class="font-bold text-slate-900 text-base">Modèle de dossier administratif</h3>
                </div>

                @if (! $subcontractorId)
                    @if ($templates->isNotEmpty())
                        <x-ui.select label="Choisir un modèle de dossier" wire:model.live="template_id">
                            @foreach ($templates as $tpl)
                                <option value="{{ $tpl->id }}">{{ $tpl->name }} ({{ $tpl->documentTypes->count() }} documents)</option>
                            @endforeach
                        </x-ui.select>

                        @if ($selectedTemplate)
                            <x-ui.alert type="info" title="Modèle sélectionné">
                                Ce modèle contient <strong>{{ $selectedTemplate->documentTypes->count() }} documents requis</strong> pour la conformité légale.
                            </x-ui.alert>

                            <div class="space-y-2 pt-1 border-t border-slate-100">
                                <p class="text-xs font-bold text-slate-700 uppercase tracking-wider">Documents requis dans ce modèle :</p>
                                <ul class="space-y-2 text-xs">
                                    @foreach ($selectedTemplate->documentTypes as $docType)
                                        <li class="flex items-center justify-between p-2 rounded-lg bg-slate-50 border border-slate-100">
                                            <span class="flex items-center gap-2 text-slate-800 font-medium">
                                                <x-ui.icon name="check-circle" class="w-4 h-4 text-emerald-500" />
                                                <span>{{ $docType->name }}</span>
                                            </span>
                                            @if ($docType->has_expiration_date)
                                                <span class="px-2 py-0.5 text-[10px] font-bold bg-amber-100 text-amber-800 rounded-full">
                                                    {{ $docType->is_mandatory ? 'Obligatoire' : 'Facultatif' }} · Expire {{ $docType->default_validity_months ? "({$docType->default_validity_months} mois)" : '' }}
                                                </span>
                                            @else
                                                <x-ui.badge status="valid" :label="$docType->is_mandatory ? 'Obligatoire' : 'Facultatif'" size="sm" />
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @else
                        <p class="text-xs text-slate-500">Aucun modèle de dossier configuré.</p>
                    @endif
                @else
                    <x-ui.alert type="info" title="Dossier administratif">
                        Les documents de ce sous-traitant sont gérés directement depuis sa fiche détaillée.
                    </x-ui.alert>
                @endif
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
            <x-ui.button variant="ghost" href="{{ route('subcontractors.index') }}">
                Annuler
            </x-ui.button>

            <x-ui.button variant="secondary" type="submit" loading>
                Enregistrer
            </x-ui.button>

            <x-ui.button variant="primary" type="button" wire:click="save(true)" loading>
                Enregistrer et envoyer la demande
            </x-ui.button>
        </div>
    </form>
</div>
