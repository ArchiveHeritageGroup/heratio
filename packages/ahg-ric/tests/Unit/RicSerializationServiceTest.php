<?php

/**
 * RicSerializationServiceTest - Unit tests for RIC-O Serialization
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

namespace Tests\Unit;

use Tests\TestCase;
use AhgRic\Services\RicSerializationService;

class RicSerializationServiceTest extends TestCase
{
    private RicSerializationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RicSerializationService();
    }

    /**
     * Test that service can be instantiated.
     */
    public function test_can_instantiate(): void
    {
        $this->assertInstanceOf(RicSerializationService::class, $this->service);
    }

    /**
     * Test serialization returns array.
     */
    public function test_serialize_record_returns_array(): void
    {
        // Test with non-existent ID returns error array
        $result = $this->service->serializeRecord(999999);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test agent serialization returns array.
     */
    public function test_serialize_agent_returns_array(): void
    {
        // Test with non-existent ID returns error array
        $result = $this->service->serializeAgent(999999);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test function serialization returns array.
     */
    public function test_serialize_function_returns_array(): void
    {
        // Test with non-existent ID returns error array
        $result = $this->service->serializeFunction(999999);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test repository serialization returns array.
     */
    public function test_serialize_repository_returns_array(): void
    {
        // Test with non-existent ID returns error array
        $result = $this->service->serializeRepository(999999);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test ISCAP compliance addition.
     */
    public function test_add_iscap_compliance(): void
    {
        $entity = [
            '@id' => 'http://example.org/test',
            '@type' => 'rico:Record',
        ];

        $result = $this->service->addIscapCompliance($entity, 1, 'information_object');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('@id', $result);
    }

    /**
     * Test record set export returns array.
     */
    public function test_export_record_set_returns_array(): void
    {
        // Test with non-existent ID
        $result = $this->service->exportRecordSet(999999);
        
        $this->assertIsArray($result);
    }
}
