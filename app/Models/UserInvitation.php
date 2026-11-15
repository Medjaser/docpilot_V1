<?php

namespace App\Models;

use App\Enums\Role;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Invitation d'un collaborateur (§9.4). */
class UserInvitation extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['email', 'role', 'token_hash', 'invited_by', 'expires_at', 'accepted_at', 'revoked_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** Génère un jeton : retourne le jeton CLAIR (pour l'e-mail), seul son hash est stocké. */
    public function issueToken(): string
    {
        $token = Str::random(48);
        $this->token_hash = hash('sha256', $token);

        return $token;
    }

    /** Hors filtre tenant : l'invité n'est pas encore rattaché à une entreprise. */
    public static function findByToken(string $token): ?self
    {
        return static::acrossAllCompanies()->where('token_hash', hash('sha256', $token))->first();
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->revoked_at === null && $this->expires_at->isFuture();
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
