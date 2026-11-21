<?php

/**
 * PageScreeningGateTest - the page-by-page read feeds the file screening gate
 * (heratio#1584 with heratio#1574). A mixed stores file (delivery note,
 * invoice, health referral) in a collection that requires screening: the one
 * referral page withholds the record's files; a file whose every page was
 * read and found clear has its files cleared; an unread page or a person's
 * earlier withhold is never overruled by a clean run; a reviewer who flags a
 * page by hand withholds the files, audited.
 *
 * The document type goes through the real LlmService to the AI gateway, which
 * is faked with Http::fake (no stray requests); page text is faked.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgAiServices\Services\PageClassifier;
use AhgPrivacy\Services\FileScreeningGate;
use AhgPrivacy\Services\PiiRedactionSuggester;
use AhgProvenanceAi\DTO\InferenceRecord;
use AhgProvenanceAi\Services\InferenceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PageScreeningGateTest extends TestCase
{
    use DatabaseTransactions;

    /** Fictitious throughout; the ID number is the demo sample's test number. */
    private const MIXED = [
        ['page' => 1, 'text' => 'Delivery note 4471. Cable drums, 6 units, delivered to the central stores. Received in good order.', 'ocr' => false],
        ['page' => 2, 'text' => 'Invoice INV-2026-0311 dated 4 March 2026. Cable drums, 6 units. Payment terms 30 days.', 'ocr' => false],
        ['page' => 3, 'text' => 'Health referral. Employee ID number 8703145127289, contact referral@example.org. Refer to the occupational clinic.', 'ocr' => true],
    ];

    private const CLEAN = [
        ['page' => 1, 'text' => 'Delivery note 4471. Cable drums, 6 units, delivered to the central stores. Received in good order.', 'ocr' => false],
        ['page' => 2, 'text' => 'Minutes of the stores committee. Items discussed: stock count and shelving.', 'ocr' => false],
    ];

    /** @var list<array{page:int,text:string,ocr:bool}> */
    public static array $pages = self::MIXED;

    protected function setUp(): void
    {
        parent::setUp();
        \AhgCore\Services\AhgSettingsService::clearCache();
        foreach (['ahg_ai_inference', 'ahg_ai_override', 'ahg_pii_scan_report', 'ahg_llm_config', 'ahg_settings', 'privacy_audit_log'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("{$t} missing.");
            }
        }
        if (! Schema::hasColumn('ahg_pii_scan_report', 'coverage')) {
            $this->markTestSkipped('ahg_pii_scan_report.coverage missing.');
        }
        self::$pages = self::MIXED;
        $this->app->instance(PiiRedactionSuggester::class, new class extends PiiRedactionSuggester {
            public function __construct() {}

            public function pageTexts(int $ioId): ?array
            {
                return PageScreeningGateTest::$pages;
            }
        });
        $this->app->bind(InferenceService::class, fn () => new class extends InferenceService {
            protected function writeRdfStarAnnotation(int $inferenceId, string $uuid, InferenceRecord $r): void {}
        });

        // The default LLM is the gateway, faked: the answer follows the page.
        DB::table('ahg_llm_config')->update(['is_default' => 0]);
        DB::table('ahg_llm_config')->insert(['provider' => 'ollama', 'name' => 'Gateway (test)', 'is_active' => 1, 'is_default' => 1,
            'endpoint_url' => 'https://ai.theahg.co.za/ai/v1', 'model' => 'llama3.1:8b', 'max_tokens' => 20, 'temperature' => 0, 'timeout_seconds' => 10]);
        Http::preventStrayRequests();
        Http::fake(['ai.theahg.co.za/ai/v1/ollama/api/generate' => function (Request $r) {
            $p = (string) $r['prompt'];
            $code = str_contains($p, 'Health') ? 'medical_record' : (str_contains($p, 'Invoice') ? 'invoice' : (str_contains($p, 'Minutes') ? 'minutes' : 'delivery_note'));

            return Http::response(['response' => $code, 'model' => 'llama3.1:8b', 'prompt_eval_count' => 50, 'eval_count' => 2]);
        }]);
    }

    protected function tearDown(): void
    {
        \AhgCore\Services\AhgSettingsService::clearCache();
        parent::tearDown();
    }

    /** A record inside a collection that requires screening. */
    private function screenedRecord(): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        $lft = (int) DB::table('information_object')->max('rgt') + 10;
        DB::table('information_object')->insert(['id' => $id, 'parent_id' => 1, 'lft' => $lft, 'rgt' => $lft + 1, 'source_culture' => 'en']);
        DB::table('ahg_settings')->updateOrInsert(['setting_key' => 'privacy_screening_collections'], ['setting_value' => (string) $id, 'setting_group' => 'privacy']);
        DB::table('ahg_settings')->updateOrInsert(['setting_key' => 'privacy_screening_repositories'], ['setting_value' => '', 'setting_group' => 'privacy']);

        return $id;
    }

    public function test_one_referral_page_withholds_the_files_of_a_mixed_file(): void
    {
        $io = $this->screenedRecord();
        $gate = app(FileScreeningGate::class);
        $this->assertTrue($gate->withholds($io), 'unscreened files wait');

        $r = app(PageClassifier::class)->run($io, null, true);

        $this->assertSame(['3-3'], $r['flagged'], 'only the referral; the invoice date is not a birth date');
        $this->assertFalse($r['cleared']);
        $this->assertSame('files', DB::table('ahg_pii_scan_report')->where('id', $r['report_id'])->value('coverage'));
        $this->assertTrue($gate->withholds($io), 'one flagged page of three withholds the files');

        $roll = PageClassifier::rollup(app(PageClassifier::class)->segments($io));
        $this->assertSame(['delivery_note' => 1, 'invoice' => 1, 'medical_record' => 1], array_map('intval', $roll['spread']));
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $q) => str_starts_with($q->url(), 'https://ai.theahg.co.za/ai/v1/'));
    }

    public function test_every_page_read_and_clear_clears_the_files(): void
    {
        self::$pages = self::CLEAN;
        $io = $this->screenedRecord();

        $r = app(PageClassifier::class)->run($io, null, false);

        $this->assertSame([], $r['flagged']);
        $this->assertTrue($r['cleared']);
        $this->assertSame(0, (int) DB::table('ahg_pii_scan_report')->where('id', $r['report_id'])->value('hits_total'));
        $this->assertFalse(app(FileScreeningGate::class)->withholds($io), 'a full clean read releases the files');
        Http::assertNothingSent();
    }

    public function test_an_unread_page_or_a_persons_withhold_is_not_overruled(): void
    {
        self::$pages = array_merge(self::CLEAN, [['page' => 3, 'text' => '', 'ocr' => true]]);
        $io = $this->screenedRecord();
        $r = app(PageClassifier::class)->run($io, null, false);
        $this->assertFalse($r['cleared'], 'an unread page is not clear');
        $this->assertTrue(app(FileScreeningGate::class)->withholds($io));

        self::$pages = self::CLEAN;
        $io2 = $this->screenedRecord();
        app(FileScreeningGate::class)->decide($io2, false, 'popia', 'Held for a person to check');
        $r2 = app(PageClassifier::class)->run($io2, null, false);
        $this->assertFalse($r2['cleared'], 'a clean run does not overrule a person');
        $this->assertTrue(app(FileScreeningGate::class)->withholds($io2));
    }

    public function test_an_empty_answer_leaves_the_page_unclassified_not_other(): void
    {
        // What the gateway's reasoning model returned with a 20-token budget.
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        Http::fake(['ai.theahg.co.za/ai/v1/ollama/api/generate' => Http::response(['response' => '', 'model' => 'qwen3:8b'])]);
        self::$pages = self::CLEAN;
        $io = $this->screenedRecord();
        $pc = app(PageClassifier::class);
        $pc->run($io, null, true);

        $this->assertSame([], PageClassifier::rollup($pc->segments($io))['spread']);
        Http::assertSent(fn (Request $q) => ($q['options']['num_predict'] ?? 0) >= 200);
    }

    public function test_a_page_flagged_by_a_reviewer_withholds_cleared_files(): void
    {
        self::$pages = self::CLEAN;
        $io = $this->screenedRecord();
        $pc = app(PageClassifier::class);
        $pc->run($io, null, false);
        $this->assertFalse(app(FileScreeningGate::class)->withholds($io));

        $page2 = collect($pc->segments($io))->firstWhere('from', 2);
        $pc->review($page2['privacy']['id'], 'flagged', 1, 'Names a staff member');

        $this->assertTrue(app(FileScreeningGate::class)->withholds($io), 'a person flagged a page');
        $this->assertSame('flagged', PageClassifier::rollup($pc->segments($io))['privacy']);
        $this->assertTrue(DB::table('privacy_audit_log')->where('entity_id', $io)->where('action', 'files_withheld')
            ->where('notes', 'like', 'Pages 2-2 flagged by a reviewer.%')->exists());
    }
}
