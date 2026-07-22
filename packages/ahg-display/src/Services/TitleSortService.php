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

    /** Indexable prefix width; must match the column definitions. */
    public const WIDTH = 191;

    /**
     * Sidecar sort columns: [source table, source column, DDL type].
     *
     * The two text columns stand in for varchar(1024) base columns that cannot
     * be ordered by index - information_object_i18n.title has only a 191-char
     * PREFIX index (unusable for ORDER BY at any length) and
     * information_object.identifier has no index at all.
     *
     * The two date columns stand in for an AGGREGATE rather than a column:
     * browse orders by MIN(event.start_date) / MAX(event.end_date), which needs
     * a join to `event` plus a GROUP BY across every selected column, and costs
     * ~10.3s a page on atom.theahg.co.za. Precomputing the aggregate per object
     * turns that into an ordinary indexed column. Only 52,249 of 454,393
     * records have a dated event; the rest are legitimately NULL and sort
     * exactly where MIN()/MAX() put them.
     */
    public const COLUMNS = [
        'title_sort' => ['information_object_i18n', 'title', 'VARCHAR(191)'],
        'identifier_sort' => ['information_object', 'identifier', 'VARCHAR(191)'],
        'start_date_sort' => ['event', 'start_date', 'DATE'],
        'end_date_sort' => ['event', 'end_date', 'DATE'],
    ];

    /** Per-request memo for available(), so browse doesn't re-probe per query. */
    protected static array $available = [];

    /**
     * Is the sidecar usable as a sort source for this column?
     *
     * Requires the table to exist, the column to exist, AND at least one
     * non-null value - an existing but unpopulated column would silently sort
     * every record as NULL, which is worse than the slow-but-correct path.
     * Callers fall back when this is false. The column check matters on
     * upgrade: instances created before identifier_sort existed have the table
     * but not the column until ensureColumns() has run.
     *
     * The table-exists half also keeps CI green: the test database is loaded
     * from database/core/*.sql rather than by running migrations, so a query
     * naming a table that only the provider creates would 500 there.
     */
    public static function available(string $column = 'title_sort'): bool
    {
        if (array_key_exists($column, self::$available)) {
            return self::$available[$column];
        }

        try {
            if ($column === 'object_id') {
                // Not a projected value - it is the sidecar's own key, and
                // ordering by it is identical to ordering by io.id. Needs only
                // that the sidecar is present and populated, since every row
                // has one by definition.
                self::$available[$column] = Schema::hasTable(self::TABLE)
                    && DB::table(self::TABLE)->limit(1)->exists();
            } else {
                self::$available[$column] = isset(self::COLUMNS[$column])
                    && Schema::hasTable(self::TABLE)
                    && Schema::hasColumn(self::TABLE, $column)
                    && DB::table(self::TABLE)->whereNotNull($column)->limit(1)->exists();
            }
        } catch (Throwable $e) {
            self::$available[$column] = false;
        }

        return self::$available[$column];
    }

    /** Drop the memo - for tests and for the rebuild command's own re-check. */
    public static function forget(): void
    {
        self::$available = [];
    }

    /**
     * Add any sidecar sort column missing on an already-created table.
     *
     * The table ships in install-title-sort.sql, so a fresh install has every
     * column; an instance created before a column was added has the table and
     * would otherwise never gain it. Guarded by hasColumn so this is a cheap
     * no-op on every boot after the first.
     */
    public function ensureColumns(): void
    {
        try {
            if (! Schema::hasTable(self::TABLE)) {
                return;
            }

            foreach (self::COLUMNS as $column => [, , $type]) {
                if (Schema::hasColumn(self::TABLE, $column)) {
                    continue;
                }
                DB::statement(
                    'ALTER TABLE `'.self::TABLE.'`
                     ADD COLUMN `'.$column.'` '.$type.' NULL,
                     ADD KEY `idx_iots_culture_'.$column.'` (`culture`, `'.$column.'`, `object_id`)'
                );
            }

            self::forget();
        } catch (Throwable $e) {
            // Leave the sidecar as-is; affected sorts fall back to the base column.
        }
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

            foreach (self::COLUMNS as $column => [$sourceTable, $sourceColumn, $type]) {
                if (! Schema::hasColumn(self::TABLE, $column)) {
                    continue;
                }

                $source = $this->columnCollation($sourceTable, $sourceColumn);
                $current = $this->columnCollation(self::TABLE, $column);

                // Non-text columns (DATE) have no collation on either side and
                // fall out here, which is correct - date ordering is not
                // collation-dependent.
                if ($source === null || $current === null || $source === $current) {
                    continue;
                }

                // Collation names come from information_schema, never user input,
                // but constrain the shape anyway before interpolating.
                if (! preg_match('/^[A-Za-z0-9_]+$/', $source)) {
                    continue;
                }

                $charset = strtok($source, '_');
                DB::statement(
                    'ALTER TABLE `'.self::TABLE.'`
                     MODIFY `'.$column.'` '.$type.'
                     CHARACTER SET '.$charset.' COLLATE '.$source.' NULL'
                );
            }
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

        // Self-healing: an install upgraded from a version without a given
        // column gains it here, and a base collation change is corrected on the
        // next rebuild.
        $this->ensureColumns();
        $this->alignCollation();

        // REPLACE rather than INSERT..ON DUPLICATE KEY: the projection is the
        // whole truth for a (object_id, culture) pair, so overwriting is right.
        //
        // identifier lives on information_object, not the i18n table, so the
        // same value is written for every culture of a record - correct, since
        // a reference code does not vary by language.
        DB::statement(
            'REPLACE INTO `'.self::TABLE.'`
                    (`object_id`, `culture`, `title_sort`, `identifier_sort`, `start_date_sort`, `end_date_sort`)
             SELECT i.id, i.culture,
                    LEFT(COALESCE(NULLIF(i.title, ""), fb.title), '.self::WIDTH.'),
                    LEFT(io.identifier, '.self::WIDTH.'),
                    ev.sd, ev.ed
               FROM `information_object_i18n` i
               JOIN `information_object` io ON io.id = i.id
               LEFT JOIN `information_object_i18n` fb
                      ON fb.id = i.id AND fb.culture = io.source_culture
               LEFT JOIN (SELECT `object_id`, MIN(`start_date`) sd, MAX(`end_date`) ed
                            FROM `event` WHERE `object_id` IS NOT NULL
                           GROUP BY `object_id`) ev ON ev.object_id = i.id'
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
                'REPLACE INTO `'.self::TABLE.'`
                        (`object_id`, `culture`, `title_sort`, `identifier_sort`, `start_date_sort`, `end_date_sort`)
                 SELECT i.id, i.culture,
                        LEFT(COALESCE(NULLIF(i.title, ""), fb.title), '.self::WIDTH.'),
                        LEFT(io.identifier, '.self::WIDTH.'),
                        (SELECT MIN(`start_date`) FROM `event` WHERE `object_id` = i.id),
                        (SELECT MAX(`end_date`)   FROM `event` WHERE `object_id` = i.id)
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
