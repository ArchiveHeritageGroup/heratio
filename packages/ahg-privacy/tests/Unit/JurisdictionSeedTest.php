<?php

/**
 * JurisdictionSeedTest - the registry seed must cover every jurisdiction the
 * product advertises.
 *
 * privacy_jurisdiction decides what an operator can SELECT as their
 * jurisdiction, because PiiScanService::jurisdictionOptions() reads it with
 * is_active = 1. It does not decide what the scanner can detect. So a regime
 * the UI offers but the seed omits is a dead end: the menu names it, the
 * settings form cannot choose it.
 *
 * That is not hypothetical. The privacy dashboard has always listed six
 * regimes; install.sql seeded five, two of them inactive, and ndpa and
 * kenya_dpa existed only in the heratio-dev database where someone had added
 * them by hand. On the one instance anyone tested the registry looked right,
 * so a fresh install advertising Nigeria and Kenya - both named target markets
 * - and offering neither went unnoticed until v1.154.712.
 *
 * Pure file parsing: no DB, no container, so it runs in CI, which builds its
 * test database from database/core/*.sql alone and would never see this table.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems. AGPL-3.0-or-later.
 */

namespace AhgPrivacy\Tests\Unit;

use PHPUnit\Framework\TestCase;

class JurisdictionSeedTest extends TestCase
{
    private const SEED = __DIR__.'/../../database/install.sql';

    private const DASHBOARD = __DIR__.'/../../resources/views/dashboard.blade.php';

    /** Codes seeded with is_active = 1. */
    private function seededActiveCodes(): array
    {
        $sql = (string) file_get_contents(self::SEED);

        $ok = preg_match(
            '/INSERT IGNORE INTO `privacy_jurisdiction`.*?VALUES\n(.*?)\nON DUPLICATE/s',
            $sql,
            $m
        );
        $this->assertSame(1, $ok, 'privacy_jurisdiction seed INSERT not found in install.sql');

        $codes = [];
        foreach (explode("\n", $m[1]) as $line) {
            // ('code', 'name', ... , is_active, sort_order)
            if (preg_match("/^\('([a-z_]+)'.*?,\s*(\d+),\s*(\d+)\)[,)]?$/", trim($line), $row)) {
                if ($row[2] === '1') {
                    $codes[] = $row[1];
                }
            }
        }

        return $codes;
    }

    /** Codes the privacy dashboard menu offers. */
    private function advertisedCodes(): array
    {
        preg_match_all(
            "/'jurisdiction'\s*=>\s*'([a-z_]+)'/",
            (string) file_get_contents(self::DASHBOARD),
            $m
        );

        return array_values(array_unique($m[1]));
    }

    /**
     * Guards the two regexes above. If the dashboard is ever made
     * registry-driven, or the seed reformatted, the parse returns nothing and
     * the real assertion would pass vacuously - a test that quietly stops
     * testing. Fail loudly instead so someone updates it deliberately.
     */
    public function test_the_files_this_test_reads_still_have_the_shape_it_expects(): void
    {
        $this->assertGreaterThanOrEqual(
            2,
            count($this->advertisedCodes()),
            'Dashboard menu no longer lists jurisdictions in the expected shape. If it now reads the registry, delete this test class; otherwise update advertisedCodes().'
        );

        $this->assertGreaterThanOrEqual(
            2,
            count($this->seededActiveCodes()),
            'install.sql jurisdiction seed no longer parses. Update seededActiveCodes().'
        );
    }

    public function test_every_advertised_jurisdiction_is_seeded_and_active(): void
    {
        $seeded = $this->seededActiveCodes();

        foreach ($this->advertisedCodes() as $code) {
            $this->assertContains(
                $code,
                $seeded,
                "The privacy dashboard offers '{$code}' but install.sql does not seed it as an active jurisdiction, so a fresh install advertises a regime nobody can select."
            );
        }
    }

    public function test_gdpr_is_seeded_active_because_the_scanner_defaults_to_it(): void
    {
        $this->assertContains('gdpr', $this->seededActiveCodes());
    }
}
