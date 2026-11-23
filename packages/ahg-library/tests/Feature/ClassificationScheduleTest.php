<?php

namespace AhgLibrary\Tests\Feature;

use AhgLibrary\Services\ClassificationScheduleService;
use App\Http\Middleware\CheckAcl;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ClassificationScheduleTest extends LibraryFeatureTestCase
{
    private ClassificationScheduleService $svc;

    protected function setUp(): void
    {
        $this->migrationFiles[] = '2026_11_22_100000_create_library_classification_tables.php';
        parent::setUp();
        $this->svc = new ClassificationScheduleService();
        Schema::table('library_item', function (Blueprint $t) {
            $t->string('classification_number', 255)->nullable();
            $t->string('dewey_decimal', 50)->nullable();
        });

        Schema::create('ahg_dropdown', function (Blueprint $t) {
            $t->id();
            foreach (['taxonomy', 'taxonomy_label', 'taxonomy_section', 'code', 'label', 'color', 'icon'] as $c) {
                $t->string($c)->nullable();
            }
            $t->integer('sort_order')->default(0);
            $t->boolean('is_default')->default(false);
            $t->boolean('is_active')->default(true);
            $t->text('metadata')->nullable();
        });
        foreach (['LC', 'Dewey', 'UDC', 'Local'] as $code) {
            DB::table('ahg_dropdown')->insert(['taxonomy' => 'library_classification_scheme', 'code' => $code, 'label' => $code]);
        }
        foreach (['off', 'warn', 'strict'] as $code) {
            DB::table('ahg_dropdown')->insert(['taxonomy' => 'library_classification_validation', 'code' => $code, 'label' => $code]);
        }
    }

    private function schedule(string $scheme = 'Dewey', string $mode = 'warn', bool $active = true, string $name = 'Test'): int
    {
        return $this->svc->saveSchedule(['scheme' => $scheme, 'name' => $name, 'validation_mode' => $mode, 'is_active' => $active]);
    }

    private function csv(string $body): string
    {
        $path = tempnam(sys_get_temp_dir(), 'class');
        file_put_contents($path, $body);

        return $path;
    }

    private function loadDewey(int $id): void
    {
        $r = $this->svc->importCsv($id, $this->csv("notation,caption\n000,Computing\n005,Programming\n005.1,Programs\n"));
        $this->assertSame([], $r['errors']);
    }

    public function test_csv_builds_hierarchy_and_reimport_updates(): void
    {
        $id = $this->schedule();
        $this->loadDewey($id);

        $roots = $this->svc->children($id, null);
        $this->assertSame(['000'], $roots->pluck('notation')->all());
        $this->assertSame(1, (int) $roots[0]->child_count);
        $leaf = DB::table('library_classification_entry')->where('notation', '005.1')->first();
        $this->assertSame(2, (int) $leaf->depth);
        $this->assertSame(['000', '005', '005.1'], array_column($this->svc->path($leaf), 'notation'));

        $r = $this->svc->importCsv($id, $this->csv("notation,caption\n005,Coding\n006,Special\n"));
        $this->assertSame(['created' => 1, 'updated' => 1, 'errors' => []], $r);
        $this->assertDatabaseHas('library_classification_entry', ['notation' => '005', 'caption' => 'Coding']);
    }

    public function test_bad_csv_writes_nothing(): void
    {
        $id = $this->schedule();
        foreach ([
            "notation\n005\n",
            "notation,caption\n005,Ok\n006,\n",
            "notation,caption\n005,Ok\n005,Again\n",
            "notation,caption,parent_notation\n005,Ok,999\n",
            "notation,caption,parent_notation\n100,A,200\n200,B,100\n",
        ] as $body) {
            $this->assertNotEmpty($this->svc->importCsv($id, $this->csv($body))['errors'], $body);
            $this->assertSame(0, DB::table('library_classification_entry')->count(), $body);
        }
    }

    public function test_check_statuses(): void
    {
        $this->assertSame('no_schedule', $this->svc->check('Dewey', '005')['status']);
        $id = $this->schedule();
        $this->loadDewey($id);

        $this->assertSame('empty', $this->svc->check('Dewey', ' ')['status']);
        $this->assertSame('exact', $this->svc->check('ddc', '005.1')['status']);
        $this->assertSame('exact', $this->svc->check('Dewey', '005.1 ABC')['status']);
        $broader = $this->svc->check('Dewey', '005.133');
        $this->assertSame('broader', $broader['status']);
        $this->assertSame('005.1', $broader['entry']->notation);
        $this->assertSame('unknown', $this->svc->check('Dewey', '700')['status']);
        $this->assertSame('invalid', $this->svc->check('Dewey', 'not-a-number')['status']);
    }

    public function test_range_entries_cover_lc_numbers(): void
    {
        $id = $this->schedule('LC');
        $this->svc->saveEntry($id, ['notation' => 'QA', 'caption' => 'Mathematics']);
        $this->svc->saveEntry($id, ['notation' => 'QA75.5', 'notation_end' => 'QA76.95', 'caption' => 'Computer science']);

        $hit = $this->svc->check('LC', 'QA76.73 .J38');
        $this->assertSame('within', $hit['status']);
        $this->assertSame('QA75.5', $hit['entry']->notation);
        $this->assertSame('broader', $this->svc->check('LC', 'QA300')['status']);
        $this->assertSame('QA', $this->svc->check('LC', 'QA300')['entry']->notation);
    }

    public function test_guard_follows_mode_and_ignores_unchanged_number(): void
    {
        $id = $this->schedule('Dewey', 'warn');
        $this->loadDewey($id);
        $bad = ['classification_scheme' => 'Dewey', 'dewey_decimal' => '700'];

        $this->assertStringContainsString('700', $this->svc->guardKeyedNumber($bad));
        $this->assertNull($this->svc->guardKeyedNumber(['classification_scheme' => 'Dewey', 'dewey_decimal' => '005.1']));

        DB::table('library_classification_schedule')->where('id', $id)->update(['validation_mode' => 'strict']);
        $this->assertNull($this->svc->guardKeyedNumber($bad, $bad), 'legacy value must not block an edit');
        try {
            $this->svc->guardKeyedNumber($bad);
            $this->fail('strict mode must refuse');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('dewey_decimal', $e->errors());
        }

        DB::table('library_classification_schedule')->where('id', $id)->update(['validation_mode' => 'off']);
        $this->assertNull($this->svc->guardKeyedNumber($bad));
    }

    public function test_strict_refuses_hierarchy_only_matches(): void
    {
        $lc = $this->schedule('LC', 'strict', true, 'LC');
        foreach (['N', 'Q', 'QA'] as $n) {
            $this->svc->saveEntry($lc, ['notation' => $n, 'caption' => $n]);
        }
        $this->svc->saveEntry($lc, ['notation' => 'QA75.5', 'notation_end' => 'QA76.95', 'caption' => 'Computing']);
        $dewey = $this->schedule('Dewey', 'strict', true, 'D');
        $this->svc->saveEntry($dewey, ['notation' => '900', 'caption' => 'History']);

        foreach ([['LC', 'NONSENSE'], ['LC', 'Q99999.999'], ['LC', 'Q SOMETHING'], ['Dewey', '999.99999'], ['Dewey', '9001']] as [$scheme, $number]) {
            $field = $scheme === 'LC' ? 'call_number' : 'dewey_decimal';
            try {
                $this->svc->guardKeyedNumber(['classification_scheme' => $scheme, $field => $number]);
                $this->fail("$number must be refused");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($field, $e->errors());
            }
        }
        foreach (['QA', 'QA76.73 .J38', 'QA75.5'] as $good) {
            $this->assertNull($this->svc->guardKeyedNumber(['classification_scheme' => 'LC', 'call_number' => $good]), $good);
        }
        $this->assertNull($this->svc->guardKeyedNumber(['classification_scheme' => 'Dewey', 'dewey_decimal' => '900 ABC']));
    }

    public function test_title_only_edit_of_legacy_record_saves_in_strict_mode(): void
    {
        $this->schedule('UDC', 'strict', true, 'U');
        $stored = ['classification_scheme' => 'UDC', 'classification_number' => '53', 'call_number' => '700 LEG', 'dewey_decimal' => null];
        // The real form posts these fields and has no classification_number input.
        $form = ['title' => 'New title', 'classification_scheme' => 'UDC', 'call_number' => '700 LEG', 'dewey_decimal' => null];

        $this->assertNull($this->svc->guardKeyedNumber($form, $stored));

        $this->expectException(ValidationException::class);
        $this->svc->guardKeyedNumber(['call_number' => '701 NEW'] + $form, $stored);
    }

    public function test_reimport_keeps_columns_absent_from_the_file(): void
    {
        $id = $this->schedule('Local');
        $this->svc->importCsv($id, $this->csv("notation,caption,notation_end,note\nA1,First,A9,Keep this note\nB1,Other,,\n"));
        $parent = (int) DB::table('library_classification_entry')->where('notation', 'B1')->value('id');
        DB::table('library_classification_entry')->where('notation', 'A1')->update(['parent_id' => $parent]);

        $r = $this->svc->importCsv($id, $this->csv("notation,caption\nA1,Renamed\n"));

        $this->assertSame([], $r['errors']);
        $row = DB::table('library_classification_entry')->where('notation', 'A1')->first();
        $this->assertSame('Renamed', $row->caption);
        $this->assertSame('Keep this note', $row->note);
        $this->assertSame('A9', $row->notation_end);
        $this->assertNotNull($row->sort_key_end);
        $this->assertSame($parent, (int) $row->parent_id);
        $this->assertSame(1, (int) $row->depth);
    }

    public function test_reimport_applies_columns_and_parents_it_does_supply(): void
    {
        $id = $this->schedule('Local');
        $this->svc->importCsv($id, $this->csv("notation,caption,note\nA1,First,Old\nB1,Other,\n"));

        $this->svc->importCsv($id, $this->csv("notation,caption,note,parent_notation\nA1,First,,B1\n"));

        $row = DB::table('library_classification_entry')->where('notation', 'A1')->first();
        $this->assertNull($row->note);
        $this->assertSame((int) DB::table('library_classification_entry')->where('notation', 'B1')->value('id'), (int) $row->parent_id);
    }

    public function test_row_with_too_many_cells_is_refused_with_its_line(): void
    {
        $id = $this->schedule('Local');
        $r = $this->svc->importCsv($id, $this->csv("notation,caption\nC0,Fine\nC1,Language, literature\n"));

        $this->assertCount(1, $r['errors']);
        $this->assertStringContainsString('Line 3', $r['errors'][0]);
        $this->assertSame(0, DB::table('library_classification_entry')->count());
    }

    public function test_broader_entry_added_later_adopts_its_children(): void
    {
        $id = $this->schedule();
        $child = $this->svc->saveEntry($id, ['notation' => '005.1', 'caption' => 'Programs']);
        $broad = $this->svc->saveEntry($id, ['notation' => '005', 'caption' => 'Programming']);

        $row = $this->svc->entry($child);
        $this->assertSame($broad, (int) $row->parent_id);
        $this->assertSame(1, (int) $row->depth);
        $this->assertSame(1, (int) $this->svc->children($id, null)[0]->child_count);

        // Loaded in stages: a later file's broader row adopts rows outside that file.
        $this->svc->importCsv($id, $this->csv("notation,caption\n000,Computing\n"));
        $this->assertSame((int) DB::table('library_classification_entry')->where('notation', '000')->value('id'), (int) $this->svc->entry($broad)->parent_id);
        $this->assertSame(2, (int) $this->svc->entry($child)->depth);
    }

    public function test_parent_set_outside_the_notation_chain_is_not_adopted_away(): void
    {
        $id = $this->schedule();
        $manual = $this->svc->saveEntry($id, ['notation' => '300', 'caption' => 'Manual']);
        $child = $this->svc->saveEntry($id, ['notation' => '005.1', 'caption' => 'Programs', 'parent_id' => $manual]);

        $this->svc->saveEntry($id, ['notation' => '005', 'caption' => 'Programming']);

        $this->assertSame($manual, (int) $this->svc->entry($child)->parent_id);
    }

    public function test_shelf_key_follows_the_call_number_not_a_hidden_field(): void
    {
        DB::table('library_item')->insert(['information_object_id' => 2000, 'material_type' => 'monograph',
            'classification_scheme' => 'LC', 'call_number' => 'Z9', 'classification_number' => 'QA1']);
        DB::table('library_item')->insert(['information_object_id' => 2001, 'material_type' => 'monograph',
            'classification_scheme' => 'LC', 'call_number' => 'B5']);
        $this->svc->rebuildShelfKeys();

        $key = DB::table('library_item_shelf_key')->where('class_number', 'Z9')->first();
        $this->assertNotNull($key);
        $this->assertStringStartsWith('Z  000009', $key->sort_key);
        $this->assertSame(['B5', 'Z9'], $this->svc->shelfList(10)->pluck('call_number')->all());

        DB::table('library_item')->where('information_object_id', 2000)->update(['call_number' => 'A1']);
        $this->svc->refreshShelfKeyForObject(2000);
        $this->assertSame(['A1', 'B5'], $this->svc->shelfList(10)->pluck('call_number')->all());
    }

    public function test_keyed_number_precedence(): void
    {
        $this->assertSame('call_number', $this->svc->keyedNumber(['classification_scheme' => 'LC', 'call_number' => 'Z9', 'classification_number' => 'QA1'])['field']);
        $this->assertSame('dewey_decimal', $this->svc->keyedNumber(['classification_scheme' => 'Dewey', 'call_number' => '005 ABC', 'dewey_decimal' => '005'])['field']);
        $this->assertSame('classification_number', $this->svc->keyedNumber(['classification_scheme' => 'LC', 'classification_number' => 'QA1'])['field']);
    }

    public function test_blank_scheme_files_when_safe_and_is_flagged_when_not(): void
    {
        foreach (['QA100', 'QA9', 'shelf by door'] as $i => $call) {
            DB::table('library_item')->insert(['information_object_id' => 3000 + $i, 'material_type' => 'monograph', 'call_number' => $call]);
        }
        $this->assertSame(2, $this->svc->rebuildShelfKeys());

        $list = $this->svc->shelfList(10);
        $this->assertSame(['QA9', 'QA100', 'shelf by door'], $list->pluck('call_number')->all());
        $this->assertNull($list[2]->shelf_sort_key);
    }

    public function test_call_number_that_does_not_fit_its_scheme_is_not_filed(): void
    {
        DB::table('library_item')->insert(['information_object_id' => 4000, 'material_type' => 'monograph',
            'classification_scheme' => 'LC', 'call_number' => '700 LEG']);
        $this->assertSame(0, $this->svc->rebuildShelfKeys());
        $this->assertNull($this->svc->shelfList(1)[0]->shelf_sort_key);
    }

    public function test_non_latin_local_notations_file_and_range(): void
    {
        $id = $this->schedule('Local');
        $this->svc->saveEntry($id, ['notation' => '甲1', 'notation_end' => '甲9', 'caption' => 'Range']);
        $this->assertSame('within', $this->svc->check('Local', '甲5')['status']);
        $this->assertSame('unknown', $this->svc->check('Local', '乙5')['status']);
    }

    public function test_notation_search_ignores_case(): void
    {
        $id = $this->schedule('Local');
        $this->svc->saveEntry($id, ['notation' => 'Ab1', 'caption' => 'Mixed']);
        $this->assertSame(['Ab1'], $this->svc->search($id, 'aB1')->pluck('notation')->all());
    }

    public function test_navigation_links_to_schedules(): void
    {
        $dir = dirname(__DIR__, 2).'/resources/views';
        $this->assertStringContainsString("route('library.classification-index')", file_get_contents($dir.'/reports/index.blade.php'));
        $this->assertStringContainsString("route('library.classification-index')", file_get_contents($dir.'/classification/_picker.blade.php'));
    }

    public function test_one_active_schedule_per_scheme(): void
    {
        $a = $this->schedule('Dewey', 'warn', true, 'A');
        $b = $this->schedule('Dewey', 'warn', true, 'B');
        $this->assertSame($b, (int) $this->svc->activeSchedule('Dewey')->id);
        $this->assertSame(0, (int) $this->svc->schedule($a)->is_active);
    }

    public function test_entry_loops_and_foreign_parents_are_refused(): void
    {
        $id = $this->schedule();
        $other = $this->schedule('Dewey', 'warn', false, 'Other');
        $a = $this->svc->saveEntry($id, ['notation' => '100', 'caption' => 'A']);
        $b = $this->svc->saveEntry($id, ['notation' => '110', 'caption' => 'B', 'parent_id' => $a]);
        $foreign = $this->svc->saveEntry($other, ['notation' => '200', 'caption' => 'F']);

        try {
            $this->svc->saveEntry($id, ['notation' => '100', 'caption' => 'A', 'parent_id' => $b], $a);
            $this->fail('loop must be refused');
        } catch (\RuntimeException) {
            $this->assertNull($this->svc->entry($a)->parent_id);
        }
        $this->expectException(\RuntimeException::class);
        $this->svc->saveEntry($id, ['notation' => '120', 'caption' => 'C', 'parent_id' => $foreign]);
    }

    public function test_entry_with_children_cannot_be_deleted(): void
    {
        $id = $this->schedule();
        $this->loadDewey($id);
        $this->expectException(\RuntimeException::class);
        $this->svc->deleteEntry((int) DB::table('library_classification_entry')->where('notation', '005')->value('id'));
    }

    public function test_shelf_keys_drive_shelf_order(): void
    {
        foreach (['QA100', 'QA9', 'QA76.73 .J38'] as $i => $call) {
            DB::table('library_item')->insert([
                'information_object_id' => 1000 + $i, 'material_type' => 'monograph',
                'classification_scheme' => 'LC', 'call_number' => $call,
            ]);
        }
        $this->assertSame(3, $this->svc->rebuildShelfKeys());
        $this->assertSame(['QA9', 'QA76.73 .J38', 'QA100'], $this->svc->shelfList(10)->pluck('call_number')->all());

        DB::table('library_item')->where('information_object_id', 1001)->update(['call_number' => null]);
        $this->svc->refreshShelfKeyForObject(1001);
        $this->assertSame(2, DB::table('library_item_shelf_key')->count());
    }

    public function test_every_route_requires_auth_and_the_right_acl(): void
    {
        $expected = [
            'index' => 'read', 'lookup' => 'read', 'check' => 'read', 'show' => 'read',
            'store' => 'create', 'update' => 'update', 'destroy' => 'delete', 'import' => 'create',
            'entry-store' => 'create', 'entry-update' => 'update', 'entry-destroy' => 'delete',
        ];
        foreach ($expected as $name => $action) {
            $middleware = Route::getRoutes()->getByName('library.classification-'.$name)->gatherMiddleware();
            $this->assertContains('auth', $middleware, $name);
            $this->assertContains('acl:'.$action, $middleware, $name);
        }
    }

    public function test_http_lookup_check_and_validation(): void
    {
        $this->withoutMiddleware();
        $id = $this->schedule();
        $this->loadDewey($id);

        $this->getJson(route('library.classification-lookup', ['scheme' => 'Dewey']))
            ->assertOk()->assertJsonPath('entries.0.notation', '000')->assertJsonPath('entries.0.children', 1);
        $this->getJson(route('library.classification-check', ['scheme' => 'Dewey', 'number' => '005.133']))
            ->assertOk()->assertJsonPath('status', 'broader')->assertJsonPath('notation', '005.1');
        $this->getJson(route('library.classification-lookup', ['scheme' => 'Dewey', 'parent' => 99999]))->assertNotFound();

        $this->postJson(route('library.classification-store'), ['scheme' => 'Nope', 'name' => 'X', 'validation_mode' => 'warn'])
            ->assertStatus(422)->assertJsonValidationErrors('scheme');
        $this->postJson(route('library.classification-store'), ['scheme' => 'UDC', 'name' => 'U', 'validation_mode' => 'warn', 'is_active' => 1])
            ->assertRedirect();
        $this->assertDatabaseHas('library_classification_schedule', ['scheme' => 'UDC', 'name' => 'U']);

        $file = UploadedFile::fake()->createWithContent('s.csv', "notation,caption\n007,Data\n");
        $this->post(route('library.classification-import', $id), ['file' => $file])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('library_classification_entry', ['schedule_id' => $id, 'notation' => '007']);
    }
}
