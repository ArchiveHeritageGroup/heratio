<?php

/**
 * ExternalAuthoritySameAsTest - verified external authorities become sameAs.
 *
 * An actor's verified Wikidata, VIAF or LoC identifiers are emitted as
 * schema:sameAs on its Linked Data entity (/id/actor/{slug}) and page markup,
 * and as owl:sameAs on its RiC agent, so machines can follow them. Unverified
 * identifiers are not asserted.
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

use AhgCore\Support\ExternalAuthorityLinks;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExternalAuthoritySameAsTest extends TestCase
{
    use DatabaseTransactions;

    private int $actor;

    private string $slug;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = (int) DB::table('object')->insertGetId(['class_name' => 'QubitActor', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $this->actor, 'entity_type_id' => 132, 'source_culture' => 'en']);
        DB::table('actor_i18n')->insert(['id' => $this->actor, 'culture' => 'en', 'authorized_form_of_name' => 'ZZ Douglas Adams']);
        $this->slug = 'zz-same-as-'.$this->actor;
        DB::table('slug')->insert(['object_id' => $this->actor, 'slug' => $this->slug]);
        $add = fn (string $type, string $value, int $verified, ?string $uri = null) => DB::table('ahg_actor_identifier')->insert([
            'actor_id' => $this->actor, 'identifier_type' => $type, 'identifier_value' => $value, 'uri' => $uri,
            'is_verified' => $verified, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $add('wikidata', 'Q42', 1);
        $add('viaf', '113230702', 1, 'https://viaf.org/viaf/113230702');
        $add('isni', '0000 0001 2137 4987', 0);   // unverified: never asserted
    }

    public function test_only_verified_identifiers_become_uris(): void
    {
        $this->assertSame(
            ['https://viaf.org/viaf/113230702', 'http://www.wikidata.org/entity/Q42'],
            ExternalAuthorityLinks::forActor($this->actor)
        );
    }

    public function test_the_linked_data_entity_carries_same_as(): void
    {
        $json = $this->get('/id/actor/'.$this->slug, ['Accept' => 'application/ld+json'])->assertOk()->json();
        $graph = $json['@graph'][0] ?? $json;
        $sameAs = (array) ($graph['sameAs'] ?? []);

        $this->assertContains('http://www.wikidata.org/entity/Q42', $sameAs);
        $this->assertContains('https://viaf.org/viaf/113230702', $sameAs);
    }

    public function test_the_ric_agent_carries_owl_same_as(): void
    {
        $agent = app(\AhgRic\Services\RicSerializationService::class)->serializeAgent($this->actor);

        $this->assertContains(['@id' => 'http://www.wikidata.org/entity/Q42'], $agent['owl:sameAs'] ?? []);
    }
}
