<?php

/**
 * DpiaRiskServiceTest - pure-logic tests for the GDPR Art 35(3) high-risk
 * screen (#1109): each of the four named triggers, DPO override precedence,
 * and the clean "no triggers" case. No DB / no container.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems. AGPL-3.0-or-later.
 */

namespace AhgPrivacy\Tests\Unit;

use AhgPrivacy\Services\DpiaRiskService;
use PHPUnit\Framework\TestCase;

class DpiaRiskServiceTest extends TestCase
{
    private DpiaRiskService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new DpiaRiskService();
    }

    public function test_benign_activity_is_not_high_risk(): void
    {
        $r = $this->svc->screen([
            'name'               => 'Newsletter list',
            'purpose'            => 'Send a monthly newsletter to subscribers',
            'categories_of_data' => ['name', 'email'],
        ]);
        $this->assertFalse($r['high_risk']);
        $this->assertSame([], $r['triggers']);
    }

    public function test_special_category_data_flags(): void
    {
        $r = $this->svc->screen([
            'name'               => 'Patient records',
            'categories_of_data' => ['name', 'health condition'],
        ]);
        $this->assertTrue($r['high_risk']);
        $this->assertContains('special_category', $r['triggers']);
    }

    public function test_profiling_flags_from_purpose(): void
    {
        $r = $this->svc->screen([
            'name'    => 'Risk engine',
            'purpose' => 'Automated decision making to score applicant behaviour',
        ]);
        $this->assertTrue($r['high_risk']);
        $this->assertContains('large_scale_profiling', $r['triggers']);
    }

    public function test_biometric_flags(): void
    {
        $r = $this->svc->screen([
            'name'               => 'Door access',
            'categories_of_data' => ['fingerprint template'],
        ]);
        $this->assertTrue($r['high_risk']);
        $this->assertContains('biometric', $r['triggers']);
    }

    public function test_cross_border_without_safeguards_flags(): void
    {
        $r = $this->svc->screen([
            'name'                  => 'US analytics',
            'purpose'               => 'web analytics',
            'transfers_outside_eea' => true,
            'safeguards'            => '',
        ]);
        $this->assertTrue($r['high_risk']);
        $this->assertContains('cross_border_non_adequate', $r['triggers']);
    }

    public function test_cross_border_with_safeguards_does_not_flag(): void
    {
        $r = $this->svc->screen([
            'name'                  => 'US analytics with SCCs',
            'purpose'               => 'web analytics',
            'transfers_outside_eea' => true,
            'safeguards'            => 'EU Standard Contractual Clauses 2021/914',
        ]);
        $this->assertNotContains('cross_border_non_adequate', $r['triggers']);
    }

    public function test_override_forces_flag_when_heuristic_would_miss(): void
    {
        $r = $this->svc->screen([
            'name'                      => 'Opaque activity',
            'purpose'                   => 'general processing',
            'special_category_override' => 1,
        ]);
        $this->assertTrue($r['high_risk']);
        $this->assertContains('special_category', $r['triggers']);
    }

    public function test_override_suppresses_false_positive(): void
    {
        // "health" keyword would normally trip special_category; override = 0 wins.
        $r = $this->svc->screen([
            'name'                      => 'Health and safety committee minutes',
            'categories_of_data'        => ['name'],
            'special_category_override' => 0,
        ]);
        $this->assertNotContains('special_category', $r['triggers']);
    }
}
