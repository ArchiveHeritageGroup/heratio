<?php

/**
 * RequestBasketWorkflowTest - heratio#1768 / #1773 / #1774.
 *  - the basket is submitted as ONE access request naming every record;
 *  - an active access-request workflow routes it step by step, and its last
 *    approval releases the records; every step is in the request's history;
 *  - staff may release with conditions (its own status);
 *  - a quotation releases nothing until the order that pays it is paid.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgAccessRequest\Services\AccessRequestService;
use AhgCore\Models\User;
use AhgCore\Services\AclService;
use AhgCore\Services\MasterAccess;
use AhgCore\Services\ReleaseLinkService;
use AhgWorkflow\Services\WorkflowService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RequestBasketWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    private string $root;

    /** @var list<int> */
    private array $records = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasColumn('access_request', 'quote_amount') || ! ReleaseLinkService::available()) {
            $this->markTestSkipped('Request quotation columns not migrated in this database.');
        }
        Mail::fake();
        AclService::forgetUser();
        MasterAccess::forget();
        $this->root = sys_get_temp_dir().'/ahg-basket-'.uniqid();
        mkdir($this->root.'/r/b', 0777, true);
        config(['heratio.uploads_path' => $this->root]);
        DB::table('ahg_ecommerce_settings')->where('id', 1)->update(['is_enabled' => 0]);
        DB::table('ahg_workflow')->where('applies_to', 'access_request')->update(['is_active' => 0]);

        foreach (['one', 'two'] as $i => $name) {
            file_put_contents($this->root."/r/b/$name.tif", 'ORIGINAL '.$name);
            $io = $this->objectRow('QubitInformationObject');
            DB::table('information_object')->insert(['id' => $io, 'source_culture' => 'en', 'lft' => 2000000 + $i * 2, 'rgt' => 2000001 + $i * 2]);
            $do = $this->objectRow('QubitDigitalObject');
            DB::table('digital_object')->insert(['id' => $do, 'object_id' => $io, 'usage_id' => 140, 'name' => "$name.tif", 'path' => '/uploads/r/b/', 'mime_type' => 'image/tiff']);
            $this->records[] = $io;
        }
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf '.escapeshellarg($this->root));
        MasterAccess::forget();
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
        DB::table('user')->insert(['id' => $id, 'username' => 'bsk-'.$id, 'email' => uniqid('bsk-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        if ($group) {
            DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => $group]);
        }
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    private function request(User $u): int
    {
        return app(AccessRequestService::class)->createRequest((int) $u->id, [
            'request_type' => 'object', 'scope_type' => 'basket', 'reason' => 'Publication',
            'items' => array_map(fn ($io) => ['id' => $io, 'title' => 'Record '.$io], $this->records),
        ]);
    }

    /** A guest's basket, held against the session. */
    private function guestBasket(): void
    {
        $this->startSession();
        foreach ($this->records as $io) {
            DB::table('cart')->insert(['session_id' => session()->getId(), 'archival_description_id' => $io, 'archival_description' => 'Record '.$io, 'slug' => 'rec-'.$io, 'created_at' => now()]);
        }
    }

    public function test_the_basket_becomes_one_request_for_every_record(): void
    {
        $u = $this->user();
        $this->guestBasket();
        $form = ['rtp_name' => 'Ann', 'rtp_surname' => 'Reader', 'rtp_email' => 'ann@example.test', 'rtp_planned_use' => 'Publication', 'rtp_institution' => 'Test Press'];

        $this->withCookie(config('session.cookie'), session()->getId());
        $this->post(route('cart.checkout'), $form)->assertRedirect(route('login'));
        $this->assertSame(0, DB::table('access_request')->where('user_id', $u->id)->count(), 'a guest is signed in first');
        session()->regenerate(); // as sign-in does: the guest basket must survive it

        $res = $this->actingAs($u)->post(route('cart.checkout'), $form);

        $req = DB::table('access_request')->where('user_id', $u->id)->first();
        $res->assertRedirect(route('accessRequest.view', $req->id));
        $this->assertSame('basket', $req->scope_type);
        $this->assertStringContainsString('Test Press', (string) $req->justification);
        $this->assertSame($this->records, DB::table('access_request_scope')->where('request_id', $req->id)->orderBy('id')->pluck('object_id')->map('intval')->all());
        $this->assertSame(0, DB::table('cart')->where('user_id', $u->id)->whereNull('completed_at')->count());
        $this->assertSame(1, app(AccessRequestService::class)->getMyRequests((int) $u->id)->total(), 'one row in My requests, not one per record');
    }

    public function test_the_workflow_routes_the_request_and_its_last_approval_releases_it(): void
    {
        $wf = DB::table('ahg_workflow')->where('applies_to', 'access_request')->value('id');
        if (! $wf) {
            $this->markTestSkipped('Access request workflow not seeded.');
        }
        DB::table('ahg_workflow')->where('id', $wf)->update(['is_active' => 1]);
        $u = $this->user();
        $staff = $this->user(100);
        $svc = app(AccessRequestService::class);

        $id = $this->request($u);
        $flow = app(WorkflowService::class);
        foreach (['Check the request', 'Release decision'] as $step) {
            $task = DB::table('ahg_workflow_task')->where('object_type', 'access_request')->where('object_id', $id)->where('status', 'pending')->first();
            $this->assertNotNull($task, "a task waits at '$step'");
            $this->assertSame($step, $svc->getOpenStep($id)->step_name);
            $this->assertSame('pending', DB::table('access_request')->where('id', $id)->value('status'));
            $this->assertTrue($flow->claimTask((int) $task->id, (int) $staff->id));
            $this->assertTrue($flow->approveTask((int) $task->id, (int) $staff->id, 'ok'));
        }

        $this->assertSame('approved', DB::table('access_request')->where('id', $id)->value('status'));
        $this->assertCount(2, ReleaseLinkService::forAccessRequests((int) $u->id)[$id] ?? []);
        $actions = array_map(fn ($h) => $h->action, $svc->getHistory($id));
        foreach (['submitted', 'routed', 'started', 'approved'] as $a) {
            $this->assertContains($a, $actions);
        }
        $this->actingAs($staff)->get(route('accessRequest.view', $id))->assertOk()->assertSee('Release decision');
    }

    public function test_release_with_conditions_is_its_own_status_and_still_releases(): void
    {
        $u = $this->user();
        $id = $this->request($u);

        app(AccessRequestService::class)->approveRequest($id, (int) $this->user(100)->id, null, 'Credit the archive; no commercial use.');

        $row = DB::table('access_request')->where('id', $id)->first();
        $this->assertSame('conditional', $row->status);
        $this->assertSame('Credit the archive; no commercial use.', $row->conditions);
        $this->assertTrue(MasterAccess::releasedTo((int) $u->id, $this->records[1]));
        $this->assertCount(2, ReleaseLinkService::forAccessRequests((int) $u->id)[$id] ?? []);
        $this->actingAs($u)->get(route('accessRequest.view', $id))->assertOk()->assertSee('Credit the archive');
    }

    public function test_a_quotation_releases_only_after_payment(): void
    {
        $u = $this->user();
        $staff = $this->user(100);
        $svc = app(AccessRequestService::class);
        $id = $this->request($u);

        $this->actingAs($staff)->post(route('accessRequest.quote', $id), ['amount' => '250.00', 'quote_note' => 'Two high-resolution scans'])->assertRedirect();
        $this->assertSame('quoted', DB::table('access_request')->where('id', $id)->value('status'));
        $this->assertFalse(MasterAccess::releasedTo((int) $u->id, $this->records[0]), 'not released on quotation');

        AclService::forgetUser();
        $this->actingAs($this->user())->post(route('cart.quote.accept', $id))->assertNotFound();
        AclService::forgetUser();
        $this->actingAs($u)->post(route('cart.quote.accept', $id))->assertRedirect();
        $order = DB::table('ahg_order')->where('access_request_id', $id)->first();
        $this->assertNotNull($order);
        $this->assertSame('250.00', (string) $order->total);
        $this->assertSame(250.0, (float) DB::table('ahg_order_item')->where('order_id', $order->id)->sum('line_total'));
        $this->assertCount(0, ReleaseLinkService::forAccessRequests((int) $u->id)[$id] ?? []);

        // What a verified PayFast ITN does once the order is marked paid.
        DB::table('ahg_order')->where('id', $order->id)->update(['status' => 'paid', 'paid_at' => now()]);
        $this->assertSame($id, $svc->recordPayment((int) $order->id));

        MasterAccess::forget();
        $this->assertSame('approved', DB::table('access_request')->where('id', $id)->value('status'));
        $this->assertTrue(MasterAccess::releasedTo((int) $u->id, $this->records[0]));
        $this->assertCount(2, ReleaseLinkService::forAccessRequests((int) $u->id)[$id] ?? []);
        $this->assertContains('paid', array_map(fn ($h) => $h->action, $svc->getHistory($id)));
        $this->assertNull($svc->recordPayment((int) $order->id), 'a repeated notification releases nothing twice');
    }
}
