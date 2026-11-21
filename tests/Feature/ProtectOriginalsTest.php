<?php

/**
 * ProtectOriginalsTest - heratio#1748 / heratio#1762. With "Protect originals"
 * on, an image master is refused to guests and non-staff until an access
 * request covering the record is approved (and unexpired); the reference copy
 * stays public; staff are unaffected; with the setting off nothing changes.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Services\AclService;
use AhgCore\Services\AhgSettingsService;
use AhgCore\Services\MasterAccess;
use AhgCore\Support\StatusRow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProtectOriginalsTest extends TestCase
{
    use DatabaseTransactions;

    private string $dir;

    private int $parent;

    private int $child;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['digital_object', 'status', 'information_object', 'access_request', 'access_request_scope', 'ahg_settings'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("$t table not present in this install.");
            }
        }
        $this->dir = 'uploads/r/protect-'.uniqid();
        $this->parent = $this->record(1000000, 1000003);
        $this->child = $this->record(1000001, 1000002, $this->parent);
        $master = $this->file($this->child, 'scan.jpg');
        $this->file(null, 'reference_scan.jpg', $master);
        $this->guest();
    }

    protected function tearDown(): void
    {
        AhgSettingsService::clearCache();
        MasterAccess::forget();
        AclService::forgetUser();
        parent::tearDown();
    }

    private function objectRow(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function record(int $lft, int $rgt, ?int $parentId = null): int
    {
        $id = $this->objectRow('QubitInformationObject');
        DB::table('information_object')->insert(['id' => $id, 'source_culture' => 'en', 'parent_id' => $parentId, 'lft' => $lft, 'rgt' => $rgt]);
        StatusRow::set($id, 158, 160);

        return $id;
    }

    private function file(?int $ioId, string $name, ?int $parentId = null): int
    {
        $id = $this->objectRow('QubitDigitalObject');
        DB::table('digital_object')->insert([
            'id' => $id, 'object_id' => $parentId ? null : $ioId, 'parent_id' => $parentId,
            'usage_id' => $parentId ? 141 : 140, 'name' => $name, 'path' => '/'.$this->dir.'/', 'mime_type' => 'image/jpeg',
        ]);

        return $id;
    }

    private function user(?int $group = null): User
    {
        $id = $this->objectRow('QubitUser');
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'protect-'.$id, 'email' => uniqid('protect-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        if ($group) {
            DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => $group]);
        }
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    private function protect(bool $on): void
    {
        DB::table('ahg_settings')->updateOrInsert(['setting_key' => MasterAccess::SETTING], ['setting_value' => $on ? 'true' : 'false', 'setting_group' => 'security']);
        AhgSettingsService::clearCache();
        MasterAccess::forget();
    }

    private function guest(): void
    {
        auth()->forgetGuards();
        AclService::forgetUser();
        MasterAccess::forget();
    }

    private function approve(User $u, int $ioId, bool $descendants, ?string $expires = null): void
    {
        $rid = DB::table('access_request')->insertGetId([
            'request_type' => 'object', 'scope_type' => 'single', 'user_id' => $u->id,
            'requested_classification_id' => (int) (DB::table('security_classification')->min('id') ?? 1),
            'reason' => 'test', 'status' => 'approved', 'expires_at' => $expires,
        ]);
        DB::table('access_request_scope')->insert(['request_id' => $rid, 'object_type' => 'information_object', 'object_id' => $ioId, 'include_descendants' => $descendants ? 1 : 0]);
        MasterAccess::forget();
    }

    private function gate(string $name, ?User $as = null)
    {
        $req = $as ? $this->actingAs($as) : $this;

        return $req->withHeader('X-Original-URI', '/'.$this->dir.'/'.$name)->get(route('io.privacy.file-check'));
    }

    public function test_off_changes_nothing(): void
    {
        $this->protect(false);
        $this->gate('scan.jpg')->assertNoContent();
    }

    public function test_guest_gets_the_preview_only(): void
    {
        $this->protect(true);
        $this->gate('scan.jpg')->assertForbidden();
        $this->gate('reference_scan.jpg')->assertNoContent();
        // Encoded spellings of the protected master are refused too: on the raw
        // URI fallback, and with nginx's own normalised path (X-Original-Path).
        $enc = fn (string $raw, ?string $served = null) => $this->withHeaders(['X-Original-URI' => $raw] + ($served !== null ? ['X-Original-Path' => $served] : []))
            ->get(route('io.privacy.file-check'));
        $enc('/'.str_replace('/', '%2F', $this->dir).'%2Fscan.jpg')->assertForbidden();
        $enc('/'.$this->dir.'/sc%61n.jpg')->assertForbidden();
        $enc('/'.$this->dir.'/x%2F..%2Fscan.jpg')->assertForbidden();
        $enc('/'.$this->dir.'/x%2F..%2Fscan.jpg', '/'.$this->dir.'/scan.jpg')->assertForbidden();
        $enc('/whatever', '/'.$this->dir.'/reference_scan.jpg')->assertNoContent();
        $this->flushHeaders();
        // The IIIF identifier of the same master is refused as well.
        $this->assertTrue(MasterAccess::refusesPath(str_replace('/', '_SL_', $this->dir.'/scan.jpg')));
    }

    public function test_approval_releases_the_original_until_it_expires(): void
    {
        $this->protect(true);
        $u = $this->user();
        $this->gate('scan.jpg', $u)->assertForbidden();

        $this->approve($u, $this->child, false, now()->subDay()->toDateTimeString());
        $this->gate('scan.jpg', $u)->assertForbidden();

        $this->approve($u, $this->parent, false);
        $this->gate('scan.jpg', $u)->assertForbidden();

        $this->approve($u, $this->parent, true);
        $this->gate('scan.jpg', $u)->assertNoContent();
    }

    public function test_staff_are_unaffected(): void
    {
        $this->protect(true);
        $this->gate('scan.jpg', $this->user(100))->assertNoContent();
    }

    public function test_preview_copy_carries_the_watermark(): void
    {
        if (trim((string) shell_exec('command -v composite')) === '' || ! is_file(public_path('images/watermarks/copyright.png'))) {
            $this->markTestSkipped('ImageMagick or the watermark images are missing');
        }
        $root = sys_get_temp_dir().'/ahg-wm-'.uniqid();
        mkdir($root.'/r/1', 0777, true);
        shell_exec('convert -size 480x360 xc:white '.escapeshellarg($root.'/r/1/reference_scan.jpg'));
        config(['heratio.uploads_path' => $root]);
        DB::table('watermark_setting')->updateOrInsert(['setting_key' => 'default_watermark_enabled'], ['setting_value' => '1']);
        DB::table('watermark_setting')->updateOrInsert(['setting_key' => 'default_watermark_type'], ['setting_value' => 'COPYRIGHT']);

        $ref = (object) ['path' => '/uploads/r/1/', 'name' => 'reference_scan.jpg'];
        $url = app(\AhgMediaProcessing\Services\WatermarkService::class)->previewCopyUrl($ref, $this->child);

        $this->assertSame('/uploads/r/1/reference_scan.wm.jpg', $url);
        $this->assertFileExists($root.'/r/1/reference_scan.wm.jpg');
        $this->assertNotSame(md5_file($root.'/r/1/reference_scan.jpg'), md5_file($root.'/r/1/reference_scan.wm.jpg'));
        // The gate judges the watermarked copy as the reference it came from.
        $this->assertSame([$this->child], app(\AhgInformationObjectManage\Services\RedactionRenderService::class)->recordIdsForIdentifier('/'.$this->dir.'/reference_scan.wm.jpg'));
        $this->protect(true);
        $this->gate('reference_scan.jpg')->assertNoContent();

        shell_exec('rm -rf '.escapeshellarg($root));
    }
}
