<?php

/**
 * ScanIoCommand - privacy:scan-io {ioId} - run PiiScanService against an
 * information_object's title + i18n descriptive text + access points, persist
 * a row in ahg_pii_scan_report, and print a summary.
 *
 * Issue #669 Phase 1.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio. Licensed AGPL-3.0-or-later.
 */

declare(strict_types=1);

namespace AhgPrivacy\Console\Commands;

use AhgPrivacy\Services\PiiScanService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ScanIoCommand extends Command
{
    protected $signature = 'privacy:scan-io
        {ioId : information_object.id to scan}
        {--jurisdiction= : Override the configured jurisdiction (gdpr, popia, uk_gdpr, ccpa)}
        {--no-persist : Print findings but do not write a row in ahg_pii_scan_report}';

    protected $description = 'Run PII scan against an information_object and persist a scan report.';

    public function handle(): int
    {
        $id = (int) $this->argument('ioId');
        if ($id <= 0) {
            $this->error('ioId must be a positive integer.');
            return self::FAILURE;
        }
        if (! Schema::hasTable('information_object')) {
            $this->error('information_object table is missing - cannot scan.');
            return self::FAILURE;
        }
        $io = DB::table('information_object')->where('id', $id)->first();
        if ($io === null) {
            $this->error(sprintf('information_object %d not found.', $id));
            return self::FAILURE;
        }

        $text = $this->assembleText($id);
        if ($text === '') {
            $this->warn('No scannable text found for this information_object.');
        }

        $service = new PiiScanService($this->option('jurisdiction') ?: null);
        $this->info(sprintf('Scanning information_object %d (jurisdiction=%s) ...', $id, $service->jurisdiction()));

        if ($this->option('no-persist')) {
            $findings = $service->scan($text);
            $this->printFindings($findings);
            return self::SUCCESS;
        }

        $reportId = $service->scanAndPersist($text, $id, null);
        if ($reportId === null) {
            $this->error('ahg_pii_scan_report table is missing - cannot persist a report.');
            return self::FAILURE;
        }

        $report = DB::table('ahg_pii_scan_report')->where('id', $reportId)->first();
        $this->info(sprintf('Persisted scan report id=%d, hits=%d.', $reportId, $report->hits_total ?? 0));
        $this->table(
            ['Type', 'Count'],
            array_map(static fn ($k, $v) => [$k, $v], array_keys((array) json_decode($report->hits_by_type ?? '{}', true)), array_values((array) json_decode($report->hits_by_type ?? '{}', true)))
        );
        return self::SUCCESS;
    }

    /**
     * Concatenate the descriptive fields most likely to contain PII. We keep this
     * narrow (title + descriptive blocks + access points + repository scope notes)
     * to avoid scanning very large blobs - large content arrives via the dedicated
     * PiiScanService->scan() entry point from background jobs.
     */
    private function assembleText(int $ioId): string
    {
        $parts = [];

        if (Schema::hasTable('information_object_i18n')) {
            $i18n = DB::table('information_object_i18n')->where('id', $ioId)->get();
            foreach ($i18n as $row) {
                foreach (['title', 'scope_and_content', 'arrangement', 'access_conditions', 'reproduction_conditions', 'physical_characteristics', 'finding_aids', 'location_of_originals', 'location_of_copies', 'related_units_of_description', 'publication_note', 'archivist_note', 'general_note'] as $col) {
                    if (isset($row->{$col}) && $row->{$col} !== null && $row->{$col} !== '') {
                        $parts[] = (string) $row->{$col};
                    }
                }
            }
        }

        if (Schema::hasTable('object_term_relation') && Schema::hasTable('term_i18n')) {
            $rows = DB::table('object_term_relation')
                ->join('term_i18n', 'term_i18n.id', '=', 'object_term_relation.term_id')
                ->where('object_term_relation.object_id', $ioId)
                ->limit(500)
                ->get(['term_i18n.name']);
            foreach ($rows as $r) {
                if (! empty($r->name)) {
                    $parts[] = (string) $r->name;
                }
            }
        }

        return implode("\n\n", $parts);
    }

    /** @param array<int,array<string,mixed>> $findings */
    private function printFindings(array $findings): void
    {
        if ($findings === []) {
            $this->info('No PII detected.');
            return;
        }
        $rows = array_map(static function (array $f): array {
            return [
                (string) $f['type'],
                substr((string) $f['value'], 0, 60),
                (string) $f['offset_start'],
                number_format((float) $f['confidence'], 2),
            ];
        }, array_slice($findings, 0, 50));
        $this->table(['type', 'value', 'offset', 'confidence'], $rows);
        if (count($findings) > 50) {
            $this->line(sprintf('... and %d more findings (truncated at 50 for CLI).', count($findings) - 50));
        }
    }
}
