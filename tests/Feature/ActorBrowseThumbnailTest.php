<?php

/**
 * ActorBrowseThumbnailTest - the authority record list shows each record's
 * thumbnail (27 Nov 2026). The list read image usage 113 instead of the
 * thumbnail (142) and only its folder, not the file name, so no authority list
 * ever showed one.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Services\AclService;
use AhgCore\Services\DigitalObjectService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActorBrowseThumbnailTest extends TestCase
{
    use DatabaseTransactions;

    private function obj(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_the_list_links_the_thumbnail_file(): void
    {
        $actor = $this->obj('QubitActor');
        DB::table('actor')->insert(['id' => $actor, 'parent_id' => 3, 'source_culture' => 'en']);
        DB::table('actor_i18n')->insert(['id' => $actor, 'culture' => 'en', 'authorized_form_of_name' => 'Thumbtest Person '.$actor]);
        DB::table('slug')->insert(['object_id' => $actor, 'slug' => 'thumbtest-'.$actor]);
        AclService::setActorPublicationStatus($actor, 160);

        $master = $this->obj('QubitDigitalObject');
        DB::table('digital_object')->insert(['id' => $master, 'object_id' => $actor, 'usage_id' => DigitalObjectService::USAGE_MASTER,
            'name' => 'master_p.jpg', 'path' => "/uploads/r/{$actor}/", 'mime_type' => 'image/jpeg']);
        $thumb = $this->obj('QubitDigitalObject');
        DB::table('digital_object')->insert(['id' => $thumb, 'parent_id' => $master, 'usage_id' => DigitalObjectService::USAGE_THUMBNAIL,
            'name' => 'thumbnail_p.jpg', 'path' => "/uploads/r/{$actor}/", 'mime_type' => 'image/jpeg']);

        $this->get('/actor/browse?subquery=Thumbtest+Person+'.$actor)
            ->assertOk()
            ->assertSee("/uploads/r/{$actor}/thumbnail_p.jpg", false);
    }
}
