<?php

/**
 * PrivacyRedactionServiceTest - pure-logic tests for field-level redaction
 * (#1108): per-type redaction, partial patterns, pseudonymisation, and the
 * anonymous fail-closed auth rule. No DB/network.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems. AGPL-3.0-or-later.
 */

namespace AhgPrivacy\Tests\Unit;

use AhgPrivacy\Services\PrivacyRedactionService;
use PHPUnit\Framework\TestCase;

class PrivacyRedactionServiceTest extends TestCase
{
    private PrivacyRedactionService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new PrivacyRedactionService();
    }

    public function test_full_redaction_replaces_the_whole_value(): void
    {
        $this->assertSame(
            PrivacyRedactionService::FULL_PLACEHOLDER,
            $this->svc->redactValue('1954-03-21', 'full')
        );
    }

    public function test_pseudonymised_is_stable_and_non_reversible(): void
    {
        $a = $this->svc->redactValue('Jane Doe', 'pseudonymised');
        $b = $this->svc->redactValue('Jane Doe', 'pseudonymised');
        $this->assertSame($a, $b, 'same input → same pseudonym');
        $this->assertStringStartsWith('Subject-', $a);
        $this->assertStringNotContainsString('Jane', $a);
    }

    public function test_partial_without_pattern_uses_placeholder(): void
    {
        $this->assertSame(
            PrivacyRedactionService::PARTIAL_PLACEHOLDER,
            $this->svc->redactValue('whatever', 'partial')
        );
    }

    public function test_partial_patterns(): void
    {
        $this->assertSame('j***@***', $this->svc->applyPattern('jane@example.com', 'email_partial'));
        $this->assertSame('******4567', $this->svc->applyPattern('0821234567', 'phone_partial'));
        $this->assertSame('********3456', $this->svc->applyPattern('123456783456', 'id_last4'));
        $this->assertSame('1954', $this->svc->applyPattern('born 1954 in town', 'year_only'));
    }

    public function test_unknown_type_defaults_to_full(): void
    {
        $this->assertSame(
            PrivacyRedactionService::FULL_PLACEHOLDER,
            $this->svc->redactValue('x', 'bogus-type')
        );
    }

    public function test_anonymous_cannot_view_unredacted(): void
    {
        $this->assertFalse($this->svc->canViewUnredacted(null));
    }
}
