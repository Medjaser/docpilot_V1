<?php

namespace App\Livewire\App\Settings;

use App\Domain\Audit\AuditLogger;
use App\Domain\Company\CompanyLogoStorage;
use App\Domain\Company\CompanySettings;
use App\Models\Company;
use App\Rules\Siret;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

#[Layout('layouts.app')]
#[Title('Paramètres de l\'entreprise - DocPilot')]
class Index extends Component
{
    use WithFileUploads;

    /** Sections gérées par ce composant ; « subscription » a sa propre route (Settings\Subscription). */
    public const SECTIONS = ['company', 'notifications', 'deadlines', 'emails'];

    public const MENU = [
        'company' => ['label' => 'Informations de l\'entreprise', 'icon' => 'building-office'],
        'notifications' => ['label' => 'Notifications', 'icon' => 'bell'],
        'deadlines' => ['label' => 'Échéances & Relances', 'icon' => 'clock'],
        'emails' => ['label' => 'Modèles d\'e-mails', 'icon' => 'envelope'],
        'subscription' => ['label' => 'Abonnement & Offre', 'icon' => 'crown'],
    ];

    /** Masquées aux gestionnaires et lecteurs (menu) et refusées en accès direct (403). */
    public const ADMIN_ONLY_SECTIONS = ['emails', 'subscription'];

    private const COMPANY_FIELDS = [
        'name', 'trade_name', 'registration_number', 'sector', 'address',
        'postal_code', 'city', 'country', 'phone', 'email',
    ];

    /** Pas de SVG : un SVG peut embarquer du JavaScript. */
    private const LOGO_RULES = [
        'nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'mimetypes:image/png,image/jpeg,image/webp',
        'max:'.CompanyLogoStorage::MAX_KB,
    ];

    private const LOGO_MESSAGES = [
        'logo.image' => 'Le logo doit être une image PNG, JPG ou WEBP.',
        'logo.mimes' => 'Le logo doit être une image PNG, JPG ou WEBP.',
        'logo.mimetypes' => 'Le logo doit être une image PNG, JPG ou WEBP.',
        'logo.max' => 'Le logo ne doit pas dépasser 2 Mo.',
    ];

    #[Locked]
    public string $section = 'company';

    // Informations de l'entreprise
    public string $name = '';
    public string $trade_name = '';
    public string $registration_number = '';
    public string $sector = '';
    public string $address = '';
    public string $postal_code = '';
    public string $city = '';
    public string $country = 'FR';
    public string $phone = '';
    public string $email = '';
    public $logo = null;

    // Notifications
    public array $notifications = [];

    // Échéances & relances
    public int $expiring_soon_days = 30;
    public array $reminder_days = [];

    // Modèles d'e-mails
    public string $email_subject = '';
    public string $email_body = '';

    public ?string $toastMessage = null;

    public function mount(string $section = 'company'): void
    {
        abort_unless(in_array($section, self::SECTIONS, true), 404);

        $company = $this->company();
        $this->authorize('view', $company);

        if (in_array($section, self::ADMIN_ONLY_SECTIONS, true)) {
            $this->authorize('administer', $company);
        }

        $this->section = $section;
        $this->toastMessage = session('status');

        foreach (self::COMPANY_FIELDS as $field) {
            $this->{$field} = (string) $company->{$field};
        }

        $this->fillSettings($company);
    }

    public function saveCompany(CompanyLogoStorage $logos, AuditLogger $audit)
    {
        $company = $this->company();
        $this->authorize('update', $company);

        $this->registration_number = preg_replace('/\s+/', '', $this->registration_number);
        $this->postal_code = trim($this->postal_code);
        $this->country = strtoupper(trim($this->country));

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'registration_number' => [
                'required',
                new Siret,
                Rule::unique('companies', 'registration_number')->ignore($company->id),
            ],
            'sector' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'regex:/^\d{5}$/'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
            'logo' => self::LOGO_RULES,
        ], [
            'name.required' => 'La raison sociale est obligatoire.',
            'registration_number.required' => 'Le SIRET est obligatoire.',
            'registration_number.unique' => 'Ce SIRET est déjà utilisé par une autre entreprise.',
            'postal_code.regex' => 'Le code postal doit contenir 5 chiffres.',
            'email.required' => 'L\'e-mail de contact est obligatoire.',
            'email.email' => 'L\'e-mail de contact n\'est pas valide.',
            ...self::LOGO_MESSAGES,
        ]);

        $previousLogo = $company->logo_path;
        $company->fill(array_map(
            fn ($value) => $value === '' ? null : $value,
            Arr::only($data, self::COMPANY_FIELDS),
        ));

        if ($this->logo) {
            try {
                $company->fill($logos->store($company, $this->logo));
            } catch (Throwable $e) {
                report($e);
                $company->refresh();
                $this->addError('logo', 'Le logo n\'a pas pu être enregistré (stockage indisponible). Réessayez ou contactez le support.');

                return;
            }
        }

        $changes = $company->getDirty();
        $before = Arr::only($company->getOriginal(), array_keys($changes));
        $company->save();

        if ($this->logo) {
            $logos->delete($previousLogo);
        }

        if ($changes) {
            $audit->log('company.updated', $company, ['before' => $before, 'after' => $changes]);
        }

        $message = 'Les informations de l\'entreprise ont été enregistrées.';

        // Recharge la page pour que la barre latérale affiche le nouveau logo
        if ($this->logo) {
            session()->flash('status', $message);

            return $this->redirectRoute('settings.index', ['section' => 'company']);
        }

        $this->toastMessage = $message;
    }

    public function removeLogo(CompanyLogoStorage $logos, AuditLogger $audit)
    {
        $company = $this->company();
        $this->authorize('update', $company);

        $before = ['logo_path' => $company->logo_path, 'logo_uuid' => $company->logo_uuid];

        if ($before['logo_path'] || $before['logo_uuid']) {
            $company->update(['logo_path' => null, 'logo_uuid' => null]);
            $logos->delete($before['logo_path']);
            $audit->log('company.updated', $company, [
                'before' => $before,
                'after' => ['logo_path' => null, 'logo_uuid' => null],
            ]);
        }

        session()->flash('status', 'Le logo a été supprimé.');

        return $this->redirectRoute('settings.index', ['section' => 'company']);
    }

    public function updatedLogo(): void
    {
        $this->authorize('update', $this->company());
        $this->validateOnly('logo', ['logo' => self::LOGO_RULES], self::LOGO_MESSAGES);
    }

    public function cancelLogo(): void
    {
        $this->authorize('update', $this->company());
        $this->reset('logo');
        $this->resetValidation('logo');
    }

    public function saveNotifications(AuditLogger $audit): void
    {
        $company = $this->company();
        $this->authorize('update', $company);

        $this->validate([
            'notifications' => ['array'],
            'notifications.*.email' => ['boolean'],
            'notifications.*.app' => ['boolean'],
            'notifications.*.recipients' => ['required', Rule::in(array_keys(CompanySettings::RECIPIENTS))],
        ], [
            'notifications.*.recipients.in' => 'Destinataires invalides.',
        ]);

        CompanySettings::for($company)->setNotifications($this->notifications)->save();
        $audit->log('company.settings_updated', $company, ['section' => 'notifications']);

        $this->notifications = CompanySettings::for($company)->notifications();
        $this->toastMessage = 'Les préférences de notifications ont été enregistrées.';
    }

    public function saveDeadlines(AuditLogger $audit): void
    {
        $company = $this->company();
        $this->authorize('update', $company);

        $this->validate([
            'expiring_soon_days' => ['required', 'integer', 'min:1', 'max:180'],
        ]);

        CompanySettings::for($company)->set('expiring_soon_days', $this->expiring_soon_days)->save();
        $audit->log('company.settings_updated', $company, ['section' => 'deadlines']);

        $this->toastMessage = 'Les règles d\'échéances ont été enregistrées.';
    }

    public function saveEmails(AuditLogger $audit): void
    {
        $company = $this->company();
        $this->authorize('update', $company);

        $this->validate([
            'email_subject' => ['required', 'string', 'max:255'],
            'email_body' => ['required', 'string', 'max:5000'],
        ]);

        CompanySettings::for($company)
            ->set('request_email.subject', $this->email_subject)
            ->set('request_email.body', $this->email_body)
            ->save();
        $audit->log('company.settings_updated', $company, ['section' => 'emails']);

        $this->toastMessage = 'Le modèle d\'e-mail a été enregistré.';
    }

    public function insertVariable(string $varName): void
    {
        $this->authorize('administer', $this->company());
        $this->email_body .= ' '.$varName;
    }

    public function render()
    {
        $company = $this->company();

        return view('livewire.app.settings.index', [
            'company' => $company,
            'canEdit' => auth()->user()->can('update', $company),
            'menu' => auth()->user()->can('administer', $company)
                ? self::MENU
                : Arr::except(self::MENU, self::ADMIN_ONLY_SECTIONS),
            'events' => CompanySettings::NOTIFICATION_EVENTS,
            'recipients' => CompanySettings::RECIPIENTS,
        ]);
    }

    private function company(): Company
    {
        return auth()->user()->company;
    }

    private function fillSettings(Company $company): void
    {
        $settings = CompanySettings::for($company);

        $this->notifications = $settings->notifications();
        $this->expiring_soon_days = (int) $settings->get('expiring_soon_days');
        $this->reminder_days = $settings->get('reminder_days');
        $this->email_subject = $settings->get('request_email.subject');
        $this->email_body = $settings->get('request_email.body');
    }
}
