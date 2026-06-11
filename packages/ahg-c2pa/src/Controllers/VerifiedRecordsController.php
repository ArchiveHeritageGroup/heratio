<?php
/**
 * Heratio - public BROWSE of provenance-verified records (issue #1209, north star).
 *
 * The walkable face of the truth anchor: a public, paginated list of the
 * PUBLISHED records that actually carry content credentials - the verifiable
 * corpus. Where /trust is the collection-wide aggregate and /authenticity/{id}
 * is the single-record report, THIS page lets a visitor browse the records that
 * CAN be verified and click straight through to each one's authenticity report.
 *
 * It delegates entirely to VerifiedRecordsService, which reads
 * ahg_c2pa_provenance (joined to the published IO set) READ-ONLY with cheap,
 * BOUNDED, paginated queries (24/page, hard-capped) - never a full-catalogue
 * scan. Nothing here writes, signs, runs AI, or re-verifies; the live re-verify
 * stays on the per-record /authenticity page each row links to.
 *
 * Both routes are registered single-segment (/verified-records and
 * /verified-records.json) in the service provider's register() via
 * callAfterResolving('router'), BEFORE the IO slug catch-all (/{slug}) loads in
 * boot(), so first-match-wins resolution always picks them (the same pattern
 * /trust and /content-credentials use). The .json companion keeps its real
 * extension (nginx passes *.json through to Laravel), is CORS-open, and is
 * declared first so it can never be captured as a slug.
 *
 * Every path is resilient: a missing layer, an unreachable DB, or an empty
 * corpus all degrade to the honest empty state ("No provenance-verified records
 * yet"), and neither the page nor the JSON ever 500s.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Controllers;

use AhgC2pa\Services\VerifiedRecordsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class VerifiedRecordsController extends Controller
{
    public function __construct(private VerifiedRecordsService $service)
    {
    }

    /**
     * Public, paginated browse of provenance-verified published records. Never
     * 500s: any fault degrades to the honest empty-state page.
     */
    public function index(Request $request): View
    {
        return view('ahg-c2pa::verified-records.index', [
            'corpus' => $this->safePage($request),
        ]);
    }

    /**
     * Machine-readable companion. CORS-open, read-only, paginated GET. Never
     * 500s; mirrors the page shape so a client can walk every page.
     */
    public function json(Request $request): JsonResponse
    {
        $corpus = $this->safePage($request);

        return response()
            ->json([
                'filter'          => $corpus['filter'],
                'available_filters' => array_map(
                    static fn (array $f): string => $f['key'],
                    $corpus['filters'],
                ),
                'layer_installed' => $corpus['layer_installed'],
                'caveat'          => $corpus['caveat'],
                'pagination'      => [
                    'page'      => $corpus['page'],
                    'per_page'  => $corpus['per_page'],
                    'total'     => $corpus['total'],
                    'last_page' => $corpus['last_page'],
                    'from'      => $corpus['from'],
                    'to'        => $corpus['to'],
                    'has_prev'  => $corpus['has_prev'],
                    'has_next'  => $corpus['has_next'],
                ],
                'records'         => array_map([$this, 'recordForJson'], $corpus['records']),
                'generated_at'    => $corpus['generated_at'],
            ])
            ->withHeaders($this->corsHeaders());
    }

    /**
     * Build one page from the request without letting a fault escape. The
     * service is already fully guarded, but a hard fault must still degrade to a
     * well-formed empty page rather than a 500.
     *
     * @return array<string,mixed>
     */
    private function safePage(Request $request): array
    {
        $filter  = (string) $request->query('filter', 'all');
        $page    = (int) $request->query('page', '1');
        $perPage = $request->has('per_page')
            ? (int) $request->query('per_page')
            : VerifiedRecordsService::DEFAULT_PER_PAGE;

        try {
            return $this->service->page($filter, $page, $perPage);
        } catch (Throwable $e) {
            Log::warning('c2pa verified-records: page threw; serving empty state', ['err' => $e->getMessage()]);

            return $this->emptyPage($filter);
        }
    }

    /**
     * Project one service record onto the public JSON shape (no internal-only
     * fields). Kept small and stable for machine consumers.
     *
     * @param  array<string,mixed> $r
     * @return array<string,mixed>
     */
    private function recordForJson(array $r): array
    {
        return [
            'information_object_id' => (int) ($r['information_object_id'] ?? 0),
            'title'                 => $r['title'] ?? null,
            'identifier'            => $r['identifier'] ?? null,
            'slug'                  => $r['slug'] ?? null,
            'credentials'           => (int) ($r['credentials'] ?? 0),
            'signed'                => (int) ($r['signed'] ?? 0),
            'verified'              => (int) ($r['verified'] ?? 0),
            'failed'                => (int) ($r['failed'] ?? 0),
            'status'                => $r['badge'] ?? 'recorded',
            'status_label'          => $r['badge_label'] ?? null,
            'last_credential_at'    => $r['last_credential_at'] ?? null,
            'authenticity_url'      => $r['authenticity_url'] ?? null,
            'authenticity_json_url' => $r['authenticity_json_url'] ?? null,
        ];
    }

    /**
     * The honest empty page shape, matching VerifiedRecordsService::page() so the
     * view + JSON never have to branch on missing keys.
     *
     * @return array<string,mixed>
     */
    private function emptyPage(string $filter): array
    {
        $filter = in_array($filter, VerifiedRecordsService::FILTERS, true) ? $filter : 'all';

        $filters = [];
        foreach (VerifiedRecordsService::FILTERS as $key) {
            $filters[] = ['key' => $key, 'label' => $key, 'active' => $key === $filter];
        }

        return [
            'filter'          => $filter,
            'filters'         => $filters,
            'page'            => 1,
            'per_page'        => VerifiedRecordsService::DEFAULT_PER_PAGE,
            'total'           => 0,
            'last_page'       => 1,
            'from'            => 0,
            'to'              => 0,
            'has_prev'        => false,
            'has_next'        => false,
            'records'         => [],
            'layer_installed' => false,
            'caveat'          => VerifiedRecordsService::HONEST_CAVEAT,
            'generated_at'    => gmdate('Y-m-d\TH:i:s\Z'),
        ];
    }

    /**
     * CORS-open, read-only headers so any page can fetch the JSON.
     *
     * @return array<string,string>
     */
    private function corsHeaders(): array
    {
        return [
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'X-Content-Type-Options'       => 'nosniff',
        ];
    }
}
