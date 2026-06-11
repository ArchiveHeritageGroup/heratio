<?php
/**
 * Heratio - public per-record AUTHENTICITY REPORT (issue #1209, north star).
 *
 * The truth anchor's per-record front door. For ONE published archival record
 * (addressed by numeric id or slug) it renders a single, plain-language report
 * that CONSOLIDATES the verification signals that already exist for that record:
 * content credentials / C2PA signing, the whole-record provenance verification
 * verdict, AI-inference provenance, and an honest "what we can and cannot
 * verify" statement with a confidence tier that is never overclaimed.
 *
 * It delegates entirely to AuthenticityReportService, which in turn reuses the
 * package's existing services (ProvenanceTraceService -> ProvenanceRecordService
 * -> C2paService::verify). Nothing here re-reads a manifest, shells to c2patool,
 * reimplements verification, or writes anything. Every path is resilient:
 *
 *   - an unknown OR unpublished record is a clean 404 (HTML) / 404 JSON,
 *   - a published record with no signals is the dignified "no authenticity
 *     signals recorded yet" state, NOT an error,
 *   - any reader fault degrades to that neutral state; the page never 500s and
 *     the JSON endpoint never 500s.
 *
 * Routes are multi-segment (/authenticity/{idOrSlug}) so the single-segment IO
 * slug catch-all (/{slug}) can never intercept them. The machine companion
 * keeps its .json extension (nginx passes *.json to Laravel); the trust badge
 * is extensionless (a *.svg path would be served statically by nginx and 404
 * before Laravel sees it).
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Controllers;

use AhgC2pa\Services\AuthenticityReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class AuthenticityReportController extends Controller
{
    public function __construct(private AuthenticityReportService $service)
    {
    }

    /**
     * Human-readable per-record authenticity report.
     * Unknown / unpublished record -> 404; published record with no signals ->
     * dignified empty state (still HTTP 200). Never 500s.
     */
    public function show(string $idOrSlug): View|Response
    {
        $report = $this->safeReport($idOrSlug);

        if ($report === null) {
            // Unknown OR unpublished: a 404 is the honest, non-leaking answer.
            abort(404);
        }

        return view('ahg-c2pa::authenticity.report', [
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
                        . 'or no authenticity report is available.',
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
                'confidence'       => $report['confidence'],
                'confidence_label' => $report['confidence_label'],
                'summary'          => $report['summary'],
                'can_verify'       => $report['can_verify'],
                'cannot_verify'    => $report['cannot_verify'],
                'signals'          => $report['signals'],
                'counts'           => $report['counts'],
                'trace_url'        => $report['trace_url'],
                'trace_json_url'   => $report['trace_json_url'],
                'generated_at'     => $report['generated_at'],
            ])
            ->withHeaders($this->corsHeaders());
    }

    /**
     * Embeddable record-level trust badge as a self-contained SVG. Extensionless
     * on purpose (nginx serves *.svg statically and 404s before Laravel). Any
     * page can <img>-embed it. Never 500s - a fault or unknown record yields the
     * neutral "no signals" badge with HTTP 200 so an embedding page never breaks.
     */
    public function badge(string $idOrSlug): Response
    {
        $confidence = AuthenticityReportService::CONFIDENCE_NONE;

        try {
            $report = $this->service->report($idOrSlug);
            if ($report !== null) {
                $confidence = (string) $report['confidence'];
            }
        } catch (Throwable $e) {
            Log::warning('c2pa authenticity: badge fell back to neutral', [
                'reference' => $idOrSlug,
                'err'       => $e->getMessage(),
            ]);
        }

        $svg = $this->renderSvg($confidence);

        return response($svg, 200, array_merge($this->corsHeaders(), [
            'Content-Type'  => 'image/svg+xml; charset=utf-8',
            // Embedders cache lightly; the live truth is the report page.
            'Cache-Control' => 'public, max-age=300',
        ]));
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
            Log::warning('c2pa authenticity: report build threw; treating as not found', [
                'reference' => $idOrSlug,
                'err'       => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * CORS-open, read-only headers so any page can fetch the JSON / embed badge.
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

    /* ----------------------------------------------------------------- *
     * SVG trust badge - self-contained, confidence-coloured, well-formed.
     * ----------------------------------------------------------------- */

    /**
     * Render a small, self-contained "Authenticity" badge for the given
     * confidence tier. No external fonts/refs; a crude but stable width metric
     * keeps it self-sizing without a font server. Mirrors the shape of the
     * per-object verify badge so the two read as one family.
     */
    private function renderSvg(string $confidence): string
    {
        [$colour, $label] = match ($confidence) {
            AuthenticityReportService::CONFIDENCE_HIGH    => ['#1a7f37', "Authenticity \u{2713}"],
            AuthenticityReportService::CONFIDENCE_PARTIAL => ['#0969da', 'Authenticity: partial'],
            AuthenticityReportService::CONFIDENCE_LOW     => ['#9a6700', 'Authenticity: recorded'],
            AuthenticityReportService::CONFIDENCE_BROKEN  => ['#cf222e', "Authenticity \u{2717}"],
            default                                       => ['#6c757d', 'Authenticity: none yet'],
        };

        $leftText  = 'Verify';
        $rightText = (string) $label;

        $leftW  = (int) max(46, 14 + strlen($leftText) * 7);
        $rightW = (int) max(120, 14 + mb_strlen($rightText) * 7);
        $total  = $leftW + $rightW;
        $height = 20;

        $leftMid  = (int) ($leftW / 2);
        $rightMid = $leftW + (int) ($rightW / 2);

        $eLabel = $this->xml($rightText);
        $eLeft  = $this->xml($leftText);
        $eState = $this->xml($confidence);

        return <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="{$total}" height="{$height}" role="img" aria-label="Authenticity confidence: {$eState}">
  <title>Authenticity confidence: {$eState}</title>
  <linearGradient id="s" x2="0" y2="100%">
    <stop offset="0" stop-color="#bbb" stop-opacity=".1"/>
    <stop offset="1" stop-opacity=".1"/>
  </linearGradient>
  <clipPath id="r"><rect width="{$total}" height="{$height}" rx="3"/></clipPath>
  <g clip-path="url(#r)">
    <rect width="{$leftW}" height="{$height}" fill="#444"/>
    <rect x="{$leftW}" width="{$rightW}" height="{$height}" fill="{$colour}"/>
    <rect width="{$total}" height="{$height}" fill="url(#s)"/>
  </g>
  <g fill="#fff" text-anchor="middle" font-family="Verdana,DejaVu Sans,Geneva,sans-serif" font-size="11">
    <text x="{$leftMid}" y="14" fill="#010101" fill-opacity=".3">{$eLeft}</text>
    <text x="{$leftMid}" y="13">{$eLeft}</text>
    <text x="{$rightMid}" y="14" fill="#010101" fill-opacity=".3">{$eLabel}</text>
    <text x="{$rightMid}" y="13">{$eLabel}</text>
  </g>
</svg>
SVG;
    }

    private function xml(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
