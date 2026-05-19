<?php

/**
 * FusekiAgentAdapter - Service for Heratio
 *
 * Candidate adapter STUB for PERSON / ORG candidates sourced from the
 * Heratio Fuseki dataset. Task 8/future will wire this against
 * urn:heratio:auth-res:graph:decisions sibling dataset; for Task 3 we
 * ship MySQL adapters only and this returns an empty list so the
 * generator's adapter-iteration logic remains uniform.
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
 */

namespace AhgAuthorityResolution\Services\Adapters;

class FusekiAgentAdapter implements CandidateAdapterInterface
{
    public function supports(string $entityType): bool
    {
        return in_array($entityType, ['PERSON', 'ORG'], true);
    }

    public function search(string $query, string $entityType, int $limit): array
    {
        // Task 8/future will wire against urn:heratio:auth-res:graph:decisions
        // sibling dataset; for Task 3 we ship MySQL adapters only.
        return [];
    }
}
