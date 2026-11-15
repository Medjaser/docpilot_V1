<div class="space-y-6">
    <!-- Toast notification -->
    @if ($toastMessage)
        <div class="fixed bottom-5 right-5 z-50 animate-bounce">
            <x-ui.alert type="success" title="Relance transmise" dismissible>
                {{ $toastMessage }}
            </x-ui.alert>
        </div>
    @endif

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Échéances des documents</h1>
            <p class="text-sm text-slate-500 mt-0.5">Suivez les dates de révision et anticipez les relances automatiques</p>
        </div>

        <x-ui.button variant="secondary" icon="download" wire:click="exportCsv">
            Exporter CSV
        </x-ui.button>
    </div>

    <!-- Tabs with Counter Badges -->
    <div class="border-b border-slate-200">
        <nav class="-mb-px flex space-x-6 overflow-x-auto">
            @php
                $tabConfig = [
                    'expired' => ['label' => 'Expirés', 'count' => $tabCounts['expired'] ?? 0],
                    '7_days' => ['label' => 'Sous 7 jours', 'count' => $tabCounts['7_days'] ?? 0],
                    '30_days' => ['label' => 'Sous 30 jours', 'count' => $tabCounts['30_days'] ?? 0],
                    '60_days' => ['label' => 'Sous 60 jours', 'count' => $tabCounts['60_days'] ?? 0],
                    'no_date' => ['label' => 'Sans date', 'count' => $tabCounts['no_date'] ?? 0],
                ];
            @endphp
            @foreach ($tabConfig as $key => $data)
                <button 
                    type="button" 
                    wire:click="$set('tab', '{{ $key }}')" 
                    class="py-3 px-1 border-b-2 font-medium text-sm transition-colors flex items-center gap-2 {{ $tab === $key ? 'border-blue-600 text-blue-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700' }}"
                >
                    <span>{{ $data['label'] }}</span>
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $tab === $key ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600' }}">
                        {{ $data['count'] }}
                    </span>
                </button>
            @endforeach
        </nav>
    </div>

    <!-- Filters Bar -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs flex flex-wrap gap-3">
        <select wire:model.live="category" class="h-9 px-3 text-xs rounded-lg border border-slate-300 bg-white">
            <option value="">Toutes les catégories</option>
            @foreach ($categoriesList as $catName)
                <option value="{{ $catName }}">{{ $catName }}</option>
            @endforeach
        </select>

        <select wire:model.live="manager" class="h-9 px-3 text-xs rounded-lg border border-slate-300 bg-white">
            <option value="">Tous les responsables</option>
            @foreach ($managersList as $m)
                <option value="{{ $m['id'] }}">{{ $m['name'] }}</option>
            @endforeach
        </select>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
        <table class="w-full text-left text-xs text-slate-600">
            <thead class="bg-slate-50 text-slate-500 font-semibold border-b border-slate-200 uppercase tracking-wider">
                <tr>
                    <th class="p-4 w-10">
                        <input type="checkbox" class="rounded border-slate-300 text-blue-600" />
                    </th>
                    <th class="p-4">Sous-traitant</th>
                    <th class="p-4">Document</th>
                    <th class="p-4">Date d'expiration</th>
                    <th class="p-4">Jours restants</th>
                    <th class="p-4">Statut</th>
                    <th class="p-4">Dernière relance</th>
                    <th class="p-4 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($deadlines as $item)
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="p-4">
                            <input type="checkbox" wire:model.live="selected" value="{{ $item['id'] }}" class="rounded border-slate-300 text-blue-600" />
                        </td>
                        <td class="p-4 font-bold text-slate-900 text-sm">
                            <a href="{{ route('subcontractors.show', $item['subcontractor_id']) }}" class="hover:text-blue-600 hover:underline">
                                {{ $item['subcontractor'] }}
                            </a>
                        </td>
                        <td class="p-4 font-medium text-slate-800">{{ $item['document'] }}</td>
                        <td class="p-4 text-slate-600 font-medium">{{ $item['expires_at'] }}</td>
                        <td class="p-4 font-bold">
                            @if ($item['days_remaining'] === null)
                                <span class="text-slate-400">Non renseignée</span>
                            @elseif ($item['days_remaining'] < 0)
                                <span class="text-rose-600">Expiré il y a {{ abs($item['days_remaining']) }} j</span>
                            @elseif ($item['days_remaining'] <= 7)
                                <span class="text-rose-600">J-{{ $item['days_remaining'] }}</span>
                            @elseif ($item['days_remaining'] <= 30)
                                <span class="text-amber-600">J-{{ $item['days_remaining'] }}</span>
                            @else
                                <span class="text-slate-600">J-{{ $item['days_remaining'] }}</span>
                            @endif
                        </td>
                        <td class="p-4">
                            <x-ui.badge :status="$item['status']" size="sm" />
                        </td>
                        <td class="p-4 text-slate-500">{{ $item['last_reminder'] }}</td>
                        <td class="p-4 text-right">
                            <x-ui.button variant="secondary" size="sm" wire:click="triggerReminder({{ $item['id'] }})">
                                Relancer
                            </x-ui.button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="p-8 text-center text-slate-400">
                            Aucun document dans cet onglet d'échéances.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Bulk Selection Bottom Bar -->
    @if (count($selected) > 0)
        <div class="fixed bottom-6 left-1/2 transform -translate-x-1/2 bg-slate-900 text-white px-6 py-3 rounded-2xl shadow-2xl flex items-center gap-4 z-40 border border-slate-700 animate-slide-up">
            <span class="text-xs font-semibold text-slate-200">
                <strong class="text-white">{{ count($selected) }}</strong> document(s) sélectionné(s)
            </span>
            <x-ui.button variant="primary" size="sm" wire:click="triggerBulkReminder" icon="paper-airplane">
                Relancer la sélection
            </x-ui.button>
        </div>
    @endif
</div>
