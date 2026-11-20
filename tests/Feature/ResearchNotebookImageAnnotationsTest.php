<?php

/**
 * ResearchNotebookImageAnnotationsTest - annotations drawn in the record
 * image viewer (ahg_iiif_annotation) are listed, read-only, in the
 * researcher's notebook at /research/annotations, each linked back to the
 * record its canvas belongs to.
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
use AhgCore\Services\AttachedDigitalObjectService;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResearchNotebookImageAnnotationsTest extends TestCase
{
    use DatabaseTransactions;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        \AhgCore\Services\AclService::forgetUser();
        foreach (['research_researcher', 'ahg_iiif_annotation', 'digital_object', 'slug'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("$t table not present in this install.");
            }
        }
        $this->dir = 'uploads/r/notebook-test-'.uniqid();
    }

    private function objectRow(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function user(): User
    {
        $id = $this->objectRow('QubitUser');
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        $email = uniqid('nbimg-', true).'@example.test';
        DB::table('user')->insert(['id' => $id, 'username' => 'nbimg-'.$id, 'email' => $email, 'active' => 1]
            + LoginController::hashPassword('secret-pass-1'));
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 99]);
        Cache::forget("acl_groups_{$id}");
        DB::table('research_researcher')->insert([
            'user_id' => $id, 'first_name' => 'Notebook', 'last_name' => 'Tester '.$id, 'email' => $email,
            'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::findOrFail($id);
    }

    private function record(string $title): int
    {
        $id = $this->objectRow('QubitInformationObject');
        DB::table('information_object')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => $title]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => Str::slug($title).'-'.$id]);

        return $id;
    }

    private function file(?int $ioId, string $name, ?int $parentId = null): int
    {
        $id = $this->objectRow('QubitDigitalObject');
        DB::table('digital_object')->insert([
            'id' => $id, 'object_id' => $parentId ? null : $ioId, 'parent_id' => $parentId,
            'usage_id' => $parentId ? 141 : 140, 'name' => $name, 'path' => '/'.$this->dir.'/', 'mime_type' => 'image/jpeg',
        ]);

        return $id;
    }

    private function drawn(int $userId, string $canvas, string $html, string $visibility = 'private'): void
    {
        DB::table('ahg_iiif_annotation')->insert([
            'uuid' => (string) Str::uuid(),
            'target_iri' => $canvas,
            'information_object_id' => null, // older rows were stored unresolved
            'visibility' => $visibility,
            'body_json' => json_encode([
                'type' => 'Annotation',
                'body' => [['type' => 'TextualBody', 'value' => $html, 'purpose' => 'describing']],
                'target' => $canvas,
            ]),
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_the_notebook_lists_the_users_own_drawn_annotations_linked_to_their_records(): void
    {
        $me = $this->user();
        $other = $this->user();

        // Primary image: the canvas is the reference derivative's URL.
        $io = $this->record('Notebook Bible Test');
        $master = $this->file($io, 'master_page.jpg');
        $this->file(null, 'reference_page.jpg', $master);
        $base = 'http://localhost/'.$this->dir.'/';
        $this->drawn((int) $me->id, $base.'reference_page.jpg/canvas/1', '<p>Margin note on the title page</p>');

        // A stranger's public annotation must not appear in my notebook.
        $this->drawn((int) $other->id, $base.'reference_page.jpg/canvas/1', '<p>Someone else wrote this</p>', 'public');

        // Attached (second) image: no object_id, linked through the link table.
        if (AttachedDigitalObjectService::available()) {
            $att = $this->file(null, 'master_verso.jpg');
            DB::table('digital_object')->where('id', $att)->update(['object_id' => null]);
            DB::table(AttachedDigitalObjectService::TABLE)->insert([
                'information_object_id' => $io, 'digital_object_id' => $att, 'sort_order' => 1, 'is_primary' => 0,
            ]);
            $this->drawn((int) $me->id, $base.'master_verso.jpg/canvas/1', '<p>Stamp on the verso</p>', 'public');
        }

        $response = $this->actingAs($me)->get(route('research.annotations'))->assertOk();

        $response->assertSee('Drawn on images')
            ->assertSee('Margin note on the title page')
            ->assertSee('Notebook Bible Test')
            ->assertSee(url('/notebook-bible-test-'.$io), false)
            ->assertDontSee('Someone else wrote this');

        if (AttachedDigitalObjectService::available()) {
            $response->assertSee('Stamp on the verso');

            // The notebook visibility filter applies to drawn annotations too.
            $this->actingAs($me)->get(route('research.annotations', ['visibility' => 'private']))
                ->assertSee('Margin note on the title page')
                ->assertDontSee('Stamp on the verso');
        }
    }
}
