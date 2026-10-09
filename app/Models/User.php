<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Utilisateur d'une entreprise cliente (ou administrateur plateforme si company_id est NULL).
 * Pas de BelongsToCompany ici : l'authentification doit pouvoir retrouver l'utilisateur
 * avant que l'entreprise courante soit connue. L'isolation passe par les Policies.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'company_id', 'first_name', 'last_name', 'email', 'password', 'role', 'status', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function isPlatformAdmin(): bool
    {
        return $this->role === UserRole::PlatformAdmin;
    }

    public function belongsToCompany(?int $companyId): bool
    {
        return $companyId !== null && $this->company_id === $companyId;
    }
}
