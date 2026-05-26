<?php

/**
 * PremisExportCommand - write a PREMIS 3.0 XML document for an IO to disk.
 *
 *   php artisan premis:export 1234
 *   php artisan premis:export 1234 --out=/tmp/io-1234.xml
 *   php artisan premis:export 1234 --refresh-rights --validate
 *
 * Issue #653 Phase 1.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license AGPL-3.0-or-later
 */

namespace AhgPreservation\Console;

use AhgPreservation\Services\PremisRightsService;
use AhgPreservation\Services\PremisXmlSerializer;
use Illuminate\Console\Command;

class PremisExportCommand extends Command
{
    protected $signature = 'premis:export
        {ioId : Information object id}
        {--out= : Write to file instead of stdout}
        {--refresh-rights : Re-project ODRL into ahg_premis_rights before serialising}
        {--validate : Run libxml schemaValidate against the bundled PREMIS XSD}';

    protected $description = 'Export a PREMIS 3.0 XML document for an information object';

    public function handle(PremisXmlSerializer $serializer, PremisRightsService $rights): int
    {
        $ioId = (int) $this->argument('ioId');
        if ($ioId <= 0) {
            $this->error('ioId must be a positive integer');
            return self::INVALID;
        }

        if ($this->option('refresh-rights')) {
            $written = $rights->createFromOdrl($ioId);
            $this->line(sprintf('Refreshed %d PREMIS rights row(s) from ODRL', $written->count()));
        }

        $xml = $serializer->serialize($ioId);

        if ($this->option('validate')) {
            $errors = $serializer->validate($xml);
            if (empty($errors)) {
                $this->info('XSD validation: PASSED');
            } else {
                $this->warn(sprintf('XSD validation: %d error(s)', count($errors)));
                foreach (array_slice($errors, 0, 10) as $e) {
                    $this->line(sprintf('  line %d: %s', $e['line'] ?? 0, $e['message'] ?? ''));
                }
            }
        }

        $out = $this->option('out');
        if ($out) {
            $bytes = file_put_contents($out, $xml);
            if ($bytes === false) {
                $this->error('Failed to write ' . $out);
                return self::FAILURE;
            }
            $this->info(sprintf('Wrote %d bytes to %s', $bytes, $out));
        } else {
            $this->line($xml);
        }

        return self::SUCCESS;
    }
}
