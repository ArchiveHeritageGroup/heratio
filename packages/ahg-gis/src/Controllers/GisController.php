<?php

/**
 * GisController - Controller for Heratio
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



namespace AhgGis\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class GisController extends Controller
{
    /**
     * Bounding box spatial search.
     */
    public function bbox(Request $request)
    {
        $north = $request->query('north', 90);
        $south = $request->query('south', -90);
        $east = $request->query('east', 180);
        $west = $request->query('west', -180);

        // AtoM `information_object` has no lat/lon columns; Heratio stores
        // geocoordinates in the `ahg_io_geolocation` extension table.
        $results = DB::table('ahg_io_geolocation as g')
            ->join('information_object as io', 'io.id', '=', 'g.information_object_id')
            ->leftJoin('information_object_i18n', function ($join) {
                $join->on('io.id', '=', 'information_object_i18n.id')
                     ->where('information_object_i18n.culture', '=', 'en');
            })
            ->leftJoin('slug', function ($join) {
                $join->on('io.id', '=', 'slug.object_id')
                     ->where('slug.slug', '!=', '');
            })
            ->whereNotNull('g.latitude')
            ->whereNotNull('g.longitude')
            ->whereBetween('g.latitude', [$south, $north])
            ->whereBetween('g.longitude', [$west, $east])
            ->select(
                'io.id',
                'information_object_i18n.title',
                'slug.slug',
                'g.latitude',
                'g.longitude'
            )
            ->limit(500)
            ->get();

        return view('ahg-gis::bbox', compact('results', 'north', 'south', 'east', 'west'));
    }

    /**
     * Radius spatial search.
     */
    public function radius(Request $request)
    {
        $lat = $request->query('lat', 0);
        $lng = $request->query('lng', 0);
        $radiusKm = $request->query('radius', 10);

        return view('ahg-gis::radius', compact('lat', 'lng', 'radiusKm'));
    }

    /**
     * GeoJSON endpoint for map display.
     */
    public function geojson(Request $request)
    {
        $features = DB::table('ahg_io_geolocation as g')
            ->join('information_object as io', 'io.id', '=', 'g.information_object_id')
            ->leftJoin('information_object_i18n', function ($join) {
                $join->on('io.id', '=', 'information_object_i18n.id')
                     ->where('information_object_i18n.culture', '=', 'en');
            })
            ->leftJoin('slug', function ($join) {
                $join->on('io.id', '=', 'slug.object_id')
                     ->where('slug.slug', '!=', '');
            })
            ->whereNotNull('g.latitude')
            ->whereNotNull('g.longitude')
            ->select(
                'io.id',
                'information_object_i18n.title',
                'slug.slug',
                'g.latitude',
                'g.longitude'
            )
            ->limit(1000)
            ->get();

        if ($request->wantsJson()) {
            $geojson = [
                'type' => 'FeatureCollection',
                'features' => $features->map(fn($f) => [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [(float) $f->longitude, (float) $f->latitude],
                    ],
                    'properties' => [
                        'id' => $f->id,
                        'title' => $f->title,
                        'slug' => $f->slug,
                    ],
                ])->values()->all(),
            ];
            return response()->json($geojson);
        }

        return view('ahg-gis::geojson', compact('features'));
    }
}
