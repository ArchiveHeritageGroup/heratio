<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     * Note: Skipped - requires MySQL database with AtoM tables (static_page, slug, menu, etc.)
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->markTestSkipped('Requires MySQL with AtoM tables (static_page, slug, menu)');
        
        $response = $this->get('/');
        $response->assertStatus(200);
    }
}
