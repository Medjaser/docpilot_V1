<div class="space-y-6">
    @if ($toastMessage)
        <div class="fixed bottom-5 right-5 z-50">
            <x-ui.alert type="success" title="Mise à jour" dismissible>
                {{ $toastMessage }}
            </x-ui.alert>
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Utilisateurs et rôles</h1>
            <p class="text-sm text-slate-500 mt-0.5">Gérez les accès de votre équipe et attribuez les droits d'administration</p>
        </div>

        <div class="flex items-center gap-2">
            <x-ui.button variant="secondary" wire:click="exportCsv" icon="download">
                Exporter CSV
            </x-ui.button>
            <x-ui.button variant="primary" wire:click="$set('showInviteModal', true)" icon="plus">
                Inviter un utilisateur
            </x-ui.button>
        </div>
    </div>

    @if ($lastInviteUrl)
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
            <div>
                <span class="font-bold text-blue-900 block">Lien d'invitation généré :</span>
                <a href="{{ $lastInviteUrl }}" target="_blank" class="text-blue-700 font-mono underline break-all">{{ $lastInviteUrl }}</a>
            </div>
            <x-ui.button variant="secondary" size="sm" href="{{ $lastInviteUrl }}" target="_blank">
                Ouvrir le lien d'invitation
            </x-ui.button>
        </div>
    @endif

    <!-- Users Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
        <table class="w-full text-left text-xs text-slate-600">
            <thead class="bg-slate-50 text-slate-500 font-semibold border-b border-slate-200 uppercase tracking-wider">
                <tr>
                    <th class="p-4">Utilisateur</th>
                    <th class="p-4">E-mail</th>
                    <th class="p-4">Rôle</th>
                    <th class="p-4">Statut</th>
                    <th class="p-4">Dernière connexion</th>
                    <th class="p-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($users as $u)
                    <tr wire:key="row-{{ $u['type'] }}-{{ $u['id'] }}" class="hover:bg-slate-50/80 transition-colors">
                        <td class="p-4 font-bold text-slate-900 text-sm flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-blue-600 text-white font-bold text-xs flex items-center justify-center shrink-0">
                                {{ $u['avatar'] }}
                            </div>
                            <span>{{ $u['name'] }}</span>
                        </td>
                        <td class="p-4 text-slate-600">{{ $u['email'] }}</td>
                        <td class="p-4">
                            @if ($u['type'] === 'user')
                                <select
                                    wire:change="updateRole({{ $u['id'] }}, $event.target.value)"
                                    class="h-8 px-2.5 rounded-lg border border-slate-200 text-xs font-semibold bg-white text-slate-800"
                                >
                                    <option value="admin" @selected($u['role_value'] === 'admin')>Administrateur</option>
                                    <option value="manager" @selected($u['role_value'] === 'manager')>Gestionnaire</option>
                                    <option value="viewer" @selected($u['role_value'] === 'viewer')>Lecteur</option>
                                </select>
                            @else
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $u['role'] === 'Administrateur' ? 'bg-purple-100 text-purple-800' : ($u['role'] === 'Gestionnaire' ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-700') }}">
                                    {{ $u['role'] }}
                                </span>
                            @endif
                        </td>
                        <td class="p-4">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium {{ $u['status'] === 'Actif' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($u['status'] === 'Désactivé' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-amber-50 text-amber-700 border border-amber-200') }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $u['status'] === 'Actif' ? 'bg-emerald-500' : ($u['status'] === 'Désactivé' ? 'bg-rose-500' : 'bg-amber-500') }}"></span>
                                <span>{{ $u['status'] }}</span>
                            </span>
                        </td>
                        <td class="p-4 text-slate-500">{{ $u['last_login'] }}</td>
                        <td class="p-4 text-right">
                            <div x-data="{ menu: false }" class="relative inline-block text-left">
                                <button @click="menu = !menu" type="button" class="p-1 text-slate-400 hover:text-slate-600 rounded">
                                    <x-ui.icon name="ellipsis-vertical" class="w-4 h-4" />
                                </button>
                                <div x-show="menu" @click.away="menu = false" style="display: none;" class="absolute right-0 mt-1 w-48 bg-white rounded-xl shadow-lg border border-slate-200 py-1 z-20 text-xs text-left">
                                    @if ($u['type'] === 'invitation')
                                        <button type="button" @click="menu = false" wire:click="resendInvitation({{ $u['id'] }})" class="w-full px-3 py-1.5 text-blue-600 hover:bg-blue-50 text-left">
                                            Renvoyer l'invitation
                                        </button>
                                        <button type="button" @click="menu = false" wire:click="revokeInvitation({{ $u['id'] }})" class="w-full px-3 py-1.5 text-rose-600 hover:bg-rose-50 text-left">
                                            Supprimer l'invitation
                                        </button>
                                    @else
                                        <button type="button" @click="menu = false" wire:click="toggleUserStatus({{ $u['id'] }})" class="w-full px-3 py-1.5 text-slate-700 hover:bg-slate-50 text-left">
                                            {{ $u['is_active'] ? 'Désactiver le compte' : 'Réactiver le compte' }}
                                        </button>
                                        <button type="button" @click="menu = false" wire:click="deleteUser({{ $u['id'] }})" class="w-full px-3 py-1.5 text-rose-600 hover:bg-rose-50 text-left">
                                            Supprimer
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Invite User Modal -->
    @if ($showInviteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 space-y-5 border border-slate-100 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-lg font-bold text-slate-900">Inviter un nouvel utilisateur</h3>
                    <button type="button" wire:click="$set('showInviteModal', false)" class="text-slate-400 hover:text-slate-600">
                        <x-ui.icon name="x-mark" class="w-5 h-5" />
                    </button>
                </div>

                <form wire:submit="sendInvite" class="space-y-4">
                    <x-ui.input label="Adresse e-mail de l'invité" type="email" wire:model="invite_email" required icon="envelope" placeholder="collaborateur@batinova.fr" />
                    @error('invite_email')
                        <p class="text-xs text-rose-600 font-semibold">{{ $message }}</p>
                    @enderror

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2 uppercase tracking-wider">Sélectionner le rôle</label>
                        <div class="space-y-2">
                            <!-- Radio Card 1: Admin -->
                            <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                                <input type="radio" wire:model="invite_role" value="admin" class="mt-0.5 text-blue-600" />
                                <div>
                                    <h4 class="text-xs font-bold text-slate-900">Administrateur</h4>
                                    <p class="text-[11px] text-slate-500">Accès complet à la configuration, facturation, audit et gestion des utilisateurs.</p>
                                </div>
                            </label>

                            <!-- Radio Card 2: Manager -->
                            <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                                <input type="radio" wire:model="invite_role" value="manager" class="mt-0.5 text-blue-600" />
                                <div>
                                    <h4 class="text-xs font-bold text-slate-900">Gestionnaire</h4>
                                    <p class="text-[11px] text-slate-500">Peut ajouter des sous-traitants, envoyer des demandes et valider/rejeter les documents.</p>
                                </div>
                            </label>

                            <!-- Radio Card 3: Reader -->
                            <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                                <input type="radio" wire:model="invite_role" value="viewer" class="mt-0.5 text-blue-600" />
                                <div>
                                    <h4 class="text-xs font-bold text-slate-900">Lecteur</h4>
                                    <p class="text-[11px] text-slate-500">Consultation seule des fiches sous-traitants et téléchargement des documents.</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-3 border-t border-slate-100">
                        <x-ui.button variant="ghost" type="button" wire:click="$set('showInviteModal', false)">
                            Annuler
                        </x-ui.button>
                        <x-ui.button variant="primary" type="submit">
                            Envoyer l'invitation
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
