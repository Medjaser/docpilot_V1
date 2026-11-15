<?php

namespace App\Domain\Documents;

use App\Enums\SubcontractorStatus;
use App\Models\Document;
use App\Models\Subcontractor;

/**
 * Recalcule et persiste le score de complétude et le statut de dossier d'un sous-traitant (§9.18).
 */
final class SubcontractorCompletenessUpdater
{
    public function __construct(private readonly CompletenessScore $calculator = new CompletenessScore) {}

    public function refresh(Subcontractor $subcontractor): Subcontractor
    {
        $documents = Document::acrossAllCompanies()
            ->with('documentType')
            ->where('subcontractor_id', $subcontractor->id)
            ->whereNull('archived_at')
            ->get();

        $mandatoryStatuses = $documents
            ->filter(fn (Document $doc) => $doc->documentType
                && $doc->documentType->is_mandatory
                && $doc->documentType->is_active
                && $doc->documentType->archived_at === null)
            ->map(fn (Document $doc) => $doc->status)
            ->values()
            ->all();

        $score = $this->calculator->calculate($mandatoryStatuses);
        $attributes = ['completeness_score' => $score];

        if ($subcontractor->archived_at === null && $subcontractor->status !== SubcontractorStatus::Archived) {
            $attributes['status'] = $mandatoryStatuses === []
                ? SubcontractorStatus::Draft
                : $this->calculator->subcontractorStatus($mandatoryStatuses);
        }

        $subcontractor->update($attributes);

        return $subcontractor->refresh();
    }
}

