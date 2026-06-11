<?php
/**
 * Heratio - public "Authenticity" front door (issue #1209, north star).
 *
 * The visible front door to the C2PA content-credentials layer. Where
 * /verify/{slug} answers "is THIS record real?", this page answers the
 * institution-level question: "how much of what you hold here can be
 * cryptographically verified, and how does that work?". It is public,
 * read-only, and degrades gracefully to a "not yet enabled" state when the
 * c2pa layer is absent or nothing has been signed yet.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Controllers;

use AhgC2pa\Services\AuthenticityStatsService;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

final class AuthenticityController extends Controller
{
    public function __construct(private AuthenticityStatsService $stats)
    {
    }

    /**
     * Public authenticity / content-credentials dashboard.
     */
    public function index(): View
    {
        return view('ahg-c2pa::authenticity.index', [
            'stats' => $this->stats->snapshot(),
        ]);
    }

    /**
     * Public, plain-language "Content Credentials" explainer / trust page
     * (deepens #1209 / #1201).
     *
     * Tells the public WHAT content credentials are, WHY this institution uses
     * them, and HOW to verify - linking the existing verify tools rather than
     * reimplementing any of them. International, jurisdiction-neutral, and
     * non-technical. Read-only: it never writes and never calls AI.
     *
     * A couple of headline numbers are pulled from AuthenticityStatsService
     * purely as social proof. They are surfaced defensively: the snapshot is
     * already Schema-guarded and try/catch-wrapped, and any failure here is
     * swallowed so the page degrades to copy-only and never 500s.
     */
    public function explainer(): View
    {
        $headline = null;

        try {
            $snapshot = $this->stats->snapshot();

            // Only surface numbers when the layer is enabled AND something is
            // actually signed; otherwise the explainer stays purely educational
            // rather than advertising an empty coverage figure.
            if (($snapshot['enabled'] ?? false) === true
                && (int) ($snapshot['records_with_credentials'] ?? 0) > 0) {
                $headline = [
                    'records_with_credentials' => (int) ($snapshot['records_with_credentials'] ?? 0),
                    'covered_masters'          => (int) ($snapshot['covered_masters'] ?? 0),
                    'coverage_pct'             => (float) ($snapshot['coverage_pct'] ?? 0.0),
                ];
            }
        } catch (\Throwable) {
            // Numbers are a nicety, not a requirement: degrade to copy-only.
            $headline = null;
        }

        return view('ahg-c2pa::content-credentials', [
            'headline' => $headline,
        ]);
    }
}
