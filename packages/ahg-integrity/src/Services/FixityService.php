<?php

/**
 * FixityService - Fixity verification pipeline for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace AhgIntegrity\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixityService
{
    /** @deprecated Use config('heratio.uploads_path') instead */
    private const UPLOAD_ROOT = '/mnt/nas/heratio/archive';

    /**
     * Compute a checksum for a file on disk.
     */
    public function computeChecksum(string $filePath, string $algorithm = 'sha256'): ?string
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return null;
        }

        return hash_file($algorithm, $filePath);
    }

    /**
     * Verify a single digital object against its stored checksum.
     */
    public function verifyObject(int $digitalObjectId, string $algorithm = 'sha256'): array
    {
        $startTime = microtime(true);

        $filePath = $this->getDigitalObjectPath($digitalObjectId);
        if (!$filePath) {
            return [
                'passed'      => false,
                'outcome'     => 'missing',
                'expected'    => null,
                'actual'      => null,
                'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'file_path'   => null,
                'file_size'   => null,
                'file_exists' => false,
                'file_readable' => false,
                'error'       => 'Digital object not found or has no path.',
            ];
        }

        $fileExists = file_exists($filePath);
        $fileReadable = $fileExists && is_readable($filePath);
        $fileSize = $fileExists ? filesize($filePath) : null;

        if (!$fileExists) {
            return [
                'passed'      => false,
                'outcome'     => 'missing',
                'expected'    => null,
                'actual'      => null,
                'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'file_path'   => $filePath,
                'file_size'   => null,
                'file_exists' => false,
                'file_readable' => false,
                'error'       => 'File does not exist on disk.',
            ];
        }

        if (!$fileReadable) {
            return [
                'passed'      => false,
                'outcome'     => 'error',
                'expected'    => null,
                'actual'      => null,
                'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'file_path'   => $filePath,
                'file_size'   => $fileSize,
                'file_exists' => true,
                'file_readable' => false,
                'error'       => 'File exists but is not readable.',
            ];
        }

        // Get expected checksum from preservation_checksum table
        $expected = null;
        if (Schema::hasTable('preservation_checksum')) {
            $checksumRow = DB::table('preservation_checksum')
                ->where('digital_object_id', $digitalObjectId)
                ->where('algorithm', $algorithm)
                ->orderBy('generated_at', 'desc')
                ->first();

            $expected = $checksumRow->checksum_value ?? null;
        }

        // Fall back to digital_object.checksum if preservation_checksum not available
        if (!$expected) {
            $do = DB::table('digital_object')->where('id', $digitalObjectId)->first();
            if ($do && $do->checksum && strtolower($do->checksum_type ?? '') === strtolower($algorithm)) {
                $expected = $do->checksum;
            }
        }

        $actual = $this->computeChecksum($filePath, $algorithm);

        $passed = $expected && $actual && hash_equals($expected, $actual);
        $outcome = 'pass';
        if (!$expected) {
            $outcome = 'no_baseline';
            $passed = false;
        } elseif (!$actual) {
            $outcome = 'error';
            $passed = false;
        } elseif (!$passed) {
            $outcome = 'fail';
        }

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        // Update preservation_checksum verified_at
        if (Schema::hasTable('preservation_checksum') && isset($checksumRow) && $checksumRow) {
            DB::table('preservation_checksum')
                ->where('id', $checksumRow->id)
                ->update([
                    'verified_at'         => now(),
                    'verification_status' => $passed ? 'verified' : 'failed',
                ]);
        }

        // If no preservation_checksum record exists and we computed a hash, create one
        if (Schema::hasTable('preservation_checksum') && !isset($checksumRow) && $actual) {
            DB::table('preservation_checksum')->insert([
                'digital_object_id'   => $digitalObjectId,
                'algorithm'           => $algorithm,
                'checksum_value'      => $actual,
                'file_size'           => $fileSize,
                'generated_at'        => now(),
                'verified_at'         => now(),
                'verification_status' => 'verified',
                'created_at'          => now(),
            ]);
        }

        return [
            'passed'        => $passed,
            'outcome'       => $outcome,
            'expected'      => $expected,
            'actual'        => $actual,
            'duration_ms'   => $durationMs,
            'file_path'     => $filePath,
            'file_size'     => $fileSize,
            'file_exists'   => true,
            'file_readable' => true,
            'error'         => null,
        ];
    }

    /**
     * Batch verify multiple digital objects.
     */
    public function batchVerify(array $objectIds, string $algorithm = 'sha256', int $throttleMs = 0): array
    {
        $results = [];

        foreach ($objectIds as $id) {
            $results[$id] = $this->verifyObject($id, $algorithm);

            if ($throttleMs > 0) {
                usleep($throttleMs * 1000);
            }
        }

        return $results;
    }

    /**
     * Get digital objects that have not been verified in N days.
     */
    public function getStaleObjects(int $staleDays, ?int $repositoryId = null, int $limit = 200): array
    {
        $cutoff = now()->subDays($staleDays);

        $query = DB::table('digital_object')
            ->leftJoin('information_object', 'digital_object.object_id', '=', 'information_object.id')
            ->leftJoin('integrity_ledger', function ($join) {
                $join->on('digital_object.id', '=', 'integrity_ledger.digital_object_id')
                    ->whereRaw('integrity_ledger.id = (
                        SELECT MAX(il2.id) FROM integrity_ledger il2
                        WHERE il2.digital_object_id = digital_object.id
                    )');
            })
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('integrity_ledger.verified_at')
                    ->orWhere('integrity_ledger.verified_at', '<', $cutoff);
            });

        if ($repositoryId) {
            $query->where('information_object.repository_id', $repositoryId);
        }

        return $query
            ->select([
                'digital_object.id as digital_object_id',
                'digital_object.object_id as information_object_id',
                'digital_object.path',
                'digital_object.byte_size',
                'information_object.repository_id',
                'integrity_ledger.verified_at as last_verified_at',
            ])
            ->orderByRaw('integrity_ledger.verified_at IS NULL DESC, integrity_ledger.verified_at ASC')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Build the file path for a digital object from the DB path column.
     */
    public function getDigitalObjectPath(int $digitalObjectId): ?string
    {
        $do = DB::table('digital_object')
            ->where('id', $digitalObjectId)
            ->first(['path']);

        if (!$do || !$do->path) {
            return null;
        }

        $path = $do->path;

        // If the path is already absolute, use it directly
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return rtrim(config('heratio.uploads_path', self::UPLOAD_ROOT), '/') . '/' . ltrim($path, '/');
    }
}
