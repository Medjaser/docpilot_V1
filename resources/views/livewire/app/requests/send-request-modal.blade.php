<x-ui.modal name="send-request" title="Envoyer une demande de documents" maxWidth="4xl">
    @if ($sent)
        <div class="py-6 text-center space-y-6">
            <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto">
                <x-ui.icon name="check-circle" class="w-10 h-10" />
            </div>

            <div>
                <h3 class="text-xl font-bold text-slate-900">
                    {{ $savedAsDraft ? 'Demande enregistrée en brouillon !' : 'Demande envoyée avec succès !' }}
                </h3>
                <p class="text-sm text-slate-500 mt-1">
                    @if ($savedAsDraft)
                        Le brouillon a été sauvegardé sur la fiche de <strong>{{ $company_name }}</strong>. Vous pourrez l'envoyer à tout moment.
                    @else
                        Un e-mail contenant le lien sécurisé de dépôt a été transmis à <strong>{{ $contact_name }}</strong>.
                    @endif
                </p>
            </div>

            <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 max-w-lg mx-auto space-y-2" x-data="{ copied: false }">
                <label class="block text-xs font-semibold text-slate-600">Lien sécurisé de dépôt (affiché une seule fois) :</label>
                <div class="flex items-center gap-2">
                    <input type="text" readonly value="{{ $generated_link }}" class="w-full h-9 px-3 text-xs bg-white rounded-lg border border-slate-300 font-mono text-slate-700" />
                    <x-ui.button variant="secondary" size="sm" @click="navigator.clipboard.writeText('{{ $generated_link }}'); copied = true; setTimeout(() => copied = false, 2000)">
                        <span x-text="copied ? 'Copié !' : 'Copier'"></span>
                    </x-ui.button>
                </div>
                <p class="text-[11px] text-amber-700">
                    Par sécurité, le jeton est haché en base de données : copiez ce lien maintenant si vous souhaitez le partager manuellement.
                </p>
            </div>

            <div class="pt-4">
                <x-ui.button variant="primary" @click="$dispatch('close-modal', { name: 'send-request' }); $wire.resetModal()">
                    Fermer la modale
                </x-ui.button>
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left Column: Form Controls (6 cols) -->
            <div class="lg:col-span-6 space-y-4 pr-0 lg:pr-4 border-r-0 lg:border-r border-slate-100">
                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Documents à demander</h4>

                <div class="space-y-2">
                    @forelse ($available_docs as $docName => $docStatus)
                        <label wire:key="available-doc-{{ md5($docName) }}" class="flex items-center justify-between p-2.5 rounded-lg border border-slate-200 hover:bg-slate-50 cursor-pointer">
                            <span class="flex items-center gap-2 text-xs font-medium text-slate-800">
                                <input type="checkbox" wire:model.live="requested_docs" value="{{ $docName }}" class="rounded border-slate-300 text-blue-600" />
                                <span>{{ $docName }}</span>
                            </span>
                            <x-ui.badge :status="$docStatus" size="sm" />
                        </label>
                    @empty
                        <p class="text-xs text-slate-400">Aucun document à demander.</p>
                    @endforelse
                </div>

                <div class="grid grid-cols-2 gap-3 pt-2">
                    <x-ui.input label="Date limite de dépôt" type="date" wire:model.live="deadline_date" required />
                    <x-ui.input label="Expiration du lien" type="date" wire:model.live="link_expiry_date" required />
                </div>

                <div class="pt-1">
                    <x-ui.toggle label="Activer les relances automatiques" wire:model="auto_reminders" help="Rappels automatiques à J-7, J-3 et J-1 avant l'échéance." />
                </div>

                <x-ui.textarea label="Message personnalisé" wire:model.live="message" rows="3" />
            </div>

            <!-- Right Column: Live Email Preview (6 cols) -->
            <div class="lg:col-span-6 bg-slate-50 rounded-xl p-5 border border-slate-200 flex flex-col justify-between">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-3">Aperçu direct de l'e-mail envoyé</span>

                    <div class="bg-white rounded-xl p-5 shadow-xs border border-slate-200/80 space-y-4 text-xs">
                        <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                            <x-ui.logo size="sm" />
                            <span class="text-[10px] text-slate-400">Objet : Documents requis pour {{ auth()->user()?->company?->name ?? 'DocPilot' }}</span>
                        </div>

                        <p class="font-semibold text-slate-900">Bonjour {{ $contact_name }},</p>
                        
                        <p class="text-slate-600 leading-relaxed">{{ $message }}</p>

                        <div class="bg-blue-50/50 p-3 rounded-lg border border-blue-100 space-y-2">
                            <p class="font-semibold text-blue-900">Liste des documents à transmettre :</p>
                            <ul class="list-disc pl-4 space-y-1 text-slate-700 font-medium">
                                @forelse ($requested_docs as $doc)
                                    <li>{{ $doc }}</li>
                                @empty
                                    <li class="italic text-slate-400">Aucun document sélectionné</li>
                                @endforelse
                            </ul>
                        </div>

                        <div class="py-2 text-center">
                            <span class="inline-block px-5 py-2.5 bg-blue-600 text-white font-bold rounded-xl shadow-xs">
                                Déposer mes documents →
                            </span>
                        </div>

                        <p class="text-[11px] text-slate-500">
                            <strong>Date limite de dépôt :</strong> {{ $deadline_date ? date('d/m/Y', strtotime($deadline_date)) : '-' }}<br>
                            <em>Ce lien restera actif jusqu'au {{ $link_expiry_date ? date('d/m/Y', strtotime($link_expiry_date)) : '-' }}.</em>
                        </p>

                        <div class="border-t border-slate-100 pt-3 text-[11px] text-slate-400">
                            Cordialement,<br>
                            <strong>{{ auth()->user()?->full_name }}</strong> — {{ auth()->user()?->company?->name }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <x-slot:footer>
            <x-ui.button variant="ghost" wire:click="saveDraft" loading>
                Enregistrer en brouillon
            </x-ui.button>

            <x-ui.button variant="primary" wire:click="send" icon="arrow-right" iconPosition="right" loading>
                Envoyer la demande
            </x-ui.button>
        </x-slot:footer>
    @endif
</x-ui.modal>
