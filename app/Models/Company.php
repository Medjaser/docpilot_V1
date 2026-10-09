<?php

namespace App\Models;

use App\Enums\CompanyStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Entreprise cliente = tenant. */
class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'trade_name', 'registration_number', 'sector', 'email', 'phone',
        'address', 'postal_code', 'city', 'country', 'logo_path',
        'status', 'subscription_plan', 'trial_ends_at', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'status' => CompanyStatus::class,
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function subcontractors(): HasMany
    {
        return $this->hasMany(Subcontractor::class);
    }

    /** Paramètre d'entreprise avec repli sur la valeur par défaut de config/docpilot.php */
    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }
}
