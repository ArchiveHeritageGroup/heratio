<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Guards against a return to the hardcoded-empty stubs.
 *
 * MuseumController::qualityDashboard() used to be
 * `$overallScore = 0; $analyzedRecords = 0;` with an 'N/A' grade - a permanent
 * zero regardless of the catalogue, which is worse than showing nothing because
 * it asserts a measurement nobody took. objectComparison() was
 * `$objects = collect();`.
 *
 * These assert the SHAPE of a real computation rather than a fixed number, so
 * they do not break when the catalogue changes.
 */
class MuseumDashboardStubTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_quality_dashboard_counts_real_records(): void
    {
        $data = app(\AhgMuseum\Controllers\MuseumController::class)->qualityDashboard()->getData();

        $expected = (int) \Illuminate\Support\Facades\DB::table('museum_metadata')->count();

        $this->assertSame($expected, $data['analyzedRecords'],
            'analyzedRecords must reflect museum_metadata, not a hardcoded 0');
        $this->assertIsInt($data['overallScore']);
        $this->assertGreaterThanOrEqual(0, $data['overallScore']);
        $this->assertLessThanOrEqual(100, $data['overallScore']);
        $this->assertArrayHasKey('grade', $data['overallGrade']);
        $this->assertNotEmpty($data['missingFieldCounts'], 'a field breakdown must be produced');
    }

    /**
     * With records present the grade must be a real letter. 'N/A' is reserved
     * for an empty catalogue and was the stub's permanent answer.
     */
    public function test_a_populated_catalogue_does_not_grade_as_not_applicable(): void
    {
        $data = app(\AhgMuseum\Controllers\MuseumController::class)->qualityDashboard()->getData();

        if ($data['analyzedRecords'] === 0) {
            $this->assertSame('N/A', $data['overallGrade']['grade']);

            return;
        }

        $this->assertContains($data['overallGrade']['grade'], ['A', 'B', 'C', 'D', 'E']);
    }
}
