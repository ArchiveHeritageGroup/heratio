<?php

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
     * Get enabled plugins list from atom_plugin table.
     */
    public static function getEnabledPlugins(): array
    {
        static $plugins = null;
        if (null !== $plugins) {
            return $plugins;
        }

        try {
            $plugins = DB::table('atom_plugin')
                ->where('is_enabled', 1)
                ->pluck('name')
                ->toArray();
        } catch (\Exception $e) {
            $plugins = [];
        }

        return $plugins;
    }

    /**
     * Check if a plugin is enabled.
     */
    public static function isPluginEnabled(string $name): bool
    {
        return in_array($name, self::getEnabledPlugins());
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
