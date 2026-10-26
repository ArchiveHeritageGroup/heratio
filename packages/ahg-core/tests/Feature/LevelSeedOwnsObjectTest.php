<?php

/**
 * LevelSeedOwnsObjectTest - heratio#1556: the level-of-description seed blocks
 * in the museum, library, DAM and gallery install.sql create terms that own a
 * QubitTerm object row, even when the connection's LAST_INSERT_ID() points at
 * some other object, and a second run creates nothing.
 *
 * The blocks are taken from the real files and run on one connection, as the
 * installers do. Each level name gets a unique suffix so the "create" path
 * runs against a database that already has the real levels.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgCore\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LevelSeedOwnsObjectTest extends TestCase
{
    use DatabaseTransactions;

    private const FILES = ['ahg-museum', 'ahg-library', 'ahg-dam', 'ahg-gallery'];

    /** The level-term statements of one install.sql, with names made unique. */
    private function levelStatements(string $package, string $suffix): array
    {
        $sql = (string) file_get_contents(base_path("packages/{$package}/database/install.sql"));
        $sql = implode("\n", array_filter(preg_split('/\r?\n/', $sql), fn ($l) => ! str_starts_with(ltrim($l), '--')));
        preg_match_all("/SET @(\\w+)_exists = \\(SELECT t\\.id FROM term t JOIN term_i18n ti ON t\\.id=ti\\.id WHERE t\\.taxonomy_id=34 AND ti\\.name='([^']+)'/", $sql, $m, PREG_SET_ORDER);
        $vars = array_column($m, 1);
        $names = array_column($m, 2);

        $out = [];
        foreach (array_map('trim', explode(';', $sql)) as $stmt) {
            if ($stmt === '' || ! preg_match('/@('.implode('|', array_map('preg_quote', $vars ?: ['^'])).')_(exists|id)\b/', $stmt)) {
                continue;
            }
            foreach ($names as $name) {
                $stmt = str_replace("'{$name}'", "'{$name} {$suffix}'", $stmt);
            }
            $out[] = preg_replace("/'level-([a-z0-9-]+)'/", "'level-$1-".strtolower($suffix)."'", $stmt);
        }

        return [$out, $names];
    }

    public function test_seeded_levels_own_their_object_rows_and_rerun_is_a_no_op(): void
    {
        $checked = 0;
        foreach (self::FILES as $package) {
            $suffix = 'ZZ'.strtoupper(bin2hex(random_bytes(3)));
            [$statements, $names] = $this->levelStatements($package, $suffix);
            if (! $names) {
                continue; // the gallery file seeds no level of its own
            }

            // Leave LAST_INSERT_ID() pointing at an unrelated object, as an
            // earlier seed statement would.
            DB::table('object')->insertGetId(['class_name' => 'QubitTaxonomy', 'created_at' => now(), 'updated_at' => now()]);

            foreach ($statements as $stmt) {
                DB::statement($stmt);
            }
            foreach ($names as $name) {
                $id = DB::table('term as t')->join('term_i18n as i', 'i.id', '=', 't.id')
                    ->where('t.taxonomy_id', 34)->where('i.name', "{$name} {$suffix}")->value('t.id');
                $this->assertNotNull($id, "{$package}: {$name} was created");
                $this->assertSame('QubitTerm', DB::table('object')->where('id', $id)->value('class_name'), "{$package}: {$name} owns a QubitTerm object");
                $checked++;
            }

            $terms = DB::table('term')->count();
            $objects = DB::table('object')->count();
            foreach ($statements as $stmt) {
                DB::statement($stmt);
            }
            $this->assertSame($terms, DB::table('term')->count(), "{$package}: a re-run creates no terms");
            $this->assertSame($objects, DB::table('object')->count(), "{$package}: a re-run creates no objects");
        }
        $this->assertGreaterThan(10, $checked, 'the seed blocks were found');
    }
}
