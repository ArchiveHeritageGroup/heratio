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
            'local_contexts_project_ids' => self::V2_ID.','.self::NOTICE_ID,
        ]);
        DB::table('icip_config')->where('config_key', 'local_contexts_synced_hub_url')->delete();
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

    private function syncedV2(): LocalContextsHubService
    {
        Http::fake([self::HUB.'/api/v2/projects/*' => Http::sequence()->push($this->fixture('hub-v2-project.json'))]);
        $hub = app(LocalContextsHubService::class);
        $this->assertTrue($hub->syncProject(self::V2_ID)['ok']);

        return $hub;
    }

    private function refake(mixed $body, int $status = 200): void
    {
        // A fresh factory, so earlier stubs do not answer this request.
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*' => Http::response($body, $status)]);
    }

    public function test_project_gone_from_hub_stops_being_served_and_returns_on_resync(): void
    {
        $hub = $this->syncedV2();
        $this->assertSame('hub', $hub->labelMetadata('tk_cv')['source'] ?? null);

        $this->refake(['detail' => 'Not found.'], 404);
        $this->assertFalse($hub->syncProject(self::V2_ID)['ok']);

        $this->assertNull($hub->getSyncedProject(self::V2_ID));
        $this->assertSame('local', $hub->labelMetadata('tk_cv')['source'] ?? null);
        $this->assertSame([], $hub->query('Sarayaku'));

        $this->refake($this->fixture('hub-v2-project.json'));
        $this->assertTrue($hub->syncProject(self::V2_ID)['ok']);
        $this->assertSame('hub', $hub->labelMetadata('tk_cv')['source'] ?? null);
    }

    public function test_project_turned_private_is_treated_as_withdrawn(): void
    {
        $hub = $this->syncedV2();

        $this->refake(['project_privacy' => 'Private'] + $this->fixture('hub-v2-project.json'));

        $this->assertFalse($hub->syncProject(self::V2_ID)['ok']);
        $this->assertNull($hub->getSyncedProject(self::V2_ID));
    }

    public function test_temporary_failure_keeps_last_good_copy(): void
    {
        $hub = $this->syncedV2();

        $this->refake(['detail' => 'Forbidden'], 403);
        $this->assertFalse($hub->syncProject(self::V2_ID)['ok']);

        $this->assertSame(403, (int) DB::table('icip_hub_project')->where('project_id', self::V2_ID)->value('http_status'));
        $this->assertNotNull($hub->getSyncedProject(self::V2_ID));
    }

    public function test_error_body_with_http_200_does_not_wipe_synced_data(): void
    {
        $hub = $this->syncedV2();

        foreach ([['detail' => 'Maintenance'], ['unique_id' => self::NOTICE_ID, 'tk_labels' => []]] as $bad) {
            $this->refake($bad);
            $this->assertFalse($hub->syncProject(self::V2_ID)['ok']);
        }

        $this->assertCount(3, $hub->getSyncedProject(self::V2_ID)['labels']);
    }

    public function test_resync_keeps_created_at(): void
    {
        $hub = $this->syncedV2();
        DB::table('icip_hub_project')->where('project_id', self::V2_ID)->update(['created_at' => '2026-01-01 00:00:00']);

        $this->refake($this->fixture('hub-v2-project.json'));
        $this->assertTrue($hub->syncProject(self::V2_ID)['ok']);

        $this->assertSame('2026-01-01 00:00:00', (string) DB::table('icip_hub_project')->where('project_id', self::V2_ID)->value('created_at'));
    }

    public function test_key_is_never_sent_over_http_and_redirects_are_not_followed(): void
    {
        $this->config(['local_contexts_hub_url' => 'http://hub.example.test']);
        Http::fake();
        $this->assertFalse(app(LocalContextsHubService::class)->syncProject(self::V2_ID)['ok']);
        Http::assertNothingSent();

        $this->config(['local_contexts_hub_url' => self::HUB]);
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake([
            'hub.example.test/*' => Http::response('', 302, ['Location' => 'https://elsewhere.example.test/stolen']),
            'elsewhere.example.test/*' => Http::response($this->fixture('hub-v2-project.json')),
        ]);
        $this->assertFalse(app(LocalContextsHubService::class)->syncProject(self::V2_ID)['ok']);
        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $req) => str_contains($req->url(), 'elsewhere.example.test'));
    }

    public function test_withdrawn_project_stays_hidden_through_later_failures(): void
    {
        $hub = $this->syncedV2();
        $this->refake(['detail' => 'Not found.'], 404);
        $hub->syncProject(self::V2_ID);

        foreach ([503, 403, 429, 302] as $status) {
            $this->refake('', $status);
            $this->assertFalse($hub->syncProject(self::V2_ID)['ok']);
            $this->assertNull($hub->getSyncedProject(self::V2_ID), "served again after $status");
            $this->assertSame('local', $hub->labelMetadata('tk_cv')['source'] ?? null);
        }
        $this->assertSame(404, (int) DB::table('icip_hub_project')->where('project_id', self::V2_ID)->value('http_status'));

        $this->refake($this->fixture('hub-v2-project.json'));
        $this->assertTrue($hub->syncProject(self::V2_ID)['ok']);
        $this->assertNotNull($hub->getSyncedProject(self::V2_ID));
    }

    public function test_project_removed_from_configuration_is_no_longer_served(): void
    {
        $hub = $this->syncedV2();

        $this->config(['local_contexts_project_ids' => '']);

        $this->assertSame([], $hub->syncAll());
        $this->assertNull($hub->getSyncedProject(self::V2_ID));
        $this->assertSame('local', $hub->labelMetadata('tk_cv')['source'] ?? null);
        $this->assertSame([], $hub->query('Sarayaku'));
    }

    public function test_rows_from_another_hub_are_not_served_and_are_cleared_on_first_sync(): void
    {
        $hub = $this->syncedV2();

        $this->config(['local_contexts_hub_url' => 'https://live.example.test']);
        $this->assertNull($hub->getSyncedProject(self::V2_ID));
        $this->assertSame('local', $hub->labelMetadata('tk_cv')['source'] ?? null);

        // A failure at the new Hub neither clears nor marks the old rows.
        $this->refake(['detail' => 'Not found.'], 404);
        $hub->syncProject(self::V2_ID);
        $this->assertSame(200, (int) DB::table('icip_hub_project')->where('project_id', self::V2_ID)->value('http_status'));

        $this->refake($this->fixture('hub-v1-project-notices.json'));
        $this->assertTrue($hub->syncProject(self::NOTICE_ID)['ok']);
        $this->assertSame(0, DB::table('icip_hub_project')->where('project_id', self::V2_ID)->count());
    }

    public function test_malformed_fields_do_not_throw_or_abort_the_run(): void
    {
        $badUrl = $this->fixture('hub-v2-project.json');
        $badUrl['tk_labels'][0]['img_url'] = ['nested' => 'object'];
        Http::fake(['*' => Http::response($badUrl)]);
        $hub = app(LocalContextsHubService::class);
        $this->assertTrue($hub->syncProject(self::V2_ID)['ok']);
        $this->assertSame('', $hub->labelMetadata('tk_cv', self::V2_ID)['image']);

        $badCommunity = $this->fixture('hub-v2-project.json');
        $badCommunity['tk_labels'][0]['community'] = ['name' => ['not' => 'text']];
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake([
            '*/'.self::V2_ID.'/' => Http::response($badCommunity),
            '*/'.self::NOTICE_ID.'/' => Http::response(['unique_id' => self::NOTICE_ID, 'project_privacy' => 'Public', 'notice' => []]),
        ]);

        $results = $hub->syncAll();

        $this->assertCount(2, $results);
        $this->assertFalse($results[0]['ok']);
        $this->assertTrue($results[1]['ok'], $results[1]['error'] ?? '');
    }

    /** A record with one label linked to the v2 project. Returns the record id. */
    private function labelledRecord(): int
    {
        // icip_tk_label has no foreign key to information_object, and the
        // display queries need only the label row.
        $ioId = 999999001;
        DB::table('icip_tk_label')->insert([
            'information_object_id' => $ioId,
            'label_type_id' => DB::table('icip_tk_label_type')->where('code', 'tk_cv')->value('id'),
            'applied_by' => 'institution',
            'local_contexts_project_id' => self::V2_ID,
        ]);

        return $ioId;
    }

    private function catalogName(): string
    {
        return (string) DB::table('icip_tk_label_type')->where('code', 'tk_cv')->value('name');
    }

    public function test_record_page_shows_hub_wording_for_a_public_project(): void
    {
        $ioId = $this->labelledRecord();
        $source = $this->fixture('hub-v2-project.json')['tk_labels'][0];
        $this->syncedV2();
        app()->setLocale((string) $source['language_tag']);

        $html = view('icip::partials.record-notices', ['ioId' => $ioId])->render();

        $this->assertStringContainsString(e($source['name']), $html);
        $this->assertStringContainsString('lang="'.$source['language_tag'].'"', $html);
    }

    public function test_record_page_uses_a_hub_translation_for_the_viewer_language(): void
    {
        $ioId = $this->labelledRecord();
        $p = $this->fixture('hub-v2-project.json');
        $p['tk_labels'][0]['language_tag'] = 'es';
        $p['tk_labels'][0]['translations'] = [['language_tag' => 'fr-CA', 'language' => 'French', 'translated_name' => 'Voix', 'translated_text' => 'Texte']];
        Http::fake(['*' => Http::response($p)]);
        $this->assertTrue(app(LocalContextsHubService::class)->syncProject(self::V2_ID)['ok']);
        app()->setLocale('fr');

        $label = app(\AhgIcip\Services\IcipAccessService::class)->displayLabels($ioId)->first();

        $this->assertSame('Voix', $label->name);
        $this->assertSame('Texte', $label->description);
        $this->assertSame('fr-CA', $label->language_tag);
    }

    public function test_record_page_withholds_hub_text_unless_the_project_is_public(): void
    {
        $ioId = $this->labelledRecord();
        $p = ['project_privacy' => 'Contributor'] + $this->fixture('hub-v2-project.json');
        Http::fake(['*' => Http::response($p)]);
        $this->assertTrue(app(LocalContextsHubService::class)->syncProject(self::V2_ID)['ok']);

        $label = app(\AhgIcip\Services\IcipAccessService::class)->displayLabels($ioId)->first();

        $this->assertSame($this->catalogName(), $label->name);
        $this->assertObjectNotHasProperty('language_tag', $label);
    }

    public function test_record_page_returns_to_catalog_text_when_project_is_withdrawn(): void
    {
        $ioId = $this->labelledRecord();
        $hub = $this->syncedV2();
        $this->refake(['detail' => 'Not found.'], 404);
        $hub->syncProject(self::V2_ID);
        $this->refake('', 503);
        $hub->syncProject(self::V2_ID);

        $label = app(\AhgIcip\Services\IcipAccessService::class)->displayLabels($ioId)->first();

        $this->assertSame($this->catalogName(), $label->name);
    }

    public function test_invalid_project_id_is_rejected_without_a_call(): void
    {
        Http::fake();

        $r = app(LocalContextsHubService::class)->syncProject('../../accounts');

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('invalid project id', $r['error']);
        Http::assertNothingSent();
    }

    public function test_server_error_is_retried_once_but_rejected_key_is_not(): void
    {
        Http::fake(['*' => Http::response('', 503)]);
        app(LocalContextsHubService::class)->syncProject(self::V2_ID);
        Http::assertSentCount(2);

        $this->refake(['detail' => 'Forbidden'], 403);
        app(LocalContextsHubService::class)->syncProject(self::V2_ID);
        Http::assertSentCount(1);
    }

    public function test_payload_variants_and_unsafe_urls_are_normalised(): void
    {
        $p = $this->fixture('hub-v2-project.json');
        $p['notice'] = $p['notice'][0];
        $p['tk_labels'][0]['translations'] = json_encode([['translated_name' => 'Name', 'language_tag' => 'en']]);
        $p['tk_labels'][0]['label_page'] = 'javascript:alert(1)';
        Http::fake(['*' => Http::response($p)]);
        $hub = app(LocalContextsHubService::class);

        $r = $hub->syncProject(self::V2_ID);

        $this->assertSame(1, $r['notices']);
        $m = $hub->labelMetadata('tk_cv', self::V2_ID);
        $this->assertSame('Name', $m['translations'][0]['translated_name']);
        $this->assertSame('', $m['url']);
        $this->assertSame('Public', $m['project_privacy']);
    }

    public function test_label_metadata_can_be_scoped_to_one_project(): void
    {
        $hub = $this->syncedV2();

        $this->assertSame('hub', $hub->labelMetadata('tk_cv', self::V2_ID)['source']);
        $this->assertSame('local', $hub->labelMetadata('tk_cv', self::NOTICE_ID)['source']);
    }

    public function test_settings_form_validates_and_stores_hub_fields(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\RequireAdmin::class);
        $url = '/admin/ahgSettings/icipSettings';

        $this->post($url, ['settings' => ['local_contexts_hub_url' => 'https://evil.example.test']])
            ->assertSessionHasErrors('settings.local_contexts_hub_url');
        $this->post($url, ['settings' => ['local_contexts_project_ids' => 'not-a-uuid']])
            ->assertSessionHasErrors('settings.local_contexts_project_ids');
        $this->flushSession();

        $this->post($url, ['settings' => [
            'local_contexts_hub_url' => 'https://sandbox.localcontextshub.org',
            'local_contexts_project_ids' => self::V2_ID.', '.self::NOTICE_ID,
        ]])->assertSessionHasNoErrors()->assertRedirect();

        $hub = app(LocalContextsHubService::class);
        $this->assertSame('https://sandbox.localcontextshub.org', $hub->hubBaseUrl());
        $this->assertSame([self::V2_ID, self::NOTICE_ID], $hub->configuredProjectIds());

        $this->get($url)->assertOk()
            ->assertSee('settings[local_contexts_project_ids]', false)
            ->assertSee(self::NOTICE_ID);
    }

    public function test_settings_form_refuses_a_guest(): void
    {
        $this->post('/admin/ahgSettings/icipSettings', ['settings' => ['local_contexts_project_ids' => self::V2_ID]])
            ->assertStatus(302);

        $this->assertNotSame(self::V2_ID, DB::table('icip_config')->where('config_key', 'local_contexts_project_ids')->value('config_value'));
    }
}
