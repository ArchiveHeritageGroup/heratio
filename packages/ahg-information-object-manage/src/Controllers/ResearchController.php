<?php

/**
 * ResearchController - Controller for Heratio
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

        // Get dates (date_display is in event_i18n.date, not event table)
        $dates = DB::table('event')
            ->join('event_i18n', function ($j) use ($culture) {
                $j->on('event.id', '=', 'event_i18n.id')->where('event_i18n.culture', '=', $culture);
            })
            ->where('event.object_id', $io->id)
            ->whereNotNull('event_i18n.date')
            ->select('event_i18n.date as date_display')
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
