<div class="space-y-6 max-w-4xl">
    @if ($toastMessage)
        <div class="fixed bottom-5 right-5 z-50">
            <x-ui.alert type="success" title="Enregistré" dismissible>
                {{ $toastMessage }}
            </x-ui.alert>
        </div>
    @endif

    <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Mon profil & Sécurité</h1>
        <p class="text-sm text-slate-500 mt-0.5">Gérez vos coordonnées personnelles, votre mot de passe et vos sessions actives</p>
    </div>

    <!-- Informations personnelles -->
    <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-2xs space-y-5">
        <h3 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">Informations personnelles</h3>
        <form wire:submit="updateProfile" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.input label="Prénom" wire:model="first_name" required />
                <x-ui.input label="Nom" wire:model="last_name" required />
            </div>
            <x-ui.input label="Adresse e-mail" type="email" wire:model="email" disabled help="L'adresse e-mail de connexion est gérée par votre administrateur." />

            <div class="flex justify-end pt-2">
                <x-ui.button variant="primary" type="submit" loading>
                    Enregistrer mon profil
                </x-ui.button>
            </div>
        </form>
    </div>

    <!-- Modification du mot de passe -->
    <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-2xs space-y-5">
        <h3 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">Modifier mon mot de passe</h3>
        <form wire:submit="updatePassword" class="space-y-4">
            <x-ui.password label="Mot de passe actuel" wire:model="current_password" required placeholder="••••••••" />
            @error('current_password')
                <p class="text-xs text-rose-600 font-semibold">{{ $message }}</p>
            @enderror

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-ui.password label="Nouveau mot de passe (min. 8 caractères)" wire:model="password" required placeholder="••••••••" />
                    <x-ui.password-strength passwordModel="password" />
                </div>
                <x-ui.password label="Confirmer le nouveau mot de passe" wire:model="password_confirmation" required placeholder="••••••••" />
            </div>
            @error('password')
                <p class="text-xs text-rose-600 font-semibold">{{ $message }}</p>
            @enderror

            <div class="flex justify-end pt-2">
                <x-ui.button variant="primary" type="submit" loading>
                    Mettre à jour le mot de passe
                </x-ui.button>
            </div>
        </form>
    </div>

    <!-- Gestion des sessions actives -->
    <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-2xs space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-base font-bold text-slate-900">Sessions actives</h3>
                <p class="text-xs text-slate-500">Appareils et navigateurs actuellement connectés à votre compte DocPilot</p>
            </div>
            <x-ui.button variant="secondary" size="sm" wire:click="logoutOtherSessions">
                Déconnecter les autres appareils
            </x-ui.button>
        </div>

        <div class="divide-y divide-slate-100 text-xs">
            @foreach ($sessions as $s)
                <div class="py-3 flex items-center justify-between gap-4">
                    <div class="space-y-0.5 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-slate-900 truncate">{{ \Illuminate\Support\Str::limit($s['user_agent'], 70) }}</span>
                            @if ($s['is_current'])
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Cet appareil</span>
                            @endif
                        </div>
                        <p class="text-slate-400 font-mono text-[11px]">IP : {{ $s['ip_address'] }} • {{ $s['last_active'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Désactivation du compte -->
    <div class="bg-white rounded-xl border border-rose-200 p-6 shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h3 class="text-base font-bold text-rose-700">Désactiver mon compte utilisateur</h3>
            <p class="text-xs text-slate-500 mt-0.5">Votre accès sera immédiatement suspendu. Un administrateur de votre entreprise pourra réactiver votre compte.</p>
        </div>
        <x-ui.button variant="danger" size="sm" wire:click="$set('showDeactivateModal', true)">
            Désactiver mon compte
        </x-ui.button>
    </div>

    @if ($showDeactivateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 border border-slate-100 shadow-2xl">
                <h3 class="text-base font-bold text-slate-900">Confirmer la désactivation du compte</h3>
                <p class="text-xs text-slate-600">Saisissez votre mot de passe actuel pour confirmer la désactivation de votre compte.</p>

                <form wire:submit="deactivateOwnAccount" class="space-y-4">
                    <x-ui.password label="Votre mot de passe" wire:model="deactivate_password" required />
                    @error('deactivate_password')
                        <p class="text-xs text-rose-600 font-semibold">{{ $message }}</p>
                    @enderror

                    <div class="flex justify-end gap-3 pt-2">
                        <x-ui.button variant="ghost" type="button" wire:click="$set('showDeactivateModal', false)">Annuler</x-ui.button>
                        <x-ui.button variant="danger" type="submit">Confirmer la désactivation</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

