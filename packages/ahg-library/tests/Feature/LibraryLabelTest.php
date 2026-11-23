<?php

/**
 * LibraryLabelTest - label geometry, Code 128 encoding, batch resolution and
 * the print page (#1476 item 2).
 *
 * @license AGPL-3.0-or-later
 */

namespace AhgLibrary\Tests\Feature;

use AhgLibrary\Services\LibraryLabelService;
use AhgCore\Models\AclGroup;
use AhgLibrary\Support\Code128;
use AhgLibrary\Support\LibraryBranch;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class LibraryLabelTest extends LibraryFeatureTestCase
{
    protected array $migrationFiles = [
        '2026_06_02_000101_create_library_circulation_tables.php',
        '2026_06_02_000102_create_library_patron_category_table.php',
        '2026_06_02_000103_create_library_notice_tables.php',
        '2026_06_02_000104_create_library_ill_request_tables.php',
    ];

    private LibraryLabelService $labels;

    private int $itemId;

    protected function setUp(): void
    {
        parent::setUp();
        LibraryBranch::forgetSchemaCache();

        if (! Schema::hasTable('library_item')) {
            $this->markTestSkipped('library_item not built by the harness');
        }
        foreach (['dewey_decimal', 'classification_number', 'cutter_number', 'volume_designation'] as $column) {
            if (! Schema::hasColumn('library_item', $column)) {
                Schema::table('library_item', fn (Blueprint $t) => $t->string($column)->nullable());
            }
        }
        foreach (['accession_number', 'call_number_suffix'] as $column) {
            if (! Schema::hasColumn('library_copy', $column)) {
                Schema::table('library_copy', fn (Blueprint $t) => $t->string($column)->nullable());
            }
        }
        if (! Schema::hasColumn('library_copy', 'branch_id')) {
            Schema::table('library_copy', fn (Blueprint $t) => $t->integer('branch_id')->nullable());
        }
        if (! Schema::hasTable('information_object_i18n')) {
            Schema::create('information_object_i18n', function (Blueprint $t) {
                $t->integer('id');
                $t->string('culture', 16);
                $t->string('title')->nullable();
            });
        }
        Schema::dropIfExists('ahg_dropdown');
        Schema::create('ahg_dropdown', function (Blueprint $t) {
            $t->id();
            $t->string('taxonomy');
            $t->string('taxonomy_label')->nullable();
            $t->string('taxonomy_section')->nullable();
            $t->string('code');
            $t->string('label');
            $t->integer('sort_order')->default(0);
            $t->boolean('is_default')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['taxonomy', 'code']);
        });

        (require dirname(__DIR__, 2).'/database/migrations/2026_11_22_100000_create_library_label_template.php')->up();

        $this->labels = new LibraryLabelService();
        $this->itemId = DB::table('library_item')->insertGetId([
            'information_object_id' => 7, 'material_type' => 'monograph',
            'call_number' => '025.4 SMI', 'created_at' => now(),
        ]);
        DB::table('information_object_i18n')->insert(['id' => 7, 'culture' => 'en', 'title' => 'Cataloguing <b>Basics</b>']);
    }

    protected function tearDown(): void
    {
        // The branch schema answer is cached per connection name across tests.
        LibraryBranch::forgetSchemaCache();
        parent::tearDown();
    }

    private function copy(string $barcode, ?string $accession = null, int $number = 1, ?int $branch = null): int
    {
        return DB::table('library_copy')->insertGetId([
            'library_item_id' => $this->itemId, 'copy_number' => $number, 'barcode' => $barcode,
            'accession_number' => $accession, 'branch_id' => $branch, 'status' => 'available', 'created_at' => now(),
        ]);
    }

    private function template(string $code): object
    {
        return DB::table('library_label_template')->where('code', $code)->first();
    }

    public function test_code128_known_check_digits_and_width(): void
    {
        // Set B values P=48 J=42 J=42 1=17 2=18 3=19 C=35, weighted by position:
        // (104 + 48 + 84 + 126 + 68 + 90 + 114 + 245) mod 103 = 55.
        $b = Code128::symbols('PJJ123C');
        $this->assertSame(104, $b[0]);
        $this->assertSame(55, $b[count($b) - 2]);
        $this->assertSame(106, $b[count($b) - 1]);

        $c = Code128::symbols('123456');
        $this->assertSame([105, 12, 34, 56], array_slice($c, 0, 4));
        $this->assertSame((105 + 12 + 68 + 168) % 103, $c[4]);
        $this->assertSame(5 * 11 + 13 + 20, Code128::modules('123456'));
        $this->assertFalse(Code128::encodable("caf\u{00E9}"));
    }

    public function test_every_builtin_template_fits_its_page(): void
    {
        $rows = DB::table('library_label_template')->where('is_builtin', 1)->get();
        $this->assertCount(5, $rows);
        foreach ($rows as $t) {
            $this->assertSame([], $this->labels->geometryErrors((array) $t), $t->code);
        }
    }

    public function test_geometry_rejects_overflow_and_overlap(): void
    {
        $t = (array) $this->template('a4_21_63x38');
        $this->assertArrayHasKey('columns_across', $this->labels->geometryErrors(['columns_across' => 4] + $t));
        $this->assertArrayHasKey('rows_down', $this->labels->geometryErrors(['rows_down' => 8] + $t));
        $this->assertArrayHasKey('pitch_x_mm', $this->labels->geometryErrors(['pitch_x_mm' => 60] + $t));
    }

    public function test_skip_and_pitch_place_labels_exactly_and_overflow_to_next_sheet(): void
    {
        $t = $this->template('a4_21_63x38');
        for ($i = 1; $i <= 3; $i++) {
            $this->copy('B000'.$i);
        }
        $copies = $this->labels->resolveCopies(['B0001', 'B0002', 'B0003'], null)['copies'];
        $out = $this->labels->sheets($copies, 'barcode', $t, 19, 0.5, -0.5);

        $this->assertSame(3, $out['count']);
        $this->assertCount(2, $out['pages']);
        // Slot 19 = row 6, column 1.
        $this->assertEqualsWithDelta(7.25 + 66 + 0.5, $out['pages'][0][0]['x'], 0.001);
        $this->assertEqualsWithDelta(15.15 + 6 * 38.1 - 0.5, $out['pages'][0][0]['y'], 0.001);
        // Slot 21 wraps to the top left of sheet two.
        $this->assertEqualsWithDelta(7.75, $out['pages'][1][0]['x'], 0.001);
        $this->assertEqualsWithDelta(14.65, $out['pages'][1][0]['y'], 0.001);
    }

    public function test_labels_that_would_not_scan_or_read_are_refused(): void
    {
        $narrow = $this->template('a4_65_38x21');
        $this->copy('LONG-BARCODE-VALUE-0001');
        $copy = $this->labels->resolveCopies(['LONG-BARCODE-VALUE-0001'], null)['copies'][0];
        [$label, $reason] = $this->labels->buildLabel($copy, 'barcode', $narrow);
        $this->assertNull($label);
        $this->assertNotNull($reason);

        $copy->call_number = '';
        [$label, $reason] = $this->labels->buildLabel($copy, 'spine', $narrow);
        $this->assertNull($label);
        $this->assertNotNull($reason);
    }

    public function test_spine_lines_carry_volume_and_copy(): void
    {
        $lines = $this->labels->spineLines((object) [
            'call_number' => '025.4 SMI', 'call_number_suffix' => 'REF', 'volume_designation' => 'v.2',
            'copy_number' => 3, 'dewey_decimal' => null, 'classification_number' => null, 'cutter_number' => null,
        ]);
        $this->assertSame(['025.4', 'SMI', 'REF', 'v.2', 'c.3'], $lines);
    }

    public function test_whole_accession_prefix_is_literal_and_missing_are_reported(): void
    {
        $this->copy('A1', '2026/014-1');
        $this->copy('A2', '2026/014-2', 2);
        $this->copy('A3', '2026/0145');
        $this->copy('A4', '2026_014-9');

        $prefix = $this->labels->resolveCopies([], '2026/014-*');
        $this->assertSame(['A1', 'A2'], array_column($prefix['copies'], 'barcode'));

        $wild = $this->labels->resolveCopies([], '2026_014-*');
        $this->assertSame(['A4'], array_column($wild['copies'], 'barcode'));

        $mixed = $this->labels->resolveCopies(['A3', 'NOPE'], null);
        $this->assertSame(['A3'], array_column($mixed['copies'], 'barcode'));
        $this->assertSame(['NOPE'], $mixed['not_found']);
    }

    public function test_operator_held_to_a_branch_gets_only_that_branch(): void
    {
        if (! Schema::hasColumn('library_loan_rule', 'branch_id')) {
            Schema::table('library_loan_rule', fn (Blueprint $t) => $t->integer('branch_id')->default(0));
        }
        LibraryBranch::forgetSchemaCache();
        session()->put(LibraryBranch::SESSION_KEY, 5);

        $this->copy('MINE', 'BR-1', 1, 5);
        $this->copy('THEIRS', 'BR-2', 2, 6);
        $this->copy('UNATTRIBUTED', 'BR-3', 3, null);

        $out = $this->labels->resolveCopies(['MINE', 'THEIRS', 'GONE'], 'BR-*');
        $this->assertSame(['MINE'], array_column($out['copies'], 'barcode'));
        $this->assertSame(1, $out['other_branch']);
        $this->assertSame(1, $out['no_branch']);
        $this->assertSame(['GONE'], $out['not_found']);
    }

    public function test_not_found_follows_the_column_collation(): void
    {
        // The harness is SQLite, so NOCASE stands in for MySQL's unicode_ci columns.
        Schema::drop('library_copy');
        DB::statement('CREATE TABLE library_copy (id INTEGER PRIMARY KEY, library_item_id INTEGER, copy_number INTEGER, barcode TEXT COLLATE NOCASE, accession_number TEXT COLLATE NOCASE, call_number_suffix TEXT, branch_id INTEGER, status TEXT, created_at TEXT)');
        $this->copy('ABC123', 'ACC-9');

        $out = $this->labels->resolveCopies(['abc123', 'acc-9', 'NOPE'], null);

        $this->assertSame(['ABC123'], array_column($out['copies'], 'barcode'));
        $this->assertSame(['NOPE'], $out['not_found']);
    }

    public function test_repeated_bare_wildcard_selects_nothing(): void
    {
        $this->copy('W1', 'W-1');

        foreach (['*', '**', ' *** ', '* *'] as $value) {
            $this->assertTrue(LibraryLabelService::isBareWildcard($value), $value);
            $this->assertSame([], $this->labels->resolveCopies([], $value)['copies'], $value);
        }
        $this->assertFalse(LibraryLabelService::isBareWildcard('W-*'));
        $this->assertCount(1, $this->labels->resolveCopies([], 'W-*')['copies']);
    }

    public function test_shift_that_pushes_labels_off_the_page_is_refused(): void
    {
        $t = (object) [
            'page_width_mm' => 210, 'page_height_mm' => 297, 'margin_left_mm' => 4.65, 'margin_top_mm' => 15,
            'label_width_mm' => 99.1, 'label_height_mm' => 38.1, 'pitch_x_mm' => 101.6, 'pitch_y_mm' => 38.1,
            'columns_across' => 2, 'rows_down' => 7,
        ];

        $this->assertSame([], $this->labels->nudgeErrors($t, 4.65, 0));
        $this->assertSame([], $this->labels->nudgeErrors($t, -4.65, -15));
        $this->assertStringContainsString('5.35', $this->labels->nudgeErrors($t, 10, 0)['nudge_x']);
        $this->assertArrayHasKey('nudge_x', $this->labels->nudgeErrors($t, -5, 0));
        $this->assertArrayHasKey('nudge_y', $this->labels->nudgeErrors($t, 0, -16));
        $this->assertArrayHasKey('nudge_y', $this->labels->nudgeErrors($t, 0, 16));
    }

    public function test_preview_form_refuses_repeated_wildcard_and_off_page_shift(): void
    {
        $this->copy('F1', 'F-1');
        $this->withoutMiddleware();
        $base = ['template_id' => $this->template('a4_21_63x38')->id, 'format' => 'barcode'];

        $this->post('/library-manage/labels/preview', $base + ['accession' => '**'])
            ->assertSessionHasErrors('accession');
        $this->post('/library-manage/labels/preview', $base + ['identifiers' => 'F1', 'nudge_x' => 10])
            ->assertSessionHasErrors('nudge_x');
        $this->post('/library-manage/labels/preview', $base + ['identifiers' => 'F1', 'nudge_y' => -16])
            ->assertSessionHasErrors('nudge_y');
    }

    public function test_routes_are_staff_only(): void
    {
        foreach (['index', 'preview', 'templates', 'templates.create', 'templates.store', 'templates.edit', 'templates.update', 'templates.delete'] as $name) {
            $middleware = Route::getRoutes()->getByName('library.labels.'.$name)->gatherMiddleware();
            $this->assertContains('auth', $middleware, $name);
            $this->assertContains('acl:update', $middleware, $name);
        }
        $this->post('/library-manage/labels/preview')->assertRedirect();
    }

    public function test_print_page_renders_escaped_true_scale_sheet(): void
    {
        $this->copy('31234567', 'ACC-1');
        $template = $this->template('a4_21_63x38');
        Schema::create('acl_user_group', function (Blueprint $t) {
            $t->integer('user_id');
            $t->integer('group_id');
        });
        Schema::create('acl_permission', function (Blueprint $t) {
            $t->id();
            $t->integer('user_id')->nullable();
            $t->integer('group_id')->nullable();
            $t->integer('object_id')->nullable();
            $t->string('action')->nullable();
            $t->boolean('grant_deny')->default(true);
            $t->string('constants')->nullable();
        });
        DB::table('acl_user_group')->insert(['user_id' => 9001, 'group_id' => AclGroup::ADMINISTRATOR_ID]);
        $payload = [
            'template_id' => $template->id, 'format' => 'combined', 'identifiers' => "31234567\nMISSING",
        ];
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);

        $reader = new User();
        $reader->id = 9002;
        // Asserted on the exception: the themed 403 page needs tables this harness lacks.
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($reader)->post('/library-manage/labels/preview', $payload);
            $this->fail('A user without update rights reached the print page');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $admin = new User();
        $admin->id = 9001;
        $response = $this->actingAs($admin)->post('/library-manage/labels/preview', $payload);

        if ($response->exception) {
            throw $response->exception;
        }
        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('size: 210mm 297mm', $html);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('MISSING', $html);
        $this->assertStringContainsString('025.4', $html);
    }

    public function test_builtin_templates_cannot_be_changed_or_deleted(): void
    {
        $t = $this->template('a4_21_63x38');
        $this->assertFalse($this->labels->deleteTemplate((int) $t->id));
        $this->labels->saveTemplate(['name' => 'Changed'] + (array) $t, (int) $t->id, 1);
        $this->assertSame($t->name, $this->template('a4_21_63x38')->name);

        $custom = $this->labels->saveTemplate(['name' => 'Mine'] + (array) $t, null, 1);
        $this->assertTrue($this->labels->deleteTemplate($custom));
    }
}
