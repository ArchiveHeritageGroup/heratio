<?php

/**
 * LocalContextsHubServiceTest - issue #1448.
 *
 * Exercises LocalContextsHubService against the Local Contexts Hub API with
 * the Hub faked (Http::fake) - no network. Fixtures in ../fixtures:
 *  - hub-v1-project-labels.json / hub-v1-project-notices.json are real
 *    responses from the keyless v1 API for two Public projects
 *    (GET https://localcontextshub.org/api/v1/projects/{id}/, 2026-09-30).
 *  - hub-v2-project.json is the same content reshaped to the v2 contract in
 *    the Hub's OpenAPI schema (community as an object, notice unique_id etc.),
 *    since v2 needs an account key.
 *
 * Runs against the pre-built heratio_test DB and rolls back each test
 * (DatabaseTransactions). Skips when the ICIP tables are not installed.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgIcip\Tests\Feature;

use AhgIcip\Services\LocalContextsHubService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LocalContextsHubServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const HUB = 'https://hub.example.test';

    private const V2_ID = 'f4f5a843-77bd-403f-84a2-a1bb9288d932';

    private const NOTICE_ID = 'ea7e96b5-36dc-4b5c-97f3-ac5aadfe3a20';

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['icip_config', 'icip_hub_project', 'icip_tk_label_type'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("$t not present in this install.");
            }
        }
        Cache::flush();
        DB::table('icip_hub_project')->delete();
        $this->config([
            'local_contexts_hub_enabled' => '1',
            'local_contexts_hub_url'     => self::HUB.'/',
            'local_contexts_api_key'     => 'test-key',
        ]);
    }

    private function config(array $values): void
    {
        foreach ($values as $k => $v) {
            DB::table('icip_config')->updateOrInsert(['config_key' => $k], ['config_value' => $v]);
        }
    }

    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(__DIR__.'/../fixtures/'.$name), true);
    }

    public function test_sync_uses_v2_with_api_key_and_persists_labels_and_notices(): void
    {
        Http::fake([self::HUB.'/api/v2/projects/'.self::V2_ID.'/' => Http::response($this->fixture('hub-v2-project.json'))]);

        $r = app(LocalContextsHubService::class)->syncProject(self::V2_ID);

        $this->assertTrue($r['ok'], $r['error'] ?? '');
        $this->assertSame(3, $r['labels']);
        $this->assertSame(1, $r['notices']);
        Http::assertSent(fn (Request $req) => $req->hasHeader('X-Api-Key', 'test-key')
            && $req->url() === self::HUB.'/api/v2/projects/'.self::V2_ID.'/');

        $p = app(LocalContextsHubService::class)->getSyncedProject(self::V2_ID);
        $this->assertSame('Reflections on Indigenous sovereignty', $p['title']);
        $cv = $p['labels'][0];
        $this->assertSame('tk', $cv['family']);
        $this->assertSame('community_voice', $cv['label_type']);
        $this->assertSame('Pueblo Kichwa de Sarayaku', $cv['community']);
        $this->assertSame(101, $cv['community_id']);
        $this->assertSame('TK Community Voice (TK CV)', $cv['translations'][0]['translated_name']);
        $this->assertSame('bc', $p['labels'][2]['family']);
        $this->assertSame('biocultural', $p['notices'][0]['notice_type']);
        $this->assertNotSame('', $p['notices'][0]['default_text']);
    }

    public function test_sync_without_key_reads_public_project_from_legacy_v1(): void
    {
        $this->config(['local_contexts_api_key' => '']);
        Http::fake([self::HUB.'/api/v1/projects/*' => Http::response($this->fixture('hub-v1-project-notices.json'))]);

        $r = app(LocalContextsHubService::class)->syncProject(self::NOTICE_ID);

        $this->assertTrue($r['ok'], $r['error'] ?? '');
        $this->assertSame(0, $r['labels']);
        $this->assertSame(2, $r['notices']);
        Http::assertSent(fn (Request $req) => ! $req->hasHeader('X-Api-Key')
            && str_contains($req->url(), '/api/v1/projects/'.self::NOTICE_ID.'/'));
    }

    public function test_label_metadata_prefers_hub_data_for_local_and_hub_codes(): void
    {
        $this->config(['local_contexts_api_key' => '']);
        Http::fake([self::HUB.'/api/v1/projects/*' => Http::response($this->fixture('hub-v1-project-labels.json'))]);
        $hub = app(LocalContextsHubService::class);
        $this->assertTrue($hub->syncProject(self::V2_ID)['ok']);

        $m = $hub->labelMetadata('tk_cv');
        $this->assertSame('hub', $m['source']);
        $this->assertSame('Comunitario', $m['name']);
        $this->assertSame('Spanish', $m['language']);
        $this->assertSame('Pueblo Kichwa de Sarayaku', $m['community']);
        $this->assertStringEndsWith('tk-community-voice.png', $m['image']);
        $this->assertSame('en', $m['translations'][0]['language_tag']);

        // Family is part of the match: research is a BC label in this project,
        // so a TK-prefixed lookup must not pick it up.
        $this->assertSame('bc', $hub->labelMetadata('bc_r')['family']);
        $this->assertSame('tk', $hub->labelMetadata('tk:outreach')['family']);
        $this->assertSame([], $hub->labelMetadata('tk:research'));

        // A code the project does not carry comes from the local catalog.
        $this->assertSame('local', $hub->labelMetadata('tk_wr')['source'] ?? null);
    }

    public function test_hub_unreachable_falls_back_to_local_catalog_without_throwing(): void
    {
        Http::fake(['*' => Http::failedConnection()]);
        $hub = app(LocalContextsHubService::class);

        $r = $hub->syncProject(self::V2_ID);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('fetch failed', $r['error']);
        $this->assertNull($hub->getSyncedProject(self::V2_ID));
        $this->assertSame('local', $hub->labelMetadata('tk_a')['source'] ?? null);
    }

    public function test_rejected_key_degrades_to_failed_sync(): void
    {
        Http::fake(['*' => Http::response(['detail' => 'Authentication not provided.'], 403)]);

        $r = app(LocalContextsHubService::class)->syncProject(self::V2_ID);

        $this->assertFalse($r['ok']);
    }
}
