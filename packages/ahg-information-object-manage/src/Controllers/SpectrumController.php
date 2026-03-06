<?php

namespace AhgInformationObjectManage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Migrated from /usr/share/nginx/archive/atom-ahg-plugins/ahgSpectrumPlugin/
 * and /usr/share/nginx/archive/atom-ahg-plugins/ahgHeritageAccountingPlugin/
 */
class SpectrumController extends Controller
{
    /**
     * Spectrum data for an IO.
     */
    public function index(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        return view('ahg-io-manage::spectrum.index', [
            'io' => $io,
        ]);
    }

    /**
     * Heritage asset accounting for an IO.
     */
    public function heritage(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        return view('ahg-io-manage::spectrum.heritage', [
            'io' => $io,
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
