<?php

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

        $results = DB::table('information_object')
            ->leftJoin('information_object_i18n', function ($join) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                     ->where('information_object_i18n.culture', '=', 'en');
            })
            ->leftJoin('slug', function ($join) {
                $join->on('information_object.id', '=', 'slug.object_id')
                     ->where('slug.name', '!=', '');
            })
            ->whereNotNull('information_object.latitude')
            ->whereNotNull('information_object.longitude')
            ->whereBetween('information_object.latitude', [$south, $north])
            ->whereBetween('information_object.longitude', [$west, $east])
            ->select(
                'information_object.id',
                'information_object_i18n.title',
                'slug.slug',
                'information_object.latitude',
                'information_object.longitude'
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
        $features = DB::table('information_object')
            ->leftJoin('information_object_i18n', function ($join) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                     ->where('information_object_i18n.culture', '=', 'en');
            })
            ->leftJoin('slug', function ($join) {
                $join->on('information_object.id', '=', 'slug.object_id')
                     ->where('slug.name', '!=', '');
            })
            ->whereNotNull('information_object.latitude')
            ->whereNotNull('information_object.longitude')
            ->select(
                'information_object.id',
                'information_object_i18n.title',
                'slug.slug',
                'information_object.latitude',
                'information_object.longitude'
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
