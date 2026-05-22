<?php

/**
 * GuardrailServiceTest - Unit test for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgAiServices\Tests\Unit;

use AhgAiServices\Services\GuardrailService;
use PHPUnit\Framework\TestCase;

/**
 * heratio#141 - RAG guardrails. Pure unit test: GuardrailService is given an
 * explicit config array so no Laravel bootstrap or DB is needed.
 */
class GuardrailServiceTest extends TestCase
{
    private function svc(string $mode): GuardrailService
    {
        $cfg = GuardrailService::defaultConfig();
        $cfg['mode'] = $mode;

        return new GuardrailService($cfg);
    }

    public function testOffModeAllowsEverything(): void
    {
        $r = $this->svc('off')->inspect([
            'provider' => 'openai', 'user_prompt' => 'secret',
            'data_scope' => 'classified', 'purpose' => 'marketing',
        ]);

        $this->assertSame('allow', $r['action']);
        $this->assertSame([], $r['flags']);
    }

    public function testBlockModeBlocksOutOfScopeDataToCloud(): void
    {
        $r = $this->svc('block')->inspect([
            'provider' => 'openai', 'user_prompt' => 'sensitive record',
            'data_scope' => 'classified', 'purpose' => 'summarization',
        ]);

        $this->assertSame('block', $r['action']);
        $this->assertStringContainsString('classified', (string) $r['reason']);
    }

    public function testWarnModeFlagsOutOfScopeButAllows(): void
    {
        $r = $this->svc('warn')->inspect([
            'provider' => 'openai', 'user_prompt' => 'x',
            'data_scope' => 'classified', 'purpose' => 'summarization',
        ]);

        $this->assertSame('allow', $r['action']);
        $this->assertContains('data_scope_out_of_policy', $r['flags']);
    }

    public function testLocalProviderCarriesAnyScope(): void
    {
        // ollama is local - data never leaves the trust domain, so even a
        // classified scope is fine under block mode.
        $r = $this->svc('block')->inspect([
            'provider' => 'ollama', 'user_prompt' => 'x',
            'data_scope' => 'classified', 'purpose' => 'summarization',
        ]);

        $this->assertSame('allow', $r['action']);
    }

    public function testBlockModeRejectsNonSanctionedPurpose(): void
    {
        $r = $this->svc('block')->inspect([
            'provider' => 'ollama', 'user_prompt' => 'x',
            'data_scope' => 'internal', 'purpose' => 'marketing',
        ]);

        $this->assertSame('block', $r['action']);
        $this->assertFalse($r['purpose_sanctioned']);
    }

    public function testSanctionedPurposeIsAccepted(): void
    {
        $r = $this->svc('block')->inspect([
            'provider' => 'ollama', 'user_prompt' => 'x',
            'data_scope' => 'internal', 'purpose' => 'summarization',
        ]);

        $this->assertSame('allow', $r['action']);
        $this->assertTrue($r['purpose_sanctioned']);
    }

    public function testMaskModeRedactsPiiOnCloudPrompt(): void
    {
        $r = $this->svc('mask')->inspect([
            'provider'    => 'openai',
            'user_prompt' => 'Contact the donor at jane@example.com or +27 11 555 1234.',
            'data_scope'  => 'internal',
            'purpose'     => 'summarization',
        ]);

        $this->assertSame('mask', $r['action']);
        $this->assertGreaterThanOrEqual(2, $r['pii_masked']);
        $this->assertStringContainsString('[REDACTED:email]', $r['user_prompt']);
        $this->assertStringContainsString('[REDACTED:number]', $r['user_prompt']);
        $this->assertStringNotContainsString('jane@example.com', $r['user_prompt']);
    }

    public function testMaskModeLeavesLocalProviderPromptsUntouched(): void
    {
        $r = $this->svc('mask')->inspect([
            'provider' => 'ollama', 'user_prompt' => 'Contact jane@example.com',
            'data_scope' => 'internal', 'purpose' => 'summarization',
        ]);

        $this->assertSame(0, $r['pii_masked']);
        $this->assertStringContainsString('jane@example.com', $r['user_prompt']);
    }

    public function testMaskPiiLeavesShortDateRangesAlone(): void
    {
        // "1939-1945" carries only 8 digits - not masked as a number.
        [$text, $count] = $this->svc('mask')->maskPii('Records covering 1939-1945 in the fonds.');

        $this->assertSame(0, $count);
        $this->assertStringContainsString('1939-1945', $text);
    }

    public function testMaskPiiCountsEmailAndLongNumber(): void
    {
        [$text, $count] = $this->svc('mask')->maskPii('Email a@b.com, ID 1234567890123.');

        $this->assertSame(2, $count);
        $this->assertStringContainsString('[REDACTED:email]', $text);
        $this->assertStringContainsString('[REDACTED:number]', $text);
    }

    public function testGroundingHighWhenOutputEchoesContext(): void
    {
        $context = ['correspondence concerning railway construction projects mozambique territory archival fonds'];
        $output  = 'Correspondence concerning railway construction projects within Mozambique territory.';

        $g = $this->svc('warn')->checkGrounding($output, $context);

        $this->assertNotNull($g);
        $this->assertTrue($g['grounded']);
        $this->assertGreaterThan(0.45, $g['grounding_score']);
        $this->assertNull($g['flag']);
    }

    public function testGroundingLowFlagsUngroundedOutput(): void
    {
        $context = ['correspondence concerning railway construction projects mozambique territory'];
        $output  = 'Quantum spectroscopy demonstrates unprecedented photovoltaic efficiency measurements aboard satellites.';

        $g = $this->svc('warn')->checkGrounding($output, $context);

        $this->assertNotNull($g);
        $this->assertFalse($g['grounded']);
        $this->assertSame('low_grounding', $g['flag']);
    }

    public function testGroundingNullWhenNoBundle(): void
    {
        $this->assertNull($this->svc('warn')->checkGrounding('any output text here', []));
    }

    public function testIsCloudProviderClassification(): void
    {
        $svc = $this->svc('warn');

        $this->assertFalse($svc->isCloudProvider('ollama'));
        $this->assertTrue($svc->isCloudProvider('openai'));
        $this->assertTrue($svc->isCloudProvider('anthropic'));
    }

    public function testSummarizeFoldsInspectAndGrounding(): void
    {
        $svc     = $this->svc('warn');
        $inspect = $svc->inspect([
            'provider' => 'openai', 'user_prompt' => 'x',
            'data_scope' => 'classified', 'purpose' => 'summarization',
        ]);
        $grounding = ['grounding_score' => 0.2, 'grounded' => false, 'flag' => 'low_grounding', 'terms_checked' => 9];

        $summary = $svc->summarize($inspect, $grounding);

        $this->assertSame(0.2, $summary['grounding_score']);
        $this->assertFalse($summary['grounded']);
        $this->assertContains('data_scope_out_of_policy', $summary['flags']);
        $this->assertContains('low_grounding', $summary['flags']);
    }
}
