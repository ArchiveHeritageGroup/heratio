<?php

/**
 * WatchedFolderService — Heratio ahg-scan
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgScan\Services;

use Illuminate\Support\Facades\DB;

/**
 * CRUD + helpers for scan_folder rows. Each folder binds one-to-one with
 * an ingest_session (session_kind='watched_folder') that holds the
 * processing config (sector, standard, derivatives, OCR, packaging, etc.).
 */
class WatchedFolderService
{
    public function list(): array
    {
        return DB::table('scan_folder as sf')
            ->leftJoin('ingest_session as s', 'sf.ingest_session_id', '=', 's.id')
            ->select(
                'sf.*',
                's.title as session_title',
                's.sector',
                's.standard',
                's.parent_id',
                's.repository_id',
                's.session_kind'
            )
            ->orderBy('sf.label')
            ->get()
            ->toArray();
    }

    public function find(int $id): ?object
    {
        return DB::table('scan_folder')->where('id', $id)->first();
    }

    public function findByCode(string $code): ?object
    {
        return DB::table('scan_folder')->where('code', $code)->first();
    }

    public function enabledFolders(): array
    {
        return DB::table('scan_folder')->where('enabled', 1)->orderBy('id')->get()->toArray();
    }

    /**
     * Create a scan_folder and its backing ingest_session in one transaction.
     *
     * @param array $data  code, label, path, layout, parent_id, repository_id,
     *                     sector, standard, auto_commit, derivative_*, process_*
     * @param int   $userId  creator
     *
     * @return int  scan_folder.id
     */
    public function create(array $data, int $userId): int
    {
        return DB::transaction(function () use ($data, $userId) {
            $sessionId = DB::table('ingest_session')->insertGetId([
                'user_id' => $userId,
                'title' => $data['label'] . ' (watched folder)',
                'entity_type' => 'description',
                'sector' => $data['sector'] ?? 'archive',
                'standard' => $data['standard'] ?? 'isadg',
                'session_kind' => 'watched_folder',
                'auto_commit' => (int) ($data['auto_commit'] ?? 1),
                'source_ref' => $data['code'],
                'repository_id' => $data['repository_id'] ?? null,
                'parent_id' => $data['parent_id'] ?? null,
                'status' => 'configure',
                'output_create_records' => 1,
                'derivative_thumbnails' => (int) ($data['derivative_thumbnails'] ?? 1),
                'derivative_reference' => (int) ($data['derivative_reference'] ?? 1),
                'process_virus_scan' => (int) ($data['process_virus_scan'] ?? 1),
                'process_ocr' => (int) ($data['process_ocr'] ?? 0),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('scan_folder')->insertGetId([
                'code' => $data['code'],
                'label' => $data['label'],
                'path' => rtrim($data['path'], '/'),
                'layout' => $data['layout'] ?? 'path',
                'ingest_session_id' => $sessionId,
                'disposition_success' => $data['disposition_success'] ?? 'move',
                'disposition_failure' => $data['disposition_failure'] ?? 'quarantine',
                'min_quiet_seconds' => (int) ($data['min_quiet_seconds'] ?? config('heratio.scan.min_quiet_seconds', 10)),
                'enabled' => (int) ($data['enabled'] ?? 1),
                'created_at' => now(),
            ]);
        });
    }

    public function update(int $id, array $data): void
    {
        DB::transaction(function () use ($id, $data) {
            $folder = $this->find($id);
            if (!$folder) {
                throw new \RuntimeException("scan_folder {$id} not found");
            }

            DB::table('scan_folder')->where('id', $id)->update([
                'label' => $data['label'] ?? $folder->label,
                'path' => rtrim($data['path'] ?? $folder->path, '/'),
                'layout' => $data['layout'] ?? $folder->layout,
                'disposition_success' => $data['disposition_success'] ?? $folder->disposition_success,
                'disposition_failure' => $data['disposition_failure'] ?? $folder->disposition_failure,
                'min_quiet_seconds' => (int) ($data['min_quiet_seconds'] ?? $folder->min_quiet_seconds),
                'enabled' => isset($data['enabled']) ? (int) $data['enabled'] : $folder->enabled,
            ]);

            if (!empty($folder->ingest_session_id)) {
                $sessionUpdates = array_filter([
                    'sector' => $data['sector'] ?? null,
                    'standard' => $data['standard'] ?? null,
                    'parent_id' => $data['parent_id'] ?? null,
                    'repository_id' => $data['repository_id'] ?? null,
                    'auto_commit' => isset($data['auto_commit']) ? (int) $data['auto_commit'] : null,
                    'derivative_thumbnails' => isset($data['derivative_thumbnails']) ? (int) $data['derivative_thumbnails'] : null,
                    'derivative_reference' => isset($data['derivative_reference']) ? (int) $data['derivative_reference'] : null,
                    'process_virus_scan' => isset($data['process_virus_scan']) ? (int) $data['process_virus_scan'] : null,
                    'process_ocr' => isset($data['process_ocr']) ? (int) $data['process_ocr'] : null,
                ], fn($v) => $v !== null);

                if ($sessionUpdates) {
                    $sessionUpdates['updated_at'] = now();
                    DB::table('ingest_session')->where('id', $folder->ingest_session_id)->update($sessionUpdates);
                }
            }
        });
    }

    public function delete(int $id): void
    {
        $folder = $this->find($id);
        if (!$folder) {
            return;
        }
        DB::transaction(function () use ($folder) {
            DB::table('scan_folder')->where('id', $folder->id)->delete();
            // Leave the ingest_session + its ingest_file history in place for audit.
            // Mark the session cancelled instead.
            if (!empty($folder->ingest_session_id)) {
                DB::table('ingest_session')->where('id', $folder->ingest_session_id)
                    ->update(['status' => 'cancelled', 'updated_at' => now()]);
            }
        });
    }

    public function touchScanned(int $folderId): void
    {
        DB::table('scan_folder')->where('id', $folderId)->update(['last_scanned_at' => now()]);
    }
}
