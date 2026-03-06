<?php

namespace AhgTermTaxonomy\Controllers;

use AhgTermTaxonomy\Services\TermBrowseService;
use AhgCore\Pagination\SimplePager;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TermController extends Controller
{
    /**
     * List all taxonomies.
     */
    public function taxonomyIndex(Request $request)
    {
        $culture = app()->getLocale();

        $taxonomies = DB::table('taxonomy')
            ->join('taxonomy_i18n', 'taxonomy.id', '=', 'taxonomy_i18n.id')
            ->where('taxonomy_i18n.culture', $culture)
            ->select([
                'taxonomy.id',
                'taxonomy_i18n.name as name',
            ])
            ->orderBy('taxonomy_i18n.name', 'asc')
            ->get();

        return view('ahg-term-taxonomy::taxonomy-index', [
            'taxonomies' => $taxonomies,
        ]);
    }

    /**
     * Browse terms filtered by taxonomy.
     */
    public function browse(Request $request)
    {
        $culture = app()->getLocale();
        $taxonomyId = $request->get('taxonomy');
        $service = new TermBrowseService($culture);

        $result = $service->browse([
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', 30),
            'sort' => $request->get('sort', 'alphabetic'),
            'subquery' => $request->get('subquery', ''),
            'taxonomy_id' => $taxonomyId,
        ]);

        $pager = new SimplePager($result);

        // Get taxonomy name for the heading
        $taxonomyName = null;
        if ($taxonomyId) {
            $taxonomyName = DB::table('taxonomy_i18n')
                ->where('id', $taxonomyId)
                ->where('culture', $culture)
                ->value('name');
        }

        return view('ahg-term-taxonomy::browse', [
            'pager' => $pager,
            'taxonomyId' => $taxonomyId,
            'taxonomyName' => $taxonomyName,
            'sortOptions' => [
                'alphabetic' => 'Name',
                'lastUpdated' => 'Date modified',
            ],
        ]);
    }

    /**
     * Show a single term.
     */
    public function show(Request $request, string $slug)
    {
        $culture = app()->getLocale();

        $term = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->join('slug', 'term.id', '=', 'slug.object_id')
            ->join('object', 'term.id', '=', 'object.id')
            ->where('slug.slug', $slug)
            ->where('term_i18n.culture', $culture)
            ->select([
                'term.id',
                'term.taxonomy_id',
                'term_i18n.name',
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->first();

        if (!$term) {
            abort(404);
        }

        // Get taxonomy name
        $taxonomyName = DB::table('taxonomy_i18n')
            ->where('id', $term->taxonomy_id)
            ->where('culture', $culture)
            ->value('name');

        // Get scope note from note + note_i18n
        $scopeNote = DB::table('note')
            ->join('note_i18n', 'note.id', '=', 'note_i18n.id')
            ->where('note.object_id', $term->id)
            ->where('note_i18n.culture', $culture)
            ->select('note_i18n.content')
            ->first();

        // Count related descriptions (information objects linked via object_term_relation)
        $relatedDescriptionsCount = DB::table('object_term_relation')
            ->where('term_id', $term->id)
            ->count();

        return view('ahg-term-taxonomy::show', [
            'term' => $term,
            'taxonomyName' => $taxonomyName,
            'scopeNote' => $scopeNote,
            'relatedDescriptionsCount' => $relatedDescriptionsCount,
        ]);
    }
}
