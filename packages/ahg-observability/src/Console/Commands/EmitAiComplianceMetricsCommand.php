<?php

/**
 * ai-compliance:emit-metrics - synthetic Prometheus gauge for the AI inference chain.
 *
 * Issue #677 Phase 4. Bridges the gap between the on-demand
 * `ai-compliance:verify-inference-log` command (Article 12 verifier in the
 * ahg-ai-compliance package) and the always-on Prometheus scrape surface
 * by writing a single gauge to a textfile that node_exporter's textfile
 * collector picks up:
 *
 *   # HELP ai_compliance_verify_status 1 when verify-inference-log PASSes
 *   # TYPE ai_compliance_verify_status gauge
 *   ai_compliance_verify_status 1
 *   ai_compliance_verify_status_last_run_seconds 1748121600
 *
 * Output path: `config('observability.textfile_dir')/heratio_ai_compliance.prom`.
 *
 * Recommended cron: hourly. The InferenceChainBroken alert in
 * config/alerts/heratio.rules.yml fires when this gauge is != 1 (which
 * covers both an explicit FAIL and a missing sample - stale textfile
 * symptoms are treated as a real break, by design).
 *
 * We deliberately do NOT depend on ahg-ai-compliance at the PHP class
 * level so this package stays usable on installs where the compliance
 * package is absent. Instead the command is shelled out via `Artisan::call`.
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

declare(strict_types=1);

namespace AhgObservability\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class EmitAiComplianceMetricsCommand extends Command
{
    protected $signature = 'ai-compliance:emit-metrics
        {--dir= : Override config observability.textfile_dir for this run}
        {--dry-run : Print the textfile content to stdout instead of writing}';

    protected $description = 'Run ai-compliance:verify-inference-log and write a Prometheus textfile gauge (1=PASS, 0=FAIL).';

    public function handle(): int
    {
        // Lazy availability check - if the compliance package isn't
        // installed on this host, surface a clear message rather than a
        // cryptic "command not found" from Artisan::call.
        if (! $this->commandAvailable('ai-compliance:verify-inference-log')) {
            $this->error('ai-compliance:verify-inference-log is not registered; install the ahg-ai-compliance package.');

            return self::FAILURE;
        }

        $exit = Artisan::call('ai-compliance:verify-inference-log', [
            '--quiet-pass' => true,
        ]);

        // 0 from the verifier = PASS; anything else = FAIL. We capture the
        // verifier output so the failure reason ends up in the scheduler log.
        $verifierOutput = trim((string) Artisan::output());
        $status = $exit === self::SUCCESS ? 1 : 0;
        $now = time();

        $payload = $this->renderTextfile($status, $now, $verifierOutput);

        if ($this->option('dry-run')) {
            $this->line($payload);

            return self::SUCCESS;
        }

        $dir = (string) ($this->option('dir') ?: config('observability.textfile_dir'));
        if ($dir === '') {
            $this->error('observability.textfile_dir is not configured.');

            return self::FAILURE;
        }

        if (! is_dir($dir) && ! @mkdir($dir, 0o755, true) && ! is_dir($dir)) {
            $this->error("Cannot create textfile directory: {$dir}");

            return self::FAILURE;
        }

        $target = rtrim($dir, '/').'/heratio_ai_compliance.prom';
        $tmp = $target.'.'.getmypid().'.tmp';

        // Write-then-rename so node_exporter never sees a half-written
        // file. textfile collector rejects files with parse errors.
        if (file_put_contents($tmp, $payload) === false) {
            $this->error("Failed to write {$tmp}");

            return self::FAILURE;
        }

        if (! @rename($tmp, $target)) {
            @unlink($tmp);
            $this->error("Failed to atomically replace {$target}");

            return self::FAILURE;
        }

        $this->info(sprintf(
            'ai_compliance_verify_status=%d written to %s (verifier exit %d)',
            $status,
            $target,
            $exit
        ));

        // Always exit 0 so the scheduler doesn't keep retrying a known-bad
        // chain - the alert from the gauge is the operator signal. The
        // verifier's reason is in the textfile + the scheduler log.
        return self::SUCCESS;
    }

    private function commandAvailable(string $name): bool
    {
        return array_key_exists($name, Artisan::all());
    }

    private function renderTextfile(int $status, int $now, string $verifierOutput): string
    {
        // Encode the verifier output as a label-safe comment for operators
        // tailing the textfile by hand. Newlines stripped to keep the
        // single-line preamble format intact.
        $reason = $status === 1
            ? 'PASS'
            : ($verifierOutput !== '' ? str_replace(["\r", "\n"], ' / ', $verifierOutput) : 'FAIL');

        $lines = [
            '# HELP ai_compliance_verify_status 1 when ai-compliance:verify-inference-log PASSed, 0 on FAIL.',
            '# TYPE ai_compliance_verify_status gauge',
            "ai_compliance_verify_status {$status}",
            '# HELP ai_compliance_verify_last_run_seconds Unix timestamp of the most recent verification attempt.',
            '# TYPE ai_compliance_verify_last_run_seconds gauge',
            "ai_compliance_verify_last_run_seconds {$now}",
            '# reason: '.$reason,
        ];

        return implode("\n", $lines)."\n";
    }
}
