<?php

namespace Tests\Unit\Domain;

use App\Domain\Documents\CompletenessScore;
use App\Enums\DocumentStatus as S;
use App\Enums\SubcontractorStatus;
use PHPUnit\Framework\TestCase;

class CompletenessScoreTest extends TestCase
{
    public function test_example_from_cadrage_4_valid_out_of_5_gives_80(): void
    {
        $score = (new CompletenessScore)->calculate([S::Valid, S::Valid, S::Valid, S::Valid, S::Missing]);

        $this->assertSame(80, $score);
    }

    public function test_batinova_example_5_valid_out_of_7_gives_71(): void
    {
        $statuses = [S::Valid, S::Valid, S::Valid, S::Valid, S::Valid, S::Missing, S::Rejected];

        $this->assertSame(71, (new CompletenessScore)->calculate($statuses));
    }

    public function test_expired_document_is_not_counted_as_valid(): void
    {
        $this->assertSame(50, (new CompletenessScore)->calculate([S::Valid, S::Expired]));
    }

    public function test_expiring_soon_still_counts_as_valid(): void
    {
        $this->assertSame(100, (new CompletenessScore)->calculate([S::Valid, S::ExpiringSoon]));
    }

    public function test_levels(): void
    {
        $calc = new CompletenessScore;

        $this->assertSame('complete', $calc->level(100));
        $this->assertSame('almost_complete', $calc->level(80));
        $this->assertSame('incomplete', $calc->level(71));
        $this->assertSame('critical', $calc->level(40));
    }

    public function test_subcontractor_status(): void
    {
        $calc = new CompletenessScore;

        $this->assertSame(SubcontractorStatus::Complete, $calc->subcontractorStatus([S::Valid, S::Valid]));
        $this->assertSame(SubcontractorStatus::Incomplete, $calc->subcontractorStatus([S::Valid, S::Missing]));
        $this->assertSame(SubcontractorStatus::Expired, $calc->subcontractorStatus([S::Valid, S::Expired]));
    }
}
