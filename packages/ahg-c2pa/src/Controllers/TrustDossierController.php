<?php
/**
 * Heratio - public per-record consolidated TRUST DOSSIER (issues #1209 / #1201,
 * next slice).
 *
 * The public, honest read surface that UNIFIES the three per-record trust
 * surfaces - the Authenticity Report (C2PA content credentials / signing), the
 * AI Inference Provenance Explorer, and the Preservation Timeline (PREMIS
 * lifecycle) - into ONE page plus a machine-readable companion, topped by an
 * honest "what can and cannot be verified about this record" statement that never
 * overclaims. It is the one-stop "defence dossier" for a published record.
 *
 * It delegates entirely to TrustDossierService, which composes the three existing
 * per-record services READ-ONLY (AuthenticityReportService,
 * InferenceProvenanceService, PreservationTimelineService) and re-implements none
 * of their queries or verdicts. Nothing here writes a row, signs anything, runs
 * AI, runs a preservation action, or re-verifies anything. Every path is
 * resilient:
 *
 *   - an unknown OR unpublished record is a clean 404 (HTML) / 404 JSON,
 *   - a published record where a sub-layer recorded nothing shows that layer's
 *     own dignified empty state, NOT an error,
 *   - a sub-service that is missing or faults degrades only its own section; the
 *     page never 500s and the JSON endpoint never 500s.
 *
 * The route is multi-segment (/trust-dossier/{idOrSlug}) so the single-segment IO
 * slug catch-all (/{slug}) can never intercept it. The machine companion keeps
 * its .json extension (nginx passes *.json to Laravel) and is CORS-open. There is
 * deliberately NO '.svg' surface here.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Controllers;

use AhgC2pa\Services\TrustDossierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class TrustDossierController extends Controller
{
    public function __construct(private TrustDossierService $service)
    {
    }

    /**
     * Human-readable, print-friendly consolidated trust dossier.
     * Unknown / unpublished record -> 404; a published record with thin or empty
     * layers -> dignified per-section empty states (still HTTP 200). Never 500s.
     */
    public function show(string $idOrSlug): View|Response
    {
        $dossier = $this->safeDossier($idOrSlug);

        if ($dossier === null) {
            // Unknown OR unpublished: a 404 is the honest, non-leaking answer.
            abort(404);
        }

        return view('ahg-c2pa::trust-dossier.show', [
            'dossier' => $dossier,
        ]);
    }

    /**
     * Machine-readable companion. CORS-open, read-only GET. Never 500s: an
     * unknown / unpublished record is a clean 404 JSON body. The structure mirrors
     * the page: one record identity, the honest headline, the can/cannot-verify
     * lists, and the three consolidated sections (each null when that layer
     * contributed nothing or was unavailable).
     */
    public function json(string $idOrSlug): JsonResponse
    {
        $dossier = $this->safeDossier($idOrSlug);

        if ($dossier === null) {
            return response()
                ->json([
                    'reference' => $idOrSlug,
                    'found'     => false,
                    'message'   => 'No published record found for this reference, '
                        . 'or no trust dossier is available.',
                ], 404)
                ->withHeaders($this->corsHeaders());
        }

        $object = $dossier['object'];

        return response()
            ->json([
                'found'                 => true,
                'information_object_id' => (int) ($object->id ?? 0),
                'record'                => [
                    'title'      => $object->title ?? null,
                    'identifier' => $object->identifier ?? null,
                    'slug'       => $object->slug ?? null,
                ],
                'headline'       => $dossier['headline'],
                'can_verify'     => $dossier['can_verify'],
                'cannot_verify'  => $dossier['cannot_verify'],
                'section_status' => $dossier['section_status'],
                'sections'       => $this->jsonSections($dossier['sections']),
                'links'          => $dossier['links'],
                'generated_at'   => $dossier['generated_at'],
            ])
            ->withHeaders($this->corsHeaders());
    }

    /**
     * Reduce the three composed section reports to JSON-safe payloads. Each
     * section reuses the SAME shape the sibling .json endpoints expose, minus the
     * internal `object` handle (the record identity is surfaced once, at the top).
     * A null section is passed through as null - the honest "this layer
     * contributed nothing or was unavailable" signal.
     *
     * @param  array{authenticity: array<string,mixed>|null, inference: array<string,mixed>|null, preservation: array<string,mixed>|null} $sections
     * @return array<string,mixed>
     */
    private function jsonSections(array $sections): array
    {
        $auth = $sections['authenticity'];
        $inf  = $sections['inference'];
        $pres = $sections['preservation'];

        return [
            'authenticity' => $auth === null ? null : [
                'confidence'       => $auth['confidence'] ?? null,
                'confidence_label' => $auth['confidence_label'] ?? null,
                'summary'          => $auth['summary'] ?? null,
                'signals'          => $auth['signals'] ?? null,
                'counts'           => $auth['counts'] ?? null,
                'trace_url'        => $auth['trace_url'] ?? null,
                'trace_json_url'   => $auth['trace_json_url'] ?? null,
                'badge_url'        => $auth['badge_url'] ?? null,
            ],
            'inference' => $inf === null ? null : [
                'available'   => $inf['available'] ?? false,
                'summary'     => $inf['summary'] ?? null,
                'counts'      => $inf['counts'] ?? null,
                'by_service'  => $inf['by_service'] ?? null,
                'models'      => $inf['models'] ?? null,
                'inferences'  => $inf['inferences'] ?? null,
            ],
            'preservation' => $pres === null ? null : [
                'summary'        => $pres['summary'] ?? null,
                'counts'         => $pres['counts'] ?? null,
                'by_stage'       => $pres['by_stage'] ?? null,
                'stages_present' => $pres['stages_present'] ?? null,
                'truncated'      => $pres['truncated'] ?? false,
                'events'         => $pres['events'] ?? null,
            ],
        ];
    }

    /**
     * Build the dossier without ever letting a fault escape.
     *
     * @return array<string,mixed>|null
     */
    private function safeDossier(string $idOrSlug): ?array
    {
        try {
            return $this->service->dossier($idOrSlug);
        } catch (Throwable $e) {
            Log::warning('c2pa trust-dossier: dossier build threw; treating as not found', [
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
