<?php

/**
 * AiController - Controller for Heratio
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

use AhgInformationObjectManage\Services\AiNerService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Migrated from /usr/share/nginx/archive/atom-ahg-plugins/ahgAIPlugin/
 */
class AiController extends Controller
{
    protected AiNerService $nerService;

    public function __construct(AiNerService $nerService)
    {
        $this->nerService = $nerService;
    }

    /**
     * Extract named entities (NER) from an IO's description text.
     * AtoM route: /ai/ner/extract/:id
     */
    public function extract(int $id)
    {
        $io = $this->getIOById($id);
        if (!$io) {
            abort(404);
        }

        // Get existing entities for this object
        $entities = $this->nerService->getEntitiesForObject($id);

        // Get entity links (entities linked to actors)
        $entityLinks = $this->nerService->getEntityLinks($id);

        // Get extraction history
        $extractionHistory = $this->nerService->getExtractionHistory($id);

        return view('ahg-io-manage::ai.extract', [
            'io'                => $io,
            'entities'          => $entities,
            'entityLinks'       => $entityLinks,
            'extractionHistory' => $extractionHistory,
        ]);
    }

    /**
     * NER Review dashboard.
     * AtoM route: /ai/ner/review
     */
    public function review()
    {
        // Get objects with pending entities, grouped by object
        $pending = $this->nerService->getPendingExtractions();

        return view('ahg-io-manage::ai.review', [
            'pending' => $pending,
        ]);
    }

    /**
     * Generate summary for an IO.
     * Displays existing scope_and_content for summary review (no external API call).
     */
    public function summarize(int $id)
    {
        $io = $this->getIOById($id);
        if (!$io) {
            abort(404);
        }

        return view('ahg-io-manage::ai.summarize', [
            'io' => $io,
        ]);
    }

    /**
     * Translate an IO's description.
     * Displays existing scope_and_content for translation review (no external API call).
     */
    public function translate(int $id)
    {
        $io = $this->getIOById($id);
        if (!$io) {
            abort(404);
        }

        // Get available i18n cultures for this IO
        try {
            $availableCultures = DB::table('information_object_i18n')
                ->where('id', $id)
                ->pluck('culture')
                ->toArray();
        } catch (\Illuminate\Database\QueryException $e) {
            $availableCultures = [app()->getLocale()];
        }

        return view('ahg-io-manage::ai.translate', [
            'io'                => $io,
            'availableCultures' => $availableCultures,
        ]);
    }

    /**
     * Fetch an IO by ID with i18n data and slug.
     */
    private function getIOById(int $id): ?object
    {
        $culture = app()->getLocale();

        return DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', $culture);
            })
            ->join('slug as s', 's.object_id', '=', 'io.id')
            ->where('io.id', $id)
            ->select(
                'io.id',
                'i18n.title',
                'i18n.scope_and_content',
                'i18n.archival_history',
                'i18n.arrangement',
                's.slug'
            )
            ->first();
    }
}
