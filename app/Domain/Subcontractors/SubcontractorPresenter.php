<?php

namespace App\Domain\Subcontractors;

use App\Enums\DocumentStatus;
use App\Enums\SubcontractorStatus;
use App\Models\Subcontractor;
use Illuminate\Database\Eloquent\Builder;

/**
 * Traduit un sous-traitant Eloquent vers les clés attendues par les vues (badges, listes).
 * Les requêtes passent toujours par Subcontractor (BelongsToCompany) : jamais de DB::table.
 */
class SubcontractorPresenter
{
    /** Statuts des badges de liste (complete / incomplete / critical) => statuts métier. */
    public const BADGE_STATUSES = [
        'complete' => [SubcontractorStatus::Complete],
        'incomplete' => [SubcontractorStatus::Draft, SubcontractorStatus::WaitingDocuments, SubcontractorStatus::Incomplete],
        'critical' => [SubcontractorStatus::ExpiredDocuments, SubcontractorStatus::Suspended],
    ];

    /** Documents encore « vivants » pour le calcul des échéances. */
    public const TRACKED_DOCUMENT_STATUSES = [
        DocumentStatus::Valid, DocumentStatus::ExpiringSoon, DocumentStatus::Expired, DocumentStatus::Pending,
    ];

    public static function badgeStatus(SubcontractorStatus $status): string
    {
        foreach (self::BADGE_STATUSES as $badge => $statuses) {
            if (in_array($status, $statuses, true)) {
                return $badge;
            }
        }

        return 'disabled';
    }

    /** Ajoute la colonne next_deadline (prochaine date d'expiration suivie). */
    public static function withNextDeadline(Builder $query): Builder
    {
        return $query->withMin(
            ['documents as next_deadline' => fn ($documents) => $documents
                ->whereNotNull('expiration_date')
                ->whereIn('status', self::TRACKED_DOCUMENT_STATUSES)],
            'expiration_date',
        );
    }

    public static function initials(string $name): string
    {
        return mb_strtoupper(mb_substr($name, 0, 2));
    }

    /** Ligne de liste (index, export, recherche). */
    public static function row(Subcontractor $subcontractor): array
    {
        $nextDeadline = $subcontractor->getAttribute('next_deadline');

        return [
            'id' => $subcontractor->id,
            'name' => $subcontractor->legal_name,
            'activity' => $subcontractor->activity ?? '—',
            'contact_name' => $subcontractor->contact_name ?: '—',
            'contact_email' => $subcontractor->contact_email,
            'city' => $subcontractor->city ?? '—',
            'manager' => $subcontractor->internalOwner?->full_name ?? '—',
            'completeness' => (int) $subcontractor->completeness_score,
            'status' => self::badgeStatus($subcontractor->status),
            'next_deadline' => $nextDeadline ? date('d/m/Y', strtotime((string) $nextDeadline)) : '—',
        ];
    }
}
