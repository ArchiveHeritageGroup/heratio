<?php

namespace Tests\Feature;

use AhgCore\Support\StatusRow;
use AhgPortableExport\Console\Commands\BundleWorkerCommand;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Portable export packages follow the same disclosure rules as the public site. */
class PortableExportDisclosureTest extends TestCase
{
    use DatabaseTransactions;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/portable-disclosure-'.Str::random(10);
        mkdir($this->dir, 0775, true);
        DB::table('ahg_settings')->where('setting_key', 'portable_export_enabled')->update(['setting_value' => 'true']);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->dir));
        parent::tearDown();
    }

    private function io(): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert(['id' => $id, 'source_culture' => 'en', 'parent_id' => 1, 'lft' => 8000000 + $id * 2, 'rgt' => 8000001 + $id * 2]);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => 'T'.$id]);
        StatusRow::set($id, 158, 160);

        return $id;
    }

    private function embargo(int $id): void
    {
        DB::table('embargo')->insert(['object_id' => $id, 'embargo_type' => 'full', 'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addYear()->toDateString(), 'status' => 'active', 'is_active' => 1]);
    }

    private function classify(int $id): void
    {
        DB::table('object_security_classification')->insert(['object_id' => $id, 'classification_id' => 3, 'assigned_at' => now(), 'active' => 1, 'inherit_to_children' => 0]);
    }

    private function link(array $recordIds, array $objectIds = []): string
    {
        $path = $this->dir.'/export-'.Str::random(8).'.zip';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('data/ios.json', json_encode(array_map(fn ($id) => ['id' => $id, 'title' => 'T'.$id], $recordIds)));
        $zip->addFromString('data/digital_objects.json', json_encode(array_map(fn ($id) => ['object_id' => $id], $objectIds)));
        $zip->close();
        $export = (int) DB::table('portable_export')->insertGetId(['user_id' => (int) DB::table('user')->min('id'), 'title' => 'Demo', 'scope_type' => 'repository', 'status' => 'complete',
            'mode' => 'read_only', 'output_path' => $path, 'destination' => 'zip',
            'disclosure_summary' => json_encode(['included_unpublished' => false]), 'created_at' => now()]);
        $token = bin2hex(random_bytes(16));
        DB::table('portable_export_share_token')->insert(['export_id' => $export, 'token' => $token, 'created_at' => now(), 'updated_at' => now()]);

        return '/portable-export/share/'.$token;
    }

    public function test_the_worker_leaves_out_embargoed_and_classified_records(): void
    {
        [$open, $embargoed, $classified] = [$this->io(), $this->io(), $this->io()];
        $this->embargo($embargoed);
        $this->classify($classified);

        $worker = app(BundleWorkerCommand::class);
        $kept = (new \ReflectionMethod($worker, 'applyDisclosureGates'))->invoke($worker, [$open, $embargoed, $classified], (object) ['id' => 1]);
        $counts = (new \ReflectionProperty($worker, 'excluded'))->getValue($worker);

        $this->assertSame([$open], $kept);
        $this->assertSame(1, $counts['embargo']);
        $this->assertSame(1, $counts['classified']);
    }

    public function test_a_share_link_serves_a_package_of_cleared_records(): void
    {
        $this->get($this->link([$this->io()]))->assertOk();
    }

    public function test_a_share_link_refuses_a_record_restricted_after_the_build(): void
    {
        $id = $this->io();
        $url = $this->link([$id]);
        $this->embargo($id);

        $this->get($url)->assertForbidden();
    }

    public function test_a_share_link_checks_the_digital_object_list_too(): void
    {
        [$open, $restricted] = [$this->io(), $this->io()];
        $url = $this->link([$open], [$restricted]);
        $this->classify($restricted);

        $this->get($url)->assertForbidden();
    }

    public function test_a_share_link_refuses_a_package_it_cannot_read(): void
    {
        $url = $this->link([$this->io()]);
        $export = DB::table('portable_export_share_token')->where('token', basename($url))->value('export_id');
        $path = DB::table('portable_export')->where('id', $export)->value('output_path');
        file_put_contents($path, 'not a zip');

        $this->get($url)->assertForbidden();
    }
}
