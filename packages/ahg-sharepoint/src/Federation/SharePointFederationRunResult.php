<?php

/**
 * SharePointFederationRunResult — value object returned by SharePointFederationRunner.
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
 * Issue #1221 — three honest states for a SharePoint federated run:
 *   - notConfigured(): no tenant on this instance; render a clean empty state.
 *   - failed():        a tenant exists but the Graph call errored; show message.
 *   - ok():            results (possibly empty) returned successfully.
 * -----------------------------------------------------------------------------
 */

namespace AhgSharePoint\Federation;

final class SharePointFederationRunResult
{
    public const STATE_NOT_CONFIGURED = 'not_configured';

    public const STATE_FAILED = 'failed';

    public const STATE_OK = 'ok';

    /**
     * @param PeerSearchResult[] $results
     */
    private function __construct(
        public readonly string $state,
        public readonly bool $configured,
        public readonly ?int $tenantId = null,
        public readonly array $results = [],
        public readonly ?string $message = null,
    ) {
    }

    public static function notConfigured(): self
    {
        return new self(
            state: self::STATE_NOT_CONFIGURED,
            configured: false,
            tenantId: null,
            results: [],
            message: 'SharePoint is not configured on this instance. Add a Microsoft 365 tenant under Admin > SharePoint > Tenants to enable federated search.',
        );
    }

    public static function failed(int $tenantId, string $message): self
    {
        return new self(
            state: self::STATE_FAILED,
            configured: true,
            tenantId: $tenantId,
            results: [],
            message: $message,
        );
    }

    /**
     * @param PeerSearchResult[] $results
     */
    public static function ok(int $tenantId, array $results): self
    {
        return new self(
            state: self::STATE_OK,
            configured: true,
            tenantId: $tenantId,
            results: $results,
            message: null,
        );
    }

    public function isOk(): bool
    {
        return $this->state === self::STATE_OK;
    }

    public function count(): int
    {
        return count($this->results);
    }

    /**
     * Flatten to a JSON-friendly array for the package-owned search route and
     * any API caller.
     */
    public function toArray(): array
    {
        return [
            'state'      => $this->state,
            'configured' => $this->configured,
            'tenant_id'  => $this->tenantId,
            'message'    => $this->message,
            'count'      => $this->count(),
            'results'    => array_map(static fn (PeerSearchResult $r) => $r->toArray(), $this->results),
        ];
    }
}
