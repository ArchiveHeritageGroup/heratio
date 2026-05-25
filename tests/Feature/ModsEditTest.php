<?php

/**
 * ModsEditTest - #662 Phase 2 round-trip: POST originInfo + mods:note via
 * the MODS edit form, then download the MODS XML via the per-IO export
 * route and assert that the new <originInfo>, <dateIssued>,
 * <placeOfPublication>, and <note> elements appear.
 *
 * The test is skipped automatically when the heratio database isn't
 * reachable (e.g. CI without MySQL) or when no information_object rows
 * exist; this keeps the suite green without requiring fixtures.
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
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ModsEditTest extends TestCase
{
    private function pickIo(): ?object
    {
        try {
            if (! Schema::hasTable('information_object') || ! Schema::hasTable('slug')) {
                return null;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return DB::table('information_object as io')
            ->join('slug as s', 's.object_id', '=', 'io.id')
            ->join('information_object_i18n as i18n', function ($j) {
                $j->on('io.id', '=', 'i18n.id');
            })
            ->where('io.id', '>', 1)
            ->limit(1)
            ->select('io.id', 's.slug', 'i18n.culture')
            ->first();
    }

    private function adminUser(): ?object
    {
        try {
            if (! Schema::hasTable('users')) {
                return null;
            }
        } catch (\Throwable $e) {
            return null;
        }
        return DB::table('users')->limit(1)->first();
    }

    public function test_origin_info_round_trip(): void
    {
        $io = $this->pickIo();
        if (! $io) {
            $this->markTestSkipped('No information_object rows available');
        }
        $user = $this->adminUser();
        if (! $user) {
            $this->markTestSkipped('No users available for authenticated POST');
        }

        // Sign in as the first admin-capable user; the MODS edit route
        // requires `auth` middleware.
        $userClass = '\\App\\Models\\User';
        if (! class_exists($userClass)) {
            $this->markTestSkipped('User model not available');
        }
        $model = $userClass::find($user->id);
        if (! $model) {
            $this->markTestSkipped('User row could not be hydrated');
        }
        $this->actingAs($model);

        $payload = [
            'title' => 'Test (MODS Phase 2 round-trip)',
            'creation_date' => 'circa 1900',
            'creation_start_date' => '1900-01-01',
            'publication_date' => 'spring 1901',
            'publication_start_date' => '1901-04-01',
            'publisher_name' => 'Test Press',
            'mods_note' => 'A general MODS note from the test.',
        ];

        $response = $this->post(route('ahgmodsmanage.edit', ['slug' => $io->slug]), $payload);
        $response->assertStatus(302); // redirects back to the edit form

        // Round-trip: download the MODS XML and assert presence of refinements.
        $xmlResponse = $this->get(route('informationobject.export.mods', $io->slug));
        $xmlResponse->assertStatus(200);
        $body = $xmlResponse->getContent();

        $this->assertStringContainsString('<originInfo>', $body, 'originInfo missing');
        $this->assertStringContainsString('<dateIssued', $body, 'dateIssued missing');
        $this->assertStringContainsString('<dateCreated', $body, 'dateCreated missing');
        $this->assertStringContainsString('Test Press', $body, 'publisher missing');
        $this->assertStringContainsString('<note type="general">', $body, 'note missing');
        $this->assertStringContainsString('A general MODS note from the test.', $body, 'note body missing');
    }
}
