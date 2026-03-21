<?php

namespace App\Tests\Unit;

use App\Controller\TrackingController;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires — méthodes statiques de TrackingController
 *
 * Couvre : formatDurationLabel, dayLabel.
 */
class TrackingControllerUnitTest extends TestCase
{
    // -------------------------------------------------------------------------
    // formatDurationLabel
    // -------------------------------------------------------------------------

    public function testFormatDurationLabelReturnsZeroMinForZeroInput(): void
    {
        $this->assertSame('0 min', $this->formatDuration(0));
    }

    public function testFormatDurationLabelReturnsZeroMinForNegativeInput(): void
    {
        $this->assertSame('0 min', $this->formatDuration(-10));
    }

    public function testFormatDurationLabelReturnsAtLeast1MinForShortDuration(): void
    {
        $this->assertSame('1 min', $this->formatDuration(30));
    }

    public function testFormatDurationLabelReturnsMinutesOnly(): void
    {
        $this->assertSame('5 min', $this->formatDuration(300));
    }

    public function testFormatDurationLabelReturnsHoursAndMinutes(): void
    {
        $this->assertSame('1 h 1 min', $this->formatDuration(3660));
    }

    public function testFormatDurationLabelReturns2HoursExact(): void
    {
        $this->assertSame('2 h 0 min', $this->formatDuration(7200));
    }

    // -------------------------------------------------------------------------
    // dayLabel
    // -------------------------------------------------------------------------

    public function testDayLabelReturnsLunForMonday(): void
    {
        $monday = new \DateTimeImmutable('2026-03-16'); // un lundi
        $this->assertSame('Lun', $this->dayLabel($monday));
    }

    public function testDayLabelReturnsDimForSunday(): void
    {
        $sunday = new \DateTimeImmutable('2026-03-22'); // un dimanche
        $this->assertSame('Dim', $this->dayLabel($sunday));
    }

    public function testDayLabelReturnsMerForWednesday(): void
    {
        $wednesday = new \DateTimeImmutable('2026-03-18'); // un mercredi
        $this->assertSame('Mer', $this->dayLabel($wednesday));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function formatDuration(int $seconds): string
    {
        $ref = new \ReflectionMethod(TrackingController::class, 'formatDurationLabel');

        return $ref->invoke(null, $seconds);
    }

    private function dayLabel(\DateTimeImmutable $date): string
    {
        $ref = new \ReflectionMethod(TrackingController::class, 'dayLabel');

        return $ref->invoke(null, $date);
    }
}
