<?php

/*
 * ProcedureEvidenceService - generic documented evidence/proof for any
 * Collections Procedure flow (#1460 Phase 1).
 *
 * Keyed by (object_id, procedure_type[, procedure_id]) so it works for every
 * procedure with no per-procedure code. An evidence row is either an uploaded
 * file (stored under the app uploads root, where the DAM writes) or a link to an
 * existing digital_object. Every write is mirrored to spectrum_procedure_history
 * and spectrum_audit_log.
 *
 * Copyright (C) 2026 Johan Pieterse - The Archive Heritage Group (Pty) Ltd.
 * Part of Heratio. Licensed under the GNU AGPL v3.
 */

namespace AhgSpectrum\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProcedureEvidenceService
{
    public const CATEGORIES = ['certificate', 'receipt', 'report', 'agreement', 'photo', 'correspondence', 'other'];

    private function tableReady(): bool
    {
        try {
            return Schema::hasTable('spectrum_procedure_evidence');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Evidence for one procedure. Link rows are enriched with the digital
     * object's name/path so the view can render an "open" link.
     *
     * @return array<int, object>
     */
    public function list(int $objectId, string $procedureType, ?int $procedureId = null): array
    {
        if (! $this->tableReady()) {
            return [];
        }

        $q = DB::table('spectrum_procedure_evidence as e')
            ->leftJoin('digital_object as d', 'd.id', '=', 'e.digital_object_id')
            ->where('e.object_id', $objectId)
            ->where('e.procedure_type', $procedureType);

        if ($procedureId !== null) {
            $q->where('e.procedure_id', $procedureId);
        }

        return $q->orderByDesc('e.created_at')
            ->select('e.*', 'd.name as do_name', 'd.path as do_path')
            ->get()
            ->all();
    }

    /**
     * Evidence counts per procedure_type for one object, for the card badges.
     *
     * @return array<string, int>
     */
    public function countsByProcedure(int $objectId): array
    {
        if (! $this->tableReady()) {
            return [];
        }

        return DB::table('spectrum_procedure_evidence')
            ->where('object_id', $objectId)
            ->groupBy('procedure_type')
            ->selectRaw('procedure_type, COUNT(*) as c')
            ->pluck('c', 'procedure_type')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    public function storeUpload(int $objectId, string $procedureType, ?int $procedureId, UploadedFile $file, ?string $category, ?string $description, ?string $evidenceDate, ?int $userId): int
    {
        $root = rtrim((string) config('heratio.uploads_path'), '/');
        $relDir = 'spectrum-evidence/'.$objectId;
        $absDir = $root.'/'.$relDir;
        if (! is_dir($absDir)) {
            @mkdir($absDir, 0775, true);
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $stored = bin2hex(random_bytes(16)).'.'.$ext;
        $file->move($absDir, $stored);

        $id = (int) DB::table('spectrum_procedure_evidence')->insertGetId([
            'object_id' => $objectId,
            'procedure_type' => $procedureType,
            'procedure_id' => $procedureId,
            'evidence_kind' => 'upload',
            'filename' => $stored,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => (int) (@filesize($absDir.'/'.$stored) ?: 0),
            'file_path' => $relDir.'/'.$stored,
            'category' => $this->normaliseCategory($category),
            'description' => $description,
            'evidence_date' => $evidenceDate ?: null,
            'uploaded_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->log($objectId, $procedureType, $procedureId, 'evidence_added', 'Uploaded '.$file->getClientOriginalName(), $userId);

        return $id;
    }

    public function storeLink(int $objectId, string $procedureType, ?int $procedureId, int $digitalObjectId, ?string $category, ?string $description, ?string $evidenceDate, ?int $userId): ?int
    {
        $do = DB::table('digital_object')->where('id', $digitalObjectId)->first();
        if (! $do) {
            return null;
        }

        $id = (int) DB::table('spectrum_procedure_evidence')->insertGetId([
            'object_id' => $objectId,
            'procedure_type' => $procedureType,
            'procedure_id' => $procedureId,
            'evidence_kind' => 'link',
            'digital_object_id' => $digitalObjectId,
            'original_name' => $do->name ?? null,
            'mime_type' => $do->mime_type ?? null,
            'category' => $this->normaliseCategory($category),
            'description' => $description,
            'evidence_date' => $evidenceDate ?: null,
            'uploaded_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->log($objectId, $procedureType, $procedureId, 'evidence_added', 'Linked digital object #'.$digitalObjectId, $userId);

        return $id;
    }

    public function delete(int $id, ?int $userId): bool
    {
        $row = DB::table('spectrum_procedure_evidence')->where('id', $id)->first();
        if (! $row) {
            return false;
        }

        // Only remove a physical file for an upload; never touch the DAM file of
        // a linked digital object.
        if (($row->evidence_kind ?? '') === 'upload' && ! empty($row->file_path)) {
            $abs = rtrim((string) config('heratio.uploads_path'), '/').'/'.ltrim((string) $row->file_path, '/');
            if (is_file($abs)) {
                @unlink($abs);
            }
        }

        DB::table('spectrum_procedure_evidence')->where('id', $id)->delete();
        $this->log((int) $row->object_id, (string) $row->procedure_type, $row->procedure_id ? (int) $row->procedure_id : null, 'evidence_removed', 'Removed evidence #'.$id, $userId);

        return true;
    }

    /** Absolute path of an uploaded evidence file, for streaming a download. */
    public function resolveAbsolutePath(object $row): ?string
    {
        if (($row->evidence_kind ?? '') !== 'upload' || empty($row->file_path)) {
            return null;
        }
        $abs = rtrim((string) config('heratio.uploads_path'), '/').'/'.ltrim((string) $row->file_path, '/');

        return is_file($abs) ? $abs : null;
    }

    public function find(int $id): ?object
    {
        return DB::table('spectrum_procedure_evidence')->where('id', $id)->first() ?: null;
    }

    private function normaliseCategory(?string $category): string
    {
        $c = strtolower(trim((string) $category));

        return in_array($c, self::CATEGORIES, true) ? $c : 'other';
    }

    /**
     * Mirror to the two Spectrum audit surfaces. A logging failure must never
     * take down the evidence action it records.
     */
    private function log(int $objectId, string $procedureType, ?int $procedureId, string $action, string $detail, ?int $userId): void
    {
        try {
            if (Schema::hasTable('spectrum_procedure_history')) {
                DB::table('spectrum_procedure_history')->insert([
                    'object_id' => $objectId,
                    'procedure_type' => $procedureType,
                    'procedure_id' => $procedureId,
                    'action' => $action,
                    'description' => $detail,
                    'user_id' => $userId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (\Throwable $e) {
        }

        try {
            if (Schema::hasTable('spectrum_audit_log')) {
                DB::table('spectrum_audit_log')->insert([
                    'object_id' => $objectId,
                    'procedure_type' => $procedureType,
                    'procedure_id' => $procedureId,
                    'action' => $action,
                    'action_date' => date('Y-m-d H:i:s'),
                    'user_id' => $userId,
                    'note' => $detail,
                ]);
            }
        } catch (\Throwable $e) {
        }
    }
}
