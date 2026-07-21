<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #1411 - repair + harden authority-to-item links.
 *
 * The AuthorityControlService dedup guard transposed its two id columns, so it
 * almost never matched; the duplicate INSERT then hit the pre-existing unique
 * index `item_authority` (library_item_id, authority_id) and THREW on every
 * re-link. The service is now fixed to guard on that pair. This migration:
 *   1. ensures the (item, authority) pair unique index exists (the real invariant),
 *   2. drops the redundant (item, authority, source_tag) index if an earlier build
 *      added one, and
 *   3. recomputes library_subject_authority.linked_count from the links.
 * Idempotent and guarded - safe on installs without these tables.
 */
return new class extends Migration
{
    private function hasIndex(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    public function up(): void
    {
        if (! Schema::hasTable('library_item_authority_link')) {
            return;
        }

        // 1. Ensure the (item, authority) pair is structurally unique. Only touch
        //    the data if the index is missing (dedupe by pair, keep lowest id).
        if (! $this->hasIndex('library_item_authority_link', 'item_authority')) {
            DB::statement('
                DELETE l FROM library_item_authority_link l
                JOIN (
                    SELECT MIN(id) AS keep_id, library_item_id, authority_id
                    FROM library_item_authority_link
                    GROUP BY library_item_id, authority_id
                ) k
                  ON l.library_item_id = k.library_item_id
                 AND l.authority_id   = k.authority_id
                WHERE l.id <> k.keep_id
            ');
            try {
                DB::statement('ALTER TABLE library_item_authority_link
                    ADD UNIQUE KEY item_authority (library_item_id, authority_id)');
            } catch (\Throwable $e) {
                // non-fatal
            }
        }

        // 2. Drop the redundant triple index if an earlier build added it (the pair
        //    index above is stricter, so this only ever confused readers).
        if ($this->hasIndex('library_item_authority_link', 'uq_item_authority_tag')) {
            try {
                DB::statement('ALTER TABLE library_item_authority_link DROP INDEX uq_item_authority_tag');
            } catch (\Throwable $e) {
                // non-fatal
            }
        }

        // 3. Recompute linked_count = number of items linked per authority.
        if (Schema::hasTable('library_subject_authority')) {
            DB::statement('
                UPDATE library_subject_authority sa
                LEFT JOIN (
                    SELECT authority_id, COUNT(*) AS cnt
                    FROM library_item_authority_link
                    GROUP BY authority_id
                ) c ON c.authority_id = sa.id
                SET sa.linked_count = COALESCE(c.cnt, 0)
            ');
        }
    }

    public function down(): void
    {
        // The pair index `item_authority` predates this migration; nothing to
        // reverse. (Drop the redundant triple index here too, in case a prior
        // build of this migration created it before this correction.)
        if (Schema::hasTable('library_item_authority_link')
            && $this->hasIndex('library_item_authority_link', 'uq_item_authority_tag')) {
            try {
                DB::statement('ALTER TABLE library_item_authority_link DROP INDEX uq_item_authority_tag');
            } catch (\Throwable $e) {
                // non-fatal
            }
        }
    }
};
