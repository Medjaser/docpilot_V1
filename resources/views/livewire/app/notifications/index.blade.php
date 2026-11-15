<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Centre de notifications</h1>
                @if ($unreadCount > 0)
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-600 text-white">
                        {{ $unreadCount }} non lue(s)
                    </span>
                @endif
            </div>
            <p class="text-sm text-slate-500 mt-0.5">Historique de vos alertes applicatives et envois d'e-mails (anti-doublon actif)</p>
        </div>

        <div class="flex items-center gap-3">
            <x-ui.button variant="secondary" wire:click="markAllAsRead" icon="check">
                Tout marquer comme lu
            </x-ui.button>
        </div>
    </div>

    <!-- Filter Buttons -->
    <div class="flex items-center gap-2">
        <button 
            type="button" 
            wire:click="$set('filter', 'all')" 
            class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ $filter === 'all' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}"
        >
            Toutes les notifications
        </button>
        <button 
            type="button" 
            wire:click="$set('filter', 'unread')" 
            class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ $filter === 'unread' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}"
        >
            Non lues uniquement ({{ $unreadCount }})
        </button>
        <button 
            type="button" 
            wire:click="$set('filter', 'emails')" 
            class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ $filter === 'emails' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}"
        >
            Historique e-mails ({{ $emailLogs->count() }})
        </button>
    </div>

    @if ($filter === 'emails')
        <div class="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-slate-500 font-semibold border-b border-slate-200 uppercase tracking-wider">
                    <tr>
                        <th class="p-4">Date d'envoi</th>
                        <th class="p-4">Destinataire</th>
                        <th class="p-4">Type d'événement</th>
                        <th class="p-4">Canal</th>
                        <th class="p-4">Clé anti-doublon</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($emailLogs as $log)
                        <tr class="hover:bg-slate-50/80">
                            <td class="p-4 font-mono text-slate-500">{{ $log['sent_at'] }}</td>
                            <td class="p-4 font-semibold text-slate-900">{{ $log['recipient'] }}</td>
                            <td class="p-4">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700">
                                    {{ $log['type'] }}
                                </span>
                            </td>
                            <td class="p-4 uppercase text-[10px] font-bold text-slate-500">{{ $log['channel'] }}</td>
                            <td class="p-4 font-mono text-[11px] text-slate-400">{{ $log['dedup_key'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-8 text-center text-slate-400">Aucun e-mail enregistré pour le moment.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <!-- Grouped Notifications -->
        <div class="space-y-6">
            @forelse ($groupedNotifications as $group => $items)
                <div class="space-y-3">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">{{ $group }}</h3>

                    <div class="bg-white rounded-xl border border-slate-200 shadow-2xs divide-y divide-slate-100 overflow-hidden">
                        @foreach ($items as $item)
                            <div class="p-4 flex items-start gap-4 hover:bg-slate-50/80 transition-colors {{ !$item['read'] ? 'bg-blue-50/20' : '' }}">
                                <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 mt-0.5">
                                    <x-ui.icon :name="$item['icon']" class="w-5 h-5" />
                                </div>

                                <div class="flex-1 text-xs">
                                    <div class="flex items-center justify-between">
                                        <h4 class="font-bold text-slate-900 text-sm">{{ $item['title'] }}</h4>
                                        <span class="text-[11px] text-slate-400">{{ $item['time'] }}</span>
                                    </div>
                                    <p class="text-slate-600 mt-1 leading-relaxed">{{ $item['text'] }}</p>
                                </div>

                                @if (!$item['read'])
                                    <button type="button" wire:click="markAsRead('{{ $item['id'] }}')" title="Marquer comme lu" class="flex items-center gap-1.5 text-[11px] text-blue-600 font-semibold hover:underline shrink-0 mt-1">
                                        <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>
                                        <span>Lu</span>
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <x-ui.empty-state icon="bell" title="Aucune notification" description="Vous n'avez pas de notification dans ce filtre." />
            @endforelse
        </div>
    @endif
</div>
