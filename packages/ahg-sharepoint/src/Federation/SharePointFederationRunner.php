<?php

/**
 * SharePointFederationRunner — self-contained entry point that runs a SharePoint
 * federated search from inside ahg-sharepoint, without any ahg-federation peer
 * row or dispatcher.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
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
 *
 * -----------------------------------------------------------------------------
 * Issue #1221 — self-contained SharePoint federated-search runner.
 *
 * This is the package-owned way to actually execute a SharePoint search. It
 * resolves a tenant from the package's own store (via SharePointFederationConfig),
 * builds a synthetic peer row, binds the relocated SharePointGraphConnector, and
 * returns a normalised RunResult. A future ahg-federation dispatcher does NOT
 * call this runner — it instantiates the connector class directly via the
 * connector registry this package publishes (see AhgSharePointServiceProvider).
 * This runner exists so the package is independently useful and testable.
 *
 * Degrade-when-unconfigured contract: run() NEVER throws for the "no tenant"
 * case. It returns a RunResult with configured=false and an honest message so
 * callers (controller, union catalogue) render a clean empty state, never a 500.
 * -----------------------------------------------------------------------------
 */

namespace AhgSharePoint\Federation;

use AhgFederation\Connectors\PeerSearchResult;
use Illuminate\Support\Facades\Log;

class SharePointFederationRunner
{
    public function __construct(
        private readonly SharePointFederationConfig $config,
        private readonly SharePointGraphConnector $connector,
    ) {
    }

    /**
     * Run a SharePoint federated search. Always returns a RunResult; it never
     * throws for the unconfigured case and never lets a Graph/transport error
     * escape (those are caught here and surfaced as RunResult::failed()).
     *
     * @param array{
     *     tenant_id?: int,
     *     site_ids?: array<int,string>,
     *     drive_ids?: array<int,string>,
     *     date_from?: ?string,
     *     date_to?: ?string,
     *     limit?: int
     * } $options
     */
    public function run(string $query, array $options = []): SharePointFederationRunResult
    {
        $tenantId = (int) ($options['tenant_id'] ?? 0);
        if ($tenantId <= 0) {
            $tenantId = (int) ($this->config->defaultTenantId() ?? 0);
        }

        if ($tenantId <= 0) {
            return SharePointFederationRunResult::notConfigured();
        }

        $limit = (int) ($options['limit'] ?? 50);
        $limit = max(1, min($limit, 50));

        $peer = $this->config->syntheticPeer($tenantId, [
            'site_ids'    => $options['site_ids'] ?? [],
            'drive_ids'   => $options['drive_ids'] ?? [],
            'max_results' => $limit,
        ]);

        $filters = [];
        $from = $options['date_from'] ?? null;
        $to = $options['date_to'] ?? null;
        if (! empty($from) || ! empty($to)) {
            $filters['date_range'] = array_filter([
                'from' => $from ?: null,
                'to'   => $to ?: null,
            ]);
        }

        try {
            $this->connector->bind($peer);
            /** @var PeerSearchResult[] $rows */
            $rows = $this->connector->search($query, $filters, $limit);
        } catch (\Throwable $e) {
            Log::warning('SharePointFederationRunner search failed', [
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
            ]);

            return SharePointFederationRunResult::failed($tenantId, $e->getMessage());
        }

        return SharePointFederationRunResult::ok($tenantId, $rows);
    }
}
