<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Abonnement et stockage</h1>
        <p class="text-sm text-slate-500 mt-0.5">Consultez votre consommation actuelle et gérez votre offre SaaS</p>
    </div>

    @if ($statusBanner)
        <x-ui.alert type="success" title="Abonnement mis à jour" dismissible>
            {{ $statusBanner }}
        </x-ui.alert>
    @endif

    <!-- Orange Trial Banner -->
    @if ($isTrial)
        <div class="bg-amber-500 text-white rounded-xl p-4 shadow-md flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0">
                    <x-ui.icon name="clock" class="w-6 h-6 text-white" />
                </div>
                <div>
                    <h3 class="font-bold text-base">Période d'essai gratuite en cours</h3>
                    <p class="text-xs text-amber-100 mt-0.5">Votre essai se termine dans <strong>{{ $trialDaysLeft }} jours</strong>. Choisissez une offre pour continuer à utiliser DocPilot sans interruption.</p>
                </div>
            </div>
            <x-ui.button variant="secondary" href="#pricing" class="bg-white text-amber-900 hover:bg-amber-50 shrink-0">
                Choisir une offre
            </x-ui.button>
        </div>
    @endif

    <!-- Current Plan & Usage Gauges Card -->
    <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-2xs space-y-6">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                    <x-ui.icon name="crown" class="w-6 h-6 text-amber-500" />
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900">Offre actuelle : <span class="text-blue-600">{{ $currentPlan }}</span></h3>
                    <p class="text-xs text-slate-500 mt-0.5">Facturation mensuelle sans engagement via Stripe</p>
                </div>
            </div>
            <span class="px-3 py-1 {{ $company->status->value === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }} rounded-full text-xs font-bold">
                {{ $company->status->label() }}
            </span>
        </div>

        <!-- 3 Usage Gauges -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Gauge 1: Sous-traitants -->
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2">
                <div class="flex items-center justify-between text-xs font-bold">
                    <span class="text-slate-700">Sous-traitants suivis</span>
                    <span class="text-blue-600">{{ $subcontractorsUsed }} / {{ $subcontractorsMax }}</span>
                </div>
                <x-ui.progress :percentage="$subcontractorsMax > 0 ? min(100, ($subcontractorsUsed / $subcontractorsMax) * 100) : 0" />
            </div>

            <!-- Gauge 2: Utilisateurs -->
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2">
                <div class="flex items-center justify-between text-xs font-bold">
                    <span class="text-slate-700">Comptes utilisateurs</span>
                    <span class="text-blue-600">{{ $usersUsed }} / {{ $usersMax }}</span>
                </div>
                <x-ui.progress :percentage="$usersMax > 0 ? min(100, ($usersUsed / $usersMax) * 100) : 0" />
            </div>

            <!-- Gauge 3: Stockage -->
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2">
                <div class="flex items-center justify-between text-xs font-bold">
                    <span class="text-slate-700">Stockage de documents</span>
                    <span class="text-blue-600">{{ $storageUsedGb }} Go / {{ $storageMaxGb }} Go</span>
                </div>
                <x-ui.progress :percentage="$storageMaxGb > 0 ? min(100, ($storageUsedGb / $storageMaxGb) * 100) : 0" />
            </div>
        </div>
    </div>

    <!-- Pricing Comparison Cards -->
    <div id="pricing" class="space-y-4 pt-4">
        <h2 class="text-xl font-bold text-slate-900 tracking-tight text-center">Formules et abonnements</h2>
        <p class="text-xs text-slate-500 text-center">Évoluez à tout moment selon le développement de vos chantiers</p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-4">
            <!-- Plan 1: Mini -->
            <div class="bg-white rounded-2xl {{ $currentPlanKey === 'mini' ? 'border-2 border-blue-600 shadow-xl' : 'border border-slate-200 shadow-2xs' }} p-6 flex flex-col justify-between space-y-6">
                <div class="space-y-4">
                    <h3 class="text-lg font-bold text-slate-900">Mini</h3>
                    <p class="text-xs text-slate-500">Idéal pour les petites structures d'artisans</p>
                    
                    <div class="flex items-baseline">
                        <span class="text-3xl font-extrabold text-slate-900">5,90 €</span>
                        <span class="text-xs text-slate-500 ml-1">HT / mois</span>
                    </div>

                    <ul class="space-y-2.5 text-xs text-slate-600 pt-2 border-t border-slate-100">
                        <li class="flex items-center gap-2">
                            <x-ui.icon name="check" class="w-4 h-4 text-emerald-500" />
                            <span>Jusqu'à <strong>5 sous-traitants</strong></span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-ui.icon name="check" class="w-4 h-4 text-emerald-500" />
                            <span>1 utilisateur administrateur</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-ui.icon name="check" class="w-4 h-4 text-emerald-500" />
                            <span>Stockage 2 Go</span>
                        </li>
                    </ul>
                </div>

                <x-ui.button variant="{{ $currentPlanKey === 'mini' ? 'primary' : 'secondary' }}" class="w-full" wire:click="selectPlan('Mini')">
                    {{ $currentPlanKey === 'mini' ? 'Offre actuelle (Renouveler)' : 'Choisir l\'offre Mini' }}
                </x-ui.button>
            </div>

            <!-- Plan 2: Essentiel (Recommended) -->
            <div class="bg-white rounded-2xl {{ $currentPlanKey === 'essentiel' ? 'border-2 border-blue-600 shadow-xl' : 'border border-slate-200 shadow-2xs' }} p-6 flex flex-col justify-between space-y-6 relative">
                <span class="absolute -top-3.5 left-1/2 transform -translate-x-1/2 bg-blue-600 text-white px-3 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider shadow-sm">
                    Recommandé
                </span>

                <div class="space-y-4">
                    <h3 class="text-lg font-bold text-slate-900">Essentiel</h3>
                    <p class="text-xs text-slate-500">Pour les PME du BTP en forte activité</p>
                    
                    <div class="flex items-baseline">
                        <span class="text-3xl font-extrabold text-slate-900">14,90 €</span>
                        <span class="text-xs text-slate-500 ml-1">HT / mois</span>
                    </div>

                    <ul class="space-y-2.5 text-xs text-slate-600 pt-2 border-t border-slate-100">
                        <li class="flex items-center gap-2">
                            <x-ui.icon name="check" class="w-4 h-4 text-emerald-500" />
                            <span>Jusqu'à <strong>25 sous-traitants</strong></span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-ui.icon name="check" class="w-4 h-4 text-emerald-500" />
                            <span>Jusqu'à 5 utilisateurs</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-ui.icon name="check" class="w-4 h-4 text-emerald-500" />
                            <span>Stockage 10 Go</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-ui.icon name="check" class="w-4 h-4 text-emerald-500" />
                            <span>Relances automatiques illimitées</span>
                        </li>
                    </ul>
                </div>

                <x-ui.button variant="{{ $currentPlanKey === 'essentiel' ? 'primary' : 'secondary' }}" class="w-full" wire:click="selectPlan('Essentiel')">
                    {{ $currentPlanKey === 'essentiel' ? 'Offre actuelle (Renouveler)' : 'Choisir l\'offre Essentiel' }}
                </x-ui.button>
            </div>

            <!-- Plan 3: Business -->
            <div class="bg-white rounded-2xl {{ $currentPlanKey === 'business' ? 'border-2 border-blue-600 shadow-xl' : 'border border-slate-200 shadow-2xs' }} p-6 flex flex-col justify-between space-y-6">
                <div class="space-y-4">
                    <h3 class="text-lg font-bold text-slate-900">Business</h3>
                    <p class="text-xs text-slate-500">Pour les grands acteurs du BTP et multi-chantiers</p>
                    
                    <div class="flex items-baseline">
                        <span class="text-3xl font-extrabold text-slate-900">29,90 €</span>
                        <span class="text-xs text-slate-500 ml-1">HT / mois</span>
                    </div>

                    <ul class="space-y-2.5 text-xs text-slate-600 pt-2 border-t border-slate-100">
                        <li class="flex items-center gap-2">
                            <x-ui.icon name="check" class="w-4 h-4 text-emerald-500" />
                            <span>Jusqu'à <strong>100 sous-traitants</strong></span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-ui.icon name="check" class="w-4 h-4 text-emerald-500" />
                            <span>Jusqu'à 15 utilisateurs</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-ui.icon name="check" class="w-4 h-4 text-emerald-500" />
                            <span>Stockage 50 Go</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-ui.icon name="check" class="w-4 h-4 text-emerald-500" />
                            <span>Support prioritaire 24/7</span>
                        </li>
                    </ul>
                </div>

                <x-ui.button variant="{{ $currentPlanKey === 'business' ? 'primary' : 'secondary' }}" class="w-full" wire:click="selectPlan('Business')">
                    {{ $currentPlanKey === 'business' ? 'Offre actuelle (Renouveler)' : 'Passer à l\'offre Business' }}
                </x-ui.button>
            </div>
        </div>
    </div>

    <!-- Stripe Checkout Modal -->
    @if ($showCheckoutModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-5 border border-slate-100 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded bg-indigo-600 text-white text-[10px] font-extrabold uppercase tracking-wider">Stripe</span>
                        <h3 class="text-base font-bold text-slate-900">Paiement sécurisé — Offre {{ $checkoutPlanLabel }}</h3>
                    </div>
                    <button type="button" wire:click="$set('showCheckoutModal', false)" class="text-slate-400 hover:text-slate-600">
                        <x-ui.icon name="x-mark" class="w-5 h-5" />
                    </button>
                </div>

                <div class="bg-slate-50 rounded-xl p-4 border border-slate-200 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-slate-900">Abonnement mensuel DocPilot {{ $checkoutPlanLabel }}</p>
                        <p class="text-[11px] text-slate-500">Facturé à {{ $company->name }}</p>
                    </div>
                    <span class="text-lg font-extrabold text-indigo-600">{{ $checkoutPlanPrice }} HT</span>
                </div>

                <div class="space-y-3 text-xs">
                    <x-ui.input label="Titulaire de la carte / Entreprise" wire:model="cardHolder" />
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Carte bancaire enregistrée (Stripe Checkout)</label>
                        <div class="h-10 px-3 rounded-lg border border-slate-300 bg-slate-50 flex items-center justify-between font-mono text-xs text-slate-700">
                            <span>•••• •••• •••• 4242</span>
                            <span class="text-slate-400">12/28 • CVC •••</span>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-3 border-t border-slate-100">
                    <x-ui.button variant="ghost" wire:click="$set('showCheckoutModal', false)">
                        Annuler
                    </x-ui.button>
                    <x-ui.button variant="primary" wire:click="confirmStripeCheckout" icon="check-circle" loading>
                        Confirmer et activer l'offre {{ $checkoutPlanLabel }}
                    </x-ui.button>
                </div>
            </div>
        </div>
    @endif

    @can('cancelSubscription', $company)
        <!-- Zone de résiliation -->
        <div class="bg-white rounded-xl border border-rose-200 p-6 shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h3 class="text-base font-bold text-rose-700">Résilier mon abonnement</h3>
                <p class="text-xs text-slate-500 mt-0.5">L'accès à DocPilot sera fermé immédiatement pour tous les utilisateurs. Les données sont archivées 90 jours après la résiliation.</p>
            </div>
            <x-ui.button variant="danger" :write="false" wire:click="openCancelModal" class="shrink-0">
                Résilier mon abonnement
            </x-ui.button>
        </div>

        @if ($showCancelModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
                <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 border border-slate-100 shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h3 class="text-base font-bold text-slate-900">Confirmer la résiliation</h3>
                        <button type="button" wire:click="$set('showCancelModal', false)" class="text-slate-400 hover:text-slate-600" aria-label="Fermer">
                            <x-ui.icon name="x-mark" class="w-5 h-5" />
                        </button>
                    </div>

                    <x-ui.alert type="error" title="Action irréversible depuis votre espace">
                        Tous les utilisateurs de {{ $company->name }} perdront l'accès à DocPilot et les liens de dépôt seront désactivés.
                    </x-ui.alert>

                    <form wire:submit="cancelSubscription" class="space-y-4 text-xs">
                        <x-ui.input
                            label="Pour confirmer, saisissez le nom de l'entreprise : {{ $company->name }}"
                            wire:model="confirm_company_name"
                            autocomplete="off"
                            required
                        />

                        <div class="flex justify-end gap-3 pt-3 border-t border-slate-100">
                            <x-ui.button variant="ghost" type="button" wire:click="$set('showCancelModal', false)">
                                Annuler
                            </x-ui.button>
                            <x-ui.button variant="danger" type="submit" :write="false" loading>
                                Résilier définitivement
                            </x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endcan
</div>
