<?php

/** Import rules for a vendor export: Excel files, source labels and long titles. */

namespace Tests\Feature;

use AhgCore\Models\AclGroup;
use AhgDataMigration\SourceMigration\HierarchyRejected;
use AhgDataMigration\SourceMigration\ReferenceHierarchyEngine;
use AhgDataMigration\SourceMigration\SourceMigrationPreflight;
use AhgDataMigration\SourceMigration\SourceMigrationRunner;
use AhgUserManage\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SourceMigrationImportRulesTest extends TestCase
{
    use DatabaseTransactions;

    private string $tag;

    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tag = 'V'.strtoupper(bin2hex(random_bytes(4)));
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    private function csv(array $rows): string
    {
        $base = tempnam(sys_get_temp_dir(), 'vcalm');
        $file = $base.'.csv';
        array_push($this->files, $base, $file);
        $fh = fopen($file, 'w');
        foreach ($rows as $row) {
            fputcsv($fh, $row);
        }
        fclose($fh);

        return $file;
    }

    private function xlsx(array $rows, ?callable $tweak = null): string
    {
        $base = tempnam(sys_get_temp_dir(), 'vcalm');
        $file = $base.'.xlsx';
        array_push($this->files, $base, $file);
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray($rows);
        if ($tweak) {
            $tweak($book->getActiveSheet());
        }
        (new Xlsx($book))->save($file);

        return $file;
    }

    private function admin(): int
    {
        $id = app(UserService::class)->create(['username' => 'vm'.bin2hex(random_bytes(4)), 'email' => 'vm'.bin2hex(random_bytes(4)).'@test.invalid',
            'password' => 'x'.bin2hex(random_bytes(8)), 'authorized_form_of_name' => 'Verifier', 'active' => 1, 'groups' => []]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => AclGroup::ADMINISTRATOR_ID]);
        Cache::forget("acl_groups_{$id}");

        return $id;
    }

    private function register(int $run): array
    {
        return json_decode(DB::table('ahg_source_migration_run')->where('id', $run)->value('register_json'), true);
    }

    private function ack(int $run): void
    {
        $answers = [];
        foreach ($this->register($run)['open_decisions'] as $d) {
            $answers[$d['key']] = $d['key'] === 'level_piece' ? 'fold_to_item' : 'keep_both';
        }
        (new SourceMigrationRunner)->acknowledge($run, $this->admin(), $answers);
    }

    private function load(string $file): int
    {
        $run = (new SourceMigrationPreflight)->run($file, 'calm');
        $this->assertSame([], $this->register($run)['blockers']);
        $this->ack($run);
        (new SourceMigrationRunner)->commit($run);

        return $run;
    }

    private function ioFor(int $run, string $ref): int
    {
        return (int) DB::table('ahg_source_migration_record')->where('run_id', $run)->where('ref_no', $ref)->value('information_object_id');
    }

    private function tagged(): int
    {
        return DB::table('information_object')->where('identifier', 'like', "GB {$this->tag}%")->count();
    }

    private function codes(callable $fn): array
    {
        try {
            $fn();
        } catch (HierarchyRejected $e) {
            return array_column($e->problems, 'code');
        }

        return [];
    }

    public function test_a_title_the_title_column_can_hold_commits(): void
    {
        $t = $this->tag;
        $title = trim(str_repeat('Correspondence with the district commissioner ', 7));
        $this->assertLessThan(1024, strlen($title));
        $this->assertGreaterThan(255, strlen($title));
        $run = (new SourceMigrationPreflight)->run($this->csv([['RefNo', 'AltRefNo', 'Level', 'Title'], ["GB {$t}", '', 'Fonds', $title]]), 'calm');
        $this->ack($run);
        (new SourceMigrationRunner)->commit($run);
        $this->assertSame(1, $this->tagged());
    }

    public function test_a_lone_closure_date_keeps_its_source_label(): void
    {
        $t = $this->tag;
        $run = $this->load($this->csv([
            ['RefNo', 'AltRefNo', 'Level', 'Title', 'AccessConditions', 'ClosedUntil'],
            ["GB {$t}", '', 'Fonds', 'Papers', 'Open', '2040'],
            ["GB {$t}/1", '', 'Series', 'Closed file', '', '2050'],
        ]));
        $id = $this->ioFor($run, "GB {$t}/1");
        $raw = (string) DB::table('information_object_i18n')->where('id', $id)->value('access_conditions');
        $value = (new \AhgCore\Services\EncryptionService)->decrypt(\AhgCore\Services\EncryptionService::CATEGORY_ACCESS_RESTRICTIONS, $raw, 'information_object_i18n', 'access_conditions', $id);
        $this->assertStringContainsString('ClosedUntil', (string) $value, 'A bare year in access conditions no longer says it is a closure date');
    }

    public function test_detect_recognises_an_excel_file(): void
    {
        $file = $this->xlsx([['RefNo', 'AltRefNo', 'Level', 'Title'], ["GB {$this->tag}", '', 'Fonds', 'Papers']]);
        $code = Artisan::call('ahg:source-migrate', ['action' => 'detect', '--file' => $file]);
        $this->assertSame(0, $code, Artisan::output());
    }

    public function test_preflight_without_mapping_recognises_an_excel_file(): void
    {
        $file = $this->xlsx([['RefNo', 'AltRefNo', 'Level', 'Title'], ["GB {$this->tag}", '', 'Fonds', 'Papers']]);
        $code = Artisan::call('ahg:source-migrate', ['action' => 'preflight', '--file' => $file]);
        $this->assertSame(0, $code, Artisan::output());
    }

    public function test_detect_recognises_a_csv_file(): void
    {
        $file = $this->csv([['RefNo', 'AltRefNo', 'Level', 'Title'], ["GB {$this->tag}", '', 'Fonds', 'Papers']]);
        $this->assertSame(0, Artisan::call('ahg:source-migrate', ['action' => 'detect', '--file' => $file]));
        $this->assertStringContainsString('Axiell Calm', Artisan::output());
    }

    public function test_an_excel_date_cell_imports_as_a_date(): void
    {
        $t = $this->tag;
        $file = $this->xlsx([['RefNo', 'AltRefNo', 'Level', 'Title', 'Date'], ["GB {$t}", '', 'Fonds', 'Papers', 32874]], function ($sheet) {
            $sheet->getStyle('E2')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        });
        $run = $this->load($file);
        $date = DB::table('event')->join('event_i18n', 'event_i18n.id', '=', 'event.id')
            ->where('event.object_id', $this->ioFor($run, "GB {$t}"))->value('event_i18n.date');
        $this->assertSame('1990-01-01', $date);
    }

    public function test_a_lone_value_in_a_targets_first_column_stays_unlabelled(): void
    {
        $t = $this->tag;
        $run = $this->load($this->csv([
            ['RefNo', 'AltRefNo', 'Level', 'Title', 'AccessConditions', 'ClosedUntil'],
            ["GB {$t}", '', 'Fonds', 'Papers', 'Open', '2040'],
            ["GB {$t}/1", '', 'Series', 'Open file', 'By appointment', ''],
        ]));
        $id = $this->ioFor($run, "GB {$t}/1");
        $raw = (string) DB::table('information_object_i18n')->where('id', $id)->value('access_conditions');
        $value = (new \AhgCore\Services\EncryptionService)->decrypt(\AhgCore\Services\EncryptionService::CATEGORY_ACCESS_RESTRICTIONS, $raw, 'information_object_i18n', 'access_conditions', $id);
        $this->assertSame('By appointment', (string) $value);
    }

    public function test_plain_numbers_in_excel_stay_numbers(): void
    {
        $t = $this->tag;
        $run = $this->load($this->xlsx([['RefNo', 'AltRefNo', 'Level', 'Title', 'Extent'], ["GB {$t}", '', 'Fonds', 'Papers', 32874]]));
        $this->assertSame('32874', DB::table('information_object_i18n')->where('id', $this->ioFor($run, "GB {$t}"))->value('extent_and_medium'));
    }

    public function test_a_very_long_title_gets_a_slug_that_fits_and_stays_unique(): void
    {
        $t = $this->tag;
        $title = trim(str_repeat("Correspondence {$t} with the commissioner ", 12));
        $this->assertLessThan(1024, strlen($title));
        $run = $this->load($this->csv([['RefNo', 'AltRefNo', 'Level', 'Title'], ["GB {$t}", '', 'Fonds', $title], ["GB {$t}/1", '', 'Series', $title]]));
        $a = (string) DB::table('slug')->where('object_id', $this->ioFor($run, "GB {$t}"))->value('slug');
        $b = (string) DB::table('slug')->where('object_id', $this->ioFor($run, "GB {$t}/1"))->value('slug');
        $this->assertLessThanOrEqual(255, mb_strlen($a));
        $this->assertLessThanOrEqual(255, mb_strlen($b));
        $this->assertNotSame($a, $b);
    }
}
