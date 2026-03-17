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
}
