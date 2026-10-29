<?php

/**
 * VectorSearchDisclosureTest - semantic search hits come from a Qdrant
 * collection that holds every record, drafts included, with titles in the
 * payload. Visitors must get only published, unrestricted hits; staff see all.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Support\StatusRow;
use AhgSearch\Services\VectorSearchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VectorSearchDisclosureTest extends TestCase
{
    use DatabaseTransactions;

    private int $published;

    private int $draft;

    protected function setUp(): void
    {
        parent::setUp();
        \AhgCore\Services\AclService::forgetUser();
        $this->published = $this->record('ZZ Vector published', 160);
        $this->draft = $this->record('ZZ Vector draft', 159);

        $hits = [
            ['id' => $this->draft, 'score' => 0.9, 'title' => 'ZZ Vector draft', 'slug' => 'vsd-'.$this->draft],
            ['id' => $this->published, 'score' => 0.8, 'title' => 'ZZ Vector published', 'slug' => 'vsd-'.$this->published],
        ];
        $this->app->instance(VectorSearchService::class, new class($hits) extends VectorSearchService
        {
            public function __construct(private array $hits) {}

            public function embedQuery(string $text): ?array
            {
                return [0.1, 0.2];
            }

            public function fetchPointVector(string $collection, int $pointId): ?array
            {
                return [0.1, 0.2];
            }

            public ?string $searched = null;

            public function qdrantSearch(string $collection, array $vector, int $limit, ?array $filter = null): ?array
            {
                $this->searched = $collection;

                return array_map(fn ($h) => $h + ['payload' => ['text' => 'ZZ internal payload']], $this->hits);
            }
        });
    }

    private function record(string $title, int $status): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert(['id' => $id, 'parent_id' => 1, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => $title]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => 'vsd-'.$id]);
        StatusRow::set($id, 158, $status);

        return $id;
    }

    private function admin(): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'vsd-'.$id, 'email' => uniqid('vsd-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    public function test_visitors_get_only_published_hits(): void
    {
        $this->getJson('/api/search/semantic?q=anything')->assertOk()
            ->assertSee('ZZ Vector published')
            ->assertDontSee('ZZ Vector draft');

        $this->getJson('/api/search/semantic/similar/'.$this->published)->assertOk()
            ->assertDontSee('ZZ Vector draft');
    }

    public function test_visitors_cannot_choose_a_collection_or_see_payloads(): void
    {
        $this->getJson('/api/search/semantic?q=anything&collection=km_heratio')->assertOk()
            ->assertJsonPath('collection', 'anc_records')
            ->assertDontSee('ZZ internal payload');
        $this->assertSame('anc_records', app(VectorSearchService::class)->searched);
    }

    public function test_staff_get_every_hit(): void
    {
        $this->actingAs($this->admin())->getJson('/api/search/semantic?q=anything')->assertOk()
            ->assertSee('ZZ Vector published')
            ->assertSee('ZZ Vector draft');
    }
}
