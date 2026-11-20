<?php

/**
 * RecordsDisposalComplianceTest - heratio#1740 (MoReq2010 / DoD 5015.2 gaps).
 *  - a legal hold on a file or series holds the records inside it: no
 *    disposal can start, be legally cleared, or be executed (destroy or
 *    transfer);
 *  - destruction needs a reason, and the certificate carries it with the
 *    four-eyes chain;
 *  - every disposal step is written to the signed audit chain with a hashed
 *    session id, never the live session cookie;
 *  - one record exports with its metadata, records-management state and
 *    audit history, and the export itself is audited.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Services\AclService;
use AhgRecordsManage\Services\DisposalExecutionService;
use AhgRecordsManage\Services\DisposalWorkflowService;
use AhgRecordsManage\Services\LegalHoldGuard;
use AhgRecordsManage\Services\RecordExportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RecordsDisposalComplianceTest extends TestCase
{
    use DatabaseTransactions;

    private int $series;

    private int $item;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['rm_disposal_action', 'integrity_legal_hold', 'destruction_certificate', 'ahg_audit_log'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("$t not present in this database.");
            }
        }
        $this->series = $this->io(null);
        $this->item = $this->io($this->series);
    }

    private function objectRow(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function io(?int $parent): int
    {
        $id = $this->objectRow('QubitInformationObject');
        DB::table('information_object')->insert(['id' => $id, 'parent_id' => $parent, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => 'RDC record '.$id]);

        return $id;
    }

    private function admin(): User
    {
        $id = $this->objectRow('QubitUser');
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'rdc-'.$id, 'email' => uniqid('rdc-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");
        AclService::forgetUser();

        return User::findOrFail($id);
    }

    private function hold(int $ioId): int
    {
        return (int) DB::table('integrity_legal_hold')->insertGetId(['information_object_id' => $ioId, 'reason' => 'Litigation', 'placed_by' => 'test', 'status' => 'active']);
    }

    /** A disposal action already approved and legally cleared by two other people. */
    private function clearedAction(string $type, int $initiator, int $approver, int $clearer): int
    {
        return (int) DB::table('rm_disposal_action')->insertGetId([
            'information_object_id' => $this->item, 'action_type' => $type, 'status' => 'cleared', 'reason' => 'Retention expired',
            'initiated_by' => $initiator, 'approved_by' => $approver, 'legal_cleared' => 1, 'legal_cleared_by' => $clearer,
        ]);
    }

    public function test_a_hold_on_the_series_holds_the_items_inside_it(): void
    {
        $wf = app(DisposalWorkflowService::class);
        $a = $this->admin();
        $b = $this->admin();
        $this->assertFalse(LegalHoldGuard::isHeld($this->item));

        $holdId = $this->hold($this->series);
        $this->assertTrue(LegalHoldGuard::isHeld($this->item), 'the item inherits the hold on its series');
        $this->assertSame((int) $this->series, (int) LegalHoldGuard::activeHoldFor($this->item)->information_object_id);

        try {
            $wf->initiateDisposal($this->item, 0, 'destroy', $a->id, 'Retention expired');
            $this->fail('disposal of a held item must not start');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('legal hold', $e->getMessage());
        }

        // Approved before the hold was placed: the hold still stops clearance and execution.
        $approved = (int) DB::table('rm_disposal_action')->insertGetId(['information_object_id' => $this->item, 'action_type' => 'destroy', 'status' => 'approved', 'reason' => 'x', 'initiated_by' => $a->id, 'approved_by' => $a->id]);
        $this->assertFalse($wf->clearLegal($approved, $b->id));

        $executor = $this->admin();
        $this->actingAs($executor);
        $destroy = $this->clearedAction('destroy', $a->id, $a->id, $b->id);
        $result = app(DisposalExecutionService::class)->executeDestroy($destroy, $executor->id);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('legal hold', $result['error']);

        $transfer = $this->clearedAction('transfer_external', $a->id, $a->id, $b->id);
        $result = app(DisposalExecutionService::class)->executeTransfer($transfer, 'Another archive', $executor->id);
        $this->assertFalse($result['success']);
        $this->assertSame('cleared', DB::table('rm_disposal_action')->where('id', $transfer)->value('status'));

        // Released: disposal may start again.
        DB::table('integrity_legal_hold')->where('id', $holdId)->update(['status' => 'released']);
        $this->assertFalse(LegalHoldGuard::isHeld($this->item));
        $this->assertGreaterThan(0, $wf->initiateDisposal($this->item, 0, 'destroy', $a->id, 'Retention expired'));
    }

    public function test_destruction_needs_a_reason(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('records.disposal.initiate.store'), ['information_object_id' => $this->item, 'action_type' => 'destroy', 'reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->assertSame(0, DB::table('rm_disposal_action')->where('information_object_id', $this->item)->count());

        $this->expectException(\RuntimeException::class);
        app(DisposalWorkflowService::class)->initiateDisposal($this->item, 0, 'destroy', $admin->id, '   ');
    }

    public function test_the_whole_destruction_is_audited_and_certified(): void
    {
        [$initiator, $approver, $clearer, $executor] = [$this->admin(), $this->admin(), $this->admin(), $this->admin()];
        // The real layout: path is the web folder, the file sits under uploads_path.
        $root = sys_get_temp_dir().'/ahg-rdc-'.uniqid();
        mkdir($root.'/archive/r/'.$this->item, 0777, true);
        config(['heratio.uploads_path' => $root.'/archive', 'heratio.storage_path' => $root]);
        $file = $root.'/archive/r/'.$this->item.'/m.tif';
        $ref = $root.'/archive/r/'.$this->item.'/m_141.jpg';
        file_put_contents($file, 'MASTER');
        file_put_contents($ref, 'REFERENCE');
        $do = $this->objectRow('QubitDigitalObject');
        DB::table('digital_object')->insert(['id' => $do, 'object_id' => $this->item, 'usage_id' => 140, 'name' => 'm.tif', 'path' => '/uploads/r/'.$this->item.'/']);
        $refId = $this->objectRow('QubitDigitalObject');
        DB::table('digital_object')->insert(['id' => $refId, 'parent_id' => $do, 'usage_id' => 141, 'name' => 'm_141.jpg', 'path' => '/uploads/r/'.$this->item.'/']);

        $this->actingAs($initiator)->post(route('records.disposal.initiate.store'), ['information_object_id' => $this->item, 'action_type' => 'destroy', 'reason' => 'Retention period of 5 years expired']);
        $id = (int) DB::table('rm_disposal_action')->where('information_object_id', $this->item)->value('id');
        $this->assertGreaterThan(0, $id);

        $this->actingAs($approver)->post(route('records.disposal.approve', $id));
        $this->actingAs($clearer)->post(route('records.disposal.clearLegal', $id));
        $this->actingAs($executor)->post(route('records.disposal.execute', $id));

        $action = DB::table('rm_disposal_action')->where('id', $id)->first();
        $this->assertSame('executed', $action->status);
        $this->assertFileDoesNotExist($file);
        $this->assertFileDoesNotExist($ref, 'the reference copy is destroyed with the master');

        $meta = json_decode(DB::table('destruction_certificate')->where('id', $action->certificate_id)->value('metadata'), true);
        $this->assertSame([hash('sha256', 'MASTER'), hash('sha256', 'REFERENCE')], array_column($meta['files_deleted'], 'hash'));
        $this->assertTrue(app(DisposalExecutionService::class)->verifyDestruction($id)['verified']);
        $this->assertSame('Retention period of 5 years expired', $meta['reason']);
        $this->assertSame([$initiator->id, $approver->id, $clearer->id, $executor->id], [(int) $meta['initiated_by'], (int) $meta['approved_by'], (int) $meta['legal_cleared_by'], (int) $meta['executed_by']]);

        $rows = DB::table('ahg_audit_log')->where('entity_type', 'rm_disposal_action')->where('entity_id', $id)->orderBy('id')->get();
        $this->assertSame(['create', 'update', 'update', 'delete'], $rows->pluck('action')->all());
        $this->assertSame([$initiator->id, $approver->id, $clearer->id, $executor->id], $rows->pluck('user_id')->map(fn ($v) => (int) $v)->all());
        foreach ($rows as $r) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $r->session_id, 'a keyed hash, not the session cookie');
            if (is_readable(storage_path('keys/inference-signing.sk'))) {
                $this->assertNotNull($r->seq, 'the row joined the signed chain');
                $this->assertNotNull($r->entry_hash);
            }
        }
        shell_exec('rm -rf '.escapeshellarg($root));
    }

    public function test_a_record_exports_with_its_metadata_and_audit_history(): void
    {
        $admin = $this->admin();
        $this->hold($this->series);
        $actionId = (int) DB::table('rm_disposal_action')->insertGetId(['information_object_id' => $this->item, 'action_type' => 'review', 'status' => 'pending', 'initiated_by' => $admin->id]);
        DB::table('ahg_audit_log')->insert(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'action' => 'update', 'entity_type' => 'information_object', 'entity_id' => $this->item, 'session_id' => 'raw-session', 'user_agent' => 'Browser', 'created_at' => now()]);
        DB::table('ahg_audit_log')->insert(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'action' => 'create', 'entity_type' => 'rm_disposal_action', 'entity_id' => $actionId, 'created_at' => now()]);

        $res = $this->actingAs($admin)->get(route('records.export', $this->item));
        $res->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="record-'.$this->item.'-export.json"');
        $json = $res->json();

        $this->assertSame(RecordExportService::FORMAT, $json['format']);
        $this->assertSame($this->item, $json['record']['id']);
        $this->assertSame('RDC record '.$this->item, $json['record']['descriptions'][0]['title']);
        $this->assertSame($this->series, $json['record']['parent_id']);
        $this->assertSame($this->series, (int) $json['records_management']['held_by_aggregation']['information_object_id']);
        $this->assertCount(1, $json['records_management']['disposal_actions']);
        $this->assertSame(['information_object', 'rm_disposal_action'], array_column($json['audit']['changes'], 'entity_type'));
        $this->assertArrayNotHasKey('session_id', $json['audit']['changes'][0]);
        $this->assertArrayNotHasKey('user_agent', $json['audit']['changes'][0]);

        $this->assertTrue(DB::table('ahg_audit_log')->where('action', 'export')->where('entity_type', 'information_object')->where('entity_id', $this->item)->exists(), 'the export is audited');

        $this->actingAs($admin)->get(route('records.export', 999999999))->assertNotFound();
    }
}
