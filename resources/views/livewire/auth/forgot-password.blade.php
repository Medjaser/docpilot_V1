<div class="bg-white rounded-2xl shadow-xl border border-slate-200/80 overflow-hidden max-w-md mx-auto p-6 sm:p-10 space-y-6">
    <div class="text-center">
        <div class="flex justify-center mb-3">
            <x-ui.logo size="lg" />
        </div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Mot de passe oublié ?</h1>
        <p class="text-sm text-slate-500 mt-2 leading-relaxed">
            Saisissez votre adresse e-mail. Nous vous enverrons un lien sécurisé pour réinitialiser votre mot de passe.
        </p>
    </div>

    @if ($sent)
        <x-ui.alert type="success" title="E-mail envoyé">
            Si un compte existe pour l'adresse <strong>{{ $email }}</strong>, un e-mail contenant les instructions de réinitialisation vous a été envoyé. Veuillez vérifier votre boîte de réception et vos spams.
        </x-ui.alert>
    @else
        <form wire:submit="sendResetLink" class="space-y-4">
            <x-ui.input 
                label="Adresse e-mail" 
                type="email" 
                wire:model="email" 
                required 
                icon="envelope" 
                placeholder="sophie@batinova.fr" 
            />

            <x-ui.button variant="primary" type="submit" class="w-full" loading>
                Envoyer le lien de réinitialisation
            </x-ui.button>

            @if (app()->environment('local'))
                <x-ui.button variant="secondary" wire:click="resetDirectly" type="button" class="w-full" loading>
                    Réinitialiser directement (Mode Dev Local)
                </x-ui.button>
            @endif
        </form>
    @endif

    @if ($devResetUrl && app()->environment('local'))
        <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl space-y-2 text-left">
            <div class="flex items-center gap-2 text-amber-800 text-xs font-semibold">
                <x-ui.icon name="sparkles" class="w-4 h-4 text-amber-600" />
                <span>Mode Dev Local</span>
            </div>
            <p class="text-xs text-amber-700 leading-relaxed">
                Le lien de réinitialisation a été généré sans passer par l'envoi d'e-mail :
            </p>
            <x-ui.button variant="secondary" href="{!! $devResetUrl !!}" class="w-full text-xs">
                Ouvrir la page de réinitialisation
            </x-ui.button>
        </div>
    @endif

    <div class="pt-4 border-t border-slate-100 text-center">
        <a href="{{ route('login') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700 flex items-center justify-center gap-1">
            <x-ui.icon name="arrow-left" class="w-4 h-4" />
            <span>Retour à la page de connexion</span>
        </a>
    </div>
</div>
