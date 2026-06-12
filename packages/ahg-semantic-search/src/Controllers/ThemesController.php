<?php

/**
 * ThemesController - the PUBLIC "Explore by theme" discovery surface
 * (heratio#1210, generative scholarship + discovery slice).
 *
 * Themes are the collection's strongest subjects: the subject terms under which
 * the most PUBLISHED records sit. The surface frames them as "ways into the
 * collection" so a visitor can start from a theme rather than a search box.
 *
 *   GET /themes            index - a landing page of the strongest subject themes,
 *                                  each a card with its published-record count and
 *                                  a few example records.
 *   GET /themes/{termId}   show  - one theme: its label, scope note, and a
 *                                  paginated, bounded list of the published
 *                                  records filed under it, each linking to the
 *                                  record (and a "browse all in this theme" link
 *                                  into the canonical GLAM browse).
 *   GET /themes.json       json  - the machine-readable theme list (CORS-open).
 *
 * READ-ONLY and published-only: every record surfaced is published (status
 * type_id = 158 / status_id = 160), the catalogue root is excluded, and no table
 * is ever written. Every path degrades to an empty-state rather than a 500.
 * International, jurisdiction-neutral.
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
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

namespace AhgSemanticSearch\Controllers;

use AhgSemanticSearch\Services\ThemeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ThemesController extends Controller
{
    protected ThemeService $service;

    public function __construct()
    {
        $this->service = new ThemeService;
    }

    /**
     * Public landing: the strongest subject themes. Never 500s - any failure
     * renders the grounded empty-state.
     */
    public function index()
    {
        $themes = [];
        try {
            $themes = $this->service->topThemes(ThemeService::DEFAULT_THEMES);
        } catch (\Throwable $e) {
            Log::info('[themes] index failed: '.$e->getMessage());
        }

        return view('ahg-semantic-search::themes.index', [
            'themes' => $themes,
            'count' => count($themes),
        ]);
    }

    /**
     * Public detail for one theme (a subject term). Paginated, bounded record
     * list. Falls back to the themes index when the term is missing, is not a
     * subject term, or has no published records - never 500s.
     *
     * @param  int|string  $termId
     */
    public function show(Request $request, $termId)
    {
        $page = (int) $request->query('page', '1');
        if ($page < 1) {
            $page = 1;
        }

        $theme = null;
        try {
            $theme = $this->service->theme((int) $termId, $page, ThemeService::PER_PAGE);
        } catch (\Throwable $e) {
            Log::info('[themes] show('.$termId.') failed: '.$e->getMessage());
        }

        if ($theme === null) {
            return redirect()->route('themes.index');
        }

        return view('ahg-semantic-search::themes.show', [
            'theme' => $theme,
        ]);
    }

    /**
     * Machine-readable theme list (CORS-open, cacheable). Never 500s - degrades
     * to an empty themes array.
     */
    public function json(): JsonResponse
    {
        $themes = [];
        try {
            $themes = $this->service->themeList(ThemeService::MAX_THEMES);
        } catch (\Throwable $e) {
            Log::info('[themes] json failed: '.$e->getMessage());
        }

        $payload = [
            'surface' => 'themes',
            'description' => 'The collection strongest subject themes by published-record count.',
            'taxonomy' => 'subject',
            'count' => count($themes),
            'themes' => array_map(static function (array $t) {
                return [
                    'id' => $t['term_id'],
                    'label' => $t['label'],
                    'record_count' => $t['record_count'],
                    'url' => url('/themes/'.$t['term_id']),
                    'browse_url' => url('/glam/browse?subject='.$t['term_id']),
                ];
            }, $themes),
            'generated_at' => now()->toIso8601String(),
        ];

        return response()
            ->json($payload, 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Cache-Control', 'public, max-age=300');
    }
}
