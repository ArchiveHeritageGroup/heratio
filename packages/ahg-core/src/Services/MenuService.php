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
     * Resolution model — DENY-BY-DEFAULT FOR NON-ADMINS:
     *
     *   - Anonymous (no user)  → globally enabled set (atom_plugin.is_enabled = 1)
     *   - Admin user           → globally enabled set, minus admin's own DENY
     *                            grants (admins can opt themselves out of a
     *                            plugin), minus their own user-hide preferences
     *   - Non-admin user       → ONLY plugins explicitly granted with
     *                            mode='allow' for this user. Default = nothing.
     *                            Their own user-hide preferences still subtract.
     *
     * `mode='allow'` for a non-admin: explicit opt-in by an admin (or for a
     * globally-disabled plugin — beta-tester case).
     * `mode='deny'`  for an admin: lets the admin remove a plugin from THEIR
     * own nav (rare, but symmetrical).
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

        $global    = self::getEnabledPlugins(null);
        $isAdmin   = self::userIsAdmin($userId);
        $adminOnly = self::adminOnlyPlugins();

        // Pull this user's per-grant rows once
        try {
            $grants  = DB::table('user_plugin_grant')
                ->where('user_id', $userId)
                ->whereIn('mode', ['allow', 'deny'])
                ->get(['plugin_name', 'mode']);
            $denied  = $grants->where('mode', 'deny')->pluck('plugin_name')->toArray();
            $allowed = $grants->where('mode', 'allow')->pluck('plugin_name')->toArray();
        } catch (\Exception $e) {
            $denied = $allowed = [];
        }

        if ($isAdmin) {
            // Admin: full global set minus their own denies, plus any globally-
            // disabled plugins the admin explicitly opted into. admin_only
            // doesn't affect admins.
            $effective = array_values(array_unique(array_merge(
                array_diff($global, $denied),
                $allowed,
            )));
        } else {
            // Non-admin: ONLY explicitly allowed plugins. Default = nothing.
            // admin_only plugins are stripped even if explicitly granted.
            $effective = array_values(array_diff($allowed, $adminOnly));
        }

        // User-level visibility (clutter reduction) — applies to both admin + user
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
     * Capability check — can this user reach a plugin's URL?
     *   - Anonymous          → uses global enabled state only
     *   - Admin              → has all globally enabled plus their own allows
     *                          minus their own denies
     *   - Non-admin          → only plugins explicitly mode='allow' for them
     *
     * Used by `PluginAccessMiddleware` — 403s requests to plugin URLs the
     * user has no capability for, regardless of their visibility preference.
     */
    public static function isPluginAccessible(string $name, ?int $userId = null): bool
    {
        if (null === $userId && function_exists('auth')) {
            try { $userId = auth()->user()?->id; } catch (\Throwable $e) {}
        }

        if ($userId === null) {
            // Anonymous request → fall back to global enable
            return in_array($name, self::getEnabledPlugins(null), true);
        }

        // Read this user's grant for the plugin (if any)
        try {
            $grant = DB::table('user_plugin_grant')
                ->where('user_id', $userId)
                ->where('plugin_name', $name)
                ->value('mode');
        } catch (\Exception $e) {
            $grant = null;
        }

        // admin_only check fires BEFORE the explicit grant — even an
        // explicitly-allowed non-admin can't see an admin-only plugin.
        $isAdmin = self::userIsAdmin($userId);
        if (!$isAdmin && in_array($name, self::adminOnlyPlugins(), true)) {
            return false;
        }

        if ($grant === 'allow') return true;
        if ($grant === 'deny')  return false;

        // No explicit grant → admin gets the global state, non-admin gets nothing.
        if ($isAdmin) {
            return in_array($name, self::getEnabledPlugins(null), true);
        }
        return false;
    }

    /**
     * Names of plugins flagged admin_only=1 in atom_plugin. Cached per-request.
     * When the column is missing (older DB), returns [].
     */
    private static function adminOnlyPlugins(): array
    {
        static $cache = null;
        if (null !== $cache) return $cache;
        try {
            // Detect the column once; absence = legacy schema, no admin-only filter.
            $hasCol = DB::selectOne(
                "SELECT COUNT(*) AS n FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'atom_plugin'
                   AND COLUMN_NAME  = 'admin_only'"
            )->n ?? 0;
            if (!$hasCol) return $cache = [];
            $cache = DB::table('atom_plugin')
                ->where('admin_only', 1)
                ->pluck('name')
                ->toArray();
        } catch (\Throwable $e) {
            $cache = [];
        }
        return $cache;
    }

    /**
     * Is this user an admin per AclService::canAdmin? Cached per-request.
     * Non-blocking: if AclService isn't loadable (CLI / boot context), returns
     * false (deny-by-default).
     */
    private static function userIsAdmin(int $userId): bool
    {
        static $adminCache = [];
        if (isset($adminCache[$userId])) return $adminCache[$userId];

        try {
            $is = \AhgCore\Services\AclService::canAdmin($userId);
        } catch (\Throwable $e) {
            $is = false;
        }
        return $adminCache[$userId] = (bool) $is;
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
