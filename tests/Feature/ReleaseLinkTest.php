<?php

/**
 * ReleaseLinkTest - heratio#1775. Paying an order or approving an object
 * access request issues a download link for the record's original; the link
 * works only for the user it was issued to (or staff), and not once expired
 * or revoked.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Services\AclService;
use AhgCore\Services\ReleaseLinkService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReleaseLinkTest extends TestCase
{
    use DatabaseTransactions;

    private string $root;

    private int $record;

    protected function setUp(): void
    {
        parent::setUp();
        if (! ReleaseLinkService::available() || ! \Illuminate\Support\Facades\Schema::hasTable('ahg_order_item')) {
            $this->markTestSkipped('Release-link columns not migrated in this database.');
        }
        AclService::forgetUser();
        $this->root = sys_get_temp_dir().'/ahg-release-'.uniqid();
        mkdir($this->root.'/r/x', 0777, true);
        file_put_contents($this->root.'/r/x/scan.tif', 'ORIGINAL');
        config(['heratio.uploads_path' => $this->root]);

        $this->record = $this->objectRow('QubitInformationObject');
        DB::table('information_object')->insert(['id' => $this->record, 'source_culture' => 'en']);
        $do = $this->objectRow('QubitDigitalObject');
        DB::table('digital_object')->insert(['id' => $do, 'object_id' => $this->record, 'usage_id' => 140, 'name' => 'scan.tif', 'path' => '/uploads/r/x/', 'mime_type' => 'image/tiff']);
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf '.escapeshellarg($this->root));
        parent::tearDown();
    }

    private function objectRow(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function user(?int $group = null): User
    {
        $id = $this->objectRow('QubitUser');
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'rel-'.$id, 'email' => uniqid('rel-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        if ($group) {
            DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => $group]);
        }
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    public function test_paid_order_releases_the_original_to_the_buyer_only(): void
    {
        $buyer = $this->user();
        $order = DB::table('ahg_order')->insertGetId(['order_number' => 'T-'.uniqid(), 'user_id' => $buyer->id, 'status' => 'paid', 'total' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $item = DB::table('ahg_order_item')->insertGetId(['order_id' => $order, 'archival_description_id' => $this->record, 'unit_price' => 0, 'line_total' => 0]);

        $this->assertSame(1, ReleaseLinkService::issueForOrder($order));
        $url = DB::table('ahg_order_item')->where('id', $item)->value('download_url');
        $this->assertNotEmpty($url);

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($this->user())->get($url)->assertForbidden();
        AclService::forgetUser();
        $res = $this->actingAs($buyer)->get($url);
        $res->assertOk();
        $this->assertSame('ORIGINAL', file_get_contents($res->baseResponse->getFile()->getPathname()));
        $this->assertSame(1, (int) DB::table('ahg_order_item')->where('id', $item)->value('download_count'));
    }

    public function test_approval_issues_a_link_and_expired_or_revoked_links_fail(): void
    {
        $u = $this->user();
        $rid = DB::table('access_request')->insertGetId([
            'request_type' => 'object', 'scope_type' => 'single', 'user_id' => $u->id,
            'requested_classification_id' => (int) (DB::table('security_classification')->min('id') ?? 1),
            'reason' => 'test', 'status' => 'pending',
        ]);
        DB::table('access_request_scope')->insert(['request_id' => $rid, 'object_type' => 'information_object', 'object_id' => $this->record]);

        app(\AhgAccessRequest\Services\AccessRequestService::class)->approveRequest($rid, (int) $this->user(100)->id);
        $token = ReleaseLinkService::forAccessRequests((int) $u->id)[$rid] ?? null;
        $this->assertNotNull($token);
        $this->actingAs($u)->get(route('cart.download', $token))->assertOk();

        DB::table('ahg_cart_downloads')->where('token', $token)->update(['expires_at' => now()->subMinute()]);
        $this->actingAs($u)->get(route('cart.download', $token))->assertNotFound();
        DB::table('ahg_cart_downloads')->where('token', $token)->update(['expires_at' => now()->addDay(), 'revoked_at' => now()]);
        $this->actingAs($u)->get(route('cart.download', $token))->assertNotFound();
    }
}
