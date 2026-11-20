<?php

/**
 * RecordsLifecycleTest - heratio#1734 / #1735 / #1759 / #1760.
 *  - a deleted digital object goes to the trash, is restored with its files,
 *    and is purged only on request or after its retention;
 *  - replacing a master keeps the old file as a numbered version, outside the
 *    web-served uploads folder, and an old version can be restored;
 *  - a declared record cannot be edited or have its files deleted;
 *  - an inactive declared record moves to the archive tier (not under a hold);
 *  - the fixity sweep tells administrators about a changed file.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Services\AclService;
use AhgCore\Services\AhgSettingsService;
use AhgCore\Services\DigitalObjectService;
use AhgCore\Services\DigitalObjectTrash;
use AhgCore\Services\FileVersionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RecordsLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    private string $root;

    private int $io;

    private string $slug;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('ahg_trash') || ! Schema::hasTable('ahg_file_version')) {
            $this->markTestSkipped('Records lifecycle tables not migrated in this database.');
        }
        $this->root = sys_get_temp_dir().'/ahg-records-'.uniqid();
        mkdir($this->root.'/archive/r/rl', 0777, true);
        config(['heratio.uploads_path' => $this->root.'/archive', 'heratio.storage_path' => $this->root]);
        $this->setting('trash_retention_days', '30');

        $this->io = $this->objectRow('QubitInformationObject');
        DB::table('information_object')->insert(['id' => $this->io, 'source_culture' => 'en', 'lft' => 5000000, 'rgt' => 5000001]);
        $this->slug = 'rl-'.$this->io;
        DB::table('slug')->insert(['object_id' => $this->io, 'slug' => $this->slug]);
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf '.escapeshellarg($this->root));
        AhgSettingsService::clearCache();
        parent::tearDown();
    }

    private function setting(string $key, string $value): void
    {
        DB::table('ahg_settings')->updateOrInsert(['setting_key' => $key], ['setting_value' => $value, 'setting_group' => 'integrity']);
        AhgSettingsService::clearCache();
    }

    private function objectRow(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** A master and its reference copy on disk; returns the master id. */
    private function files(string $content = 'MASTER v1'): int
    {
        file_put_contents($this->root.'/archive/r/rl/scan.tif', $content);
        file_put_contents($this->root.'/archive/r/rl/scan_141.jpg', 'REF');
        $m = $this->objectRow('QubitDigitalObject');
        DB::table('digital_object')->insert(['id' => $m, 'object_id' => $this->io, 'usage_id' => 140, 'name' => 'scan.tif', 'path' => '/uploads/r/rl/', 'mime_type' => 'image/tiff', 'byte_size' => strlen($content)]);
        $r = $this->objectRow('QubitDigitalObject');
        DB::table('digital_object')->insert(['id' => $r, 'parent_id' => $m, 'usage_id' => 141, 'name' => 'scan_141.jpg', 'path' => '/uploads/r/rl/', 'mime_type' => 'image/jpeg']);

        return $m;
    }

    private function admin(): User
    {
        $id = $this->objectRow('QubitUser');
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'rl-'.$id, 'email' => uniqid('rl-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");
        AclService::forgetUser();

        return User::findOrFail($id);
    }

    public function test_a_deleted_object_goes_to_the_trash_and_comes_back(): void
    {
        $m = $this->files();
        $this->assertTrue(DigitalObjectService::delete($m));

        $this->assertFileDoesNotExist($this->root.'/archive/r/rl/scan.tif');
        $this->assertSame(0, DB::table('digital_object')->where('id', $m)->count());
        $t = DB::table('ahg_trash')->where('digital_object_id', $m)->first();
        $this->assertSame('trashed', $t->status);
        $this->assertCount(2, json_decode($t->files_json, true));
        $this->assertStringStartsWith($this->root.'/archive/.trash/', json_decode($t->files_json, true)[0]['to']);

        $this->assertTrue(DigitalObjectTrash::restore((int) $t->id));
        $this->assertSame('MASTER v1', file_get_contents($this->root.'/archive/r/rl/scan.tif'));
        $this->assertSame(2, DB::table('digital_object')->whereIn('id', [$m, $m + 1])->orWhere('parent_id', $m)->count());
        $this->assertSame('restored', DB::table('ahg_trash')->where('id', $t->id)->value('status'));

        // Deleted again and past its retention: the purge removes the files.
        DigitalObjectService::delete($m);
        $t2 = DB::table('ahg_trash')->where('digital_object_id', $m)->where('status', 'trashed')->first();
        DB::table('ahg_trash')->where('id', $t2->id)->update(['purge_after' => now()->subMinute()]);
        $this->assertSame(1, DigitalObjectTrash::purgeExpired());
        $this->assertFileDoesNotExist(json_decode($t2->files_json, true)[0]['to']);
    }

    public function test_with_no_retention_a_delete_is_immediate_as_before(): void
    {
        $this->setting('trash_retention_days', '0');
        $m = $this->files();
        $this->assertTrue(DigitalObjectService::delete($m));
        $this->assertFileDoesNotExist($this->root.'/archive/r/rl/scan.tif');
        $this->assertSame(0, DB::table('ahg_trash')->where('digital_object_id', $m)->count());
    }

    /** heratio#1734: a folder shared with another system's catalogue (PSIS on heratio.org). */
    public function test_a_shared_uploads_folder_is_only_touched_where_heratio_alone_uses_the_file(): void
    {
        config([
            'heratio.trash_path' => $this->root.'/trash',
            'database.connections.shared_uploads' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        DB::purge('shared_uploads');
        DB::connection('shared_uploads')->statement('create table digital_object (id integer primary key, path varchar(255), name varchar(255))');

        // A repository-slug folder is the other system's: refused, file stays.
        $m = $this->files();
        $this->assertFalse(DigitalObjectService::delete($m));
        $this->assertFileExists($this->root.'/archive/r/rl/scan.tif');
        $this->assertSame(1, DB::table('digital_object')->where('id', $m)->count());

        // A Heratio numeric folder nobody else uses: trashed, outside the uploads folder.
        mkdir($this->root.'/archive/r/'.$this->io, 0777, true);
        DB::table('digital_object')->whereIn('id', [$m, $m + 1])->orWhere('parent_id', $m)->update(['path' => '/uploads/r/'.$this->io.'/']);
        rename($this->root.'/archive/r/rl/scan.tif', $this->root.'/archive/r/'.$this->io.'/scan.tif');
        rename($this->root.'/archive/r/rl/scan_141.jpg', $this->root.'/archive/r/'.$this->io.'/scan_141.jpg');
        $this->assertTrue(DigitalObjectService::delete($m));
        $t = DB::table('ahg_trash')->where('digital_object_id', $m)->first();
        $this->assertStringStartsWith($this->root.'/trash/', json_decode($t->files_json, true)[0]['to']);

        // The other system points at it again: the purge leaves it alone.
        DB::connection('shared_uploads')->table('digital_object')->insert(['path' => '/uploads/r/'.$this->io.'/', 'name' => 'scan.tif']);
        DB::table('ahg_trash')->where('id', $t->id)->update(['purge_after' => now()->subMinute()]);
        $this->assertSame(0, DigitalObjectTrash::purgeExpired());
        $this->assertFileExists(json_decode($t->files_json, true)[0]['to']);

        // The other catalogue cannot be read: fail closed.
        config(['database.connections.shared_uploads.database' => $this->root.'/missing/none.sqlite']);
        DB::purge('shared_uploads');
        $this->assertNotNull(\AhgCore\Services\SharedUploadsGuard::refuse($this->root.'/archive/r/'.$this->io.'/other.tif'));
        DB::purge('shared_uploads');
    }

    public function test_replacing_a_master_keeps_numbered_versions_outside_the_uploads_folder(): void
    {
        $m = $this->files('MASTER v1');
        $master = DB::table('digital_object')->where('id', $m)->first();
        $path = $this->root.'/archive/r/rl/scan.tif';
        $new = $this->root.'/new.tif';

        file_put_contents($new, 'MASTER v2');
        $v = FileVersionService::replace($master, $path, $new);
        $this->assertSame([1, 1], [(int) $v->version_major, (int) $v->version_minor]);
        file_put_contents($new, 'MASTER v3');
        $v = FileVersionService::replace($master, $path, $new, true, 'Rescanned');
        $this->assertSame([2, 0], [(int) $v->version_major, (int) $v->version_minor]);
        $this->assertSame('MASTER v3', file_get_contents($path));

        $all = FileVersionService::list($m);
        $this->assertSame(['2.0', '1.1', '1.0'], $all->map(fn ($r) => $r->version_major.'.'.$r->version_minor)->all());
        $original = $all->first(fn ($r) => $r->version_major == 1 && $r->version_minor == 0);
        $this->assertStringStartsWith($this->root.'/versions/'.$m.'/', $original->stored_path);
        $this->assertStringNotContainsString('/archive/', $original->stored_path, 'kept copies are not under the served uploads folder');
        $this->assertSame('MASTER v1', file_get_contents($original->stored_path));

        $back = FileVersionService::restore((int) $original->id);
        $this->assertSame([2, 1], [(int) $back->version_major, (int) $back->version_minor]);
        $this->assertSame('MASTER v1', file_get_contents($path));
    }

    public function test_the_edit_page_replace_now_writes_the_file(): void
    {
        $m = $this->files('MASTER v1');
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('scan.tif', 'MASTER via form');

        $this->actingAs($this->admin())->put(route('io.digitalobject.update', $m), ['replace_file' => $file, 'version_type' => 'major', 'version_note' => 'From the form']);

        $this->assertSame('MASTER via form', file_get_contents($this->root.'/archive/r/rl/scan.tif'));
        $this->assertSame(2, FileVersionService::list($m)->count());
    }

    public function test_a_declared_record_is_read_only(): void
    {
        $m = $this->files();
        $admin = $this->admin();
        DB::table('record_declaration')->insert(['information_object_id' => $this->io, 'status' => 'declared', 'declared_at' => now()]);

        $this->actingAs($admin)->get(route('informationobject.edit', $this->slug))->assertRedirect('/'.$this->slug)->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('io.digitalobject.delete', $m))->assertRedirect('/'.$this->slug);
        $this->assertSame(1, DB::table('digital_object')->where('id', $m)->count(), 'the file was not deleted');
        $this->actingAs($admin)->deleteJson(route('informationobject.destroy', $this->slug))->assertStatus(423);
    }

    public function test_an_inactive_declared_record_moves_to_the_archive_tier_unless_held(): void
    {
        $this->setting('records_archive_after_days', '30');
        DB::table('object')->where('id', $this->io)->update(['updated_at' => now()->subDays(60)]);
        $d = DB::table('record_declaration')->insertGetId(['information_object_id' => $this->io, 'status' => 'declared', 'declared_at' => now()->subDays(60)]);

        $held = $this->objectRow('QubitInformationObject');
        DB::table('information_object')->insert(['id' => $held, 'source_culture' => 'en', 'lft' => 5000002, 'rgt' => 5000003]);
        DB::table('object')->where('id', $held)->update(['updated_at' => now()->subDays(60)]);
        $dh = DB::table('record_declaration')->insertGetId(['information_object_id' => $held, 'status' => 'declared', 'declared_at' => now()->subDays(60)]);
        DB::table('integrity_legal_hold')->insert(['information_object_id' => $held, 'reason' => 'test', 'placed_by' => 1, 'status' => 'active']);

        Artisan::call('ahg:records-archive');

        $this->assertSame('archived', DB::table('record_declaration')->where('id', $d)->value('status'));
        $this->assertNotNull(DB::table('record_declaration')->where('id', $d)->value('archived_at'));
        $this->assertSame('declared', DB::table('record_declaration')->where('id', $dh)->value('status'));
        $this->assertTrue(\AhgCore\Http\Middleware\DeclaredRecordLock::isDeclared($this->io), 'archived stays read-only');
    }

    public function test_the_fixity_sweep_tells_administrators_about_a_changed_file(): void
    {
        $m = $this->files('MASTER v1');
        $admin = $this->admin();
        DB::table('preservation_checksum')->insert(['digital_object_id' => $m, 'algorithm' => 'sha256', 'checksum_value' => hash('sha256', 'what it was'), 'generated_at' => now()->subYear()]);

        Artisan::call('ahg:preservation-fixity', ['--age' => 30, '--limit' => 500, '--notify' => true]);

        $n = DB::table('ahg_notification')->where('user_id', $admin->id)->where('type', 'preservation_fixity')->first();
        $this->assertNotNull($n);
        $this->assertStringContainsString('scan.tif', (string) $n->message);
    }
}
