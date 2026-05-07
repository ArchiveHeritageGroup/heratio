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
        $defaults = $this->ingestSettings();
        $row = $this->buildSessionRow($config, $defaults);
        $row['user_id'] = $userId;
        $row['config'] = json_encode($config);
        $row['status'] = 'configure';
        $row['created_at'] = now();
        $row['updated_at'] = now();

        return DB::table('ingest_session')->insertGetId($row);
    }

    public function updateSession(int $id, array $config): void
    {
        $defaults = $this->ingestSettings();
        $row = $this->buildSessionRow($config, $defaults);
        $row['config'] = json_encode($config);
        $row['updated_at'] = now();

        DB::table('ingest_session')->where('id', $id)->update($row);
    }

    /**
     * Read every ingest_* setting once with its form-default fallback. Used
     * by createSession / updateSession to fill columns the operator didn't
     * touch, by configureDefaults() to pre-fill the configure form when
     * starting a new session, and by OaisPackagerService to fall back to a
     * configured AIP/SIP/DIP path when the session column is empty.
     */
    public function ingestSettings(): array
    {
        return [
            'aip_path' => (string) \AhgCore\Services\AhgSettingsService::get('ingest_aip_path', ''),
            'dip_path' => (string) \AhgCore\Services\AhgSettingsService::get('ingest_dip_path', ''),
            'sip_path' => (string) \AhgCore\Services\AhgSettingsService::get('ingest_sip_path', ''),
            'generate_aip' => \AhgCore\Services\AhgSettingsService::getBool('ingest_generate_aip', true),
            'generate_dip' => \AhgCore\Services\AhgSettingsService::getBool('ingest_generate_dip', true),
            'generate_sip' => \AhgCore\Services\AhgSettingsService::getBool('ingest_generate_sip', true),
            'create_records' => \AhgCore\Services\AhgSettingsService::getBool('ingest_create_records', true),
            'default_sector' => (string) \AhgCore\Services\AhgSettingsService::get('ingest_default_sector', 'archive'),
            'default_standard' => (string) \AhgCore\Services\AhgSettingsService::get('ingest_default_standard', 'isadg'),
            'face_detect' => \AhgCore\Services\AhgSettingsService::getBool('ingest_face_detect', false),
            'format_id' => \AhgCore\Services\AhgSettingsService::getBool('ingest_format_id', false),
            'ner' => \AhgCore\Services\AhgSettingsService::getBool('ingest_ner', false),
            'ocr' => \AhgCore\Services\AhgSettingsService::getBool('ingest_ocr', false),
            'reference' => \AhgCore\Services\AhgSettingsService::getBool('ingest_reference', true),
            'spellcheck' => \AhgCore\Services\AhgSettingsService::getBool('ingest_spellcheck', false),
            // Global-only: there's no matching ingest_session.process_spellcheck_lang
            // column AND no input on configure.blade.php for a per-session override.
            // The future spellcheck step (gated on the orchestration in #109)
            // reads this setting directly. Closing this loop per-session would
            // need a schema add + a form select - filed + closed as #110 path (b).
            'spellcheck_lang' => (string) \AhgCore\Services\AhgSettingsService::get('ingest_spellcheck_lang', 'en_ZA'),
            'summarize' => \AhgCore\Services\AhgSettingsService::getBool('ingest_summarize', false),
            'thumbnails' => \AhgCore\Services\AhgSettingsService::getBool('ingest_thumbnails', true),
            'translate' => \AhgCore\Services\AhgSettingsService::getBool('ingest_translate', false),
            'translate_from' => (string) \AhgCore\Services\AhgSettingsService::get('ingest_translate_from', 'en'),
            'translate_to' => (string) \AhgCore\Services\AhgSettingsService::get('ingest_translate_to', 'af'),
            'virus_scan' => \AhgCore\Services\AhgSettingsService::getBool('ingest_virus_scan', true),
        ];
    }

    /**
     * Build the ingest_session row payload, using posted form values where
     * present and falling back to operator-configured ingest_* settings.
     * Centralised so create + update share the same column-coverage logic
     * (create previously dropped every process_* and output_* checkbox on
     * the floor; the form had knobs that never reached the DB).
     */
    protected function buildSessionRow(array $config, array $defaults): array
    {
        $bool = fn ($v, $default) => array_key_exists($v, $config)
            ? (!empty($config[$v]) ? 1 : 0)
            : ($default ? 1 : 0);

        return [
            'title' => $config['title'] ?? '',
            'entity_type' => $config['entity_type'] ?? 'description',
            'sector' => $config['sector'] ?? $defaults['default_sector'],
            'standard' => $config['standard'] ?? $defaults['default_standard'],
            'repository_id' => $config['repository_id'] ?? null,
            'parent_id' => $config['parent_id'] ?? null,
            'output_create_records' => $bool('output_create_records', $defaults['create_records']),
            'output_generate_sip' => $bool('output_generate_sip', $defaults['generate_sip']),
            'output_generate_aip' => $bool('output_generate_aip', $defaults['generate_aip']),
            'output_generate_dip' => $bool('output_generate_dip', $defaults['generate_dip']),
            'output_sip_path' => $config['output_sip_path'] ?? ($defaults['sip_path'] ?: null),
            'output_aip_path' => $config['output_aip_path'] ?? ($defaults['aip_path'] ?: null),
            'output_dip_path' => $config['output_dip_path'] ?? ($defaults['dip_path'] ?: null),
            'process_ner' => $bool('process_ner', $defaults['ner']),
            'process_ocr' => $bool('process_ocr', $defaults['ocr']),
            'process_virus_scan' => $bool('process_virus_scan', $defaults['virus_scan']),
            'process_summarize' => $bool('process_summarize', $defaults['summarize']),
            'process_spellcheck' => $bool('process_spellcheck', $defaults['spellcheck']),
            'process_translate' => $bool('process_translate', $defaults['translate']),
            'process_translate_lang' => $config['process_translate_lang']
                ?? ($defaults['translate_from'] . '-' . $defaults['translate_to']),
            'process_format_id' => $bool('process_format_id', $defaults['format_id']),
            'process_face_detect' => $bool('process_face_detect', $defaults['face_detect']),
            'derivative_thumbnails' => $bool('derivative_thumbnails', $defaults['thumbnails']),
            'derivative_reference' => $bool('derivative_reference', $defaults['reference']),
        ];
    }

    /**
     * Configure-form initial values for new sessions. Returned object mirrors
     * the ingest_session row shape so the form's `@checked($session->X ??
     * Y)` pattern works whether $session is a real DB row or a synthetic
     * defaults object.
     */
    public function configureDefaults(): object
    {
        $d = $this->ingestSettings();
        return (object) [
            'title' => '',
            'entity_type' => 'description',
            'sector' => $d['default_sector'],
            'standard' => $d['default_standard'],
            'repository_id' => null,
            'parent_id' => null,
            'output_create_records' => $d['create_records'],
            'output_generate_sip' => $d['generate_sip'],
            'output_generate_aip' => $d['generate_aip'],
            'output_generate_dip' => $d['generate_dip'],
            'output_sip_path' => $d['sip_path'] ?: null,
            'output_aip_path' => $d['aip_path'] ?: null,
            'output_dip_path' => $d['dip_path'] ?: null,
            'process_ner' => $d['ner'],
            'process_ocr' => $d['ocr'],
            'process_virus_scan' => $d['virus_scan'],
            'process_summarize' => $d['summarize'],
            'process_spellcheck' => $d['spellcheck'],
            // No process_spellcheck_lang on the synthetic session: there's no
            // column to persist it AND no form input to override it. Closed
            // as #110 path (b) - global-only, read directly from settings by
            // the future spellcheck step.
            'process_translate' => $d['translate'],
            'process_translate_lang' => $d['translate_from'] . '-' . $d['translate_to'],
            'process_format_id' => $d['format_id'],
            'process_face_detect' => $d['face_detect'],
            'derivative_thumbnails' => $d['thumbnails'],
            'derivative_reference' => $d['reference'],
        ];
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

        // #115 repository_quota: refuse the ingest up-front when the repository
        // is over its operator-set cap. Filesize is the staged file's size on
        // disk - the digital_object row that ingestFile inserts later uses the
        // same number for its byte_size column. Throw rather than redirect so
        // ScanWatchCommand / ProcessScanFile / any future caller sees the
        // failure and can surface it to the operator.
        $proposedBytes = (int) (@filesize($stagedPath) ?: 0);
        if ($proposedBytes > 0 && !\AhgCore\Services\RepositoryQuotaService::canAccept(
            $repositoryId ? (int) $repositoryId : null,
            $proposedBytes,
        )) {
            throw new \RuntimeException(\AhgCore\Services\RepositoryQuotaService::rejectionMessage(
                (int) $repositoryId,
                $proposedBytes,
            ));
        }

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
