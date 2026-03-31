<?php

/**
 * MenuController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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


use AhgMenuManage\Services\MenuService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    protected MenuService $service;

    public function __construct()
    {
        $this->service = new MenuService(app()->getLocale());
    }

    /**
     * Browse: show the full menu tree.
     */
    public function browse()
    {
        $tree = $this->service->getTree();

        return view('ahg-menu-manage::browse', [
            'tree' => $tree,
            'total' => count($tree),
        ]);
    }

    /**
     * Show a single menu item.
     */
    public function show(int $id)
    {
        $menu = $this->service->getById($id);

        if (!$menu) {
            abort(404);
        }

        // Get children from the tree (direct children only)
        $culture = app()->getLocale();
        $children = \Illuminate\Support\Facades\DB::table('menu')
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
            'menu' => (object) $menu,
            'children' => $children,
        ]);
    }

    /**
     * Show the create form.
     */
    public function create()
    {
        $parentChoices = $this->service->getParentChoices();

        return view('ahg-menu-manage::edit', [
            'menu' => null,
            'parentChoices' => $parentChoices,
        ]);
    }

    /**
     * Show the edit form.
     */
    public function edit(int $id)
    {
        $menu = $this->service->getById($id);

        if (!$menu) {
            abort(404);
        }

        $parentChoices = $this->service->getParentChoices();

        return view('ahg-menu-manage::edit', [
            'menu' => (object) $menu,
            'parentChoices' => $parentChoices,
        ]);
    }

    /**
     * Store a new menu item.
     */
    public function store(Request $request)
    {
        $request->validate([
            'label' => 'required|string|max:255',
            'name' => 'nullable|string|max:255',
            'path' => 'nullable|string|max:255',
            'parent_id' => 'nullable|integer',
            'description' => 'nullable|string',
        ]);

        $data = [
            'label' => $request->input('label'),
            'name' => $request->input('name'),
            'path' => $request->input('path'),
            'parentId' => $request->input('parent_id', MenuService::ROOT_ID),
            'description' => $request->input('description'),
        ];

        $newId = $this->service->create($data);

        return redirect()
            ->route('menu.show', $newId)
            ->with('success', 'Menu item created successfully.');
    }

    /**
     * Update an existing menu item.
     */
    public function update(Request $request, int $id)
    {
        $menu = $this->service->getById($id);

        if (!$menu) {
            abort(404);
        }

        $request->validate([
            'label' => 'required|string|max:255',
            'name' => 'nullable|string|max:255',
            'path' => 'nullable|string|max:255',
            'parent_id' => 'nullable|integer',
            'description' => 'nullable|string',
        ]);

        $data = [
            'label' => $request->input('label'),
            'name' => $request->input('name'),
            'path' => $request->input('path'),
            'parentId' => $request->input('parent_id', MenuService::ROOT_ID),
            'description' => $request->input('description'),
        ];

        $this->service->update($id, $data);

        return redirect()
            ->route('menu.show', $id)
            ->with('success', 'Menu item updated successfully.');
    }

    /**
     * Show delete confirmation page.
     */
    public function confirmDelete(int $id)
    {
        $menu = $this->service->getById($id);

        if (!$menu) {
            abort(404);
        }

        return view('ahg-menu-manage::delete', [
            'menu' => (object) $menu,
        ]);
    }

    /**
     * Delete a menu item.
     */
    public function destroy(Request $request, int $id)
    {
        $menu = $this->service->getById($id);

        if (!$menu) {
            abort(404);
        }

        try {
            $this->service->delete($id);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('menu.show', $id)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('menu.browse')
            ->with('success', 'Menu item deleted successfully.');
    }

    /**
     * Move a menu item up (swap with previous sibling).
     */
    public function moveUp(int $id)
    {
        $menu = \Illuminate\Support\Facades\DB::table('menu')->where('id', $id)->first();
        if (!$menu) {
            abort(404);
        }

        // Find previous sibling (same parent, lower lft)
        $prev = \Illuminate\Support\Facades\DB::table('menu')
            ->where('parent_id', $menu->parent_id)
            ->where('lft', '<', $menu->lft)
            ->orderBy('lft', 'desc')
            ->first();

        if ($prev) {
            // Swap lft/rgt values
            $menuLft = $menu->lft;
            $menuRgt = $menu->rgt;
            $prevLft = $prev->lft;
            $prevRgt = $prev->rgt;

            \Illuminate\Support\Facades\DB::table('menu')->where('id', $id)->update(['lft' => $prevLft, 'rgt' => $prevRgt]);
            \Illuminate\Support\Facades\DB::table('menu')->where('id', $prev->id)->update(['lft' => $menuLft, 'rgt' => $menuRgt]);
        }

        return redirect()->route('menu.browse');
    }

    /**
     * Move a menu item down (swap with next sibling).
     */
    public function moveDown(int $id)
    {
        $menu = \Illuminate\Support\Facades\DB::table('menu')->where('id', $id)->first();
        if (!$menu) {
            abort(404);
        }

        // Find next sibling (same parent, higher lft)
        $next = \Illuminate\Support\Facades\DB::table('menu')
            ->where('parent_id', $menu->parent_id)
            ->where('lft', '>', $menu->lft)
            ->orderBy('lft', 'asc')
            ->first();

        if ($next) {
            $menuLft = $menu->lft;
            $menuRgt = $menu->rgt;
            $nextLft = $next->lft;
            $nextRgt = $next->rgt;

            \Illuminate\Support\Facades\DB::table('menu')->where('id', $id)->update(['lft' => $nextLft, 'rgt' => $nextRgt]);
            \Illuminate\Support\Facades\DB::table('menu')->where('id', $next->id)->update(['lft' => $menuLft, 'rgt' => $menuRgt]);
        }

        return redirect()->route('menu.browse');
    }
}
