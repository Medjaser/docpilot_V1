<div class="space-y-6">
    @if ($toastMessage)
        <div class="fixed bottom-5 right-5 z-50" wire:key="toast-{{ md5($toastMessage) }}">
            <x-ui.alert type="success" title="Action enregistrée" dismissible>
                {{ $toastMessage }}
            </x-ui.alert>
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Gestion des entreprises clientes</h1>
            <p class="text-sm text-slate-500 mt-0.5">Supervision administrative des comptes SaaS, gestion des quotas et accès support tracé</p>
        </div>
    </div>

    <!-- Privacy Disclaimer Banner -->
    <x-ui.alert type="info" title="Respect de la confidentialité des données (RGPD / Secret d'affaires)">
        Conformément aux règles de sécurité DocPilot, les écrans d'administration ne permettent à aucun moment d'afficher ou d'accéder au contenu confidentiel des documents déposés par les clients.
    </x-ui.alert>

    @error('status')
        <x-ui.alert type="error" title="Action refusée">{{ $message }}</x-ui.alert>
    @enderror

    <!-- Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
        <table class="w-full text-left text-xs text-slate-600">
            <thead class="bg-slate-50 text-slate-500 font-semibold border-b border-slate-200 uppercase tracking-wider">
                <tr>
                    <th class="p-4">Entreprise</th>
                    <th class="p-4">Offre</th>
                    <th class="p-4">Statut</th>
                    <th class="p-4">Utilisateurs</th>
                    <th class="p-4">Sous-traitants</th>
                    <th class="p-4">Date d'inscription</th>
                    <th class="p-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($companies as $c)
                    @php
                        $planCfg = $plans[$c->subscription_plan] ?? $plans['essentiel'];
                        $maxUsers = data_get($c->settings, 'limits.max_users', $planCfg['max_users'] ?? 5);
                        $maxSubs = data_get($c->settings, 'limits.max_subcontractors', $planCfg['max_subcontractors'] ?? 25);
                    @endphp
                    <tr wire:key="company-{{ $c->id }}" class="hover:bg-slate-50/80 transition-colors">
                        <td class="p-4 font-bold text-slate-900 text-sm">
                            <div class="flex items-center gap-3">
                                @if ($logoUrl = $c->logoUrl())
                                    <img src="{{ $logoUrl }}" alt="" class="w-8 h-8 rounded-lg object-contain border border-slate-200 bg-white shrink-0" />
                                @else
                                    <div class="w-8 h-8 rounded-lg bg-slate-800 text-white font-bold text-xs flex items-center justify-center shrink-0">
                                        {{ $c->initials() }}
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <span class="block truncate">{{ $c->name }}</span>
                                    @if ($c->status_reason && ! $c->status->isWritable())
                                        <span class="block text-[11px] font-normal text-slate-400 truncate" title="{{ $c->status_reason }}">{{ $c->status_reason }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="p-4 font-semibold text-blue-600">{{ $planCfg['label'] ?? ucfirst($c->subscription_plan) }}</td>
                        <td class="p-4">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $c->status->badgeClasses() }}">
                                {{ $c->status->label() }}
                            </span>
                            @if ($c->trialDaysLeft() !== null)
                                <span class="block text-[11px] text-slate-400 mt-1">{{ $c->trialDaysLeft() }} j restants</span>
                            @endif
                        </td>
                        <td class="p-4 text-slate-700 font-medium">{{ $c->users_count }} / {{ $maxUsers }}</td>
                        <td class="p-4 text-slate-700 font-medium">{{ $c->subcontractors_count }} / {{ $maxSubs }}</td>
                        <td class="p-4 text-slate-500">{{ $c->created_at?->format('d/m/Y') }}</td>
                        <td class="p-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <x-ui.button variant="ghost" size="sm" wire:click="openLimitsModal({{ $c->id }})">
                                    Gérer limites
                                </x-ui.button>

                                <x-ui.button variant="secondary" size="sm" wire:click="openSupportModal(@js($c->name), {{ $c->id }})">
                                    Accès support
                                </x-ui.button>

                                @php $actions = \App\Livewire\Admin\Companies\Index::statusActions($c->status); @endphp
                                @if ($actions)
                                    <div x-data="{ menu: false }" class="relative inline-block text-left">
                                        <button @click="menu = !menu" type="button" class="p-1 text-slate-400 hover:text-slate-600 rounded" aria-label="Changer le statut de {{ $c->name }}">
                                            <x-ui.icon name="ellipsis-vertical" class="w-4 h-4" />
                                        </button>
                                        <div x-show="menu" @click.away="menu = false" style="display: none;" class="absolute right-0 mt-1 w-44 bg-white rounded-xl shadow-lg border border-slate-200 py-1 z-20 text-xs text-left">
                                            @foreach ($actions as $target => $label)
                                                <button
                                                    type="button"
                                                    @click="menu = false"
                                                    wire:click="openStatusModal({{ $c->id }}, '{{ $target }}')"
                                                    class="w-full px-3 py-1.5 text-left {{ in_array($target, ['suspended', 'terminated'], true) ? 'text-rose-600 hover:bg-rose-50' : 'text-emerald-600 hover:bg-emerald-50' }}"
                                                >
                                                    {{ $label }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center text-slate-400">Aucune entreprise cliente.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Modal Gestion des limites et de l'offre -->
    @if ($showLimitsModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 border border-slate-100 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">Quotas & Offre : {{ $limitsCompanyName }}</h3>
                    <button type="button" wire:click="$set('showLimitsModal', false)" class="text-slate-400 hover:text-slate-600">
                        <x-ui.icon name="x-mark" class="w-5 h-5" />
                    </button>
                </div>

                <form wire:submit="saveLimits" class="space-y-4 text-xs">
                    <x-ui.select label="Formule d'abonnement" wire:model="limits_plan">
                        <option value="mini">Mini (5,90 € / mois)</option>
                        <option value="essentiel">Essentiel (14,90 € / mois)</option>
                        <option value="business">Business (29,90 € / mois)</option>
                    </x-ui.select>

                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.input label="Max utilisateurs" type="number" wire:model="limits_max_users" required />
                        <x-ui.input label="Max sous-traitants" type="number" wire:model="limits_max_subcontractors" required />
                    </div>

                    <div class="flex justify-end gap-3 pt-3 border-t border-slate-100">
                        <x-ui.button variant="ghost" type="button" wire:click="$set('showLimitsModal', false)">
                            Annuler
                        </x-ui.button>
                        <x-ui.button variant="primary" type="submit" loading>
                            Enregistrer les limites
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Changement de statut (motif obligatoire, audité) -->
    @if ($showStatusModal && $statusCompany && $statusTargetEnum)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 border border-slate-100 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">
                        {{ \App\Livewire\Admin\Companies\Index::statusActions($statusCompany->status)[$statusTargetEnum->value] ?? 'Changer le statut' }} : {{ $statusCompany->name }}
                    </h3>
                    <button type="button" wire:click="closeStatusModal" class="text-slate-400 hover:text-slate-600" aria-label="Fermer">
                        <x-ui.icon name="x-mark" class="w-5 h-5" />
                    </button>
                </div>

                <p class="text-xs text-slate-600">
                    Statut actuel : <strong>{{ $statusCompany->status->label() }}</strong> → nouveau statut : <strong>{{ $statusTargetEnum->label() }}</strong>.
                    Ce changement est enregistré dans le journal d'audit de l'entreprise.
                </p>

                <form wire:submit="confirmStatusChange" class="space-y-4 text-xs">
                    <x-ui.textarea label="Motif" wire:model="status_reason" required rows="3" placeholder="ex. Impayé depuis 30 jours, demande du client..." />

                    <div class="flex justify-end gap-3 pt-3 border-t border-slate-100">
                        <x-ui.button variant="ghost" type="button" wire:click="closeStatusModal">
                            Annuler
                        </x-ui.button>
                        <x-ui.button :variant="in_array($statusTargetEnum->value, ['suspended', 'terminated'], true) ? 'danger' : 'success'" type="submit" loading>
                            Confirmer
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Support Access Audited Modal -->
    @if ($showSupportModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 border border-slate-100 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">Demande d'accès support client</h3>
                    <button type="button" wire:click="$set('showSupportModal', false)" class="text-slate-400 hover:text-slate-600">
                        <x-ui.icon name="x-mark" class="w-5 h-5" />
                    </button>
                </div>

                <div class="bg-amber-50 border border-amber-200 p-3 rounded-xl text-xs text-amber-900 space-y-1">
                    <div class="flex items-center gap-2 font-bold text-amber-800">
                        <x-ui.icon name="exclamation-triangle" class="w-4 h-4 text-amber-600" />
                        <span>Avertissement de traçabilité</span>
                    </div>
                    <p class="text-amber-800 leading-relaxed">
                        Cet accès support temporaire pour <strong>{{ $selected_company_name }}</strong> sera intégralement tracé dans l'audit log et sera visible par l'administrateur de l'entreprise client.
                    </p>
                </div>

                <form wire:submit="grantSupportAccess" class="space-y-4 text-xs">
                    <x-ui.textarea label="Motif précis de la demande d'accès" wire:model="support_reason" required placeholder="ex. Assistance technique demandée par e-mail ticket #4812..." rows="3" />

                    <x-ui.select label="Durée de l'accès support" wire:model="support_duration_hours">
                        <option value="1">1 heure</option>
                        <option value="2">2 heures</option>
                        <option value="4">4 heures</option>
                        <option value="8">8 heures</option>
                    </x-ui.select>

                    <div class="flex justify-end gap-3 pt-3 border-t border-slate-100">
                        <x-ui.button variant="ghost" type="button" wire:click="$set('showLimitsModal', false); $set('showSupportModal', false)">
                            Annuler
                        </x-ui.button>
                        <x-ui.button variant="primary" type="submit">
                            Valider l'accès support
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
