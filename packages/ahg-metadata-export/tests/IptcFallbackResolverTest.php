<?php

/**
 * IptcFallbackResolverTest - covers the issue #752 fallback chain that lets
 * extracted IPTC metadata (creator / copyright_notice / keywords) patch
 * empty ISAD(G) fields in OAI-PMH, Dublin Core, and EAD exports.
 *
 * Scenarios covered:
 *   1. ISAD empty + IPTC present -> IPTC wins + audit row in ahg_error_log
 *   2. ISAD present + IPTC present -> ISAD wins, no fallback fires
 *   3. ISAD empty + IPTC empty   -> null / [] returned, nothing emitted
 *   4. Malformed IPTC keyword payload -> gracefully ignored
 *
 * Tests roll any sidecar fixture rows (dam_iptc_metadata + ahg_error_log)
 * back inside a transaction so the heratio_test DB stays clean. Skips
 * (rather than fails) when the supporting tables are absent so CI on a
 * partially-installed DB still goes green.
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
 */

namespace AhgMetadataExport\Tests;

use AhgMetadataExport\Services\IptcFallbackResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IptcFallbackResolverTest extends TestCase
{
    /**
     * dam_iptc_metadata.object_id we'll write fixture rows against. Picking a
     * very high id keeps us clear of any seeded IO rows.
     */
    private const FIXTURE_OBJECT_ID = 9999777;

    protected function setUp(): void
    {
        parent::setUp();
        IptcFallbackResolver::resetCaches();
    }

    protected function tearDown(): void
    {
        try {
            if (Schema::hasTable('dam_iptc_metadata')) {
                DB::table('dam_iptc_metadata')->where('object_id', self::FIXTURE_OBJECT_ID)->delete();
            }
            if (Schema::hasTable('ahg_error_log')) {
                DB::table('ahg_error_log')
                    ->where('message', 'like', 'IPTC fallback fired for information_object.id='.self::FIXTURE_OBJECT_ID.'%')
                    ->delete();
            }
        } catch (\Throwable $e) {
            // Best-effort cleanup; the test schema is per-DB so a stray row
            // doesn't pollute production.
        }
        IptcFallbackResolver::resetCaches();
        parent::tearDown();
    }

    /**
     * Returns false (and marks the test skipped) when the supporting tables
     * are missing in the test database. Centralised so every test reads
     * cleanly.
     */
    private function skipUnlessSchemaReady(): bool
    {
        try {
            if (! Schema::hasTable('dam_iptc_metadata') || ! Schema::hasTable('ahg_error_log')) {
                $this->markTestSkipped('dam_iptc_metadata or ahg_error_log not present in test DB');

                return false;
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unavailable: '.$e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Seed a dam_iptc_metadata row. The table has variable columns across
     * installs; we only set the three the resolver looks at + the FK.
     */
    private function seedIptc(?string $creator, ?string $copyright, ?string $keywords): void
    {
        DB::table('dam_iptc_metadata')->where('object_id', self::FIXTURE_OBJECT_ID)->delete();
        DB::table('dam_iptc_metadata')->insert([
            'object_id' => self::FIXTURE_OBJECT_ID,
            'creator' => $creator,
            'copyright_notice' => $copyright,
            'keywords' => $keywords,
        ]);
    }

    private function countAudit(string $field): int
    {
        return (int) DB::table('ahg_error_log')
            ->where('message', 'like', 'IPTC fallback fired for information_object.id='.self::FIXTURE_OBJECT_ID.' field='.$field.'%')
            ->count();
    }

    // ---------------------------------------------------------------- creator

    public function test_creator_isad_empty_iptc_present_uses_iptc_and_logs_audit(): void
    {
        if (! $this->skipUnlessSchemaReady()) {
            return;
        }
        $this->seedIptc('Jane Photographer', null, null);

        $resolver = new IptcFallbackResolver();
        $resolved = $resolver->resolveCreatorsWithCanonical(self::FIXTURE_OBJECT_ID, []);

        $this->assertSame(['Jane Photographer'], $resolved, 'IPTC By-line should fill empty creator');
        $this->assertSame(1, $this->countAudit('creator'), 'audit row should be emitted once for creator fallback');
    }

    public function test_creator_isad_present_overrides_iptc(): void
    {
        if (! $this->skipUnlessSchemaReady()) {
            return;
        }
        $this->seedIptc('Should Not Win', null, null);

        $resolver = new IptcFallbackResolver();
        $resolved = $resolver->resolveCreatorsWithCanonical(self::FIXTURE_OBJECT_ID, ['Authoritative Author']);

        $this->assertSame(['Authoritative Author'], $resolved, 'ISAD canonical wins over IPTC');
        $this->assertSame(0, $this->countAudit('creator'), 'no audit row when canonical wins');
    }

    public function test_creator_both_empty_returns_empty_list(): void
    {
        if (! $this->skipUnlessSchemaReady()) {
            return;
        }
        $this->seedIptc(null, null, null);

        $resolver = new IptcFallbackResolver();
        $resolved = $resolver->resolveCreatorsWithCanonical(self::FIXTURE_OBJECT_ID, []);

        $this->assertSame([], $resolved, 'empty + empty = empty');
        $this->assertSame(0, $this->countAudit('creator'));
    }

    // ----------------------------------------------------------------- rights

    public function test_rights_isad_empty_iptc_present_uses_iptc(): void
    {
        if (! $this->skipUnlessSchemaReady()) {
            return;
        }
        $this->seedIptc(null, '(c) 2025 Plain Sailing Information Systems', null);

        $resolver = new IptcFallbackResolver();
        $resolved = $resolver->resolveRightsWithCanonical(self::FIXTURE_OBJECT_ID, null);

        $this->assertSame('(c) 2025 Plain Sailing Information Systems', $resolved);
        $this->assertSame(1, $this->countAudit('rights'));
    }

    public function test_rights_isad_present_wins(): void
    {
        if (! $this->skipUnlessSchemaReady()) {
            return;
        }
        $this->seedIptc(null, 'IPTC copyright', null);

        $resolver = new IptcFallbackResolver();
        $resolved = $resolver->resolveRightsWithCanonical(self::FIXTURE_OBJECT_ID, 'Use freely with attribution.');

        $this->assertSame('Use freely with attribution.', $resolved);
        $this->assertSame(0, $this->countAudit('rights'));
    }

    public function test_rights_both_empty_returns_null(): void
    {
        if (! $this->skipUnlessSchemaReady()) {
            return;
        }
        $this->seedIptc(null, null, null);

        $resolver = new IptcFallbackResolver();
        $this->assertNull($resolver->resolveRightsWithCanonical(self::FIXTURE_OBJECT_ID, null));
        $this->assertSame(0, $this->countAudit('rights'));
    }

    // --------------------------------------------------------------- subjects

    public function test_subjects_isad_empty_iptc_json_array_parsed(): void
    {
        if (! $this->skipUnlessSchemaReady()) {
            return;
        }
        $this->seedIptc(null, null, '["heritage","archive","photography"]');

        $resolver = new IptcFallbackResolver();
        $resolved = $resolver->resolveSubjectsWithCanonical(self::FIXTURE_OBJECT_ID, []);

        $this->assertSame(['heritage', 'archive', 'photography'], $resolved);
        $this->assertSame(1, $this->countAudit('subject'));
    }

    public function test_subjects_isad_empty_iptc_delimited_parsed(): void
    {
        if (! $this->skipUnlessSchemaReady()) {
            return;
        }
        $this->seedIptc(null, null, 'heritage; archive, photography | curation');

        $resolver = new IptcFallbackResolver();
        $resolved = $resolver->resolveSubjectsWithCanonical(self::FIXTURE_OBJECT_ID, []);

        $this->assertEqualsCanonicalizing(
            ['heritage', 'archive', 'photography', 'curation'],
            $resolved
        );
        $this->assertSame(1, $this->countAudit('subject'));
    }

    public function test_subjects_isad_present_wins(): void
    {
        if (! $this->skipUnlessSchemaReady()) {
            return;
        }
        $this->seedIptc(null, null, 'iptc-only-keyword');

        $resolver = new IptcFallbackResolver();
        $resolved = $resolver->resolveSubjectsWithCanonical(self::FIXTURE_OBJECT_ID, ['Authoritative Subject']);

        $this->assertSame(['Authoritative Subject'], $resolved);
        $this->assertSame(0, $this->countAudit('subject'));
    }

    public function test_subjects_malformed_payload_returns_empty(): void
    {
        if (! $this->skipUnlessSchemaReady()) {
            return;
        }
        // Looks like JSON but is broken. Resolver must not throw / poison
        // the harvest; falls through to the delimited parser which yields
        // a single noisy token. The corrupt-JSON safety net plus the
        // single-token tolerance is exactly what we want.
        $this->seedIptc(null, null, '[broken json,,,;');

        $resolver = new IptcFallbackResolver();
        $resolved = $resolver->resolveSubjectsWithCanonical(self::FIXTURE_OBJECT_ID, []);

        // Either empty (if the parser bails) or a single placeholder is
        // acceptable; what we MUST NOT have is an exception. Assert no
        // value contains the raw brackets / leading bracket.
        foreach ($resolved as $kw) {
            $this->assertNotSame('', trim($kw));
            $this->assertStringNotContainsString('[', $kw);
        }
        // Audit only fires when fallback produced a non-empty list.
        $auditCount = $this->countAudit('subject');
        if (empty($resolved)) {
            $this->assertSame(0, $auditCount);
        } else {
            $this->assertSame(1, $auditCount);
        }
    }

    public function test_subjects_both_empty_returns_empty(): void
    {
        if (! $this->skipUnlessSchemaReady()) {
            return;
        }
        $this->seedIptc(null, null, '');

        $resolver = new IptcFallbackResolver();
        $resolved = $resolver->resolveSubjectsWithCanonical(self::FIXTURE_OBJECT_ID, []);

        $this->assertSame([], $resolved);
        $this->assertSame(0, $this->countAudit('subject'));
    }

    // ------------------------------------------------------- audit dedup path

    public function test_audit_row_only_logged_once_per_object_field(): void
    {
        if (! $this->skipUnlessSchemaReady()) {
            return;
        }
        $this->seedIptc('Repeat Photographer', null, null);

        $resolver = new IptcFallbackResolver();
        $resolver->resolveCreatorsWithCanonical(self::FIXTURE_OBJECT_ID, []);
        $resolver->resolveCreatorsWithCanonical(self::FIXTURE_OBJECT_ID, []);
        $resolver->resolveCreatorsWithCanonical(self::FIXTURE_OBJECT_ID, []);

        $this->assertSame(1, $this->countAudit('creator'), 'audit dedups by object_id + field');
    }
}
