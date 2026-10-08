<?php

/**
 * FusekiTestWriteGuardTest - the test suite never writes to a real triplestore.
 *
 * heratio#1527: the test database's settings point at the shared Fuseki, the
 * same /openric-model production reads. A test's MySQL rows roll back; its
 * triples did not, and ResearchEventEmitTest left 45 orphan activity graphs in
 * production. Both write paths - SparqlUpdateService and the queued
 * FusekiSyncJob - now skip under APP_ENV=testing unless a test opts in.
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

namespace Tests\Unit;

use AhgRic\Jobs\FusekiSyncJob;
use AhgRic\Services\SparqlUpdateService;
use Illuminate\Support\Facades\Log;
use ReflectionProperty;
use Tests\TestCase;

class FusekiTestWriteGuardTest extends TestCase
{
    /** Nothing listens on port 1, so a real attempt fails at once. */
    private const DEAD_ENDPOINT = 'http://127.0.0.1:1/openric-model/update';

    private function service(): SparqlUpdateService
    {
        $svc = new SparqlUpdateService();
        $prop = new ReflectionProperty($svc, 'updateEndpoint');
        $prop->setAccessible(true);
        $prop->setValue($svc, self::DEAD_ENDPOINT);

        return $svc;
    }

    public function test_writes_are_blocked_under_tests(): void
    {
        $this->assertTrue(SparqlUpdateService::writesBlocked());

        $result = $this->service()->executeUpdate('INSERT DATA { GRAPH <urn:test> { <urn:s> <urn:p> <urn:o> } }');

        $this->assertTrue($result['ok']);
        $this->assertArrayHasKey('skipped', $result, 'the update reached the network');
    }

    public function test_opting_in_really_writes(): void
    {
        // Proves the guard is what made the previous test pass: with it off the
        // write is attempted, and the dead endpoint refuses it.
        config(['ahg-ric.fuseki_writes_in_tests' => true]);
        Log::spy();

        $result = $this->service()->executeUpdate('INSERT DATA { GRAPH <urn:test> { <urn:s> <urn:p> <urn:o> } }');

        $this->assertFalse($result['ok']);
        $this->assertArrayNotHasKey('skipped', $result);
    }

    public function test_queued_sync_job_is_blocked_under_tests(): void
    {
        Log::spy();

        (new FusekiSyncJob(self::DEAD_ENDPOINT, null, null, 2, 'INSERT DATA { <urn:s> <urn:p> <urn:o> }'))->handle();

        // A real attempt against the dead endpoint would log a curl failure.
        Log::shouldNotHaveReceived('warning');
    }
}
