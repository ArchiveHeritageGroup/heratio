<?php

/**
 * HeritageDisclosureNoteCommand - render statutory disclosure notes from the
 * live heritage accounting tables.
 *
 * Usage:
 *   php artisan heritage:disclosure-note --standard=grap-103 \
 *       --period=2025-04-01..2026-03-31 --out=/tmp/grap-103-note.md
 *
 *   php artisan heritage:disclosure-note --standard=ipsas-45 \
 *       --period=2025-01-01..2025-12-31
 *
 *   php artisan heritage:disclosure-note --standard=transitional
 *
 * Placeholder substitution is intentionally simple ({{ key }} style) so the
 * templates remain auditor-readable plain markdown.
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

namespace AhgHeritageManage\Console\Commands;

use AhgHeritageManage\Services\OciMovementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HeritageDisclosureNoteCommand extends Command
{
    protected $signature = 'heritage:disclosure-note
                            {--standard=grap-103 : One of grap-103, ipsas-45, transitional}
                            {--period= : Period as YYYY-MM-DD..YYYY-MM-DD (defaults to current fiscal year)}
                            {--out= : Write rendered note to this path (otherwise stdout)}';

    protected $description = 'Render a statutory disclosure note (GRAP 103 / IPSAS 45 / transitional) populated from live heritage data.';

    public function handle(OciMovementService $oci): int
    {
        $standard = strtolower((string) $this->option('standard'));
        $valid = ['grap-103', 'ipsas-45', 'transitional'];
        if (! in_array($standard, $valid, true)) {
            $this->error("Unknown --standard '{$standard}'. Use one of: " . implode(', ', $valid));
            return self::INVALID;
        }

        [$periodStart, $periodEnd] = $this->resolvePeriod($this->option('period'));

        $templatePath = realpath(__DIR__ . '/../../../templates/disclosures/' . $standard . '-note.md.template');
        if (! $templatePath || ! is_readable($templatePath)) {
            $this->error("Template not found for standard '{$standard}'.");
            return self::FAILURE;
        }
        $template = (string) file_get_contents($templatePath);

        $vars = $this->collectVariables($standard, $periodStart, $periodEnd, $oci);
        $rendered = $this->renderTemplate($template, $vars);

        $out = $this->option('out');
        if ($out) {
            $dir = dirname($out);
            if (! is_dir($dir)) {
                @mkdir($dir, 0o755, true);
            }
            file_put_contents($out, $rendered);
            $this->info("Wrote " . strlen($rendered) . " bytes to {$out}");
        } else {
            $this->line($rendered);
        }
        return self::SUCCESS;
    }

    /**
     * @return array{0:string,1:string}
     */
    protected function resolvePeriod(?string $period): array
    {
        if ($period && str_contains($period, '..')) {
            [$a, $b] = array_map('trim', explode('..', $period, 2));
            return [$a, $b];
        }
        // Default to current calendar year.
        return [date('Y') . '-01-01', date('Y') . '-12-31'];
    }

    protected function collectVariables(string $standard, string $start, string $end, OciMovementService $oci): array
    {
        $vars = [
            'standard'             => strtoupper(str_replace('-', ' ', $standard)),
            'period_start'         => $start,
            'period_end'           => $end,
            'generated_at'         => date('Y-m-d H:i'),
            'reporting_entity'     => config('app.name', 'Heratio'),
        ];

        $assetCount = 0;
        $carrying = 0.0;
        if (Schema::hasTable('heritage_asset')) {
            $row = DB::table('heritage_asset')
                ->selectRaw('COUNT(*) AS n, COALESCE(SUM(current_carrying_amount),0) AS total')
                ->first();
            $assetCount = (int) ($row->n ?? 0);
            $carrying = (float) ($row->total ?? 0);
        }
        $vars['asset_count'] = number_format($assetCount);
        $vars['total_carrying_amount'] = number_format($carrying, 2);

        $summary = $oci->summariseForPeriod($start, $end);
        $vars['oci_revaluation_up'] = number_format($summary['revaluation_up'] ?? 0, 2);
        $vars['oci_revaluation_down'] = number_format($summary['revaluation_down'] ?? 0, 2);
        $vars['oci_impairment'] = number_format($summary['impairment'] ?? 0, 2);
        $vars['oci_reversal'] = number_format($summary['reversal'] ?? 0, 2);
        $vars['oci_disposal'] = number_format($summary['disposal'] ?? 0, 2);
        $vars['movements_to_oci'] = number_format($summary['by_posting']['OCI'] ?? 0, 2);
        $vars['movements_to_pl'] = number_format($summary['by_posting']['P&L'] ?? 0, 2);
        $vars['movements_to_reserve'] = number_format($summary['by_posting']['Reserve'] ?? 0, 2);
        $vars['movement_count'] = number_format($summary['count'] ?? 0);

        $valuerLines = '';
        if (Schema::hasTable('ahg_valuer')) {
            $valuers = DB::table('ahg_valuer')->where('active', 1)
                ->orderBy('name')->limit(50)->get();
            foreach ($valuers as $v) {
                $valuerLines .= sprintf(
                    "- %s%s%s\n",
                    $v->name,
                    $v->credential ? ' (' . $v->credential . ')' : '',
                    $v->professional_body ? ' - ' . $v->professional_body : ''
                );
            }
        }
        $vars['valuer_list'] = $valuerLines ?: '_No qualified valuers on record._';

        // Measurement basis distribution from heritage_asset
        $basisLines = '';
        if (Schema::hasTable('heritage_asset')) {
            $rows = DB::table('heritage_asset')
                ->selectRaw('COALESCE(measurement_basis, "unspecified") AS basis, COUNT(*) AS n')
                ->groupBy('basis')
                ->get();
            foreach ($rows as $r) {
                $basisLines .= sprintf("- %s: %s\n", $r->basis, number_format((int) $r->n));
            }
        }
        $vars['measurement_basis_summary'] = $basisLines ?: '_No assets recognised yet._';

        return $vars;
    }

    /**
     * Replace {{ var }} placeholders. Missing keys render as the empty string
     * so an incomplete dataset never crashes the audit pipeline.
     */
    protected function renderTemplate(string $tpl, array $vars): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($m) use ($vars) {
            return (string) ($vars[$m[1]] ?? '');
        }, $tpl);
    }
}
