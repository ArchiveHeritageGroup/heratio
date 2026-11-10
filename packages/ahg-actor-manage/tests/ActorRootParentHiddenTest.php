<?php

/**
 * ActorRootParentHiddenTest - an authority record whose parent is the repository
 * root (6) does not show "Parent authority record [Untitled]" (10 Nov 2026:
 * migrated authority records on heratio.org showed it; only the actor root, 3,
 * was skipped).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgActorManage\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActorRootParentHiddenTest extends TestCase
{
    use DatabaseTransactions;

    public function test_repository_root_is_not_shown_as_parent(): void
    {
        // The repository root as heratio.org has it: an actor row with an empty name and a slug.
        DB::table('object')->insertOrIgnore(['id' => 6, 'class_name' => 'QubitRepository', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insertOrIgnore(['id' => 6, 'source_culture' => 'en']);
        DB::table('actor_i18n')->insertOrIgnore(['id' => 6, 'culture' => 'en']);
        if (! DB::table('slug')->where('object_id', 6)->exists()) {
            DB::table('slug')->insert(['object_id' => 6, 'slug' => 'zz-repository-root']);
        }

        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitActor', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'parent_id' => 6, 'source_culture' => 'en']);
        DB::table('actor_i18n')->insert(['id' => $id, 'culture' => 'en', 'authorized_form_of_name' => 'ZZ Root Parent Person']);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => 'zz-root-parent-'.$id]);
        \AhgCore\Support\StatusRow::set($id, 158, 160);

        $this->get('/actor/zz-root-parent-'.$id)->assertOk()->assertSee('ZZ Root Parent Person')->assertDontSee('Parent authority record');
    }
}
