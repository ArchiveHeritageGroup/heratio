<?php

/**
 * CrossReferenceGapsTest - the stricter rules of heratio#1524: whole-word
 * grounding of the printed form, one-character OCR merges, duplicate leaves
 * counted by item, non-English text, the authority queue, and an offline
 * explorer package that carries only files released to the public.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgAiServices\Services\CrossReferenceService;
use AhgApi\Services\ExplorerFilePackager;
use AhgCore\Services\DisclosureGate;
use AhgCore\Support\StatusRow;
use AhgInformationObjectManage\Services\InformationObjectService as Io;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CrossReferenceGapsTest extends TestCase
{
    use DatabaseTransactions;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/xref-'.uniqid();
        mkdir($this->tmp.'/uploads/gate', 0775, true);
        mkdir($this->tmp.'/out', 0775, true);
        config(['heratio.uploads_path' => $this->tmp.'/uploads']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);
        parent::tearDown();
    }

    public function test_grounding_needs_whole_words_and_keeps_the_form_the_item_prints(): void
    {
        $text = "The Annual report from LADYSMITH names General\nBuller.";
        $svc = new CrossReferenceService(fn () => ['people' => ['Ann', 'General Buller', ['bad']], 'places' => ['Ladysmith'], 'units' => [], 'newspapers' => []]);

        $values = array_column($svc->entities(1, $text, false), 'value');

        $this->assertEqualsCanonicalizing(['General Buller', 'LADYSMITH'], $values);
    }

    public function test_only_a_one_character_slip_merges_spellings(): void
    {
        $groups = (new CrossReferenceService)->group([
            [1, 'place', 'Bloemfontein'], [2, 'place', 'Bloemfontien'], [3, 'place', 'Bloemfonteim'],
        ]);
        $names = collect($groups)->pluck('variants')->flatten()->all();

        $this->assertCount(3, $names);
        $merged = collect($groups)->firstWhere('name', 'Bloemfontein');
        $this->assertContains('Bloemfonteim', $merged['variants'], 'one substituted letter is an OCR slip');
        $this->assertNotContains('Bloemfontien', $merged['variants'], 'two changed letters are not');
    }

    public function test_a_leaf_needs_three_distinct_items_not_three_pairs(): void
    {
        $vol = Io::create(['title' => 'ZZ Vol'], 'en');
        $p1 = Io::create(['title' => 'ZZ P1', 'parent_id' => $vol], 'en');
        $p2 = Io::create(['title' => 'ZZ P2', 'parent_id' => $vol], 'en');
        $one = Io::create(['title' => 'a', 'parent_id' => $p1], 'en');
        $b = [];
        foreach ([1, 2, 3] as $i) {
            $b[] = Io::create(['title' => "b{$i}", 'parent_id' => $p2], 'en');
        }
        $pairs = array_map(fn ($x) => ['a' => $one, 'b' => $x, 'a_share' => 1.0, 'b_share' => 1.0], $b);

        $this->assertSame([], (new CrossReferenceService)->duplicateLeaves($pairs));
    }

    public function test_a_leaf_counts_one_to_one_matches_not_items_with_any_match(): void
    {
        $vol = Io::create(['title' => 'ZZ Vol'], 'en');
        $p1 = Io::create(['title' => 'ZZ P1', 'parent_id' => $vol], 'en');
        $p2 = Io::create(['title' => 'ZZ P2', 'parent_id' => $vol], 'en');
        $a = $b = [];
        foreach ([1, 2, 3] as $i) {
            $a[$i] = Io::create(['title' => "a{$i}", 'parent_id' => $p1], 'en');
            $b[$i] = Io::create(['title' => "b{$i}", 'parent_id' => $p2], 'en');
        }
        $pair = fn ($x, $y) => ['a' => $x, 'b' => $y, 'a_share' => 1.0, 'b_share' => 1.0];
        $svc = new CrossReferenceService;

        $this->assertSame([], $svc->duplicateLeaves([$pair($a[1], $b[1]), $pair($a[2], $b[1]), $pair($a[3], $b[2]), $pair($a[3], $b[3])]));

        // A greedy pairing of a1-b1 would need to move aside for a2 to reach three.
        $leaves = $svc->duplicateLeaves([$pair($a[1], $b[1]), $pair($a[1], $b[2]), $pair($a[2], $b[1]), $pair($a[3], $b[3])]);
        $this->assertSame(3, $leaves[0]['shared_items'] ?? null);
    }

    public function test_the_queue_keeps_the_context_of_the_grounded_match(): void
    {
        $text = "Caf\u{e9} notes: The Annual report names Ann Smith and General\nBuller here.";
        $io = Io::create(['title' => 'ZZ Context', 'scope_and_content' => $text], 'en');
        $svc = new CrossReferenceService(fn () => ['people' => ['Ann', 'General Buller'], 'places' => [], 'units' => [], 'newspapers' => []]);

        $svc->promoteToAuthorityQueue($io, $text, $svc->entities($io, $text));

        $context = fn (string $value) => DB::table('ahg_mention_context as c')->join('ahg_mention as m', 'm.id', '=', 'c.mention_id')
            ->join('ahg_ner_entity as e', 'e.id', '=', 'm.ner_entity_id')->where('e.object_id', $io)->where('e.entity_value', $value)->first();
        $ann = $context('Ann');
        $this->assertStringStartsWith(' Smith', $ann->surrounding_text_after);
        $this->assertStringEndsWith('names ', $ann->surrounding_text_before);
        $shown = app(\AhgAuthorityResolution\Services\PromoteToMentionService::class)->fetchSourceText($io);
        $this->assertSame('Ann', substr($shown, $ann->character_offset_start, $ann->character_offset_end - $ann->character_offset_start), 'offsets fit the text the review screen highlights');
        $buller = $context('General Buller');
        $this->assertSame("General\nBuller", substr($shown, $buller->character_offset_start, $buller->character_offset_end - $buller->character_offset_start));
        $this->assertStringStartsWith(' here', $buller->surrounding_text_after);
    }

    public function test_a_name_only_in_the_ocr_text_keeps_its_context(): void
    {
        $ocr = 'Telegram from Colenso about General Buller today.';
        $io = Io::create(['title' => 'ZZ Scan'], 'en');
        $svc = new CrossReferenceService(fn () => ['people' => ['General Buller'], 'places' => [], 'units' => [], 'newspapers' => []]);

        $svc->promoteToAuthorityQueue($io, $ocr, $svc->entities($io, $ocr));

        $row = DB::table('ahg_mention_context as c')->join('ahg_mention as m', 'm.id', '=', 'c.mention_id')->where('m.object_id', $io)->first();
        $this->assertStringEndsWith('about ', $row->surrounding_text_before);
        $this->assertStringStartsWith(' today', $row->surrounding_text_after);
    }

    public function test_a_record_restricted_since_the_last_export_leaves_the_folder(): void
    {
        $id = $this->record(160, 'later');
        $pdf = 'later-'.$id.'.pdf';
        file_put_contents($this->tmp.'/uploads/gate/'.$pdf, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n");
        $pdfId = (int) DB::table('object')->insertGetId(['class_name' => 'QubitDigitalObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('digital_object')->insert(['id' => $pdfId, 'object_id' => $id, 'usage_id' => 140, 'name' => $pdf, 'path' => '/uploads/r/gate/', 'mime_type' => 'application/pdf']);
        $out = $this->tmp.'/out';
        $records = [['id' => (string) $id]];
        app(ExplorerFilePackager::class)->package($records, $out);
        $this->assertFileExists("$out/img/{$id}.png");
        $this->assertFileExists("$out/files/{$id}.pdf");

        StatusRow::set($id, 158, 159);
        $records = [['id' => (string) $id]];
        app(ExplorerFilePackager::class)->package($records, $out);

        $this->assertFileDoesNotExist("$out/img/{$id}.png");
        $this->assertFileDoesNotExist("$out/files/{$id}.pdf");
        $this->assertArrayNotHasKey('img', $records[0]);
    }

    public function test_a_re_export_without_files_removes_the_earlier_files(): void
    {
        $id = $this->record(160, 'nofiles');
        $out = $this->tmp.'/out';
        $records = [['id' => (string) $id]];
        app(ExplorerFilePackager::class)->package($records, $out);

        app(ExplorerFilePackager::class)->clear($out);

        $this->assertDirectoryDoesNotExist("$out/img");
        $this->assertDirectoryDoesNotExist("$out/files");
    }

    public function test_an_export_never_empties_a_folder_it_did_not_write(): void
    {
        $id = $this->record(160, 'foreign');
        $out = $this->tmp.'/out';
        mkdir("$out/img");
        file_put_contents("$out/img/holiday.jpg", 'keep me');
        $records = [['id' => (string) $id]];

        try {
            app(ExplorerFilePackager::class)->package($records, $out);
            $this->fail('an unmarked folder holding files must be refused');
        } catch (\RuntimeException) {
            $this->assertFileExists("$out/img/holiday.jpg");
        }
        app(ExplorerFilePackager::class)->clear($out);
        $this->assertFileExists("$out/img/holiday.jpg");
    }

    public function test_a_linked_img_folder_is_not_followed_when_clearing(): void
    {
        $id = $this->record(160, 'linked');
        $out = $this->tmp.'/out';
        $records = [['id' => (string) $id]];
        app(ExplorerFilePackager::class)->package($records, $out);
        File::deleteDirectory("$out/img");
        mkdir($this->tmp.'/elsewhere');
        file_put_contents($this->tmp.'/elsewhere/keep.png', 'keep me');
        symlink($this->tmp.'/elsewhere', "$out/img");

        try {
            app(ExplorerFilePackager::class)->clear($out);
            $this->fail('a linked folder must be refused');
        } catch (\RuntimeException) {
            $this->assertFileExists($this->tmp.'/elsewhere/keep.png');
        }
    }

    public function test_packaging_records_no_download_but_the_gate_still_does(): void
    {
        $id = $this->record(160, 'count');
        $pdf = 'count-'.$id.'.pdf';
        file_put_contents($this->tmp.'/uploads/gate/'.$pdf, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n");
        $pdfId = (int) DB::table('object')->insertGetId(['class_name' => 'QubitDigitalObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('digital_object')->insert(['id' => $pdfId, 'object_id' => $id, 'usage_id' => 140, 'name' => $pdf, 'path' => '/uploads/r/gate/', 'mime_type' => 'application/pdf']);
        $records = [['id' => (string) $id]];

        $stats = app(ExplorerFilePackager::class)->package($records, $this->tmp.'/out');

        $this->assertSame(1, $stats['pdfs']);
        $this->assertSame(0, DB::table('ahg_usage_event')->where('object_id', $id)->count());

        $web = '/uploads/r/gate/'.$pdf;
        $request = \Illuminate\Http\Request::create('/privacy/file-check');
        $request->headers->set('X-Original-URI', $web);
        $request->headers->set('X-Original-Path', $web);
        $response = app(\AhgInformationObjectManage\Controllers\PrivacyController::class)->fileCheck($request);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame(1, DB::table('ahg_usage_event')->where('object_id', $id)->where('event_type', 'download')->count());
    }

    public function test_a_collection_not_in_english_is_read_in_its_own_language(): void
    {
        $vol = Io::create(['title' => 'ZZ Bundel'], 'af');
        $item = Io::create(['title' => 'ZZ Knipsel', 'scope_and_content' => 'Die trein het vroeg vanoggend by die stasie aangekom', 'parent_id' => $vol], 'af');

        $this->assertStringContainsString('Die trein', (new CrossReferenceService)->items($vol)[$item] ?? '');
    }

    public function test_grounded_names_reach_the_authority_queue_as_pending_only(): void
    {
        $text = 'General Buller reached Colenso.';
        $io = Io::create(['title' => 'ZZ Item', 'scope_and_content' => $text], 'en');
        $svc = new CrossReferenceService(fn () => ['people' => ['General Buller'], 'places' => ['Colenso'], 'units' => [], 'newspapers' => []]);
        $kept = $svc->entities($io, $text);

        $this->assertSame(2, $svc->promoteToAuthorityQueue($io, $text, $kept));
        $this->assertSame(0, $svc->promoteToAuthorityQueue($io, $text, $kept), 'a re-run queues nothing twice');
        $this->assertSame(['pending'], DB::table('ahg_mention')->where('object_id', $io)->distinct()->pluck('state')->all());
        $this->assertSame(0, DB::table('ahg_ner_entity')->where('object_id', $io)->whereNotNull('linked_actor_id')->count());
    }

    public function test_the_package_carries_released_files_only_by_relative_path(): void
    {
        $open = $this->record(160, 'open');
        $draft = $this->record(159, 'draft');
        $records = [['id' => (string) $open, 'img' => 'https://site/x.jpg'], ['id' => (string) $draft, 'img' => 'https://site/y.jpg']];

        $stats = app(ExplorerFilePackager::class)->package($records, $this->tmp.'/out');

        $this->assertSame("img/{$open}.png", $records[0]['img']);
        $this->assertFileExists($this->tmp."/out/img/{$open}.png");
        $this->assertArrayNotHasKey('img', $records[1], 'a refused file leaves no link behind');
        $this->assertFileDoesNotExist($this->tmp."/out/img/{$draft}.png");
        $this->assertSame(1, $stats['images']);
        $this->assertSame(1, $stats['skipped']);
    }

    public function test_a_file_outside_the_uploads_folder_is_never_copied(): void
    {
        file_put_contents($this->tmp.'/secret.png', base64_decode(self::PNG));
        $id = $this->record(160, 'open', '/uploads/r/../../secret.png');
        $records = [['id' => (string) $id]];

        app(ExplorerFilePackager::class)->package($records, $this->tmp.'/out');

        $this->assertArrayNotHasKey('img', $records[0]);
        $this->assertSame([], glob($this->tmp.'/out/img/*'));
    }

    public function test_the_export_leaves_out_restricted_records_and_works_from_relative_paths(): void
    {
        $vol = Io::create(['title' => 'ZZ Export volume'], 'en');
        StatusRow::set($vol, 158, 160);
        $open = $this->record(160, 'open');
        $secret = $this->record(160, 'embargoed');
        DB::table('information_object')->whereIn('id', [$open, $secret])->update(['parent_id' => $vol]);
        $bounds = DB::table('information_object')->where('id', $vol)->first(['lft', 'rgt']);
        DB::table('information_object')->where('id', $vol)->update(['lft' => 900000001, 'rgt' => 900000006]);
        DB::table('information_object')->where('id', $open)->update(['lft' => 900000002, 'rgt' => 900000003]);
        DB::table('information_object')->where('id', $secret)->update(['lft' => 900000004, 'rgt' => 900000005]);
        $pdf = 'open-'.$open.'.pdf';
        file_put_contents($this->tmp.'/uploads/gate/'.$pdf, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n");
        $pdfId = (int) DB::table('object')->insertGetId(['class_name' => 'QubitDigitalObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('digital_object')->insert(['id' => $pdfId, 'object_id' => $open, 'usage_id' => 140, 'name' => $pdf, 'path' => '/uploads/r/gate/', 'mime_type' => 'application/pdf']);
        $real = app(DisclosureGate::class);
        $this->app->instance(DisclosureGate::class, new class($real, $secret) extends DisclosureGate
        {
            public function __construct(private DisclosureGate $real, private int $secret) {}

            public function restrictedIds(): array
            {
                return array_merge($this->real->restrictedIds(), [$this->secret]);
            }
        });

        $this->artisan('ahg:explorer-export', ['--ancestor' => (string) $vol, '--with-files' => true, '--min' => 1, '--out' => $this->tmp.'/out'])->assertSuccessful();

        $data = json_decode(substr(rtrim((string) file_get_contents($this->tmp.'/out/data.js'), ";\n"), strlen('window.DATA = ')), true);
        $byId = collect($data['records'])->keyBy('id');
        $this->assertFalse($byId->has((string) $secret), 'an embargoed record stays out of a package');
        $this->assertSame("img/{$open}.png", $byId[(string) $open]['img']);
        $this->assertSame("files/{$open}.pdf", $byId[(string) $open]['pdf']);
        $this->assertFileExists($this->tmp."/out/files/{$open}.pdf");
        $this->assertNotNull($bounds);
    }

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private function record(int $status, string $stem, ?string $webFile = null): int
    {
        $io = Io::create(['title' => 'ZZ '.$stem], 'en');
        StatusRow::set($io, 158, $status);
        $name = $stem.'-'.$io.'_141.png';
        $path = '/uploads/r/gate/';
        if ($webFile !== null) {
            $path = dirname($webFile).'/';
            $name = basename($webFile);
        } else {
            file_put_contents($this->tmp.'/uploads/gate/'.$name, base64_decode(self::PNG));
        }
        $master = (int) DB::table('object')->insertGetId(['class_name' => 'QubitDigitalObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('digital_object')->insert(['id' => $master, 'object_id' => $io, 'usage_id' => 140, 'name' => 'm-'.$name, 'path' => $path, 'mime_type' => 'image/png']);
        $ref = (int) DB::table('object')->insertGetId(['class_name' => 'QubitDigitalObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('digital_object')->insert(['id' => $ref, 'parent_id' => $master, 'usage_id' => 141, 'name' => $name, 'path' => $path, 'mime_type' => 'image/png']);

        return $io;
    }
}
