<?php

/**
 * ImportReplaceTest - "update" and "replace" imports (heratio#1535).
 *
 * "Delete and replace" had no branch at all and inserted duplicates. Now:
 * update fills in the non-blank cells of a matched record; replace overwrites
 * every mapped field in place (a blank cell clears it) and keeps the record's
 * id and children. Authorities match on their identifier before their name.
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

use AhgDataMigration\Services\DataMigrationService;
use AhgInformationObjectManage\Services\InformationObjectService as Io;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportReplaceTest extends TestCase
{
    use DatabaseTransactions;

    private function import(string $type, array $mapped, string $importType): int
    {
        $svc = app(DataMigrationService::class);
        $map = [];
        $m = new \ReflectionMethod($svc, 'importRecord');

        return (int) $m->invokeArgs($svc, [$type, $mapped, $importType, 'en', &$map]);
    }

    private function io(): array
    {
        $id = Io::create(['title' => 'ZZ Import target', 'identifier' => 'ZZ-IMP-001', 'scope_and_content' => 'Original scope.', 'arrangement' => 'Original arrangement.'], 'en');
        $child = Io::create(['title' => 'ZZ Import child', 'parent_id' => $id], 'en');

        return [$id, $child];
    }

    private function i18n(int $id): object
    {
        return DB::table('information_object_i18n')->where('id', $id)->where('culture', 'en')->first();
    }

    public function test_update_fills_in_non_blank_cells_and_leaves_the_rest(): void
    {
        [$id] = $this->io();

        $got = $this->import('informationObject', ['identifier' => 'ZZ-IMP-001', 'title' => 'ZZ Updated title', 'scope_and_content' => ''], 'update');

        $this->assertSame($id, $got);
        $this->assertSame('ZZ Updated title', $this->i18n($id)->title);
        $this->assertSame('Original scope.', $this->i18n($id)->scope_and_content, 'a blank cell must not wipe a value on update');
    }

    public function test_replace_overwrites_in_place_and_keeps_id_and_children(): void
    {
        [$id, $child] = $this->io();
        $before = DB::table('information_object')->count();

        $got = $this->import('informationObject', ['identifier' => 'ZZ-IMP-001', 'title' => 'ZZ Replaced', 'scope_and_content' => '', 'arrangement' => 'New arrangement.'], 'replace');

        $this->assertSame($id, $got, 'replace keeps the record id');
        $this->assertSame($before, DB::table('information_object')->count(), 'no duplicate is created');
        $this->assertSame('ZZ Replaced', $this->i18n($id)->title);
        $this->assertNull($this->i18n($id)->scope_and_content, 'a blank cell clears the field on replace');
        $this->assertSame('New arrangement.', $this->i18n($id)->arrangement);
        $this->assertSame($id, (int) DB::table('information_object')->where('id', $child)->value('parent_id'));
    }

    public function test_an_authority_matches_on_its_identifier_before_its_name(): void
    {
        $actorId = (int) DB::table('object')->insertGetId(['class_name' => 'QubitActor', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $actorId, 'source_culture' => 'en', 'description_identifier' => 'ZZ-AUTH-9']);
        DB::table('actor_i18n')->insert(['id' => $actorId, 'culture' => 'en', 'authorized_form_of_name' => 'ZZ Old Name']);

        $got = $this->import('actor', ['description_identifier' => 'ZZ-AUTH-9', 'authorized_form_of_name' => 'ZZ New Name', 'history' => 'Founded 1902.'], 'update');

        $this->assertSame($actorId, $got);
        $this->assertSame('ZZ New Name', DB::table('actor_i18n')->where('id', $actorId)->value('authorized_form_of_name'));
    }
}
