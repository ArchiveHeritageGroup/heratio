<?php

/**
 * IdempotencyKeyMiddlewareTest
 *
 * Issue #652 Phase 1 - verifies Idempotency-Key replay returns cached
 * response without re-running the controller.
 *
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright 2026 Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IdempotencyKeyMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    public function test_replay_with_same_key_returns_cached_response(): void
    {
        if (! class_exists(\AhgApi\Middleware\IdempotencyKeyMiddleware::class)) {
            $this->markTestSkipped('Idempotency middleware not yet wired in.');
        }
        if (! Schema::hasTable('ahg_api_idempotency_key')) {
            $this->markTestSkipped('ahg_api_idempotency_key table missing — run package install.sql.');
        }

        $counter = 0;
        Route::post('_test/idempotent', function () use (&$counter) {
            $counter++;

            return response()->json(['count' => $counter], 201);
        })->middleware(\AhgApi\Middleware\IdempotencyKeyMiddleware::class);

        $key = 'test-key-'.bin2hex(random_bytes(6));

        $first = $this->postJson('/_test/idempotent', ['x' => 1], ['Idempotency-Key' => $key]);
        $first->assertStatus(201);
        $this->assertSame(1, $first->json('count'));

        $second = $this->postJson('/_test/idempotent', ['x' => 1], ['Idempotency-Key' => $key]);
        $second->assertStatus(201);
        // Controller should NOT have re-run — counter stays at 1.
        $this->assertSame(1, $second->json('count'));
        $this->assertSame('true', $second->headers->get('X-Idempotent-Replay'));
    }

    public function test_replay_with_different_body_returns_409(): void
    {
        if (! class_exists(\AhgApi\Middleware\IdempotencyKeyMiddleware::class)) {
            $this->markTestSkipped('Idempotency middleware not yet wired in.');
        }
        if (! Schema::hasTable('ahg_api_idempotency_key')) {
            $this->markTestSkipped('ahg_api_idempotency_key table missing — run package install.sql.');
        }

        Route::post('_test/idempotent', function () {
            return response()->json(['ok' => true], 201);
        })->middleware(\AhgApi\Middleware\IdempotencyKeyMiddleware::class);

        $key = 'test-key-'.bin2hex(random_bytes(6));

        $this->postJson('/_test/idempotent', ['x' => 1], ['Idempotency-Key' => $key])
            ->assertStatus(201);

        $this->postJson('/_test/idempotent', ['x' => 99], ['Idempotency-Key' => $key])
            ->assertStatus(409);
    }

    public function test_missing_idempotency_key_header_is_passthrough(): void
    {
        if (! class_exists(\AhgApi\Middleware\IdempotencyKeyMiddleware::class)) {
            $this->markTestSkipped('Idempotency middleware not yet wired in.');
        }

        $counter = 0;
        Route::post('_test/idempotent-passthrough', function () use (&$counter) {
            $counter++;

            return response()->json(['count' => $counter], 201);
        })->middleware(\AhgApi\Middleware\IdempotencyKeyMiddleware::class);

        $this->postJson('/_test/idempotent-passthrough', ['x' => 1])->assertStatus(201);
        $this->postJson('/_test/idempotent-passthrough', ['x' => 1])->assertStatus(201);

        $this->assertSame(2, $counter, 'Both requests should have executed when no Idempotency-Key header is present.');
    }
}
