<?php

/**
 * PageClassifierTest - a mixed file read page by page (heratio#1584): one
 * page with personal information flags the whole record (the roll-up is the
 * maximum, not the mean) and raises its PII scan report; every page result
 * is a provenance row with model and input digest; a review is an override
 * event and changes the effective value. Page text and the LLM are faked;
 * the Fuseki write is skipped.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgAiServices\Services\LlmService;
use AhgAiServices\Services\PageClassifier;
use AhgPrivacy\Services\PiiRedactionSuggester;
use AhgProvenanceAi\DTO\InferenceRecord;
use AhgProvenanceAi\Services\InferenceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PageClassifierTest extends TestCase
{
    use DatabaseTransactions;

    private const PAGES = [
        ['page' => 1, 'text' => 'Dear Sir, we confirm the delivery of the cable drums as agreed. Yours faithfully, the Secretary', 'ocr' => false],
        ['page' => 2, 'text' => 'Minutes of the meeting held on 3 March. Present: the committee. Items discussed: budget and stores.', 'ocr' => false],
        ['page' => 3, 'text' => 'Medical referral. Patient contact: patient@example.org, cell 082 555 0199. Refer to the clinic.', 'ocr' => true],
        ['page' => 4, 'text' => '', 'ocr' => true],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // Settings are cached in a static, outside the test transaction.
        \AhgCore\Services\AhgSettingsService::clearCache();
        if (! Schema::hasTable('ahg_ai_inference') || ! Schema::hasTable('ahg_ai_override') || ! Schema::hasTable('ahg_pii_scan_report')) {
            $this->markTestSkipped('ahg_ai_inference / ahg_ai_override / ahg_pii_scan_report missing.');
        }
        $this->app->instance(PiiRedactionSuggester::class, new class extends PiiRedactionSuggester {
            public function __construct() {}

            public function pageTexts(int $ioId): ?array
            {
                return PageClassifierTest::pages();
            }
        });
        $this->app->instance(LlmService::class, new class extends LlmService {
            public function __construct() {}

            public function completeFull(string $systemPrompt, string $userPrompt, ?int $configId = null, array $options = []): array
            {
                $code = str_contains($userPrompt, 'Medical') ? 'medical_record' : (str_contains($userPrompt, 'Minutes') ? 'minutes' : 'letter');

                return ['success' => true, 'text' => $code, 'model' => 'fake-model'];
            }
        });
        $this->app->bind(InferenceService::class, fn () => new class extends InferenceService {
            protected function writeRdfStarAnnotation(int $inferenceId, string $uuid, InferenceRecord $r): void {}
        });
    }

    protected function tearDown(): void
    {
        \AhgCore\Services\AhgSettingsService::clearCache();
        parent::tearDown();
    }

    /** CI's database has no administrator, so make one (as BulkEditTest does). */
    private function makeAdmin(): \AhgCore\Models\User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'pages-admin-'.$id, 'email' => uniqid('pages-', true).'@example.test',
            'password_hash' => \Illuminate\Support\Facades\Hash::make('secret'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        \Illuminate\Support\Facades\Cache::forget("acl_groups_{$id}");

        return \AhgCore\Models\User::findOrFail($id);
    }

    public static function pages(): array
    {
        return self::PAGES;
    }

    private function record(): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_one_flagged_page_flags_the_record_and_each_page_has_provenance(): void
    {
        $io = $this->record();
        $pc = app(PageClassifier::class);
        $r = $pc->run($io, null, true);

        $this->assertSame(4, $r['units']);
        $this->assertSame(['3-3'], $r['flagged']);
        $this->assertNotNull($r['report_id'], 'the record-level PII report is raised');
        $this->assertSame('pending', DB::table('ahg_pii_scan_report')->where('id', $r['report_id'])->value('status'));
        $this->assertSame($io, (int) DB::table('ahg_pii_scan_report')->where('id', $r['report_id'])->value('information_object_id'));

        $seg = $pc->segments($io);
        $roll = PageClassifier::rollup($seg);
        $this->assertSame('flagged', $roll['privacy'], 'one page of four is enough');
        $this->assertSame(['3'], $roll['flagged']);
        $this->assertSame(['4'], $roll['unread'], 'a page without text is not called clear');
        $this->assertSame(['letter' => 1, 'minutes' => 1, 'medical_record' => 1], array_map('intval', $roll['spread']) + []);

        // Provenance on every page result: model, input digest, and no page text.
        $rows = DB::table('ahg_ai_inference')->where('target_entity_id', $io)->where('target_field', 'like', 'page:%')->get();
        $this->assertCount(7, $rows, '4 privacy + 3 document types (page 4 has no text)');
        foreach ($rows as $row) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $row->input_hash);
            $this->assertNotSame('', (string) $row->model_name);
            $this->assertStringNotContainsString('patient@example.org', (string) $row->input_excerpt.$row->output_excerpt);
        }
    }

    public function test_a_review_is_an_override_event_and_changes_the_roll_up(): void
    {
        $io = $this->record();
        $pc = app(PageClassifier::class);
        $pc->run($io, null, true);
        $page3 = collect($pc->segments($io))->firstWhere('from', 3);

        $pc->review($page3['privacy']['id'], 'clear', 1, 'Fictitious test contact');
        $pc->review($page3['doctype']['id'], 'letter', 1);

        $this->assertSame(2, DB::table('ahg_ai_override')->whereIn('inference_id', [$page3['privacy']['id'], $page3['doctype']['id']])->count());
        $after = collect($pc->segments($io))->firstWhere('from', 3);
        $this->assertSame('clear', $after['privacy']['label']);
        $this->assertSame('flagged', $after['privacy']['original'], 'the AI result is kept');
        $this->assertTrue($after['privacy']['overridden']);
        $this->assertSame('incomplete', PageClassifier::rollup($pc->segments($io))['privacy'], 'still page 4 unread');

        $this->expectException(\InvalidArgumentException::class);
        $pc->review($page3['doctype']['id'], 'not-a-type', 1);
    }

    public function test_the_page_shows_the_strip_and_the_roll_up_to_an_administrator(): void
    {
        $io = $this->record();
        $lft = (int) DB::table('information_object')->max('rgt') + 10;
        DB::table('information_object')->insert(['id' => $io, 'parent_id' => 1, 'lft' => $lft, 'rgt' => $lft + 1, 'source_culture' => 'en']);
        DB::table('slug')->insert(['object_id' => $io, 'slug' => 'pages-'.$io]);
        DB::table('information_object_i18n')->insert(['id' => $io, 'culture' => 'en', 'title' => 'Zz mixed file']);
        app(PageClassifier::class)->run($io, null, true);
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('admin.ai.pages', 'pages-'.$io))->assertOk()
            ->assertSee('Page strip')->assertSee('the record is flagged for privacy review')
            ->assertSee('Medical record')->assertDontSee('patient@example.org');
        \Illuminate\Support\Facades\Queue::fake();
        $this->actingAs($admin)->post(route('admin.ai.pages.run', 'pages-'.$io))->assertRedirect()->assertSessionHas('success');
        \Illuminate\Support\Facades\Queue::assertPushed(\AhgAiServices\Jobs\ClassifyPagesJob::class, fn ($j) => $j->ioId === $io);
    }

    public function test_the_settings_are_on_the_ai_configuration_page_and_validated(): void
    {
        if (! Schema::hasTable('ahg_settings')) {
            $this->markTestSkipped('ahg_settings missing.');
        }
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->get(route('admin.ai.config'))->assertOk()->assertSee('Per-page classification');

        $this->actingAs($admin)->post(route('admin.ai.config'), ['_action' => 'save_settings',
            'pages' => ['window_words' => '3000', 'on_ingest' => '1', 'collections' => ' 91126 , 916233 ']])->assertRedirect()->assertSessionHas('success');
        $get = fn ($k) => DB::table('ahg_settings')->where('setting_key', $k)->value('setting_value');
        $this->assertSame('3000', $get('ai_page_window_words'));
        $this->assertSame('1', $get('ai_page_classify_on_ingest'));
        $this->assertSame('91126,916233', $get('ai_page_classify_collections'));

        $this->actingAs($admin)->post(route('admin.ai.config'), ['_action' => 'save_settings',
            'pages' => ['window_words' => '3000', 'on_ingest' => '1', 'collections' => 'Dakawa']])->assertSessionHasErrors('pages.collections');
        $this->assertSame('91126,916233', $get('ai_page_classify_collections'), 'a bad value is not saved');
    }

    public function test_windows_span_pages_and_an_empty_page_stays_its_own_unit(): void
    {
        $u = PageClassifier::units(self::PAGES, 30);
        $this->assertSame([1, 2], [$u[0]['from'], $u[0]['to']], 'a 30-word window runs from page 1 into page 2');
        $this->assertSame('', end($u)['text']);
        $this->assertSame(4, end($u)['from']);
        $this->assertCount(4, PageClassifier::units(self::PAGES, 0), 'window 0 = one unit per page');
    }

    public function test_daytime_only_for_the_gpu_step(): void
    {
        $this->assertTrue(PageClassifier::gpuHours(new \DateTimeImmutable('2026-11-10 08:00:00', new \DateTimeZone('UTC'))), '10:00 SAST');
        $this->assertFalse(PageClassifier::gpuHours(new \DateTimeImmutable('2026-11-10 17:30:00', new \DateTimeZone('UTC'))), '19:30 SAST');
    }
}
