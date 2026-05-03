<?php

/**
 * AhgSettingsService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */



namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AHG Settings & Dropdown service.
 *
 * Settings: reads from `ahg_settings` table (key/value with setting_group).
 * Dropdowns: reads from `ahg_dropdown` and `ahg_dropdown_column_map` tables.
 * Migrated from AtomExtensions\Services\AhgSettingsService + DropdownService.
 */
class AhgSettingsService
{
    /** @var array|null Cached settings */
    private static ?array $cache = null;

    /** @var array Cached column→taxonomy mappings */
    private static array $mapCache = [];

    /** @var array Cached dropdown values by taxonomy */
    private static array $valuesCache = [];

    /**
     * Get a setting value by key.
     */
    public static function get(string $key, $default = null)
    {
        self::loadCache();

        return self::$cache[$key] ?? $default;
    }

    /**
     * Get a boolean setting.
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if (null === $value) {
            return $default;
        }

        return in_array($value, ['true', '1', 1, true], true);
    }

    /**
     * Get an integer setting.
     */
    public static function getInt(string $key, int $default = 0): int
    {
        $value = self::get($key);

        return null !== $value ? (int) $value : $default;
    }

    /**
     * Get all settings for a group.
     */
    public static function getGroup(string $group): array
    {
        self::loadCache();

        try {
            return DB::table('ahg_settings')
                ->where('setting_group', $group)
                ->pluck('setting_value', 'setting_key')
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check if a feature is enabled.
     */
    public static function isEnabled(string $feature): bool
    {
        $key = str_ends_with($feature, '_enabled') ? $feature : $feature . '_enabled';

        return self::getBool($key);
    }

    /**
     * Set a setting value.
     */
    public static function set(string $key, $value, string $group = 'general'): void
    {
        DB::table('ahg_settings')->updateOrInsert(
            ['setting_key' => $key],
            [
                'setting_value' => $value,
                'setting_group' => $group,
                'updated_at' => now(),
            ]
        );

        self::clearCache();
    }

    /**
     * Clear the settings cache.
     */
    public static function clearCache(): void
    {
        self::$cache = null;
    }

    /**
     * Load all settings into cache.
     */
    private static function loadCache(): void
    {
        if (null !== self::$cache) {
            return;
        }

        try {
            self::$cache = DB::table('ahg_settings')
                ->pluck('setting_value', 'setting_key')
                ->toArray();
        } catch (\Exception $e) {
            self::$cache = [];
        }
    }

    // ========================================================================
    // DROPDOWN — Column Mapping (ahg_dropdown_column_map)
    // ========================================================================

    /**
     * Get the taxonomy name mapped to a table.column.
     */
    public static function getDropdownTaxonomy(string $table, string $column): ?string
    {
        $key = "{$table}.{$column}";

        if (isset(self::$mapCache[$key])) {
            return self::$mapCache[$key];
        }

        try {
            $map = DB::table('ahg_dropdown_column_map')
                ->where('table_name', $table)
                ->where('column_name', $column)
                ->first();

            $taxonomy = $map->taxonomy ?? null;
        } catch (\Exception $e) {
            $taxonomy = null;
        }

        self::$mapCache[$key] = $taxonomy;

        return $taxonomy;
    }

    /**
     * Check if a column is mapped to a dropdown taxonomy.
     */
    public static function isDropdownMapped(string $table, string $column): bool
    {
        return self::getDropdownTaxonomy($table, $column) !== null;
    }

    /**
     * Check if a column mapping is strict (only dropdown values allowed).
     */
    public static function isDropdownStrict(string $table, string $column): bool
    {
        try {
            $map = DB::table('ahg_dropdown_column_map')
                ->where('table_name', $table)
                ->where('column_name', $column)
                ->first();

            return (bool) ($map->is_strict ?? true);
        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * Get all column mappings for a table.
     */
    public static function getDropdownMappingsForTable(string $table): array
    {
        try {
            return DB::table('ahg_dropdown_column_map')
                ->where('table_name', $table)
                ->get()
                ->keyBy('column_name')
                ->all();
        } catch (\Exception $e) {
            return [];
        }
    }

    // ========================================================================
    // DROPDOWN — Validation
    // ========================================================================

    /**
     * Validate a value against the dropdown for a table.column.
     * Returns true if valid or if column is unmapped/non-strict.
     */
    public static function isDropdownValid(string $table, string $column, ?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $taxonomy = self::getDropdownTaxonomy($table, $column);
        if ($taxonomy === null) {
            return true;
        }

        $values = self::getDropdownValidValues($taxonomy);

        if (in_array($value, $values, true)) {
            return true;
        }

        if (!self::isDropdownStrict($table, $column)) {
            return true;
        }

        return false;
    }

    /**
     * Validate a value directly against a taxonomy.
     */
    public static function isDropdownValidForTaxonomy(string $taxonomy, ?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return in_array($value, self::getDropdownValidValues($taxonomy), true);
    }

    /**
     * Get all valid value codes for a taxonomy.
     */
    public static function getDropdownValidValues(string $taxonomy): array
    {
        if (isset(self::$valuesCache[$taxonomy])) {
            return self::$valuesCache[$taxonomy];
        }

        try {
            $values = DB::table('ahg_dropdown')
                ->where('taxonomy', $taxonomy)
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->pluck('code')
                ->all();
        } catch (\Exception $e) {
            $values = [];
        }

        self::$valuesCache[$taxonomy] = $values;

        return $values;
    }

    /**
     * Validate multiple column values for a table row.
     * Returns array of invalid columns with details, empty if all valid.
     */
    public static function validateDropdownRow(string $table, array $row): array
    {
        $errors = [];
        $mappings = self::getDropdownMappingsForTable($table);

        foreach ($mappings as $column => $map) {
            if (!isset($row[$column]) || $row[$column] === null || $row[$column] === '') {
                continue;
            }

            $value = $row[$column];
            $validValues = self::getDropdownValidValues($map->taxonomy);

            if (!in_array($value, $validValues, true) && $map->is_strict) {
                $errors[$column] = [
                    'value' => $value,
                    'taxonomy' => $map->taxonomy,
                    'valid_values' => $validValues,
                ];
            }
        }

        return $errors;
    }

    // ========================================================================
    // DROPDOWN — Choices & Labels
    // ========================================================================

    /**
     * Get dropdown choices as [code => label] for a table.column.
     */
    public static function getDropdownChoicesForColumn(string $table, string $column, bool $includeEmpty = true): array
    {
        $taxonomy = self::getDropdownTaxonomy($table, $column);
        if ($taxonomy === null) {
            return [];
        }

        return self::getDropdownChoices($taxonomy, $includeEmpty);
    }

    /**
     * Get dropdown choices as [code => label] for a taxonomy.
     *
     * Issue #59 Tier 1 — labels come from ahg_dropdown_i18n (current culture)
     * with an en fallback and a parent-table fallback, all via COALESCE. If
     * ahg_dropdown_i18n is missing (fresh install), the COALESCE collapses to
     * the parent ahg_dropdown.label so callers see no behaviour change.
     */
    public static function getDropdownChoices(string $taxonomy, bool $includeEmpty = true): array
    {
        $choices = $includeEmpty ? ['' => ''] : [];

        try {
            $terms = self::dropdownQueryWithI18n()
                ->where('d.taxonomy', $taxonomy)
                ->where('d.is_active', 1)
                ->orderBy('d.sort_order')
                ->orderBy('d.label')
                ->get();

            foreach ($terms as $term) {
                $choices[$term->code] = $term->label;
            }
        } catch (\Exception $e) {
            // Table may not exist yet
        }

        return $choices;
    }

    /**
     * Get dropdown choices with full attributes (code, label, color, icon, etc.).
     *
     * Issue #59 Tier 1 — culture-aware label via the same COALESCE pattern as
     * getDropdownChoices(). All other columns (code, color, icon, sort_order,
     * is_default, metadata) come from the parent ahg_dropdown row unchanged.
     */
    public static function getDropdownChoicesWithAttributes(string $taxonomy): \Illuminate\Support\Collection
    {
        try {
            return self::dropdownQueryWithI18n([
                'd.id', 'd.color', 'd.icon', 'd.sort_order', 'd.is_default', 'd.metadata',
            ])
                ->where('d.taxonomy', $taxonomy)
                ->where('d.is_active', 1)
                ->orderBy('d.sort_order')
                ->orderBy('d.label')
                ->get()
                ->keyBy('code');
        } catch (\Exception $e) {
            return collect();
        }
    }

    /**
     * Issue #59 Tier 1 — shared culture-aware base query for ahg_dropdown.
     *
     * LEFT JOINs ahg_dropdown_i18n in the current locale + 'en' fallback, then
     * SELECTs `code` plus a COALESCE'd `label` field that resolves to:
     *   1. i18n[current_culture].label   (if present + non-empty)
     *   2. i18n['en'].label               (if present + non-empty)
     *   3. ahg_dropdown.label             (parent / source-culture cache)
     *
     * Schema::hasTable() guards the JOIN so callers on installs without the
     * i18n table still get the parent label — no exception, no behaviour change.
     *
     * Pass extra columns via $extraSelect to round out the SELECT list. The
     * `code` column is always included; `label` is the COALESCE'd alias.
     */
    protected static function dropdownQueryWithI18n(array $extraSelect = []): \Illuminate\Database\Query\Builder
    {
        $culture = (string) app()->getLocale();
        $hasI18n = Schema::hasTable('ahg_dropdown_i18n');

        $select = array_merge(['d.code'], $extraSelect);

        $q = DB::table('ahg_dropdown as d');

        if ($hasI18n) {
            $q->leftJoin('ahg_dropdown_i18n as di_cur', function ($j) use ($culture) {
                $j->on('di_cur.id', '=', 'd.id')->where('di_cur.culture', '=', $culture);
            });
            $q->leftJoin('ahg_dropdown_i18n as di_fb', function ($j) {
                $j->on('di_fb.id', '=', 'd.id')->where('di_fb.culture', '=', 'en');
            });
            $select[] = DB::raw("COALESCE(NULLIF(di_cur.label, ''), NULLIF(di_fb.label, ''), d.label) AS label");
        } else {
            $select[] = 'd.label';
        }

        return $q->select($select);
    }

    /**
     * Resolve a dropdown code to its display label for a table.column.
     */
    public static function resolveDropdownLabel(string $table, string $column, ?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        $taxonomy = self::getDropdownTaxonomy($table, $column);
        if ($taxonomy === null) {
            return $code;
        }

        return self::resolveDropdownLabelForTaxonomy($taxonomy, $code);
    }

    /**
     * Resolve a dropdown code to its display label for a taxonomy.
     */
    public static function resolveDropdownLabelForTaxonomy(string $taxonomy, ?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        // Issue #59 Tier 1 — culture-aware label via the same COALESCE base
        // query as getDropdownChoices(). Returns the parent label when no i18n
        // row exists (or when ahg_dropdown_i18n hasn't been installed yet).
        try {
            $row = self::dropdownQueryWithI18n()
                ->where('d.taxonomy', $taxonomy)
                ->where('d.code', $code)
                ->first();

            return ($row && $row->label !== null && $row->label !== '') ? $row->label : $code;
        } catch (\Exception $e) {
            return $code;
        }
    }

    /**
     * Resolve a dropdown code to its color for a taxonomy.
     */
    public static function resolveDropdownColor(string $taxonomy, string $code): ?string
    {
        try {
            return DB::table('ahg_dropdown')
                ->where('taxonomy', $taxonomy)
                ->where('code', $code)
                ->value('color');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the default value code for a table.column.
     */
    public static function getDropdownDefault(string $table, string $column): ?string
    {
        $taxonomy = self::getDropdownTaxonomy($table, $column);
        if ($taxonomy === null) {
            return null;
        }

        return self::getDropdownDefaultForTaxonomy($taxonomy);
    }

    /**
     * Get the default value code for a taxonomy.
     */
    public static function getDropdownDefaultForTaxonomy(string $taxonomy): ?string
    {
        try {
            $default = DB::table('ahg_dropdown')
                ->where('taxonomy', $taxonomy)
                ->where('is_active', 1)
                ->where('is_default', 1)
                ->value('code');

            if ($default !== null) {
                return $default;
            }

            return DB::table('ahg_dropdown')
                ->where('taxonomy', $taxonomy)
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->value('code');
        } catch (\Exception $e) {
            return null;
        }
    }

    // ========================================================================
    // DROPDOWN — Statistics
    // ========================================================================

    /**
     * Get statistics about dropdown coverage.
     */
    public static function getDropdownStats(): array
    {
        try {
            $totalTaxonomies = DB::table('ahg_dropdown')
                ->select('taxonomy')
                ->distinct()
                ->count('taxonomy');

            $totalValues = DB::table('ahg_dropdown')->count();

            $activeValues = DB::table('ahg_dropdown')
                ->where('is_active', 1)
                ->count();

            $mappedColumns = DB::table('ahg_dropdown_column_map')->count();

            $strictColumns = DB::table('ahg_dropdown_column_map')
                ->where('is_strict', 1)
                ->count();

            return [
                'taxonomies' => $totalTaxonomies,
                'total_values' => $totalValues,
                'active_values' => $activeValues,
                'mapped_columns' => $mappedColumns,
                'strict_columns' => $strictColumns,
            ];
        } catch (\Exception $e) {
            return [
                'taxonomies' => 0,
                'total_values' => 0,
                'active_values' => 0,
                'mapped_columns' => 0,
                'strict_columns' => 0,
            ];
        }
    }

    /**
     * Clear all caches (settings + dropdown).
     */
    public static function clearAllCaches(): void
    {
        self::$cache = null;
        self::$mapCache = [];
        self::$valuesCache = [];
    }
}
