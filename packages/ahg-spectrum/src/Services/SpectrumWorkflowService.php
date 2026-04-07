<?php

/**
 * SpectrumWorkflowService
 *
 * Core workflow logic for Spectrum 5.1 procedure linking and hand-offs.
 * Migrated from ahgSpectrumWorkflowService (ahgSpectrumPlugin).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 */

namespace AhgSpectrum\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SpectrumWorkflowService
{
    /**
     * Procedure trigger chains — which procedures are auto-started
     * when a given procedure reaches its final state.
     *
     * Source: Spectrum 5.1 procedure relationships + AtoM ahgSpectrumWorkflowService.
     */
    const TRIGGER_MAP = [
        'object_entry'       => ['acquisition', 'location_movement'],
        'acquisition'        => ['cataloguing', 'location_movement', 'valuation'],
        'location_movement'  => ['condition_checking'],
        'condition_checking' => ['conservation'],
        'conservation'       => ['condition_checking'],
        'valuation'          => ['insurance'],
        'loans_in'           => ['location_movement', 'condition_checking'],
        'loans_out'          => ['location_movement', 'condition_checking', 'insurance'],
        'loss_damage'        => ['insurance', 'conservation'],
        'deaccession'        => ['object_exit'],
        'cataloguing'        => ['rights_management'],
    ];

    /**
     * Trigger downstream procedures when a procedure reaches its final state.
     *
     * @return string[] List of procedure types that were triggered
     */
    public static function triggerDownstreamProcedures(int $recordId, string $completedProcedure, int $userId): array
    {
        $triggers = self::TRIGGER_MAP[$completedProcedure] ?? [];
        if (empty($triggers)) {
            return [];
        }

        $triggered = [];
        $completedLabel = self::getProcedureLabel($completedProcedure);

        foreach ($triggers as $downstreamType) {
            if (!self::canStartProcedure($recordId, $downstreamType)) {
                continue;
            }

            // Get initial state from config
            $config = self::getActiveConfig($downstreamType);
            if (!$config) {
                continue;
            }

            $configData = json_decode($config->config_json, true);
            $initialState = $configData['initial_state'] ?? 'pending';

            // Create or reset the workflow state to initial
            $existing = DB::table('spectrum_workflow_state')
                ->where('record_id', $recordId)
                ->where('procedure_type', $downstreamType)
                ->first();

            if ($existing) {
                DB::table('spectrum_workflow_state')
                    ->where('id', $existing->id)
                    ->update([
                        'current_state' => $initialState,
                        'updated_at'    => now(),
                    ]);
            } else {
                DB::table('spectrum_workflow_state')->insert([
                    'procedure_type' => $downstreamType,
                    'record_id'      => $recordId,
                    'current_state'  => $initialState,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }

            // Log the auto-trigger in history
            DB::table('spectrum_workflow_history')->insert([
                'procedure_type' => $downstreamType,
                'record_id'      => $recordId,
                'from_state'     => 'not_started',
                'to_state'       => $initialState,
                'transition_key' => 'auto_trigger',
                'user_id'        => $userId,
                'note'           => "Automatically triggered by completion of {$completedLabel}",
                'metadata'       => json_encode(['triggered_by' => $completedProcedure]),
                'created_at'     => now(),
            ]);

            $triggered[] = $downstreamType;
        }

        return $triggered;
    }

    /**
     * Check whether a procedure can be started for a given record.
     *
     * Returns false if:
     *  - No active config exists for the procedure
     *  - The procedure is already in a non-initial, non-final state (prevents circular re-triggering)
     */
    public static function canStartProcedure(int $recordId, string $procedureType): bool
    {
        $config = self::getActiveConfig($procedureType);
        if (!$config) {
            return false;
        }

        $configData   = json_decode($config->config_json, true);
        $initialState = $configData['initial_state'] ?? 'pending';

        // Derive final states
        $finalStates = $configData['final_states'] ?? [];
        if (empty($finalStates)) {
            $finalStates = self::deriveFinalStates($configData);
        }

        // Check current state
        $existing = DB::table('spectrum_workflow_state')
            ->where('record_id', $recordId)
            ->where('procedure_type', $procedureType)
            ->first();

        if (!$existing) {
            return true; // Never started — can start
        }

        $currentState = $existing->current_state;

        // Can start if still in initial state or already completed (allows re-trigger after restart)
        if ($currentState === $initialState || in_array($currentState, $finalStates)) {
            return true;
        }

        // Already in progress — don't re-trigger
        return false;
    }

    /**
     * Get linked procedures (upstream and downstream) for a procedure type.
     *
     * @return array{triggers: string[], triggered_by: string[]}
     */
    public static function getLinkedProcedures(string $procedureType): array
    {
        $triggers = self::TRIGGER_MAP[$procedureType] ?? [];

        // Find which procedures trigger this one
        $triggeredBy = [];
        foreach (self::TRIGGER_MAP as $source => $targets) {
            if (in_array($procedureType, $targets)) {
                $triggeredBy[] = $source;
            }
        }

        return [
            'triggers'     => $triggers,
            'triggered_by' => $triggeredBy,
        ];
    }

    /**
     * Get the current status of downstream procedures for a specific record.
     *
     * @return array[] Each entry: {procedure_type, label, current_state, state_label}
     */
    public static function getDownstreamStatus(int $recordId, string $procedureType): array
    {
        $triggers = self::TRIGGER_MAP[$procedureType] ?? [];
        if (empty($triggers)) {
            return [];
        }

        $result = [];
        foreach ($triggers as $downstreamType) {
            $state = DB::table('spectrum_workflow_state')
                ->where('record_id', $recordId)
                ->where('procedure_type', $downstreamType)
                ->first();

            $result[] = [
                'procedure_type' => $downstreamType,
                'label'          => self::getProcedureLabel($downstreamType),
                'current_state'  => $state->current_state ?? 'not_started',
                'state_label'    => $state ? ucwords(str_replace('_', ' ', $state->current_state)) : 'Not Started',
            ];
        }

        return $result;
    }

    /**
     * Get the current status of upstream procedures for a specific record.
     */
    public static function getUpstreamStatus(int $recordId, string $procedureType): array
    {
        $linked = self::getLinkedProcedures($procedureType);
        $triggeredBy = $linked['triggered_by'];
        if (empty($triggeredBy)) {
            return [];
        }

        $result = [];
        foreach ($triggeredBy as $upstreamType) {
            $state = DB::table('spectrum_workflow_state')
                ->where('record_id', $recordId)
                ->where('procedure_type', $upstreamType)
                ->first();

            $result[] = [
                'procedure_type' => $upstreamType,
                'label'          => self::getProcedureLabel($upstreamType),
                'current_state'  => $state->current_state ?? 'not_started',
                'state_label'    => $state ? ucwords(str_replace('_', ' ', $state->current_state)) : 'Not Started',
            ];
        }

        return $result;
    }

    /**
     * Whether a procedure has no downstream triggers (terminal).
     */
    public static function isTerminalProcedure(string $procedureType): bool
    {
        return empty(self::TRIGGER_MAP[$procedureType] ?? []);
    }

    /**
     * Get a human-readable label for a procedure type.
     */
    public static function getProcedureLabel(string $procedureType): string
    {
        return SpectrumNotificationService::getProcedureLabelStatic($procedureType);
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    protected static function getActiveConfig(string $procedureType): ?object
    {
        if (!Schema::hasTable('spectrum_workflow_config')) {
            return null;
        }

        return DB::table('spectrum_workflow_config')
            ->where('procedure_type', $procedureType)
            ->where('is_active', 1)
            ->first();
    }

    protected static function deriveFinalStates(array $configData): array
    {
        $states      = $configData['states'] ?? [];
        $transitions = $configData['transitions'] ?? [];
        $finalStates = [];

        foreach ($states as $state) {
            $hasOutgoing = false;
            foreach ($transitions as $tKey => $tDef) {
                if ($tKey === 'restart') {
                    continue;
                }
                if (isset($tDef['from']) && in_array($state, $tDef['from'])) {
                    $hasOutgoing = true;
                    break;
                }
            }
            if (!$hasOutgoing) {
                $finalStates[] = $state;
            }
        }

        return $finalStates;
    }
}
