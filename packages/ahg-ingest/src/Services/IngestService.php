<?php

/**
 * IngestService - Service for Heratio
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



namespace AhgIngest\Services;

use Illuminate\Support\Facades\DB;

class IngestService
{
    /**
     * List ingest sessions.
     *
     * By default hides non-wizard sessions (watched folders, scan API) so the
     * Ingest wizard UI stays focused on interactive batches. Pass $includeAllKinds
     * = true to show everything (admin debugging).
     */
    public function getSessions(?int $userId = null, bool $includeAllKinds = false): array
    {
        $query = DB::table('ingest_session')
            ->leftJoin('user', 'ingest_session.user_id', '=', 'user.id')
            ->leftJoin('actor_i18n', 'user.id', '=', 'actor_i18n.id')
            ->select('ingest_session.*', 'actor_i18n.authorized_form_of_name as user_name');

        if ($userId) {
            $query->where('ingest_session.user_id', $userId);
        }

        if (!$includeAllKinds) {
            $query->where(function ($w) {
                $w->where('ingest_session.session_kind', 'wizard')
                  ->orWhereNull('ingest_session.session_kind');
            });
        }

        return $query->orderByDesc('ingest_session.updated_at')->get()->toArray();
    }

    public function getSession(int $id): ?object
    {
        return DB::table('ingest_session')->where('id', $id)->first();
    }

    public function createSession(int $userId, array $config): int
    {
        return DB::table('ingest_session')->insertGetId([
            'user_id' => $userId,
            'title' => $config['title'] ?? '',
            'entity_type' => $config['entity_type'] ?? 'description',
            'sector' => $config['sector'] ?? 'archive',
            'standard' => $config['standard'] ?? 'isadg',
            'repository_id' => $config['repository_id'] ?? null,
            'parent_id' => $config['parent_id'] ?? null,
            'config' => json_encode($config),
            'status' => 'configure',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function updateSession(int $id, array $config): void
    {
        DB::table('ingest_session')->where('id', $id)->update([
            'title' => $config['title'] ?? '',
            'entity_type' => $config['entity_type'] ?? 'description',
            'sector' => $config['sector'] ?? 'archive',
            'standard' => $config['standard'] ?? 'isadg',
            'repository_id' => $config['repository_id'] ?? null,
            'parent_id' => $config['parent_id'] ?? null,
            'config' => json_encode($config),
            'updated_at' => now(),
        ]);
    }

    public function updateSessionStatus(int $id, string $status): void
    {
        DB::table('ingest_session')->where('id', $id)->update([
            'status' => $status,
            'updated_at' => now(),
        ]);
    }

    /**
     * Delete an ingest session and all its dependent rows (files, mappings,
     * rows, validation errors, jobs). Wizard sessions only — refuses
     * watched-folder and scan-API sessions, which are long-lived records
     * that must be retired through their owning package (ahg-scan) so that
     * historical ingest_file audit rows and scan_folder linkage are
     * preserved correctly.
     *
     * @throws \RuntimeException when the session is not a wizard session.
     */
    public function deleteSession(int $id): bool
    {
        $session = DB::table('ingest_session')->where('id', $id)->first();
        if (!$session) {
            return false;
        }
        $kind = $session->session_kind ?? 'wizard';
        if ($kind !== 'wizard') {
            throw new \RuntimeException(
                "Ingest session {$id} is a {$kind} session; delete it via its owning package (/admin/scan for watched folders) so history is preserved."
            );
        }

        DB::table('ingest_validation')->where('session_id', $id)->delete();
        DB::table('ingest_row')->where('session_id', $id)->delete();
        DB::table('ingest_mapping')->where('session_id', $id)->delete();
        DB::table('ingest_file')->where('session_id', $id)->delete();
        DB::table('ingest_job')->where('session_id', $id)->delete();
        return DB::table('ingest_session')->where('id', $id)->delete() > 0;
    }

    public function getFiles(int $sessionId): \Illuminate\Support\Collection
    {
        return DB::table('ingest_file')
            ->where('session_id', $sessionId)
            ->orderBy('created_at')
            ->get();
    }

    public function getMappings(int $sessionId): \Illuminate\Support\Collection
    {
        return DB::table('ingest_mapping')
            ->where('session_id', $sessionId)
            ->orderBy('source_column')
            ->get();
    }

    public function getValidationErrors(int $sessionId): \Illuminate\Support\Collection
    {
        return DB::table('ingest_validation')
            ->where('session_id', $sessionId)
            ->orderBy('row_number')
            ->get();
    }

    public function getRowCount(int $sessionId): int
    {
        return DB::table('ingest_row')
            ->where('session_id', $sessionId)
            ->where('is_excluded', 0)
            ->count();
    }

    public function validateSession(int $sessionId): array
    {
        $total = DB::table('ingest_row')->where('session_id', $sessionId)->count();
        $excluded = DB::table('ingest_row')->where('session_id', $sessionId)->where('is_excluded', 1)->count();
        $errors = DB::table('ingest_validation')->where('session_id', $sessionId)->count();

        return [
            'total' => $total,
            'excluded' => $excluded,
            'valid' => $total - $excluded - $errors,
            'errors' => $errors,
        ];
    }

    public function getJobBySession(int $sessionId): ?object
    {
        return DB::table('ingest_job')
            ->where('session_id', $sessionId)
            ->orderByDesc('id')
            ->first();
    }

    public function getRepositories(): array
    {
        return DB::table('repository')
            ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
            ->select('repository.id', 'actor_i18n.authorized_form_of_name as name')
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->get()
            ->toArray();
    }

    /**
     * Streaming-mode entry point: ingest a single staged file against a
     * long-lived session (session_kind = watched_folder | scan_api).
     *
     * Resolves / creates the information object, creates the digital object,
     * and returns [io_id, do_id]. Callers own stage progression and status
     * updates on the ingest_file row.
     *
     * @param int    $sessionId    ingest_session.id (must exist; kind != 'wizard' recommended)
     * @param string $stagedPath   absolute path to the file on disk
     * @param array  $meta         resolved destination + descriptive metadata:
     *                             - parent_id    (int, required)     destination parent
     *                             - identifier   (string, optional)  IO identifier; dedupes against (parent, identifier)
     *                             - title        (string, optional)  defaults to identifier or filename stem
     *                             - level_of_description_id (int, optional)
     *                             - repository_id (int, optional)    inherited from session if unset
     *                             - scope_and_content (string, optional)
     *                             - source_standard (string, optional)
     *                             - culture      (string, optional)  defaults to 'en'
     *                             - merge        (string, optional)  'add-sequence' (default) | 'replace' | 'error'
     * @param string $originalName original filename (for digital_object.name)
     *
     * @return array{io_id:int, do_id:int, was_existing_io:bool}
     */
    public function ingestFile(int $sessionId, string $stagedPath, array $meta, string $originalName): array
    {
        if (!is_file($stagedPath)) {
            throw new \RuntimeException("Staged file not found: {$stagedPath}");
        }

        $session = $this->getSession($sessionId);
        if (!$session) {
            throw new \RuntimeException("Ingest session {$sessionId} not found.");
        }

        $parentId = (int) ($meta['parent_id'] ?? $session->parent_id ?? 1);
        if (!$parentId) {
            throw new \RuntimeException('ingestFile requires a parent_id in meta or on the session.');
        }

        $repositoryId = $meta['repository_id'] ?? $session->repository_id ?? null;
        $culture = $meta['culture'] ?? 'en';

        // Resolve existing IO by (parent_id, identifier) if identifier supplied.
        $ioId = null;
        $wasExisting = false;
        if (!empty($meta['identifier'])) {
            $existing = DB::table('information_object')
                ->where('parent_id', $parentId)
                ->where('identifier', $meta['identifier'])
                ->value('id');
            if ($existing) {
                $mergeMode = $meta['merge'] ?? 'add-sequence';
                if ($mergeMode === 'error') {
                    throw new \RuntimeException("IO with identifier '{$meta['identifier']}' already exists under parent {$parentId}.");
                }
                $ioId = (int) $existing;
                $wasExisting = true;
            }
        }

        if ($ioId === null) {
            $title = $meta['title']
                ?? $meta['identifier']
                ?? pathinfo($originalName, PATHINFO_FILENAME)
                ?: 'Untitled';

            $ioData = [
                'title' => $title,
                'parent_id' => $parentId,
                'identifier' => $meta['identifier'] ?? null,
                'repository_id' => $repositoryId,
                'level_of_description_id' => $meta['level_of_description_id'] ?? null,
                'scope_and_content' => $meta['scope_and_content'] ?? null,
                'source_standard' => $meta['source_standard'] ?? $session->standard ?? null,
            ];

            $ioId = \AhgInformationObjectManage\Services\InformationObjectService::create($ioData, $culture);
        }

        $doId = $this->createDigitalObjectFromPath($ioId, $stagedPath, $originalName);

        return ['io_id' => $ioId, 'do_id' => $doId, 'was_existing_io' => $wasExisting];
    }

    /**
     * Create a digital_object row + move the file into the canonical location.
     * Path-variant of DigitalObjectService::upload() which requires an
     * UploadedFile; this one accepts an on-disk staged path.
     */
    protected function createDigitalObjectFromPath(int $ioId, string $stagedPath, string $originalName): int
    {
        $uploadsBase = config('heratio.uploads_path');
        $targetDir = rtrim($uploadsBase, '/') . '/' . $ioId;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException("Cannot create upload directory: {$targetDir}");
        }

        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $safeName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $baseName);
        $filename = 'master_' . $safeName . ($extension ? '.' . $extension : '');

        $targetPath = $targetDir . '/' . $filename;
        // If a file with this name already exists, append a sequence number.
        $seq = 1;
        while (file_exists($targetPath)) {
            $filename = 'master_' . $safeName . '_' . $seq . ($extension ? '.' . $extension : '');
            $targetPath = $targetDir . '/' . $filename;
            $seq++;
        }

        if (!@rename($stagedPath, $targetPath)) {
            if (!@copy($stagedPath, $targetPath)) {
                throw new \RuntimeException("Failed to move staged file into uploads: {$stagedPath} -> {$targetPath}");
            }
            @unlink($stagedPath);
        }

        $byteSize = filesize($targetPath);
        $checksum = hash_file('sha256', $targetPath);
        $mimeType = function_exists('mime_content_type') ? (@mime_content_type($targetPath) ?: 'application/octet-stream') : 'application/octet-stream';
        $mediaTypeId = $this->resolveMediaTypeId($mimeType);

        $now = now()->format('Y-m-d H:i:s');
        $doObjectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitDigitalObject',
            'created_at' => $now,
            'updated_at' => $now,
            'serial_number' => 0,
        ]);

        $webPath = '/uploads/r/' . $ioId . '/';

        DB::table('digital_object')->insert([
            'id' => $doObjectId,
            'object_id' => $ioId,
            'usage_id' => \AhgCore\Services\DigitalObjectService::USAGE_MASTER,
            'mime_type' => $mimeType,
            'media_type_id' => $mediaTypeId,
            'name' => $filename,
            'path' => $webPath,
            'byte_size' => $byteSize,
            'checksum' => $checksum,
            'checksum_type' => 'sha256',
            'parent_id' => null,
        ]);

        // Auto metadata extraction (closes #86 Phase 6 for the ingest path).
        // Mirrors the wire-up in DigitalObjectController::upload: fires the
        // shared orchestrator against the file at its canonical location,
        // captures a secondary audit row with sector + extracted/written
        // fields. Failures are non-fatal so a broken extractor cannot break
        // the ingest itself. Gated on meta_extract_on_upload (default true).
        try {
            $extractor = app(\AhgMetadataExtraction\Services\MetadataExtractionService::class);
            $result = $extractor->extractAndApplyOnUpload($ioId, $targetPath);
            if (!empty($result['written']) || !empty($result['extracted_fields'])) {
                \AhgCore\Support\AuditLog::captureSecondaryMutation($ioId, 'information_object', 'metadata_extraction_apply', [
                    'data' => [
                        'sector'            => $result['sector'] ?? null,
                        'extracted_fields'  => $result['extracted_fields'] ?? [],
                        'written'           => $result['written'] ?? [],
                        'skipped'           => $result['skipped'] ?? [],
                        'digital_object_id' => $doObjectId,
                        'filename'          => $filename,
                        'source'            => 'ingest',
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            \Log::warning('Metadata extraction on ingest failed: ' . $e->getMessage(), [
                'io_id' => $ioId,
                'do_id' => $doObjectId,
                'trace_first' => $e->getFile() . ':' . $e->getLine(),
            ]);
        }

        return $doObjectId;
    }

    protected function resolveMediaTypeId(string $mimeType): int
    {
        $type = explode('/', $mimeType)[0] ?? '';
        return match ($type) {
            'image' => \AhgCore\Services\DigitalObjectService::MEDIA_IMAGE,
            'audio' => \AhgCore\Services\DigitalObjectService::MEDIA_AUDIO,
            'video' => \AhgCore\Services\DigitalObjectService::MEDIA_VIDEO,
            'text' => \AhgCore\Services\DigitalObjectService::MEDIA_TEXT,
            'application' => in_array($mimeType, [
                'application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/rtf', 'application/vnd.oasis.opendocument.text',
            ]) ? \AhgCore\Services\DigitalObjectService::MEDIA_TEXT
              : \AhgCore\Services\DigitalObjectService::MEDIA_OTHER,
            default => \AhgCore\Services\DigitalObjectService::MEDIA_OTHER,
        };
    }
}
