<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PrivacyScanPiiCommand extends Command
{
    protected $signature = 'ahg:privacy-scan-pii
        {--connection= : Source DB connection; blank = this instance}
        {--limit=2000 : Max IOs to scan in this run}
        {--since= : Only scan IOs updated since DATE (Y-m-d)}
        {--dry-run : Report matches without writing ahg_pii_scan_report}';

    protected $description = 'Scan information_object scope/history for PII patterns (SA ID, RSA passport, email, phone, IBAN); flags into ahg_pii_scan_report (counts per pattern, never the matched values)';

    public function handle(): int
    {
        $conn = (string) $this->option('connection');
        $limit = max(1, (int) $this->option('limit'));
        $since = $this->option('since');
        $dry = (bool) $this->option('dry-run');

        // Conservative regex set - false positives are tolerable since results land in
        // ahg_pii_scan_report for human review, not auto-redaction.
        $patterns = [
            'sa_id' => '/\b\d{6}[ ]?\d{4}[ ]?\d{3}\b/',                     // SA 13-digit ID
            'rsa_pass' => '/\b[A-Z]\d{8}\b/',                                  // RSA passport letter+8digits
            'email' => '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/',
            'phone_za' => '/\b(?:\+27|0)[ -]?[0-9]{2}[ -]?[0-9]{3}[ -]?[0-9]{4}\b/',
            'credit' => '/\b(?:\d{4}[ -]?){3}\d{4}\b/',
            'iban' => '/\b[A-Z]{2}\d{2}[A-Z0-9]{11,30}\b/',
        ];

        // Not 'atom' by default - that database exists only on an AtoM overlay,
        // so this was denied on every run on a Heratio-native instance.
        $conn = \AhgCore\Support\DiscoverySource::connectionName($conn);
        if (! \AhgCore\Support\DiscoverySource::usable($conn)) {
            $this->warn("Source connection '{$conn}' is not usable here - nothing to scan.");

            return self::SUCCESS;
        }

        // There is no `history` column on information_object_i18n; the field is
        // archival_history, which is already checked beside it. Naming the
        // non-existent one threw "Unknown column 'history'" on every run.
        $q = DB::connection($conn)->table('information_object_i18n as i18n')
            ->where('i18n.culture', 'en')
            ->where(function ($w) {
                $w->whereNotNull('i18n.scope_and_content')
                    ->orWhereNotNull('i18n.archival_history');
            })
            ->select('i18n.id', 'i18n.scope_and_content', 'i18n.archival_history');

        if ($since) {
            // updated_at is not on the i18n table either - timestamps live on
            // `object`, which these rows share an id with (CTI).
            $q->join('object as o', 'o.id', '=', 'i18n.id')
                ->where('o.updated_at', '>=', $since);
        }
        $q->limit($limit);

        $scanned = 0;
        $flagged = 0;
        $byType = array_fill_keys(array_keys($patterns), 0);
        $writeRows = [];
        $startedAt = now();
        foreach ($q->cursor() as $row) {
            $scanned++;
            $haystack = ($row->scope_and_content ?? '')."\n".($row->archival_history ?? '');
            $hits = [];
            foreach ($patterns as $name => $re) {
                if (preg_match_all($re, $haystack, $m) && ! empty($m[0])) {
                    $hits[$name] = array_slice(array_unique($m[0]), 0, 5);
                    $byType[$name]++;
                }
            }
            if (! empty($hits)) {
                $flagged++;
                // Counts per pattern only - never the matched values. The old
                // sample_hits column carried the phone numbers, passports and
                // emails themselves, and a failed insert then echoed them into
                // ahg_cron_run.output and cron_schedule.last_run_output (CH-000135).
                $writeRows[] = [
                    'information_object_id' => $row->id,
                    'scan_started_at' => $startedAt,
                    'scan_finished_at' => now(),
                    'hits_total' => array_sum(array_map('count', $hits)),
                    'hits_by_type' => json_encode(array_map('count', $hits)),
                    // ponytail: the pattern set above is SA-specific (sa_id, rsa_pass,
                    // phone_za), so this labels what it actually detects. Upgrade path:
                    // take patterns and jurisdiction from ahg-privacy's PiiScanService.
                    'jurisdiction' => 'popia',
                    'status' => 'pending',
                ];
                if ($flagged <= 10) {
                    $this->line(sprintf('  obj=%-7d patterns=[%s]', $row->id, implode(',', array_keys($hits))));
                }
            }
            if ($scanned % 1000 === 0) {
                $this->line("  scanned {$scanned}/{$limit}");
            }
        }

        // ahg_pii_scan_report is the review queue ahg-privacy reads. This used to
        // write privacy_redaction_cache, which is the redacted-file cache that
        // RedactionRenderService serves from, with columns that do not exist
        // there - so no scan ever stored a result (CH-000136).
        if (! $dry && Schema::hasTable('ahg_pii_scan_report') && ! empty($writeRows)) {
            foreach (array_chunk($writeRows, 500) as $chunk) {
                DB::table('ahg_pii_scan_report')->insert($chunk);
            }
        }

        $this->info("done; scanned={$scanned} flagged={$flagged}".($dry ? ' (dry-run)' : ''));
        $this->info('  by pattern: '.json_encode($byType));

        return self::SUCCESS;
    }
}
