<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgReports\Controllers\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared card builders for the reporting cockpits. card(), resolveMetric()
 * and summarise() were byte-identical in NorthStarCockpitController and
 * TrustConsoleController. All self-contained (no host state).
 */
trait BuildsReportCards
{
    private function card(
        string $key,
        string $title,
        string $description,
        string $icon,
        string $route,
        array $opts = []
    ): array {
        return [
            'key'            => $key,
            'title'          => $title,
            'description'    => $description,
            'icon'           => $icon,
            'route'          => $route,
            'fallback_route' => $opts['fallback_route'] ?? null,
            'route_param'    => $opts['route_param'] ?? [],
            'metric_table'   => $opts['metric_table'] ?? null,
            'metric_label'   => $opts['metric_label'] ?? null,
            'url'            => null,
            'metric'         => null,
        ];
    }

    private function resolveMetric(array $card): ?array
    {
        $table = $card['metric_table'] ?? null;

        if (! $table) {
            return null;
        }

        try {
            if (! Schema::hasTable($table)) {
                return null;
            }

            $count = DB::table($table)->count();

            return [
                'value' => $count,
                'label' => $card['metric_label'] ?? 'records',
            ];
        } catch (\Throwable $e) {
            // Absent package, missing column, locked table, driver error -
            // none of these should ever break the cockpit. No metric shown.
            return null;
        }
    }

    private function summarise(array $groups): array
    {
        $total = 0;
        $live  = 0;

        foreach ($groups as $group) {
            foreach ($group['cards'] as $card) {
                $total++;
                if (! empty($card['url'])) {
                    $live++;
                }
            }
        }

        return [
            'total' => $total,
            'live'  => $live,
        ];
    }
}
