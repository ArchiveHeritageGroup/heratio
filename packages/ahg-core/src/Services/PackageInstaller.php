<?php
/*
 * Heratio — PackageInstaller
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing iSystems
 * Licensed under AGPL-3.0-or-later. See LICENSE.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-install / sentinel-gated runner for a Heratio package's database/install.sql.
 *
 * Standalone install plan §6 stage 9 + Phase 1 #6 — every Heratio package that
 * ships a database/install.sql can have it idempotently loaded:
 *
 *   - On a fresh install the canonical path is `bin/install` running every
 *     install.sql via the mysql client (alphabetical, two passes so that
 *     cross-plugin INSERTs land on the second pass).
 *   - As a safety-net for cases where a deploy hits a web request before
 *     bin/install has been run, this service is also called from each
 *     ServiceProvider's app->booted() and via `php artisan
 *     heratio:install-bootstrap`.
 *
 * The check is cheap: extract the first `CREATE TABLE IF NOT EXISTS <name>`
 * from the install.sql, ask Schema::hasTable($name). If present, skip. If
 * missing, execute the file via DB::unprepared() inside FOREIGN_KEY_CHECKS=0.
 *
 * The sentinel is by convention "the first table the file creates", which
 * for tool-generated install.sql files (database/tools/port-plugin-install-
 * sql.sh) is reliably the first owned table of that package.
 */
class PackageInstaller
{
    /**
     * Run a package's install.sql.
     *
     * Two modes:
     *   $force = false  — sentinel-gated. Skip if the file's first CREATE
     *                     TABLE target already exists. Cheap, intended for
     *                     per-request safety-net auto-install.
     *   $force = true   — always run. Idempotent because every file uses
     *                     CREATE TABLE IF NOT EXISTS / INSERT IGNORE.
     *                     Used by `heratio:install-bootstrap` and bin/install
     *                     so seed-only packages and packages whose first
     *                     table is also defined by 03_framework.sql still
     *                     get their seed INSERTs.
     *
     * @return bool true if the SQL ran, false if skipped
     */
    /** Last error message recorded by autoInstall, if any. Reset per call. */
    public static string $lastError = '';

    public static function autoInstall(string $packageRoot, bool $force = false, ?string $sqlFile = null): bool
    {
        self::$lastError = '';
        $sqlFile = $sqlFile ?? (rtrim($packageRoot, '/') . '/database/install.sql');
        if (!is_readable($sqlFile)) {
            self::$lastError = "not readable: {$sqlFile}";
            return false;
        }

        if (!$force) {
            $sentinel = self::firstTableName($sqlFile);
            if ($sentinel === null) {
                self::$lastError = "no CREATE TABLE found (sentinel)";
                return false;
            }

            try {
                if (Schema::hasTable($sentinel)) {
                    self::$lastError = "sentinel table {$sentinel} exists";
                    return false;
                }
            } catch (\Throwable $e) {
                self::$lastError = "Schema::hasTable threw: " . $e->getMessage();
                return false;
            }
        }

        $sql = file_get_contents($sqlFile);
        if ($sql === false || $sql === '') {
            self::$lastError = "empty or unreadable file";
            return false;
        }

        try {
            DB::unprepared("SET FOREIGN_KEY_CHECKS = 0;\n" . $sql . "\nSET FOREIGN_KEY_CHECKS = 1;\n");
            Log::info("PackageInstaller: ran {$sqlFile} (force=" . ($force ? 'true' : 'false') . ')');
            return true;
        } catch (\Throwable $e) {
            self::$lastError = "DB::unprepared threw: " . $e->getMessage();
            Log::warning("PackageInstaller: run of {$sqlFile} failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Iterate every packages/<name>/database/install*.sql under the given
     * packages root and run autoInstall on each. Used by the
     * heratio:install-bootstrap artisan command and by bin/install's
     * Laravel-boot-sweep stage.
     *
     * Pattern is install*.sql (not just install.sql) so per-feature schema
     * splits like install_voice_usage.sql / install_template_extensions.sql /
     * install_i18n.sql get swept too — otherwise those tables only land via
     * service-provider boot hooks during a web request, which never happens
     * in the CI artifact build. Sort naturally puts install.sql before
     * install_*.sql per package because '.' < '_' in ASCII; cross-package
     * ordering stays alphabetic by package name.
     *
     * @return array{ran:int, skipped:int, files:array}
     */
    public static function installAll(string $packagesRoot, bool $force = true): array
    {
        $ran = 0;
        $skipped = 0;
        $files = [];

        $dirs = glob(rtrim($packagesRoot, '/') . '/*/database/install*.sql');
        sort($dirs);

        foreach ($dirs as $sqlFile) {
            $packageRoot = dirname(dirname($sqlFile));
            $packageName = basename($packageRoot);
            $fileLabel   = basename($sqlFile);
            // Per-package + per-file key so install.sql + install_*.sql for
            // the same package both appear in the result map.
            $reportKey = $fileLabel === 'install.sql' ? $packageName : "{$packageName}/{$fileLabel}";
            $didRun = self::autoInstall($packageRoot, $force, $sqlFile);
            // Stash the per-package error string when we skipped — surfaces in
            // the verbose listing of `heratio:install-bootstrap` so CI can see
            // why a package didn't install (otherwise the warning is hidden in
            // storage/logs/laravel.log which CI doesn't expose).
            $files[$reportKey] = $didRun ? 'installed' : ('skipped — ' . self::$lastError);
            $didRun ? $ran++ : $skipped++;
        }

        return ['ran' => $ran, 'skipped' => $skipped, 'files' => $files];
    }

    /**
     * Pull the first table name from a CREATE TABLE [IF NOT EXISTS] statement
     * in the given file. Returns null if no CREATE TABLE is found.
     */
    private static function firstTableName(string $sqlFile): ?string
    {
        $fh = @fopen($sqlFile, 'r');
        if ($fh === false) {
            return null;
        }

        try {
            while (($line = fgets($fh)) !== false) {
                if (preg_match('/^\s*CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z_][a-zA-Z0-9_]*)`?/i', $line, $m)) {
                    return $m[1];
                }
            }
        } finally {
            fclose($fh);
        }
        return null;
    }
}
