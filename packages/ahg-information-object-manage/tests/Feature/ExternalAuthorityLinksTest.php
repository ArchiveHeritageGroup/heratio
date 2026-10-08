<?php

/**
 * ExternalAuthorityLinksTest - authority links on the archival description page.
 *
 * heratio#1538: Wikidata, VIAF and other identifiers were shown on the
 * authority (actor) page only. The description page now lists them for its
 * creators and name access points, in the shared access-points section.
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

use AhgInformationObjectManage\Services\InformationObjectService as Io;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExternalAuthorityLinksTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('ahg_actor_identifier')) {
            $this->markTestSkipped('ahg_actor_identifier missing');
        }
    }

    private function actor(string $name): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitActor', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('actor_i18n')->insert(['id' => $id, 'culture' => 'en', 'authorized_form_of_name' => $name]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => 'zz-actor-'.$id]);

        return $id;
    }

    private function identifier(int $actorId, string $type, string $value, string $uri): void
    {
        DB::table('ahg_actor_identifier')->insert([
            'actor_id' => $actorId, 'identifier_type' => $type, 'identifier_value' => $value,
            'uri' => $uri, 'is_verified' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function description(): array
    {
        $id = Io::create(['title' => 'ZZ authority links test'], 'en');
        \AhgCore\Support\StatusRow::set($id, 158, 160);

        return [$id, DB::table('slug')->where('object_id', $id)->value('slug')];
    }

    public function test_creator_and_name_access_point_links_are_shown(): void
    {
        [$io, $slug] = $this->description();
        $creator = $this->actor('ZZ Creator Person');
        $this->identifier($creator, 'wikidata', 'Q42', 'https://www.wikidata.org/wiki/Q42');
        $eventId = (int) DB::table('object')->insertGetId(['class_name' => 'QubitEvent', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('event')->insert(['id' => $eventId, 'object_id' => $io, 'actor_id' => $creator, 'type_id' => 111, 'source_culture' => 'en']);

        $named = $this->actor('ZZ Named Body');
        $this->identifier($named, 'viaf', '123456', 'https://viaf.org/viaf/123456');
        $relId = (int) DB::table('object')->insertGetId(['class_name' => 'QubitRelation', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('relation')->insert(['id' => $relId, 'subject_id' => $io, 'object_id' => $named, 'type_id' => 161, 'source_culture' => 'en']);

        $html = $this->section($io, $slug);

        $this->assertStringContainsString('External authority links', $html);
        $this->assertStringContainsString('https://www.wikidata.org/wiki/Q42', $html);
        $this->assertStringContainsString('https://viaf.org/viaf/123456', $html);
        $this->assertStringContainsString('ZZ Creator Person', $html);
    }

    public function test_no_row_when_no_linked_actor_has_identifiers(): void
    {
        [$io, $slug] = $this->description();

        $this->assertStringNotContainsString('External authority links', $this->section($io, $slug));
    }

    /** The full description page, as a visitor sees it (heratio#1547: no longer a section-only render). */
    private function section(int $io, string $slug): string
    {
        return $this->get('/'.$slug)->assertOk()->getContent();
    }
}
