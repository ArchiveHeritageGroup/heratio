<?php

/**
 * ConditionService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */



namespace AhgInformationObjectManage\Services;

use Illuminate\Support\Facades\DB;

/**
 * Service for condition report and SPECTRUM condition check operations.
 * Migrated from /usr/share/nginx/archive/atom-ahg-plugins/ahgConditionPlugin/
 *
 * Tables: condition_report, condition_damage, spectrum_condition_check
 */
class ConditionService
{
    /**
     * Get all condition reports for an information object.
     */
    public function getReportsForObject(int $objectId): \Illuminate\Support\Collection
    {
        return DB::table('condition_report')
            ->where('information_object_id', $objectId)
            ->orderBy('assessment_date', 'desc')
            ->get();
    }

    /**
     * Get the most recent condition report for an object.
     */
    public function getLatestReport(int $objectId): ?object
    {
        return DB::table('condition_report')
            ->where('information_object_id', $objectId)
            ->orderBy('assessment_date', 'desc')
            ->first();
    }

    /**
     * Get a single condition report with its damages.
     */
    public function getReport(int $reportId): ?object
    {
        $report = DB::table('condition_report')
            ->where('id', $reportId)
            ->first();

        if ($report) {
            $report->damages = $this->getDamages($reportId);
        }

        return $report;
    }

    /**
     * Get damage records for a condition report.
     */
    public function getDamages(int $reportId): \Illuminate\Support\Collection
    {
        return DB::table('condition_damage')
            ->where('condition_report_id', $reportId)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get SPECTRUM condition checks for an object.
     */
    public function getSpectrumChecks(int $objectId): \Illuminate\Support\Collection
    {
        return DB::table('spectrum_condition_check')
            ->where('object_id', $objectId)
            ->orderBy('check_date', 'desc')
            ->get();
    }

    /**
     * Create a new condition report.
     *
     * @return int The new report ID
     */
    public function createReport(array $data): int
    {
        return DB::table('condition_report')->insertGetId([
            'information_object_id' => $data['information_object_id'],
            'assessor_user_id'      => $data['assessor_user_id'] ?? null,
            'assessment_date'       => $data['assessment_date'],
            'context'               => $data['context'] ?? 'routine',
            'overall_rating'        => $data['overall_rating'] ?? 'good',
            'summary'               => $data['summary'] ?? null,
            'recommendations'       => $data['recommendations'] ?? null,
            'priority'              => $data['priority'] ?? 'normal',
            'next_check_date'       => $data['next_check_date'] ?? null,
            'environmental_notes'   => $data['environmental_notes'] ?? null,
            'handling_notes'        => $data['handling_notes'] ?? null,
            'display_notes'         => $data['display_notes'] ?? null,
            'storage_notes'         => $data['storage_notes'] ?? null,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
    }

    /**
     * Update an existing condition report.
     */
    public function updateReport(int $id, array $data): bool
    {
        $update = [];

        $fields = [
            'assessor_user_id', 'assessment_date', 'context', 'overall_rating',
            'summary', 'recommendations', 'priority', 'next_check_date',
            'environmental_notes', 'handling_notes', 'display_notes', 'storage_notes',
        ];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        $update['updated_at'] = now();

        return DB::table('condition_report')
            ->where('id', $id)
            ->update($update) >= 0;
    }

    /**
     * Delete a condition report and all its associated damages.
     */
    public function deleteReport(int $id): bool
    {
        DB::table('condition_damage')
            ->where('condition_report_id', $id)
            ->delete();

        return DB::table('condition_report')
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * Add a damage record to a condition report.
     *
     * @return int The new damage ID
     */
    public function addDamage(int $reportId, array $data): int
    {
        return DB::table('condition_damage')->insertGetId([
            'condition_report_id' => $reportId,
            'damage_type'         => $data['damage_type'],
            'location'            => $data['location'] ?? 'overall',
            'severity'            => $data['severity'] ?? 'minor',
            'description'         => $data['description'] ?? null,
            'dimensions'          => $data['dimensions'] ?? null,
            'is_active'           => $data['is_active'] ?? 1,
            'treatment_required'  => $data['treatment_required'] ?? 0,
            'treatment_notes'     => $data['treatment_notes'] ?? null,
            'created_at'          => now(),
        ]);
    }

    /**
     * Overall rating options.
     */
    public function getRatingOptions(): array
    {
        return [
            'excellent' => 'Excellent',
            'good'      => 'Good',
            'fair'      => 'Fair',
            'poor'      => 'Poor',
            'unacceptable' => 'Unacceptable',
        ];
    }

    /**
     * Context options for condition assessments.
     */
    public function getContextOptions(): array
    {
        return [
            'routine'       => 'Routine',
            'acquisition'   => 'Acquisition',
            'loan_in'       => 'Loan In',
            'loan_out'      => 'Loan Out',
            'exhibition'    => 'Exhibition',
            'conservation'  => 'Conservation',
            'storage'       => 'Storage',
            'transit'       => 'Transit',
            'damage_report' => 'Damage Report',
            'insurance'     => 'Insurance',
            'audit'         => 'Audit',
            'other'         => 'Other',
        ];
    }

    /**
     * Priority options.
     */
    public function getPriorityOptions(): array
    {
        return [
            'low'      => 'Low',
            'normal'   => 'Normal',
            'high'     => 'High',
            'urgent'   => 'Urgent',
            'critical' => 'Critical',
        ];
    }

    /**
     * Damage type options.
     */
    public function getDamageTypeOptions(): array
    {
        return [
            'tear'          => 'Tear',
            'crack'         => 'Crack',
            'scratch'       => 'Scratch',
            'stain'         => 'Stain',
            'foxing'        => 'Foxing',
            'mould'         => 'Mould',
            'insect_damage' => 'Insect Damage',
            'water_damage'  => 'Water Damage',
            'fire_damage'   => 'Fire Damage',
            'fading'        => 'Fading',
            'discolouration' => 'Discolouration',
            'deformation'   => 'Deformation',
            'loss'          => 'Loss',
            'abrasion'      => 'Abrasion',
            'corrosion'     => 'Corrosion',
            'other'         => 'Other',
        ];
    }

    /**
     * Severity options.
     */
    public function getSeverityOptions(): array
    {
        return [
            'negligible' => 'Negligible',
            'minor'      => 'Minor',
            'moderate'   => 'Moderate',
            'severe'     => 'Severe',
            'critical'   => 'Critical',
        ];
    }
}
