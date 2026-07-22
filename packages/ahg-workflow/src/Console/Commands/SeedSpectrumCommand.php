<?php

/**
 * SeedSpectrumCommand — installs the Spectrum 5.1 procedure starter pack:
 * 21 workflows tagged with `spectrum_procedure`, each with paraphrased
 * canonical steps.
 *
 * Source data: packages/ahg-workflow/database/spectrum_procedures.json.
 *
 * Heratio Spectrum#B (heratio#143 follow-up).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgWorkflow\Console\Commands;

use AhgWorkflow\Services\SpectrumProcedureCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedSpectrumCommand extends Command
{
    protected $signature = 'workflow:seed-spectrum
        {--overwrite : Replace name/description AND delete-and-reinstall steps for existing Spectrum workflows. WARNING: discards hand-customised steps for those procedures.}
        {--only=* : Install only specific procedure codes (e.g. --only=object_entry --only=cataloguing)}
        {--dry-run : Show what would change without writing anything}';

    protected $description = 'Install the museum procedure starter pack — 21 workflows with paraphrased canonical steps.';

    public function handle(): int
    {
        $jsonPath = base_path('packages/ahg-workflow/database/spectrum_procedures.json');
        if (! is_file($jsonPath)) {
            $this->error("Seed file not found: {$jsonPath}");

            return self::FAILURE;
        }

        $raw = json_decode((string) file_get_contents($jsonPath), true);
        if (! is_array($raw) || empty($raw['procedures'])) {
            $this->error("Seed file is malformed (no 'procedures' key).");

            return self::FAILURE;
        }

        $overwrite = (bool) $this->option('overwrite');
        $dryRun = (bool) $this->option('dry-run');
        $onlyCodes = array_filter((array) $this->option('only'));

        if ($dryRun) {
            $this->warn('DRY RUN — no DB writes will be made.');
        }
        if ($overwrite) {
            $this->warn('OVERWRITE mode — existing Spectrum workflow steps will be REPLACED. Hand-customised steps for those procedures will be lost.');
        }
        if (! empty($onlyCodes)) {
            $this->info('Limited to: '.implode(', ', $onlyCodes));
        }

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'invalid_code' => 0];
        $catalogCodes = SpectrumProcedureCatalog::codes();

        foreach ($raw['procedures'] as $code => $procedure) {
            if (! empty($onlyCodes) && ! in_array($code, $onlyCodes, true)) {
                continue;
            }
            if (! in_array($code, $catalogCodes, true)) {
                $this->warn("  ✗ {$code}: not in catalog (skipping)");
                $stats['invalid_code']++;

                continue;
            }

            $result = $this->seedProcedure($code, $procedure, $overwrite, $dryRun);
            $stats[$result['action']]++;
            $this->line('  '.$result['icon']." {$code}: ".$result['message']);
        }

        $this->newLine();
        $this->info(sprintf(
            'Done. Created: %d  Updated: %d  Skipped: %d  Invalid: %d',
            $stats['created'], $stats['updated'], $stats['skipped'], $stats['invalid_code']
        ));

        return self::SUCCESS;
    }

    /**
     * @return array{action:string, icon:string, message:string}
     */
    private function seedProcedure(string $code, array $procedure, bool $overwrite, bool $dryRun): array
    {
        $existing = DB::table('ahg_workflow')->where('spectrum_procedure', $code)->first();

        if ($existing === null) {
            // CREATE — workflow doesn't exist yet
            if ($dryRun) {
                return ['action' => 'created', 'icon' => '+', 'message' => 'would CREATE workflow + '.count($procedure['steps'] ?? []).' steps'];
            }
            // Spectrum#B v1.65.1 — wrap create+steps in a transaction so a mid-insert
            // failure (e.g. column truncation) doesn't leave behind a partial workflow.
            [$workflowId, $stepCount] = DB::transaction(function () use ($code, $procedure) {
                $wfId = $this->createWorkflow($code, $procedure);
                $count = $this->insertSteps($wfId, $procedure['steps'] ?? []);

                return [$wfId, $count];
            });

            return ['action' => 'created', 'icon' => '+', 'message' => "created workflow id={$workflowId} with {$stepCount} steps"];
        }

        // EXISTS
        if (! $overwrite) {
            return ['action' => 'skipped', 'icon' => '=', 'message' => "exists (id={$existing->id}), no --overwrite — skipping"];
        }

        // OVERWRITE — update metadata + replace steps
        if ($dryRun) {
            $existingStepCount = DB::table('ahg_workflow_step')->where('workflow_id', $existing->id)->count();

            return ['action' => 'updated', 'icon' => '~', 'message' => "would UPDATE workflow id={$existing->id} and REPLACE {$existingStepCount} existing steps with ".count($procedure['steps'] ?? []).' seed steps'];
        }

        DB::transaction(function () use ($existing, $procedure) {
            DB::table('ahg_workflow')->where('id', $existing->id)->update([
                'name' => $procedure['name'] ?? $existing->name,
                'description' => $procedure['description'] ?? $existing->description,
                'updated_at' => now(),
            ]);
            DB::table('ahg_workflow_step')->where('workflow_id', $existing->id)->delete();
            $this->insertSteps((int) $existing->id, $procedure['steps'] ?? []);
        });

        return ['action' => 'updated', 'icon' => '~', 'message' => "updated workflow id={$existing->id} (steps replaced)"];
    }

    private function createWorkflow(string $code, array $procedure): int
    {
        return (int) DB::table('ahg_workflow')->insertGetId([
            'name' => $procedure['name'] ?? "Spectrum: {$code}",
            'description' => $procedure['description'] ?? null,
            'scope_type' => 'global',
            'trigger_event' => 'submit',
            'applies_to' => 'information_object',
            'is_active' => 1,
            'is_default' => 0,
            'require_all_steps' => 1,
            'allow_parallel' => 0,
            'notification_enabled' => 1,
            'spectrum_procedure' => $code,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertSteps(int $workflowId, array $steps): int
    {
        $count = 0;
        foreach (array_values($steps) as $i => $step) {
            DB::table('ahg_workflow_step')->insert([
                'workflow_id' => $workflowId,
                'name' => $step['name'] ?? 'Step '.($i + 1),
                'description' => $step['description'] ?? null,
                'step_order' => $i + 1,
                'step_type' => $step['step_type'] ?? 'review',
                'action_required' => $step['action_required'] ?? 'approve_reject',
                'instructions' => $step['instructions'] ?? null,
                'is_optional' => $step['is_optional'] ?? 0,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $count++;
        }

        return $count;
    }
}
