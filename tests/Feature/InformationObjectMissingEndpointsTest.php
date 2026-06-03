<?php

/**
 * InformationObjectMissingEndpointsTest - Coverage for issue #742.
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
 *
 * Smoke-tests the eight endpoints added by #742 (the missing PSIS-parity
 * surface):
 *   - GET  /informationobject/{slug}/modifications
 *   - GET  /informationobject/{slug}/tree-view
 *   - POST /informationobject/tree-sync/{id}
 *   - POST /informationobject/tree-move
 *   - GET  /informationobject/slug-preview
 *   - POST /informationobject/{id}/finding-aid
 *   - DELETE /informationobject/{id}/finding-aid
 *   - GET  /informationobject/browse/hierarchyData
 */

namespace Tests\Feature;

use Database\Factories\InformationObjectFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InformationObjectMissingEndpointsTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Helper: create an IO with a slug + i18n title for slug-keyed routes.
     */
    private function makeIoWithSlug(string $title, string $slug): object
    {
        $io = InformationObjectFactory::new()->withI18n(['title' => $title])->create();

        // The factory's afterCreating hook already inserts a slug row, and
        // slug.slug_U_1 is unique on object_id — a second insert collides.
        // Update the existing row to the requested slug instead of inserting.
        DB::table('slug')->updateOrInsert(
            ['object_id' => $io->id],
            ['slug' => $slug],
        );

        return $io;
    }

    public function test_modifications_page_renders_for_known_slug(): void
    {
        $io = $this->makeIoWithSlug('Test Modifications IO', 'test-modifications-' . uniqid());

        $resp = $this->get('/informationobject/' . DB::table('slug')->where('object_id', $io->id)->value('slug') . '/modifications');

        $resp->assertOk();
        $resp->assertSee('Modifications');
    }

    public function test_modifications_page_404s_for_unknown_slug(): void
    {
        $this->get('/informationobject/does-not-exist-' . uniqid() . '/modifications')
            ->assertNotFound();
    }

    public function test_tree_view_page_renders_for_known_slug(): void
    {
        $io = $this->makeIoWithSlug('Tree View IO', 'tree-view-' . uniqid());

        $slug = DB::table('slug')->where('object_id', $io->id)->value('slug');
        $resp = $this->get('/informationobject/' . $slug . '/tree-view');

        $resp->assertOk();
        $resp->assertSee('Tree view');
    }

    public function test_hierarchy_data_returns_json_array(): void
    {
        $resp = $this->getJson('/informationobject/browse/hierarchyData');

        $resp->assertOk();
        $this->assertIsArray($resp->json());
    }

    public function test_hierarchy_data_with_root_id_param(): void
    {
        $io = InformationObjectFactory::new()->create();

        $resp = $this->getJson('/informationobject/browse/hierarchyData?root_id=' . $io->id);
        $resp->assertOk();
        $this->assertIsArray($resp->json());
    }

    public function test_slug_preview_empty_title(): void
    {
        $resp = $this->getJson('/informationobject/slug-preview?title=');
        $resp->assertOk();
        $resp->assertJson(['slug' => '', 'conflict' => false, 'fallback' => false]);
    }

    public function test_slug_preview_builds_kebab_case_slug(): void
    {
        $resp = $this->getJson('/informationobject/slug-preview?title=' . urlencode('Hello World Title #742'));
        $resp->assertOk();
        $body = $resp->json();
        $this->assertNotEmpty($body['slug']);
        // Should be slugged — lowercase + hyphenated.
        $this->assertSame(strtolower($body['slug']), $body['slug']);
    }

    public function test_slug_preview_detects_conflict_with_existing_slug(): void
    {
        $io = $this->makeIoWithSlug('Conflict Test', 'conflict-test-fixed');

        $resp = $this->getJson('/informationobject/slug-preview?title=' . urlencode('Conflict Test Fixed'));
        $resp->assertOk();
        // The exact suffix depends on what else is in the DB, but the slug
        // returned must not equal 'conflict-test-fixed' verbatim.
        $body = $resp->json();
        $this->assertSame(true, $body['conflict']);
        $this->assertNotSame('conflict-test-fixed', $body['slug']);
    }

    public function test_finding_aid_actions_require_auth(): void
    {
        $io = InformationObjectFactory::new()->create();

        // POST without auth - middleware redirects (302) or 401/419 (csrf
        // missing). Either way: not 200/204.
        $resp = $this->post('/informationobject/' . $io->id . '/finding-aid');
        $this->assertNotEquals(200, $resp->getStatusCode());
        $this->assertNotEquals(204, $resp->getStatusCode());

        $resp = $this->delete('/informationobject/' . $io->id . '/finding-aid');
        $this->assertNotEquals(200, $resp->getStatusCode());
        $this->assertNotEquals(204, $resp->getStatusCode());
    }

    public function test_tree_sync_requires_auth(): void
    {
        $io = InformationObjectFactory::new()->create();
        $resp = $this->post('/informationobject/tree-sync/' . $io->id);
        $this->assertNotEquals(200, $resp->getStatusCode());
    }

    public function test_tree_move_requires_auth(): void
    {
        $resp = $this->post('/informationobject/tree-move', ['id' => 1, 'new_parent_id' => 2]);
        $this->assertNotEquals(200, $resp->getStatusCode());
    }
}
