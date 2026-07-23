<?php

/**
 * SettingHelper - Service for Heratio
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

class SettingHelper
{
    private static array $cache = [];

    /** Reset the static request-lifetime cache (used after settings writes). */
    public static function flush(): void
    {
        self::$cache = [];
    }

    /**
     * Resolve a setting_i18n value to a plain label string for the wanted culture.
     *
     * Some AtoM-derived ui_label rows store the entire culture map as a single
     * JSON object in one row's value (e.g. {"en":"Archival institution","fr":...})
     * rather than one plain-string row per culture. Left raw, that JSON leaks into
     * page titles/headings. This decodes such a map and picks $culture, then the
     * $fallback, then the first available value. Plain-string values pass through
     * untouched. &nbsp; entities are normalised to spaces.
     */
    public static function pickI18nLabel(?string $raw, string $culture = 'en', string $fallback = 'en'): string
    {
        if ($raw === null) {
            return '';
        }
        $s = trim($raw);
        if (isset($s[0]) && $s[0] === '{') {
            $decoded = json_decode($s, true);
            if (is_array($decoded)) {
                $picked = $decoded[$culture] ?? $decoded[$fallback] ?? null;
                if ($picked === null) {
                    foreach ($decoded as $v) {
                        if (is_string($v) && $v !== '') {
                            $picked = $v;
                            break;
                        }
                    }
                }
                $s = is_string($picked) ? $picked : '';
            }
        }

        return strtr((string) $s, ['&nbsp;' => ' ']);
    }

    /**
     * Get a global AtoM setting value from the setting + setting_i18n tables.
     */
    public static function get(string $name, string $default = '', string $culture = 'en'): string
    {
        $key = "{$name}:{$culture}";

        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        try {
            $value = DB::table('setting')
                ->leftJoin('setting_i18n', function ($j) use ($culture) {
                    $j->on('setting.id', '=', 'setting_i18n.id')
                        ->where('setting_i18n.culture', '=', $culture);
                })
                ->where('setting.name', $name)
                ->whereNull('setting.scope')
                ->value('setting_i18n.value');
        } catch (\Exception $e) {
            $value = null;
        }

        self::$cache[$key] = $value ?? $default;

        return self::$cache[$key];
    }

    /**
     * Get the hits_per_page setting (default 10).
     */
    public static function hitsPerPage(): int
    {
        return max(1, (int) self::get('hits_per_page', '10'));
    }

    /**
     * Get a scoped setting value (e.g. element_visibility scope).
     */
    public static function getScoped(string $scope, string $name, string $default = '', string $culture = 'en'): string
    {
        $key = "{$scope}:{$name}:{$culture}";

        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $value = DB::table('setting')
            ->leftJoin('setting_i18n', function ($j) use ($culture) {
                $j->on('setting.id', '=', 'setting_i18n.id')
                    ->where('setting_i18n.culture', '=', $culture);
            })
            ->where('setting.name', $name)
            ->where('setting.scope', $scope)
            ->value('setting_i18n.value');

        self::$cache[$key] = $value ?? $default;

        return self::$cache[$key];
    }

    /**
     * Check field visibility, matching AtoM's check_field_visibility() function.
     *
     * If the user is authenticated (or running CLI), the field is always visible.
     * Otherwise, check the element_visibility setting value.
     *
     * @param  string  $fieldName  The setting name (e.g. 'isad_identity_area')
     * @param  array  $options  Optional: ['public' => true] to always check the setting
     */
    public static function checkFieldVisibility(string $fieldName, array $options = []): bool
    {
        // The config key is set during boot: atom.element_visibility.<fieldName>
        $configValue = config("atom.element_visibility.{$fieldName}", true);

        // If 'public' option is set, always check the config value regardless of auth
        if (! empty($options['public'])) {
            return (bool) $configValue;
        }

        // Authenticated users always see all fields (matching AtoM behavior)
        if (php_sapi_name() === 'cli' || auth()->check()) {
            return true;
        }

        return (bool) $configValue;
    }

    /**
     * Load all element_visibility settings into config('atom.element_visibility.*').
     * Called once during boot.
     */
    public static function loadElementVisibility(string $culture = 'en'): void
    {
        try {
            $settings = DB::table('setting')
                ->leftJoin('setting_i18n', function ($j) use ($culture) {
                    $j->on('setting.id', '=', 'setting_i18n.id')
                        ->where('setting_i18n.culture', '=', $culture);
                })
                ->where('setting.scope', 'element_visibility')
                ->select('setting.name', 'setting_i18n.value')
                ->get();

            foreach ($settings as $row) {
                config(["atom.element_visibility.{$row->name}" => (bool) (int) ($row->value ?? '1')]);
            }
        } catch (\Exception $e) {
            // setting table doesn't exist yet — skip
        }
    }

    /**
     * Resolve a default-template view name for an entity show page (#98 Phase 1).
     *
     * Reads the setting at scope='default_template', name=$entityType (the AtoM
     * legacy mechanism for picking ISAD vs DACS vs RAD vs MODS for descriptions
     * — and ISAAR for actors, ISDIAH for repositories). Falls back to the base
     * view when the chosen template's blade hasn't been authored yet so an
     * operator can pick a value the codebase doesn't yet implement and still
     * see a valid page.
     *
     * Example:
     *     return view(
     *         SettingHelper::resolveTemplateView('informationobject', 'ahg-io-manage::show', 'isad'),
     *         compact('io', ...),
     *     );
     *
     * Phase 2 of #98 will author show-dacs / show-rad / show-mods etc.; this
     * resolver picks them up the moment the blade file lands on disk, no
     * controller change required.
     */
    public static function resolveTemplateView(string $entityType, string $viewBase, string $defaultTemplate): string
    {
        $template = self::getScoped('default_template', $entityType, $defaultTemplate);
        $template = trim($template) !== '' ? trim($template) : $defaultTemplate;
        $candidate = $viewBase.'-'.$template;
        if (\Illuminate\Support\Facades\View::exists($candidate)) {
            return $candidate;
        }

        return $viewBase;
    }

    /**
     * Per-RECORD template resolution (#1425).
     *
     * resolveTemplateView() above chooses one template for the whole instance
     * from a global setting. This variant lets an INDIVIDUAL record pick its
     * own standard: it reads the taxonomy-70 `code` of the record's
     * $displayStandardId (isad/dc/mods/rad/dacs/ric/…) and renders
     * "$viewBase-$code" when that blade exists. When the record has no display
     * standard, or its code has no dedicated blade, it falls back to the
     * instance-wide resolveTemplateView() - so nothing regresses for records
     * that never set one. Incidentally fixes DACS/RAD/MODS, whose blades
     * existed but were only ever reachable via the global setting.
     */
    public static function resolveObjectTemplateView(string $entityType, string $viewBase, string $defaultTemplate, ?int $displayStandardId): string
    {
        $code = self::standardCode($displayStandardId);
        if ($code !== null) {
            $candidate = $viewBase.'-'.$code;
            if (\Illuminate\Support\Facades\View::exists($candidate)) {
                return $candidate;
            }
        }

        return self::resolveTemplateView($entityType, $viewBase, $defaultTemplate);
    }

    /**
     * The taxonomy-70 natural-key `code` for a display-standard term id, or
     * null. Cached per request. Never throws (a missing term table on a bare
     * install just yields null).
     *
     * @var array<int,?string>
     */
    private static array $standardCodeCache = [];

    public static function standardCode(?int $displayStandardId): ?string
    {
        if (! $displayStandardId) {
            return null;
        }
        if (array_key_exists($displayStandardId, self::$standardCodeCache)) {
            return self::$standardCodeCache[$displayStandardId];
        }

        try {
            $code = DB::table('term')
                ->where('id', $displayStandardId)
                ->where('taxonomy_id', 70)
                ->value('code');
            $code = is_string($code) && trim($code) !== '' ? trim($code) : null;
        } catch (\Throwable $e) {
            $code = null;
        }

        return self::$standardCodeCache[$displayStandardId] = $code;
    }

    /**
     * The edit-route name for a record's description standard (#1425).
     *
     * Maps the taxonomy-70 code to its per-standard editor
     * (ric->ahgricmanage.edit, dacs->ahgdacsmanage.edit, …). Returns $fallback
     * whenever the record has no standard, the code is unknown, OR the target
     * route is not registered - the last case is what keeps a standalone /
     * minimal install (where a given *-manage package is absent) working: it
     * simply drops back to the generic editor. Route names are matched with
     * Route::has() rather than assumed.
     */
    public static function standardEditRoute(?int $displayStandardId, string $fallback = 'informationobject.edit'): string
    {
        $map = [
            'ric' => 'ahgricmanage.edit',
            'dacs' => 'ahgdacsmanage.edit',
            'dc' => 'ahgdcmanage.edit',
            'mods' => 'ahgmodsmanage.edit',
            'rad' => 'ahgradmanage.edit',
        ];

        $code = self::standardCode($displayStandardId);
        if ($code !== null && isset($map[$code])) {
            try {
                if (\Illuminate\Support\Facades\Route::has($map[$code])) {
                    return $map[$code];
                }
            } catch (\Throwable $e) {
                // fall through to fallback
            }
        }

        return $fallback;
    }

    /**
     * Check if the audit log feature is enabled.
     */
    public static function isAuditLogEnabled(): bool
    {
        $value = self::get('audit_log_enabled', '');
        if ($value === '') {
            try {
                $value = DB::table('setting')
                    ->leftJoin('setting_i18n', function ($j) {
                        $j->on('setting.id', '=', 'setting_i18n.id')
                            ->where('setting_i18n.culture', '=', 'en');
                    })
                    ->where('setting.name', 'audit_log_enabled')
                    ->value('setting_i18n.value');
            } catch (\Exception $e) {
                $value = null;
            }
        }

        // Default to true if the setting does not exist
        return $value === '' || $value === null || (bool) (int) $value;
    }
}
