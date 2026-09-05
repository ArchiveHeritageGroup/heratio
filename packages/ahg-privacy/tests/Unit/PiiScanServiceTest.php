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

    // ── an incomplete scan is not a clean scan ───────────────────────────

    public function test_a_completed_scan_reports_no_errors(): void
    {
        $svc = new PiiScanService('gdpr', []);
        $svc->scan('Contact a.person@example.test about the 1975 accession.');

        $this->assertTrue($svc->lastScanWasComplete());
        $this->assertSame([], $svc->scanErrors());
    }

    public function test_scan_errors_reset_between_scans(): void
    {
        $svc = new PiiScanService('gdpr', []);
        $svc->scan('nothing of interest here');
        $this->assertSame([], $svc->scanErrors());
        $svc->scan('still nothing');
        $this->assertSame([], $svc->scanErrors());
    }

    /**
     * The distinction the guard exists for. preg_match_all returns 0 when it
     * found nothing and false when it could not run, and as booleans those are
     * the same value - so `if (! preg_match_all(...)) return;` reported a clean
     * record for a scan that never happened.
     *
     * Failure is provoked with a pattern that cannot compile rather than one
     * that backtracks: PCRE2 in PHP 8.3 optimises the classic catastrophic
     * cases away, so they return a genuine 0 and prove nothing. The return
     * value is what the guard tests, and it is false either way.
     */
    public function test_engine_failure_and_no_match_are_indistinguishable_as_booleans(): void
    {
        $failed = @preg_match_all('/[unterminated/', 'anything', $m1);
        $noMatch = preg_match_all('/\bzzzz\b/', 'nothing here', $m2);

        $this->assertFalse($failed, 'an unusable pattern returns false');
        $this->assertSame(0, $noMatch, 'a working pattern that matches nothing returns 0');
        $this->assertNotSame($failed, $noMatch, 'the two outcomes differ - but only by type');

        // The trap: identical once coerced, which is how the old guard read them.
        $this->assertSame((bool) $failed, (bool) $noMatch);
        $this->assertFalse((bool) $failed);
    }


    // ── only a passed checksum may assert a category ─────────────────────

    /**
     * A number that fails its own check digit looks like an identifier and is
     * not one. It used to drop to 0.3, band 'low', count toward the risk score
     * and enter categories_of_data on an Article 30 record - a legal document
     * asserting the institution processes national identifiers, on the strength
     * of a value the scanner had itself just disproved.
     */
    public function test_a_failed_checksum_bands_review_however_confident(): void
    {
        foreach ([0.0, 0.3, 0.6, 0.85, 0.95, 1.0] as $confidence) {
            $this->assertSame(
                'review',
                PrivacyController::bandFor('national_id', $confidence, false),
                "a failed checksum must band review at confidence {$confidence}"
            );
        }
    }

    public function test_a_passed_checksum_bands_on_confidence_as_before(): void
    {
        $this->assertSame('high', PrivacyController::bandFor('national_id', 0.95, true));
        $this->assertSame('medium', PrivacyController::bandFor('national_id', 0.60, true));
        $this->assertSame('low', PrivacyController::bandFor('national_id', 0.30, true));
    }

    /**
     * null is "no checksum exists for this jurisdiction", not "it failed".
     * Reading it as a failure would silently mute every jurisdiction that has
     * no validator - which today is most of them.
     */
    public function test_no_checksum_at_all_is_not_treated_as_a_failure(): void
    {
        $this->assertSame('high', PrivacyController::bandFor('national_id', 0.95, null));
        $this->assertSame('high', PrivacyController::bandFor('national_id', 0.95));
    }

    public function test_scanner_records_whether_the_checksum_passed(): void
    {
        $svc = new PiiScanService('popia', []);

        $good = $svc->scan('ID number 8001015001084 on the admission form.');
        $ids = array_values(array_filter($good, fn ($f) => $f['type'] === 'national_id'));
        $this->assertNotEmpty($ids, 'precondition: the valid ID is detected');
        $this->assertTrue($ids[0]['validated']);
        $this->assertSame('high', PrivacyController::bandFor('national_id', $ids[0]['confidence'], $ids[0]['validated']));

        $bad = $svc->scan('ID number 8001015001080 on the admission form.');
        $ids = array_values(array_filter($bad, fn ($f) => $f['type'] === 'national_id'));
        $this->assertNotEmpty($ids, 'a failed checksum is still surfaced, not discarded');
        $this->assertFalse($ids[0]['validated']);
        $this->assertSame('review', PrivacyController::bandFor('national_id', $ids[0]['confidence'], $ids[0]['validated']));
    }

    /**
     * Findings are stored as JSON, so rows written before this change carry no
     * `validated` key at all. They must keep the band they already had rather
     * than all become 'review' the moment the code ships.
     */
    public function test_a_finding_stored_before_this_change_is_unaffected(): void
    {
        $legacy = ['type' => 'national_id', 'confidence' => 0.95];
        $validated = array_key_exists('validated', $legacy) ? $legacy['validated'] : null;

        $this->assertSame('high', PrivacyController::bandFor($legacy['type'], $legacy['confidence'], $validated));
    }

    // ── Luhn is a typo check, not proof of cardness ──────────────────────

    /**
     * The record that found this: information_object_i18n 906002 reads
     * "Barcode 123 1764827606 barcode". Thirteen digits, and it passes Luhn by
     * coincidence - one check digit means roughly one in ten arbitrary digit
     * runs does. It was reported as a payment card at 0.95, entered the
     * catalogue scan's categories as "Payment card numbers", and from there
     * fed both the Article 30 draft and a RetentionProposal that persists
     * automatically for a DPO to accept.
     */
    public function test_a_barcode_that_passes_luhn_by_chance_cannot_assert(): void
    {
        $svc = new PiiScanService('popia', []);
        $cards = array_values(array_filter(
            $svc->scan('Barcode 123 1764827606 barcode'),
            fn ($f) => $f['type'] === 'credit_card'
        ));

        $this->assertNotEmpty($cards, 'still surfaced - a reviewer should see a card-shaped number');
        $this->assertFalse($cards[0]['validated'], 'no issuer prefix, so it may not assert');
        $this->assertSame('review', PrivacyController::bandFor('credit_card', $cards[0]['confidence'], $cards[0]['validated']));
    }

    /**
     * Published vendor test numbers, not values this implementation produced -
     * testing a checksum against its own output proves nothing.
     */
    public static function publishedCardNumbers(): array
    {
        return [
            'Visa 16' => ['4111 1111 1111 1111'],
            'Visa alt' => ['4012 8888 8888 1881'],
            'Mastercard' => ['5555 5555 5555 4444'],
            'Mastercard 2-series' => ['2223 0031 2200 3222'],
            'Amex' => ['3782 822463 10005'],
            'Discover' => ['6011 1111 1111 1117'],
            'JCB' => ['3530 1113 3330 0000'],
            'Diners' => ['3056 930902 5904'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('publishedCardNumbers')]
    public function test_a_genuine_card_still_asserts(string $number): void
    {
        $svc = new PiiScanService('popia', []);
        $cards = array_values(array_filter(
            $svc->scan("Payment reference {$number} on file"),
            fn ($f) => $f['type'] === 'credit_card'
        ));

        $this->assertNotEmpty($cards, "{$number} should be detected");
        $this->assertTrue($cards[0]['validated'], "{$number} carries a real issuer prefix");
        $this->assertSame('high', PrivacyController::bandFor('credit_card', $cards[0]['confidence'], $cards[0]['validated']));
    }

    public function test_a_number_that_fails_luhn_is_not_reported_at_all(): void
    {
        $svc = new PiiScanService('popia', []);
        $cards = array_filter(
            $svc->scan('Reference 4111 1111 1111 1112 in the file'),
            fn ($f) => $f['type'] === 'credit_card'
        );

        $this->assertSame([], $cards, 'a failed check digit is not card-shaped enough to mention');
    }

    // ── an identity number carries a date of birth ───────────────────────

    /**
     * Found by scanning the real catalogue: "Demo DO Fonds 1784035271814".
     * That is a millisecond epoch timestamp - 2026-07-14 13:21:11 - and it was
     * reported as a VALIDATED South African identity number at 0.90, which
     * drafted a "National identifiers" retention proposal. Its first six digits
     * are 178403, and there is no month 84. The validator checked length and
     * Luhn and nothing else, and a timestamp is thirteen digits that passes
     * Luhn about one time in ten. This catalogue had two.
     */
    public function test_an_epoch_timestamp_is_not_a_validated_identity_number(): void
    {
        $svc = new PiiScanService('popia', []);

        foreach (['1784035271814', '1784038368393'] as $timestamp) {
            $ids = array_values(array_filter(
                $svc->scan("Demo DO Fonds {$timestamp}"),
                fn ($f) => $f['type'] === 'national_id'
            ));

            $this->assertNotEmpty($ids, 'still surfaced for a reviewer');
            $this->assertFalse($ids[0]['validated'], "{$timestamp} has no valid date of birth in it");
            $this->assertSame('review', PrivacyController::bandFor('national_id', $ids[0]['confidence'], $ids[0]['validated']));
        }
    }

    public function test_a_real_shaped_identity_number_still_validates(): void
    {
        $svc = new PiiScanService('popia', []);
        $ids = array_values(array_filter(
            $svc->scan('ID 8001015001084 on the form'),
            fn ($f) => $f['type'] === 'national_id'
        ));

        $this->assertNotEmpty($ids);
        $this->assertTrue($ids[0]['validated'], '800101 is 1980-01-01, a real date');
    }

    /**
     * The seeded demonstration identity numbers must keep validating. Their
     * date is real (870314) and only the citizenship digit is impossible, which
     * is deliberately not checked - rejecting on it would collapse the very
     * confidence gate ahg:privacy-seed-demo-pii exists to show.
     */
    public function test_the_demonstration_identity_numbers_are_unaffected(): void
    {
        $svc = new PiiScanService('popia', []);
        $ids = array_values(array_filter(
            $svc->scan('Identity number 8703145127289 in the register'),
            fn ($f) => $f['type'] === 'national_id'
        ));

        $this->assertNotEmpty($ids);
        $this->assertTrue($ids[0]['validated']);
    }

    /** 29 February is valid in one century and not the other, so accept either. */
    public function test_a_leap_day_birth_date_is_not_rejected_on_century_ambiguity(): void
    {
        $svc = new PiiScanService('popia', []);
        // 000229 - 2000 was a leap year, 1900 was not.
        $found = false;
        for ($check = 0; $check <= 9; $check++) {
            $candidate = '000229500108'.$check;
            $ids = array_values(array_filter(
                $svc->scan("ID {$candidate} here"),
                fn ($f) => $f['type'] === 'national_id'
            ));
            if ($ids && $ids[0]['validated']) { $found = true; break; }
        }
        $this->assertTrue($found, 'a 29 February date valid in 2000 must not be rejected');
    }

    // ── a date without context is not a date of birth ────────────────────

    /**
     * The record that found this: "WhatsApp Image 2026-07-10 at 09.27.44".
     * A filename. It was read as a date of birth and put "Date of birth" on an
     * Article 30 draft. Nothing about a bare date says birth rather than
     * accession, exposure or hearing - an archive is made of dates - and the
     * existing year bound could not help, because that one is in the past.
     */
    public function test_a_bare_date_cannot_assert(): void
    {
        $svc = new PiiScanService('popia', []);

        foreach ([
            'WhatsApp Image 2026-07-10 at 09.27.44',
            'Accession received 2019-03-04 from the estate',
            'Photograph exposed 1961-08-19, Kimberley',
        ] as $text) {
            $dates = array_values(array_filter($svc->scan($text), fn ($f) => $f['type'] === 'date_of_birth'));
            $this->assertNotEmpty($dates, "still surfaced: {$text}");
            $this->assertFalse($dates[0]['validated'], "no birth context in: {$text}");
            $this->assertSame('review', PrivacyController::bandFor('date_of_birth', $dates[0]['confidence'], $dates[0]['validated']));
        }
    }

    public function test_a_date_with_birth_context_still_asserts(): void
    {
        $svc = new PiiScanService('popia', []);

        foreach (['Subject born 1954-11-02 in Kimberley', 'Date of birth: 1954-11-02', 'D.O.B 1954-11-02'] as $text) {
            $dates = array_values(array_filter($svc->scan($text), fn ($f) => $f['type'] === 'date_of_birth'));
            $this->assertNotEmpty($dates, $text);
            $this->assertTrue($dates[0]['validated'], $text);
        }
    }

    /**
     * The gate matches whole words, never substrings. A sibling instance had a
     * financial gate keyed on 'ref' with a substring match, so it fired inside
     * 'reference' - a word in nearly every archival description - and the gate
     * meant to suppress false positives was open almost always.
     */
    public function test_the_gate_does_not_fire_on_a_substring(): void
    {
        $svc = new PiiScanService('popia', []);
        // "rebirth" and "birthplace" contain "birth"; only the whole word counts.
        $dates = array_values(array_filter(
            $svc->scan('Catalogue of the Rebirth Collection, item dated 1961-08-19'),
            fn ($f) => $f['type'] === 'date_of_birth'
        ));

        $this->assertNotEmpty($dates);
        $this->assertFalse($dates[0]['validated'], '"Rebirth" is not a claim about anyone being born');
    }

    /** The window follows the match, so a mention far away cannot legitimise it. */
    public function test_context_far_from_the_match_does_not_count(): void
    {
        $svc = new PiiScanService('popia', []);
        $text = 'Birth register of the parish. '.str_repeat('Further correspondence follows. ', 6).'Item dated 1961-08-19.';
        $dates = array_values(array_filter($svc->scan($text), fn ($f) => $f['type'] === 'date_of_birth'));

        $this->assertNotEmpty($dates);
        $this->assertFalse($dates[0]['validated'], 'the word is in the field but nowhere near the date');
    }
}
