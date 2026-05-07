<?php

/**
 * DbAwareLoader - DB-first translation loader for Heratio.
 *
 * Wraps Laravel's stock FileLoader. For the JSON-namespace lookup
 * (Translator::load('*', '*', $locale) - the `__('Some Key')` path) it merges
 * rows from the `ui_string` table on top of whatever the JSON files provide.
 * For non-JSON groups (PHP-array translations under lang/{locale}/{group}.php
 * and namespaced packages) it delegates straight through to FileLoader.
 *
 * The fallback chain for a JSON key in culture C is:
 *   1. ui_string row WHERE culture = C
 *   2. lang/C.json on disk (if the row is missing AND the file has the key)
 *
 * That two-step chain lets a fresh install boot before
 * `php artisan ahg:translation:import-json-to-db` has been run, and keeps the
 * file fallback as a deploy-time safety net (operator can wipe the table and
 * re-seed without app downtime).
 *
 * Issue #57 - unify UI-string storage.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing iSystems
 * Licensed under AGPL-3.0-or-later. See LICENSE.
 */

namespace AhgTranslation\Translation;

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DbAwareLoader implements Loader
{
    /**
     * Per-process cache of culture -> [key => value] pulled from ui_string.
     * Keyed by culture; one SELECT per culture per process. Translator::load
     * already memoises into Translator::$loaded so we get hit at most once
     * per culture per request even before this cache.
     *
     * @var array<string, array<string, string>>
     */
    protected array $dbCache = [];

    /**
     * Whether we've established that the ui_string table exists on this
     * install. Set on first probe; sticky thereafter so we don't re-query
     * information_schema on every load() call.
     *
     * @var bool|null  null = not yet probed; true/false after first probe
     */
    protected ?bool $tableExists = null;

    public function __construct(protected Loader $inner) {}

    /**
     * Load translations. For JSON keys (group='*', namespace='*' or null)
     * we merge ui_string rows on top of the file-loaded values.
     *
     * Laravel's Translator::get() always calls load('*', '*', $locale) first
     * and reads $loaded['*']['*'][$locale][$key]. So returning a merged map
     * here is enough - no other interception point needed.
     */
    public function load($locale, $group, $namespace = null)
    {
        // Delegate non-JSON loads (PHP arrays in lang/{locale}/{group}.php
        // and namespaced package translations) straight through. Those are
        // out of #57 scope.
        if ($group !== '*' || ($namespace !== null && $namespace !== '*')) {
            return $this->inner->load($locale, $group, $namespace);
        }

        $fileLines = $this->inner->load($locale, $group, $namespace) ?: [];
        $dbLines   = $this->loadDb($locale);

        // DB wins. File values are the deploy-time fallback that fills in
        // keys not yet promoted to ui_string (e.g. fresh install pre-import,
        // or a key added in code after the last import run).
        return array_merge($fileLines, $dbLines);
    }

    public function addNamespace($namespace, $hint)
    {
        $this->inner->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path)
    {
        $this->inner->addJsonPath($path);
    }

    public function namespaces()
    {
        return $this->inner->namespaces();
    }

    /**
     * Pull every (key, value) row for this culture from ui_string. Cached
     * per-process; one query per culture even across many __() calls.
     * Returns an empty array on any failure (table missing, DB down, etc.)
     * so the file fallback in load() kicks in cleanly.
     */
    protected function loadDb(string $locale): array
    {
        if (isset($this->dbCache[$locale])) {
            return $this->dbCache[$locale];
        }

        if ($this->tableExists === false) {
            return $this->dbCache[$locale] = [];
        }

        try {
            if ($this->tableExists === null) {
                $this->tableExists = Schema::hasTable('ui_string');
                if (!$this->tableExists) {
                    return $this->dbCache[$locale] = [];
                }
            }

            $rows = DB::table('ui_string')
                ->where('culture', $locale)
                ->whereNotNull('value')
                ->where('value', '!=', '')
                ->select(['key', 'value'])
                ->get();

            $map = [];
            foreach ($rows as $r) {
                $map[(string) $r->key] = (string) $r->value;
            }
            return $this->dbCache[$locale] = $map;
        } catch (\Throwable $e) {
            // DB unavailable / table missing / install in flight - return
            // empty so the JSON fallback in load() takes over. Don't 500
            // the request just because translation lookups can't read DB.
            return $this->dbCache[$locale] = [];
        }
    }

    /**
     * Drop the per-process cache. Called by UiStringService after a write
     * so the next __() lookup in the same request sees the fresh value.
     */
    public function flush(?string $locale = null): void
    {
        if ($locale === null) {
            $this->dbCache = [];
            return;
        }
        unset($this->dbCache[$locale]);
    }

    /**
     * Expose the underlying FileLoader for callers that need file-only paths
     * (e.g. the import command reads JSON files directly).
     */
    public function inner(): Loader
    {
        return $this->inner;
    }
}
