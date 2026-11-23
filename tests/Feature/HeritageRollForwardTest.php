<?php

namespace Tests\Feature;

use AhgHeritageManage\Jurisdiction\GenericRollForwardProfile;
use AhgHeritageManage\Jurisdiction\Grap103RollForwardProfile;
use AhgHeritageManage\Services\HeritageAccountingService;
use AhgHeritageManage\Services\HeritageRegisterReconciliationService;
use AhgHeritageManage\Services\HeritageRollForwardService;
use AhgHeritageManage\Services\HeritageValuationService;
use AhgHeritageManage\Services\OciMovementService;
use AhgHeritageManage\Services\RollForwardProfileRegistry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MintsApiAdmin;
use Tests\TestCase;

class HeritageRollForwardTest extends TestCase
{
    use DatabaseTransactions;
    use MintsApiAdmin;

    private function asset(float $amount, string $date = '2024-06-01', array $extra = []): int
    {
        return (int) DB::table('heritage_asset')->insertGetId($extra + [
            'recognition_status' => 'recognised',
            'recognition_date' => $date,
            'initial_carrying_amount' => $amount,
            'current_carrying_amount' => $amount,
            'revaluation_surplus' => 0,
            'impairment_loss' => 0,
            'created_at' => $date.' 00:00:00',
        ]);
    }

    private function impair(int $id, float $loss, string $date): void
    {
        app(HeritageAccountingService::class)->addImpairment($id, [
            'assessment_date' => $date, 'impairment_identified' => 1, 'impairment_loss' => $loss, 'recoverable_amount' => null,
        ], null);
    }

    public function test_full_cycle_rolls_forward_and_reconciles(): void
    {
        $held = $this->asset(1000);
        $bought = $this->asset(500, '2025-03-01');
        $sold = $this->asset(200);
        $valuation = app(HeritageValuationService::class);

        $valuation->record($held, ['new_value' => 1300, 'valuation_date' => '2025-02-01', 'currency' => 'USD']);
        $this->impair($held, 100, '2025-05-01');
        DB::table('heritage_asset')->where('id', $sold)->update(['derecognition_date' => '2025-07-01', 'recognition_status' => 'derecognised']);

        $r = app(HeritageRollForwardService::class)->compute('2025-01-01', '2025-12-31');
        $l = $r['lines'];

        $this->assertEqualsWithDelta(1200, $l['opening'], .001);
        $this->assertEqualsWithDelta(500, $l['additions'], .001);
        $this->assertEqualsWithDelta(300, $l['revaluation_increase'], .001);
        $this->assertEqualsWithDelta(-100, $l['impairment'], .001);
        $this->assertEqualsWithDelta(-200, $l['disposals'], .001);
        $this->assertEqualsWithDelta(1700, $l['closing'], .001);
        $this->assertEqualsWithDelta($l['closing'], $l['opening'] + $l['additions'] + $l['revaluation_increase'] + $l['revaluation_decrease'] + $l['impairment'] + $l['reversal'] + $l['disposals'], .001);
        $this->assertSame(2, $r['asset_count']);
        $this->assertTrue($r['reconciled'], json_encode($r['unreconciled']));
    }

    public function test_impairment_takes_the_reserve_first_then_surplus_or_deficit(): void
    {
        $id = $this->asset(1000);
        app(HeritageValuationService::class)->record($id, ['new_value' => 1200, 'valuation_date' => '2025-01-15', 'currency' => 'USD']);
        $this->impair($id, 300, '2025-02-01');

        $rows = DB::table('ahg_heritage_oci_movement')->where('heritage_asset_id', $id)->where('movement_type', 'impairment')->pluck('amount', 'posted_to');
        $this->assertEqualsWithDelta(-200, (float) $rows['OCI'], .001);
        $this->assertEqualsWithDelta(-100, (float) $rows['P&L'], .001);

        $asset = DB::table('heritage_asset')->find($id);
        $this->assertEqualsWithDelta(0, (float) $asset->revaluation_surplus, .001);
        $this->assertEqualsWithDelta(900, (float) $asset->current_carrying_amount, .001);
    }

    public function test_impairment_above_carrying_amount_is_refused_and_writes_nothing(): void
    {
        $id = $this->asset(100);
        try {
            $this->impair($id, 150, '2025-02-01');
            $this->fail('Expected refusal');
        } catch (\InvalidArgumentException) {
        }
        $this->assertSame(0, DB::table('heritage_impairment_assessment')->where('heritage_asset_id', $id)->count());
        $this->assertSame(0, DB::table('ahg_heritage_oci_movement')->where('heritage_asset_id', $id)->count());
    }

    public function test_revaluation_after_impairment_is_measured_from_carrying_amount(): void
    {
        $id = $this->asset(1000, '2024-06-01', ['last_valuation_amount' => 1000, 'last_valuation_date' => '2024-06-01']);
        $this->impair($id, 300, '2025-01-01');

        $out = app(HeritageValuationService::class)->record($id, ['new_value' => 900, 'valuation_date' => '2025-03-01', 'currency' => 'USD']);

        $this->assertEqualsWithDelta(200, $out['change'], .001);
        $this->assertTrue(app(HeritageRollForwardService::class)->compute('2025-01-01', '2025-12-31')['reconciled']);

        // The increase reverses the earlier P&L loss; nothing reaches the reserve.
        $up = DB::table('ahg_heritage_oci_movement')->where('heritage_asset_id', $id)->where('movement_type', 'revaluation_up')->pluck('amount', 'posted_to');
        $this->assertEqualsWithDelta(200, (float) $up['P&L'], .001);
        $this->assertFalse($up->has('OCI'));
        $this->assertEqualsWithDelta(0, (float) DB::table('heritage_asset')->where('id', $id)->value('revaluation_surplus'), .001);
        $this->assertEqualsWithDelta(0, (float) DB::table('heritage_valuation_history')->where('heritage_asset_id', $id)->value('revaluation_surplus_change'), .001);
    }

    public function test_increase_beyond_the_pl_loss_goes_to_the_reserve_and_is_not_reversed_twice(): void
    {
        $id = $this->asset(1000, '2024-06-01', ['last_valuation_amount' => 1000, 'last_valuation_date' => '2024-06-01']);
        $this->impair($id, 300, '2025-01-01');
        $valuation = app(HeritageValuationService::class);

        $valuation->record($id, ['new_value' => 1100, 'valuation_date' => '2025-03-01', 'currency' => 'USD']);
        $up = DB::table('ahg_heritage_oci_movement')->where('heritage_asset_id', $id)->where('movement_type', 'revaluation_up')->pluck('amount', 'posted_to');
        $this->assertEqualsWithDelta(300, (float) $up['P&L'], .001);
        $this->assertEqualsWithDelta(100, (float) $up['OCI'], .001);
        $this->assertEqualsWithDelta(100, (float) DB::table('heritage_asset')->where('id', $id)->value('revaluation_surplus'), .001);
        $this->assertEqualsWithDelta(0, app(OciMovementService::class)->getAccumulatedPlImpairment($id), .001);

        $valuation->record($id, ['new_value' => 1150, 'valuation_date' => '2025-04-01', 'currency' => 'USD']);
        $this->assertEqualsWithDelta(150, (float) DB::table('heritage_asset')->where('id', $id)->value('revaluation_surplus'), .001);
        $this->assertEqualsWithDelta(150, app(OciMovementService::class)->getAccumulatedOciSurplus($id), .001);
    }

    public function test_impairment_uses_a_register_reserve_that_has_no_ledger_rows(): void
    {
        $id = $this->asset(1000, '2024-06-01', ['current_carrying_amount' => 1200, 'revaluation_surplus' => 200]);

        $this->impair($id, 100, '2025-02-01');

        $rows = DB::table('ahg_heritage_oci_movement')->where('heritage_asset_id', $id)->pluck('amount', 'posted_to');
        $this->assertEqualsWithDelta(-100, (float) $rows['OCI'], .001);
        $this->assertFalse($rows->has('P&L'));
        $this->assertEqualsWithDelta(100, (float) DB::table('heritage_asset')->where('id', $id)->value('revaluation_surplus'), .001);
    }

    public function test_downward_revaluation_uses_a_register_reserve_that_has_no_ledger_rows(): void
    {
        $id = $this->asset(1000, '2024-06-01', ['current_carrying_amount' => 1200, 'revaluation_surplus' => 200]);

        app(HeritageValuationService::class)->record($id, ['new_value' => 1050, 'valuation_date' => '2025-02-01', 'currency' => 'USD']);

        $rows = DB::table('ahg_heritage_oci_movement')->where('heritage_asset_id', $id)->pluck('amount', 'posted_to');
        $this->assertEqualsWithDelta(-150, (float) $rows['OCI'], .001);
        $this->assertFalse($rows->has('P&L'));
        $this->assertEqualsWithDelta(50, (float) DB::table('heritage_asset')->where('id', $id)->value('revaluation_surplus'), .001);
    }

    public function test_same_day_correction_back_to_an_earlier_figure_is_recorded(): void
    {
        $id = $this->asset(1000);
        $valuation = app(HeritageValuationService::class);
        $valuation->record($id, ['new_value' => 1100, 'valuation_date' => '2025-02-01', 'currency' => 'USD', 'skip_if_unchanged' => true]);
        $valuation->record($id, ['new_value' => 1200, 'valuation_date' => '2025-02-01', 'currency' => 'USD', 'skip_if_unchanged' => true]);

        $out = $valuation->record($id, ['new_value' => 1100, 'valuation_date' => '2025-02-01', 'currency' => 'USD', 'skip_if_unchanged' => true]);

        $this->assertSame('recorded', $out['status']);
        $this->assertEqualsWithDelta(1100, (float) DB::table('heritage_asset')->where('id', $id)->value('current_carrying_amount'), .001);
    }

    public function test_note_does_not_report_zero_unreconciled_assets(): void
    {
        $note = app(HeritageRollForwardService::class)->markdown([
            'lines' => [], 'reconciled' => false, 'unreconciled' => [],
            'ledger_only' => [['asset_id' => 1, 'date' => '2025-03-01', 'type' => 'impairment', 'amount' => -400.0]],
        ], app(RollForwardProfileRegistry::class)->for('GRAP103'));

        $this->assertStringNotContainsString('0 asset(s)', $note);
        $this->assertStringContainsString('1 movement(s) on the reserve ledger', $note);
    }

    public function test_movements_keyed_only_on_the_reserve_ledger_are_not_reconciled(): void
    {
        $oci = app(OciMovementService::class);
        $impaired = $this->asset(1000);
        $oci->recordImpairment($impaired, 400, '2025-03-01');
        $revalued = $this->asset(1000);
        $oci->recordRevaluation($revalued, 1000, 1100, '2025-03-01');
        $disposed = $this->asset(1000);
        $oci->recordDisposal($disposed, 900, 1000, '2025-03-01');
        $reversed = $this->asset(1000);
        $oci->recordReversal($reversed, 50, '2025-03-01');

        $r = app(HeritageRollForwardService::class)->compute('2025-01-01', '2025-12-31');

        $this->assertFalse($r['reconciled']);
        $this->assertEqualsCanonicalizing([$impaired, $revalued, $disposed, $reversed], array_values(array_intersect(array_column($r['ledger_only'], 'asset_id'), [$impaired, $revalued, $disposed, $reversed])));
        $note = app(HeritageRollForwardService::class)->markdown($r, app(RollForwardProfileRegistry::class)->for('GRAP103'));
        $this->assertStringContainsString('on the reserve ledger have no matching register entry', $note);
    }

    public function test_zero_initial_amount_falls_through_to_acquisition_cost(): void
    {
        DB::table('heritage_asset')->update(['recognition_status' => 'pending', 'derecognition_date' => null]);
        $this->asset(0, '2025-03-01', ['acquisition_cost' => 1000, 'current_carrying_amount' => 1000]);

        $r = app(HeritageRollForwardService::class)->compute('2025-01-01', '2025-12-31');

        $this->assertEqualsWithDelta(1000, $r['lines']['additions'], .001);
        $this->assertEqualsWithDelta(1000, $r['lines']['closing'], .001);
        $this->assertSame([], $r['unreconciled']);
    }

    public function test_replaying_an_older_procedure_cannot_rewind_the_asset(): void
    {
        $id = $this->asset(1000);
        $valuation = app(HeritageValuationService::class);
        $valuation->record($id, ['new_value' => 1000, 'valuation_date' => '2024-07-01', 'currency' => 'USD']);
        $valuation->record($id, ['new_value' => 800, 'valuation_date' => '2025-01-01', 'currency' => 'USD']);
        $this->impair($id, 300, '2025-02-01');

        $out = $valuation->record($id, ['new_value' => 1000, 'valuation_date' => '2024-07-01', 'skip_if_unchanged' => true]);

        $this->assertSame('unchanged', $out['status']);
        $asset = DB::table('heritage_asset')->find($id);
        $this->assertEqualsWithDelta(500, (float) $asset->current_carrying_amount, .001);
        $this->assertSame('2025-01-01', substr((string) $asset->last_valuation_date, 0, 10));
    }

    public function test_replayed_bridge_valuation_does_not_undo_a_later_impairment(): void
    {
        $id = $this->asset(1000, '2024-06-01', ['last_valuation_amount' => 1000, 'last_valuation_date' => '2024-06-01']);
        $this->impair($id, 300, '2025-01-01');

        $out = app(HeritageValuationService::class)->record($id, ['new_value' => 1000, 'valuation_date' => '2024-06-01', 'skip_if_unchanged' => true]);

        $this->assertSame('unchanged', $out['status']);
        $this->assertEqualsWithDelta(700, (float) DB::table('heritage_asset')->where('id', $id)->value('current_carrying_amount'), .001);
    }

    public function test_direct_edit_of_carrying_amount_is_reported_as_unreconciled(): void
    {
        $id = $this->asset(1000);
        DB::table('heritage_asset')->where('id', $id)->update(['current_carrying_amount' => 1250]);

        $r = app(HeritageRollForwardService::class)->compute('2025-01-01', '2025-12-31');

        $this->assertFalse($r['reconciled']);
        $row = collect($r['unreconciled'])->firstWhere('asset_id', $id);
        $this->assertEqualsWithDelta(250, $row['difference'], .001);
    }

    public function test_profiles_are_pluggable_and_fall_back_to_generic(): void
    {
        $registry = app(RollForwardProfileRegistry::class);
        $this->assertInstanceOf(Grap103RollForwardProfile::class, $registry->for('grap103'));
        $this->assertInstanceOf(GenericRollForwardProfile::class, $registry->for('FRS102'));
        $this->assertSame(array_keys((new GenericRollForwardProfile())->lineLabels()), array_keys((new Grap103RollForwardProfile())->lineLabels()));
    }

    public function test_grap_disclosure_note_carries_the_roll_forward(): void
    {
        $grap = (int) DB::table('heritage_accounting_standard')->where('code', 'GRAP103')->value('id');
        $other = (int) DB::table('heritage_accounting_standard')->where('code', 'FRS102')->value('id');
        DB::table('heritage_asset')->update(['accounting_standard_id' => $other]);
        $this->asset(1000, '2024-06-01', ['accounting_standard_id' => $grap]);
        $this->asset(5000, '2024-06-01', ['accounting_standard_id' => $other]);
        $this->asset(70, '2024-06-01', ['accounting_standard_id' => null]);
        $path = sys_get_temp_dir().'/grap-note-'.uniqid().'.md';
        Artisan::call('heritage:disclosure-note', ['--standard' => 'grap-103', '--period' => '2025-01-01..2025-12-31', '--out' => $path]);
        $note = file_get_contents($path);
        unlink($path);

        $this->assertStringContainsString('Reconciliation of the carrying amount of heritage assets (GRAP 103)', $note);
        $this->assertStringContainsString('Carrying amount at end of the reporting period | 1,000.00', $note);
        $this->assertStringContainsString('**Excluded:** 1 recognised asset(s)', $note);
    }

    public function test_register_comparison_finds_differences_without_writing(): void
    {
        $io = (int) DB::table('heritage_asset')->max('information_object_id') + 900001;
        $this->asset(100, '2024-06-01', ['information_object_id' => $io]);
        $base = ['category_id' => null, 'title' => 'T', 'created_by' => 1, 'status' => 'active'];
        DB::table('ipsas_heritage_asset')->insert($base + ['asset_number' => 'RF-1', 'information_object_id' => $io, 'current_value' => 150]);
        DB::table('ipsas_heritage_asset')->insert($base + ['asset_number' => 'RF-2', 'information_object_id' => null, 'current_value' => 10]);
        $before = [DB::table('heritage_asset')->count(), DB::table('ipsas_heritage_asset')->count()];

        $r = app(HeritageRegisterReconciliationService::class)->compare();

        $this->assertSame(1, $r['matched']);
        $this->assertSame(1, $r['ipsas_without_record']);
        $this->assertCount(1, $r['value_differences']);
        $this->assertSame($before, [DB::table('heritage_asset')->count(), DB::table('ipsas_heritage_asset')->count()]);
    }

    public function test_report_is_admin_only_and_renders_for_an_admin(): void
    {
        $this->assertAdminGated($this->get('/heritage/reports/roll-forward'));

        $this->asset(1000);
        $admin = \AhgCore\Models\User::findOrFail($this->mintApiAdmin()->id);
        $this->actingAs($admin)->get('/heritage/reports/roll-forward?from=2025-01-01&to=2025-12-31')
            ->assertOk()->assertSee('Carrying Amount Roll-forward')->assertSee('1,000.00');
        $this->actingAs($admin)->get('/heritage/reports/roll-forward?to=2025-12-31')->assertOk();
        $this->actingAs($admin)->get('/heritage/reports/roll-forward?from=2025-12-31&to=2025-01-01')->assertStatus(302);
    }

    public function test_impairment_form_posts_and_an_excess_loss_returns_a_field_error(): void
    {
        $id = $this->asset(1000);
        $admin = \AhgCore\Models\User::findOrFail($this->mintApiAdmin()->id);
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);

        $this->actingAs($admin)->post("/heritage/accounting/{$id}/impairment", [
            'assessment_date' => '2025-02-01', 'impairment_identified' => 1, 'physical_damage' => 1, 'recoverable_amount' => 750,
        ])->assertRedirect(route('heritage.accounting.view', $id));
        $this->assertEqualsWithDelta(750, (float) DB::table('heritage_asset')->where('id', $id)->value('current_carrying_amount'), .001);
        $this->assertEqualsWithDelta(-250, (float) DB::table('ahg_heritage_oci_movement')->where('heritage_asset_id', $id)->sum('amount'), .001);

        $this->actingAs($admin)->post("/heritage/accounting/{$id}/impairment", [
            'assessment_date' => '2025-03-01', 'impairment_identified' => 1, 'impairment_loss' => 5000,
        ])->assertSessionHasErrors('impairment_loss');
        $this->assertEqualsWithDelta(750, (float) DB::table('heritage_asset')->where('id', $id)->value('current_carrying_amount'), .001);
    }
}
