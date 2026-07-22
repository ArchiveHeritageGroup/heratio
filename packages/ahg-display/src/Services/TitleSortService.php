<?php

/**
 * Heratio - browse title-sort projection.
 *
 * (c) 2026 Johan Pieterse / Plain Sailing iSystems / The Archive and
 * Heritage Group (Pty) Ltd. Released under the AGPL-3.0-or-later licence.
 *
 * Maintains `information_object_title_sort`, the sidecar that makes
 * alphabetical browse sortable by index instead of by filesort. See
 * database/install-title-sort.sql for why it has to exist at all.
 */

namespace AhgDisplay\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class TitleSortService
{
    public const TABLE = 'information_object_title_sort';

    /** Indexable prefix width; must match the column definition. */
    public const WIDTH = 191;

    /** Per-request memo for available(), so browse doesn't re-probe per query. */
    protected static ?bool $available = null;

    /**
     * Is the sidecar usable as a sort source?
     *
     * Requires both that the table exists AND that it holds rows - an existing
     * but empty table would silently sort every record as NULL, which is worse
     * than the slow-but-correct path. Callers fall back when this is false.
     *
     * The table-exists half also keeps CI green: the test database is loaded
     * from database/core/*.sql rather than by running migrations, so a query
     * naming a table that only a migration creates would 500 there.
     */
    public static function available(): bool
    {
        if (self::$available !== null) {
            return self::$available;
        }

        try {
            self::$available = Schema::hasTable(self::TABLE)
                && DB::table(self::TABLE)->limit(1)->exists();
        } catch (Throwable $e) {
            self::$available = false;
        }

        return self::$available;
    }

    /** Drop the memo - for tests and for the rebuild command's own re-check. */
    public static function forget(): void
    {
        self::$available = null;
    }

    /**
     * Make the sidecar's sort column collate exactly like its source column.
     *
     * Collation decides ordering, and it is NOT the same everywhere: on
     * heratio-dev information_object_i18n.title is utf8mb4_0900_ai_ci, while on
     * atom.theahg.co.za it is utf8mb4_general_ci. Those two disagree about
     * punctuation - with a hardcoded general_ci sidecar, dev ordered
     * "The Crystal Palace - 3D structure" and "The Crystal Palace (demo ...)"
     * the opposite way round from the base column. A sort sidecar that orders
     * differently from the column it stands in for is worse than no sidecar,
     * so the collation is detected per install rather than assumed.
     *
     * Cheap and idempotent: MySQL only rewrites the table when the collation
     * actually differs, so this is a no-op on every run after the first.
     */
    public function alignCollation(): void
    {
        try {
            if (! Schema::hasTable(self::TABLE)) {
                return;
            }

            $source = $this->columnCollation('information_object_i18n', 'title');
            $current = $this->columnCollation(self::TABLE, 'title_sort');

            if ($source === null || $current === null || $source === $current) {
                return;
            }

            // Collation names come from information_schema, never user input,
            // but constrain the shape anyway before interpolating.
            if (! preg_match('/^[A-Za-z0-9_]+$/', $source)) {
                return;
            }

            $charset = strtok($source, '_');
            DB::statement(
                'ALTER TABLE `'.self::TABLE.'`
                 MODIFY `title_sort` VARCHAR('.self::WIDTH.')
                 CHARACTER SET '.$charset.' COLLATE '.$source.' NULL'
            );
        } catch (Throwable $e) {
            // Leave the sidecar as-is; ordering may differ but nothing breaks.
        }
    }

    protected function columnCollation(string $table, string $column): ?string
    {
        $row = DB::selectOne(
            'SELECT COLLATION_NAME AS c FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return $row->c ?? null;
    }

    /**
     * Rebuild the whole projection in one set-based statement.
     *
     * Deliberately not row-by-row: the full projection over 454,392 rows
     * computes in ~3s on atom.theahg.co.za, which is cheap enough to run on a
     * schedule and makes the sidecar self-healing. Any title written by one of
     * the ~27 code paths that touch information_object_i18n is picked up on the
     * next pass without those paths needing to know this table exists.
     *
     * @return int rows written
     */
    public function rebuildAll(): int
    {
        if (! Schema::hasTable(self::TABLE)) {
            return 0;
        }

        // Self-healing: an install whose base collation changes (or a table
        // created before this check existed) is corrected on the next rebuild.
        $this->alignCollation();

        // REPLACE rather than INSERT..ON DUPLICATE KEY: the projection is the
        // whole truth for a (object_id, culture) pair, so overwriting is right.
        DB::statement(
            'REPLACE INTO `'.self::TABLE.'` (`object_id`, `culture`, `title_sort`)
             SELECT i.id, i.culture,
                    LEFT(COALESCE(NULLIF(i.title, ""), fb.title), '.self::WIDTH.')
               FROM `information_object_i18n` i
               JOIN `information_object` io ON io.id = i.id
               LEFT JOIN `information_object_i18n` fb
                      ON fb.id = i.id AND fb.culture = io.source_culture'
        );

        // Rows whose information_object has since been deleted would otherwise
        // linger and sort into results that no longer exist.
        DB::statement(
            'DELETE ts FROM `'.self::TABLE.'` ts
              LEFT JOIN `information_object` io ON io.id = ts.object_id
              WHERE io.id IS NULL'
        );

        self::forget();

        return (int) DB::table(self::TABLE)->count();
    }

    /**
     * Refresh a single object across every culture it has.
     *
     * Call after saving a title if you want the new value ordered correctly
     * before the next scheduled rebuild. Safe to call when the table is absent.
     */
    public function refreshFor(int $objectId): void
    {
        if ($objectId <= 0 || ! Schema::hasTable(self::TABLE)) {
            return;
        }

        try {
            DB::statement(
                'REPLACE INTO `'.self::TABLE.'` (`object_id`, `culture`, `title_sort`)
                 SELECT i.id, i.culture,
                        LEFT(COALESCE(NULLIF(i.title, ""), fb.title), '.self::WIDTH.')
                   FROM `information_object_i18n` i
                   JOIN `information_object` io ON io.id = i.id
                   LEFT JOIN `information_object_i18n` fb
                          ON fb.id = i.id AND fb.culture = io.source_culture
                  WHERE i.id = ?',
                [$objectId]
            );
        } catch (Throwable $e) {
            // A stale sort key is a cosmetic ordering issue; never fail a save.
        }
    }
}
