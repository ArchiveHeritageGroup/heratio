<?php

/**
 * OdiScorecardServiceTest - unit tests for OdiScorecardService.
 *
 * Tests the pure (database-free) scoring layer:
 *   - computeQualityScore weighting + 0-100 normalisation
 *   - clamping of out-of-range inputs
 *   - coverage ratios for preprints/ORCID against item_count
 *   - empty-collection edge cases
 *
 * Copyright (C) 2026 Johan Pieterse
 * AGPL-3.0
 */

namespace AhgLibrary\Tests\Unit;

use AhgLibrary\Services\OdiScorecardService;
use AhgLibrary\Tests\AhgLibraryTestCase;
use ReflectionClass;

class OdiScorecardServiceTest extends AhgLibraryTestCase
{
    private OdiScorecardService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = (new ReflectionClass(OdiScorecardService::class))->newInstanceWithoutConstructor();
    }

    public function test_perfect_collection_scores_100(): void
    {
        $score = $this->svc->computeQualityScore([
            'item_count'            => 10,
            'link_resolver_present' => true,
            'oa_percentage'         => 100.0,
            'preprints_indexed'     => 10,
            'orcid_in_records'      => 10,
        ]);

        $this->assertSame(100.0, $score);
    }

    public function test_empty_collection_scores_zero(): void
    {
        $score = $this->svc->computeQualityScore([
            'item_count'            => 0,
            'link_resolver_present' => false,
            'oa_percentage'         => 0.0,
            'preprints_indexed'     => 0,
            'orcid_in_records'      => 0,
        ]);

        $this->assertSame(0.0, $score);
    }

    public function test_link_resolver_only_yields_weight(): void
    {
        // link_resolver weight is 0.25 -> 25.0 on the 0-100 scale.
        $score = $this->svc->computeQualityScore([
            'item_count'            => 5,
            'link_resolver_present' => true,
            'oa_percentage'         => 0.0,
            'preprints_indexed'     => 0,
            'orcid_in_records'      => 0,
        ]);

        $this->assertSame(25.0, $score);
    }

    public function test_oa_percentage_weighted_at_35(): void
    {
        // 50% OA only -> 0.5 * 35 = 17.5
        $score = $this->svc->computeQualityScore([
            'item_count'            => 4,
            'link_resolver_present' => false,
            'oa_percentage'         => 50.0,
            'preprints_indexed'     => 0,
            'orcid_in_records'      => 0,
        ]);

        $this->assertSame(17.5, $score);
    }

    public function test_coverage_ratios_capped_at_one(): void
    {
        // preprints/orcid exceed item_count -> capped at 100% coverage.
        $score = $this->svc->computeQualityScore([
            'item_count'            => 2,
            'link_resolver_present' => false,
            'oa_percentage'         => 0.0,
            'preprints_indexed'     => 99,
            'orcid_in_records'      => 99,
        ]);

        // preprints 0.15 + orcid 0.25 = 0.40 -> 40.0
        $this->assertSame(40.0, $score);
    }

    public function test_out_of_range_oa_is_clamped(): void
    {
        $score = $this->svc->computeQualityScore([
            'item_count'            => 1,
            'link_resolver_present' => false,
            'oa_percentage'         => 250.0, // nonsense input
            'preprints_indexed'     => 0,
            'orcid_in_records'      => 0,
        ]);

        // clamped to 100% OA -> 35.0
        $this->assertSame(35.0, $score);
    }

    public function test_partial_mixed_metrics(): void
    {
        // link resolver yes (0.25), 40% OA (0.4*0.35=0.14), 1/4 preprints
        // (0.25*0.15=0.0375), 2/4 orcid (0.5*0.25=0.125) -> 0.5525 -> 55.25
        $score = $this->svc->computeQualityScore([
            'item_count'            => 4,
            'link_resolver_present' => true,
            'oa_percentage'         => 40.0,
            'preprints_indexed'     => 1,
            'orcid_in_records'      => 2,
        ]);

        $this->assertSame(55.25, $score);
    }

    public function test_link_resolver_present_default_true(): void
    {
        $this->assertTrue($this->svc->linkResolverPresent());
    }

    public function test_missing_keys_default_to_zero(): void
    {
        // No keys at all -> 0.
        $this->assertSame(0.0, $this->svc->computeQualityScore([]));
    }
}
