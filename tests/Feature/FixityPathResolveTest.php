<?php

namespace Tests\Feature;

use AhgIntegrity\Services\FixityService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The scheduled fixity run resolved digital_object.path (a web FOLDER such as /uploads/r/123/)
 * as an absolute disk path with no file name, so it never found a file on any install.
 */
class FixityPathResolveTest extends TestCase
{
    public function test_path_includes_file_name_and_uploads_root(): void
    {
        $root = sys_get_temp_dir().'/fixity-test-'.getmypid();
        @mkdir($root.'/r/98765', 0777, true);
        file_put_contents($root.'/r/98765/master_test-page.jpg', 'x');
        config(['heratio.uploads_path' => $root]);
        DB::beginTransaction();
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitDigitalObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('digital_object')->insert(['id' => $id, 'name' => 'master_test-page.jpg', 'path' => '/uploads/r/98765/']);
        $do = (object) ['id' => $id, 'name' => 'master_test-page.jpg'];
        $path = app(FixityService::class)->getDigitalObjectPath((int) $do->id);

        $this->assertNotNull($path);
        $this->assertStringEndsWith('/'.$do->name, $path);
        $this->assertStringStartsNotWith('/uploads/', $path);
        $this->assertStringStartsWith(rtrim((string) config('heratio.uploads_path'), '/'), $path);
        $this->assertStringContainsString('/r/98765/', $path);
        DB::rollBack();
        @unlink($root.'/r/98765/master_test-page.jpg'); @rmdir($root.'/r/98765'); @rmdir($root.'/r'); @rmdir($root);
    }
}
