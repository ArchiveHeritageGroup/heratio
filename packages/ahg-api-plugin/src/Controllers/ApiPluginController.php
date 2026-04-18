<?php

/**
 * ApiPluginController - Controller for Heratio
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



namespace AhgApiPlugin\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API Plugin Controller — admin interface for searching information objects via API.
 * Migrated from ahgAPIPlugin.
 */
class ApiPluginController extends Controller
{
    /**
     * Search information objects — admin search interface.
     */
    public function searchInformationObjects(Request $request)
    {
        $query = trim($request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;
        $results = collect();
        $total = 0;

        if ($query !== '') {
            $baseQuery = DB::table('information_object as io')
                ->join('information_object_i18n as ioi', function ($join) {
                    $join->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
                })
                ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
                ->where(function ($q) use ($query) {
                    $q->where('ioi.title', 'like', "%{$query}%")
                      ->orWhere('io.identifier', 'like', "%{$query}%")
                      ->orWhere('ioi.scope_and_content', 'like', "%{$query}%");
                })
                ->where('io.id', '!=', 1);

            $total = (clone $baseQuery)->count();

            $results = $baseQuery
                ->select(
                    'io.id',
                    'io.identifier',
                    'io.level_of_description_id',
                    'io.parent_id',
                    'io.repository_id',
                    'ioi.title',
                    'slug.slug'
                )
                ->orderBy('ioi.title')
                ->offset($offset)
                ->limit($limit)
                ->get();
        }

        $totalPages = $total > 0 ? (int) ceil($total / $limit) : 1;

        return view('ahg-api-plugin::search-information-objects', compact('results', 'query', 'total', 'page', 'totalPages'));
    }
}
