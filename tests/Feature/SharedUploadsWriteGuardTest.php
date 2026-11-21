<?php

/**
 * SharedUploadsWriteGuardTest - a file Heratio writes itself (a PDF display
 * copy) may go beside a master in a shared repository folder, unless the other
 * catalogue points at that exact file or cannot be read. Moving and deleting
 * keep the stricter numeric-folder rule.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Services\SharedUploadsGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SharedUploadsWriteGuardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_writes_in_shared_folders_follow_the_catalogue_check_only(): void
    {
        $root = sys_get_temp_dir().'/ahg-suw-'.uniqid();
        mkdir($root.'/archive/r/some-repository/a/b', 0777, true);
        config([
            'heratio.uploads_path' => $root.'/archive',
            'database.connections.shared_uploads' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        DB::purge('shared_uploads');
        DB::connection('shared_uploads')->statement('create table digital_object (id integer primary key, path varchar(255), name varchar(255))');
        DB::connection('shared_uploads')->table('digital_object')->insert(['path' => '/uploads/r/some-repository/a/b/', 'name' => 'theirs.pdf']);

        $new = $root.'/archive/r/some-repository/a/b/reference_mine.pdf';
        $this->assertNull(SharedUploadsGuard::refuseWrite($new), 'a new file of our own may be written');
        $this->assertNotNull(SharedUploadsGuard::refuse($new), 'moving or deleting there is still refused');
        $this->assertNotNull(SharedUploadsGuard::refuseWrite($root.'/archive/r/some-repository/a/b/theirs.pdf'), 'a file the other catalogue uses is never written over');

        // Never overwrite a file Heratio did not write, even one nobody references.
        file_put_contents($root.'/archive/r/some-repository/a/b/orphan.pdf', 'x');
        $this->assertNotNull(SharedUploadsGuard::refuseWrite($root.'/archive/r/some-repository/a/b/orphan.pdf'));
        // Never a PSIS derivative name.
        $this->assertNotNull(SharedUploadsGuard::refuseWrite($root.'/archive/r/some-repository/a/b/scan_141.jpg'));
        $this->assertNotNull(SharedUploadsGuard::refuseWrite($root.'/archive/r/some-repository/a/b/scan_142.png'));
        // A file Heratio wrote and recorded may be written again.
        file_put_contents($new, 'pdf');
        $this->assertNotNull(SharedUploadsGuard::refuseWrite($new), 'not yet recorded');
        SharedUploadsGuard::recordWrite($new, 123, 'office_pdf');
        $this->assertNull(SharedUploadsGuard::refuseWrite($new), 'recorded: Heratio may refresh its own file');
        $this->assertSame(1, DB::table('ahg_shared_upload_write')->where('name', 'reference_mine.pdf')->count());

        config(['database.connections.shared_uploads.database' => $root.'/missing/none.sqlite']);
        DB::purge('shared_uploads');
        $this->assertNotNull(SharedUploadsGuard::refuseWrite($new), 'fails closed when the catalogue cannot be read');
        DB::purge('shared_uploads');
        shell_exec('rm -rf '.escapeshellarg($root));
    }
}
