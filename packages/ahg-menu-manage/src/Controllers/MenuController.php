<?php

namespace AhgMenuManage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class MenuController extends Controller
{
    public function browse()
    {
        $culture = app()->getLocale();

        $menus = DB::table('menu')
            ->leftJoin('menu_i18n', function ($join) use ($culture) {
                $join->on('menu.id', '=', 'menu_i18n.id')
                    ->where('menu_i18n.culture', '=', $culture);
            })
            ->select([
                'menu.id',
                'menu.parent_id',
                'menu.name',
                'menu.path',
                'menu.lft',
                'menu.rgt',
                'menu.created_at',
                'menu.updated_at',
                'menu.source_culture',
                'menu.serial_number',
                'menu_i18n.label',
                'menu_i18n.description',
            ])
            ->orderBy('menu.lft', 'asc')
            ->get()
            ->map(function ($item) {
                return (array) $item;
            })
            ->toArray();

        // Build tree structure
        $tree = $this->buildTree($menus);

        return view('ahg-menu-manage::browse', [
            'tree' => $tree,
            'total' => count($menus),
        ]);
    }

    public function show(int $id)
    {
        $culture = app()->getLocale();

        $menu = DB::table('menu')
            ->leftJoin('menu_i18n', function ($join) use ($culture) {
                $join->on('menu.id', '=', 'menu_i18n.id')
                    ->where('menu_i18n.culture', '=', $culture);
            })
            ->where('menu.id', $id)
            ->select([
                'menu.id',
                'menu.parent_id',
                'menu.name',
                'menu.path',
                'menu.lft',
                'menu.rgt',
                'menu.created_at',
                'menu.updated_at',
                'menu.source_culture',
                'menu.serial_number',
                'menu_i18n.label',
                'menu_i18n.description',
            ])
            ->first();

        if (!$menu) {
            abort(404);
        }

        // Get children
        $children = DB::table('menu')
            ->leftJoin('menu_i18n', function ($join) use ($culture) {
                $join->on('menu.id', '=', 'menu_i18n.id')
                    ->where('menu_i18n.culture', '=', $culture);
            })
            ->where('menu.parent_id', $id)
            ->select([
                'menu.id',
                'menu.parent_id',
                'menu.name',
                'menu.path',
                'menu.lft',
                'menu.rgt',
                'menu.created_at',
                'menu.updated_at',
                'menu.source_culture',
                'menu.serial_number',
                'menu_i18n.label',
                'menu_i18n.description',
            ])
            ->orderBy('menu.lft', 'asc')
            ->get();

        return view('ahg-menu-manage::show', [
            'menu' => $menu,
            'children' => $children,
        ]);
    }

    /**
     * Build a nested tree from flat menu list using parent_id.
     */
    private function buildTree(array $items, ?int $parentId = null): array
    {
        $tree = [];

        foreach ($items as $item) {
            if ($item['parent_id'] == $parentId) {
                $item['children'] = $this->buildTree($items, $item['id']);
                $tree[] = $item;
            }
        }

        return $tree;
    }
}
