<div class="bg-white rounded-2xl shadow-xl border border-slate-200/80 overflow-hidden grid grid-cols-1 lg:grid-cols-12 min-h-[620px]">
    <!-- Left Column: Blue Gradient Banner (Desktop only) -->
    <div class="hidden lg:flex lg:col-span-5 bg-gradient-to-br from-blue-700 via-blue-600 to-indigo-900 p-8 text-white flex-col justify-between relative overflow-hidden">
        <!-- Background decorative circles -->
        <div class="absolute -top-16 -left-16 w-64 h-64 rounded-full bg-white/10 blur-2xl pointer-events-none"></div>
        <div class="absolute -bottom-20 -right-20 w-80 h-80 rounded-full bg-blue-400/20 blur-3xl pointer-events-none"></div>

        <div class="relative z-10">
            <x-ui.logo variant="light" size="lg" />
            <h1 class="text-xl font-extrabold text-white mt-8 leading-snug tracking-tight">
                Centralisez les documents de vos sous-traitants, suivez les échéances et automatisez les relances
            </h1>

            <div class="mt-8 space-y-5">
                <div class="flex items-start gap-3.5">
                    <div class="w-9 h-9 rounded-xl bg-white/15 backdrop-blur-xs flex items-center justify-center text-white shrink-0">
                        <x-ui.icon name="clock" class="w-5 h-5" />
                    </div>
                    <div>
                        <h4 class="font-semibold text-sm text-white">Gagnez du temps</h4>
                        <p class="text-xs text-blue-100/80 mt-0.5">Relances automatiques et dépôts simplifiés sans compte.</p>
                    </div>
                </div>

                <div class="flex items-start gap-3.5">
                    <div class="w-9 h-9 rounded-xl bg-white/15 backdrop-blur-xs flex items-center justify-center text-white shrink-0">
                        <x-ui.icon name="shield-check" class="w-5 h-5" />
                    </div>
                    <div>
                        <h4 class="font-semibold text-sm text-white">Réduisez les risques</h4>
                        <p class="text-xs text-blue-100/80 mt-0.5">Vigilance légale et contrôle de conformité à 100%.</p>
                    </div>
                </div>

                <div class="flex items-start gap-3.5">
                    <div class="w-9 h-9 rounded-xl bg-white/15 backdrop-blur-xs flex items-center justify-center text-white shrink-0">
                        <x-ui.icon name="sparkles" class="w-5 h-5" />
                    </div>
                    <div>
                        <h4 class="font-semibold text-sm text-white">Travaillez en toute sérénité</h4>
                        <p class="text-xs text-blue-100/80 mt-0.5">Tableau de bord clair et alertes sur les échéances.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Jobsite Photo Card at bottom -->
        <div class="relative z-10 mt-8 rounded-xl overflow-hidden border border-white/20 shadow-lg group">
            <img src="{{ asset('images/chantier.jpg') }}" alt="Chantier BTP" class="w-full h-32 object-cover transition-transform duration-500 group-hover:scale-105" />
            <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 via-slate-900/20 to-transparent p-3 flex items-end">
                <p class="text-xs font-medium text-white flex items-center gap-1.5">
                    <x-ui.icon name="building-office" class="w-4 h-4 text-blue-300" />
                    <span>Solution pensée pour les PME du BTP</span>
                </p>
            </div>
        </div>
    </div>

    <!-- Right Column: Login Form -->
    <div class="lg:col-span-7 p-6 sm:p-10 flex flex-col justify-center">
        <div class="max-w-md w-full mx-auto space-y-6">
            <!-- Mobile Logo -->
            <div class="lg:hidden flex justify-center mb-4">
                <x-ui.logo variant="dark" size="lg" />
            </div>

            <div class="text-center lg:text-left">
                <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Connexion à votre espace</h2>
                <p class="text-sm text-slate-500 mt-1">Saisissez vos identifiants pour accéder au tableau de bord</p>
            </div>

            @if ($failed || $errorMessage)
                <x-ui.alert type="error" title="Erreur de connexion">
                    {{ $errorMessage ?? 'L\'adresse e-mail ou le mot de passe est incorrect. Veuillez réessayer.' }}
                </x-ui.alert>
            @endif

            <form wire:submit="login" class="space-y-4">
                <x-ui.input 
                    label="E-mail professionnel" 
                    type="email" 
                    wire:model="email" 
                    required 
                    icon="envelope" 
                    placeholder="sophie@batinova.fr" 
                />

                <div class="space-y-1">
                    <x-ui.password 
                        label="Mot de passe" 
                        wire:model="password" 
                        required 
                        placeholder="••••••••" 
                    />
                </div>

                <div class="flex items-center justify-between text-sm pt-1">
                    <x-ui.checkbox label="Se souvenir de moi" wire:model="remember" />
                    <a href="{{ route('password.request') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700 hover:underline">
                        Mot de passe oublié ?
                    </a>
                </div>

                <div class="pt-2">
                    <x-ui.button variant="primary" type="submit" class="w-full" size="md" loading>
                        Se connecter
                    </x-ui.button>
                </div>
            </form>

            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-slate-200"></div>
                </div>
                <div class="relative flex justify-center text-xs uppercase">
                    <span class="bg-white px-3 text-slate-400 font-medium">ou</span>
                </div>
            </div>

            <div>
                <x-ui.button variant="secondary" href="{{ route('register') }}" class="w-full">
                    Créer un compte entreprise
                </x-ui.button>
            </div>
        </div>
    </div>
</div>
