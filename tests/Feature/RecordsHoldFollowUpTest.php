<?php

/**
 * RecordsHoldFollowUpTest - heratio#1740 follow-up gaps.
 *  - a legal hold blocks ordinary deletion: a description, its digital
 *    objects, attached objects, the trash purge and the API;
 *  - releasing a hold stores the reason; placing and releasing both join
 *    the signed audit chain;
 *  - audit:prune keeps signed rows and records-management evidence;
 *  - disposal action types come from the Dropdown Manager;
 *  - file plan and retention schedule export, the file plan CSV importing back;
 *  - ahg:records-audit-scrub hashes raw session ids and anonymises IPs, and
 *    changes nothing with --dry-run.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Services\AclService;
use AhgCore\Services\AttachedDigitalObjectService;
use AhgCore\Services\DigitalObjectService;
use AhgCore\Services\LegalHoldGate;
use AhgCore\Services\RecordUnderLegalHoldException;
use AhgIntegrity\Services\LegalHoldService;
use AhgInformationObjectManage\Services\InformationObjectService;
use AhgRecordsManage\Services\DisposalActionTypes;
use AhgRecordsManage\Services\FilePlanExportService;
use AhgRecordsManage\Services\FilePlanImportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecordsHoldFollowUpTest extends TestCase
{
    use DatabaseTransactions;

    private int $series;

    private int $item;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['integrity_legal_hold', 'ahg_audit_log', 'ahg_dropdown', 'rm_fileplan_node', 'rm_retention_schedule'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("$t not present in this database.");
            }
        }
        if (! Schema::hasColumn('integrity_legal_hold', 'release_reason')) {
            $this->markTestSkipped('Migration 2026_11_20_200000 not applied.');
        }
        DisposalActionTypes::flush();
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
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => 'RHF record '.$id]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => 'rhf-'.$id.'-'.Str::random(6)]);

        return $id;
    }

    private function digitalObject(int $ioId): int
    {
        $id = $this->objectRow('QubitDigitalObject');
        DB::table('digital_object')->insert(['id' => $id, 'object_id' => $ioId, 'usage_id' => 140, 'name' => 'rhf-'.$id.'.tif', 'path' => '/uploads/r/'.$ioId.'/']);

        return $id;
    }

    private function admin(): User
    {
        $id = $this->objectRow('QubitUser');
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'rhf-'.$id, 'email' => uniqid('rhf-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");
        AclService::forgetUser();

        return User::findOrFail($id);
    }

    private function hold(int $ioId): int
    {
        return (int) DB::table('integrity_legal_hold')->insertGetId(['information_object_id' => $ioId, 'reason' => 'Litigation', 'placed_by' => 'test', 'status' => 'active']);
    }

    // 1. Legal holds block ordinary deletion

    public function test_a_hold_on_the_series_blocks_deleting_the_items_digital_object(): void
    {
        $do = $this->digitalObject($this->item);
        $holdId = $this->hold($this->series);

        try {
            DigitalObjectService::delete($do);
            $this->fail('a held record\'s digital object must not be deleted');
        } catch (RecordUnderLegalHoldException $e) {
            $this->assertSame($holdId, (int) $e->hold->id);
            $this->assertStringContainsString('legal hold', $e->getMessage());
        }
        $this->assertTrue(DB::table('digital_object')->where('id', $do)->exists());

        // Through the web: a clear error, nothing deleted.
        $this->actingAs($this->admin())->from('/rhf-back')
            ->delete(route('io.digitalobject.delete', $do))
            ->assertRedirect('/rhf-back')
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'legal hold'));
        $this->assertTrue(DB::table('digital_object')->where('id', $do)->exists());

        DB::table('integrity_legal_hold')->where('id', $holdId)->update(['status' => 'released']);
        $this->assertTrue(DigitalObjectService::delete($do), 'deletable once the hold is released');
    }

    public function test_a_hold_on_an_item_blocks_deleting_its_series(): void
    {
        $this->hold($this->item);
        $this->assertNull(LegalHoldGate::holdOn($this->series), 'the series itself is not held');
        $this->assertNotNull(LegalHoldGate::holdOn($this->series, true), 'but it contains a held record');

        try {
            InformationObjectService::delete($this->series);
            $this->fail('deleting the series would delete the held item');
        } catch (RecordUnderLegalHoldException $e) {
            $this->assertSame($this->item, (int) $e->hold->information_object_id);
        }

        $slug = DB::table('slug')->where('object_id', $this->series)->value('slug');
        $this->actingAs($this->admin())
            ->delete(route('informationobject.destroy', $slug))
            ->assertRedirect(route('informationobject.show', $slug))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'legal hold'));
        $this->assertTrue(DB::table('information_object')->where('id', $this->series)->exists());
        $this->assertTrue(DB::table('information_object')->where('id', $this->item)->exists());
    }

    public function test_detach_refuses_before_touching_the_file(): void
    {
        if (! AttachedDigitalObjectService::available()) {
            $this->markTestSkipped('attachments table not present');
        }
        $root = sys_get_temp_dir().'/ahg-rhf-'.uniqid();
        mkdir($root.'/archive/r/'.$this->item, 0777, true);
        config(['heratio.uploads_path' => $root.'/archive', 'heratio.storage_path' => $root]);
        $do = $this->objectRow('QubitDigitalObject');
        DB::table('digital_object')->insert(['id' => $do, 'object_id' => null, 'usage_id' => 140, 'name' => 'att.tif', 'path' => '/uploads/r/'.$this->item.'/']);
        file_put_contents($root.'/archive/r/'.$this->item.'/att.tif', 'ATTACHED');
        $link = (int) DB::table(AttachedDigitalObjectService::TABLE)->insertGetId(['information_object_id' => $this->item, 'digital_object_id' => $do, 'sort_order' => 1]);
        $this->hold($this->series);

        $this->assertSame($this->item, LegalHoldGate::ownerOf($do), 'an attached object belongs to the description it is linked to');
        try {
            app(AttachedDigitalObjectService::class)->detach($link);
            $this->fail('detach of a held record must be refused');
        } catch (RecordUnderLegalHoldException $e) {
        }
        $this->assertFileExists($root.'/archive/r/'.$this->item.'/att.tif');
        $this->assertTrue(DB::table(AttachedDigitalObjectService::TABLE)->where('id', $link)->exists());
        shell_exec('rm -rf '.escapeshellarg($root));
    }

    public function test_the_trash_keeps_a_held_records_files(): void
    {
        if (! Schema::hasTable('ahg_trash')) {
            $this->markTestSkipped('ahg_trash not present');
        }
        $trash = (int) DB::table('ahg_trash')->insertGetId([
            'digital_object_id' => 1, 'information_object_id' => $this->item, 'name' => 'x.tif', 'rows_json' => '{}', 'files_json' => '[]',
            'status' => 'trashed', 'deleted_at' => now()->subDays(100), 'purge_after' => now()->subDay(),
        ]);
        $this->hold($this->series);

        $this->assertFalse(\AhgCore\Services\DigitalObjectTrash::purge($trash));
        $this->actingAs($this->admin())->from('/admin/trash')->post(route('admin.trash.purge', $trash))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'legal hold'));
        $this->assertSame('trashed', DB::table('ahg_trash')->where('id', $trash)->value('status'));
    }

    // 2. Hold release reason and signed change trail

    public function test_placing_and_releasing_a_hold_is_signed_and_keeps_the_release_reason(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $svc = app(LegalHoldService::class);

        $holdId = $svc->placeHold($this->item, 'Subpoena 42', $admin->id);
        $this->assertTrue($svc->releaseHold($holdId, $admin->id, 'Case settled'));

        $hold = DB::table('integrity_legal_hold')->where('id', $holdId)->first();
        $this->assertSame('released', $hold->status);
        $this->assertSame('Case settled', $hold->release_reason);

        $rows = DB::table('ahg_audit_log')->where('entity_type', 'integrity_legal_hold')->where('entity_id', $holdId)->orderBy('id')->get();
        $this->assertSame(['legal_hold_place', 'legal_hold_release'], $rows->pluck('action_name')->all());
        $this->assertSame('Case settled', json_decode($rows[1]->metadata, true)['release_reason']);
        foreach ($rows as $r) {
            $this->assertSame($admin->id, (int) $r->user_id);
            if (is_readable(storage_path('keys/inference-signing.sk'))) {
                $this->assertNotNull($r->seq, 'the row joined the signed chain');
                $this->assertNotNull($r->signature);
            }
        }

        // Through the web form too.
        $second = $svc->placeHold($this->item, 'Second matter', $admin->id);
        $this->post(route('integrity.holds.release', $second), ['release_reason' => 'Withdrawn'])->assertRedirect(route('integrity.holds'));
        $this->assertSame('Withdrawn', DB::table('integrity_legal_hold')->where('id', $second)->value('release_reason'));
    }

    // 3. audit:prune keeps signed and disposal rows

    public function test_audit_prune_keeps_signed_and_records_evidence_rows(): void
    {
        $old = now()->subDays(4000);
        $row = fn (array $extra) => (int) DB::table('ahg_audit_log')->insertGetId(array_merge([
            'uuid' => (string) Str::uuid(), 'action' => 'update', 'entity_type' => 'information_object', 'entity_id' => $this->item, 'created_at' => $old,
        ], $extra));
        $plain = $row(['module' => 'information-object']);
        $nullModule = $row([]);
        $disposal = $row(['module' => 'records-manage', 'entity_type' => 'rm_disposal_action']);
        $hold = $row(['module' => 'integrity', 'entity_type' => 'integrity_legal_hold']);
        $maxSeq = (int) DB::table('ahg_audit_log')->max('seq');
        $signed = $row(['module' => 'information-object', 'seq' => $maxSeq + 1000000, 'entry_hash' => hash('sha256', Str::uuid()), 'prev_hash' => str_repeat('0', 64)]);

        Artisan::call('audit:prune', ['--days' => 3650, '--dry-run' => true]);
        $this->assertTrue(DB::table('ahg_audit_log')->where('id', $plain)->exists(), 'dry run deletes nothing');

        Artisan::call('audit:prune', ['--days' => 3650]);
        $this->assertFalse(DB::table('ahg_audit_log')->where('id', $plain)->exists(), 'ordinary old rows are pruned');
        $this->assertFalse(DB::table('ahg_audit_log')->where('id', $nullModule)->exists());
        foreach ([$disposal, $hold, $signed] as $kept) {
            $this->assertTrue(DB::table('ahg_audit_log')->where('id', $kept)->exists(), "row $kept is kept");
        }
    }

    // 4. Disposal action types from the Dropdown Manager

    public function test_disposal_action_types_come_from_the_dropdown_manager(): void
    {
        $this->assertSame(['destroy', 'transfer_archives', 'transfer_external', 'retain_permanent', 'review'], DisposalActionTypes::codes());
        $admin = $this->admin();

        // Relabelled and switched off in the Dropdown Manager.
        DB::table('ahg_dropdown')->where('taxonomy', DisposalActionTypes::TAXONOMY)->where('code', 'review')->update(['label' => 'Look again']);
        DB::table('ahg_dropdown')->where('taxonomy', DisposalActionTypes::TAXONOMY)->where('code', 'transfer_external')->update(['is_active' => 0]);
        DisposalActionTypes::flush();

        $this->actingAs($admin)->get(route('records.disposal.initiate', $this->item))
            ->assertOk()->assertSee('Look again')->assertDontSee('value="transfer_external"', false);

        $this->post(route('records.disposal.initiate.store'), ['information_object_id' => $this->item, 'action_type' => 'transfer_external'])
            ->assertSessionHasErrors('action_type');
        $this->post(route('records.disposal.initiate.store'), ['information_object_id' => $this->item, 'action_type' => 'made_up'])
            ->assertSessionHasErrors('action_type');
        $this->post(route('records.disposal.initiate.store'), ['information_object_id' => $this->item, 'action_type' => 'review'])
            ->assertSessionHasNoErrors();
        $this->assertSame('review', DB::table('rm_disposal_action')->where('information_object_id', $this->item)->value('action_type'), 'the stored code is unchanged');

        // A code with no executor behind it is never offered.
        DB::table('ahg_dropdown')->insert(['taxonomy' => DisposalActionTypes::TAXONOMY, 'taxonomy_label' => 'Disposal action type', 'code' => 'shred_twice', 'label' => 'Shred twice', 'is_active' => 1]);
        DisposalActionTypes::flush();
        $this->assertNotContains('shred_twice', DisposalActionTypes::codes());
    }

    // 5. File plan and retention schedules export

    public function test_file_plan_csv_round_trips_through_the_importer(): void
    {
        $admin = $this->admin();
        $p = 'RHF'.random_int(1000, 9999);
        $plan = (int) DB::table('rm_fileplan_node')->insertGetId(['code' => $p, 'title' => 'Plan', 'node_type' => 'plan', 'status' => 'active', 'depth' => 0, 'created_by' => $admin->id, 'created_at' => now()]);
        DB::table('rm_fileplan_node')->insert(['parent_id' => $plan, 'code' => $p.'/1', 'title' => 'Finance, "budgets"', 'description' => '=SUM(A1)', 'retention_period' => '7 years', 'disposal_action' => 'destroy', 'node_type' => 'series', 'status' => 'active', 'depth' => 1, 'created_by' => $admin->id, 'created_at' => now()]);

        $res = $this->actingAs($admin)->get(route('records.fileplan.export', 'csv'));
        $res->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));
        $csv = $res->getContent();
        $this->assertStringStartsWith(implode(',', FilePlanExportService::FILEPLAN_COLUMNS)."\n", $csv);
        $this->assertStringContainsString("'=SUM(A1)", $csv, 'a formula-like value is guarded');

        // Import it into an empty file plan: same codes, titles and fields come back.
        $before = DB::table('rm_fileplan_node')->where('code', 'like', $p.'%')->orderBy('code')->get(['code', 'title', 'description', 'retention_period', 'disposal_action'])->map(fn ($r) => (array) $r)->all();
        DB::table('rm_fileplan_node')->delete();
        $file = tempnam(sys_get_temp_dir(), 'rhf').'.csv';
        file_put_contents($file, $csv);
        $importer = app(FilePlanImportService::class);
        $headers = \PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getActiveSheet()->toArray(null, true, true, true)[1];
        $mapping = $importer->detectColumnMapping($headers);
        $this->assertSame(['code' => 'A', 'title' => 'B', 'description' => 'C', 'retention_period' => 'D', 'disposal_action' => 'E'], $mapping);
        $result = $importer->importFromSpreadsheet($file, $mapping, null, null, $admin->id);
        $this->assertSame([], $result['errors']);
        @unlink($file);

        $after = DB::table('rm_fileplan_node')->where('code', 'like', $p.'%')->orderBy('code')->get(['code', 'title', 'description', 'retention_period', 'disposal_action'])
            ->map(fn ($r) => array_map(fn ($v) => $v === '' ? null : $v, (array) $r))->all();
        $this->assertSame(array_map(fn ($r) => array_map(fn ($v) => $v === '' ? null : $v, $r), $before), $after);
        $this->assertSame($p, DB::table('rm_fileplan_node')->where('id', DB::table('rm_fileplan_node')->where('code', $p.'/1')->value('parent_id'))->value('code'), 'the hierarchy is rebuilt');

        $json = $this->get(route('records.fileplan.export', 'json'))->assertOk()->json();
        $this->assertSame('heratio-fileplan', $json['format']);
        $this->assertContains($p.'/1', array_column($json['nodes'], 'code'));
        $this->assertSame($p, collect($json['nodes'])->firstWhere('code', $p.'/1')['parent_code']);
    }

    public function test_retention_schedules_export_with_their_classes(): void
    {
        $admin = $this->admin();
        $ref = 'RHF-RS-'.random_int(1000, 9999);
        $sid = (int) DB::table('rm_retention_schedule')->insertGetId(['schedule_ref' => $ref, 'title' => 'General schedule', 'status' => 'draft', 'version' => 1, 'created_by' => $admin->id, 'created_at' => now()]);
        if (Schema::hasTable('rm_disposal_class')) {
            DB::table('rm_disposal_class')->insert(['retention_schedule_id' => $sid, 'class_ref' => 'C1', 'title' => 'Invoices', 'retention_period_years' => 5, 'disposal_action' => 'destroy', 'is_active' => 1, 'created_by' => $admin->id, 'created_at' => now()]);
        }

        $json = $this->actingAs($admin)->get(route('records.schedules.export', 'json'))->assertOk()->json();
        $this->assertSame('heratio-retention-schedules', $json['format']);
        $s = collect($json['schedules'])->firstWhere('schedule_ref', $ref);
        $this->assertSame('General schedule', $s['title']);

        $csv = $this->get(route('records.schedules.export', 'csv'))->assertOk()->getContent();
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => str_contains($l, $ref)));
        $this->assertCount(1, $lines);
        $this->assertStringStartsWith('schedule_ref,schedule_title,', $csv);
        if (Schema::hasTable('rm_disposal_class')) {
            $this->assertSame('Invoices', $s['disposal_classes'][0]['title']);
            $this->assertStringContainsString('C1,Invoices', $lines[0]);
        }
    }

    // 6. ahg:records-audit-scrub

    public function test_audit_scrub_hashes_sessions_and_anonymises_ips_and_dry_run_changes_nothing(): void
    {
        DB::table('ahg_settings')->updateOrInsert(['setting_key' => 'audit_ip_anonymize'], ['setting_value' => '1']);
        $raw = Str::random(40);
        $yesterday = now()->subDay();
        $ins = fn (array $extra) => (int) DB::table('ahg_audit_log')->insertGetId(array_merge([
            'uuid' => (string) Str::uuid(), 'action' => 'update', 'entity_type' => 'rm_disposal_action', 'entity_id' => 1,
            'module' => 'records-manage', 'session_id' => $raw, 'ip_address' => '203.0.113.77', 'created_at' => $yesterday,
        ], $extra));
        $old = $ins([]);
        $otherModule = $ins(['module' => 'information-object']);
        $today = $ins(['created_at' => now()]);
        $signed = $ins(['seq' => (int) DB::table('ahg_audit_log')->max('seq') + 1000000, 'entry_hash' => hash('sha256', Str::uuid()), 'prev_hash' => str_repeat('0', 64)]);
        $snapshot = fn () => DB::table('ahg_audit_log')->whereIn('id', [$old, $otherModule, $today, $signed])->orderBy('id')->get(['id', 'session_id', 'ip_address'])->toArray();

        $before = $snapshot();
        $this->assertSame(0, Artisan::call('ahg:records-audit-scrub', ['--dry-run' => true]));
        $this->assertStringContainsString('Nothing was written', Artisan::output());
        $this->assertEquals($before, $snapshot(), '--dry-run changes nothing');

        $this->assertSame(0, Artisan::call('ahg:records-audit-scrub'));
        $row = DB::table('ahg_audit_log')->where('id', $old)->first();
        $this->assertSame(hash_hmac('sha256', $raw, (string) config('app.key')), $row->session_id, 'the same keyed hash the writer uses');
        $this->assertSame('203.0.113.0', $row->ip_address);
        foreach ([$otherModule, $today, $signed] as $untouched) {
            $r = DB::table('ahg_audit_log')->where('id', $untouched)->first();
            $this->assertSame($raw, $r->session_id, "row $untouched is outside the scrub");
        }

        // Re-runnable: a second run changes nothing more.
        $after = $snapshot();
        Artisan::call('ahg:records-audit-scrub');
        $this->assertEquals($after, $snapshot());
    }
}
