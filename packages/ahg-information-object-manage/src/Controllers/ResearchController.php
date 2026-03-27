<?php

namespace AhgInformationObjectManage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Migrated from /usr/share/nginx/archive/atom-ahg-plugins/ahgResearchPlugin/
 */
class ResearchController extends Controller
{
    /**
     * Source assessment for an IO.
     */
    public function sourceAssessment(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        return view('ahg-io-manage::research.assessment', [
            'io' => $io,
        ]);
    }

    /**
     * Annotation studio for an IO.
     */
    public function annotations(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        return view('ahg-io-manage::research.annotations', [
            'io' => $io,
        ]);
    }

    /**
     * Trust score for an IO.
     */
    public function trustScore(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        return view('ahg-io-manage::research.trust', [
            'io' => $io,
        ]);
    }

    /**
     * Research dashboard.
     */
    public function dashboard()
    {
        return view('ahg-io-manage::research.dashboard');
    }

    /**
     * Generate citation for an IO.
     * Migrated from ahgResearchPlugin citation action + ahgDoiPlugin.
     */
    public function citation(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $culture = app()->getLocale();

        // Get creators
        $creators = DB::table('event')
            ->join('actor_i18n', function ($j) use ($culture) {
                $j->on('actor_i18n.id', '=', 'event.actor_id')->where('actor_i18n.culture', $culture);
            })
            ->where('event.object_id', $io->id)
            ->where('event.type_id', 111)
            ->select('actor_i18n.authorized_form_of_name as name')
            ->get();

        // Get repository
        $repository = DB::table('information_object as io2')
            ->join('actor_i18n as repo_ai', function ($j) use ($culture) {
                $j->on('repo_ai.id', '=', 'io2.repository_id')->where('repo_ai.culture', $culture);
            })
            ->where('io2.id', $io->id)
            ->select('repo_ai.authorized_form_of_name as name')
            ->first();

        // Get dates
        $dates = DB::table('event')
            ->where('object_id', $io->id)
            ->whereNotNull('date_display')
            ->select('date_display')
            ->first();

        return view('ahg-io-manage::research.citation', [
            'io' => $io,
            'creators' => $creators,
            'repository' => $repository,
            'dates' => $dates,
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
            ->select('io.id', 'i18n.title', 'i18n.scope_and_content', 's.slug')
            ->first();
    }
}
