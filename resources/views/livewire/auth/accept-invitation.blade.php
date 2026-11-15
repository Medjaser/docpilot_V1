<div class="bg-white rounded-2xl shadow-xl border border-slate-200/80 overflow-hidden max-w-lg mx-auto p-6 sm:p-10 space-y-6">
    <div class="text-center">
        <div class="flex justify-center mb-3">
            <x-ui.logo size="lg" />
        </div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Rejoindre {{ $company_name }}</h1>
    </div>

    @if ($expired)
        <div class="space-y-6 text-center">
            <x-ui.alert type="error" title="Invitation expirée">
                Cette invitation n'est plus valide. Veuillez demander une nouvelle invitation à <strong>{{ $inviter_name }}</strong>.
            </x-ui.alert>

            <x-ui.button variant="secondary" href="{{ route('login') }}" class="w-full">
                Retour à la page de connexion
            </x-ui.button>
        </div>
    @else
        <!-- Company card -->
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-blue-600 text-white font-bold text-lg flex items-center justify-center shrink-0">
                {{ mb_strtoupper(mb_substr($company_name, 0, 2)) }}
            </div>
            <div class="text-left">
                <h3 class="font-semibold text-slate-900 text-sm">{{ $company_name }}</h3>
                <p class="text-xs text-slate-500 mt-0.5">
                    <strong>{{ $inviter_name }}</strong> vous invite à rejoindre l'espace en tant que <span class="text-blue-600 font-semibold">{{ $role_label }}</span>.
                </p>
            </div>
        </div>

        <form wire:submit="accept" class="space-y-4 text-left">
            <div>
                <x-ui.input 
                    label="Adresse e-mail" 
                    type="email" 
                    wire:model="email" 
                    readonly 
                    icon="lock"
                    class="bg-slate-100 text-slate-600 cursor-not-allowed"
                    help="Cet e-mail ne peut pas être modifié."
                />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.input 
                    label="Prénom" 
                    wire:model="first_name" 
                    required 
                    placeholder="Marc" 
                />

                <x-ui.input 
                    label="Nom" 
                    wire:model="last_name" 
                    required 
                    placeholder="Dubois" 
                />
            </div>

            <div>
                <x-ui.password 
                    label="Mot de passe" 
                    wire:model="password" 
                    required 
                    placeholder="••••••••" 
                />
                <x-ui.password-strength passwordModel="password" />
            </div>

            <x-ui.password 
                label="Confirmer le mot de passe" 
                wire:model="password_confirmation" 
                required 
                placeholder="••••••••" 
            />

            <x-ui.button variant="primary" type="submit" class="w-full" loading>
                Accepter l'invitation
            </x-ui.button>
        </form>
    @endif
</div>
