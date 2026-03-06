<?php

namespace AhgInformationObjectManage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Migrated from /usr/share/nginx/archive/atom-ahg-plugins/ahgPreservationPlugin/
 */
class PreservationController extends Controller
{
    /**
     * Show preservation packages for an IO.
     */
    public function index(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        // Get AIPs linked to this object
        $aips = DB::table('aip')
            ->where('object_id', $io->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Get PREMIS events
        $premisObjects = DB::table('premis_object')
            ->where('information_object_id', $io->id)
            ->get();

        return view('ahg-io-manage::preservation.index', [
            'io' => $io,
            'aips' => $aips,
            'premisObjects' => $premisObjects,
        ]);
    }

    private function getIO(string $slug): ?object
    {
        $culture = app()->getLocale();

        return DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', $culture);
            })
            ->join('slug as s', 's.object_id', '=', 'io.id')
            ->where('s.slug', $slug)
            ->select('io.id', 'i18n.title', 's.slug')
            ->first();
    }
}
