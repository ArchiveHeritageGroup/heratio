<?php

/**
 * ShaclValidationServiceTest - Unit tests for SHACL validation
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 */

namespace Tests\Unit;

use Tests\TestCase;
use AhgRic\Services\ShaclValidationService;

class ShaclValidationServiceTest extends TestCase
{
    private ShaclValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ShaclValidationService();
    }

    public function test_can_instantiate(): void
    {
        $this->assertInstanceOf(ShaclValidationService::class, $this->service);
    }

    public function test_validate_returns_array(): void
    {
        $entity = [
            '@id' => 'http://example.org/test',
            '@type' => 'rico:Record',
            'rico:identifier' => 'TEST-001',
        ];

        $result = $this->service->validateBeforeSave($entity, 'Record');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('warnings', $result);
    }

    public function test_mandatory_fields_check(): void
    {
        $entity = [
            '@id' => 'http://example.org/test',
            '@type' => 'rico:Person',
        ];

        $result = $this->service->validateBeforeSave($entity, 'Person');
        
        $this->assertIsArray($result);
    }
}
