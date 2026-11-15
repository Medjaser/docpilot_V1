<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Journal d'activité</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-100 text-purple-800">
                    Administrateur uniquement
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-0.5">Traçabilité complète des actions, connexions, dépôts et accès support</p>
        </div>

        <x-ui.button variant="secondary" wire:click="exportCsv" icon="download">
            Exporter CSV
        </x-ui.button>
    </div>

    <!-- Filters Bar -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs flex flex-wrap gap-3">
        <select wire:model.live="action_type" class="h-9 px-3 text-xs rounded-lg border border-slate-300 bg-white">
            <option value="">Toutes les actions</option>
            <option value="Connexion">Connexions & Sécurité</option>
            <option value="Dépôt">Dépôts de documents</option>
            <option value="Validation">Validations</option>
            <option value="Rejet">Rejets</option>
            <option value="Relance">Demandes & Relances</option>
            <option value="Support">Accès Support Plateforme</option>
        </select>

        <select wire:model.live="user_filter" class="h-9 px-3 text-xs rounded-lg border border-slate-300 bg-white">
            <option value="">Tous les utilisateurs</option>
            @foreach ($usersList as $u)
                <option value="{{ $u['id'] }}">{{ $u['name'] }}</option>
            @endforeach
            <option value="system">Système (Auto)</option>
        </select>
    </div>

    <!-- Table with Expandable Row (Alpine) -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
        <table class="w-full text-left text-xs text-slate-600">
            <thead class="bg-slate-50 text-slate-500 font-semibold border-b border-slate-200 uppercase tracking-wider">
                <tr>
                    <th class="p-3 w-8"></th>
                    <th class="p-3">Horodatage</th>
                    <th class="p-3">Utilisateur</th>
                    <th class="p-3">Action</th>
                    <th class="p-3">Objet concerné</th>
                    <th class="p-3">Adresse IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($auditLogs as $log)
                    <tr x-data="{ expanded: false }" class="hover:bg-slate-50/80 transition-colors">
                        <td class="p-3 text-center">
                            <button type="button" @click="expanded = !expanded" class="text-slate-400 hover:text-slate-600">
                                <x-ui.icon name="chevron-right" class="w-4 h-4 transition-transform duration-200" ::class="{ 'rotate-90': expanded }" />
                            </button>
                        </td>
                        <td class="p-3 font-mono text-slate-500">{{ $log['timestamp'] }}</td>
                        <td class="p-3 font-semibold text-slate-900">{{ $log['user'] }}</td>
                        <td class="p-3">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700">
                                {{ $log['action'] }}
                            </span>
                            <span class="text-[10px] font-mono text-slate-400 ml-1">({{ $log['raw_action'] }})</span>
                        </td>
                        <td class="p-3">
                            <div class="font-medium text-blue-600 cursor-pointer" @click="expanded = !expanded">{{ $log['target'] }}</div>
                            <div x-show="expanded" x-cloak class="mt-2 font-mono text-[11px] text-slate-600 bg-slate-50 p-2 rounded border border-slate-200 break-all">
                                {{ $log['details'] }}
                            </div>
                        </td>
                        <td class="p-3 font-mono text-slate-400">{{ $log['ip'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center text-slate-400">Aucune entrée d'audit correspondant aux filtres.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($paginator->hasPages())
            <div class="p-4 border-t border-slate-200 bg-slate-50/50">
                {{ $paginator->links() }}
            </div>
        @endif
    </div>
</div>
