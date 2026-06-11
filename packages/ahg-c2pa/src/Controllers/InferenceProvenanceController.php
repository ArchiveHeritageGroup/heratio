<?php
/**
 * Heratio - public per-record INFERENCE-PROVENANCE explorer (issue #1201).
 *
 * The public, honest read surface that answers, for ONE published archival
 * record: "which AI inferences contributed to this record's metadata - with
 * which model, through which gateway, when - and did a human stay accountable?"
 *
 * It delegates entirely to InferenceProvenanceService, which reuses the existing
 * inference-provenance foundation (ahg_ai_inference + ahg_ai_override, shipped
 * for issue #61 / ADR-0002) READ-ONLY. Nothing here runs AI, writes a row, or
 * re-verifies anything. Every path is resilient:
 *
 *   - an unknown OR unpublished record is a clean 404 (HTML) / 404 JSON,
 *   - a published record with NO recorded inference is the dignified
 *     "no AI inference recorded for this record" state, NOT an error,
 *   - any reader fault degrades to that neutral state; the page never 500s and
 *     the JSON endpoint never 500s.
 *
 * Routes are multi-segment (/inference-provenance/{idOrSlug}) so the single-
 * segment IO slug catch-all (/{slug}) can never intercept them. The machine
 * companion keeps its .json extension (nginx passes *.json to Laravel).
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Controllers;

use AhgC2pa\Services\InferenceProvenanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class InferenceProvenanceController extends Controller
{
    public function __construct(private InferenceProvenanceService $service)
    {
    }

    /**
     * Human-readable per-record inference-provenance explorer.
     * Unknown / unpublished record -> 404; published record with no recorded
     * inference -> dignified empty state (still HTTP 200). Never 500s.
     */
    public function show(string $idOrSlug): View|Response
    {
        $report = $this->safeReport($idOrSlug);

        if ($report === null) {
            // Unknown OR unpublished: a 404 is the honest, non-leaking answer.
            abort(404);
        }

        return view('ahg-c2pa::inference-provenance.show', [
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
                        . 'or no inference-provenance report is available.',
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
                'inference_store_available' => $report['available'],
                'summary'                   => $report['summary'],
                'counts'                    => $report['counts'],
                'by_service'                => $report['by_service'],
                'models'                    => $report['models'],
                'inferences'                => $report['inferences'],
                'authenticity_url'          => $report['authenticity_url'],
                'generated_at'              => $report['generated_at'],
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
            Log::warning('c2pa inference-provenance: report build threw; treating as not found', [
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
