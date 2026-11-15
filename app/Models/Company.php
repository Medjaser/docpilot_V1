<?php

namespace App\Models;

use App\Domain\Company\CompanySettings;
use App\Enums\CompanyStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Entreprise cliente = tenant. */
class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'trade_name', 'registration_number', 'sector', 'email', 'phone',
        'address', 'postal_code', 'city', 'country', 'logo_path', 'logo_uuid',
        'status', 'status_reason', 'subscription_plan', 'trial_ends_at', 'suspended_at', 'terminated_at',
        'settings', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CompanyStatus::class,
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
            'terminated_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(UserInvitation::class);
    }

    public function subcontractors(): HasMany
    {
        return $this->hasMany(Subcontractor::class);
    }

    public function documentTypes(): HasMany
    {
        return $this->hasMany(DocumentType::class);
    }

    public function documentTemplates(): HasMany
    {
        return $this->hasMany(DocumentTemplate::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function documentRequests(): HasMany
    {
        return $this->hasMany(DocumentRequest::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /** Paramètre d'entreprise, valeurs par défaut de CompanySettings fusionnées. */
    public function setting(string $key, mixed $default = null): mixed
    {
        return CompanySettings::for($this)->get($key, $default);
    }

    /** Suspendue, résiliée ou archivée : aucune écriture possible. */
    public function isReadOnly(): bool
    {
        return ! $this->status->isWritable();
    }

    /** Jours d'essai restants (0 le dernier jour), null hors essai. */
    public function trialDaysLeft(?CarbonInterface $today = null): ?int
    {
        if ($this->status !== CompanyStatus::Trial || $this->trial_ends_at === null) {
            return null;
        }

        $today = ($today ?? now())->copy()->startOfDay();

        return max(0, (int) $today->diffInDays($this->trial_ends_at->copy()->startOfDay(), false));
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->name, 0, 2));
    }

    /**
     * URL publique absolue du logo (utilisable dans les e-mails). Elle ne révèle pas l'entreprise
     * et change à chaque remplacement, ce qui invalide aussi le cache navigateur.
     */
    public function logoUrl(): ?string
    {
        return $this->logo_uuid && $this->logo_path
            ? route('company.logo', ['uuid' => $this->logo_uuid])
            : null;
    }
}
