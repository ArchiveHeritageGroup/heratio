<?php

/**
 * ImportMuseumCsvCommand - Artisan command for museum CSV import.
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

namespace AhgMuseum\Console\Commands;

use AhgMuseum\Services\MuseumCsvImporter;
use Illuminate\Console\Command;

class ImportMuseumCsvCommand extends Command
{
    protected $signature = 'sector:museum-csv-import
        {filename : CSV file to import}
        {--validate-only : Validate without importing}
        {--mapping= : Mapping profile ID to use}
        {--repository= : Target repository slug}
        {--update=legacyId : Match field for updates (identifier, legacyId)}
        {--update-mode=skip : Update mode: skip, update, merge}
        {--culture=en : Default culture for i18n fields}
        {--limit= : Maximum rows to process}
        {--skip=0 : Number of rows to skip}';

    protected $description = 'Import museum CSV data with Spectrum 5.0 validation';

    public function handle(): int
    {
        $filename = $this->argument('filename');

        if (!file_exists($filename)) {
            $this->error("File not found: {$filename}");
            return 1;
        }

        $importer = new MuseumCsvImporter();
        $importer->setCulture($this->option('culture'));
        $importer->setMatchField($this->option('update'));
        $importer->setUpdateMode($this->option('update-mode'));

        if ($this->option('mapping')) {
            $importer->loadMappingProfile((int) $this->option('mapping'));
        }

        if ($this->option('repository')) {
            $importer->setRepositoryBySlug($this->option('repository'));
        }

        $importer->setProgressCallback(fn(string $msg) => $this->info($msg));

        $this->info(sprintf('Processing %s for sector: museum (Spectrum 5.0)', basename($filename)));

        if ($this->option('validate-only')) {
            return $this->runValidation($importer, $filename);
        }

        $limit = $this->option('limit') ? (int) $this->option('limit') : PHP_INT_MAX;
        $skip = (int) $this->option('skip');

        $counters = $importer->import($filename, $limit, $skip);

        $this->newLine();
        $this->info('=== Import Summary ===');
        $this->line(sprintf('Total rows processed: %d', $counters['total']));
        $this->line(sprintf('Records created: %d', $counters['imported']));
        $this->line(sprintf('Records updated: %d', $counters['updated']));
        $this->line(sprintf('Records skipped: %d', $counters['skipped']));
        $this->line(sprintf('Errors: %d', $counters['errors']));

        foreach ($importer->getErrors() as $error) {
            $this->error('  ' . $error);
        }

        return $counters['errors'] === 0 ? 0 : 1;
    }

    protected function runValidation(MuseumCsvImporter $importer, string $filename): int
    {
        $this->info('Running validation only (no import)...');

        $report = $importer->validate($filename);

        $this->newLine();
        $this->info('=== Validation Results ===');
        $this->line(sprintf('Total rows: %d', $report['total']));
        $this->line(sprintf('Errors: %d', count($report['errors'])));
        $this->line(sprintf('Warnings: %d', count($report['warnings'])));

        if (!$report['valid']) {
            $this->newLine();
            $this->error('Errors found:');
            foreach (array_slice($report['errors'], 0, 20) as $err) {
                $this->line('  ' . $err);
            }
        }

        if (!empty($report['warnings'])) {
            $this->newLine();
            $this->warn('Warnings:');
            foreach (array_slice($report['warnings'], 0, 20) as $warn) {
                $this->line('  ' . $warn);
            }
        }

        return $report['valid'] ? 0 : 1;
    }
}
