<div class="bg-white rounded-2xl shadow-xl border border-slate-200/80 overflow-hidden max-w-md mx-auto p-6 sm:p-10 space-y-6 text-center">
    <!-- Icon Envelope with Checkmark in Light Blue Circle -->
    <div class="relative w-20 h-20 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center mx-auto">
        <x-ui.icon name="envelope" class="w-10 h-10" />
        <div class="absolute -bottom-1 -right-1 w-7 h-7 bg-emerald-500 text-white rounded-full flex items-center justify-center border-2 border-white">
            <x-ui.icon name="check" class="w-4 h-4" />
        </div>
    </div>

    <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Vérifiez votre adresse e-mail</h1>
        <p class="text-sm text-slate-500 mt-2 leading-relaxed">
            Un lien de vérification vous a été envoyé à l'adresse <strong class="text-slate-900">{{ $email }}</strong>.
            Veuillez cliquer sur le lien dans l'e-mail pour activer votre compte.
        </p>
    </div>

    @if ($feedbackMessage)
        <x-ui.alert :type="$feedbackType" :title="$feedbackTitle">
            {{ $feedbackMessage }}
        </x-ui.alert>
    @elseif (session('warning'))
        <x-ui.alert type="warning" title="Attention">
            {{ session('warning') }}
        </x-ui.alert>
    @elseif ($resent)
        <x-ui.alert type="success" title="Nouveau lien envoyé">
            Un nouveau lien de vérification a été transmis à votre adresse e-mail.
        </x-ui.alert>
    @endif

    <div class="pt-2 space-y-3">
        @if (app()->environment('local'))
            <x-ui.button variant="primary" wire:click="verifyDirectly" class="w-full" loading>
                Valider mon e-mail directement (Mode Dev Local)
            </x-ui.button>
        @endif

        <x-ui.button variant="secondary" wire:click="resend" icon="arrow-path" class="w-full" loading>
            Renvoyer l'e-mail de vérification
        </x-ui.button>

        <button type="button" wire:click="logout" class="text-xs font-medium text-slate-500 hover:text-slate-700 underline block mx-auto pt-2">
            Se déconnecter
        </button>
    </div>
</div>
