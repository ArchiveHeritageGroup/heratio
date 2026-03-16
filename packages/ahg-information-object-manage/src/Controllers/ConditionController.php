<?php

namespace AhgInformationObjectManage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Migrated from /usr/share/nginx/archive/atom-ahg-plugins/ahgConditionPlugin/
 */
class ConditionController extends Controller
{
    public function index(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        // Get condition checks for this object
        try {
            $checks = DB::table('ahg_condition_check')
                ->where('object_id', $io->id)
                ->orderBy('check_date', 'desc')
                ->get();
        } catch (\Illuminate\Database\QueryException $e) {
            $checks = collect();
        }

        return view('ahg-io-manage::condition.index', [
            'io' => $io,
            'checks' => $checks,
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
