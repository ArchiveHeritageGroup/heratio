<?php

/**
 * ahg:term-object-collisions - find, and with --apply repair, terms that do
 * not own their object row (heratio#1556).
 *
 * term.id references object.id ON DELETE CASCADE, so a term whose id belongs
 * to another object (a taxonomy, a digital object) is deleted, with all its
 * i18n, closure and usage rows, when that object is deleted. Seed SQL that
 * read a stale LAST_INSERT_ID() created three such level-of-description terms
 * (500 Object, 512 Installation, 1752 Specimen).
 *
 * The repair moves each such term to a new object row of its own: insert
 * object + term with the new id, repoint every foreign key that references
 * term.id plus level_of_description_sector.term_id (no FK), give the term a
 * slug, then delete the old term row. The old object row is left alone; it
 * belongs to the taxonomy or digital object. Each term is repaired in its own
 * transaction. Without --apply nothing is written. Re-running is safe: a
 * repaired term no longer matches.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TermObjectCollisionsCommand extends Command
{
    protected $signature = 'ahg:term-object-collisions {--apply : Repair the terms found (default: report only)}';

    protected $description = 'Report (or repair with --apply) terms whose object row is missing or belongs to another kind of object';

    /** Term references that have no foreign key. */
    private const NON_FK_REFERENCES = [['level_of_description_sector', 'term_id']];

    public function handle(): int
    {
        $found = self::collisions();
        if ($found === []) {
            $this->info('No term collisions: every term owns its object row.');

            return self::SUCCESS;
        }

        $refs = $this->references();
        foreach ($found as $t) {
            $uses = [];
            foreach ($refs as [$table, $column]) {
                if ($table === 'term' && $column === 'id') {
                    continue;
                }
                $n = DB::table($table)->where($column, $t->id)->count();
                if ($n) {
                    $uses[] = "{$table}.{$column}={$n}";
                }
            }
            $this->line(sprintf('term %d "%s" (taxonomy %d): object row is %s; used by %s',
                $t->id, $t->name ?? '', $t->taxonomy_id, $t->class_name ?? 'MISSING', $uses ? implode(', ', $uses) : 'nothing'));
        }

        if (! $this->option('apply')) {
            $this->warn(count($found).' term(s) need repair. Re-run with --apply to move them to their own object rows.');

            return self::FAILURE;
        }

        foreach ($found as $t) {
            $new = $this->repair((int) $t->id, $refs);
            $this->info("term {$t->id} -> {$new}");
        }
        \Illuminate\Support\Facades\Cache::flush();
        $this->info('Done. Reindex terms (php artisan ahg:es-reindex --index=term) so search uses the new ids.');

        return self::SUCCESS;
    }

    /** Terms whose object row is missing or is not a QubitTerm. */
    public static function collisions(): array
    {
        return DB::select("SELECT t.id, t.taxonomy_id, o.class_name,
                (SELECT name FROM term_i18n i WHERE i.id = t.id ORDER BY i.culture = t.source_culture DESC LIMIT 1) AS name
            FROM term t LEFT JOIN object o ON o.id = t.id
            WHERE o.id IS NULL OR o.class_name <> 'QubitTerm' ORDER BY t.id");
    }

    /** Every [table, column] that references term.id. */
    private function references(): array
    {
        $fks = DB::select("SELECT table_name AS t, column_name AS c FROM information_schema.key_column_usage
            WHERE table_schema = DATABASE() AND referenced_table_name = 'term' AND referenced_column_name = 'id'");
        $refs = array_map(fn ($r) => [$r->t, $r->c], $fks);
        foreach (self::NON_FK_REFERENCES as [$table, $column]) {
            if (\Illuminate\Support\Facades\Schema::hasColumn($table, $column)) {
                $refs[] = [$table, $column];
            }
        }

        return $refs;
    }

    private function repair(int $old, array $refs): int
    {
        return DB::transaction(function () use ($old, $refs) {
            $term = DB::table('term')->where('id', $old)->lockForUpdate()->first();
            $new = (int) DB::table('object')->insertGetId([
                'class_name' => 'QubitTerm', 'created_at' => now(), 'updated_at' => now(), 'serial_number' => 0,
            ]);
            DB::table('term')->insert(array_merge((array) $term, ['id' => $new]));

            foreach ($refs as [$table, $column]) {
                if ($table === 'term' && $column === 'id') {
                    continue;
                }
                DB::table($table)->where($column, $old)->update([$column => $new]);
            }

            $name = DB::table('term_i18n')->where('id', $new)->orderByRaw('culture = ? DESC', [$term->source_culture])->value('name');
            // Level terms are seeded as level-<name> (packages' install.sql).
            $base = ((int) $term->taxonomy_id === 34 ? 'level-' : '').(Str::slug((string) $name) ?: 'term');
            $slug = $base;
            for ($i = 2; DB::table('slug')->where('slug', $slug)->exists(); $i++) {
                $slug = $base.'-'.$i;
            }
            DB::table('slug')->insert(['object_id' => $new, 'slug' => $slug, 'serial_number' => 0]);

            // Only the term row goes; the object row (and its slug) belong to
            // the taxonomy or digital object that owns the id.
            DB::table('term')->where('id', $old)->delete();

            return $new;
        });
    }
}
