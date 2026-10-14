<?php

/**
 * DraftRecordFileGateTest - the /uploads gate refuses a draft record's files
 * to anyone who cannot already see drafts, while published records' files,
 * shared files and files that belong to no record stay public.
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

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Support\StatusRow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DraftRecordFileGateTest extends TestCase
{
    use DatabaseTransactions;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        \AhgCore\Services\AclService::forgetUser();
        foreach (['digital_object', 'status', 'information_object'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("$t table not present in this install.");
            }
        }
        $this->dir = 'uploads/r/gate-'.uniqid();
    }

    private function objectRow(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** A record with publication status 160 (published) or 159 (draft). */
    private function record(int $status): int
    {
        $id = $this->objectRow('QubitInformationObject');
        DB::table('information_object')->insert(['id' => $id, 'source_culture' => 'en']);
        StatusRow::set($id, 158, $status);

        return $id;
    }

    private function file(?int $ioId, string $name, ?int $parentId = null): int
    {
        $id = $this->objectRow('QubitDigitalObject');
        DB::table('digital_object')->insert([
            'id' => $id, 'object_id' => $parentId ? null : $ioId, 'parent_id' => $parentId,
            'usage_id' => $parentId ? 141 : 140, 'name' => $name, 'path' => '/'.$this->dir.'/', 'mime_type' => 'text/plain',
        ]);

        return $id;
    }

    private function gate(string $name, ?User $as = null)
    {
        $req = $as ? $this->actingAs($as) : $this;

        return $req->withHeader('X-Original-URI', '/'.$this->dir.'/'.$name)->get(route('io.privacy.file-check'));
    }

    private function admin(): User
    {
        $id = $this->objectRow('QubitUser');
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'gate-'.$id, 'email' => uniqid('gate-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    public function test_a_draft_records_file_and_its_derivatives_are_refused_to_the_public(): void
    {
        $master = $this->file($this->record(159), 'consent.jpg');
        $this->file(null, 'consent_141.jpg', $master);

        $this->gate('consent.jpg')->assertForbidden();
        $this->gate('consent_141.jpg')->assertForbidden();
    }

    public function test_published_shared_and_unlinked_files_stay_public(): void
    {
        $this->file($this->record(160), 'public.jpg');
        $this->file($this->record(159), 'shared.jpg');
        $this->file($this->record(160), 'shared.jpg');

        $this->gate('public.jpg')->assertNoContent();
        $this->gate('shared.jpg')->assertNoContent();
        $this->gate('not-a-record-file.png')->assertNoContent();
    }

    public function test_staff_who_see_drafts_still_get_the_file(): void
    {
        $this->file($this->record(159), 'draft.pdf');

        $this->gate('draft.pdf', $this->admin())->assertNoContent();
    }
}
