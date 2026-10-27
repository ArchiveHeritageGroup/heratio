<?php

/**
 * WorkedExamplesTest - runs the calls shown on the help page
 * docs/help/api-worked-examples.md (heratio#1544), in the same order, so the
 * page cannot drift from what the API does. If this test changes, change the
 * page too.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgUserManage\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkedExamplesTest extends TestCase
{
    use DatabaseTransactions;

    private function key(array $scopes, ?int $groupId): string
    {
        $user = app(UserService::class)->create(['username' => 'we'.bin2hex(random_bytes(4)), 'email' => 'we'.bin2hex(random_bytes(4)).'@test.invalid',
            'password' => 'x'.bin2hex(random_bytes(8)), 'authorized_form_of_name' => 'Worked examples', 'active' => 1, 'groups' => []]);
        if ($groupId) {
            DB::table('acl_user_group')->insert(['user_id' => $user, 'group_id' => $groupId]);
        }
        Cache::forget("acl_groups_{$user}");

        return app(\AhgApi\Services\ApiKeyService::class)->issue($user, 'worked examples', $scopes)['api_key'];
    }

    public function test_the_rest_examples_work_as_documented(): void
    {
        $key = $this->key(['read', 'write', 'delete'], \AhgCore\Models\AclGroup::ADMINISTRATOR_ID);
        $h = ['X-API-Key' => $key];

        // 1. Discovery needs no key.
        $this->getJson('/api/v2')->assertOk();

        // 2. List, paged.
        $list = $this->withHeaders($h)->getJson('/api/v2/descriptions?limit=2&sort=updated')->assertOk();
        $this->assertArrayHasKey('results', $list->json('data'));
        $this->assertSame(2, $list->json('meta.limit'));
        $this->assertArrayHasKey('self', $list->json('links'));

        // 3. Create.
        $title = 'Worked example '.bin2hex(random_bytes(3));
        $created = $this->withHeaders($h)->postJson('/api/v2/descriptions', [
            'title' => $title,
            'identifier' => 'WE-001',
            'scope_and_content' => 'Letters about the harbour works.',
            'publication_status' => 'published',
            'dates' => [['type' => 'creation', 'start_date' => '1890-01-01', 'end_date' => '1899-12-31', 'date_display' => '1890s']],
        ])->assertStatus(201);
        $slug = $created->json('data.slug');
        $this->assertNotEmpty($slug);
        $this->assertSame($title, $created->json('data.title'));

        // 4. Read it back: the full record, with its rights block.
        $read = $this->withHeaders($h)->getJson("/api/v2/descriptions/{$slug}")->assertOk();
        $this->assertSame($title, $read->json('data.title'));
        $this->assertArrayHasKey('rights', $read->json('data'));

        // 5. Update one field.
        $this->withHeaders($h)->patchJson("/api/v2/descriptions/{$slug}", ['scope_and_content' => 'Letters and plans about the harbour works.'])
            ->assertOk()->assertJsonPath('data.message', 'Description updated.');

        // 6. Search.
        $found = $this->withHeaders($h)->postJson('/api/v2/search', ['query' => $title, 'limit' => 5])->assertOk();
        $this->assertContains($slug, array_column($found->json('data.results') ?? [], 'slug'));

        // 7. Delete, then it is gone.
        $this->withHeaders($h)->deleteJson("/api/v2/descriptions/{$slug}")->assertOk()->assertJsonPath('data.message', 'Description deleted.');
        $this->withHeaders($h)->getJson("/api/v2/descriptions/{$slug}")->assertNotFound();
    }

    public function test_the_error_examples_work_as_documented(): void
    {
        $this->getJson('/api/v2/descriptions')->assertUnauthorized();
        $this->withHeaders(['X-API-Key' => 'not-a-key'])->getJson('/api/v2/descriptions')->assertUnauthorized();

        // A read-only key may not write.
        $readOnly = $this->key(['read'], \AhgCore\Models\AclGroup::EDITOR_ID);
        $this->withHeaders(['X-API-Key' => $readOnly])->postJson('/api/v2/descriptions', ['title' => 'Nope'])->assertForbidden();
    }

    public function test_the_harvesting_examples_work_as_documented(): void
    {
        $identify = $this->get('/oai?verb=Identify')->assertOk()->getContent();
        $this->assertStringContainsString('<Identify>', $identify);

        $this->assertStringContainsString('<metadataPrefix>oai_dc</metadataPrefix>', $this->get('/oai?verb=ListMetadataFormats')->assertOk()->getContent());

        $records = $this->get('/oai?verb=ListRecords&metadataPrefix=oai_dc')->assertOk()->getContent();
        $this->assertTrue(str_contains($records, '<ListRecords>') || str_contains($records, 'noRecordsMatch'));

        $this->get('/.well-known/resourcesync')->assertOk();
        $this->get('/robots.txt')->assertOk();
        $this->get('/sitemap.xml')->assertOk();
    }
}
