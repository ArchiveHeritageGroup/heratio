<?php

/**
 * JurisdictionSeedTest - the registry is the only list of jurisdictions.
 *
 * privacy_jurisdiction decides what an operator can choose, because every menu
 * and select now reads it through PrivacyService::activeJurisdictions(). It
 * does not decide what the scanner can detect; that comes from the pattern
 * constants, and a regime with no market-specific patterns is still offered
 * and still labelled as such.
 *
 * Two failures put this file here, and both were invisible on heratio-dev.
 *
 * The seed shipped five regimes, two of them inactive, while the privacy
 * dashboard and the Spectrum compliance page each hardcoded six and the
 * complaint form four. A fresh install therefore advertised Nigeria and Kenya
 * - both named target markets - and let an operator select neither. It went
 * unnoticed because ndpa and kenya_dpa had been added to the dev database by
 * hand, so on the one instance anyone looked at, the registry was right.
 *
 * The seed also stored emoji flags in `icon`, which the views interpolate into
 * `<span class="fi fi-{icon}">`. Every flag on a fresh install rendered as the
 * dead class `fi fi-🇿🇦`. Dev held ISO codes and looked correct.
 *
 * The pattern in both: a hardcoded copy of something the registry already
 * knows, kept honest only by a database nobody ships. So these tests assert
 * the coupling itself, not a list of codes that would need updating with every
 * new regime.
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

    /** Every jurisdiction code the product knows about. */
    private const CODES = ['popia', 'ndpa', 'kenya_dpa', 'gdpr', 'uk_gdpr', 'pipeda', 'ccpa'];

    /**
     * A view naming this many distinct regimes is listing them, not gating on
     * one. Gating on a single regime is legitimate and common - the PAIA
     * button is South African and correctly checks for popia.
     */
    private const MENU_THRESHOLD = 3;

    /** Seeded rows as code => ['icon' => ..., 'active' => bool]. */
    private function seededRows(): array
    {
        $sql = (string) file_get_contents(self::SEED);

        $ok = preg_match(
            '/INSERT IGNORE INTO `privacy_jurisdiction`.*?VALUES\n(.*?)\nON DUPLICATE/s',
            $sql,
            $m
        );
        $this->assertSame(1, $ok, 'privacy_jurisdiction seed INSERT not found in install.sql');

        $rows = [];
        foreach (explode("\n", $m[1]) as $line) {
            // ('code', ... , 'icon', is_active, sort_order)
            if (preg_match("/^\('([a-z_]+)'.*,\s*'([^']*)',\s*(\d+),\s*(\d+)\)[,)]?$/", trim($line), $row)) {
                $rows[$row[1]] = ['icon' => $row[2], 'active' => $row[3] === '1'];
            }
        }

        return $rows;
    }

    /** @return string[] blade templates in the two privacy-facing packages */
    private function viewFiles(): array
    {
        $files = [];
        foreach ([__DIR__.'/../../resources/views', __DIR__.'/../../../ahg-spectrum/resources/views'] as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($it as $f) {
                if ($f->isFile() && str_ends_with($f->getFilename(), '.blade.php')) {
                    $files[] = $f->getPathname();
                }
            }
        }

        return $files;
    }

    /**
     * Guards the parser below, so a reformatted seed fails loudly instead of
     * turning the real assertions into no-ops.
     */
    public function test_the_seed_still_has_the_shape_this_test_reads(): void
    {
        $this->assertGreaterThanOrEqual(
            2,
            count($this->seededRows()),
            'install.sql jurisdiction seed no longer parses. Update seededRows().'
        );

        $this->assertNotEmpty($this->viewFiles(), 'No blade templates found to scan.');
    }

    /**
     * The drift guard. A view that lists regimes itself is a second registry,
     * and it will disagree with the real one - that is the whole history above.
     */
    public function test_no_view_hardcodes_a_jurisdiction_menu(): void
    {
        foreach ($this->viewFiles() as $file) {
            $body = (string) file_get_contents($file);

            $found = array_values(array_filter(
                self::CODES,
                fn (string $code): bool => (bool) preg_match("/['\"]".preg_quote($code, '/')."['\"]/", $body)
            ));

            $this->assertLessThan(
                self::MENU_THRESHOLD,
                count($found),
                basename($file).' names '.count($found).' jurisdictions directly ('.implode(', ', $found).'). Render the list from PrivacyService::activeJurisdictions() instead, or the registry and this view will drift apart.'
            );
        }
    }

    /**
     * `icon` is an ISO 3166-1 alpha-2 code, not an emoji flag: the views build
     * a flag-icons class from it.
     */
    public function test_seeded_icons_are_iso_alpha_2_codes_not_emoji(): void
    {
        foreach ($this->seededRows() as $code => $row) {
            $this->assertMatchesRegularExpression(
                '/^[a-z]{2}$/',
                $row['icon'],
                "Jurisdiction '{$code}' seeds icon '{$row['icon']}'. The views interpolate this into a flag-icons CSS class, so it must be a two-letter ISO country code."
            );
        }
    }

    /**
     * PrivacyService falls back to popia and gdpr when the table is missing.
     * A fallback naming a regime the seed does not create would hand an
     * operator a choice that vanishes the moment the table appears.
     */
    public function test_the_fallback_regimes_are_seeded_and_active(): void
    {
        $active = array_keys(array_filter($this->seededRows(), fn (array $r): bool => $r['active']));

        $this->assertContains('popia', $active);
        $this->assertContains('gdpr', $active);
    }
}
