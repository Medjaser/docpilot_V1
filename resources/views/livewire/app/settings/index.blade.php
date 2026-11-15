<div class="space-y-6">
    @if ($toastMessage)
        <div class="fixed bottom-5 right-5 z-50" wire:key="toast-{{ md5($toastMessage) }}">
            <x-ui.alert type="success" title="Enregistré" dismissible>
                {{ $toastMessage }}
            </x-ui.alert>
        </div>
    @endif

    <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Paramètres de l'entreprise</h1>
        <p class="text-sm text-slate-500 mt-0.5">Configurez les coordonnées, les relances automatiques et les modèles de communication</p>
    </div>

    @unless ($canEdit)
        <x-ui.alert type="{{ $company->isReadOnly() ? 'error' : 'info' }}" title="Lecture seule">
            @if ($company->isReadOnly())
                Le compte de votre entreprise est suspendu : les paramètres ne peuvent pas être modifiés.
            @else
                Seuls les administrateurs de l'entreprise peuvent modifier ces paramètres.
            @endif
        </x-ui.alert>
    @endunless

    <!-- Main Grid: Vertical Menu + Section Content -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Vertical Left Menu (3 cols) -->
        <nav class="lg:col-span-3 space-y-1 bg-white p-2 rounded-xl border border-slate-200 shadow-2xs self-start" aria-label="Sections des paramètres">
            @foreach ($menu as $key => $item)
                @php
                    $isActive = $section === $key;
                    $href = $key === 'subscription' ? route('subscription') : route('settings.index', ['section' => $key]);
                @endphp
                <a
                    href="{{ $href }}"
                    @if ($isActive) aria-current="page" @endif
                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-semibold transition-colors text-left {{ $isActive ? 'bg-blue-50 text-blue-700 font-bold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"
                >
                    <x-ui.icon :name="$item['icon']" class="w-4 h-4 shrink-0 {{ $key === 'subscription' ? 'text-amber-500' : ($isActive ? 'text-blue-600' : 'text-slate-400') }}" />
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <!-- Section Content (9 cols) -->
        <div class="lg:col-span-9 bg-white rounded-xl border border-slate-200 p-6 shadow-2xs">
            <!-- Section : Informations de l'entreprise -->
            @if ($section === 'company')
                <form wire:submit="saveCompany" class="space-y-6">
                    <h3 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">Informations de l'entreprise</h3>

                    <!-- Logo -->
                    <div class="flex items-start gap-4">
                        @php $logoUrl = $company->logoUrl(); @endphp
                        <div class="w-16 h-16 rounded-2xl shrink-0 overflow-hidden flex items-center justify-center {{ $logo || $logoUrl ? 'bg-white border border-slate-200' : 'bg-blue-600 text-white font-bold text-xl' }}">
                            @if ($logo && ! $errors->has('logo') && $logo->isPreviewable())
                                <img src="{{ $logo->temporaryUrl() }}" alt="Aperçu du nouveau logo" class="w-full h-full object-contain" />
                            @elseif ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="Logo {{ $company->name }}" class="w-full h-full object-contain" />
                            @else
                                {{ $company->initials() }}
                            @endif
                        </div>
                        <div class="space-y-1.5">
                            <label for="logo" class="block text-xs font-semibold text-slate-700">Logo de l'entreprise</label>
                            @if ($canEdit)
                                <input id="logo" type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp" class="text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                                <div wire:loading wire:target="logo" class="text-[11px] text-blue-600">Téléversement en cours…</div>
                            @endif
                            <p class="text-[10px] text-slate-400">PNG, JPG ou WEBP, 2 Mo max. Redimensionné à 512 px.</p>
                            @error('logo')
                                <p class="text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                            @if ($canEdit)
                                <div class="flex items-center gap-3">
                                    @if ($logo)
                                        <button type="button" wire:click="cancelLogo" class="text-xs font-semibold text-slate-500 hover:text-slate-700">Annuler</button>
                                        <span class="text-[11px] text-amber-600">Aperçu : enregistrez pour appliquer le nouveau logo.</span>
                                    @elseif ($logoUrl)
                                        <button type="button" wire:click="removeLogo" wire:confirm="Supprimer le logo de l'entreprise ?" class="text-xs font-semibold text-rose-600 hover:text-rose-700">
                                            Supprimer le logo
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-ui.input label="Raison sociale" wire:model="name" required :disabled="! $canEdit" />
                        <x-ui.input label="Nom commercial" wire:model="trade_name" :disabled="! $canEdit" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-ui.input label="SIRET" wire:model="registration_number" required inputmode="numeric" maxlength="17" placeholder="14 chiffres" help="14 chiffres avec une clé de contrôle valide, ex. 12345678900015." :disabled="! $canEdit" />
                        <x-ui.input label="Secteur d'activité" wire:model="sector" :disabled="! $canEdit" />
                    </div>

                    <x-ui.input label="Adresse du siège social" wire:model="address" :disabled="! $canEdit" />

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <x-ui.input label="Code postal" wire:model="postal_code" inputmode="numeric" maxlength="5" :disabled="! $canEdit" />
                        <x-ui.input label="Ville" wire:model="city" :disabled="! $canEdit" />
                        <x-ui.input label="Pays (code ISO)" wire:model="country" required maxlength="2" :disabled="! $canEdit" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-ui.input label="Téléphone" wire:model="phone" icon="phone" :disabled="! $canEdit" />
                        <x-ui.input label="E-mail de contact" type="email" wire:model="email" icon="envelope" required :disabled="! $canEdit" />
                    </div>

                    @if ($canEdit)
                        <div class="pt-4 border-t border-slate-100 space-y-3">
                            @if ($errors->any())
                                <x-ui.alert type="error" title="Les informations n'ont pas été enregistrées">
                                    <ul class="list-disc pl-4 space-y-0.5">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </x-ui.alert>
                            @endif
                            <div class="flex justify-end">
                                <x-ui.button variant="primary" type="submit" loading wire:loading.attr="disabled" wire:target="saveCompany,logo">
                                    Enregistrer les informations
                                </x-ui.button>
                            </div>
                        </div>
                    @endif
                </form>
            @endif

            <!-- Section : Notifications -->
            @if ($section === 'notifications')
                <form wire:submit="saveNotifications" class="space-y-6">
                    <h3 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">Préférences de notifications</h3>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-600">
                            <thead class="bg-slate-50 font-semibold border-b border-slate-200">
                                <tr>
                                    <th class="p-3">Événement</th>
                                    <th class="p-3 text-center">E-mail</th>
                                    <th class="p-3 text-center">Dans l'application</th>
                                    <th class="p-3">Destinataires</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($events as $event => $label)
                                    <tr wire:key="notification-{{ $event }}">
                                        <td class="p-3 font-semibold text-slate-800">{{ $label }}</td>
                                        <td class="p-3 text-center">
                                            <input type="checkbox" wire:model="notifications.{{ $event }}.email" aria-label="{{ $label }} : e-mail" class="rounded border-slate-300 text-blue-600" @disabled(! $canEdit) />
                                        </td>
                                        <td class="p-3 text-center">
                                            <input type="checkbox" wire:model="notifications.{{ $event }}.app" aria-label="{{ $label }} : dans l'application" class="rounded border-slate-300 text-blue-600" @disabled(! $canEdit) />
                                        </td>
                                        <td class="p-3">
                                            <select wire:model="notifications.{{ $event }}.recipients" aria-label="{{ $label }} : destinataires" class="w-full h-8 px-2 rounded-lg border border-slate-300 text-xs text-slate-900 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500" @disabled(! $canEdit)>
                                                @foreach ($recipients as $value => $recipientLabel)
                                                    <option value="{{ $value }}">{{ $recipientLabel }}</option>
                                                @endforeach
                                            </select>
                                            @error("notifications.{$event}.recipients")
                                                <p class="mt-1 text-[11px] font-medium text-rose-600">{{ $message }}</p>
                                            @enderror
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($canEdit)
                        <div class="pt-4 border-t border-slate-100 flex justify-end">
                            <x-ui.button variant="primary" type="submit" loading>
                                Enregistrer les préférences
                            </x-ui.button>
                        </div>
                    @endif
                </form>
            @endif

            <!-- Section : Échéances & Relances -->
            @if ($section === 'deadlines')
                <form wire:submit="saveDeadlines" class="space-y-6">
                    <h3 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">Règles d'échéances et relances automatiques</h3>

                    <x-ui.input label="Seuil 'Bientôt expiré' (en jours)" type="number" min="1" max="180" wire:model="expiring_soon_days" help="Par défaut, les documents passant sous 30 jours entrent en alerte." :disabled="! $canEdit" />

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">Paliers d'alertes automatiques</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($reminder_days as $d)
                                <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-900 border border-amber-200">
                                    J-{{ $d }}
                                </span>
                            @endforeach
                        </div>
                    </div>

                    @if ($canEdit)
                        <div class="pt-4 border-t border-slate-100 flex justify-end">
                            <x-ui.button variant="primary" type="submit" loading>
                                Enregistrer les règles
                            </x-ui.button>
                        </div>
                    @endif
                </form>
            @endif

            <!-- Section : Modèles d'e-mails -->
            @if ($section === 'emails')
                <form wire:submit="saveEmails" class="space-y-6">
                    <h3 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">Modèles d'e-mails de demande</h3>

                    <x-ui.input label="Sujet de l'e-mail par défaut" wire:model="email_subject" required :disabled="! $canEdit" />

                    <div>
                        @if ($canEdit)
                            <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wider">Cliquer pour insérer une variable dynamique :</label>
                            <div class="flex flex-wrap gap-1.5 mb-3">
                                @foreach (['{nom_entreprise}', '{nom_sous_traitant}', '{nom_contact}', '{documents_manquants}', '{date_limite}', '{lien_depot}', '{coordonnees_gestionnaire}'] as $v)
                                    <button type="button" wire:click="insertVariable('{{ $v }}')" class="px-2.5 py-1 rounded bg-blue-50 hover:bg-blue-100 text-blue-700 font-mono text-[11px] font-semibold border border-blue-200">
                                        + {{ $v }}
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        <x-ui.textarea label="Corps de l'e-mail" wire:model="email_body" rows="8" :disabled="! $canEdit" />
                    </div>

                    @if ($canEdit)
                        <div class="pt-4 border-t border-slate-100 flex justify-end">
                            <x-ui.button variant="primary" type="submit" loading>
                                Enregistrer le modèle
                            </x-ui.button>
                        </div>
                    @endif
                </form>
            @endif
        </div>
    </div>
</div>
