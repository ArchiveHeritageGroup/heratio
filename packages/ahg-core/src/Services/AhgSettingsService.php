<?php

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;

/**
 * AHG Settings service.
 *
 * Reads from `ahg_settings` table (key/value with setting_group).
 * Migrated from AtomExtensions\Services\AhgSettingsService.
 */
class AhgSettingsService
{
    /** @var array|null Cached settings */
    private static ?array $cache = null;

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
}
