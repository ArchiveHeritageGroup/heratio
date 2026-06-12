<?php

/**
 * ExploreCollectionController - the PUBLIC "Explore the collection" hub.
 *
 * One coherent public entry point into the browse-by discovery surfaces this
 * package already ships (Explore by theme, Browse by place, People and
 * organisations, and the Collection timeline). It is a HUB, not a new surface:
 * it REUSES the existing read-only services for a small, already-bounded teaser
 * from each, then links onward to the full slice page.
 *
 *   GET /explore-collection       index - the hub: a short intro framing it as
 *                                          "ways to explore the collection", then
 *                                          one section per installed slice (top
 *                                          themes, top places, top creators, a
 *                                          compact timeline strip). Each section
 *                                          is Route::has-gated, so it renders only
 *                                          when that slice is installed, and links
 *                                          onward to the full page.
 *   GET /explore-collection.json  json  - the machine-readable twin of the same
 *                                          teaser data (CORS-open).
 *
 * Services reused READ-ONLY (this controller adds no queries of its own and edits
 * none of the slice files):
 *   - ThemeService::topThemes()    -> strongest subject themes (-> /themes)
 *   - PlaceService::topPlaces()    -> busiest places            (-> /places)
 *   - PersonService::topCreators() -> busiest creators          (-> /people)
 *   - TimelineService::buckets()   -> period buckets            (-> /timeline)
 *
 * Each teaser is capped small (a handful of items) on top of the service's own
 * already-bounded top-N. Every path is wrapped so the hub never 500s; when every
 * slice is empty (or none is installed) the view renders a calm "exploration
 * tools are warming up" state. Published-only, jurisdiction-neutral, no host is
 * ever hardcoded (url()/route() only).
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

use AhgSemanticSearch\Services\PersonService;
use AhgSemanticSearch\Services\PlaceService;
use AhgSemanticSearch\Services\ThemeService;
use AhgSemanticSearch\Services\TimelineService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class ExploreCollectionController extends Controller
{
    /** How many themes the teaser shows (on top of the service's own cap). */
    public const TEASER_THEMES = 6;

    /** How many places the teaser shows. */
    public const TEASER_PLACES = 12;

    /** How many creators the teaser shows. */
    public const TEASER_CREATORS = 8;

    /** How many timeline period buckets the strip shows. */
    public const TEASER_PERIODS = 12;

    /**
     * Public hub. Each slice is gated on Route::has() so a section renders only
     * when that slice is installed, and is wrapped so a slice failure degrades to
     * an empty teaser rather than 500-ing the whole hub.
     */
    public function index()
    {
        $data = $this->gather();

        return view('ahg-semantic-search::explore-collection.index', $data);
    }

    /**
     * Machine-readable twin (CORS-open, cacheable). Same teaser data the hub
     * renders, with only the installed slices present. Never 500s.
     */
    public function json(): JsonResponse
    {
        $data = $this->gather();

        $payload = [
            'surface' => 'explore-collection',
            'description' => 'A public hub gathering the browse-by discovery surfaces (themes, places, people, timeline) with a small teaser from each.',
            'sections' => [],
            'has_any' => $data['hasAny'],
            'generated_at' => now()->toIso8601String(),
        ];

        if ($data['themesEnabled']) {
            $payload['sections']['themes'] = [
                'url' => route('themes.index'),
                'items' => array_map(static function (array $t) {
                    return [
                        'id' => $t['term_id'],
                        'label' => $t['label'],
                        'record_count' => $t['record_count'],
                        'url' => Route::has('themes.show')
                            ? route('themes.show', ['termId' => $t['term_id']])
                            : null,
                    ];
                }, $data['themes']),
            ];
        }

        if ($data['placesEnabled']) {
            $payload['sections']['places'] = [
                'url' => route('places.index'),
                'items' => array_map(static function (array $p) {
                    return [
                        'id' => $p['term_id'],
                        'label' => $p['label'],
                        'record_count' => $p['record_count'],
                        'url' => Route::has('places.show')
                            ? route('places.show', ['termId' => $p['term_id']])
                            : null,
                    ];
                }, $data['places']),
            ];
        }

        if ($data['peopleEnabled']) {
            $payload['sections']['people'] = [
                'url' => route('people.index'),
                'items' => array_map(static function (array $c) {
                    return [
                        'id' => $c['actor_id'],
                        'label' => $c['name'],
                        'record_count' => $c['record_count'],
                        'url' => Route::has('people.show')
                            ? route('people.show', ['actorId' => $c['actor_id']])
                            : null,
                    ];
                }, $data['people']),
            ];
        }

        if ($data['timelineEnabled']) {
            $payload['sections']['timeline'] = [
                'url' => route('timeline.index'),
                'items' => array_map(static function (array $b) {
                    return [
                        'label' => $b['period_label'],
                        'from_year' => $b['from_year'],
                        'to_year' => $b['to_year'],
                        'count' => $b['count'],
                        'browse_url' => $b['browse_url'],
                    ];
                }, $data['timeline']),
            ];
        }

        return response()
            ->json($payload, 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * Gather the per-slice teaser data once, shared by the HTML hub and the JSON
     * twin. Each slice is:
     *   - Route::has-gated  (so it only renders when that slice is installed, and
     *                        so an onward link is never dead), and
     *   - try/catch-wrapped (so a slice failure degrades to an empty teaser).
     *
     * @return array<string,mixed>
     */
    protected function gather(): array
    {
        // A slice is "enabled" only when its full-page route is registered, so the
        // section header link and every onward link is guaranteed to resolve.
        $themesEnabled = Route::has('themes.index');
        $placesEnabled = Route::has('places.index');
        $peopleEnabled = Route::has('people.index');
        $timelineEnabled = Route::has('timeline.index');

        $themes = [];
        if ($themesEnabled) {
            try {
                $themes = (new ThemeService)->topThemes(self::TEASER_THEMES);
            } catch (\Throwable $e) {
                Log::info('[explore-collection] themes teaser failed: '.$e->getMessage());
            }
        }

        $places = [];
        if ($placesEnabled) {
            try {
                $places = (new PlaceService)->topPlaces(self::TEASER_PLACES);
            } catch (\Throwable $e) {
                Log::info('[explore-collection] places teaser failed: '.$e->getMessage());
            }
        }

        $people = [];
        if ($peopleEnabled) {
            try {
                $people = (new PersonService)->topCreators(self::TEASER_CREATORS);
            } catch (\Throwable $e) {
                Log::info('[explore-collection] people teaser failed: '.$e->getMessage());
            }
        }

        $timeline = [];
        if ($timelineEnabled) {
            try {
                $buckets = (new TimelineService)->buckets();
                // Keep the strip short: the service already orders chronologically
                // and bounds its output; we cap the teaser to a handful of periods.
                $timeline = array_slice($buckets, 0, self::TEASER_PERIODS);
            } catch (\Throwable $e) {
                Log::info('[explore-collection] timeline teaser failed: '.$e->getMessage());
            }
        }

        $hasAny = ! empty($themes) || ! empty($places) || ! empty($people) || ! empty($timeline);

        return [
            'themesEnabled' => $themesEnabled,
            'placesEnabled' => $placesEnabled,
            'peopleEnabled' => $peopleEnabled,
            'timelineEnabled' => $timelineEnabled,
            'themes' => $themes,
            'places' => $places,
            'people' => $people,
            'timeline' => $timeline,
            'hasAny' => $hasAny,
        ];
    }
}
