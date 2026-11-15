<div class="bg-white rounded-2xl shadow-xl border border-slate-200/80 overflow-hidden max-w-2xl mx-auto p-6 sm:p-10">
    <!-- Header -->
    <div class="text-center mb-8">
        <div class="flex justify-center mb-3">
            <x-ui.logo size="lg" />
        </div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Créer votre espace entreprise</h1>
        <p class="text-xs font-semibold text-blue-600 bg-blue-50 inline-block px-3 py-1 rounded-full mt-2">
            14 jours d'essai gratuit, sans carte bancaire
        </p>
    </div>

    <!-- Stepper Indicator -->
    <div class="flex items-center justify-between max-w-md mx-auto mb-8 relative">
        <div class="absolute top-4 left-6 right-6 h-0.5 bg-slate-200 -z-0"></div>
        <div class="absolute top-4 left-6 h-0.5 bg-blue-600 transition-all duration-300 -z-0" style="width: {{ $step === 1 ? '0%' : '100%' }}"></div>

        <!-- Step 1 Circle -->
        <div class="relative z-10 flex flex-col items-center gap-1.5 bg-white px-2">
            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-colors {{ $step >= 1 ? 'bg-blue-600 text-white ring-4 ring-blue-100' : 'bg-slate-100 text-slate-400 border border-slate-300' }}">
                @if ($step > 1)
                    <x-ui.icon name="check" class="w-4 h-4 text-white" />
                @else
                    1
                @endif
            </div>
            <span class="text-xs font-medium {{ $step === 1 ? 'text-blue-600 font-semibold' : 'text-slate-500' }}">Entreprise</span>
        </div>

        <!-- Step 2 Circle -->
        <div class="relative z-10 flex flex-col items-center gap-1.5 bg-white px-2">
            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-colors {{ $step === 2 ? 'bg-blue-600 text-white ring-4 ring-blue-100' : 'bg-slate-100 text-slate-400 border border-slate-300' }}">
                2
            </div>
            <span class="text-xs font-medium {{ $step === 2 ? 'text-blue-600 font-semibold' : 'text-slate-500' }}">Administrateur</span>
        </div>
    </div>

    <!-- Step 1: Votre Entreprise -->
    @if ($step === 1)
        <form wire:submit="nextStep" class="space-y-4">
            <h3 class="text-base font-semibold text-slate-900 border-b border-slate-100 pb-2 mb-4 flex items-center gap-2">
                <x-ui.icon name="building-office" class="w-5 h-5 text-blue-600" />
                <span>Étape 1 : Informations de votre entreprise</span>
            </h3>

            <x-ui.input 
                label="Raison sociale" 
                wire:model="company_name" 
                required 
                icon="building-office" 
                placeholder="BatiNova Rénovation" 
            />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.input 
                    label="Numéro SIRET (14 chiffres)" 
                    wire:model="siret" 
                    required 
                    icon="document" 
                    placeholder="12345678900012" 
                    help="Exemple: 12345678900012"
                />

                <x-ui.select 
                    label="Secteur d'activité" 
                    wire:model="industry" 
                    required 
                    icon="building-office"
                    :options="[
                        'BTP - Construction / Rénovation' => 'BTP - Construction / Rénovation',
                        'Électricité générale' => 'Électricité générale',
                        'Plomberie / Chauffage' => 'Plomberie / Chauffage',
                        'Maçonnerie / Gros œuvre' => 'Maçonnerie / Gros œuvre',
                        'Peinture / Second œuvre' => 'Peinture / Second œuvre',
                        'Autre secteur BTP' => 'Autre secteur BTP',
                    ]"
                />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.input 
                    label="Ville du siège" 
                    wire:model="city" 
                    required 
                    icon="map-pin" 
                    placeholder="Lyon" 
                />

                <x-ui.input 
                    label="Téléphone professionnel" 
                    wire:model="phone" 
                    required 
                    icon="phone" 
                    placeholder="04 78 00 11 22" 
                />
            </div>

            <div class="pt-4 flex justify-between items-center border-t border-slate-100">
                <a href="{{ route('login') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-700">
                    Déjà un compte ? Se connecter
                </a>
                <x-ui.button variant="primary" type="submit" icon="arrow-right" iconPosition="right">
                    Suivant
                </x-ui.button>
            </div>
        </form>
    @endif

    <!-- Step 2: Votre compte administrateur -->
    @if ($step === 2)
        <form wire:submit="register" class="space-y-4">
            <h3 class="text-base font-semibold text-slate-900 border-b border-slate-100 pb-2 mb-4 flex items-center gap-2">
                <x-ui.icon name="user" class="w-5 h-5 text-blue-600" />
                <span>Étape 2 : Votre compte administrateur</span>
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.input 
                    label="Prénom" 
                    wire:model="first_name" 
                    required 
                    icon="user" 
                    placeholder="Sophie" 
                />

                <x-ui.input 
                    label="Nom" 
                    wire:model="last_name" 
                    required 
                    icon="user" 
                    placeholder="Martin" 
                />
            </div>

            <x-ui.input 
                label="E-mail professionnel" 
                type="email" 
                wire:model="email" 
                required 
                icon="envelope" 
                placeholder="sophie@batinova.fr" 
            />

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

            <div class="pt-2">
                <x-ui.checkbox wire:model="terms" required>
                    J'accepte les <a href="#" class="text-blue-600 underline font-medium">Conditions Générales d'Utilisation</a> et la <a href="#" class="text-blue-600 underline font-medium">Politique de Confidentialité</a>.
                </x-ui.checkbox>
                @error('terms')
                    <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="pt-4 flex justify-between items-center border-t border-slate-100">
                <x-ui.button variant="ghost" type="button" wire:click="previousStep" icon="arrow-left">
                    Précédent
                </x-ui.button>

                <x-ui.button variant="primary" type="submit" loading>
                    Créer mon espace
                </x-ui.button>
            </div>
        </form>
    @endif
</div>
