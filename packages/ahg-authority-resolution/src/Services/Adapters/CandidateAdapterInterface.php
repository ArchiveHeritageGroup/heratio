<?php

/**
 * CandidateAdapterInterface - Service for Heratio
 *
 * Contract for authority-store adapters used by CandidateGeneratorService.
 * Each adapter encapsulates one source of candidate authority records
 * (MySQL actors, MySQL place terms, Fuseki agent graph, Fuseki place
 * graph, etc.) and reports which entity types it can serve.
 *
 * Adapter results are normalised dicts (associative arrays) so the
 * generator can score and persist them without caring about the source.
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

interface CandidateAdapterInterface
{
    /**
     * Does this adapter handle the given entity_type?
     *
     * @param string $entityType One of PERSON, ORG, GPE, PLACE, LOC
     */
    public function supports(string $entityType): bool;

    /**
     * Search the underlying authority store for candidates matching $query.
     *
     * @param string $query      The mention's entity_value to look up.
     * @param string $entityType One of PERSON, ORG, GPE, PLACE, LOC.
     * @param int    $limit      Soft cap on rows returned.
     *
     * @return list<array{
     *     source: string,
     *     authority_id: int|null,
     *     fuseki_uri: string|null,
     *     display_name: string,
     * }>
     */
    public function search(string $query, string $entityType, int $limit): array;
}
