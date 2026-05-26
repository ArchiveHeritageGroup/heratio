<?php

/**
 * auditlog:report - generate a filtered compliance export of ahg_audit_log
 * rows. Issue #676 Phase 6.
 *
 * Produces a structured export of audit rows matching the supplied filter.
 * Deterministic row ordering (by seq ASC, then id ASC) so the same filter
 * always produces byte-identical output. Output goes to --out or stdout.
 *
 * Formats:
 *   - csv      : RFC 4180, all fields quoted. UTF-8 BOM-less.
 *   - json     : JSON array of audit rows; chain columns preserved.
 *   - markdown : table + a "Hash of rows" header line containing
 *                sha256(canonical JSON of result set) so the regulator can
 *                confirm the report has not been tampered with.
 *
 * Usage:
 *   php artisan auditlog:report
 *   php artisan auditlog:report --from=2026-01-01 --to=2026-12-31
 *   php artisan auditlog:report --tenant=42 --action=create --format=json
 *   php artisan auditlog:report --user=7 --entity-type=information_object \
 *       --out=/tmp/io-edits.csv
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

declare(strict_types=1);

namespace AhgAuditTrail\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ReportCommand extends Command
{
    protected $signature = 'auditlog:report
        {--from=        : Start date (inclusive, parsed as YYYY-MM-DD or anything strtotime understands)}
        {--to=          : End date (inclusive); rows on this date are included up to 23:59:59}
        {--tenant=      : Tenant id filter}
        {--user=        : User id filter}
        {--entity-type= : Entity type filter (e.g. information_object)}
        {--entity-id=   : Entity id filter (requires --entity-type for the index to fire)}
        {--action=      : Action filter (e.g. create, update, delete, cli.command_start)}
        {--format=csv   : Output format: csv, json, or markdown}
        {--out=         : Output path; defaults to stdout}
        {--limit=       : Cap the number of rows returned (audit-trail tables can be huge)}';

    protected $description = 'Export filtered ahg_audit_log rows for compliance reporting (#676 Phase 6)';

    public function handle(): int
    {
        if (!Schema::hasTable('ahg_audit_log')) {
            $this->error('ahg_audit_log table does not exist - nothing to report.');
            return self::FAILURE;
        }

        $format = strtolower((string) $this->option('format'));
        if (!in_array($format, ['csv', 'json', 'markdown'], true)) {
            $this->error("Unknown --format '{$format}'. Use csv, json, or markdown.");
            return self::FAILURE;
        }

        try {
            $rows = $this->fetchRows();
        } catch (Throwable $e) {
            $this->error('Failed to fetch audit rows: '.$e->getMessage());
            return self::FAILURE;
        }

        $output = match ($format) {
            'csv'      => $this->renderCsv($rows),
            'json'     => $this->renderJson($rows),
            'markdown' => $this->renderMarkdown($rows),
        };

        $outPath = $this->option('out');
        if ($outPath !== null && $outPath !== '') {
            $written = @file_put_contents($outPath, $output);
            if ($written === false) {
                $this->error("Could not write to {$outPath}");
                return self::FAILURE;
            }
            $this->info(sprintf('Wrote %d row(s) to %s (%d bytes, %s)',
                count($rows), $outPath, $written, $format));
            return self::SUCCESS;
        }

        $this->getOutput()->write($output);
        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchRows(): array
    {
        $hasTenant = Schema::hasColumn('ahg_audit_log', 'tenant_id');
        $hasSeq    = Schema::hasColumn('ahg_audit_log', 'seq');

        $q = DB::table('ahg_audit_log');

        if (($from = $this->option('from')) !== null && $from !== '') {
            $ts = $this->parseFromDate((string) $from);
            if ($ts !== null) {
                $q->where('created_at', '>=', $ts);
            }
        }
        if (($to = $this->option('to')) !== null && $to !== '') {
            $ts = $this->parseToDate((string) $to);
            if ($ts !== null) {
                $q->where('created_at', '<=', $ts);
            }
        }
        if ($hasTenant && ($t = $this->option('tenant')) !== null && $t !== '') {
            $q->where('tenant_id', (int) $t);
        }
        if (($u = $this->option('user')) !== null && $u !== '') {
            $q->where('user_id', (int) $u);
        }
        if (($et = $this->option('entity-type')) !== null && $et !== '') {
            $q->where('entity_type', (string) $et);
        }
        if (($eid = $this->option('entity-id')) !== null && $eid !== '') {
            $q->where('entity_id', (int) $eid);
        }
        if (($a = $this->option('action')) !== null && $a !== '') {
            $q->where('action', (string) $a);
        }

        // Deterministic ordering: seq ASC first (so chained rows come out in
        // verification order), then id ASC as a tiebreaker for legacy rows
        // that pre-date the seq column.
        if ($hasSeq) {
            $q->orderBy('seq')->orderBy('id');
        } else {
            $q->orderBy('id');
        }

        if (($lim = $this->option('limit')) !== null && $lim !== '' && (int) $lim > 0) {
            $q->limit((int) $lim);
        }

        $rows = [];
        foreach ($q->get() as $r) {
            $rows[] = (array) $r;
        }
        return $rows;
    }

    private function parseFromDate(string $v): ?string
    {
        $ts = strtotime($v.' 00:00:00');
        if ($ts === false) {
            $ts = strtotime($v);
        }
        return $ts === false ? null : date('Y-m-d H:i:s', $ts);
    }

    private function parseToDate(string $v): ?string
    {
        // For a date-only "to" value we include the entire day. For a
        // full datetime string we honour it verbatim.
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return $v.' 23:59:59';
        }
        $ts = strtotime($v);
        return $ts === false ? null : date('Y-m-d H:i:s', $ts);
    }

    /**
     * RFC 4180-compliant CSV. All fields quoted. CRLF line endings.
     * Multi-line / embedded-quote values are escaped per the spec.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    private function renderCsv(array $rows): string
    {
        if (empty($rows)) {
            // Header-only output is still useful (regulator can see the
            // filter shape that produced an empty set).
            return $this->csvLine($this->canonicalColumns([]))."\r\n";
        }

        $cols = $this->canonicalColumns($rows[0]);
        $out = $this->csvLine($cols)."\r\n";
        foreach ($rows as $row) {
            $line = [];
            foreach ($cols as $col) {
                $line[] = $this->stringifyCellForCsv($row[$col] ?? null);
            }
            $out .= $this->csvLine($line)."\r\n";
        }
        return $out;
    }

    /**
     * @param array<int, string|int|float|null> $cells
     */
    private function csvLine(array $cells): string
    {
        $quoted = [];
        foreach ($cells as $cell) {
            $s = (string) $cell;
            // Escape embedded double-quotes per RFC 4180.
            $s = str_replace('"', '""', $s);
            $quoted[] = '"'.$s.'"';
        }
        return implode(',', $quoted);
    }

    private function stringifyCellForCsv(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_scalar($v)) {
            return (string) $v;
        }
        // arrays / objects (JSON columns may already be strings if the driver
        // didn't decode them; if so, leave them; otherwise re-encode).
        return (string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function renderJson(array $rows): string
    {
        return json_encode(
            ['rows' => $rows, 'count' => count($rows)],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) ?: '{}';
    }

    /**
     * Markdown table + hash-of-rows in the header.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    private function renderMarkdown(array $rows): string
    {
        $hash = $this->hashOfRows($rows);
        $generatedAt = gmdate('Y-m-d\TH:i:s\Z');

        $header = "# Audit log report\n\n";
        $header .= "- Generated: `{$generatedAt}` (UTC)\n";
        $header .= '- Row count: '.count($rows)."\n";
        $header .= "- Hash of rows (sha256): `{$hash}`\n";
        $header .= "- Filter: ".$this->describeFilter()."\n\n";

        if (empty($rows)) {
            return $header."_No rows matched the filter._\n";
        }

        // Pick a small set of columns for the markdown view - the full row is
        // available via --format=json or --format=csv. Markdown is meant for
        // human eyeballing, not bulk machine ingest.
        $cols = ['seq', 'id', 'created_at', 'action', 'tenant_id', 'user_id', 'username',
                 'entity_type', 'entity_id', 'status'];

        $row0 = $rows[0];
        $cols = array_values(array_filter($cols, fn ($c) => array_key_exists($c, $row0)));

        $body = '| '.implode(' | ', $cols).' |'."\n";
        $body .= '| '.implode(' | ', array_fill(0, count($cols), '---')).' |'."\n";
        foreach ($rows as $r) {
            $cells = [];
            foreach ($cols as $c) {
                $v = $r[$c] ?? '';
                if ($v === null) {
                    $v = '';
                }
                // pipes and newlines break markdown tables - escape them.
                $cells[] = str_replace(['|', "\r", "\n"], ['\\|', ' ', ' '], (string) $v);
            }
            $body .= '| '.implode(' | ', $cells).' |'."\n";
        }

        return $header.$body;
    }

    /**
     * Canonical sha256 over the result set so the regulator can re-derive the
     * same hash from the JSON / CSV export. We hash a canonical JSON shape
     * (keys sorted recursively, no whitespace) to remove any ordering noise.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    private function hashOfRows(array $rows): string
    {
        $canon = array_map(function ($row) {
            // sort row keys
            ksort($row);
            // decode JSON columns so the hash reflects structured payloads,
            // not the (driver-dependent) string serialisation
            foreach ($row as $k => $v) {
                if (is_string($v) && $v !== '' && ($v[0] === '{' || $v[0] === '[')) {
                    $decoded = json_decode($v, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $row[$k] = $decoded;
                    }
                }
            }
            return $row;
        }, $rows);

        $json = json_encode($canon, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = '';
        }
        return hash('sha256', $json);
    }

    private function describeFilter(): string
    {
        $parts = [];
        foreach (['from', 'to', 'tenant', 'user', 'entity-type', 'entity-id', 'action', 'limit'] as $opt) {
            $v = $this->option($opt);
            if ($v !== null && $v !== '') {
                $parts[] = "{$opt}=".(string) $v;
            }
        }
        return empty($parts) ? '_(none)_' : '`'.implode(' ', $parts).'`';
    }

    /**
     * Canonical column list for the CSV header / row order. We use the
     * first row's keys (insertion-ordered) when available; otherwise we
     * fall back to the union of likely-present columns so an empty result
     * still produces a sensible header.
     *
     * @param array<string,mixed> $sample
     * @return list<string>
     */
    private function canonicalColumns(array $sample): array
    {
        if (!empty($sample)) {
            return array_keys($sample);
        }
        return [
            'id', 'uuid', 'seq', 'prev_hash', 'entry_hash', 'signature', 'kid',
            'tenant_id', 'user_id', 'username', 'user_email', 'ip_address',
            'user_agent', 'session_id', 'action', 'entity_type', 'entity_id',
            'entity_slug', 'entity_title', 'module', 'action_name',
            'request_method', 'request_uri', 'old_values', 'new_values',
            'changed_fields', 'metadata', 'security_classification',
            'status', 'error_message', 'created_at', 'culture_id',
        ];
    }
}
