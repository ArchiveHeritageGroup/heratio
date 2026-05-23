<?php

/**
 * SeedSpectrumCommandTest - Heratio Spectrum#B: seed pack install tests.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeedSpectrumCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dry_run_makes_no_changes(): void
    {
        $beforeWorkflows = DB::table('ahg_workflow')->whereNotNull('spectrum_procedure')->count();
        $beforeSteps = DB::table('ahg_workflow_step')->count();

        $this->artisan('workflow:seed-spectrum --dry-run')->assertSuccessful();

        $this->assertSame($beforeWorkflows, DB::table('ahg_workflow')->whereNotNull('spectrum_procedure')->count());
        $this->assertSame($beforeSteps, DB::table('ahg_workflow_step')->count());
    }

    public function test_default_install_creates_21_workflows_with_steps(): void
    {
        $this->artisan('workflow:seed-spectrum')->assertSuccessful();

        $workflows = DB::table('ahg_workflow')->whereNotNull('spectrum_procedure')->get();
        $this->assertGreaterThanOrEqual(21, $workflows->count());

        // Each Spectrum workflow should have at least one step.
        foreach ($workflows as $wf) {
            $stepCount = DB::table('ahg_workflow_step')->where('workflow_id', $wf->id)->count();
            $this->assertGreaterThan(0, $stepCount, "Spectrum workflow '{$wf->spectrum_procedure}' must have at least one step");
        }
    }

    public function test_default_install_is_idempotent_existing_workflows_are_skipped(): void
    {
        // First run
        $this->artisan('workflow:seed-spectrum')->assertSuccessful();
        $firstCount = DB::table('ahg_workflow')->whereNotNull('spectrum_procedure')->count();
        $firstSteps = DB::table('ahg_workflow_step')->count();

        // Second run — without --overwrite, nothing should change
        $this->artisan('workflow:seed-spectrum')->assertSuccessful();
        $this->assertSame($firstCount, DB::table('ahg_workflow')->whereNotNull('spectrum_procedure')->count());
        $this->assertSame($firstSteps, DB::table('ahg_workflow_step')->count());
    }

    public function test_only_flag_installs_just_named_procedures(): void
    {
        $this->artisan('workflow:seed-spectrum --only=object_entry --only=cataloguing')->assertSuccessful();

        $procedures = DB::table('ahg_workflow')
            ->whereNotNull('spectrum_procedure')
            ->pluck('spectrum_procedure')
            ->toArray();

        $this->assertContains('object_entry', $procedures);
        $this->assertContains('cataloguing', $procedures);
        $this->assertNotContains('loans_in', $procedures, '--only must exclude other procedures');
        $this->assertNotContains('audit', $procedures);
    }

    public function test_overwrite_replaces_steps_for_existing_workflow(): void
    {
        $this->artisan('workflow:seed-spectrum --only=object_entry')->assertSuccessful();

        $wf = DB::table('ahg_workflow')->where('spectrum_procedure', 'object_entry')->first();
        $this->assertNotNull($wf);

        // Add a hand-customised step
        DB::table('ahg_workflow_step')->insert([
            'workflow_id'     => $wf->id,
            'name'            => 'Hand-customised step that --overwrite must remove',
            'step_order'      => 99,
            'step_type'       => 'review',
            'action_required' => 'approve_reject',
            'is_active'       => 1,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        $this->assertTrue(
            DB::table('ahg_workflow_step')->where('workflow_id', $wf->id)->where('name', 'like', 'Hand-customised%')->exists()
        );

        // Now run --overwrite
        $this->artisan('workflow:seed-spectrum --only=object_entry --overwrite')->assertSuccessful();

        // Hand-customised step is gone, only seed steps remain
        $this->assertFalse(
            DB::table('ahg_workflow_step')->where('workflow_id', $wf->id)->where('name', 'like', 'Hand-customised%')->exists()
        );
        $stepCount = DB::table('ahg_workflow_step')->where('workflow_id', $wf->id)->count();
        $this->assertGreaterThan(0, $stepCount);
    }

    public function test_seeded_workflows_are_tagged_with_correct_spectrum_code(): void
    {
        $this->artisan('workflow:seed-spectrum --only=cataloguing')->assertSuccessful();

        $wf = DB::table('ahg_workflow')->where('spectrum_procedure', 'cataloguing')->first();
        $this->assertNotNull($wf);
        $this->assertStringContainsString('Cataloguing', $wf->name);
        $this->assertSame(1, (int) $wf->is_active);
    }
}
