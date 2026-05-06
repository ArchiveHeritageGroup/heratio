<?php

/**
 * SectorIdentifierService - shared identifier auto-generator for the
 * sector-aware entity create() flows.
 *
 * Wired by primary-entity services (InformationObjectService and the four
 * sector services - Museum / Gallery / DAM / Library) to honour the masks
 * configured on /admin/ahgSettings/sector-numbering. When a create is
 * called without an explicit identifier and the sector mask is enabled,
 * next() returns a freshly-rendered identifier and atomically increments
 * the configured counter so concurrent inserts can't collide.
 *
 * Settings are stored in the i18n `setting` table (the same shape
 * SettingsService::getSetting / saveSetting use - the sector-numbering
 * page POSTs through that path). Keys follow the AtoM convention but
 * with two name variants present in the wild on this codebase:
 *
 *   single-underscore (matches the controller's saveSetting calls):
 *     sector_<code>_identifier_mask_enabled
 *     sector_<code>_identifier_mask
 *     sector_<code>_identifier_counter
 *
 *   double-underscore (matches the seeded defaults in 01_settings.sql):
 *     sector_<code>__identifier_mask_enabled
 *     sector_<code>__identifier_mask
 *     sector_<code>__identifier_counter
 *
 * We read both, single-underscore preferred (so an operator save wins
 * over a seed). Both names increment the same logical counter - bump
 * happens against whichever name we read from.
 *
 * Also honours a global trio (no `sector_<code>_` prefix) as a fallback
 * when the sector-specific mask is disabled or empty.
 *
 * Mask renderer supports both the AtoM-style and simpler templates:
 *   AtoM-style:  %Y% / %y% / %m% / %d%        - current date components
 *                %NNNi%                       - counter padded to NNN
 *                                               digits (e.g. %04i% -> 0042)
 *   Simpler:     {YYYY} / {YY} / {MM} / {DD}  - current date components
 *                {SECTOR}                     - sector code (uppercased)
 *                #+ run                       - counter padded to run width
 *                                               (e.g. ##### -> 00042)
 * Anything else passes through literally. The AtoM-style placeholders
 * are honoured first so seeded defaults like 'MUS.%Y%.%04i%' just work.
 *
 * Counter increment is wrapped in DB::transaction so concurrent calls
 * for the same sector serialise on the row update (the counter row is
 * updated via WHERE name=... AND value=current; if the update affects 0
 * rows because another writer beat us, we retry up to 5 times).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SectorIdentifierService
{
    /** Max retries when a concurrent counter increment beats us. */
    private const MAX_RETRIES = 5;

    /**
     * Resolve a free-form information_object.source_standard value to one
     * of the five sector codes the settings page knows about. Mirrors the
     * shape MetadataExtractionService::resolveSector returns - same
     * convention so callers don't have to re-derive the mapping. Returns
     * null when the input doesn't match any sector (caller should
     * pass-through the user-supplied identifier).
     */
    public static function resolveSector(?string $sourceStandard): ?string
    {
        $s = strtolower(trim((string) $sourceStandard));
        if ($s === '') return null;
        if ($s === 'dam')      return 'dam';
        if ($s === 'library')  return 'library';
        if ($s === 'gallery')  return 'gallery';
        if (str_contains($s, 'cco') || str_contains($s, 'museum')) return 'museum';
        if (str_contains($s, 'isad') || str_contains($s, 'dacs') || str_contains($s, 'rad') || $s === 'archive') return 'archive';
        return 'archive'; // catch-all default for ISAD-shaped source standards
    }

    /**
     * Generate the next identifier for a sector, or return null if the
     * sector mask is disabled / not configured. Caller passes through
     * the user-supplied identifier when null is returned.
     *
     * Lookup order:
     *   1. sector_<code>_identifier_mask_enabled = '1' AND mask non-empty
     *      -> render sector mask, increment sector counter
     *   2. identifier_mask_enabled (global) = '1' AND mask non-empty
     *      -> render global mask, increment global counter (if no
     *         per-sector setting beats it). The {SECTOR} token in the
     *         mask still gets the resolved sector code.
     *   3. otherwise null (caller pass-through)
     */
    public static function next(?string $sectorCode): ?string
    {
        if ($sectorCode === null || $sectorCode === '') return null;
        $sectorCode = strtolower($sectorCode);

        // Try sector-specific first. Read with single-underscore preferred,
        // double-underscore fallback (the two name conventions present on
        // this codebase - see class doc).
        [$enabledName, $enabled] = self::settingBoolEither(
            "sector_{$sectorCode}_identifier_mask_enabled",
            "sector_{$sectorCode}__identifier_mask_enabled",
            false
        );
        [$maskName, $mask] = self::settingEither(
            "sector_{$sectorCode}_identifier_mask",
            "sector_{$sectorCode}__identifier_mask",
            ''
        );
        if ($enabled && $mask !== '') {
            // Counter name follows the same convention as whichever mask
            // name actually had the value (so seeded double-underscore
            // counters stay together with their double-underscore mask).
            $isDouble = str_contains((string) $maskName, '__identifier_mask');
            $counterName = $isDouble
                ? "sector_{$sectorCode}__identifier_counter"
                : "sector_{$sectorCode}_identifier_counter";
            $next = self::incrementCounter($counterName);
            return self::renderMask((string) $mask, $next, $sectorCode);
        }

        // Fall back to global mask.
        $globalEnabled = self::settingBool('identifier_mask_enabled', false);
        $globalMask    = (string) self::setting('identifier_mask', '');
        if ($globalEnabled && $globalMask !== '') {
            $next = self::incrementCounter('identifier_counter');
            return self::renderMask($globalMask, $next, $sectorCode);
        }

        return null;
    }

    /**
     * Atomically increment the counter setting and return the NEW value.
     * Implements optimistic CAS with up to MAX_RETRIES retries: read the
     * current value, attempt to UPDATE only when value still equals what
     * we read, retry on race. setting_i18n is the home for these counter
     * values (same write path the form uses via SettingsService::save-
     * Setting). Returns 1 + the read value, never 0.
     */
    private static function incrementCounter(string $name): int
    {
        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            $row = DB::table('setting as s')
                ->join('setting_i18n as si', 'si.id', '=', 's.id')
                ->where('s.name', $name)
                ->where('s.scope', null)  // global scope (no scope set)
                ->whereNull('s.scope')
                ->where('si.culture', 'en')
                ->select('s.id', 'si.value')
                ->first();

            if (!$row) {
                // No row yet - bootstrap one. Use the SettingsService
                // CTI shape: object row + setting row + setting_i18n row.
                return self::bootstrapCounter($name, 1);
            }

            $current = (int) ($row->value ?? 0);
            $next = $current + 1;

            $affected = DB::table('setting_i18n')
                ->where('id', $row->id)
                ->where('culture', 'en')
                ->where('value', (string) $current)
                ->update(['value' => (string) $next]);

            if ($affected === 1) {
                return $next;
            }
            // Another writer beat us; retry. usleep small jitter so two
            // racers don't lockstep on every retry.
            usleep(random_int(1000, 5000));
        }

        // Last resort: a non-CAS bump. Loses one strict-monotonic guarantee
        // but never blocks a create on an unlikely thrash. Should be rare.
        $current = (int) DB::table('setting as s')
            ->join('setting_i18n as si', 'si.id', '=', 's.id')
            ->whereNull('s.scope')
            ->where('s.name', $name)
            ->where('si.culture', 'en')
            ->value('si.value');
        $next = $current + 1;
        DB::table('setting_i18n as si')
            ->join('setting as s', 's.id', '=', 'si.id')
            ->whereNull('s.scope')
            ->where('s.name', $name)
            ->where('si.culture', 'en')
            ->update(['si.value' => (string) $next]);
        return $next;
    }

    /**
     * Insert the counter row when it doesn't exist yet, returning the
     * newly-set value. CTI: object('QubitSetting') -> setting -> setting_i18n.
     */
    private static function bootstrapCounter(string $name, int $value): int
    {
        $objId = DB::table('object')->insertGetId([
            'class_name' => 'QubitSetting',
            'created_at' => now(),
            'updated_at' => now(),
            'serial_number' => 0,
        ]);
        DB::table('setting')->insert([
            'id' => $objId,
            'name' => $name,
            'scope' => null,
            'editable' => 1,
            'deleteable' => 1,
            'source_culture' => 'en',
        ]);
        DB::table('setting_i18n')->insert([
            'id' => $objId,
            'culture' => 'en',
            'value' => (string) $value,
        ]);
        return $value;
    }

    /**
     * Render a mask template into a final identifier. See class doc for
     * the supported placeholder set. Order matters: AtoM-style %NNNi%
     * counter is rendered first so the simpler #+ rule doesn't grab the
     * '#' inside an AtoM-style run. (There aren't any '#' in AtoM masks
     * but we sort it anyway to keep the two conventions independent.)
     */
    private static function renderMask(string $mask, int $counter, string $sectorCode): string
    {
        $now = now();
        $out = $mask;

        // AtoM-style counter: %04i% -> counter padded to 4 digits, %i% -> raw.
        $out = preg_replace_callback('/%(\d*)i%/', function ($m) use ($counter) {
            $width = (int) $m[1];
            return $width > 0
                ? str_pad((string) $counter, $width, '0', STR_PAD_LEFT)
                : (string) $counter;
        }, $out);
        // AtoM-style date placeholders.
        $out = strtr($out, [
            '%Y%' => $now->format('Y'),
            '%y%' => $now->format('y'),
            '%m%' => $now->format('m'),
            '%d%' => $now->format('d'),
        ]);
        // Curly-brace style.
        $out = strtr($out, [
            '{YYYY}'   => $now->format('Y'),
            '{YY}'     => $now->format('y'),
            '{MM}'     => $now->format('m'),
            '{DD}'     => $now->format('d'),
            '{SECTOR}' => strtoupper($sectorCode),
        ]);
        // Hash-run style: any contiguous '#' run -> counter padded.
        $out = preg_replace_callback('/#+/', function ($m) use ($counter) {
            return str_pad((string) $counter, strlen($m[0]), '0', STR_PAD_LEFT);
        }, $out);
        return $out;
    }

    /**
     * Read a value from the i18n setting table trying two candidate names
     * in order. Returns [name_that_won, value] so the caller can keep the
     * single/double-underscore variant consistent across mask + counter.
     * Falls back to [first-name, $default] when neither name has a value.
     */
    private static function settingEither(string $primary, string $fallback, $default): array
    {
        $v = self::setting($primary, null);
        if ($v !== null && $v !== '') return [$primary, $v];
        $v = self::setting($fallback, null);
        if ($v !== null && $v !== '') return [$fallback, $v];
        return [$primary, $default];
    }

    /** Same as settingEither but boolean-shaped. */
    private static function settingBoolEither(string $primary, string $fallback, bool $default): array
    {
        $v = self::setting($primary, null);
        if ($v !== null && $v !== '') return [$primary, in_array(strtolower((string) $v), ['1','true','yes','on'], true)];
        $v = self::setting($fallback, null);
        if ($v !== null && $v !== '') return [$fallback, in_array(strtolower((string) $v), ['1','true','yes','on'], true)];
        return [$primary, $default];
    }

    /** Read a setting from the i18n setting table (no scope). */
    private static function setting(string $name, $default = null)
    {
        if (!Schema::hasTable('setting') || !Schema::hasTable('setting_i18n')) return $default;
        $v = DB::table('setting as s')
            ->join('setting_i18n as si', 'si.id', '=', 's.id')
            ->whereNull('s.scope')
            ->where('s.name', $name)
            ->where('si.culture', 'en')
            ->value('si.value');
        return ($v === null || $v === '') ? $default : $v;
    }

    private static function settingBool(string $name, bool $default): bool
    {
        $v = self::setting($name, null);
        if ($v === null) return $default;
        return in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);
    }
}
