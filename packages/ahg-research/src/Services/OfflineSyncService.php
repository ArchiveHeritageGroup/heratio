<?php

/**
 * OfflineSyncService - Controller for Heratio
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
 */

namespace AhgResearch\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies the work a researcher did offline in a /research/mobile package back
 * into their research. Consumes the researcher-sync.json the offline viewer
 * produces:
 *
 *   { heratio_sync:1, package_id, sync_token, group_source, group_ref,
 *     changes: { notes:[{io_id,slug,text}], sources:[{io_id,slug,title,author,year,url}],
 *                metadata_suggestions:[{io_id,slug,field,text}],
 *                files:[{io_id,slug,name,type,size,data}] } }
 *
 * Ownership is always resolved server-side (the caller passes the verified
 * researcher_id); the payload's identity is never trusted. Sync-back targets:
 *   - note                -> research_annotation (annotation_type=note)
 *   - source              -> research_annotation (annotation_type=source)
 *   - metadata_suggestion -> research_metadata_suggestion (curator review queue)
 *   - file                -> uploads/research-offline/ + research_offline_attachment
 */
class OfflineSyncService
{
    private const MAX_FILE_BYTES = 5 * 1024 * 1024; // 5 MB per attachment

    /**
     * @return array{applied:int,conflicts:int,notes:int,sources:int,suggestions:int,files:int,errors:array,log_id:int}
     */
    public function applyBundle(int $researcherId, array $payload): array
    {
        $changes = is_array($payload['changes'] ?? null) ? $payload['changes'] : [];
        $notes = is_array($changes['notes'] ?? null) ? $changes['notes'] : [];
        $sources = is_array($changes['sources'] ?? null) ? $changes['sources'] : [];
        $suggestions = is_array($changes['metadata_suggestions'] ?? null) ? $changes['metadata_suggestions'] : [];
        $files = is_array($changes['files'] ?? null) ? $changes['files'] : [];

        $queued = count($notes) + count($sources) + count($suggestions) + count($files);

        $logId = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('research_offline_sync_log')) {
            $logId = (int) DB::table('research_offline_sync_log')->insertGetId([
                'researcher_id'   => $researcherId,
                'sync_started_at' => date('Y-m-d H:i:s'),
                'queued_count'    => $queued,
                'payload_hash'    => hash('sha256', json_encode($changes)),
            ]);
        }

        $applied = 0;
        $conflicts = 0;
        $errors = [];
        $counts = ['notes' => 0, 'sources' => 0, 'suggestions' => 0, 'files' => 0];

        foreach ($notes as $n) {
            try {
                if ($this->applyNote($researcherId, (array) $n)) {
                    $applied++;
                    $counts['notes']++;
                }
            } catch (\Throwable $e) {
                $conflicts++;
                $errors[] = 'note: '.$e->getMessage();
            }
        }
        foreach ($sources as $s) {
            try {
                if ($this->applySource($researcherId, (array) $s)) {
                    $applied++;
                    $counts['sources']++;
                }
            } catch (\Throwable $e) {
                $conflicts++;
                $errors[] = 'source: '.$e->getMessage();
            }
        }
        foreach ($suggestions as $s) {
            try {
                if ($this->applySuggestion($researcherId, (array) $s)) {
                    $applied++;
                    $counts['suggestions']++;
                }
            } catch (\Throwable $e) {
                $conflicts++;
                $errors[] = 'suggestion: '.$e->getMessage();
            }
        }
        foreach ($files as $f) {
            try {
                if ($this->applyFile($researcherId, (array) $f)) {
                    $applied++;
                    $counts['files']++;
                }
            } catch (\Throwable $e) {
                $conflicts++;
                $errors[] = 'file: '.$e->getMessage();
            }
        }

        if ($logId) {
            DB::table('research_offline_sync_log')->where('id', $logId)->update([
                'sync_completed_at' => date('Y-m-d H:i:s'),
                'applied_count'     => $applied,
                'conflict_count'    => $conflicts,
            ]);
        }

        return array_merge([
            'applied' => $applied,
            'conflicts' => $conflicts,
            'errors' => array_values(array_slice($errors, 0, 5)),
            'log_id' => $logId,
        ], $counts);
    }

    private function applyNote(int $researcherId, array $e): bool
    {
        $content = trim((string) ($e['text'] ?? $e['content'] ?? ''));
        $objectId = (int) ($e['io_id'] ?? $e['object_id'] ?? 0);
        if ($content === '' || $objectId <= 0) {
            return false;
        }

        DB::table('research_annotation')->insert([
            'researcher_id'   => $researcherId,
            'object_id'       => $objectId,
            'entity_type'     => 'information_object',
            'annotation_type' => 'note',
            'content'         => $content,
            'content_format'  => 'text',
            'is_private'      => 1,
            'visibility'      => 'private',
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    private function applySource(int $researcherId, array $e): bool
    {
        $objectId = (int) ($e['io_id'] ?? $e['object_id'] ?? 0);
        $parts = array_filter([
            trim((string) ($e['title'] ?? '')),
            trim((string) ($e['author'] ?? '')) !== '' ? 'by '.$e['author'] : '',
            trim((string) ($e['year'] ?? '')) !== '' ? '('.$e['year'].')' : '',
            trim((string) ($e['url'] ?? '')),
        ], fn ($v) => $v !== '');
        $content = trim((string) ($e['content'] ?? implode(' ', $parts)));
        if ($content === '' || $objectId <= 0) {
            return false;
        }

        DB::table('research_annotation')->insert([
            'researcher_id'   => $researcherId,
            'object_id'       => $objectId,
            'entity_type'     => 'information_object',
            'annotation_type' => 'source',
            'title'           => isset($e['title']) ? mb_substr((string) $e['title'], 0, 255) : null,
            'content'         => $content,
            'content_format'  => 'text',
            'tags'            => 'source',
            'is_private'      => 1,
            'visibility'      => 'private',
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    private function applySuggestion(int $researcherId, array $e): bool
    {
        $field = trim((string) ($e['field'] ?? ''));
        $suggestion = trim((string) ($e['text'] ?? $e['suggestion'] ?? ''));
        $objectId = (int) ($e['io_id'] ?? $e['object_id'] ?? 0);
        if ($field === '' || $suggestion === '' || $objectId <= 0) {
            return false;
        }

        DB::table('research_metadata_suggestion')->insert([
            'researcher_id' => $researcherId,
            'object_id'     => $objectId,
            'field'         => mb_substr($field, 0, 191),
            'suggestion'    => $suggestion,
            'status'        => 'open',
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    private function applyFile(int $researcherId, array $e): bool
    {
        $dataUrl = (string) ($e['data'] ?? '');
        $name = trim((string) ($e['name'] ?? 'attachment'));
        $objectId = (int) ($e['io_id'] ?? $e['object_id'] ?? 0);
        if ($dataUrl === '' || strpos($dataUrl, 'base64,') === false) {
            return false;
        }

        [$meta, $b64] = explode('base64,', $dataUrl, 2);
        $binary = base64_decode($b64, true);
        if ($binary === false) {
            return false;
        }
        if (strlen($binary) > self::MAX_FILE_BYTES) {
            throw new \RuntimeException('Attachment "'.$name.'" exceeds the 5 MB offline limit.');
        }

        $uploadsRoot = rtrim((string) config('heratio.uploads_path', storage_path('app')), '/');
        $relDir = '/research-offline/'.$researcherId;
        $absDir = $uploadsRoot.$relDir;

        if (! is_dir($absDir)) {
            @mkdir($absDir, 0775, true);
        }
        if (! is_dir($absDir) || ! is_writable($absDir)) {
            throw new \RuntimeException('Attachment folder is not writable ('.$relDir.').');
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: 'attachment';
        $safeName = date('YmdHis').'_'.substr(md5($b64), 0, 8).'_'.$safeName;
        $absPath = $absDir.'/'.$safeName;
        if (@file_put_contents($absPath, $binary) === false) {
            throw new \RuntimeException('Could not write attachment "'.$name.'".');
        }

        $mime = '';
        if (preg_match('#data:([^;]+);#', 'data:'.$meta.';', $m)) {
            $mime = $m[1];
        }

        DB::table('research_offline_attachment')->insert([
            'researcher_id' => $researcherId,
            'object_id'     => $objectId ?: null,
            'file_name'     => mb_substr($name, 0, 500),
            'mime_type'     => $mime !== '' ? mb_substr($mime, 0, 255) : (isset($e['type']) ? mb_substr((string) $e['type'], 0, 255) : null),
            'file_size'     => strlen($binary),
            'file_path'     => $relDir.'/'.$safeName,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        return true;
    }
}
