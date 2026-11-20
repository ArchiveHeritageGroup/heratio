<?php

/**
 * ahg:records-audit-scrub - heratio#1740 follow-up.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgRecordsManage\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Before v1.166.28 the disposal audit writer (WritesDisposalAuditLog) stored
 * the raw session id - the user's live session cookie - and the full IP
 * address in ahg_audit_log. This brings those old rows in line with the
 * current writer:
 *
 *   - session_id becomes hash_hmac('sha256', <raw id>, app.key), the same
 *     keyed hash the writer stores now. A value that is already a 64-character
 *     hex hash is left alone, so the command can be run again safely;
 *   - ip_address gets its last IPv4 octet set to 0, exactly what the writers
 *     do when audit_ip_anonymize is on. With the setting off, IPs are left
 *     as they are (the writers keep them in full too). IPv6 addresses are not
 *     changed by that rule, here or in the writers.
 *
 * Only rows with module = 'records-manage' created before --before (default:
 * the start of today) are looked at. Rows in the signed hash chain (seq set)
 * are never changed - their signature covers these columns, and rewriting
 * them would break auditlog:verify-chain. They are counted instead.
 *
 * --dry-run reports the same counts and writes nothing.
 */
class RecordsAuditScrubCommand extends Command
{
    protected $signature = 'ahg:records-audit-scrub
        {--dry-run : Report what would change without writing anything}
        {--before= : Only rows created before this date-time (default: start of today)}';

    protected $description = 'Hash raw session ids and anonymise IPs in old records-management audit rows';

    public function handle(): int
    {
        if (! Schema::hasTable('ahg_audit_log')) {
            $this->warn('ahg_audit_log does not exist. Nothing to do.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        try {
            $before = $this->option('before') ? \Carbon\Carbon::parse((string) $this->option('before')) : now()->startOfDay();
        } catch (\Throwable $e) {
            $this->error('Could not read --before: '.$e->getMessage());

            return self::FAILURE;
        }
        $key = (string) config('app.key');
        if ($key === '') {
            $this->error('APP_KEY is not set, so the session hash cannot be made. Nothing changed.');

            return self::FAILURE;
        }
        $anonymise = \AhgCore\Services\AhgSettingsService::get('audit_ip_anonymize', '1') === '1';
        $hasSeq = Schema::hasColumn('ahg_audit_log', 'seq');

        $counts = ['examined' => 0, 'session_hashed' => 0, 'ip_anonymised' => 0, 'rows_changed' => 0, 'chained_skipped' => 0];

        $this->line(($dry ? '[DRY RUN] ' : '')."records-manage audit rows created before {$before->format('Y-m-d H:i:s')}");
        if (! $anonymise) {
            $this->line('audit_ip_anonymize is off: IP addresses are left as they are.');
        }

        DB::table('ahg_audit_log')
            ->where('module', 'records-manage')
            ->where('created_at', '<', $before->format('Y-m-d H:i:s'))
            ->select(array_merge(['id', 'session_id', 'ip_address'], $hasSeq ? ['seq'] : []))
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$counts, $dry, $key, $anonymise, $hasSeq) {
                foreach ($rows as $r) {
                    $counts['examined']++;
                    $update = [];

                    $sid = (string) ($r->session_id ?? '');
                    if ($sid !== '' && ! preg_match('/^[0-9a-f]{64}$/', $sid)) {
                        $update['session_id'] = hash_hmac('sha256', $sid, $key);
                    }
                    $ip = (string) ($r->ip_address ?? '');
                    if ($anonymise && $ip !== '') {
                        $anon = preg_replace('/\.\d+$/', '.0', $ip);
                        if ($anon !== $ip) {
                            $update['ip_address'] = $anon;
                        }
                    }
                    if (! $update) {
                        continue;
                    }
                    if ($hasSeq && $r->seq !== null) {
                        $counts['chained_skipped']++;

                        continue;
                    }

                    $counts['session_hashed'] += isset($update['session_id']) ? 1 : 0;
                    $counts['ip_anonymised'] += isset($update['ip_address']) ? 1 : 0;
                    $counts['rows_changed']++;
                    if (! $dry) {
                        DB::table('ahg_audit_log')->where('id', $r->id)->update($update);
                    }
                }
            });

        $verb = $dry ? 'would be ' : '';
        $this->table(['', 'Rows'], [
            ['Examined', $counts['examined']],
            ['Session id '.$verb.'hashed', $counts['session_hashed']],
            ['IP '.$verb.'anonymised', $counts['ip_anonymised']],
            ['Rows '.$verb.'changed', $counts['rows_changed']],
            ['Signed (chained) rows left unchanged', $counts['chained_skipped']],
        ]);

        if (! $dry && $counts['rows_changed'] > 0) {
            Log::info('ahg:records-audit-scrub changed '.$counts['rows_changed'].' ahg_audit_log row(s)', $counts);
        }
        $this->info($dry ? '[DRY RUN] Nothing was written.' : 'Done.');

        return self::SUCCESS;
    }
}
