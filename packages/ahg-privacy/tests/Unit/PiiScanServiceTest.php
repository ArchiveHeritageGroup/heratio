<?php

/**
 * PiiScanServiceTest - pure-logic tests for the parts of the PII scanner that
 * can be asserted without a database: operator watchlist terms, jurisdiction
 * resolution, and the jurisdiction fallback that stops an unsupported market
 * from silently detecting nothing.
 *
 * No DB / no container by design. CI builds its test database from
 * database/core/*.sql alone, so a DB-backed test here would skip in CI and
 * prove nothing - which is why parseCustomTerms() is a pure static and the
 * constructor accepts an explicit term list.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems. AGPL-3.0-or-later.
 */

namespace AhgPrivacy\Tests\Unit;

use AhgPrivacy\Controllers\PrivacyController;
use AhgPrivacy\Services\PiiScanService;
use PHPUnit\Framework\TestCase;

class PiiScanServiceTest extends TestCase
{
    /** @param list<string> $terms */
    private function svc(array $terms = [], string $jurisdiction = 'popia'): PiiScanService
    {
        return new PiiScanService($jurisdiction, $terms);
    }

    /**
     * @param array<int,array<string,mixed>> $findings
     * @return array<int,array<string,mixed>>
     */
    private function ofType(array $findings, string $type): array
    {
        return array_values(array_filter($findings, static fn ($f) => $f['type'] === $type));
    }

    // =====================================================================
    //  parseCustomTerms() - pure string handling
    // =====================================================================

    public function test_separators_newline_comma_and_semicolon_all_split(): void
    {
        $terms = PiiScanService::parseCustomTerms("Blaauwbosch\nvan der Merwe, Kerkstraat; Nkosi");
        $this->assertSame(['Blaauwbosch', 'van der Merwe', 'Kerkstraat', 'Nkosi'], $terms);
    }

    public function test_crlf_from_a_pasted_spreadsheet_column_splits(): void
    {
        $this->assertSame(['Alpha', 'Beta'], PiiScanService::parseCustomTerms("Alpha\r\nBeta"));
    }

    public function test_blank_entries_and_stray_separators_are_dropped(): void
    {
        $this->assertSame(['Alpha', 'Beta'], PiiScanService::parseCustomTerms("Alpha\n\n  \n,,;Beta,\n"));
    }

    public function test_empty_and_whitespace_only_input_yields_no_terms(): void
    {
        $this->assertSame([], PiiScanService::parseCustomTerms(''));
        $this->assertSame([], PiiScanService::parseCustomTerms("  \n\t \r\n "));
    }

    public function test_duplicates_collapse_case_insensitively_keeping_first_spelling(): void
    {
        $this->assertSame(['Nkosi'], PiiScanService::parseCustomTerms("Nkosi\nNKOSI\nnkosi"));
    }

    public function test_single_character_terms_are_rejected(): void
    {
        // A bare letter matches most documents and would bury real findings.
        $this->assertSame(['ab'], PiiScanService::parseCustomTerms("a\nb\nab"));
    }

    public function test_term_list_is_capped(): void
    {
        $raw = implode("\n", array_map(static fn ($i) => 'term'.$i, range(1, PiiScanService::MAX_CUSTOM_TERMS + 50)));
        $this->assertCount(PiiScanService::MAX_CUSTOM_TERMS, PiiScanService::parseCustomTerms($raw));
    }

    // =====================================================================
    //  collectCustomTerms() - matching behaviour
    // =====================================================================

    public function test_custom_term_is_matched_and_reported_at_090(): void
    {
        $f = $this->ofType($this->svc(['Blaauwbosch'])->scan('Survey of Blaauwbosch farm.'), 'custom_term');
        $this->assertCount(1, $f);
        $this->assertSame('Blaauwbosch', $f[0]['value']);
        $this->assertSame(0.90, $f[0]['confidence']);
    }

    public function test_matching_ignores_case(): void
    {
        $f = $this->ofType($this->svc(['Nkosi'])->scan('Letter from NKOSI and nkosi.'), 'custom_term');
        $this->assertCount(2, $f);
    }

    public function test_matching_is_whole_word(): void
    {
        // "Smith" must not fire inside "Smithers" or "Blacksmith".
        $f = $this->ofType($this->svc(['Smith'])->scan('Smithers and the blacksmith met Smith.'), 'custom_term');
        $this->assertCount(1, $f);
        $this->assertSame('Smith', $f[0]['value']);
    }

    public function test_offsets_locate_each_occurrence_separately(): void
    {
        $text = 'Nkosi wrote; Nkosi replied.';
        $f = $this->ofType($this->svc(['Nkosi'])->scan($text), 'custom_term');
        $this->assertCount(2, $f);
        $this->assertSame(0, $f[0]['offset_start']);
        $this->assertSame(13, $f[1]['offset_start']);
        foreach ($f as $hit) {
            $this->assertSame('Nkosi', substr($text, $hit['offset_start'], $hit['offset_end'] - $hit['offset_start']));
        }
    }

    /**
     * The term list is free text, so it MUST be quoted before compiling. These
     * two are the cases that break a naive implementation: "C++" would throw as
     * a pattern, and both would match wildly if they compiled at all.
     */
    public function test_regex_metacharacters_in_a_term_are_matched_literally(): void
    {
        $f = $this->ofType($this->svc(['C++'])->scan('Written in C++ back then.'), 'custom_term');
        $this->assertCount(1, $f);
        $this->assertSame('C++', $f[0]['value']);
    }

    public function test_a_term_with_parentheses_and_a_dot_matches_literally(): void
    {
        $f = $this->ofType($this->svc(['Smith (Jr.)'])->scan('Filed by Smith (Jr.) in 1962.'), 'custom_term');
        $this->assertCount(1, $f);
        $this->assertSame('Smith (Jr.)', $f[0]['value']);
    }

    public function test_a_dot_in_a_term_does_not_act_as_a_wildcard(): void
    {
        $this->assertSame([], $this->ofType($this->svc(['a.c'])->scan('abc'), 'custom_term'));
    }

    public function test_no_terms_configured_produces_no_custom_findings(): void
    {
        $this->assertSame([], $this->ofType($this->svc([])->scan('Blaauwbosch and Nkosi.'), 'custom_term'));
    }

    // =====================================================================
    //  Jurisdiction resolution + the unsupported-jurisdiction fallback
    // =====================================================================

    public function test_jurisdiction_falls_back_to_gdpr_when_the_setting_is_unreadable(): void
    {
        // No container here, so the settings read throws and is caught. This is
        // the same path a fresh install takes before ahg_settings exists.
        $this->assertSame('gdpr', (new PiiScanService())->jurisdiction());
    }

    public function test_an_explicit_jurisdiction_is_kept(): void
    {
        $this->assertSame('popia', $this->svc([], 'popia')->jurisdiction());
    }

    public function test_supported_jurisdictions_are_derived_from_the_pattern_sets(): void
    {
        $this->assertContains('popia', PiiScanService::supportedJurisdictions('phone'));
        $this->assertContains('gdpr', PiiScanService::supportedJurisdictions('phone'));
        // gdpr has no single national-ID format, so it is absent from that set.
        $this->assertNotContains('gdpr', PiiScanService::supportedJurisdictions('national_id'));
        $this->assertContains('uk_gdpr', PiiScanService::allSupportedJurisdictions());
    }

    /**
     * An unsupported jurisdiction used to hand collectPhones()/collectNationalIds()
     * a key their isset() guards skipped, so the scan returned ZERO phone and
     * ZERO national-ID findings and reported success. Reachable today: the
     * privacy_jurisdiction registry ships ndpa, kenya_dpa and pipeda, none of
     * which either pattern constant carries.
     */
    public function test_an_unsupported_jurisdiction_still_detects_phones_and_ids(): void
    {
        $f = $this->svc([], 'pipeda')->scan('Call 0821234567, ID 8001015009087.');
        $this->assertCount(1, $this->ofType($f, 'phone'));
        $this->assertCount(1, $this->ofType($f, 'national_id'));
    }

    /**
     * Worse than detecting nothing: with the national-ID detector disabled, the
     * jurisdiction-independent credit-card detector inherited the span. A 13-digit
     * SA ID passes Luhn, so the ID was reported AS A PAYMENT CARD at 0.95 - the
     * top confidence in the system - and reached the ROPA as "Payment card numbers".
     */
    public function test_an_unsupported_jurisdiction_does_not_misclassify_an_id_as_a_card(): void
    {
        $f = $this->svc([], 'pipeda')->scan('ID 8001015009087 on file.');
        $this->assertSame([], $this->ofType($f, 'credit_card'));
        $this->assertCount(1, $this->ofType($f, 'national_id'));
    }

    public function test_unsupported_jurisdiction_matches_the_gdpr_union(): void
    {
        $text = 'Call 0821234567, ID 8001015009087.';
        $strip = static fn (array $f) => array_map(static fn ($x) => $x['type'].':'.$x['value'], $f);
        $this->assertSame(
            $strip($this->svc([], 'gdpr')->scan($text)),
            $strip($this->svc([], 'ndpa')->scan($text))
        );
    }

    // =====================================================================
    //  Guards on the validated detectors, so the bands stay meaningful
    // =====================================================================

    public function test_a_card_failing_luhn_is_not_reported(): void
    {
        $this->assertSame([], $this->ofType($this->svc()->scan('Paid with 4539578763621487.'), 'credit_card'));
    }

    public function test_a_card_passing_luhn_is_reported(): void
    {
        $this->assertCount(1, $this->ofType($this->svc()->scan('Paid with 4539578763621486.'), 'credit_card'));
    }

    // =====================================================================
    //  Band vocabulary - the "enumerations free to disagree" guard
    // =====================================================================

    /**
     * Every band bandFor() can emit must exist in RISK_BANDS, which is what the
     * view renders from. Enumerating the bands in two places is what let the
     * controller advertise four while the view rendered three; this fails loudly
     * at test time instead of silently as an unstyled badge at runtime.
     */
    public function test_every_band_the_scanner_can_emit_has_a_colour(): void
    {
        $confidences = [0.0, 0.29, 0.30, 0.55, 0.59, 0.60, 0.80, 0.84, 0.85, 0.90, 0.95, 1.0];
        $types = ['email', 'phone', 'ip', 'national_id', 'credit_card', 'date_of_birth', 'custom_term', 'unknown'];

        foreach ($types as $type) {
            foreach ($confidences as $c) {
                $band = PrivacyController::bandFor($type, $c);
                $this->assertArrayHasKey(
                    $band,
                    PrivacyController::RISK_BANDS,
                    sprintf('bandFor(%s, %.2f) returned "%s", which has no colour', $type, $c, $band)
                );
            }
        }
    }

    public function test_a_custom_term_is_never_banded_as_risk_however_confident(): void
    {
        foreach ([0.0, 0.5, 0.90, 1.0] as $c) {
            $this->assertSame('review', PrivacyController::bandFor('custom_term', $c));
        }
    }

    public function test_confidence_bands_hold_at_their_boundaries(): void
    {
        $this->assertSame('high', PrivacyController::bandFor('email', 0.85));
        $this->assertSame('medium', PrivacyController::bandFor('email', 0.84));
        $this->assertSame('medium', PrivacyController::bandFor('email', 0.60));
        $this->assertSame('low', PrivacyController::bandFor('email', 0.59));
    }
}
