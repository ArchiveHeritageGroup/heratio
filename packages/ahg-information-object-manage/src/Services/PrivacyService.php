<?php

/**
 * PrivacyService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems.co.za
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

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service for Privacy/PII/POPIA/GDPR operations.
 * Migrated from /usr/share/nginx/archive/atom-ahg-plugins/ahgPrivacyPlugin/
 *
 * Tables: privacy_visual_redaction, privacy_dsar_request, privacy_processing_activity, privacy_breach
 */
class PrivacyService
{
    /**
     * Get all visual redactions for an information object.
     */
    public function getRedactions(int $objectId): Collection
    {
        try {
            return DB::table('privacy_visual_redaction')
                ->where('object_id', $objectId)
                ->select(
                    'id',
                    'object_id',
                    'digital_object_id',
                    'page_number',
                    'region_type',
                    'coordinates',
                    'normalized',
                    'source',
                    'linked_entity_id',
                    'label',
                    'color',
                    'status',
                    'created_by',
                    'reviewed_by',
                    'reviewed_at',
                    'applied_at',
                    'created_at',
                    'updated_at'
                )
                ->orderBy('page_number')
                ->orderBy('created_at')
                ->get();
        } catch (\Illuminate\Database\QueryException $e) {
            return collect();
        }
    }

    /**
     * Save (insert or update) a visual redaction.
     */
    public function saveRedaction(array $data): int
    {
        $record = [
            'object_id'         => $data['object_id'],
            'digital_object_id' => $data['digital_object_id'] ?? null,
            'page_number'       => $data['page_number'] ?? 1,
            'region_type'       => $data['region_type'] ?? 'rectangle',
            'coordinates'       => is_array($data['coordinates'] ?? null) ? json_encode($data['coordinates']) : ($data['coordinates'] ?? '{}'),
            'normalized'        => $data['normalized'] ?? 1,
            'source'            => $data['source'] ?? 'manual',
            'linked_entity_id'  => $data['linked_entity_id'] ?? null,
            'label'             => $data['label'] ?? null,
            'color'             => $data['color'] ?? '#000000',
            'status'            => $data['status'] ?? 'pending',
            'created_by'        => $data['created_by'] ?? null,
            'updated_at'        => now(),
        ];

        if (!empty($data['id'])) {
            DB::table('privacy_visual_redaction')
                ->where('id', $data['id'])
                ->update($record);

            return (int) $data['id'];
        }

        $record['created_at'] = now();

        return DB::table('privacy_visual_redaction')->insertGetId($record);
    }

    /**
     * Delete a visual redaction by ID.
     */
    public function deleteRedaction(int $id): bool
    {
        try {
            return DB::table('privacy_visual_redaction')
                ->where('id', $id)
                ->delete() > 0;
        } catch (\Illuminate\Database\QueryException $e) {
            return false;
        }
    }

    /**
     * Get all DSAR (Data Subject Access Request) records.
     */
    public function getDsarRequests(): Collection
    {
        try {
            return DB::table('privacy_dsar_request')
                ->select(
                    'id',
                    'reference',
                    'request_type',
                    'data_subject_name',
                    'data_subject_email',
                    'data_subject_id_type',
                    'received_date',
                    'deadline_date',
                    'completed_date',
                    'status',
                    'notes',
                    'assigned_to',
                    'created_by',
                    'created_at'
                )
                ->orderBy('received_date', 'desc')
                ->get();
        } catch (\Illuminate\Database\QueryException $e) {
            return collect();
        }
    }

    /**
     * Get all processing activities.
     */
    public function getProcessingActivities(): Collection
    {
        try {
            return DB::table('privacy_processing_activity')
                ->select(
                    'id',
                    'name',
                    'description',
                    'jurisdiction',
                    'purpose',
                    'lawful_basis',
                    'lawful_basis_code',
                    'data_categories',
                    'data_subjects',
                    'recipients',
                    'third_countries',
                    'transfers',
                    'retention_period',
                    'security_measures',
                    'dpia_required',
                    'dpia_completed',
                    'dpia_date',
                    'status',
                    'owner',
                    'department',
                    'created_by',
                    'created_at',
                    'updated_at',
                    'next_review_date',
                    'submitted_at',
                    'submitted_by',
                    'approved_at',
                    'approved_by',
                    'rejected_at',
                    'rejected_by',
                    'rejection_reason',
                    'assigned_officer_id'
                )
                ->orderBy('name')
                ->get();
        } catch (\Illuminate\Database\QueryException $e) {
            return collect();
        }
    }

    /**
     * Get dashboard statistics: DSAR counts by status, breach counts, processing activity counts,
     * redaction counts.
     */
    public function getDashboardStats(): object
    {
        $stats = new \stdClass();

        // DSAR stats
        try {
            $stats->dsar_total = DB::table('privacy_dsar_request')->count();
            $stats->dsar_pending = DB::table('privacy_dsar_request')
                ->where('status', 'pending')
                ->count();
            $stats->dsar_in_progress = DB::table('privacy_dsar_request')
                ->where('status', 'in_progress')
                ->count();
            $stats->dsar_completed = DB::table('privacy_dsar_request')
                ->where('status', 'completed')
                ->count();
            $stats->dsar_overdue = DB::table('privacy_dsar_request')
                ->where('deadline_date', '<', now())
                ->whereNull('completed_date')
                ->whereIn('status', ['pending', 'in_progress'])
                ->count();
        } catch (\Illuminate\Database\QueryException $e) {
            $stats->dsar_total = 0;
            $stats->dsar_pending = 0;
            $stats->dsar_in_progress = 0;
            $stats->dsar_completed = 0;
            $stats->dsar_overdue = 0;
        }

        // Breach stats
        try {
            $stats->breach_total = DB::table('privacy_breach')->count();
            $stats->breach_open = DB::table('privacy_breach')
                ->whereNotIn('status', ['resolved', 'closed'])
                ->count();
            $stats->breach_critical = DB::table('privacy_breach')
                ->where('severity', 'critical')
                ->whereNotIn('status', ['resolved', 'closed'])
                ->count();
        } catch (\Illuminate\Database\QueryException $e) {
            $stats->breach_total = 0;
            $stats->breach_open = 0;
            $stats->breach_critical = 0;
        }

        // Processing activity stats
        try {
            $stats->processing_total = DB::table('privacy_processing_activity')->count();
            $stats->processing_active = DB::table('privacy_processing_activity')
                ->where('status', 'active')
                ->count();
            $stats->processing_review_due = DB::table('privacy_processing_activity')
                ->whereNotNull('next_review_date')
                ->where('next_review_date', '<=', now()->addDays(30))
                ->count();
        } catch (\Illuminate\Database\QueryException $e) {
            $stats->processing_total = 0;
            $stats->processing_active = 0;
            $stats->processing_review_due = 0;
        }

        // Redaction stats
        try {
            $stats->redaction_total = DB::table('privacy_visual_redaction')->count();
            $stats->redaction_pending = DB::table('privacy_visual_redaction')
                ->where('status', 'pending')
                ->count();
            $stats->redaction_applied = DB::table('privacy_visual_redaction')
                ->where('status', 'applied')
                ->count();
        } catch (\Illuminate\Database\QueryException $e) {
            $stats->redaction_total = 0;
            $stats->redaction_pending = 0;
            $stats->redaction_applied = 0;
        }

        // PII scan stats (from NER entities with PII-related types)
        try {
            $piiTypes = ['SA_ID', 'PASSPORT', 'EMAIL', 'PHONE', 'ADDRESS', 'DOB', 'BANK', 'TAX', 'MEDICAL', 'BIOMETRIC', 'IP_ADDRESS'];
            $stats->pii_scans_completed = DB::table('ahg_ner_extraction')
                ->where('status', '!=', 'pending')
                ->count();
            $stats->pii_detections = DB::table('ahg_ner_entity')
                ->whereIn('entity_type', $piiTypes)
                ->count();
        } catch (\Illuminate\Database\QueryException $e) {
            $stats->pii_scans_completed = 0;
            $stats->pii_detections = 0;
        }

        return $stats;
    }
}
