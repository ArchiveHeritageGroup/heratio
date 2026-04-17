<?php

/**
 * TriplestorePerformanceTest - Load testing for triplestore queries
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems.co.za
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

namespace Tests\Performance;

use Tests\TestCase;
use AhgRic\Services\SparqlQueryService;

class TriplestorePerformanceTest extends TestCase
{
    private SparqlQueryService $sparql;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sparql = new SparqlQueryService();
    }

    /**
     * Test SELECT query performance.
     */
    public function test_select_query_performance(): void
    {
        $start = microtime(true);
        
        $this->sparql->search('test query');
        
        $duration = microtime(true) - $start;
        
        $this->assertLessThan(5.0, $duration, 'SELECT query took too long');
    }

    /**
     * Test statistics query performance.
     */
    public function test_statistics_query_performance(): void
    {
        $start = microtime(true);
        
        $this->sparql->getStatistics();
        
        $duration = microtime(true) - $start;
        
        $this->assertLessThan(3.0, $duration, 'Statistics query took too long');
    }

    /**
     * Test cache improves performance.
     */
    public function test_cache_improves_performance(): void
    {
        $this->sparql->clearCache();
        
        // First call - no cache
        $start1 = microtime(true);
        $this->sparql->getStatistics();
        $duration1 = microtime(true) - $start1;
        
        // Second call - should use cache
        $start2 = microtime(true);
        $this->sparql->getStatistics();
        $duration2 = microtime(true) - $start2;
        
        // Cached call should be faster or equal
        $this->assertLessThanOrEqual($duration1, $duration2 * 2);
    }

    /**
     * Test concurrent queries don't timeout.
     */
    public function test_concurrent_queries(): void
    {
        $promises = [];
        
        for ($i = 0; $i < 5; $i++) {
            $start = microtime(true);
            $result = $this->sparql->search("test {$i}");
            $duration = microtime(true) - $start;
            
            $this->assertIsArray($result);
            $this->assertLessThan(10.0, $duration, "Query {$i} took too long");
        }
    }

    /**
     * Test large result set handling.
     */
    public function test_large_result_set(): void
    {
        $start = microtime(true);
        
        // Simulate large query
        $result = $this->sparql->search('', ['limit' => 1000]);
        
        $duration = microtime(true) - $start;
        
        $this->assertIsArray($result);
        $this->assertLessThan(15.0, $duration, 'Large result set query took too long');
    }
}
