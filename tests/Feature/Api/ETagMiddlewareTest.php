<?php

/**
 * ETagMiddlewareTest
 *
 * Issue #652 Phase 1 - verifies ETag round-trip on a GET endpoint.
 *
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright 2026 Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ETagMiddlewareTest extends TestCase
{
    public function test_get_request_carries_etag_header(): void
    {
        if (! class_exists(\AhgApi\Middleware\ETagMiddleware::class)) {
            $this->markTestSkipped('ETag middleware not yet wired in.');
        }

        Route::get('_test/etag', function () {
            return response()->json(['ping' => 'pong']);
        })->middleware(\AhgApi\Middleware\ETagMiddleware::class);

        $response = $this->get('/_test/etag');
        $response->assertStatus(200);
        $this->assertNotEmpty($response->headers->get('ETag'));
    }

    public function test_matching_if_none_match_returns_304(): void
    {
        if (! class_exists(\AhgApi\Middleware\ETagMiddleware::class)) {
            $this->markTestSkipped('ETag middleware not yet wired in.');
        }

        Route::get('_test/etag', function () {
            return response()->json(['ping' => 'pong']);
        })->middleware(\AhgApi\Middleware\ETagMiddleware::class);

        $first = $this->get('/_test/etag');
        $etag = $first->headers->get('ETag');

        $second = $this->withHeaders(['If-None-Match' => $etag])->get('/_test/etag');
        $second->assertStatus(304);
        $this->assertSame('', (string) $second->getContent());
    }

    public function test_mismatched_if_none_match_returns_200(): void
    {
        if (! class_exists(\AhgApi\Middleware\ETagMiddleware::class)) {
            $this->markTestSkipped('ETag middleware not yet wired in.');
        }

        Route::get('_test/etag', function () {
            return response()->json(['ping' => 'pong']);
        })->middleware(\AhgApi\Middleware\ETagMiddleware::class);

        $response = $this->withHeaders(['If-None-Match' => '"deadbeef"'])->get('/_test/etag');
        $response->assertStatus(200);
    }
}
