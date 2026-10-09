<?php

namespace App\Models;

use App\Enums\DocumentRequestStatus;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/** Demande de documents envoyée à un sous-traitant, avec son lien de dépôt sécurisé (§9.8). */
class DocumentRequest extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'subcontractor_id', 'token_hash', 'status', 'message', 'due_date', 'expires_at',
        'sent_at', 'last_reminder_at', 'reminder_count', 'auto_reminders', 'cancelled_at', 'created_by',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'status' => DocumentRequestStatus::class,
            'due_date' => 'date',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'last_reminder_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'auto_reminders' => 'boolean',
        ];
    }

    /**
     * Génère un nouveau jeton. Retourne le jeton CLAIR (à mettre dans l'URL / l'e-mail) ;
     * seul son hash SHA-256 est stocké en base.
     */
    public function issueToken(): string
    {
        $token = Str::random(config('docpilot.portal.token_length', 48));
        $this->token_hash = hash('sha256', $token);

        return $token;
    }

    /** Retrouve une demande à partir du jeton clair (hors filtre tenant : le tenant n'est pas encore connu). */
    public static function findByToken(string $token): ?self
    {
        return static::acrossAllCompanies()->where('token_hash', hash('sha256', $token))->first();
    }

    public function isUsable(): bool
    {
        return $this->status === DocumentRequestStatus::Sent
            && $this->cancelled_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Subcontractor::class);
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'requested_documents')->withTimestamps();
    }
}
