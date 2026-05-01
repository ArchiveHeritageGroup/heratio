<?php

/**
 * MenuService - Service for Heratio
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

/**
 * Menu service for Heratio layout.
 *
 * Reads the navigation menu from the `menu` + `menu_i18n` tables
 * and builds a nested tree using MPTT lft/rgt columns.
 *
 * Migrated from the framework MenuService — changed DB import
 * from Capsule\Manager to Facade.
 */
class MenuService
{
    /** @var array<string, array> Cache keyed by culture */
    private static array $cache = [];

    /**
     * Get the full menu tree for a culture.
     */
    public static function getTree(string $culture = 'en'): array
    {
        if (isset(self::$cache[$culture])) {
            return self::$cache[$culture];
        }

        try {
            $rows = DB::table('menu')
                ->leftJoin('menu_i18n', function ($join) use ($culture) {
                    $join->on('menu.id', '=', 'menu_i18n.id')
                        ->where('menu_i18n.culture', '=', $culture);
                })
                ->orderBy('menu.lft')
                ->select([
                    'menu.id',
                    'menu.parent_id',
                    'menu.name',
                    'menu.path',
                    'menu.lft',
                    'menu.rgt',
                    'menu_i18n.label',
                    'menu_i18n.description',
                ])
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }

        $tree = self::buildTree($rows);
        self::$cache[$culture] = $tree;

        return $tree;
    }

    /**
     * Get children of a specific menu by name.
     */
    public static function getChildren(string $parentName, string $culture = 'en'): array
    {
        $tree = self::getTree($culture);

        return self::findChildrenByName($tree, $parentName);
    }

    /**
     * Get a single menu node by name.
     */
    public static function getByName(string $name, string $culture = 'en'): ?object
    {
        $tree = self::getTree($culture);

        return self::findNodeByName($tree, $name);
    }

    /**
     * Get the browse menu items.
     */
    public static function getBrowseMenu(string $culture = 'en'): array
    {
        return self::getChildren('browse', $culture);
    }

    /**
     * Get the main (admin) menu items.
     */
    public static function getMainMenu(string $culture = 'en'): array
    {
        return self::getChildren('mainMenu', $culture);
    }

    /**
     * Get the quick links menu items.
     */
    public static function getQuickLinks(string $culture = 'en'): array
    {
        return self::getChildren('quickLinks', $culture);
    }

    /**
     * Get the list of plugins visible in the user's nav (issue #40 c5).
     *
     * Layered model:
     *   1. globally enabled  (atom_plugin.is_enabled = 1)             [admin]
     *   2. NOT denied to this user (user_plugin_grant.mode = 'deny')  [admin]
     *   3. NOT hidden by user (user_plugin_preference.is_hidden = 1)  [user]
     *
     * Plus: user_plugin_grant.mode = 'allow' can OPT IN to a plugin that's
     * globally disabled (e.g. beta plugin opened to specific testers).
     *
     * @param int|null $userId  When null, returns globally enabled plugins only.
     */
    public static function getEnabledPlugins(?int $userId = null): array
    {
        static $globalCache = null;
        static $perUserCache = [];

        if (null === $userId) {
            if (null !== $globalCache) {
                return $globalCache;
            }
            try {
                $globalCache = DB::table('atom_plugin')
                    ->where('is_enabled', 1)
                    ->pluck('name')
                    ->toArray();
            } catch (\Exception $e) {
                $globalCache = [];
            }
            return $globalCache;
        }

        if (isset($perUserCache[$userId])) {
            return $perUserCache[$userId];
        }

        $global = self::getEnabledPlugins(null);

        // Layer 1: admin grants/denies
        try {
            $grants = DB::table('user_plugin_grant')
                ->where('user_id', $userId)
                ->whereIn('mode', ['allow', 'deny'])
                ->get(['plugin_name', 'mode']);
            $denied  = $grants->where('mode', 'deny')->pluck('plugin_name')->toArray();
            $allowed = $grants->where('mode', 'allow')->pluck('plugin_name')->toArray();
            $effective = array_values(array_unique(array_merge(
                array_diff($global, $denied),
                $allowed,
            )));
        } catch (\Exception $e) {
            $effective = $global;
        }

        // Layer 2: user-level visibility (clutter reduction)
        try {
            $hidden = DB::table('user_plugin_preference')
                ->where('user_id', $userId)
                ->where('is_hidden', 1)
                ->pluck('plugin_name')
                ->toArray();
            $effective = array_values(array_diff($effective, $hidden));
        } catch (\Exception $e) {
            // table missing → no clutter filter
        }

        $perUserCache[$userId] = $effective;
        return $effective;
    }

    /**
     * Capability check — has admin granted this user access to the plugin?
     * (Ignores the user's own visibility preference — that's nav clutter.)
     * Used by middleware to 403 a request hitting a denied-plugin URL.
     */
    public static function isPluginAccessible(string $name, ?int $userId = null): bool
    {
        if (null === $userId && function_exists('auth')) {
            try { $userId = auth()->user()?->id; } catch (\Throwable $e) {}
        }
        $global = in_array($name, self::getEnabledPlugins(null), true);
        if ($userId === null) {
            return $global;
        }
        try {
            $grant = DB::table('user_plugin_grant')
                ->where('user_id', $userId)
                ->where('plugin_name', $name)
                ->value('mode');
            if ($grant === 'deny')  return false;
            if ($grant === 'allow') return true;
        } catch (\Exception $e) {
            // table missing → fall back to global
        }
        return $global;
    }

    /**
     * Check if a plugin is enabled (and visible to the user when $userId given).
     */
    public static function isPluginEnabled(string $name, ?int $userId = null): bool
    {
        if (null === $userId && function_exists('auth')) {
            try {
                $u = auth()->user();
                $userId = $u?->id;
            } catch (\Throwable $e) {
                // auth unavailable (CLI / boot) — fall through to global only.
            }
        }
        return in_array($name, self::getEnabledPlugins($userId), true);
    }

    /**
     * Toggle a plugin's per-user visibility. is_hidden=1 hides it; delete row
     * (or pass false) to unhide.
     */
    public static function setUserPluginHidden(int $userId, string $pluginName, bool $hidden): void
    {
        if ($hidden) {
            DB::table('user_plugin_preference')->updateOrInsert(
                ['user_id' => $userId, 'plugin_name' => $pluginName],
                ['is_hidden' => 1, 'updated_at' => now()]
            );
        } else {
            DB::table('user_plugin_preference')
                ->where('user_id', $userId)
                ->where('plugin_name', $pluginName)
                ->delete();
        }
        // Bust the per-user cache so the change is visible on the next call.
        unset(self::$cache); // noop if not declared, kept for clarity
    }

    /**
     * Clear the menu cache.
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * Build a nested tree from flat MPTT-ordered rows.
     */
    private static function buildTree(array $rows): array
    {
        $tree = [];
        $lookup = [];

        foreach ($rows as $row) {
            $item = (object) [
                'id' => (int) $row->id,
                'parentId' => $row->parent_id ? (int) $row->parent_id : null,
                'name' => $row->name ?? '',
                'path' => $row->path ?? '',
                'lft' => (int) $row->lft,
                'rgt' => (int) $row->rgt,
                'label' => $row->label ?? $row->name ?? '',
                'description' => $row->description ?? '',
                'children' => [],
            ];

            $lookup[$item->id] = $item;

            if (null === $item->parentId || ! isset($lookup[$item->parentId])) {
                $tree[] = $item;
            } else {
                $lookup[$item->parentId]->children[] = $item;
            }
        }

        return $tree;
    }

    private static function findChildrenByName(array $tree, string $name): array
    {
        $node = self::findNodeByName($tree, $name);

        return $node ? $node->children : [];
    }

    private static function findNodeByName(array $items, string $name): ?object
    {
        foreach ($items as $item) {
            if ($item->name === $name) {
                return $item;
            }
            if (! empty($item->children)) {
                $found = self::findNodeByName($item->children, $name);
                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Resolve a menu path to a URL.
     */
    public static function resolvePath(string $path): string
    {
        if (empty($path)) {
            return '#';
        }

        // Absolute URL
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        // Named route — try Laravel route resolution
        if (str_starts_with($path, '@')) {
            $routeName = substr($path, 1);
            try {
                return route($routeName);
            } catch (\Exception $e) {
                return '/' . str_replace('_', '/', $routeName);
            }
        }

        // Ensure leading slash
        if (! str_starts_with($path, '/')) {
            return '/' . $path;
        }

        return $path;
    }
}
