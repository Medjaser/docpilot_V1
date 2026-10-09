<?php

namespace Tests\Unit\Domain;

use App\Domain\Documents\ExpirationAlertSchedule;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ExpirationAlertScheduleTest extends TestCase
{
    public function test_alert_milestones(): void
    {
        $schedule = new ExpirationAlertSchedule([60, 30, 15, 7, 0]);
        $today = new DateTimeImmutable('2026-08-01');

        $this->assertSame('30d', $schedule->alertFor(new DateTimeImmutable('2026-08-31'), $today));
        $this->assertSame('7d', $schedule->alertFor(new DateTimeImmutable('2026-08-08'), $today));
        $this->assertSame('0d', $schedule->alertFor(new DateTimeImmutable('2026-08-01'), $today));
        $this->assertSame('expired', $schedule->alertFor(new DateTimeImmutable('2026-07-25'), $today));
        $this->assertNull($schedule->alertFor(new DateTimeImmutable('2026-08-20'), $today));
    }
}
