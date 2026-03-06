<?php

namespace AhgInformationObjectManage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Migrated from /usr/share/nginx/archive/atom-ahg-plugins/ahgProvenancePlugin/
 */
class ProvenanceController extends Controller
{
    public function index(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        // Get provenance events for this object
        $events = DB::table('ahg_provenance_event')
            ->where('object_id', $io->id)
            ->orderBy('event_date', 'desc')
            ->get();

        return view('ahg-io-manage::provenance.index', [
            'io' => $io,
            'events' => $events,
        ]);
    }

    public function timeline(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $events = DB::table('ahg_provenance_event')
            ->where('object_id', $io->id)
            ->orderBy('event_date', 'asc')
            ->get();

        return view('ahg-io-manage::provenance.timeline', [
            'io' => $io,
            'events' => $events,
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
