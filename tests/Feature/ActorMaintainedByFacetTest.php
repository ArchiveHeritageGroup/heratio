<?php

/**
 * ActorMaintainedByFacetTest - the authority browse "Maintained by" facet lists
 * maintaining repositories and filters on them (27 Nov 2026). It read the
 * name-access-point relation instead, so it listed people, and the browse
 * ignored ?maintainedBy= so clicking an entry changed nothing.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgActorManage\Services\ActorBrowseService;
use AhgCore\Constants\TermId;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActorMaintainedByFacetTest extends TestCase
{
    use DatabaseTransactions;

    private function actor(string $name, string $class = 'QubitActor'): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'parent_id' => 3, 'source_culture' => 'en']);
        DB::table('actor_i18n')->insert(['id' => $id, 'culture' => 'en', 'authorized_form_of_name' => $name]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => 'mbtest-'.$id]);
        \AhgCore\Services\AclService::setActorPublicationStatus($id, 160);   // guests only see published records
        if ($class === 'QubitRepository') {
            DB::table('repository')->insert(['id' => $id, 'source_culture' => 'en']);
        }

        return $id;
    }

    private function link(int $subject, int $object, int $type): void
    {
        $rid = (int) DB::table('object')->insertGetId(['class_name' => 'QubitRelation', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('relation')->insert(['id' => $rid, 'subject_id' => $subject, 'object_id' => $object, 'type_id' => $type, 'source_culture' => 'en']);
    }

    public function test_facet_lists_the_maintaining_repository_and_filters_on_it(): void
    {
        $repo = $this->actor('MB Test Repository', 'QubitRepository');
        $kept = $this->actor('MB Test Kept Person');
        $other = $this->actor('MB Test Other Person');
        $this->link($kept, $repo, TermId::RELATION_MAINTAINING_REPOSITORY);
        $this->link($kept, $other, TermId::RELATION_NAME_ACCESS_POINT);   // a name link must not show up

        $svc = new ActorBrowseService('en');
        $facets = $svc->getMaintainedByFacets();
        $this->assertArrayHasKey($repo, $facets);
        $this->assertArrayNotHasKey($other, $facets);

        $params = ['page' => 1, 'limit' => 50, 'sort' => 'alphabetic', 'sortDir' => '', 'subquery' => '',
            'entityType' => '', 'repository' => '', 'maintainedBy' => (string) $repo, 'hasDigitalObject' => '', 'emptyField' => '',
            'relatedType' => '', 'relatedAuthority' => ''];
        for ($i = 0; $i < 10; $i++) {   // the controller always sends these
            $params += ["sq{$i}" => '', "sf{$i}" => '', "so{$i}" => 'and'];
        }
        $res = $svc->browseAdvanced($params);
        $ids = collect($res['hits'] ?? $res['results'] ?? $res['items'] ?? [])->map(fn ($h) => (int) (is_array($h) ? $h['id'] : $h->id))->all();
        $this->assertContains($kept, $ids);
        $this->assertNotContains($other, $ids);
    }
}
