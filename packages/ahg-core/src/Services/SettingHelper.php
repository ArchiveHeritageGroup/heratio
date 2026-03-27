<?php

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;

class SettingHelper
{
    private static array $cache = [];

    /**
     * Get a global AtoM setting value from the setting + setting_i18n tables.
     */
    public static function get(string $name, string $default = '', string $culture = 'en'): string
    {
        $key = "{$name}:{$culture}";

        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $value = DB::table('setting')
            ->leftJoin('setting_i18n', function ($j) use ($culture) {
                $j->on('setting.id', '=', 'setting_i18n.id')
                  ->where('setting_i18n.culture', '=', $culture);
            })
            ->where('setting.name', $name)
            ->whereNull('setting.scope')
            ->value('setting_i18n.value');

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
     * @param string $fieldName  The setting name (e.g. 'isad_identity_area')
     * @param array  $options    Optional: ['public' => true] to always check the setting
     * @return bool
     */
    public static function checkFieldVisibility(string $fieldName, array $options = []): bool
    {
        // The config key is set during boot: atom.element_visibility.<fieldName>
        $configValue = config("atom.element_visibility.{$fieldName}", true);

        // If 'public' option is set, always check the config value regardless of auth
        if (!empty($options['public'])) {
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
    }

    /**
     * Check if the audit log feature is enabled.
     */
    public static function isAuditLogEnabled(): bool
    {
        $value = self::get('audit_log_enabled', '');
        if ($value === '') {
            // Also check the setting without scope (global)
            $value = DB::table('setting')
                ->leftJoin('setting_i18n', function ($j) {
                    $j->on('setting.id', '=', 'setting_i18n.id')
                      ->where('setting_i18n.culture', '=', 'en');
                })
                ->where('setting.name', 'audit_log_enabled')
                ->value('setting_i18n.value');
        }
        // Default to true if the setting does not exist
        return $value === '' || $value === null || (bool) (int) $value;
    }
}
