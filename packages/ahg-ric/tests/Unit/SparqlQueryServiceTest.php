<?php

/**
 * SparqlQueryServiceTest - Unit tests for SPARQL query service
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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

namespace Tests\Unit;

use Tests\TestCase;
use AhgRic\Services\SparqlQueryService;

class SparqlQueryServiceTest extends TestCase
{
    private SparqlQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SparqlQueryService();
    }

    public function test_can_instantiate(): void
    {
        $this->assertInstanceOf(SparqlQueryService::class, $this->service);
    }

    public function test_search_returns_array(): void
    {
        $result = $this->service->search('test');
        
        $this->assertIsArray($result);
    }

    public function test_get_statistics_returns_array(): void
    {
        $result = $this->service->getStatistics();
        
        $this->assertIsArray($result);
    }

    public function test_clear_cache_no_error(): void
    {
        $this->service->clearCache();
        $this->assertTrue(true);
    }
}
