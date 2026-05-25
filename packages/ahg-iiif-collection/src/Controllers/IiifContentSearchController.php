<?php
/**
 * Heratio - IIIF Content Search 2.0 endpoint controller (issue #694).
 *
 * @copyright Copyright (c) 2026, The Archive and Heritage Group (Pty) Ltd
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

namespace AhgIiifCollection\Controllers;

use AhgIiifCollection\Services\IiifContentSearchService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * IiifContentSearchController - thin HTTP layer over IiifContentSearchService.
 *
 * Routes (registered in packages/ahg-iiif-collection/routes/web.php):
 *
 *   GET  /iiif-manifest/{slug}/search?q=<term>[&motivation=highlighting]
 *        -> 200 AnnotationPage   (application/ld+json)
 *        -> 404 when slug is unknown
 *
 *   GET  /iiif-manifest/{slug}/autocomplete?q=<prefix>
 *        -> 200 AnnotationCollection of TextualBody items
 *        -> 404 when slug is unknown
 *
 * Notes on path choice: the spec example uses /iiif/{manifestId}/search,
 * but on this host nginx hard-routes /iiif/ to Cantaloupe (the IIIF Image
 * API proxy) so a Laravel route under that prefix is unreachable. The
 * /iiif-manifest/ prefix is the same one used by the existing manifest
 * route and is what gets advertised in the SearchService2 service block,
 * so harvesters discover the correct URL from the manifest itself.
 *
 * Auth: anonymous read. Search results expose the same text already
 * surfaced by the manifest's canvas annotations, so no additional gate
 * is required beyond what the manifest itself enforces.
 */
class IiifContentSearchController extends Controller
{
    private IiifContentSearchService $search;

    public function __construct(IiifContentSearchService $search)
    {
        $this->search = $search;
    }

    public function search(Request $request, string $slug): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $motivation = $request->query('motivation');
        if ($motivation !== null) {
            $motivation = (string) $motivation;
        }

        $page = $this->search->search($slug, $query, $motivation);
        if ($page === null) {
            return response()->json([
                '@context' => 'http://iiif.io/api/search/2/context.json',
                'type' => 'Error',
                'error' => 'Manifest not found',
            ], 404, $this->headers());
        }

        return response()->json($page, 200, $this->headers(), JSON_UNESCAPED_SLASHES);
    }

    public function autocomplete(Request $request, string $slug): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $page = $this->search->autocomplete($slug, $query);
        if ($page === null) {
            return response()->json([
                '@context' => 'http://iiif.io/api/search/2/context.json',
                'type' => 'Error',
                'error' => 'Manifest not found',
            ], 404, $this->headers());
        }

        return response()->json($page, 200, $this->headers(), JSON_UNESCAPED_SLASHES);
    }

    /**
     * Headers shared by both endpoints. We send application/ld+json per
     * the IIIF specs and a permissive CORS origin so cross-site viewers
     * (Mirador / Universal Viewer hosted elsewhere) can consume the
     * response.
     *
     * @return array<string,string>
     */
    private function headers(): array
    {
        return [
            'Content-Type' => 'application/ld+json;profile="http://iiif.io/api/search/2/context.json"',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'no-cache, must-revalidate',
        ];
    }
}
