<?php

/**
 * CsvImportCommand — bulk CSV import for archival descriptions.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use AhgInformationObjectManage\Services\ArchivesCsvImporter;
use Illuminate\Console\Command;

class CsvImportCommand extends Command
{
    protected $signature = 'ahg:csv-import
        {file : Path to CSV file}
        {--source-name= : Source name identifier}
        {--default-legacy-parent-id= : Default parent ID for orphan records}
        {--skip-matched : Skip records that already exist}
        {--update= : Update strategy (match, overwrite, skip)}
        {--limit= : Maximum records to import}
        {--index : Reindex after import}';

    protected $description = 'CSV import (ISAD(G) archives sector)';

    public function handle(ArchivesCsvImporter $importer): int
    {
        $file = (string) $this->argument('file');
        if (! is_file($file) || ! is_readable($file)) {
            $this->error("CSV not readable: {$file}");
            return self::FAILURE;
        }

        $limit = $this->option('limit') ? max(1, (int) $this->option('limit')) : PHP_INT_MAX;
        $sourceName = (string) ($this->option('source-name') ?? basename($file));
        $skipMatched = (bool) $this->option('skip-matched');
        $updateMode = (string) ($this->option('update') ?? 'skip');
        $defaultParent = $this->option('default-legacy-parent-id');

        if (method_exists($importer, 'setSourceName')) {
            $importer->setSourceName($sourceName);
        }
        if ($defaultParent !== null && method_exists($importer, 'setDefaultLegacyParentId')) {
            $importer->setDefaultLegacyParentId((int) $defaultParent);
        }
        if (method_exists($importer, 'setUpdateMode')) {
            $importer->setUpdateMode($updateMode, $skipMatched);
        }

        $this->info("importing {$file} (source={$sourceName}, limit=" . ($limit === PHP_INT_MAX ? 'all' : $limit) . ")");
        $result = $importer->import($file, $limit, 0);

        $this->info(sprintf(
            'imported=%d skipped=%d errors=%d',
            $result['imported'] ?? 0,
            $result['skipped'] ?? 0,
            $result['errors'] ?? 0,
        ));

        if ($this->option('index')) {
            $this->info('reindexing…');
            $this->call('ahg:es-reindex', ['--index' => 'informationobject']);
        }

        return ($result['errors'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
