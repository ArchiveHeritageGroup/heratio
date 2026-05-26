<?php

/**
 * PreservationScanCommand - run identify + malware scan across an IO
 * (or every IO missing a recent fixity event).
 *
 *   php artisan preservation:scan 1234
 *   php artisan preservation:scan --stale-days=90 --limit=50
 *   php artisan preservation:scan --tools=siegfried,clamav
 *
 * Issue #653 Phase 1.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license AGPL-3.0-or-later
 */

namespace AhgPreservation\Console;

use AhgPreservation\Services\FixityScanService;
use AhgPreservation\Services\PreservationService;
use AhgPreservation\Tools\ClamAVTool;
use AhgPreservation\Tools\FixityToolInterface;
use AhgPreservation\Tools\NullFixityTool;
use AhgPreservation\Tools\SiegfriedTool;
use Illuminate\Console\Command;

class PreservationScanCommand extends Command
{
    protected $signature = 'preservation:scan
        {ioId? : Information object id (omit to sweep stale IOs)}
        {--stale-days=90 : When ioId is omitted, treat IOs with no fixity_check newer than this as stale}
        {--limit=50 : Max IOs to scan in a single sweep}
        {--tools= : Comma-separated tool names (siegfried,clamav,null). Defaults to all available.}';

    protected $description = 'Run format identification + malware scan across an IO (or all stale IOs)';

    public function handle(PreservationService $preservation): int
    {
        $tools   = $this->resolveTools();
        $service = new FixityScanService($preservation, $tools);

        $this->line('Tools in play: ' . implode(', ', array_map(fn ($t) => $t->name(), $tools)));

        $ioArg = $this->argument('ioId');
        if ($ioArg !== null) {
            $this->scanOne($service, (int) $ioArg);
            return self::SUCCESS;
        }

        $days  = (int) $this->option('stale-days');
        $limit = (int) $this->option('limit');
        $ids   = $service->staleIos($days, $limit);
        if (empty($ids)) {
            $this->info(sprintf('No IOs are stale (last %d days).', $days));
            return self::SUCCESS;
        }
        $this->line(sprintf('Scanning %d stale IO(s)...', count($ids)));
        foreach ($ids as $id) {
            $this->scanOne($service, (int) $id);
        }
        return self::SUCCESS;
    }

    protected function scanOne(FixityScanService $service, int $ioId): void
    {
        $r = $service->scanIo($ioId);
        $this->line(sprintf(
            '  io=%d scanned=%d identified=%d clean=%d infected=%d errors=%d',
            $r['information_object_id'],
            $r['objects_scanned'],
            $r['identified'],
            $r['clean'],
            $r['infected'],
            $r['errors']
        ));
        if (! empty($r['infected'])) {
            foreach ($r['results'] as $row) {
                if (! empty($row['scan']) && empty($row['scan']['clean'])) {
                    $this->warn(sprintf(
                        '    INFECTED do=%d threats=%s',
                        $row['digital_object_id'],
                        implode(',', $row['scan']['threats'] ?? [])
                    ));
                }
            }
        }
    }

    /**
     * @return FixityToolInterface[]
     */
    protected function resolveTools(): array
    {
        $opt = (string) ($this->option('tools') ?? '');
        $names = $opt !== ''
            ? array_filter(array_map('trim', explode(',', strtolower($opt))))
            : ['siegfried', 'clamav'];

        $tools = [];
        foreach ($names as $name) {
            $tool = match ($name) {
                'siegfried' => new SiegfriedTool(),
                'clamav'    => new ClamAVTool(),
                'null'      => new NullFixityTool(),
                default     => null,
            };
            if ($tool && $tool->isAvailable()) {
                $tools[] = $tool;
            }
        }
        // Always end with NullFixityTool so identify()/scan() can never starve.
        $tools[] = new NullFixityTool();
        return $tools;
    }
}
