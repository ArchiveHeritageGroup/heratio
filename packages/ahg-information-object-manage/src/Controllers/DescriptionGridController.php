<?php

/**
 * DescriptionGridController - grid editing of descriptions (heratio#1581).
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

namespace AhgInformationObjectManage\Controllers;

use AhgInformationObjectManage\Services\DescriptionGridService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DescriptionGridController extends Controller
{
    public function __construct(private DescriptionGridService $grid) {}

    /**
     * GET /informationobject/grid?collection=<slug or id> | ?ids=1,2,3, &page=N
     */
    public function index(Request $request)
    {
        $collection = null;
        $scope = [];
        if ($request->filled('collection')) {
            $key = (string) $request->query('collection');
            $id = ctype_digit($key) ? (int) $key : (int) DB::table('slug')->where('slug', $key)->value('object_id');
            $collection = DB::table('information_object as io')->leftJoin('slug as s', 's.object_id', '=', 'io.id')
                ->where('io.id', $id)->where('io.id', '>', 1)->first(['io.id', 's.slug']);
            abort_unless($collection, 404);
            $collection->title = \AhgInformationObjectManage\Services\InformationObjectService::getById((int) $collection->id)?->title;
            $scope = ['collection' => (int) $collection->id];
        } elseif ($request->filled('ids')) {
            $scope = ['ids' => array_filter(array_map('intval', explode(',', (string) $request->query('ids'))))];
        }

        $rows = $scope ? $this->grid->page($scope, (int) $request->query('page', 1), Auth::id())->withQueryString() : null;

        return view('ahg-io-manage::grid.index', [
            'collection' => $collection,
            'rows' => $rows,
            'columns' => $this->grid->columns(),
            'defaultColumns' => DescriptionGridService::DEFAULT_COLUMNS,
            'longText' => DescriptionGridService::LONG_TEXT,
            'levels' => $this->grid->levels(),
            'culture' => app()->getLocale(),
        ]);
    }

    /**
     * POST /informationobject/grid/save - one row: id, updated_at, fields{column: value}.
     */
    public function save(Request $request): JsonResponse
    {
        $v = $request->validate([
            'id' => 'required|integer|min:2',
            'updated_at' => 'required|string|max:32',
            'fields' => 'present|array',
        ]);
        // Normalise "" to null the way the edit form's inputs arrive.
        $fields = array_map(fn ($x) => is_string($x) && trim($x) === '' ? null : $x, $v['fields']);

        $result = $this->grid->saveRow((int) $v['id'], $v['updated_at'], $fields, Auth::id());

        return response()->json($result, $result['status']);
    }
}
