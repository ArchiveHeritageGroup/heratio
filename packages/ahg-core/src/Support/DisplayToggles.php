<?php

namespace AhgCore\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Site-wide display toggles from the AtoM-inherited `setting` table.
 *
 * These are the switches on Admin -> Settings -> Default page elements. They
 * live in `setting` / `setting_i18n`, not `ahg_settings`, because the form was
 * ported from AtoM along with its storage.
 */
class DisplayToggles
{
    /** Cached per connection: a test suite can swap to a different database mid-process. */
    private static array $cache = [];

    /**
     * Is the digital-object carousel enabled? (#1493)
     *
     * DEFAULTS TO TRUE when the setting is absent. Instances installed fresh
     * have no `toggle*` rows at all - the page-elements form creates them on
     * first submit - so treating "missing" as "off" would silently remove the
     * carousel from every new install. Absent means "nobody has expressed a
     * preference", which is not the same as "off".
     */
    public static function carousel(): bool
    {
        return self::flag('toggleIoSlider', true);
    }

    /** Read a boolean toggle, memoised per connection. */
    public static function flag(string $name, bool $default): bool
    {
        $conn = DB::connection()->getName();
        $key = $conn.'|'.$name;

        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $value = $default;

        if (Schema::hasTable('setting') && Schema::hasTable('setting_i18n')) {
            $row = DB::table('setting')
                ->join('setting_i18n', function ($j) {
                    $j->on('setting_i18n.id', '=', 'setting.id')
                        ->where('setting_i18n.culture', '=', 'en');
                })
                ->where('setting.name', $name)
                ->value('setting_i18n.value');

            // A row that exists but holds '' is a real answer: the page-elements
            // form writes an empty string for an unticked box, so absent and
            // empty must NOT be treated the same way.
            if ($row !== null) {
                $value = filter_var($row, FILTER_VALIDATE_BOOLEAN);
            }
        }

        return self::$cache[$key] = $value;
    }

    /** Drop the memo - for tests that change a setting mid-process. */
    public static function forget(): void
    {
        self::$cache = [];
    }
}
