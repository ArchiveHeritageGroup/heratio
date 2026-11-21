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

    public function test_nothing_is_written_into_shared_folders(): void
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

        // 21 Nov 2026: Heratio writes nothing into the other system's folders.
        $new = $root.'/archive/r/some-repository/a/b/reference_mine.pdf';
        $this->assertNotNull(SharedUploadsGuard::refuseWrite($new), 'no new file in a shared folder');
        $this->assertNotNull(SharedUploadsGuard::refuseWrite($root.'/archive/r/some-repository/a/b/theirs.pdf'));
        // Heratio's own numeric folders are fine.
        $own = $root.'/archive/r/12345/reference_mine.pdf';
        $this->assertNull(SharedUploadsGuard::refuseWrite($own));

        config(['database.connections.shared_uploads.database' => $root.'/missing/none.sqlite']);
        DB::purge('shared_uploads');
        $this->assertNotNull(SharedUploadsGuard::refuseWrite($own), 'fails closed when the catalogue cannot be read');
        DB::purge('shared_uploads');
        shell_exec('rm -rf '.escapeshellarg($root));
    }
}
