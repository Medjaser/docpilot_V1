<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Utilisateur d'une entreprise cliente (ou administrateur plateforme si is_platform_admin).
 * Pas de BelongsToCompany ici : l'authentification doit pouvoir retrouver l'utilisateur
 * avant que l'entreprise courante soit connue. L'isolation passe par les Policies.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    // is_platform_admin volontairement absent : jamais assignable depuis un formulaire
    protected $fillable = [
        'company_id', 'first_name', 'last_name', 'email', 'password',
        'role', 'status', 'last_login_at', 'deactivated_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'status' => UserStatus::class,
            'is_platform_admin' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function ownedSubcontractors(): HasMany
    {
        return $this->hasMany(Subcontractor::class, 'internal_owner_id');
    }

    public function sentInvitations(): HasMany
    {
        return $this->hasMany(UserInvitation::class, 'invited_by');
    }

    /** Notifications filtrées par le CompanyScope, même si un ID utilisateur est manipulé. */
    public function notifications(): MorphMany
    {
        return $this->morphMany(DatabaseNotification::class, 'notifiable')->latest();
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getNameAttribute(): string
    {
        return $this->full_name;
    }

    public function isPlatformAdmin(): bool
    {
        return $this->is_platform_admin;
    }

    public function belongsToCompany(?int $companyId): bool
    {
        return $companyId !== null && $this->company_id === $companyId;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isPlatformAdmin()) {
            return false;
        }

        if (! $this->role) {
            return false;
        }

        // Entreprise suspendue : lecture seule, quel que soit le rôle (composants et policies)
        if (! self::isReadPermission($permission) && $this->company?->isReadOnly()) {
            return false;
        }

        $permissions = $this->role->permissions();

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public static function isReadPermission(string $permission): bool
    {
        return str_ends_with($permission, '.view') || str_ends_with($permission, '.download');
    }
}
