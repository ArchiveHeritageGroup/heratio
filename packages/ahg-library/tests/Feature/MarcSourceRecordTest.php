<?php

/**
 * Source MARC record capture, export, diff and replay (#1476).
 *
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Tests\Feature;

use AhgCore\Models\User;
use AhgLibrary\Services\LibraryService;
use AhgLibrary\Services\MarcRecordReplayService;
use AhgLibrary\Services\MarcRecordStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MarcSourceRecordTest extends TestCase
{
    private MarcRecordStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        // CREATE TABLE commits implicitly, so it runs before the test transaction.
        (require __DIR__ . '/../../database/migrations/2026_11_22_000001_create_library_marc_record_table.php')->up();
        DB::beginTransaction();
        $this->store = new MarcRecordStore();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function xml(string $title = 'Source Title', string $wrap = 'collection'): string
    {
        $record = <<<XML
<record>
    <leader>00000nam a2200000 i 4500</leader>
    <controlfield tag="001">SRC-1</controlfield>
    <controlfield tag="007">ta</controlfield>
    <controlfield tag="007">cr</controlfield>
    <controlfield tag="008">260101s2020    xx            000 0 eng d</controlfield>
    <datafield tag="020" ind1=" " ind2=" "><subfield code="a">9780306406157</subfield></datafield>
    <datafield tag="245" ind1="1" ind2="0"><subfield code="a">{$title}</subfield><subfield code="b">Sub</subfield></datafield>
    <datafield tag="264" ind1=" " ind2="1"><subfield code="a">Pretoria</subfield><subfield code="b">Unisa Press</subfield></datafield>
    <datafield tag="500" ind1=" " ind2=" "><subfield code="a">First note</subfield></datafield>
    <datafield tag="500" ind1=" " ind2=" "><subfield code="a">Second note</subfield></datafield>
    <datafield tag="949" ind1="9" ind2="1"><subfield code="x">Vendor local</subfield></datafield>
  </record>
XML;
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<{$wrap} xmlns=\"http://www.loc.gov/MARC21/slim\">\n  {$record}\n</{$wrap}>\n";
    }

    private function iso(array $fields, string $coding = 'a'): string
    {
        $directory = '';
        $body = '';
        foreach ($fields as [$tag, $content]) {
            $field = $content . "\x1E";
            $directory .= $tag . sprintf('%04d%05d', strlen($field), strlen($body));
            $body .= $field;
        }
        $base = 24 + strlen($directory) + 1;
        $length = $base + strlen($body) + 1;
        $leader = sprintf('%05dnam %s22%05d i 4500', $length, $coding, $base);
        return $leader . $directory . "\x1E" . $body . "\x1D";
    }

    private function isoFields(): array
    {
        return [
            ['001', 'BIN-1'],
            ['007', 'ta'],
            ['007', 'cr'],
            ['245', "10\x1FaBinary Title \xC3\xA9\x1FbSub"],
            ['500', "  \x1FaNote one"],
            ['500', "  \x1FaNote two"],
            ['949', "91\x1FxLocal\x1FxRepeat"],
        ];
    }

    private function makeItem(array $data = []): array
    {
        $ioId = (new LibraryService('en'))->create($data + ['title' => 'Item ' . uniqid()]);
        return [$ioId, (int) DB::table('library_item')->where('information_object_id', $ioId)->value('id')];
    }

    private function makeUser(?int $group): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert([
            'id' => $id, 'username' => 'marc-src-' . $id, 'email' => uniqid('marc-src-', true) . '@example.test',
            'password_hash' => Hash::make('secret'), 'active' => 1,
        ]);
        if ($group) {
            DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => $group]);
        }
        Cache::forget("acl_groups_{$id}");
        return User::findOrFail($id);
    }

    public function test_preview_commit_stores_the_exact_file(): void
    {
        $raw = $this->xml('Commit Path Title');
        $this->actingAs($this->makeUser(100))
            ->post(route('library.marc-import-commit'), ['marc_file' => base64_encode($raw)])
            ->assertRedirect(route('library.browse'));

        $ioId = DB::table('information_object_i18n')->where('title', 'Commit Path Title')->value('id');
        $itemId = (int) DB::table('library_item')->where('information_object_id', $ioId)->value('id');
        $row = $this->store->replaySource($itemId);

        $this->assertSame($raw, $row->marcxml);
        $this->assertSame('marcxml', $row->source_format);
        $this->assertSame(hash('sha256', $raw), $row->content_sha256);
    }

    public function test_batch_file_gives_each_item_only_its_own_record(): void
    {
        $one = $this->xml('Batch One');
        $two = str_replace('Vendor local', 'Other vendor', $this->xml('Batch Two'));
        preg_match('#<record>.*</record>#s', $one, $a);
        preg_match('#<record>.*</record>#s', $two, $b);
        $batch = "<collection xmlns=\"http://www.loc.gov/MARC21/slim\">{$a[0]}{$b[0]}</collection>";

        $this->actingAs($this->makeUser(100))
            ->post(route('library.marc-import-commit'), ['marc_file' => base64_encode($batch)]);

        $ioId = DB::table('information_object_i18n')->where('title', 'Batch Two')->value('id');
        $itemId = (int) DB::table('library_item')->where('information_object_id', $ioId)->value('id');
        $row = $this->store->replaySource($itemId);

        $this->assertSame('marcxml_record', $row->source_format);
        $this->assertSame(0, (int) $row->is_verbatim, 're-serialised, so not the received bytes');
        $lines = $this->store->fieldLines($row->marcxml);
        $this->assertContains('949 91 $x Other vendor', $lines);
        $this->assertNotContains('949 91 $x Vendor local', $lines);
        $this->assertContains('500    $a Second note', $lines);
    }

    public function test_binary_import_keeps_bytes_and_a_lossless_marcxml(): void
    {
        [$ioId, $itemId] = $this->makeItem();
        $iso = $this->iso($this->isoFields());
        $second = $this->iso([['001', 'OTHER'], ['245', "10\x1FaOther"]]);

        $this->store->captureImport($ioId, $iso . $second);
        $row = $this->store->replaySource($itemId);

        $this->assertSame($iso, $row->source_binary, 'only the record the item came from');
        $lines = $this->store->fieldLines($row->marcxml);
        $this->assertSame(2, count(array_filter($lines, fn ($l) => str_starts_with($l, '007 '))));
        $this->assertContains('245 10 $a Binary Title é $b Sub', $lines);
        $this->assertContains('500    $a Note two', $lines);
        $this->assertContains('949 91 $x Local $x Repeat', $lines);
    }

    public function test_marc8_binary_is_kept_without_a_converted_copy(): void
    {
        [$ioId, $itemId] = $this->makeItem();
        $iso = $this->iso([['245', "10\x1FaCaf\xE2e"]], ' ');

        $this->store->captureImport($ioId, $iso);
        $row = $this->store->replaySource($itemId);

        $this->assertSame($iso, $row->source_binary);
        $this->assertNull($row->marcxml);
    }

    public function test_marc8_replay_never_writes_undecoded_text(): void
    {
        [$ioId, $itemId] = $this->makeItem();
        // Bytes that happen to be valid UTF-8 but are MARC-8 here.
        $this->store->captureImport($ioId, $this->iso([['245', "10\x1FaT\x1FbCaf\xC3\xA9"], ['300', "  \x1Fa120 p."]], ' '));
        $replay = new MarcRecordReplayService();

        $plan = collect($replay->plan($itemId)['fields'])->keyBy('column');
        $this->assertSame('unusable', $plan['subtitle']['status']);
        $this->assertSame('', $plan['subtitle']['mapped']);
        $this->assertSame('fill', $plan['pagination']['status']);
    }

    public function test_non_utf8_xml_import_succeeds_and_keeps_original_bytes(): void
    {
        $raw = mb_convert_encoding(
            str_replace('encoding="UTF-8"', 'encoding="ISO-8859-1"', $this->xml('Café Latin')),
            'ISO-8859-1',
            'UTF-8'
        );
        $user = $this->makeUser(100);
        $this->actingAs($user)->post(route('library.marc-import-commit'), ['marc_file' => base64_encode($raw)])
            ->assertRedirect(route('library.browse'));

        $ioId = DB::table('information_object_i18n')->where('title', 'Café Latin')->value('id');
        $itemId = (int) DB::table('library_item')->where('information_object_id', $ioId)->value('id');
        $row = $this->store->replaySource($itemId);

        $this->assertSame($raw, $row->source_binary);
        $this->assertContains('245 10 $a Café Latin $b Sub', $this->store->fieldLines($row->marcxml));
        $this->get(route('library.marc-source-download', [$itemId, 1, 'binary']))
            ->assertOk()->assertHeader('Content-Type', 'application/xml');
    }

    public function test_binary_commit_route_stores_the_source(): void
    {
        $iso = $this->iso($this->isoFields());
        $this->actingAs($this->makeUser(100))
            ->post(route('library.marc-binary-commit'), ['marc_file' => base64_encode($iso)])
            ->assertRedirect();

        $ioId = DB::table('information_object_i18n')->where('title', 'Binary Title é')->value('id');
        $itemId = (int) DB::table('library_item')->where('information_object_id', $ioId)->value('id');
        $this->assertSame($iso, $this->store->replaySource($itemId)->source_binary);
    }

    public function test_copy_cataloguing_import_stores_the_source(): void
    {
        $raw = $this->xml('Copy Catalogued');
        $this->actingAs($this->makeUser(100))
            ->post(route('library.copy-cataloguing-import'), ['marc_content' => base64_encode($raw)])
            ->assertRedirect();

        $ioId = DB::table('information_object_i18n')->where('title', 'Copy Catalogued')->value('id');
        $itemId = (int) DB::table('library_item')->where('information_object_id', $ioId)->value('id');
        $this->assertSame($raw, $this->store->replaySource($itemId)->marcxml);
    }

    public function test_direct_file_upload_still_commits(): void
    {
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('records.xml', $this->xml('Direct Upload'));
        $this->actingAs($this->makeUser(100))
            ->post(route('library.marc-import-commit'), ['marc_file' => $file])
            ->assertRedirect(route('library.browse'));
        $this->assertTrue(DB::table('information_object_i18n')->where('title', 'Direct Upload')->exists());
    }

    public function test_edit_adds_a_version_and_leaves_the_source_alone(): void
    {
        [$ioId, $itemId] = $this->makeItem();
        $raw = $this->xml();
        $this->store->captureImport($ioId, $raw);

        $this->actingAs($this->makeUser(100))->put(route('library.marc-update', $itemId), [
            'info_object_id' => $ioId,
            'title_statement' => ['245' => ['a' => 'Edited Title']],
        ])->assertRedirect();

        $versions = $this->store->versions($itemId);
        $this->assertCount(2, $versions);
        $this->assertSame('edit', $versions[0]->event);
        $this->assertSame(0, (int) $versions[0]->is_verbatim);
        $this->assertStringContainsString('Edited Title', $this->store->version($itemId, 2)->marcxml);
        $this->assertSame($raw, $this->store->replaySource($itemId)->marcxml);
    }

    public function test_first_edit_of_an_older_record_keeps_a_baseline_and_no_replay_source(): void
    {
        [, $itemId] = $this->makeItem(['publisher' => 'Before']);
        $slug = DB::table('slug')->where('object_id', DB::table('library_item')->where('id', $itemId)->value('information_object_id'))->value('slug');

        (new LibraryService('en'))->update($slug, ['title' => 'After edit']);

        $events = array_column($this->store->versions($itemId), 'event');
        $this->assertSame('baseline', end($events));
        $this->assertNull($this->store->replaySource($itemId), 'a generated row is never a replay source');
    }

    public function test_unchanged_record_adds_no_version(): void
    {
        [, $itemId] = $this->makeItem();
        $this->store->snapshot($itemId, 'edit');
        DB::table('object')->where('id', DB::table('library_item')->where('id', $itemId)->value('information_object_id'))
            ->update(['updated_at' => now()->addHour()]);
        $this->store->snapshot($itemId, 'edit');

        $this->assertCount(1, $this->store->versions($itemId));
    }

    public function test_diff_reports_fields_the_catalogue_does_not_hold(): void
    {
        $result = $this->store->diff($this->xml(), str_replace('Second note', 'Changed note', $this->xml()));

        $this->assertSame(['500    $a Second note'], $result['removed']);
        $this->assertSame(['500    $a Changed note'], $result['added']);
    }

    public function test_replay_plan_writes_nothing_and_apply_respects_corrections(): void
    {
        [$ioId, $itemId] = $this->makeItem(['publisher' => 'Corrected by staff']);
        $this->store->captureImport($ioId, $this->xml());
        $replay = new MarcRecordReplayService();

        $plan = collect($replay->plan($itemId)['fields'])->keyBy('column');
        $this->assertSame('conflict', $plan['publisher']['status']);
        $this->assertSame('fill', $plan['isbn']['status']);
        $this->assertNull(DB::table('library_item')->where('id', $itemId)->value('isbn'));

        $replay->apply($itemId, $replay->fillableColumns($itemId));
        $row = DB::table('library_item')->where('id', $itemId)->first();
        $this->assertSame('9780306406157', $row->isbn);
        $this->assertSame('Corrected by staff', $row->publisher);
        $this->assertSame('replay', $this->store->versions($itemId)[0]->event);

        $replay->apply($itemId, ['publisher']);
        $this->assertSame('Unisa Press', DB::table('library_item')->where('id', $itemId)->value('publisher'));
    }

    public function test_replay_marks_oversized_values_unusable(): void
    {
        [$ioId, $itemId] = $this->makeItem();
        $this->store->captureImport($ioId, str_replace('9780306406157', '9780306406157 (paperback edition)', $this->xml()));
        $replay = new MarcRecordReplayService();

        $plan = collect($replay->plan($itemId)['fields'])->keyBy('column');
        $this->assertSame('unusable', $plan['isbn']['status']);
        $this->assertSame(0, $replay->apply($itemId, ['isbn']));
    }

    public function test_replay_command_reports_by_default_and_fills_only_with_apply(): void
    {
        [$ioId, $itemId] = $this->makeItem(['publisher' => 'Corrected by staff']);
        $this->store->captureImport($ioId, $this->xml());

        $this->artisan('ahg:library-marc-replay', ['--item' => $itemId])->assertSuccessful();
        $this->assertNull(DB::table('library_item')->where('id', $itemId)->value('isbn'));

        $this->artisan('ahg:library-marc-replay', ['--item' => $itemId, '--apply' => true])->assertSuccessful();
        $row = DB::table('library_item')->where('id', $itemId)->first();
        $this->assertSame('9780306406157', $row->isbn);
        $this->assertSame('Corrected by staff', $row->publisher);
    }

    public function test_pages_and_exports_for_staff(): void
    {
        [$ioId, $itemId] = $this->makeItem();
        $iso = $this->iso($this->isoFields());
        $this->store->captureImport($ioId, $iso);
        $this->actingAs($this->makeUser(100));

        $this->get(route('library.marc-source', $itemId))->assertOk()->assertSee('MARC21 binary');
        $this->get(route('library.marc-source-diff', $itemId))->assertOk()->assertSee('949 91');
        $this->get(route('library.marc-source-replay', $itemId))->assertOk()->assertSee('Replay field mapping');

        $binary = $this->get(route('library.marc-source-download', [$itemId, 1, 'binary']));
        $binary->assertOk();
        $this->assertSame($iso, $binary->getContent());
        $this->get(route('library.marc-source-download', [$itemId, 1, 'xml']))->assertOk();
        $this->get(route('library.marc-source-download', [$itemId, 99, 'xml']))->assertNotFound();
    }

    public function test_guests_and_users_without_update_are_refused(): void
    {
        [$ioId, $itemId] = $this->makeItem();
        $this->store->captureImport($ioId, $this->xml());

        $this->get(route('library.marc-source-download', [$itemId, 1, 'xml']))->assertRedirect();

        $this->actingAs($this->makeUser(null));
        $this->get(route('library.marc-source-download', [$itemId, 1, 'xml']))->assertForbidden();
        $this->post(route('library.marc-source-replay-apply', $itemId), ['columns' => ['isbn']])->assertForbidden();
        $this->assertNull(DB::table('library_item')->where('id', $itemId)->value('isbn'));
    }

    private const FIXED_008 = '260101s2020    xx            000 0 eng  ';

    public function test_binary_replay_keeps_a_40_byte_008_ending_in_blanks(): void
    {
        $this->assertSame(40, strlen(self::FIXED_008));
        $iso = $this->iso([['001', 'BIN-008'], ['008', self::FIXED_008], ['245', "10\x1FaFixed"]]);
        $this->actingAs($this->makeUser(100))
            ->post(route('library.marc-binary-commit'), ['marc_file' => base64_encode($iso)]);
        $ioId = DB::table('information_object_i18n')->where('title', 'Fixed')->value('id');
        $itemId = (int) DB::table('library_item')->where('information_object_id', $ioId)->value('id');
        $this->assertSame(self::FIXED_008, DB::table('library_item')->where('id', $itemId)->value('marc_008'));

        $plan = collect((new MarcRecordReplayService())->plan($itemId)['fields'])->keyBy('column');
        $this->assertSame('same', $plan['marc_008']['status']);
    }

    public function test_xml_replay_keeps_trailing_blanks_in_control_fields(): void
    {
        [$ioId, $itemId] = $this->makeItem();
        $this->store->captureImport($ioId, str_replace('260101s2020    xx            000 0 eng d', self::FIXED_008, $this->xml()));

        $plan = collect((new MarcRecordReplayService())->plan($itemId)['fields'])->keyBy('column');
        $this->assertSame(self::FIXED_008, $plan['marc_008']['mapped']);
    }

    public function test_pretty_printed_control_field_is_not_stored_with_its_layout(): void
    {
        [$ioId, $itemId] = $this->makeItem();
        $this->store->captureImport($ioId, str_replace('>260101s2020    xx            000 0 eng d<', ">\n      260101s2020    xx            000 0 eng d\n    <", $this->xml()));

        $plan = collect((new MarcRecordReplayService())->plan($itemId)['fields'])->keyBy('column');
        $this->assertSame('260101s2020    xx            000 0 eng d', $plan['marc_008']['mapped']);
    }

    public function test_unparseable_xml_source_is_not_replayed(): void
    {
        [$ioId, $itemId] = $this->makeItem();
        // Latin-1 bytes with no encoding declaration: not valid UTF-8, not parseable.
        $raw = "<?xml version=\"1.0\"?><record><leader>x</leader><bad>Caf\xE9</record>";
        $this->store->captureImport($ioId, $raw);
        $row = $this->store->replaySource($itemId);
        $this->assertSame('marcxml', $row->source_format);
        $this->assertNull($row->marcxml);

        $replay = new MarcRecordReplayService();
        $plan = $replay->plan($itemId);
        $this->assertFalse($plan['replayable']);
        $this->assertSame([], $plan['fields']);
        $this->assertSame(0, $replay->apply($itemId, array_keys(MarcRecordReplayService::COLUMNS)));
        $this->assertNull(DB::table('library_item')->where('id', $itemId)->value('marc_leader'));

        $this->actingAs($this->makeUser(100))
            ->get(route('library.marc-source-replay', $itemId))->assertOk()->assertSee('cannot be replayed');
    }

    public function test_copy_cataloguing_refuses_an_unreadable_payload(): void
    {
        $before = DB::table('library_item')->count();
        $this->actingAs($this->makeUser(100))
            ->post(route('library.copy-cataloguing-import'), ['marc_content' => base64_encode("<?xml version=\"1.0\"?><record><bad>Caf\xE9</record>")])
            ->assertSessionHas('error');
        $this->assertSame($before, DB::table('library_item')->count());
    }

    public function test_binary_record_without_terminator_is_stored_as_binary(): void
    {
        [$ioId, $itemId] = $this->makeItem();
        $iso = rtrim($this->iso([['001', 'NO-END'], ['245', "10\x1FaNo terminator"], ['300', "  \x1Fa99 p."]]), "\x1D");

        $this->store->captureImport($ioId, $iso);
        $row = $this->store->replaySource($itemId);

        $this->assertSame('iso2709', $row->source_format);
        $this->assertSame($iso, $row->source_binary);
        $this->assertContains('245 10 $a No terminator', $this->store->fieldLines($row->marcxml));
        $plan = collect((new MarcRecordReplayService())->plan($itemId)['fields'])->keyBy('column');
        $this->assertSame('99 p.', $plan['pagination']['mapped']);
    }

    public function test_binary_record_quoting_xml_is_stored_as_binary(): void
    {
        [$ioId, $itemId] = $this->makeItem();
        $this->store->captureImport($ioId, $this->iso([['245', "10\x1FaT"], ['500', "  \x1FaAbout <record> tags"]]));
        $this->assertSame('iso2709', $this->store->replaySource($itemId)->source_format);
    }

    private function matchedRecord(string $identifier, string $title): string
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<record xmlns=\"http://www.loc.gov/MARC21/slim\"><leader>00000nam a2200000 i 4500</leader>"
            . "<controlfield tag=\"001\">{$identifier}</controlfield>"
            . "<datafield tag=\"245\" ind1=\"1\" ind2=\"0\"><subfield code=\"a\">{$title}</subfield></datafield></record>\n";
    }

    public function test_importer_update_keeps_baseline_and_incoming_record(): void
    {
        $identifier = 'MATCH-' . uniqid();
        [, $itemId] = $this->makeItem(['identifier' => $identifier, 'title' => 'Before import']);
        $raw = $this->matchedRecord($identifier, 'After import');

        $result = (new \AhgMetadataExport\Services\Importers\MarcXmlImporter())->commit($raw, 'en');

        $this->assertSame('update', $result[0]['action']);
        $versions = $this->store->versions($itemId);
        $this->assertSame(['import', 'baseline'], array_column($versions, 'event'));
        $this->assertStringContainsString('Before import', $this->store->version($itemId, 1)->marcxml);
        $this->assertSame($raw, $this->store->replaySource($itemId)->marcxml);
    }

    public function test_importer_update_of_item_with_history_records_the_import(): void
    {
        $identifier = 'MATCH-' . uniqid();
        [$ioId, $itemId] = $this->makeItem(['identifier' => $identifier, 'title' => 'State before second import']);
        $this->store->captureImport($ioId, $this->xml());

        $raw = $this->matchedRecord($identifier, 'Second import');
        (new \AhgMetadataExport\Services\Importers\MarcXmlImporter())->commit($raw, 'en');

        $versions = $this->store->versions($itemId);
        $this->assertSame(['import', 'baseline', 'import'], array_column($versions, 'event'));
        $this->assertStringContainsString('State before second import', $this->store->version($itemId, 2)->marcxml);
        $this->assertSame($raw, $this->store->replaySource($itemId)->marcxml);
    }

    public function test_library_api_import_captures_the_update(): void
    {
        $identifier = 'MATCH-' . uniqid();
        [, $itemId] = $this->makeItem(['identifier' => $identifier, 'title' => 'Before API import']);
        $raw = $this->matchedRecord($identifier, 'After API import');

        // Key handling is not under test; the controller still checks the acting user's ACL.
        $this->withoutMiddleware();
        $this->actingAs($this->makeUser(100))
            ->postJson('/api/cataloguing/marc/import', ['marcxml' => $raw, 'culture' => 'en'])
            ->assertCreated()
            ->assertJsonPath('data.attributes.updated', 1);

        $this->assertSame(['import', 'baseline'], array_column($this->store->versions($itemId), 'event'));
        $this->assertStringContainsString('Before API import', $this->store->version($itemId, 1)->marcxml);
        $this->assertSame($raw, $this->store->replaySource($itemId)->marcxml);
    }

    public function test_importer_batch_update_stores_only_the_matching_record(): void
    {
        $identifier = 'MATCH-' . uniqid();
        [, $itemId] = $this->makeItem(['identifier' => $identifier]);
        $inner = fn (string $x) => preg_replace('/^.*?<record[^>]*>|<\/record>.*$/s', '', $x);
        $batch = '<collection xmlns="http://www.loc.gov/MARC21/slim"><record>' . $inner($this->matchedRecord($identifier, 'Batch match'))
            . '</record><record>' . $inner($this->matchedRecord('NEW-' . uniqid(), 'Batch other')) . '</record></collection>';

        (new \AhgMetadataExport\Services\Importers\MarcXmlImporter())->commit($batch, 'en');

        $row = $this->store->replaySource($itemId);
        $this->assertSame('marcxml_record', $row->source_format);
        $this->assertSame(0, (int) $row->is_verbatim);
        $this->assertStringContainsString('Batch match', $row->marcxml);
        $this->assertStringNotContainsString('Batch other', $row->marcxml);
    }

    public function test_metadata_export_commit_route_captures_the_update(): void
    {
        $identifier = 'MATCH-' . uniqid();
        [, $itemId] = $this->makeItem(['identifier' => $identifier]);
        $raw = $this->matchedRecord($identifier, 'Route import');

        $this->actingAs($this->makeUser(100))
            ->post(route('ahgmetadataexport.marc.import.commit'), ['marcxml_payload' => base64_encode($raw), 'culture' => 'en'])
            ->assertOk();

        $this->assertSame($raw, $this->store->replaySource($itemId)->marcxml);
    }

    public function test_replay_apply_rejects_columns_outside_the_mapping(): void
    {
        [$ioId, $itemId] = $this->makeItem();
        $this->store->captureImport($ioId, $this->xml());

        $this->actingAs($this->makeUser(100))
            ->post(route('library.marc-source-replay-apply', $itemId), ['columns' => ['barcode']])
            ->assertSessionHasErrors('columns.0');
    }
}
