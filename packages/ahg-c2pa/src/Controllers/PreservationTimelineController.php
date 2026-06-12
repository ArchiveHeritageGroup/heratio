<?php
/**
 * Heratio - public per-record PRESERVATION-TIMELINE explorer (issue #1244,
 * building on the #1201 provenance epic).
 *
 * The public, honest read surface that shows, for ONE published archival record,
 * the recorded digital-preservation lifecycle of its digital objects: ingest,
 * fixity checks, format identification, migrations / normalisations, and virus
 * scans - in chronological order, each with its outcome and the responsible
 * agent or tool.
 *
 * It delegates entirely to PreservationTimelineService, which reads the
 * PREMIS-style stores owned by the (locked) ahg-preservation package READ-ONLY
 * (preservation_event + preservation_fixity_check + preservation_object_format +
 * preservation_virus_scan + preservation_format_conversion). Nothing here runs a
 * preservation action, writes a row, or re-verifies anything. Every path is
 * resilient:
 *
 *   - an unknown OR unpublished record is a clean 404 (HTML) / 404 JSON,
 *   - a published record with NO recorded preservation event is the dignified
 *     "no preservation events recorded yet" state, NOT an error,
 *   - any reader fault degrades to that neutral state; the page never 500s and
 *     the JSON endpoint never 500s.
 *
 * Routes are multi-segment (/preservation-timeline/{idOrSlug}) so the single-
 * segment IO slug catch-all (/{slug}) can never intercept them. The machine
 * companion keeps its .json extension (nginx passes *.json to Laravel).
 *
 * Distinct from /inference-provenance (AI inference) and /authenticity (C2PA
 * signing); it links to both for the full trust picture.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Controllers;

use AhgC2pa\Services\PreservationTimelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class PreservationTimelineController extends Controller
{
    public function __construct(private PreservationTimelineService $service)
    {
    }

    /**
     * Human-readable per-record preservation-timeline explorer.
     * Unknown / unpublished record -> 404; published record with no recorded
     * preservation event -> dignified empty state (still HTTP 200). Never 500s.
     */
    public function show(string $idOrSlug): View|Response
    {
        $report = $this->safeReport($idOrSlug);

        if ($report === null) {
            // Unknown OR unpublished: a 404 is the honest, non-leaking answer.
            abort(404);
        }

        return view('ahg-c2pa::preservation-timeline.show', [
            'report' => $report,
        ]);
    }

    /**
     * Machine-readable companion. CORS-open, read-only GET. Never 500s: an
     * unknown / unpublished record is a clean 404 JSON body.
     */
    public function json(string $idOrSlug): JsonResponse
    {
        $report = $this->safeReport($idOrSlug);

        if ($report === null) {
            return response()
                ->json([
                    'reference' => $idOrSlug,
                    'found'     => false,
                    'message'   => 'No published record found for this reference, '
                        . 'or no preservation timeline is available.',
                ], 404)
                ->withHeaders($this->corsHeaders());
        }

        $object = $report['object'];

        return response()
            ->json([
                'found'                 => true,
                'information_object_id' => (int) ($object->id ?? 0),
                'record'                => [
                    'title'      => $object->title ?? null,
                    'identifier' => $object->identifier ?? null,
                    'slug'       => $object->slug ?? null,
                ],
                'summary'          => $report['summary'],
                'counts'           => $report['counts'],
                'by_stage'         => $report['by_stage'],
                'stages_present'   => $report['stages_present'],
                'truncated'        => $report['truncated'],
                'events'           => $report['events'],
                'authenticity_url' => $report['authenticity_url'],
                'inference_url'    => $report['inference_url'],
                'generated_at'     => $report['generated_at'],
            ])
            ->withHeaders($this->corsHeaders());
    }

    /**
     * Build the report without ever letting a fault escape.
     *
     * @return array<string,mixed>|null
     */
    private function safeReport(string $idOrSlug): ?array
    {
        try {
            return $this->service->report($idOrSlug);
        } catch (Throwable $e) {
            Log::warning('c2pa preservation-timeline: report build threw; treating as not found', [
                'reference' => $idOrSlug,
                'err'       => $e->getMessage(),
            ]);

            return null;
        }
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
