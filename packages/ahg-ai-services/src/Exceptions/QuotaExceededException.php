<?php
/**
 * QuotaExceededException - thrown by the per-tenant quota gate before any
 * AI service dispatches an inference call. Carries machine-readable
 * details (tenant_id, service, window, used, limit) so callers can render
 * a useful error to the operator or surface a structured JSON response.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or (at
 * your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public
 * License for more details.
 */

declare(strict_types=1);

namespace AhgAiServices\Exceptions;

use RuntimeException;

final class QuotaExceededException extends RuntimeException
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $service,
        public readonly string $window,
        public readonly int $used,
        public readonly int $limit,
        ?string $message = null,
    ) {
        parent::__construct(
            $message ?? sprintf(
                'AI quota exceeded for service "%s" (tenant %d, window=%s, used=%d, limit=%d)',
                $service,
                $tenantId,
                $window,
                $used,
                $limit,
            ),
        );
    }

    /**
     * @return array{tenant_id:int,service:string,window:string,used:int,limit:int}
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'service'   => $this->service,
            'window'    => $this->window,
            'used'      => $this->used,
            'limit'     => $this->limit,
        ];
    }
}
