<?php

/**
 * DescriptionRicReadTest - GET /api/v2/descriptions/{slug}/ric (heratio#1558
 * ric_query): the record in RiC-O through the description read's gate, with
 * only the parent and parts that pass the same gate.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Constants\TermId;
use AhgUserManage\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DescriptionRicReadTest extends TestCase
{
    use DatabaseTransactions;

    private function record(string $title, int $parent, bool $published): array
    {
        $now = now();
        $id = DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => $now, 'updated_at' => $now]);
        $lft = (int) DB::table('information_object')->max('rgt') + 10;
        DB::table('information_object')->insert(['id' => $id, 'parent_id' => $parent, 'lft' => $lft, 'rgt' => $lft + 1, 'source_culture' => 'en', 'identifier' => 'RIC-'.$id]);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => $title]);
        $slug = 'ric-read-'.$id;
        DB::table('slug')->insert(['object_id' => $id, 'slug' => $slug]);
        DB::table('status')->insert(['id' => \AhgCore\Support\StatusRow::allocateId(), 'object_id' => $id,
            'type_id' => TermId::STATUS_TYPE_PUBLICATION, 'status_id' => $published ? TermId::PUBLICATION_STATUS_PUBLISHED : TermId::PUBLICATION_STATUS_DRAFT]);

        return [$id, $slug];
    }

    private function key(): string
    {
        $user = app(UserService::class)->create(['username' => 'rr'.bin2hex(random_bytes(4)), 'email' => 'rr'.bin2hex(random_bytes(4)).'@test.invalid',
            'password' => 'x'.bin2hex(random_bytes(8)), 'authorized_form_of_name' => 'RiC reader', 'active' => 1, 'groups' => []]);
        Cache::forget("acl_groups_{$user}");

        return app(\AhgApi\Services\ApiKeyService::class)->issue($user, 'ric test', ['read'])['api_key'];
    }

    public function test_the_record_comes_with_only_the_parts_a_visitor_may_see(): void
    {
        if (! class_exists(\AhgRic\Services\RicSerializationService::class)) {
            $this->markTestSkipped('ahg-ric not installed.');
        }
        [$fonds, $fondsSlug] = $this->record('RiC read fonds', 1, true);
        [, $partSlug] = $this->record('Published part', $fonds, true);
        [, $draftSlug] = $this->record('Draft part', $fonds, false);
        $key = $this->key();

        $ric = $this->withHeaders(['X-API-Key' => $key])->getJson("/api/v2/descriptions/{$fondsSlug}/ric")->assertOk()->json('data');
        $this->assertSame(url('/'.$fondsSlug), $ric['url']);
        $this->assertSame([url('/'.$partSlug)], array_column($ric['rico:hasOrHadPart'], '@id'), 'the draft part is not listed');
        $this->assertStringNotContainsString('Draft part', json_encode($ric));
        $this->assertArrayNotHasKey('rico:hasInstantiation', $ric, 'files are for staff here');

        // The published part names its parent; the draft part is not found at all.
        $part = $this->withHeaders(['X-API-Key' => $key])->getJson("/api/v2/descriptions/{$partSlug}/ric")->assertOk()->json('data');
        $this->assertSame(url('/'.$fondsSlug), $part['rico:isOrWasIncludedIn']['@id']);
        $this->withHeaders(['X-API-Key' => $key])->getJson("/api/v2/descriptions/{$draftSlug}/ric")->assertNotFound();
    }
}
