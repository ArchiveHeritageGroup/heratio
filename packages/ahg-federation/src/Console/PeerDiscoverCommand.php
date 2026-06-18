<?php

/**
 * PeerDiscoverCommand - crawl federation peers and record their advertised
 * Federation Query Protocol capabilities (F2, heratio#1315).
 *
 *   php artisan ahg:federation-discover            probe every discoverable peer
 *   php artisan ahg:federation-discover --enabled  only federation_enabled peers
 *
 * Delegates to PeerDiscoveryService, which fetches each peer's
 * /open-data/protocol(.json) + /open-data/maturity(.json) over the shared
 * SSRF-guarded FederationClient and writes the outcome (reachable / version /
 * declared surfaces / maturity / status) back onto the peer row. Fail-soft:
 * zero peers prints an empty summary and exits 0; a dead peer is reported, not
 * fatal.
 *
 * Scheduled daily by AhgFederationServiceProvider (gated on federation_enabled).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * @author     Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgFederation\Console;

use AhgFederation\Services\PeerDiscoveryService;
use Illuminate\Console\Command;

class PeerDiscoverCommand extends Command
{
    protected $signature = 'ahg:federation-discover
                            {--enabled : Probe only federation_enabled peers (default: every discoverable peer)}';

    protected $description = 'Crawl federation peers and cache their advertised Federation Query Protocol capabilities.';

    public function handle(PeerDiscoveryService $service): int
    {
        $enabledOnly = (bool) $this->option('enabled');

        $this->info($enabledOnly
            ? 'Discovering federation-enabled peers...'
            : 'Discovering all reachable peers...');

        $summary = $service->discoverAll($enabledOnly);

        if ($summary['probed'] === 0) {
            $this->line('No discoverable peers (a peer needs an http(s) base_url). Nothing to do.');

            return self::SUCCESS;
        }

        foreach ($summary['results'] as $r) {
            $surfaces = empty($r['surfaces']) ? '-' : implode(',', $r['surfaces']);
            $this->line(sprintf(
                '  [%-13s] %s  v%s  surfaces=%s  maturity=%s',
                $r['status'],
                $r['name'],
                $r['protocol_version'] ?? '?',
                $surfaces,
                $r['maturity'] ?? '-'
            ));
        }

        $this->info(sprintf(
            'Probed %d peer%s: %d ok, %d unreachable, %d non-compliant.',
            $summary['probed'],
            $summary['probed'] === 1 ? '' : 's',
            $summary['ok'] ?? 0,
            $summary['unreachable'] ?? 0,
            $summary['non_compliant'] ?? 0
        ));

        return self::SUCCESS;
    }
}
