<?php

/**
 * SharePointFederatedSearchController — package-owned admin UI for running a
 * SharePoint federated search directly, independent of the ahg-federation
 * dispatcher.
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
 * Issue #1221 — a working, registered way to run a SharePoint search from this
 * package. Route: GET /sharepoint/federated-search (rendered form + results),
 * and GET /sharepoint/federated-search.json (JSON for the union catalogue or an
 * AJAX caller).
 *
 * This does NOT compete with the live ahg-federation /federation/* routes: it
 * sits under the package's own /sharepoint prefix. It degrades cleanly when no
 * tenant is configured (honest "not configured" panel, HTTP 200, never a 500).
 * -----------------------------------------------------------------------------
 */

namespace AhgSharePoint\Controllers;

use AhgSharePoint\Federation\SharePointFederationConfig;
use AhgSharePoint\Federation\SharePointFederationRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SharePointFederatedSearchController extends Controller
{
    public function __construct(
        private readonly SharePointFederationConfig $config,
        private readonly SharePointFederationRunner $runner,
    ) {
    }

    /**
     * Rendered admin search page. Always HTTP 200.
     */
    public function index(Request $request)
    {
        $configured = $this->config->isConfigured();
        $tenantOptions = $this->config->tenantOptions();
        $query = trim((string) $request->query('q', ''));

        $result = null;
        if ($configured && $query !== '') {
            $result = $this->runner->run($query, $this->extractOptions($request));
        }

        return view('ahg-sharepoint::federated-search', [
            'configured'    => $configured,
            'tenantOptions' => $tenantOptions,
            'query'         => $query,
            'result'        => $result,
        ]);
    }

    /**
     * JSON endpoint — for the union catalogue or AJAX. Always HTTP 200 with an
     * honest state. When unconfigured, returns {state:"not_configured", ...}.
     */
    public function json(Request $request): JsonResponse
    {
        if (! $this->config->isConfigured()) {
            return response()->json([
                'state'      => 'not_configured',
                'configured' => false,
                'count'      => 0,
                'results'    => [],
                'message'    => 'SharePoint is not configured on this instance.',
            ]);
        }

        $query = trim((string) $request->query('q', ''));
        if ($query === '') {
            return response()->json([
                'state'      => 'ok',
                'configured' => true,
                'count'      => 0,
                'results'    => [],
                'message'    => null,
            ]);
        }

        $result = $this->runner->run($query, $this->extractOptions($request));

        return response()->json($result->toArray());
    }

    /**
     * @return array{
     *     tenant_id?: int,
     *     site_ids?: array<int,string>,
     *     drive_ids?: array<int,string>,
     *     date_from?: ?string,
     *     date_to?: ?string,
     *     limit?: int
     * }
     */
    private function extractOptions(Request $request): array
    {
        $options = [];

        $tenantId = (int) $request->query('tenant_id', 0);
        if ($tenantId > 0) {
            $options['tenant_id'] = $tenantId;
        }

        $siteIds = $request->query('site_ids');
        if (is_string($siteIds) && $siteIds !== '') {
            $options['site_ids'] = array_values(array_filter(array_map('trim', explode(',', $siteIds))));
        } elseif (is_array($siteIds)) {
            $options['site_ids'] = array_values(array_filter(array_map('trim', $siteIds)));
        }

        $driveIds = $request->query('drive_ids');
        if (is_string($driveIds) && $driveIds !== '') {
            $options['drive_ids'] = array_values(array_filter(array_map('trim', explode(',', $driveIds))));
        } elseif (is_array($driveIds)) {
            $options['drive_ids'] = array_values(array_filter(array_map('trim', $driveIds)));
        }

        $from = trim((string) $request->query('date_from', ''));
        if ($from !== '') {
            $options['date_from'] = $from;
        }
        $to = trim((string) $request->query('date_to', ''));
        if ($to !== '') {
            $options['date_to'] = $to;
        }

        $limit = (int) $request->query('limit', 0);
        if ($limit > 0) {
            $options['limit'] = $limit;
        }

        return $options;
    }
}
