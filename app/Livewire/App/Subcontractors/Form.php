<?php

namespace App\Livewire\App\Subcontractors;

use App\Domain\Audit\AuditLogger;
use App\Domain\Company\CompanyDefaultsSeeder;
use App\Enums\DocumentStatus;
use App\Enums\RequestStatus;
use App\Enums\SubcontractorStatus;
use App\Models\Document;
use App\Models\DocumentRequest;
use App\Models\DocumentTemplate;
use App\Models\Subcontractor;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Créer un sous-traitant - DocPilot')]
class Form extends Component
{
    #[Locked]
    public ?int $subcontractorId = null;

    // 1. Entreprise
    public string $legal_name = '';
    public string $trade_name = '';
    public string $siret = '';
    public string $provider_type = 'Sous-traitant direct';
    public string $activity = '';

    // 2. Contact
    public string $first_name = '';
    public string $last_name = '';
    public string $email = '';
    public string $phone = '';

    // 3. Adresse
    public string $address = '';
    public string $postal_code = '';
    public string $city = '';
    public string $country = 'FR';

    // 4. Suivi interne
    public ?int $internal_owner_id = null;
    public string $start_date = '';
    public string $end_date = '';
    public array $tags = [];
    public string $new_tag = '';
    public string $internal_comments = '';

    // 5. Modèle de dossier
    public ?int $template_id = null;

    public function mount(?int $id = null): void
    {
        $companyId = auth()->user()?->company_id;

        if ($companyId && DocumentTemplate::count() === 0) {
            CompanyDefaultsSeeder::seedDefaultsForCompany($companyId);
        }

        if ($id) {
            $subcontractor = Subcontractor::findOrFail($id);
            $this->authorize('update', $subcontractor);
            $this->subcontractorId = $id;

            $this->legal_name = $subcontractor->legal_name ?? '';
            $this->trade_name = $subcontractor->trade_name ?? '';
            $this->siret = $subcontractor->registration_number ?? '';
            $this->provider_type = $subcontractor->type ?? 'Sous-traitant direct';
            $this->activity = $subcontractor->activity ?? '';
            $this->first_name = $subcontractor->contact_first_name ?? '';
            $this->last_name = $subcontractor->contact_last_name ?? '';
            $this->email = $subcontractor->contact_email ?? '';
            $this->phone = $subcontractor->contact_phone ?? '';
            $this->address = $subcontractor->address ?? '';
            $this->postal_code = $subcontractor->postal_code ?? '';
            $this->city = $subcontractor->city ?? '';
            $this->country = $subcontractor->country ?? 'FR';
            $this->internal_owner_id = $subcontractor->internal_owner_id;
            $this->start_date = $subcontractor->start_date?->format('Y-m-d') ?? '';
            $this->end_date = $subcontractor->end_date?->format('Y-m-d') ?? '';
            $this->tags = is_array($subcontractor->tags) ? $subcontractor->tags : [];
            $this->internal_comments = $subcontractor->notes ?? '';
        } else {
            $this->authorize('create', Subcontractor::class);
            $this->country = 'FR';
            $this->internal_owner_id = auth()->id();
            $this->tags = [];

            $defaultTemplate = DocumentTemplate::first();
            $this->template_id = $defaultTemplate?->id;
        }
    }

    public function addTag(): void
    {
        $tag = trim($this->new_tag);
        if ($tag !== '' && !in_array($tag, $this->tags, true)) {
            $this->tags[] = $tag;
            $this->new_tag = '';
        }
    }

    public function removeTag(string $tagToRemove): void
    {
        $this->tags = array_values(array_filter($this->tags, fn($t) => $t !== $tagToRemove));
    }

    protected function rules(): array
    {
        return [
            'legal_name' => 'required|string|min:2|max:255',
            'trade_name' => 'nullable|string|max:255',
            'siret' => 'required|string|size:14|regex:/^[0-9]{14}$/',
            'provider_type' => 'nullable|string|max:100',
            'activity' => 'required|string|max:255',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'postal_code' => 'required|string|max:20',
            'city' => 'required|string|max:100',
            'country' => 'required|string|size:2',
            'internal_owner_id' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'internal_comments' => 'nullable|string|max:2000',
            'template_id' => 'nullable|integer',
        ];
    }

    protected array $messages = [
        'siret.required' => 'Le numéro SIRET est obligatoire.',
        'siret.size' => 'SIRET invalide (doit contenir exactement 14 chiffres).',
        'siret.regex' => 'SIRET invalide (chiffres uniquement).',
        'legal_name.required' => 'La raison sociale est obligatoire.',
        'legal_name.min' => 'La raison sociale doit contenir au moins 2 caractères.',
        'activity.required' => "L'activité est obligatoire.",
        'first_name.required' => 'Le prénom est obligatoire.',
        'last_name.required' => 'Le nom est obligatoire.',
        'email.required' => "L'adresse e-mail est obligatoire.",
        'email.email' => "L'adresse e-mail n'est pas valide.",
        'postal_code.required' => 'Le code postal est obligatoire.',
        'city.required' => 'La ville est obligatoire.',
        'country.required' => 'Le pays est obligatoire.',
        'country.size' => 'Le code pays doit contenir 2 lettres.',
    ];

    public function save(bool $sendRequest = false)
    {
        $this->siret = preg_replace('/\s+/', '', $this->siret ?? '');
        $this->country = strtoupper(trim($this->country ?: 'FR'));

        $this->subcontractorId
            ? $this->authorize('update', Subcontractor::findOrFail($this->subcontractorId))
            : $this->authorize('create', Subcontractor::class);

        $this->validate();

        if (! $this->subcontractorId) {
            $company = auth()->user()?->company;
            if ($company) {
                $customLimit = data_get($company->settings, 'limits.max_subcontractors');
                $planLimit = config("docpilot.plans.{$company->subscription_plan}.max_subcontractors", 25);
                $maxSubcontractors = (int) ($customLimit ?: $planLimit);
                $currentCount = Subcontractor::whereNull('archived_at')->count();

                if ($currentCount >= $maxSubcontractors) {
                    $this->addError('legal_name', "Limite atteinte ({$maxSubcontractors} sous-traitants max pour votre offre). Veuillez mettre à niveau votre abonnement.");

                    return;
                }
            }
        }

        $attributes = [
            'legal_name' => $this->legal_name,
            'trade_name' => $this->trade_name ?: null,
            'registration_number' => $this->siret,
            'type' => $this->provider_type ?: null,
            'activity' => $this->activity,
            'contact_first_name' => $this->first_name,
            'contact_last_name' => $this->last_name,
            'contact_email' => $this->email,
            'contact_phone' => $this->phone ?: null,
            'address' => $this->address ?: null,
            'postal_code' => $this->postal_code,
            'city' => $this->city,
            'country' => $this->country,
            'internal_owner_id' => $this->internal_owner_id ?: null,
            'start_date' => $this->start_date ?: null,
            'end_date' => $this->end_date ?: null,
            'tags' => !empty($this->tags) ? array_values($this->tags) : null,
            'notes' => $this->internal_comments ?: null,
        ];

        if ($this->subcontractorId) {
            $subcontractor = Subcontractor::findOrFail($this->subcontractorId);
            $subcontractor->update($attributes);
            app(AuditLogger::class)->log('subcontractor.updated', $subcontractor);
        } else {
            $attributes['status'] = $sendRequest ? SubcontractorStatus::WaitingDocuments : SubcontractorStatus::Draft;
            $attributes['completeness_score'] = 0;
            $subcontractor = Subcontractor::create($attributes);
            app(AuditLogger::class)->log('subcontractor.created', $subcontractor);

            if ($this->template_id) {
                $template = DocumentTemplate::with('documentTypes')->find($this->template_id);
                if ($template) {
                    foreach ($template->documentTypes as $type) {
                        Document::create([
                            'subcontractor_id' => $subcontractor->id,
                            'document_type_id' => $type->id,
                            'status' => $sendRequest ? DocumentStatus::Requested : DocumentStatus::Missing,
                        ]);
                    }
                }
            }
        }

        if ($sendRequest) {
            $request = new DocumentRequest([
                'subcontractor_id' => $subcontractor->id,
                'message' => "Bonjour, veuillez mettre à jour votre dossier administratif en déposant vos documents sur notre portail sécurisé.",
                'due_date' => now()->addDays(14),
                'expires_at' => now()->addDays((int) config('docpilot.portal.link_days', 30))->endOfDay(),
                'status' => RequestStatus::Sent,
                'auto_reminders' => true,
                'sent_at' => now(),
                'created_by' => auth()->id(),
            ]);
            $request->issueToken();
            $request->save();

            $docs = $subcontractor->documents()->get();
            foreach ($docs as $doc) {
                $request->requestedDocuments()->create([
                    'document_type_id' => $doc->document_type_id,
                    'document_id' => $doc->id,
                    'status' => DocumentStatus::Requested,
                    'due_date' => now()->addDays(14),
                ]);
                $doc->update(['status' => DocumentStatus::Requested]);
            }

            $subcontractor->update(['status' => SubcontractorStatus::WaitingDocuments]);
            app(AuditLogger::class)->log('document_request.created', $request);
        }

        session()->flash('status', $this->subcontractorId ? 'Sous-traitant mis à jour avec succès.' : 'Sous-traitant créé avec succès.');

        return redirect()->route('subcontractors.show', $subcontractor->id);
    }

    public function render()
    {
        $companyId = auth()->user()?->company_id;

        $managers = $companyId ? User::query()
            ->where('company_id', $companyId)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']) : collect();

        $templates = DocumentTemplate::with('documentTypes')->get();

        $selectedTemplate = $this->template_id
            ? $templates->firstWhere('id', (int) $this->template_id)
            : $templates->first();

        return view('livewire.app.subcontractors.form', [
            'managers' => $managers,
            'templates' => $templates,
            'selectedTemplate' => $selectedTemplate,
        ]);
    }
}
