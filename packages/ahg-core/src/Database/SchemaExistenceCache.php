<?php

/**
 * SchemaExistenceCache - Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgCore\Database;

/**
 * Remembers that tables and columns EXIST, across requests.
 *
 * Package providers check their schema on every boot - about 500
 * information_schema queries per request, roughly 0.75 s of every page on prod.
 * Almost every answer is "yes, it exists", and that answer does not change
 * between deploys, so it is kept in a PHP array file that opcache holds.
 *
 * Only positive answers are stored. A missing table or column is always
 * re-checked live, so the providers' create-if-missing logic behaves exactly
 * as before. The file is keyed by release version and database, so a deploy
 * starts clean; `php artisan ahg:schema-cache-clear` clears it by hand.
 *
 * ponytail: a table dropped with raw SQL (not Schema::drop) stays "existing"
 * until the next deploy or a clear. Restores recreate every table, so that
 * does not bite them; the upgrade path is an explicit install command, so
 * providers stop checking schema at boot at all.
 */
class SchemaExistenceCache
{
    private static ?string $path = null;

    /** @var array{t: array<string,int>, c: array<string,array<string,int>>} */
    private static array $data = ['t' => [], 'c' => []];

    private static bool $loaded = false;

    private static bool $dirty = false;

    private static bool $enabled = false;

    public static function enable(string $path): void
    {
        self::$path = $path;
        self::$enabled = true;
        self::$loaded = false;
        self::$dirty = false;
        self::$data = ['t' => [], 'c' => []];
        register_shutdown_function([self::class, 'save']);
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    public static function enabled(): bool
    {
        return self::$enabled;
    }

    public static function hasTable(string $table): bool
    {
        return self::$enabled && isset(self::load()['t'][strtolower($table)]);
    }

    public static function hasColumn(string $table, string $column): bool
    {
        return self::$enabled && isset(self::load()['c'][strtolower($table)][strtolower($column)]);
    }

    public static function rememberTable(string $table): void
    {
        if (! self::$enabled) {
            return;
        }
        self::load();
        $t = strtolower($table);
        if (! isset(self::$data['t'][$t])) {
            self::$data['t'][$t] = 1;
            self::$dirty = true;
        }
    }

    /** @param string[] $columns the table's full live column listing */
    public static function rememberColumns(string $table, array $columns): void
    {
        if (! self::$enabled || $columns === []) {
            return;
        }
        self::load();
        $t = strtolower($table);
        self::rememberTable($t);
        foreach ($columns as $c) {
            $c = strtolower((string) $c);
            if (! isset(self::$data['c'][$t][$c])) {
                self::$data['c'][$t][$c] = 1;
                self::$dirty = true;
            }
        }
    }

    /** Forget a table and its columns (drop, rename). */
    public static function forgetTable(string $table): void
    {
        self::load();
        $t = strtolower($table);
        if (isset(self::$data['t'][$t]) || isset(self::$data['c'][$t])) {
            unset(self::$data['t'][$t], self::$data['c'][$t]);
            self::$dirty = true;
        }
    }

    /** Forget a table's columns only (ALTER). */
    public static function forgetColumns(string $table): void
    {
        self::load();
        $t = strtolower($table);
        if (isset(self::$data['c'][$t])) {
            unset(self::$data['c'][$t]);
            self::$dirty = true;
        }
    }

    public static function save(): void
    {
        if (! self::$enabled || ! self::$dirty || self::$path === null) {
            return;
        }
        $tmp = self::$path.'.'.getmypid().'.tmp';
        $php = '<?php return '.var_export(self::$data, true).';'."\n";
        if (@file_put_contents($tmp, $php) !== false) {
            @chmod($tmp, 0664);
            @rename($tmp, self::$path);
        }
        self::$dirty = false;
    }

    private static function load(): array
    {
        if (! self::$loaded) {
            self::$loaded = true;
            if (self::$path !== null && is_file(self::$path)) {
                $d = @include self::$path;
                if (is_array($d) && isset($d['t'], $d['c'])) {
                    self::$data = $d;
                }
            }
        }

        return self::$data;
    }
}
