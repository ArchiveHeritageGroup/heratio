<?php

/**
 * WizardImportRunsTest - heratio#1555: the Data Migration wizard imports.
 * Before the fix every step checked a disk-relative upload path with
 * file_exists() and found nothing, the map page posted `mapping` while the
 * controller read `mappings`, the job row was written without its file or
 * mapping, and nothing ran queued jobs. This drives upload -> map -> execute
 * and runs the queued job.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgDataMigration\Jobs\RunMigrationImport;
use AhgUserManage\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WizardImportRunsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
        $user = app(UserService::class)->create(['username' => 'dm'.bin2hex(random_bytes(4)), 'email' => 'dm'.bin2hex(random_bytes(4)).'@test.invalid',
            'password' => 'x'.bin2hex(random_bytes(8)), 'authorized_form_of_name' => 'Migration tester', 'active' => 1, 'groups' => []]);
        DB::table('acl_user_group')->insert(['user_id' => $user, 'group_id' => \AhgCore\Models\AclGroup::ADMINISTRATOR_ID]);
        Cache::forget("acl_groups_{$user}");
        $this->actingAs(\AhgCore\Models\User::query()->findOrFail($user));
    }

    public function test_a_csv_goes_from_upload_to_imported_records(): void
    {
        $tag = 'DM'.strtoupper(bin2hex(random_bytes(3)));
        // Semicolons and Windows-1252 (the e-acute), as spreadsheets often export.
        $csv = mb_convert_encoding("Ref;Name;Scope\n{$tag}-1;Café minutes {$tag};First scope\n{$tag}-2;Second file {$tag};Second scope\n", 'Windows-1252', 'UTF-8');

        $this->post('/admin/data-migration/upload', [
            'file' => UploadedFile::fake()->createWithContent('legacy.csv', $csv),
            'target_type' => 'archives',
            'import_type' => 'create',
            'delimiter' => 'auto',
            'encoding' => 'auto',
        ])->assertRedirect(route('data-migration.map'));

        $this->get('/admin/data-migration/map')->assertOk()->assertSee('Ref')->assertSee('Scope');

        $response = $this->post('/admin/data-migration/execute', [
            'mapping' => json_encode(['Ref' => 'identifier', 'Name' => 'title', 'Scope' => 'scope_and_content']),
            'name' => 'Wizard test',
        ]);
        $jobId = (int) DB::table('atom_migration_job')->max('id');
        $response->assertRedirect(route('data-migration.job', $jobId));

        $job = DB::table('atom_migration_job')->find($jobId);
        $this->assertSame('pending', $job->status);
        $this->assertFileExists($job->source_file);
        $this->assertSame('identifier', json_decode($job->mapping_snapshot, true)['Ref']);
        Queue::assertPushed(RunMigrationImport::class, fn ($q) => $q->jobId === $jobId);

        app()->call([new RunMigrationImport($jobId), 'handle']);

        $job = DB::table('atom_migration_job')->find($jobId);
        $this->assertSame('completed', $job->status, (string) $job->progress_message);
        $this->assertSame(2, (int) $job->imported_records);
        $title = DB::table('information_object as io')->join('information_object_i18n as i', 'i.id', '=', 'io.id')
            ->where('io.identifier', "{$tag}-1")->value('i.title');
        $this->assertSame("Café minutes {$tag}", $title, 'encoding converted to UTF-8');

        // A second run does nothing: the job is no longer pending.
        app()->call([new RunMigrationImport($jobId), 'handle']);
        $this->assertSame(1, DB::table('information_object')->where('identifier', "{$tag}-2")->count());
    }

    public function test_the_chosen_spreadsheet_sheet_is_the_one_mapped(): void
    {
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $book->getActiveSheet()->fromArray([['Wrong sheet'], ['x']]);
        $book->createSheet()->fromArray([['Accession no', 'Received'], ['A-1', '2026']]);
        $path = tempnam(sys_get_temp_dir(), 'dmx').'.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);

        $this->post('/admin/data-migration/upload', [
            'file' => new UploadedFile($path, 'accessions.xlsx', null, null, true),
            'target_type' => 'accession',
            'sheet_index' => 1,
        ])->assertRedirect(route('data-migration.map'));
        @unlink($path);

        $this->get('/admin/data-migration/map')->assertOk()->assertSee('Accession no')->assertDontSee('Wrong sheet');
    }

    public function test_unsupported_files_and_sector_targets_are_refused(): void
    {
        $this->post('/admin/data-migration/upload', [
            'file' => UploadedFile::fake()->createWithContent('package.opex', '<opex/>'),
            'target_type' => 'archives',
        ])->assertSessionHasErrors('file');

        $this->post('/admin/data-migration/upload', [
            'file' => UploadedFile::fake()->createWithContent('books.csv', "a\n1\n"),
            'target_type' => 'library',
        ])->assertSessionHasErrors('target_type');
    }

    public function test_preservica_pages_queue_nothing(): void
    {
        $before = DB::table('atom_migration_job')->count();
        $this->post('/admin/data-migration/preservica/import', ['x' => 1])->assertRedirect(route('data-migration.index'));
        $this->post('/admin/data-migration/preservica/export', ['x' => 1])->assertRedirect(route('data-migration.index'));
        $this->assertSame($before, DB::table('atom_migration_job')->count());
    }
}
