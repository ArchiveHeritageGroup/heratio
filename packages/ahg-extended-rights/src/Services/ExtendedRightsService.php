<?php

/**
 * ExtendedRightsService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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


use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ExtendedRightsService
 *
 * Comprehensive rights management service handling:
 * - Rights Records (PREMIS)
 * - Rights Statements (rightsstatements.org)
 * - Creative Commons licenses
 * - Traditional Knowledge Labels
 * - Orphan works due diligence
 * - Embargo management
 * - Territory restrictions
 * - PREMIS rights grants
 * - Reporting and CSV export
 *
 * Migrated from AtoM ahgRightsPlugin RightsService.
 */
class ExtendedRightsService
{
    protected string $culture;

    // PREMIS acts
    public const ACT_RENDER = 'render';
    public const ACT_DISSEMINATE = 'disseminate';
    public const ACT_REPLICATE = 'replicate';
    public const ACT_MIGRATE = 'migrate';
    public const ACT_MODIFY = 'modify';
    public const ACT_DELETE = 'delete';
    public const ACT_PRINT = 'print';
    public const ACT_USE = 'use';

    // Embargo types
    public const EMBARGO_FULL = 'full';
    public const EMBARGO_METADATA = 'metadata_only';
    public const EMBARGO_DIGITAL = 'digital_only';
    public const EMBARGO_PARTIAL = 'partial';

    public function __construct()
    {
        $this->culture = app()->getLocale() ?: 'en';
    }

    // =========================================================================
    // RIGHTS RECORDS
    // =========================================================================

    /**
     * Get all rights records for an object
     */
    public function getRightsForObject(int $objectId): Collection
    {
        if (!Schema::hasTable('rights_record')) {
            return collect();
        }

        return DB::table('rights_record as r')
            ->leftJoin('rights_record_i18n as ri', function ($join) {
                $join->on('r.id', '=', 'ri.id')
                    ->where('ri.culture', '=', $this->culture);
            })
            ->leftJoin('rights_statement as rs', 'r.rights_statement_id', '=', 'rs.id')
            ->leftJoin('rights_statement_i18n as rsi', function ($join) {
                $join->on('rs.id', '=', 'rsi.rights_statement_id')
                    ->where('rsi.culture', '=', $this->culture);
            })
            ->leftJoin('rights_cc_license as cc', 'r.cc_license_id', '=', 'cc.id')
            ->leftJoin('rights_cc_license_i18n as cci', function ($join) {
                $join->on('cc.id', '=', 'cci.id')
                    ->where('cci.culture', '=', $this->culture);
            })
            ->where('r.object_id', $objectId)
            ->select([
                'r.*',
                'ri.rights_note',
                'ri.restriction_note',
                'rs.code as rights_statement_code',
                'rs.uri as rights_statement_uri',
                'rsi.name as rights_statement_name',
                'cc.code as cc_license_code',
                'cc.uri as cc_license_uri',
                'cc.badge_url as cc_badge_url',
                'cci.name as cc_license_name',
            ])
            ->orderBy('r.created_at', 'desc')
            ->get();
    }

    /**
     * Get single rights record
     */
    public function getRightsRecord(int $id): ?object
    {
        if (!Schema::hasTable('rights_record')) {
            return null;
        }

        $record = DB::table('rights_record as r')
            ->leftJoin('rights_record_i18n as ri', function ($join) {
                $join->on('r.id', '=', 'ri.id')
                    ->where('ri.culture', '=', $this->culture);
            })
            ->where('r.id', $id)
            ->select(['r.*', 'ri.rights_note', 'ri.restriction_note'])
            ->first();

        if ($record) {
            $record->grants = $this->getGrantsForRecord($id);
            $record->territories = $this->getTerritoriesForRecord($id);
        }

        return $record;
    }

    /**
     * Save (create or update) a rights record
     */
    public function saveRightsRecord(array $data): int
    {
        $id = $data['id'] ?? null;

        $recordData = [
            'object_id' => $data['object_id'],
            'basis' => $data['basis'] ?? 'copyright',
            'rights_statement_id' => $data['rights_statement_id'] ?? null,
            'cc_license_id' => $data['cc_license_id'] ?? null,
            'copyright_status' => $data['copyright_status'] ?? null,
            'copyright_holder' => $data['copyright_holder'] ?? null,
            'copyright_jurisdiction' => $data['copyright_jurisdiction'] ?? null,
            'copyright_determination_date' => $data['copyright_status_date'] ?? null,
            'copyright_note' => $data['copyright_note'] ?? null,
            'copyright_expiry_date' => $data['copyright_expiry_date'] ?? null,
            'license_type' => $data['license_type'] ?? null,
            'license_identifier' => $data['license_identifier'] ?? null,
            'license_terms' => $data['license_terms'] ?? null,
            'license_url' => $data['license_url'] ?? null,
            'license_note' => $data['license_note'] ?? null,
            'statute_jurisdiction' => $data['statute_jurisdiction'] ?? null,
            'statute_citation' => $data['statute_citation'] ?? null,
            'statute_determination_date' => $data['statute_determination_date'] ?? null,
            'statute_note' => $data['statute_note'] ?? null,
            'donor_name' => $data['donor_name'] ?? null,
            'policy_identifier' => $data['policy_identifier'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'end_date_open' => $data['end_date_open'] ?? 0,
            'rights_holder_name' => $data['rights_holder_name'] ?? null,
        ];

        if ($id) {
            $recordData['updated_at'] = now();
            DB::table('rights_record')->where('id', $id)->update($recordData);
        } else {
            $recordData['created_by'] = $data['created_by'] ?? auth()->id();
            $recordData['created_at'] = now();
            $id = DB::table('rights_record')->insertGetId($recordData);
        }

        // Update i18n
        DB::table('rights_record_i18n')->updateOrInsert(
            ['id' => $id, 'culture' => $this->culture],
            [
                'rights_note' => $data['rights_note'] ?? null,
                'restriction_note' => $data['restriction_note'] ?? null,
            ]
        );

        // Handle granted rights
        if (isset($data['granted_rights'])) {
            // Remove existing grants
            DB::table('rights_grant')->where('rights_record_id', $id)->delete();

            foreach ($data['granted_rights'] as $grant) {
                if (!empty($grant['act'])) {
                    $this->createGrant($id, $grant);
                }
            }
        }

        return $id;
    }

    /**
     * Delete rights record
     */
    public function deleteRightsRecord(int $id): bool
    {
        DB::table('rights_grant')->where('rights_record_id', $id)->delete();
        DB::table('rights_record_i18n')->where('id', $id)->delete();

        return DB::table('rights_record')->where('id', $id)->delete() > 0;
    }

    // =========================================================================
    // RIGHTS GRANTS (PREMIS Acts)
    // =========================================================================

    public function getGrantsForRecord(int $recordId): Collection
    {
        if (!Schema::hasTable('rights_grant')) {
            return collect();
        }

        return DB::table('rights_grant as g')
            ->leftJoin('rights_grant_i18n as gi', function ($join) {
                $join->on('g.id', '=', 'gi.id')
                    ->where('gi.culture', '=', $this->culture);
            })
            ->where('g.rights_record_id', $recordId)
            ->select(['g.*', 'gi.restriction_note'])
            ->get();
    }

    public function createGrant(int $recordId, array $data): int
    {
        $id = DB::table('rights_grant')->insertGetId([
            'rights_record_id' => $recordId,
            'act' => $data['act'],
            'restriction' => $data['restriction'] ?? 'allow',
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'condition_type' => $data['condition_type'] ?? null,
            'condition_value' => $data['condition_value'] ?? null,
            'created_at' => now(),
        ]);

        if (!empty($data['restriction_note'])) {
            DB::table('rights_grant_i18n')->insert([
                'id' => $id,
                'culture' => $this->culture,
                'restriction_note' => $data['restriction_note'],
            ]);
        }

        return $id;
    }

    // =========================================================================
    // EMBARGO MANAGEMENT
    // =========================================================================

    public function getEmbargo(int $objectId): ?object
    {
        if (!Schema::hasTable('rights_embargo')) {
            return null;
        }

        return DB::table('rights_embargo as e')
            ->leftJoin('rights_embargo_i18n as ei', function ($join) {
                $join->on('e.id', '=', 'ei.id')
                    ->where('ei.culture', '=', $this->culture);
            })
            ->where('e.object_id', $objectId)
            ->where('e.status', 'active')
            ->select(['e.*', 'ei.reason_note', 'ei.internal_note'])
            ->first();
    }

    public function getActiveEmbargoes(?string $status = 'active'): Collection
    {
        if (!Schema::hasTable('rights_embargo')) {
            return collect();
        }

        $query = DB::table('rights_embargo as e')
            ->leftJoin('rights_embargo_i18n as ei', function ($join) {
                $join->on('e.id', '=', 'ei.id')
                    ->where('ei.culture', '=', $this->culture);
            })
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('e.object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'e.object_id')
            ->select([
                'e.*',
                'ei.reason_note',
                'ioi.title as object_title',
                's.slug',
            ]);

        if ($status !== 'all') {
            $query->where('e.status', $status);
        }

        return $query->orderBy('e.end_date')->limit(100)->get();
    }

    public function getExpiringEmbargoes(int $days = 30): Collection
    {
        if (!Schema::hasTable('rights_embargo')) {
            return collect();
        }

        return DB::table('rights_embargo as e')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('e.object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'e.object_id')
            ->where('e.status', 'active')
            ->whereNotNull('e.end_date')
            ->whereRaw('e.end_date <= DATE_ADD(NOW(), INTERVAL ? DAY)', [$days])
            ->where('e.end_date', '>=', now()->toDateString())
            ->select(['e.*', 'ioi.title as object_title', 's.slug'])
            ->orderBy('e.end_date')
            ->get();
    }

    public function getEmbargoesForReview(): Collection
    {
        if (!Schema::hasTable('rights_embargo')) {
            return collect();
        }

        return DB::table('rights_embargo as e')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('e.object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'e.object_id')
            ->where('e.status', 'active')
            ->whereNotNull('e.review_date')
            ->where('e.review_date', '<=', now()->toDateString())
            ->select(['e.*', 'ioi.title as object_title', 's.slug'])
            ->orderBy('e.review_date')
            ->get();
    }

    public function getEmbargoById(int $id): ?object
    {
        if (!Schema::hasTable('rights_embargo')) {
            return null;
        }

        return DB::table('rights_embargo as e')
            ->leftJoin('rights_embargo_i18n as ei', function ($join) {
                $join->on('e.id', '=', 'ei.id')
                    ->where('ei.culture', '=', $this->culture);
            })
            ->where('e.id', $id)
            ->select(['e.*', 'ei.reason_note', 'ei.internal_note'])
            ->first();
    }

    public function getEmbargoLog(int $embargoId): Collection
    {
        if (!Schema::hasTable('rights_embargo_log')) {
            return collect();
        }

        return DB::table('rights_embargo_log')
            ->where('embargo_id', $embargoId)
            ->orderBy('performed_at', 'desc')
            ->get();
    }

    public function createEmbargo(array $data): int
    {
        $id = DB::table('rights_embargo')->insertGetId([
            'object_id' => $data['object_id'],
            'embargo_type' => $data['embargo_type'] ?? 'full',
            'reason' => $data['reason'],
            'start_date' => $data['start_date'] ?? now()->toDateString(),
            'end_date' => $data['end_date'] ?? null,
            'auto_release' => $data['auto_release'] ?? 1,
            'review_date' => $data['review_date'] ?? null,
            'review_interval_months' => $data['review_interval_months'] ?? 12,
            'notify_before_days' => $data['notify_before_days'] ?? 30,
            'notify_emails' => json_encode(array_filter(
                array_map('trim', explode(',', $data['notify_emails'] ?? ''))
            )),
            'status' => 'active',
            'created_by' => $data['created_by'] ?? auth()->id(),
            'created_at' => now(),
        ]);

        DB::table('rights_embargo_i18n')->insert([
            'id' => $id,
            'culture' => $this->culture,
            'reason_note' => $data['reason_note'] ?? null,
            'internal_note' => $data['internal_note'] ?? null,
        ]);

        $this->logEmbargoAction($id, 'created', null, 'active');

        return $id;
    }

    public function updateEmbargo(int $id, array $data): bool
    {
        $embargo = DB::table('rights_embargo')->where('id', $id)->first();
        if (!$embargo) {
            return false;
        }

        DB::table('rights_embargo')->where('id', $id)->update([
            'embargo_type' => $data['embargo_type'],
            'reason' => $data['reason'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'auto_release' => $data['auto_release'] ?? 0,
            'review_date' => $data['review_date'] ?? null,
            'review_interval_months' => $data['review_interval_months'] ?? 12,
            'notify_before_days' => $data['notify_before_days'] ?? 30,
            'notify_emails' => json_encode(array_filter(
                array_map('trim', explode(',', $data['notify_emails'] ?? ''))
            )),
            'updated_at' => now(),
        ]);

        DB::table('rights_embargo_i18n')->updateOrInsert(
            ['id' => $id, 'culture' => $this->culture],
            [
                'reason_note' => $data['reason_note'] ?? null,
                'internal_note' => $data['internal_note'] ?? null,
            ]
        );

        $this->logEmbargoAction($id, 'updated', $embargo->status, $embargo->status);

        return true;
    }

    public function liftEmbargo(int $id, ?string $reason = null, ?int $userId = null): bool
    {
        $embargo = DB::table('rights_embargo')->where('id', $id)->first();
        if (!$embargo) {
            return false;
        }

        DB::table('rights_embargo')->where('id', $id)->update([
            'status' => 'lifted',
            'lifted_at' => now(),
            'lifted_by' => $userId ?? auth()->id(),
            'lift_reason' => $reason,
            'updated_at' => now(),
        ]);

        $this->logEmbargoAction($id, 'lifted', $embargo->status, 'lifted', null, null, $reason, $userId);

        return true;
    }

    public function extendEmbargo(int $id, string $newEndDate, ?string $reason = null, ?int $userId = null): bool
    {
        $embargo = DB::table('rights_embargo')->where('id', $id)->first();
        if (!$embargo) {
            return false;
        }

        $oldEndDate = $embargo->end_date;

        DB::table('rights_embargo')->where('id', $id)->update([
            'end_date' => $newEndDate,
            'status' => 'active',
            'notification_sent' => 0,
            'updated_at' => now(),
        ]);

        $this->logEmbargoAction($id, 'extended', $embargo->status, 'active', $oldEndDate, $newEndDate, $reason, $userId);

        return true;
    }

    public function processExpiredEmbargoes(): int
    {
        if (!Schema::hasTable('rights_embargo')) {
            return 0;
        }

        $expired = DB::table('rights_embargo')
            ->where('status', 'active')
            ->where('auto_release', 1)
            ->whereNotNull('end_date')
            ->where('end_date', '<', now()->toDateString())
            ->get();

        $count = 0;
        foreach ($expired as $embargo) {
            DB::table('rights_embargo')->where('id', $embargo->id)->update([
                'status' => 'expired',
                'updated_at' => now(),
            ]);
            $this->logEmbargoAction($embargo->id, 'auto_released', 'active', 'expired');
            ++$count;
        }

        return $count;
    }

    protected function logEmbargoAction(
        int $embargoId,
        string $action,
        ?string $oldStatus,
        ?string $newStatus,
        ?string $oldEndDate = null,
        ?string $newEndDate = null,
        ?string $notes = null,
        ?int $userId = null
    ): void {
        if (!Schema::hasTable('rights_embargo_log')) {
            return;
        }

        DB::table('rights_embargo_log')->insert([
            'embargo_id' => $embargoId,
            'action' => $action,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'old_end_date' => $oldEndDate,
            'new_end_date' => $newEndDate,
            'notes' => $notes,
            'performed_by' => $userId ?? auth()->id(),
            'performed_at' => now(),
        ]);
    }

    // =========================================================================
    // ORPHAN WORKS
    // =========================================================================

    public function getOrphanWorks(?string $status = 'all'): Collection
    {
        if (!Schema::hasTable('rights_orphan_work')) {
            return collect();
        }

        $query = DB::table('rights_orphan_work as o')
            ->leftJoin('rights_orphan_work_i18n as oi', function ($join) {
                $join->on('o.id', '=', 'oi.id')
                    ->where('oi.culture', '=', $this->culture);
            })
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('o.object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'o.object_id')
            ->select([
                'o.*',
                'oi.notes',
                'oi.search_summary',
                'ioi.title as object_title',
                's.slug',
            ]);

        if ($status !== 'all') {
            $query->where('o.status', $status);
        }

        return $query->orderBy('o.created_at', 'desc')->limit(100)->get();
    }

    public function getOrphanWork(int $objectId): ?object
    {
        if (!Schema::hasTable('rights_orphan_work')) {
            return null;
        }

        return DB::table('rights_orphan_work as o')
            ->leftJoin('rights_orphan_work_i18n as oi', function ($join) {
                $join->on('o.id', '=', 'oi.id')
                    ->where('oi.culture', '=', $this->culture);
            })
            ->where('o.object_id', $objectId)
            ->select(['o.*', 'oi.notes', 'oi.search_summary'])
            ->first();
    }

    public function getOrphanWorkById(int $id): ?object
    {
        if (!Schema::hasTable('rights_orphan_work')) {
            return null;
        }

        return DB::table('rights_orphan_work as o')
            ->leftJoin('rights_orphan_work_i18n as oi', function ($join) {
                $join->on('o.id', '=', 'oi.id')
                    ->where('oi.culture', '=', $this->culture);
            })
            ->where('o.id', $id)
            ->select(['o.*', 'oi.notes', 'oi.search_summary'])
            ->first();
    }

    public function getOrphanWorkSearchSteps(int $orphanWorkId): Collection
    {
        if (!Schema::hasTable('rights_orphan_search_step')) {
            return collect();
        }

        return DB::table('rights_orphan_search_step')
            ->where('orphan_work_id', $orphanWorkId)
            ->orderBy('search_date')
            ->get();
    }

    public function createOrphanWork(array $data): int
    {
        $id = DB::table('rights_orphan_work')->insertGetId([
            'object_id' => $data['object_id'],
            'status' => 'in_progress',
            'work_type' => $data['work_type'],
            'search_started_date' => $data['search_started_date'] ?? now()->toDateString(),
            'search_jurisdiction' => $data['search_jurisdiction'] ?? 'ZA',
            'intended_use' => $data['intended_use'] ?? null,
            'proposed_fee' => $data['proposed_fee'] ?? null,
            'created_by' => $data['created_by'] ?? auth()->id(),
            'created_at' => now(),
        ]);

        DB::table('rights_orphan_work_i18n')->insert([
            'id' => $id,
            'culture' => $this->culture,
            'notes' => $data['notes'] ?? null,
        ]);

        return $id;
    }

    public function updateOrphanWork(int $id, array $data): bool
    {
        DB::table('rights_orphan_work')->where('id', $id)->update([
            'work_type' => $data['work_type'],
            'search_jurisdiction' => $data['search_jurisdiction'] ?? 'ZA',
            'intended_use' => $data['intended_use'] ?? null,
            'proposed_fee' => $data['proposed_fee'] ?? null,
            'updated_at' => now(),
        ]);

        DB::table('rights_orphan_work_i18n')->updateOrInsert(
            ['id' => $id, 'culture' => $this->culture],
            ['notes' => $data['notes'] ?? null]
        );

        return true;
    }

    public function addOrphanWorkSearchStep(int $orphanWorkId, array $data): int
    {
        return DB::table('rights_orphan_search_step')->insertGetId([
            'orphan_work_id' => $orphanWorkId,
            'source_type' => $data['source_type'],
            'source_name' => $data['source_name'],
            'source_url' => $data['source_url'] ?? null,
            'search_date' => $data['search_date'] ?? now()->toDateString(),
            'search_terms' => $data['search_terms'] ?? null,
            'results_found' => $data['results_found'] ?? 0,
            'results_description' => $data['results_description'] ?? null,
            'performed_by' => $data['performed_by'] ?? auth()->id(),
            'created_at' => now(),
        ]);
    }

    public function completeOrphanWorkSearch(int $id, bool $rightsHolderFound = false): bool
    {
        return DB::table('rights_orphan_work')->where('id', $id)->update([
            'status' => $rightsHolderFound ? 'rights_holder_found' : 'completed',
            'search_completed_date' => now()->toDateString(),
            'rights_holder_found' => $rightsHolderFound ? 1 : 0,
            'updated_at' => now(),
        ]) > 0;
    }

    // =========================================================================
    // TK LABELS
    // =========================================================================

    public function getTkLabels(): Collection
    {
        if (!Schema::hasTable('rights_tk_label')) {
            return collect();
        }

        return DB::table('rights_tk_label as tl')
            ->leftJoin('rights_tk_label_i18n as tli', function ($join) {
                $join->on('tl.id', '=', 'tli.id')
                    ->where('tli.culture', '=', $this->culture);
            })
            ->where('tl.is_active', 1)
            ->select(['tl.*', 'tli.name', 'tli.description', 'tli.usage_protocol'])
            ->orderBy('tl.category')
            ->orderBy('tl.sort_order')
            ->get();
    }

    public function getTkLabelsForObject(int $objectId): Collection
    {
        if (!Schema::hasTable('rights_object_tk_label')) {
            return collect();
        }

        return DB::table('rights_object_tk_label as otl')
            ->join('rights_tk_label as tl', 'otl.tk_label_id', '=', 'tl.id')
            ->leftJoin('rights_tk_label_i18n as tli', function ($join) {
                $join->on('tl.id', '=', 'tli.id')
                    ->where('tli.culture', '=', $this->culture);
            })
            ->where('otl.object_id', $objectId)
            ->select([
                'otl.*',
                'tl.code',
                'tl.category',
                'tl.uri',
                'tl.color',
                'tli.name',
                'tli.description',
                'tli.usage_protocol',
            ])
            ->orderBy('tl.sort_order')
            ->get();
    }

    public function getTkLabelAssignments(): Collection
    {
        if (!Schema::hasTable('rights_object_tk_label')) {
            return collect();
        }

        return DB::table('rights_object_tk_label as otl')
            ->join('rights_tk_label as tl', 'otl.tk_label_id', '=', 'tl.id')
            ->leftJoin('rights_tk_label_i18n as tli', function ($join) {
                $join->on('tl.id', '=', 'tli.id')
                    ->where('tli.culture', '=', $this->culture);
            })
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('otl.object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'otl.object_id')
            ->select([
                'otl.*',
                'tl.code',
                'tl.color',
                'tli.name as label_name',
                'ioi.title as object_title',
                's.slug',
            ])
            ->orderBy('otl.created_at', 'desc')
            ->limit(100)
            ->get();
    }

    public function assignTkLabel(int $objectId, int $labelId, array $data = []): int
    {
        return DB::table('rights_object_tk_label')->insertGetId([
            'object_id' => $objectId,
            'tk_label_id' => $labelId,
            'community_name' => $data['community_name'] ?? null,
            'community_contact' => $data['community_contact'] ?? null,
            'custom_text' => $data['custom_text'] ?? null,
            'verified' => $data['verified'] ?? 0,
            'created_by' => $data['created_by'] ?? auth()->id(),
            'created_at' => now(),
        ]);
    }

    public function removeTkLabel(int $objectId, int $labelId): bool
    {
        return DB::table('rights_object_tk_label')
            ->where('object_id', $objectId)
            ->where('tk_label_id', $labelId)
            ->delete() > 0;
    }

    // =========================================================================
    // TERRITORY RESTRICTIONS
    // =========================================================================

    public function getTerritoriesForRecord(int $recordId): Collection
    {
        if (!Schema::hasTable('rights_territory')) {
            return collect();
        }

        return DB::table('rights_territory')
            ->where('rights_record_id', $recordId)
            ->get();
    }

    // =========================================================================
    // REFERENCE DATA
    // =========================================================================

    public function getRightsStatements(): Collection
    {
        if (!Schema::hasTable('rights_statement')) {
            return collect();
        }

        return DB::table('rights_statement as rs')
            ->leftJoin('rights_statement_i18n as rsi', function ($join) {
                $join->on('rs.id', '=', 'rsi.rights_statement_id')
                    ->where('rsi.culture', '=', $this->culture);
            })
            ->where('rs.is_active', 1)
            ->select(['rs.*', 'rsi.name', 'rsi.definition', 'rsi.scope_note'])
            ->orderBy('rs.sort_order')
            ->get();
    }

    public function getCcLicenses(): Collection
    {
        if (!Schema::hasTable('rights_cc_license')) {
            return collect();
        }

        return DB::table('rights_cc_license as cc')
            ->leftJoin('rights_cc_license_i18n as cci', function ($join) {
                $join->on('cc.id', '=', 'cci.id')
                    ->where('cci.culture', '=', $this->culture);
            })
            ->where('cc.is_active', 1)
            ->select(['cc.*', 'cci.name', 'cci.description', 'cci.human_readable'])
            ->orderBy('cc.sort_order')
            ->get();
    }

    public function getFormOptions(): array
    {
        return [
            'basis_options' => [
                'copyright' => 'Copyright',
                'license' => 'License',
                'statute' => 'Statute',
                'donor' => 'Donor',
                'policy' => 'Policy',
                'other' => 'Other',
            ],
            'copyright_status_options' => [
                'copyrighted' => 'Copyrighted',
                'public_domain' => 'Public Domain',
                'unknown' => 'Unknown',
            ],
            'act_options' => [
                'render' => 'Render',
                'disseminate' => 'Disseminate',
                'replicate' => 'Replicate',
                'migrate' => 'Migrate',
                'modify' => 'Modify',
                'delete' => 'Delete',
                'print' => 'Print',
                'use' => 'Use',
            ],
            'restriction_options' => [
                'allow' => 'Allow',
                'disallow' => 'Disallow',
                'conditional' => 'Conditional',
            ],
            'embargo_type_options' => [
                'full' => 'Full',
                'metadata_only' => 'Metadata Only',
                'digital_only' => 'Digital Only',
                'partial' => 'Partial',
            ],
            'embargo_reason_options' => [
                'privacy' => 'Privacy',
                'legal' => 'Legal Requirement',
                'donor' => 'Donor Agreement',
                'cultural' => 'Cultural Sensitivity',
                'commercial' => 'Commercial Interest',
                'security' => 'Security',
                'other' => 'Other',
            ],
            'work_type_options' => [
                'literary' => 'Literary Work',
                'artistic' => 'Artistic Work',
                'musical' => 'Musical Work',
                'dramatic' => 'Dramatic Work',
                'audiovisual' => 'Audiovisual Work',
                'photographic' => 'Photographic Work',
                'sound_recording' => 'Sound Recording',
                'other' => 'Other',
            ],
            'search_source_options' => [
                'copyright_registry' => 'Copyright Registry',
                'author_society' => 'Author/Artist Society',
                'publisher_records' => 'Publisher Records',
                'library_catalog' => 'Library Catalog',
                'internet_search' => 'Internet Search',
                'newspaper_archive' => 'Newspaper Archive',
                'other' => 'Other',
            ],
            'rights_statements' => $this->getRightsStatements(),
            'cc_licenses' => $this->getCcLicenses(),
            'tk_labels' => $this->getTkLabels(),
        ];
    }

    // =========================================================================
    // ACCESS CHECKS
    // =========================================================================

    public function checkAccess(int $objectId, ?int $userId = null): array
    {
        $result = [
            'accessible' => true,
            'restrictions' => [],
            'embargo' => null,
            'rights_statement' => null,
            'cc_license' => null,
            'tk_labels' => [],
        ];

        // Check embargo
        $embargo = $this->getEmbargo($objectId);
        if ($embargo) {
            $result['accessible'] = false;
            $result['embargo'] = $embargo;
            $result['restrictions'][] = [
                'type' => 'embargo',
                'reason' => $embargo->reason ?? null,
                'until' => $embargo->end_date ?? null,
            ];
        }

        // Check rights records
        $rights = $this->getRightsForObject($objectId);
        foreach ($rights as $right) {
            if ($right->rights_statement_code ?? null) {
                $result['rights_statement'] = [
                    'code' => $right->rights_statement_code,
                    'name' => $right->rights_statement_name,
                    'uri' => $right->rights_statement_uri,
                ];
            }
            if ($right->cc_license_code ?? null) {
                $result['cc_license'] = [
                    'code' => $right->cc_license_code,
                    'name' => $right->cc_license_name,
                    'uri' => $right->cc_license_uri,
                    'badge_url' => $right->cc_badge_url,
                ];
            }
        }

        $result['tk_labels'] = $this->getTkLabelsForObject($objectId)->toArray();

        return $result;
    }

    // =========================================================================
    // STATISTICS
    // =========================================================================

    public function getStatistics(): array
    {
        $stats = [
            'total_rights_records' => Schema::hasTable('rights_record')
                ? DB::table('rights_record')->count() : 0,
            'by_basis' => [],
            'active_embargoes' => 0,
            'expiring_soon' => 0,
            'orphan_works_in_progress' => 0,
            'tk_label_assignments' => 0,
            'by_rights_statement' => [],
            'by_cc_license' => [],
        ];

        if (Schema::hasTable('rights_record')) {
            $stats['by_basis'] = DB::table('rights_record')
                ->selectRaw('basis, COUNT(*) as count')
                ->groupBy('basis')
                ->pluck('count', 'basis')
                ->toArray();

            $stats['by_rights_statement'] = DB::table('rights_record as r')
                ->join('rights_statement as rs', 'r.rights_statement_id', '=', 'rs.id')
                ->selectRaw('rs.code, COUNT(*) as count')
                ->groupBy('rs.code')
                ->pluck('count', 'code')
                ->toArray();

            $stats['by_cc_license'] = DB::table('rights_record as r')
                ->join('rights_cc_license as cc', 'r.cc_license_id', '=', 'cc.id')
                ->selectRaw('cc.code, COUNT(*) as count')
                ->groupBy('cc.code')
                ->pluck('count', 'code')
                ->toArray();
        }

        if (Schema::hasTable('rights_embargo')) {
            $stats['active_embargoes'] = DB::table('rights_embargo')
                ->where('status', 'active')->count();
            $stats['expiring_soon'] = DB::table('rights_embargo')
                ->where('status', 'active')
                ->whereNotNull('end_date')
                ->whereRaw('end_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)')
                ->where('end_date', '>=', now()->toDateString())
                ->count();
        }

        if (Schema::hasTable('rights_orphan_work')) {
            $stats['orphan_works_in_progress'] = DB::table('rights_orphan_work')
                ->where('status', 'in_progress')->count();
        }

        if (Schema::hasTable('rights_object_tk_label')) {
            $stats['tk_label_assignments'] = DB::table('rights_object_tk_label')->count();
        }

        return $stats;
    }

    // =========================================================================
    // REPORT DATA
    // =========================================================================

    public function getReportData(string $type): mixed
    {
        switch ($type) {
            case 'embargoes':
                return $this->getActiveEmbargoes('all');

            case 'orphan_works':
                return $this->getOrphanWorks('all');

            case 'tk_labels':
                return $this->getTkLabelAssignments();

            default:
                return $this->getStatistics();
        }
    }

    public function exportReportCsv(string $type, $data): void
    {
        $filename = "rights_{$type}_" . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");

        $output = fopen('php://output', 'w');

        switch ($type) {
            case 'embargoes':
                fputcsv($output, ['Object', 'Type', 'Reason', 'Start Date', 'End Date', 'Status']);
                foreach ($data as $row) {
                    fputcsv($output, [
                        $row->object_title ?? $row->object_id,
                        $row->embargo_type,
                        $row->reason,
                        $row->start_date,
                        $row->end_date,
                        $row->status,
                    ]);
                }
                break;

            case 'tk_labels':
                fputcsv($output, ['Object', 'Label Code', 'Label Name', 'Community', 'Verified']);
                foreach ($data as $row) {
                    fputcsv($output, [
                        $row->object_title ?? $row->object_id,
                        $row->code,
                        $row->label_name,
                        $row->community_name ?? '',
                        ($row->verified ?? 0) ? 'Yes' : 'No',
                    ]);
                }
                break;

            case 'orphan_works':
                fputcsv($output, ['Object', 'Work Type', 'Status', 'Started', 'Completed', 'Jurisdiction']);
                foreach ($data as $row) {
                    fputcsv($output, [
                        $row->object_title ?? $row->object_id,
                        $row->work_type,
                        $row->status,
                        $row->search_started_date ?? '',
                        $row->search_completed_date ?? '',
                        $row->search_jurisdiction ?? '',
                    ]);
                }
                break;
        }

        fclose($output);
        exit;
    }
}
