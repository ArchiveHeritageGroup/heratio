<?php

namespace AhgDiscovery\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Discovery Controller — unified search/discovery across all entity types.
 * Migrated from ahgDiscoveryPlugin.
 */
class DiscoveryController extends Controller
{
    /**
     * Discovery landing page with search.
     */
    public function index(Request $request)
    {
        $query = trim($request->input('q', ''));
        $type = $request->input('type', 'all');
        $page = max(1, (int) $request->input('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;
        $results = collect();
        $total = 0;
        $culture = app()->getLocale() ?: 'en';

        if ($query !== '') {
            if ($type === 'all' || $type === 'information_object') {
                $ioResults = $this->searchInformationObjects($query, $culture, $limit, $offset);
                if ($type === 'information_object') {
                    $results = $ioResults['results'];
                    $total = $ioResults['total'];
                }
            }

            if ($type === 'all' || $type === 'actor') {
                $actorResults = $this->searchActors($query, $culture, $limit, $offset);
                if ($type === 'actor') {
                    $results = $actorResults['results'];
                    $total = $actorResults['total'];
                }
            }

            if ($type === 'all' || $type === 'repository') {
                $repoResults = $this->searchRepositories($query, $culture, $limit, $offset);
                if ($type === 'repository') {
                    $results = $repoResults['results'];
                    $total = $repoResults['total'];
                }
            }

            if ($type === 'all') {
                $combined = collect();
                foreach (['information_object' => 'Archival description', 'actor' => 'Authority record', 'repository' => 'Repository'] as $t => $label) {
                    $r = $t === 'information_object' ? ($ioResults ?? ['results' => collect()])
                       : ($t === 'actor' ? ($actorResults ?? ['results' => collect()])
                       : ($repoResults ?? ['results' => collect()]));
                    foreach ($r['results'] as $item) {
                        $item->entity_type = $label;
                        $combined->push($item);
                    }
                }
                $results = $combined->take($limit);
                $total = ($ioResults['total'] ?? 0) + ($actorResults['total'] ?? 0) + ($repoResults['total'] ?? 0);
            }
        }

        // Counts per type for sidebar
        $counts = [];
        if ($query !== '') {
            $counts['information_object'] = $ioResults['total'] ?? 0;
            $counts['actor'] = $actorResults['total'] ?? 0;
            $counts['repository'] = $repoResults['total'] ?? 0;
        }

        $totalPages = $total > 0 ? (int) ceil($total / $limit) : 1;

        return view('ahg-discovery::index', compact('results', 'query', 'type', 'total', 'page', 'totalPages', 'counts'));
    }

    /**
     * AJAX autocomplete suggestions.
     */
    public function suggest(Request $request)
    {
        $query = trim($request->input('q', ''));
        $culture = app()->getLocale() ?: 'en';

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $suggestions = collect();

        // IO titles
        $ios = DB::table('information_object_i18n as ioi')
            ->leftJoin('slug', 'ioi.id', '=', 'slug.object_id')
            ->where('ioi.culture', $culture)
            ->where('ioi.title', 'like', "%{$query}%")
            ->where('ioi.id', '!=', 1)
            ->select('ioi.title as label', 'slug.slug', DB::raw("'Archival description' as type"))
            ->limit(5)->get();
        $suggestions = $suggestions->merge($ios);

        // Actor names
        $actors = DB::table('actor_i18n as ai')
            ->leftJoin('slug', 'ai.id', '=', 'slug.object_id')
            ->where('ai.culture', $culture)
            ->where('ai.authorized_form_of_name', 'like', "%{$query}%")
            ->where('ai.id', '!=', 1)
            ->select('ai.authorized_form_of_name as label', 'slug.slug', DB::raw("'Authority record' as type"))
            ->limit(5)->get();
        $suggestions = $suggestions->merge($actors);

        return response()->json($suggestions->take(10)->values());
    }

    private function searchInformationObjects(string $query, string $culture, int $limit, int $offset): array
    {
        $base = DB::table('information_object as io')
            ->join('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', $culture);
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.id', '!=', 1)
            ->where(function ($q) use ($query) {
                $q->where('ioi.title', 'like', "%{$query}%")
                  ->orWhere('io.identifier', 'like', "%{$query}%")
                  ->orWhere('ioi.scope_and_content', 'like', "%{$query}%");
            });

        $total = (clone $base)->count();
        $results = $base->select('io.id', 'io.identifier', 'ioi.title as label', 'slug.slug',
                                 DB::raw("'Archival description' as entity_type"))
            ->orderBy('ioi.title')->offset($offset)->limit($limit)->get();

        return ['results' => $results, 'total' => $total];
    }

    private function searchActors(string $query, string $culture, int $limit, int $offset): array
    {
        $base = DB::table('actor as a')
            ->join('actor_i18n as ai', function ($j) use ($culture) {
                $j->on('a.id', '=', 'ai.id')->where('ai.culture', '=', $culture);
            })
            ->leftJoin('slug', 'a.id', '=', 'slug.object_id')
            ->where('a.id', '!=', 1)
            ->where('ai.authorized_form_of_name', 'like', "%{$query}%");

        $total = (clone $base)->count();
        $results = $base->select('a.id', 'ai.authorized_form_of_name as label', 'slug.slug',
                                 DB::raw("'Authority record' as entity_type"))
            ->orderBy('ai.authorized_form_of_name')->offset($offset)->limit($limit)->get();

        return ['results' => $results, 'total' => $total];
    }

    private function searchRepositories(string $query, string $culture, int $limit, int $offset): array
    {
        $base = DB::table('repository as r')
            ->join('actor_i18n as ai', function ($j) use ($culture) {
                $j->on('r.id', '=', 'ai.id')->where('ai.culture', '=', $culture);
            })
            ->leftJoin('slug', 'r.id', '=', 'slug.object_id')
            ->where('ai.authorized_form_of_name', 'like', "%{$query}%");

        $total = (clone $base)->count();
        $results = $base->select('r.id', 'ai.authorized_form_of_name as label', 'slug.slug',
                                 DB::raw("'Repository' as entity_type"))
            ->orderBy('ai.authorized_form_of_name')->offset($offset)->limit($limit)->get();

        return ['results' => $results, 'total' => $total];
    }
}
