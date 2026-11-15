<div class="bg-white rounded-2xl shadow-xl border border-slate-200/80 overflow-hidden max-w-md mx-auto p-6 sm:p-10 space-y-6">
    <div class="text-center">
        <div class="flex justify-center mb-3">
            <x-ui.logo size="lg" />
        </div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Nouveau mot de passe</h1>
        <p class="text-sm text-slate-500 mt-2">Choisissez un nouveau mot de passe sécurisé pour votre compte.</p>
    </div>

    @if ($resetSuccess)
        <x-ui.alert type="success" title="Mot de passe réinitialisé">
            Votre mot de passe a été mis à jour avec succès. Vous pouvez maintenant vous connecter.
        </x-ui.alert>
        <x-ui.button variant="primary" href="{{ route('login') }}" class="w-full">
            Se connecter
        </x-ui.button>
    @else
        <form wire:submit="resetPassword" class="space-y-4">
            <x-ui.input 
                label="Adresse e-mail" 
                type="email" 
                wire:model="email" 
                required 
                icon="envelope" 
                readonly
                class="bg-slate-50"
            />

            <div>
                <x-ui.password 
                    label="Nouveau mot de passe" 
                    wire:model="password" 
                    required 
                    placeholder="••••••••" 
                />
                <x-ui.password-strength passwordModel="password" />
            </div>

            <x-ui.password 
                label="Confirmer le nouveau mot de passe" 
                wire:model="password_confirmation" 
                required 
                placeholder="••••••••" 
            />

            <x-ui.button variant="primary" type="submit" class="w-full" loading>
                Réinitialiser le mot de passe
            </x-ui.button>
        </form>
    @endif

    <div class="pt-4 border-t border-slate-100 text-center space-y-3">
        <x-ui.button variant="secondary" href="{{ route('login') }}" icon="arrow-left" class="w-full">
            Retour à la page de connexion
        </x-ui.button>
    </div>
</div>
