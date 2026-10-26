<?php

/**
 * DataMigrationRepository - thin facade over DataMigrationService that maps
 * the controller's repository-style API (getStats, getMappings, getRecentJobs,
 * queueImportJob, etc.) onto the service's method shape.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * This file is part of Heratio. AGPL-3.0-or-later.
 */

namespace AtomExtensions\Repositories;

use AhgDataMigration\Services\DataMigrationService;
use Illuminate\Support\Facades\DB;

class DataMigrationRepository
{
    protected DataMigrationService $service;

    public function __construct(DataMigrationService $service)
    {
        $this->service = $service;
    }

    public function getStats(): array
    {
        $jobs = $this->service->getJobs(1000);
        $counts = ['total' => count($jobs), 'queued' => 0, 'running' => 0, 'completed' => 0, 'failed' => 0];
        foreach ($jobs as $job) {
            $status = $job['status'] ?? 'queued';
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
        }

        return $counts;
    }

    public function getMappings(): array
    {
        return $this->service->getSavedMappings();
    }

    public function getMappingById(int $id): ?array
    {
        return $this->service->getMapping($id);
    }

    public function saveMapping(array $data): int
    {
        return $this->service->saveMapping($data);
    }

    public function deleteMapping(int $id): bool
    {
        return $this->service->deleteMapping($id);
    }

    public function getAllJobs(int $limit = 200): array
    {
        return $this->service->getJobs($limit);
    }

    public function getRecentJobs(int $limit = 10): array
    {
        return $this->service->getJobs($limit);
    }

    public function getJob(int $id): ?array
    {
        return $this->service->getJob($id);
    }

    public function getJobResults(int $id): array
    {
        $job = $this->service->getJob($id);
        if (! $job) {
            return ['created' => 0, 'updated' => 0, 'errors' => [], 'log' => null];
        }
        $result = is_string($job['result'] ?? null) ? json_decode($job['result'], true) : ($job['result'] ?? []);

        return is_array($result) ? $result : [];
    }

    public function cancelJob(int $id): bool
    {
        $this->service->updateJobProgress($id, ['status' => 'cancelled']);

        return true;
    }

    public function getFileColumns(string $path): array
    {
        if (! $path || ! file_exists($path)) {
            return [];
        }
        $parsed = $this->service->parseCSV($path, 1);

        return $parsed['headers'] ?? [];
    }

    public function getFileRowCount(string $path): int
    {
        if (! $path || ! file_exists($path)) {
            return 0;
        }
        $count = 0;
        if (($fh = @fopen($path, 'r')) !== false) {
            while (fgets($fh) !== false) {
                $count++;
            }
            fclose($fh);
        }

        return max(0, $count - 1); // minus header
    }

    public function getTargetFields(string $targetType): array
    {
        return $this->service->getTargetFields($targetType);
    }

    public function previewRecords(?string $path, array $mappings = [], int $limit = 10): array
    {
        if (! $path || ! file_exists($path)) {
            return [];
        }
        $parsed = $this->service->parseCSV($path, $limit);

        return $parsed['rows'] ?? [];
    }

    public function getRepositories(): array
    {
        return DB::table('repository as r')
            ->leftJoin('actor_i18n as ai', function ($j) {
                $j->on('ai.id', '=', 'r.id')->where('ai.culture', '=', app()->getLocale());
            })
            ->select('r.id', 'ai.authorized_form_of_name as name')
            ->orderBy('ai.authorized_form_of_name')
            ->get()
            ->toArray();
    }

    public function getImportResults(int $limit = 100): array
    {
        return $this->service->getJobs($limit);
    }

    public function validateImport(string $path, array $mappings): array
    {
        $parsed = $this->service->parseCSV($path, 50);

        return [
            'valid_rows' => count($parsed['rows'] ?? []),
            'errors' => [],
            'warnings' => [],
        ];
    }

    /**
     * Record an import job and hand it to the queue worker (heratio#1555).
     * createJob reads source_file / mapping_snapshot / import_options; the
     * old keys here (file_path, mappings) were dropped, so a job had neither.
     */
    public function queueImportJob(string $path, string $targetType, array $mappings, array $options = [], ?string $name = null): int
    {
        $jobId = $this->service->createJob([
            'name' => $name ?: null,
            'target_type' => $targetType,
            'source_file' => $path,
            'mapping_snapshot' => $mappings,
            'import_options' => $options,
            'total_records' => $this->getFileRowCount($path),
        ]);
        \AhgDataMigration\Jobs\RunMigrationImport::dispatch($jobId);

        return $jobId;
    }
}
