<?php
/**
 * Heratio - public collection-wide TRUST DASHBOARD (issue #1209, north star).
 *
 * "Trust at a glance": the public, read-only summary of how much of everything
 * published here carries verifiable authenticity. Where /verify is the
 * content-credentials front door and /authenticity/{id} is the per-record
 * report, THIS page rolls the whole collection up into a handful of honest big
 * numbers + simple CSS bars: how many published records and master files carry
 * content credentials, how many are C2PA-signed, how many verify vs failed vs
 * are unsigned, and how much of the metadata involved AI - with a human kept
 * accountable.
 *
 * It delegates entirely to TrustDashboardService, which reads the existing
 * signal tables (ahg_c2pa_provenance / ahg_c2pa_manifest / ahg_ai_inference /
 * ahg_ai_override) READ-ONLY with cheap aggregate COUNTs, scoped to PUBLISHED
 * records. Nothing here writes, signs, runs AI, or re-verifies. Every path is
 * resilient: a missing layer or unreachable DB degrades to the honest empty
 * state ("authenticity signals are still being established"), and neither the
 * page nor the JSON ever 500s.
 *
 * The HTML route is registered single-segment (/trust) in the service
 * provider's register() via callAfterResolving('router'), BEFORE the IO slug
 * catch-all (/{slug}) loads in boot(), so first-match-wins resolution always
 * picks it (the same pattern /content-credentials uses). The machine companion
 * keeps its real .json extension (nginx passes *.json through to Laravel) and
 * is CORS-open so any page can fetch it.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Controllers;

use AhgC2pa\Services\TrustDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class TrustController extends Controller
{
    public function __construct(private TrustDashboardService $service)
    {
    }

    /**
     * Public "Trust at a glance" dashboard. Never 500s: any fault degrades to the
     * honest empty-state snapshot so the page always renders.
     */
    public function index(): View
    {
        return view('ahg-c2pa::trust.index', [
            'trust' => $this->safeSnapshot(),
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
     * Build the snapshot without letting a fault escape. TrustDashboardService is
     * already fully guarded, but a hard fault must still degrade to a well-formed
     * empty snapshot rather than a 500.
     *
     * @return array<string,mixed>
     */
    private function safeSnapshot(): array
    {
        try {
            return $this->service->snapshot();
        } catch (Throwable $e) {
            Log::warning('c2pa trust dashboard: snapshot threw; serving empty state', ['err' => $e->getMessage()]);

            return $this->emptySnapshot();
        }
    }

    /**
     * The honest zero-state shape, matching TrustDashboardService::snapshot() so
     * the view never has to branch on missing keys.
     *
     * @return array<string,mixed>
     */
    private function emptySnapshot(): array
    {
        return [
            'generated_at'        => gmdate('Y-m-d\TH:i:s\Z'),
            'caveat'              => TrustDashboardService::HONEST_CAVEAT,
            'content_credentials' => [
                'layer_installed'          => false,
                'can_sign'                 => false,
                'reason'                   => 'unavailable',
                'published_records'        => 0,
                'records_with_credentials' => 0,
                'records_signed'           => 0,
                'masters_total'            => 0,
                'masters_signed'           => 0,
                'masters_unsigned'         => 0,
                'signed_verified'          => 0,
                'signed_failed'            => 0,
                'manifests_total'          => 0,
                'coverage_pct'             => 0.0,
                'credentials_pct'          => 0.0,
                'verified_pct'             => 0.0,
                'last_signed_at'           => null,
                'issuers'                  => 0,
            ],
            'ai_inference'        => [
                'layer_installed'   => false,
                'published_records' => 0,
                'records_with_ai'   => 0,
                'inferences_total'  => 0,
                'reviewed'          => 0,
                'pending'           => 0,
                'ai_coverage_pct'   => 0.0,
                'reviewed_pct'      => 0.0,
            ],
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
