<?php

/**
 * ahg:seed-duplicates - report, and with --apply remove, the rows left by
 * install seeds that reused a stale LAST_INSERT_ID() or re-ran (heratio#1556;
 * the seeds were fixed in v1.164.5, this clears what they left):
 *
 *  - System form templates seeded more than once. Per name, the lowest id is
 *    kept. A copy is merged into it only if the copy is identical (template
 *    columns and its whole field list); its assignments, drafts and
 *    submission-log rows move to the kept template first, then the copy is
 *    deleted (its fields and field mappings go with it). A copy that differs
 *    is reported and left alone. User templates (is_system = 0) are never
 *    touched.
 *  - Landing-page blocks on page ids that do not exist. Blocks on real pages
 *    are never touched.
 *
 * Without --apply nothing is written. Re-running is safe.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedDuplicatesCommand extends Command
{
    protected $signature = 'ahg:seed-duplicates {--apply : Remove what is found (default: report only)}';

    protected $description = 'Report (or remove with --apply) duplicate system form templates and landing-page blocks on missing pages';

    /** Rows that point at a form template, moved to the kept copy. */
    private const TEMPLATE_REFS = ['ahg_form_assignment', 'ahg_form_draft', 'ahg_form_submission_log'];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        [$copies, $differ] = $this->templateCopies();
        $orphans = $this->orphanBlocks();

        foreach ($copies as [$keep, $copy, $refs]) {
            $this->line(sprintf('form template %d "%s" duplicates %d%s', $copy->id, $copy->name, $keep->id,
                $refs ? ' (moves '.implode(', ', array_map(fn ($t, $n) => "{$t}={$n}", array_keys($refs), $refs)).')' : ''));
        }
        foreach ($differ as [$keep, $copy]) {
            $this->warn(sprintf('form template %d "%s" has the name of %d but differs; left alone - review by hand', $copy->id, $copy->name, $keep->id));
        }
        if ($orphans) {
            $pages = collect($orphans)->groupBy('page_id')->map->count();
            $this->line(count($orphans).' landing-page block(s) on pages that do not exist: '
                .$pages->map(fn ($n, $p) => "page {$p} x{$n}")->implode(', '));
        }

        if (! $copies && ! $orphans) {
            $this->info('No seed duplicates found.');

            return self::SUCCESS;
        }
        if (! $apply) {
            $this->warn(sprintf('%d template copy(ies) and %d orphan block(s) would be removed. Re-run with --apply.', count($copies), count($orphans)));

            return self::FAILURE;
        }

        foreach ($copies as [$keep, $copy]) {
            DB::transaction(function () use ($keep, $copy) {
                foreach (self::TEMPLATE_REFS as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->where('template_id', $copy->id)->update(['template_id' => $keep->id]);
                    }
                }
                $fields = DB::table('ahg_form_field')->where('template_id', $copy->id)->pluck('id');
                if (Schema::hasTable('ahg_form_field_mapping')) {
                    DB::table('ahg_form_field_mapping')->whereIn('field_id', $fields)->delete();
                }
                DB::table('ahg_form_field')->where('template_id', $copy->id)->delete();
                DB::table('ahg_form_template')->where('id', $copy->id)->delete();
            });
        }
        if ($orphans) {
            $ids = array_column($orphans, 'id');
            // Children first: a block may nest inside another orphan block.
            DB::transaction(function () use ($ids) {
                DB::table('atom_landing_page_block')->whereIn('parent_block_id', $ids)->update(['parent_block_id' => null]);
                DB::table('atom_landing_page_block')->whereIn('id', $ids)->delete();
            });
        }
        $this->info(sprintf('Removed %d template copy(ies) and %d orphan block(s).', count($copies), count($orphans)));

        return self::SUCCESS;
    }

    /** @return array{0: array<int, array{0: object, 1: object, 2: array<string,int>}>, 1: array<int, array{0: object, 1: object}>} */
    private function templateCopies(): array
    {
        if (! Schema::hasTable('ahg_form_template') || ! Schema::hasTable('ahg_form_field')) {
            return [[], []];
        }
        $copies = [];
        $differ = [];
        $groups = DB::table('ahg_form_template')->where('is_system', 1)->orderBy('id')->get()->groupBy('name');
        foreach ($groups as $group) {
            $keep = $group->first();
            foreach ($group->slice(1) as $copy) {
                if ($this->signature($copy) !== $this->signature($keep)) {
                    $differ[] = [$keep, $copy];

                    continue;
                }
                $refs = [];
                foreach (self::TEMPLATE_REFS as $table) {
                    $n = Schema::hasTable($table) ? DB::table($table)->where('template_id', $copy->id)->count() : 0;
                    if ($n) {
                        $refs[$table] = $n;
                    }
                }
                $copies[] = [$keep, $copy, $refs];
            }
        }

        return [$copies, $differ];
    }

    /** A template and its fields, without ids and timestamps. */
    private function signature(object $template): string
    {
        $t = array_diff_key((array) $template, array_flip(['id', 'created_at', 'updated_at']));
        $fields = DB::table('ahg_form_field')->where('template_id', $template->id)
            ->orderBy('sort_order')->orderBy('field_name')->get()
            ->map(fn ($f) => array_diff_key((array) $f, array_flip(['id', 'template_id', 'created_at', 'updated_at'])))
            ->all();

        return md5(json_encode([$t, $fields]));
    }

    /** Blocks whose page does not exist. */
    private function orphanBlocks(): array
    {
        if (! Schema::hasTable('atom_landing_page_block') || ! Schema::hasTable('atom_landing_page')) {
            return [];
        }

        return DB::table('atom_landing_page_block as b')
            ->leftJoin('atom_landing_page as p', 'p.id', '=', 'b.page_id')
            ->whereNull('p.id')->orderBy('b.id')->get(['b.id', 'b.page_id'])
            ->map(fn ($r) => (array) $r)->all();
    }
}
