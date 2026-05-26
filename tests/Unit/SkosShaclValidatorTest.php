<?php

/**
 * Heratio - Unit tests for the minimal SKOS SHACL validator.
 *
 * (c) 2026 Johan Pieterse / Plain Sailing iSystems / The Archive and
 * Heritage Group (Pty) Ltd. Released under the AGPL-3.0-or-later licence.
 *
 * Covers the four rules from packages/ahg-term-taxonomy/resources/shacl/
 * skos-shapes.ttl. No DB - the validator works purely on in-memory
 * concept dicts so tests run without a Laravel application.
 *
 * #661 Phase 3.
 */

namespace Tests\Unit;

use AhgTermTaxonomy\Validation\ShaclValidator;
use PHPUnit\Framework\TestCase;

class SkosShaclValidatorTest extends TestCase
{
    public function test_clean_taxonomy_produces_no_violations(): void
    {
        $v = new ShaclValidator();
        $reports = $v->validate([
            [
                'id' => 1, 'uri' => 'http://h/term/a', 'prefLabel' => 'Archives',
                'broader' => null, 'altLabels' => [],
            ],
            [
                'id' => 2, 'uri' => 'http://h/term/b', 'prefLabel' => 'Repositories',
                'broader' => 'http://h/term/a', 'altLabels' => [],
            ],
        ]);
        $this->assertSame([], $reports);
    }

    public function test_s1_concept_without_prefLabel_violates(): void
    {
        $v = new ShaclValidator();
        $reports = $v->validate([
            ['id' => 1, 'uri' => 'http://h/term/empty', 'prefLabel' => '', 'broader' => null, 'altLabels' => []],
        ]);
        $this->assertCount(1, $reports);
        $this->assertSame('S1-MinPrefLabel', $reports[0]['shape']);
    }

    public function test_s2_multiple_prefLabels_same_language_violates(): void
    {
        $v = new ShaclValidator();
        $reports = $v->validate([
            [
                'id' => 1,
                'uri' => 'http://h/term/dup',
                'prefLabel' => 'Archives',
                'prefLabels' => ['en' => ['Archives', 'Arkivalia']],
                'broader' => null,
                'altLabels' => [],
            ],
        ]);
        $shapes = array_column($reports, 'shape');
        $this->assertContains('S2-UniqueLangPrefLabel', $shapes);
    }

    public function test_s3_pref_and_alt_share_literal_violates(): void
    {
        $v = new ShaclValidator();
        $reports = $v->validate([
            [
                'id' => 1,
                'uri' => 'http://h/term/collide',
                'prefLabel' => 'Archives',
                'broader' => null,
                'altLabels' => [['lang' => 'en', 'name' => 'Archives']],
            ],
        ]);
        $shapes = array_column($reports, 'shape');
        $this->assertContains('S3-PrefAltDisjoint', $shapes);
    }

    public function test_s4_broader_cycle_is_caught(): void
    {
        // a -> b -> c -> a
        $v = new ShaclValidator();
        $reports = $v->validate([
            ['id' => 1, 'uri' => 'http://h/term/a', 'prefLabel' => 'A', 'broader' => 'http://h/term/b', 'altLabels' => []],
            ['id' => 2, 'uri' => 'http://h/term/b', 'prefLabel' => 'B', 'broader' => 'http://h/term/c', 'altLabels' => []],
            ['id' => 3, 'uri' => 'http://h/term/c', 'prefLabel' => 'C', 'broader' => 'http://h/term/a', 'altLabels' => []],
        ]);
        $shapes = array_column($reports, 'shape');
        $this->assertContains('S4-NoBroaderCycles', $shapes);
    }

    public function test_s4_acyclic_tree_does_not_violate(): void
    {
        // a -> b -> c (linear, no cycle)
        $v = new ShaclValidator();
        $reports = $v->validate([
            ['id' => 1, 'uri' => 'http://h/term/a', 'prefLabel' => 'A', 'broader' => null, 'altLabels' => []],
            ['id' => 2, 'uri' => 'http://h/term/b', 'prefLabel' => 'B', 'broader' => 'http://h/term/a', 'altLabels' => []],
            ['id' => 3, 'uri' => 'http://h/term/c', 'prefLabel' => 'C', 'broader' => 'http://h/term/b', 'altLabels' => []],
        ]);
        $shapes = array_column($reports, 'shape');
        $this->assertNotContains('S4-NoBroaderCycles', $shapes);
    }
}
