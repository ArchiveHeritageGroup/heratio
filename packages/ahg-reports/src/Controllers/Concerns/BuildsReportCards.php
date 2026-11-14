<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgReports\Controllers\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
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
            // [table, record-id column] the '_sample_' record must have a row in.
            'sample_from'    => $opts['sample_from'] ?? null,
            'url'            => null,
            'metric'         => null,
        ];
    }

    /**
     * Resolve the live link for a card, preferring the primary route and
     * falling back to an alternate. Returns null (no link) whenever neither
     * route is registered, so the card still renders as "Not configured".
     * A '_sample_' route parameter becomes the slug of a real published record
     * (one with a row in the card's sample_from table when it names one):
     * '_sample_' itself is no record, so every per-record card opened a 404.
     */
    private function resolveUrl(array $card): ?string
    {
        $params = $card['route_param'] ?? [];
        foreach ($params as $k => $v) {
            if ($v === '_sample_') {
                $slug = $this->sampleSlug($card['sample_from'] ?? null);
                if ($slug === null) {
                    return null;
                }
                $params[$k] = $slug;
            }
        }

        foreach ([$card['route'], $card['fallback_route']] as $name) {
            if (! $name) {
                continue;
            }

            try {
                if (Route::has($name)) {
                    return route($name, $params);
                }
            } catch (\Throwable $e) {
                // URL generation can fail (a required parameter we cannot
                // satisfy). Skip silently; the card renders without a link.
            }
        }

        return null;
    }

    /**
     * Slug of a published record: the one with the most rows in $from when it
     * names a table, otherwise (or when no published record has any) the first
     * published record - the per-record pages say plainly when nothing is recorded.
     */
    private function sampleSlug(?array $from): ?string
    {
        return ($from ? $this->publishedSlug($from) : null) ?? $this->publishedSlug(null);
    }

    private function publishedSlug(?array $from): ?string
    {
        try {
            $q = DB::table('slug as s')
                ->join('information_object as io', 'io.id', '=', 's.object_id')
                ->join('status as st', function ($j) {
                    $j->on('st.object_id', '=', 'io.id')->where('st.type_id', 158)->where('st.status_id', 160);
                })
                ->where('io.id', '!=', 1);
            if ($from) {
                [$table, $col] = $from;
                if (! Schema::hasTable($table)) {
                    return null;
                }
                $q->join($table.' as x', 'x.'.$col, '=', 'io.id')
                    ->groupBy('s.slug')->orderByRaw('count(*) desc');
            } else {
                $q->orderBy('io.id');
            }

            return $q->value('s.slug');
        } catch (\Throwable $e) {
            return null;
        }
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
