<?php
/**
 * Heratio - public, catalogue-wide TRANSPARENCY REPORT (issue #1209).
 *
 * The PUBLIC, institution-wide transparency scorecard - the public counterpart
 * to the operator-only admin trust console (/admin/trust-console) and to the
 * per-record trust dossier (/trust-dossier). Where the dossier answers "what
 * can we attest about THIS record", this page rolls the whole PUBLISHED
 * catalogue up into five honest big numbers + simple CSS bars: content
 * credentials, AI provenance, integrity (fixity), preservation events, and
 * accessibility (alt-text).
 *
 * It delegates entirely to TransparencyReportService, which reads the existing
 * signal tables READ-ONLY with cheap aggregate COUNT / EXISTS queries, scoped to
 * PUBLISHED records. Nothing here writes, signs, runs AI, performs a
 * preservation action, or re-verifies. Every path is resilient: a missing layer
 * or unreachable DB degrades to the honest empty state, and neither the page nor
 * the JSON ever 500s.
 *
 * The HTML route is registered single-segment (/transparency) in the service
 * provider's register() via callAfterResolving('router'), BEFORE the IO slug
 * catch-all (/{slug}) loads in boot(), so first-match-wins resolution always
 * picks it (the same pattern /trust uses). The machine companion keeps its real
 * .json extension (nginx passes *.json through to Laravel) and is CORS-open so
 * any page can fetch it.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Controllers;

use AhgC2pa\Services\TransparencyReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class TransparencyController extends Controller
{
    public function __construct(private TransparencyReportService $service)
    {
    }

    /**
     * Public catalogue-wide transparency report. Never 500s: any fault degrades
     * to the honest empty-state snapshot so the page always renders.
     */
    public function index(): View
    {
        return view('ahg-c2pa::transparency.index', [
            'report' => $this->safeSnapshot(),
        ]);
    }

    /**
     * Machine-readable companion. CORS-open, read-only GET. Never 500s.
     */
    public function json(): JsonResponse
    {
        return response()
            ->json($this->safeSnapshot())
            ->withHeaders($this->corsHeaders());
    }

    /**
     * Build the snapshot without letting a fault escape. TransparencyReportService
     * is already fully guarded, but a hard fault must still degrade to a
     * well-formed empty snapshot rather than a 500.
     *
     * @return array<string,mixed>
     */
    private function safeSnapshot(): array
    {
        try {
            return $this->service->snapshot();
        } catch (Throwable $e) {
            Log::warning('c2pa transparency report: snapshot threw; serving empty state', ['err' => $e->getMessage()]);

            return $this->emptySnapshot();
        }
    }

    /**
     * The honest zero-state shape, matching TransparencyReportService::snapshot()
     * so the view never has to branch on missing keys.
     *
     * @return array<string,mixed>
     */
    private function emptySnapshot(): array
    {
        $dim = static function (string $key, string $unit): array {
            return [
                'key'       => $key,
                'count'     => 0,
                'total'     => 0,
                'share_pct' => 0.0,
                'unit'      => $unit,
                'installed' => false,
            ];
        };

        return [
            'generated_at'        => gmdate('Y-m-d\TH:i:s\Z'),
            'caveat'              => TransparencyReportService::HONEST_CAVEAT,
            'published_records'   => 0,
            'published_masters'   => 0,
            'content_credentials' => $dim('content_credentials', 'records') + [
                'masters_total' => 0, 'masters_signed' => 0, 'masters_pct' => 0.0,
                'records_signed' => 0, 'verified' => 0, 'failed' => 0,
            ],
            'ai_provenance'       => $dim('ai_provenance', 'records') + [
                'inferences_total' => 0, 'reviewed' => 0, 'reviewed_pct' => 0.0,
            ],
            'integrity'           => $dim('integrity', 'master files') + [
                'verified' => 0, 'verified_pct' => 0.0,
            ],
            'preservation'        => $dim('preservation', 'objects') + [
                'events_total' => 0,
            ],
            'accessibility'       => $dim('accessibility', 'images'),
            'has_any_signal'      => false,
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
