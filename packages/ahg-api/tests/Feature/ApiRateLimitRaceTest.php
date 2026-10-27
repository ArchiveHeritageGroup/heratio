<?php

/**
 * ApiRateLimitRaceTest - two requests opening the same rate-limit window must
 * both count, not 500. heratio.org error log, 27 Oct 2026 00:26: "Duplicate
 * entry '12-2026-10-27 00:00:00' for key unique_key_window" from
 * updateOrInsert, which checked for the row and then inserted it.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgApi\Middleware\ApiRateLimit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiRateLimitRaceTest extends TestCase
{
    use DatabaseTransactions;

    private function hit(int $keyId): int
    {
        $request = Request::create('/api/v2/descriptions?limit=1');
        $request->attributes->set('api_key_id', $keyId);

        return (new ApiRateLimit())->handle($request, fn () => response('ok'))->getStatusCode();
    }

    private function windowCount(int $keyId): int
    {
        return (int) DB::table('ahg_api_rate_limit')->where('api_key_id', $keyId)
            ->where('window_start', now()->startOfHour())->value('request_count');
    }

    /** Let "the other request" create the window row right after the first query matching $when. */
    private function raceAfter(int $keyId, callable $when): \stdClass
    {
        $state = new \stdClass();
        $state->raced = false;
        DB::listen(function ($q) use ($state, $keyId, $when) {
            if (! $state->raced && str_contains($q->sql, 'ahg_api_rate_limit') && $when(strtolower($q->sql))) {
                $state->raced = true;
                DB::table('ahg_api_rate_limit')->insert(['api_key_id' => $keyId, 'window_start' => now()->startOfHour(), 'request_count' => 1]);
            }
        });

        return $state;
    }

    private function key(): int
    {
        $user = (int) DB::table('user')->value('id');
        if (! $user) {
            $this->markTestSkipped('no user to own a test key');
        }

        return (int) DB::table('ahg_api_key')->insertGetId(['user_id' => $user, 'name' => 'rate limit race test',
            'api_key' => hash('sha256', random_bytes(16)), 'api_key_prefix' => 'rlt'.bin2hex(random_bytes(2))]);
    }

    /** The prod failure: the row appears after an "exists?" check and before the insert. */
    public function test_a_window_created_after_the_existence_check_is_not_a_500(): void
    {
        $keyId = $this->key();
        $race = $this->raceAfter($keyId, fn ($sql) => str_contains($sql, 'exists'));

        $this->assertSame(200, $this->hit($keyId));
        $this->assertSame($race->raced ? 2 : 1, $this->windowCount($keyId), 'every request is counted');
    }

    /** The only gap left: the row appears between the limit read and the write. */
    public function test_a_window_created_after_the_limit_read_is_counted(): void
    {
        $keyId = $this->key();
        $race = $this->raceAfter($keyId, fn ($sql) => str_starts_with(ltrim($sql), 'select') && str_contains($sql, 'limit 1'));

        $this->assertSame(200, $this->hit($keyId));
        $this->assertTrue($race->raced);
        $this->assertSame(2, $this->windowCount($keyId));
        $this->assertSame(200, $this->hit($keyId));
        $this->assertSame(3, $this->windowCount($keyId));
    }
}
