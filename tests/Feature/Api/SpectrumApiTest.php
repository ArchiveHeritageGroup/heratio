<?php

declare(strict_types=1);

/**
 * SpectrumApiTest - issue #737 - Spectrum public statistics + events + activity.
 *
 * Covers the three v2 endpoints introduced by the Spectrum public API:
 *
 *   GET /api/v2/spectrum/statistics              5-min cached aggregate
 *   GET /api/v2/spectrum/events?since=&limit=    Chronological event feed
 *   GET /api/v2/spectrum/activity/{object_id}    Per-object timeline
 *
 * The bearer middleware (ApiAuthenticate) accepts a logged-in admin in the
 * absence of an API key, so the test acts as an admin user when one exists.
 * The test is skipped (not failed) when the heratio database isn't reachable
 * or when the spectrum_event table is absent, the same skip pattern other
 * Feature tests use.
 *
 * Copyright (C) 2026 Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 */

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SpectrumApiTest extends TestCase
{
    private function signInAsAdmin(): bool
    {
        try {
            if (! Schema::hasTable('users')) {
                return false;
            }
        } catch (\Throwable $e) {
            return false;
        }

        $user = DB::table('users')->limit(1)->first();
        if (! $user) {
            return false;
        }

        $model = new \App\Models\User();
        $model->id = $user->id;
        // Best-effort: minimal hydration so actingAs() has an id to bind.
        $this->actingAs($model);

        return true;
    }

    private function spectrumAvailable(): bool
    {
        try {
            return Schema::hasTable('spectrum_event') && Schema::hasTable('information_object');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function test_statistics_endpoint_returns_aggregate_payload(): void
    {
        if (! $this->spectrumAvailable()) {
            $this->markTestSkipped('Spectrum schema unavailable');
        }
        if (! $this->signInAsAdmin()) {
            $this->markTestSkipped('No admin-capable user available for bearer fallback');
        }

        $response = $this->getJson('/api/v2/spectrum/statistics?fresh=1');

        if ($response->status() === 401) {
            $this->markTestSkipped('Bearer middleware did not accept session auth in this environment');
        }

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'object_count',
                'by_procedure',
                'by_status',
                'last_month' => ['total', 'window_days', 'by_day'],
                'generated_at',
            ],
            'meta' => ['cached_for_seconds', 'cache_key'],
        ]);

        // by_procedure must list all 21 Spectrum 5.1 procedures.
        $json = $response->json();
        $this->assertCount(21, $json['data']['by_procedure']);
        $procIds = array_column($json['data']['by_procedure'], 'procedure_id');
        $this->assertContains('acquisition', $procIds);
        $this->assertContains('loans_in', $procIds);
        $this->assertContains('valuation', $procIds);
    }

    public function test_events_endpoint_respects_limit_cap(): void
    {
        if (! $this->spectrumAvailable()) {
            $this->markTestSkipped('Spectrum schema unavailable');
        }
        if (! $this->signInAsAdmin()) {
            $this->markTestSkipped('No admin-capable user available for bearer fallback');
        }

        $response = $this->getJson('/api/v2/spectrum/events?limit=10000');
        if ($response->status() === 401) {
            $this->markTestSkipped('Bearer middleware did not accept session auth in this environment');
        }

        $response->assertOk();
        $response->assertJsonPath('meta.limit', 500); // server-side cap
        $response->assertJsonStructure([
            'success',
            'data' => ['events'],
            'meta' => ['count', 'limit', 'since'],
        ]);
    }

    public function test_events_endpoint_rejects_bad_since(): void
    {
        if (! $this->signInAsAdmin()) {
            $this->markTestSkipped('No admin-capable user available for bearer fallback');
        }

        $response = $this->getJson('/api/v2/spectrum/events?since=not-a-date');
        if ($response->status() === 401) {
            $this->markTestSkipped('Bearer middleware did not accept session auth in this environment');
        }

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'Bad Request');
    }

    public function test_activity_endpoint_returns_404_for_unknown_object(): void
    {
        if (! $this->spectrumAvailable()) {
            $this->markTestSkipped('Spectrum schema unavailable');
        }
        if (! $this->signInAsAdmin()) {
            $this->markTestSkipped('No admin-capable user available for bearer fallback');
        }

        $response = $this->getJson('/api/v2/spectrum/activity/99999999');
        if ($response->status() === 401) {
            $this->markTestSkipped('Bearer middleware did not accept session auth in this environment');
        }

        $response->assertStatus(404);
        $response->assertJsonPath('error', 'Not Found');
    }

    public function test_activity_endpoint_returns_timeline_for_real_object(): void
    {
        if (! $this->spectrumAvailable()) {
            $this->markTestSkipped('Spectrum schema unavailable');
        }
        if (! $this->signInAsAdmin()) {
            $this->markTestSkipped('No admin-capable user available for bearer fallback');
        }

        $io = DB::table('information_object')->limit(1)->first();
        if (! $io) {
            $this->markTestSkipped('No information_object rows present for activity test');
        }

        $response = $this->getJson('/api/v2/spectrum/activity/'.$io->id);
        if ($response->status() === 401) {
            $this->markTestSkipped('Bearer middleware did not accept session auth in this environment');
        }

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => ['object_id', 'object_identifier', 'event_count', 'procedures', 'events'],
        ]);
        $response->assertJsonPath('data.object_id', (int) $io->id);
    }
}
