<?php

/**
 * SharePointFederationConfig — resolves SharePoint federated-search configuration
 * from this package's OWN Microsoft 365 tenant store, never from ahg-federation
 * peer config.
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
 * Issue #1221 — self-contained SharePoint federated-search config.
 *
 * This class is the single source of truth for "is SharePoint federated search
 * usable on this instance?". It reads from the package's own tenant store
 * (SharePointTenantRepository over the sharepoint_tenant table) so the connector
 * and the package's own search UI never depend on ahg-federation peer rows.
 *
 * It degrades cleanly: every method is safe to call when there is no tenant
 * configured, when the sharepoint_tenant table is missing (fresh install before
 * sharepoint:install), or when the DB is unreachable. In all of those cases
 * isConfigured() returns false and the callers render an honest
 * "SharePoint not configured" state instead of throwing.
 * -----------------------------------------------------------------------------
 */

namespace AhgSharePoint\Federation;

use AhgSharePoint\Repositories\SharePointTenantRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SharePointFederationConfig
{
    public function __construct(
        private readonly SharePointTenantRepository $tenants,
    ) {
    }

    /**
     * True only when at least one SharePoint tenant is on record. Never throws:
     * a missing table or an unreachable DB resolves to "not configured".
     */
    public function isConfigured(): bool
    {
        return $this->defaultTenantId() !== null;
    }

    /**
     * The tenant id the package-owned search UI uses by default: the first
     * tenant by id. Returns null when nothing is configured. Never throws.
     */
    public function defaultTenantId(): ?int
    {
        try {
            if (! Schema::hasTable('sharepoint_tenant')) {
                return null;
            }
            $row = DB::table('sharepoint_tenant')->orderBy('id')->first(['id']);

            return $row !== null ? (int) $row->id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Tenants as {id, label} options for the package-owned search form and for
     * the View::composer that injects them into the (locked) peer-edit blade.
     * Never throws; returns [] when unconfigured.
     *
     * @return array<int, array{id:int, label:string}>
     */
    public function tenantOptions(): array
    {
        try {
            if (! Schema::hasTable('sharepoint_tenant')) {
                return [];
            }
            $rows = $this->tenants->all();
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row->id ?? 0);
            if ($id <= 0) {
                continue;
            }
            $name = trim((string) ($row->name ?? ''));
            $aad = trim((string) ($row->tenant_id ?? ''));
            $label = $name !== '' ? $name : ($aad !== '' ? $aad : 'Tenant #'.$id);
            $out[] = ['id' => $id, 'label' => $label];
        }

        return $out;
    }

    /**
     * Build a synthetic peer row for the connector's bind(), assembled entirely
     * from the package's own tenant store plus an optional scope. This lets the
     * package run a SharePoint search WITHOUT any ahg-federation peer row.
     *
     * @param array{
     *     site_ids?: array<int,string>,
     *     drive_ids?: array<int,string>,
     *     max_results?: int
     * } $scope
     */
    public function syntheticPeer(int $tenantId, array $scope = []): object
    {
        $config = [
            'tenant_id'             => $tenantId,
            'default_site_ids'      => array_values(array_filter((array) ($scope['site_ids'] ?? []))),
            'default_drive_ids'     => array_values(array_filter((array) ($scope['drive_ids'] ?? []))),
            'max_results_per_query' => (int) ($scope['max_results'] ?? 50),
        ];

        return (object) [
            'peer_id'     => 0,
            'peer_name'   => 'SharePoint',
            'peer_type'   => SharePointGraphConnector::PEER_TYPE,
            'base_url'    => null,
            'priority'    => 100,
            'max_results' => $config['max_results_per_query'],
            'config'      => $config,
        ];
    }
}
