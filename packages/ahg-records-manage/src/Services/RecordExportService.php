<?php

/**
 * RecordExportService - heratio#1740: one record, out of the system, whole.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgRecordsManage\Services;

use AhgRecordsManage\Services\Concerns\WritesDisposalAuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Builds a self-describing JSON package for one record: its description in
 * every language, its components (digital objects with their checksums), its
 * records-management state (declaration, disposal class, holds, disposal
 * actions, destruction certificates) and its audit history - the change trail
 * from ahg_audit_log, with the hash-chain columns so a receiver can check it,
 * and the request trail from security_audit_log.
 *
 * Session ids and user agents are left out: they identify a browser, not an
 * action. The export itself is written to the audit trail.
 *
 * ponytail: one record per call, JSON only. Bulk export of an aggregation and
 * a MoReq2010 XML serialisation are the upgrade path.
 */
class RecordExportService
{
    use WritesDisposalAuditLog;

    public const FORMAT = 'heratio-record-export/1';

    /** entity_type values the change trail uses for a description. */
    private const IO_ENTITY_TYPES = ['information_object', 'informationobject', 'description', 'information_object_files', 'QubitInformationObject'];

    /** Audit columns that identify a browser session rather than an action. */
    private const AUDIT_DROP = ['session_id', 'user_agent'];

    public function export(int $ioId, int $userId): ?array
    {
        $io = DB::table('information_object')->where('id', $ioId)->first();
        if (! $io) {
            return null;
        }

        $actions = $this->rows('rm_disposal_action', 'information_object_id', $ioId);
        $actionIds = array_map(fn ($a) => (int) $a->id, $actions);

        $package = [
            'format' => self::FORMAT,
            'exported_at' => now()->toIso8601String(),
            'exported_by' => $userId,
            'record' => [
                'id' => (int) $io->id,
                'identifier' => $io->identifier ?? null,
                'parent_id' => $io->parent_id ?? null,
                'repository_id' => $io->repository_id ?? null,
                'level_of_description_id' => $io->level_of_description_id ?? null,
                'source_culture' => $io->source_culture ?? null,
                'slug' => DB::table('slug')->where('object_id', $ioId)->value('slug'),
                'created_at' => DB::table('object')->where('id', $ioId)->value('created_at'),
                'updated_at' => DB::table('object')->where('id', $ioId)->value('updated_at'),
                'descriptions' => $this->rows('information_object_i18n', 'id', $ioId),
            ],
            'components' => Schema::hasTable('digital_object')
                ? DB::table('digital_object')->where('object_id', $ioId)
                    ->get(['id', 'name', 'mime_type', 'byte_size', 'checksum', 'checksum_type', 'usage_id', 'parent_id'])->all()
                : [],
            'records_management' => [
                'declaration' => Schema::hasTable('record_declaration')
                    ? DB::table('record_declaration')->where('information_object_id', $ioId)->first() : null,
                'disposal_class' => Schema::hasTable('rm_record_disposal_class')
                    ? app(DisposalClassService::class)->getAssignmentForRecord($ioId) : null,
                'legal_holds' => $this->rows('integrity_legal_hold', 'information_object_id', $ioId),
                'held_by_aggregation' => LegalHoldGuard::activeHoldFor($ioId),
                'disposal_actions' => $actions,
                'destruction_certificates' => $this->rows('destruction_certificate', 'information_object_id', $ioId),
            ],
            'audit' => [
                'changes' => $this->changeTrail($ioId, $actionIds),
                'requests' => Schema::hasTable('security_audit_log')
                    ? DB::table('security_audit_log')->where('object_id', $ioId)->orderBy('id')
                        ->get(['id', 'object_type', 'user_id', 'user_name', 'action', 'action_category', 'details', 'created_at'])->all()
                    : [],
            ],
        ];

        $this->auditLog($userId, 'export', 'information_object', $ioId, 'Record exported with metadata and audit history', [
            'format' => self::FORMAT,
            'audit_changes' => count($package['audit']['changes']),
            'audit_requests' => count($package['audit']['requests']),
        ]);

        return $package;
    }

    private function rows(string $table, string $column, int $id): array
    {
        return Schema::hasTable($table) ? DB::table($table)->where($column, $id)->orderBy('id')->get()->all() : [];
    }

    private function changeTrail(int $ioId, array $actionIds): array
    {
        if (! Schema::hasTable('ahg_audit_log')) {
            return [];
        }

        return DB::table('ahg_audit_log')
            ->where(function ($q) use ($ioId, $actionIds) {
                $q->where(fn ($w) => $w->where('entity_id', $ioId)->whereIn('entity_type', self::IO_ENTITY_TYPES));
                if ($actionIds) {
                    $q->orWhere(fn ($w) => $w->where('entity_type', 'rm_disposal_action')->whereIn('entity_id', $actionIds));
                }
            })
            ->orderBy('id')
            ->get()
            ->map(fn ($r) => (object) array_diff_key((array) $r, array_flip(self::AUDIT_DROP)))
            ->all();
    }
}
