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
}
