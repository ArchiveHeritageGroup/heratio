<?php

/**
 * ApiDocumentationTest - API Documentation Tests
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

namespace Tests\Feature;

use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    /**
     * Test OpenAPI specification endpoint exists.
     */
    public function test_openapi_endpoint_exists(): void
    {
        $response = $this->getJson('/api/ric/v1/openapi.json');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'openapi',
            'info',
            'paths',
            'components',
        ]);
    }

    /**
     * Test API returns proper JSON-LD content type.
     */
    public function test_returns_jsonld_content_type(): void
    {
        $response = $this->get('/api/ric/v1/health');
        
        $response->assertHeader('Content-Type', 'application/json');
    }

    /**
     * Test API info endpoint.
     */
    public function test_api_info_endpoint(): void
    {
        $response = $this->getJson('/api/ric/v1');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'name',
            'version',
            'description',
        ]);
    }
}
