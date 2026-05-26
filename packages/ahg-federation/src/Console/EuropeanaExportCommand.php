<?php

/**
 * EuropeanaExportCommand - artisan wrapper for EuropeanaExportService.
 *
 *   php artisan europeana:export
 *   php artisan europeana:export --out=storage/europeana/
 *   php artisan europeana:export --since=2026-01-01
 *
 * Scheduled weekly (Sundays 02:00) from the package service provider.
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

namespace AhgFederation\Console;

use AhgFederation\Edm\EuropeanaExportService;
use Illuminate\Console\Command;

class EuropeanaExportCommand extends Command
{
    protected $signature = 'europeana:export
        {--out=storage/europeana/ : Output directory (relative to base or absolute)}
        {--since= : Only re-export IOs updated on/after this ISO date}
        {--culture=en : Culture for i18n field selection}';

    protected $description = 'Serialise every published IO to EDM RDF/XML, build sitemap.xml, and pack a Europeana ingest zip bundle.';

    public function handle(EuropeanaExportService $service): int
    {
        $out = (string) $this->option('out');
        $since = $this->option('since');
        $culture = (string) ($this->option('culture') ?: 'en');

        $this->info("europeana:export starting (out={$out}, since=".($since ?: 'null').", culture={$culture})");

        $result = $service->run($out, $since ?: null, $culture);

        if (($result['status'] ?? '') === EuropeanaExportService::STATUS_SUCCESS) {
            $this->info(sprintf(
                'europeana:export ok: %d records, bundle=%s (%s bytes)',
                (int) ($result['record_count'] ?? 0),
                (string) ($result['bundle'] ?? ''),
                (string) ($result['bundle_size_bytes'] ?? '0')
            ));
            return self::SUCCESS;
        }

        $this->error('europeana:export failed: '.((string) ($result['error'] ?? 'unknown')));
        return self::FAILURE;
    }
}
