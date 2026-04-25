<?php

/**
 * DisplayReindexCommand — rebuild the GLAM browse facet cache.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace AhgCore\Commands;

use AhgCore\Constants\TermId;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class DisplayReindexCommand extends Command
{
    protected $signature = 'ahg:display-reindex
        {--facet= : Only rebuild a specific facet (glam_type, creator, subject, place, genre, level, repository, media_type)}
        {--repository= : Only reindex a specific repository (reserved)}
        {--limit=50 : Max rows to store per facet variant}';

    protected $description = 'Rebuild GLAM browse facet cache';

    /** Subject taxonomy id. */
    private const TAX_SUBJECT = 35;
    /** Place taxonomy id. */
    private const TAX_PLACE = 42;
    /** Genre taxonomy id. */
    private const TAX_GENRE = 78;

    private const FACETS = [
        'glam_type', 'creator', 'subject', 'place',
        'genre', 'level', 'repository', 'media_type',
    ];

    public function handle(): int
    {
        $only = $this->option('facet');
        $limit = max(1, (int) $this->option('limit'));
        $culture = app()->getLocale();

        $targets = $only ? [$only] : self::FACETS;

        foreach ($targets as $facet) {
            if (!in_array($facet, self::FACETS, true)) {
                $this->warn("Unknown facet '{$facet}'. Supported: " . implode(', ', self::FACETS));
                continue;
            }
            $this->info("Rebuilding facet: {$facet}");
            $this->rebuildFacet($facet, $culture, $limit);
        }

        $this->info('Done.');
        return 0;
    }

    private function rebuildFacet(string $facet, string $culture, int $limit): void
    {
        // Wipe both variants for this facet.
        DB::table('display_facet_cache')
            ->whereIn('facet_type', [$facet, $facet . '_all'])
            ->delete();

        $publishedRows = $this->computeFacet($facet, $culture, true, $limit);
        $allRows = $this->computeFacet($facet, $culture, false, $limit);

        $now = now();
        $insert = [];
        foreach ($publishedRows as $r) {
            $insert[] = $this->shape($facet, $r, $now, false);
        }
        foreach ($allRows as $r) {
            $insert[] = $this->shape($facet, $r, $now, true);
        }
        if (!empty($insert)) {
            // Chunk to avoid placeholder limits on very wide facets (place can hit ~150 rows).
            foreach (array_chunk($insert, 500) as $chunk) {
                DB::table('display_facet_cache')->insert($chunk);
            }
        }

        $this->line(sprintf(
            '  %-12s  published=%d  all=%d',
            $facet,
            count($publishedRows),
            count($allRows)
        ));
    }

    /**
     * Returns array of stdClass with { id, name, count } sorted by count desc.
     */
    private function computeFacet(string $facet, string $culture, bool $publishedOnly, int $limit): array
    {
        $q = DB::table('information_object as io')
            ->leftJoin('display_object_config as doc', 'io.id', '=', 'doc.object_id')
            ->where('io.id', '>', 1);

        if ($publishedOnly) {
            $q->join('status as s_pub', function ($j) {
                $j->on('s_pub.object_id', '=', 'io.id')
                    ->where('s_pub.type_id', '=', TermId::STATUS_TYPE_PUBLICATION)
                    ->where('s_pub.status_id', '=', TermId::PUBLICATION_STATUS_PUBLISHED);
            });
        }

        switch ($facet) {
            case 'glam_type':
                $q->whereNotNull('doc.object_type')
                    ->select('doc.object_type as facet_id', 'doc.object_type as facet_name', DB::raw('COUNT(DISTINCT io.id) as cnt'))
                    ->groupBy('doc.object_type');
                break;

            case 'creator':
                $q->join('event as ef', 'ef.object_id', '=', 'io.id')
                    ->join('actor_i18n as ai', function ($j) use ($culture) {
                        $j->on('ef.actor_id', '=', 'ai.id')->where('ai.culture', '=', $culture);
                    })
                    ->select('ef.actor_id as facet_id', 'ai.authorized_form_of_name as facet_name', DB::raw('COUNT(DISTINCT io.id) as cnt'))
                    ->groupBy('ef.actor_id', 'ai.authorized_form_of_name');
                break;

            case 'subject':
                $this->joinTaxonomy($q, $culture, 'otr_s', 'ts', 'tis', self::TAX_SUBJECT);
                $q->select('ts.id as facet_id', 'tis.name as facet_name', DB::raw('COUNT(DISTINCT io.id) as cnt'))
                    ->groupBy('ts.id', 'tis.name');
                break;

            case 'place':
                $this->joinTaxonomy($q, $culture, 'otr_p', 'tp', 'tip', self::TAX_PLACE);
                $q->select('tp.id as facet_id', 'tip.name as facet_name', DB::raw('COUNT(DISTINCT io.id) as cnt'))
                    ->groupBy('tp.id', 'tip.name');
                break;

            case 'genre':
                $this->joinTaxonomy($q, $culture, 'otr_g', 'tg', 'tig', self::TAX_GENRE);
                $q->select('tg.id as facet_id', 'tig.name as facet_name', DB::raw('COUNT(DISTINCT io.id) as cnt'))
                    ->groupBy('tg.id', 'tig.name');
                break;

            case 'level':
                $q->join('term_i18n as til', function ($j) use ($culture) {
                    $j->on('io.level_of_description_id', '=', 'til.id')->where('til.culture', '=', $culture);
                })
                    ->whereNotNull('io.level_of_description_id')
                    ->select('io.level_of_description_id as facet_id', 'til.name as facet_name', DB::raw('COUNT(DISTINCT io.id) as cnt'))
                    ->groupBy('io.level_of_description_id', 'til.name');
                break;

            case 'repository':
                $q->join('actor_i18n as rai', function ($j) use ($culture) {
                    $j->on('io.repository_id', '=', 'rai.id')->where('rai.culture', '=', $culture);
                })
                    ->whereNotNull('io.repository_id')
                    ->select('io.repository_id as facet_id', 'rai.authorized_form_of_name as facet_name', DB::raw('COUNT(DISTINCT io.id) as cnt'))
                    ->groupBy('io.repository_id', 'rai.authorized_form_of_name');
                break;

            case 'media_type':
                $q->join('digital_object as dof', function ($j) {
                    $j->on('dof.object_id', '=', 'io.id')->whereNull('dof.parent_id');
                })
                    ->select(
                        DB::raw("SUBSTRING_INDEX(dof.mime_type, '/', 1) as facet_id"),
                        DB::raw("SUBSTRING_INDEX(dof.mime_type, '/', 1) as facet_name"),
                        DB::raw('COUNT(DISTINCT io.id) as cnt')
                    )
                    ->groupBy(DB::raw("SUBSTRING_INDEX(dof.mime_type, '/', 1)"));
                break;

            default:
                return [];
        }

        return $q->orderByDesc('cnt')->limit($limit)->get()->all();
    }

    private function joinTaxonomy(Builder $q, string $culture, string $relAlias, string $termAlias, string $i18nAlias, int $taxonomyId): void
    {
        $q->join("object_term_relation as {$relAlias}", "{$relAlias}.object_id", '=', 'io.id')
            ->join("term as {$termAlias}", function ($j) use ($relAlias, $termAlias, $taxonomyId) {
                $j->on("{$relAlias}.term_id", '=', "{$termAlias}.id")
                    ->where("{$termAlias}.taxonomy_id", '=', $taxonomyId);
            })
            ->join("term_i18n as {$i18nAlias}", function ($j) use ($termAlias, $i18nAlias, $culture) {
                $j->on("{$termAlias}.id", '=', "{$i18nAlias}.id")
                    ->where("{$i18nAlias}.culture", '=', $culture);
            });
    }

    /**
     * Glam_type and media_type store the categorical name in term_name (term_id = 0).
     * Other facets store both: numeric term_id + human-readable term_name.
     */
    private function shape(string $facet, $row, $now, bool $isAllVariant): array
    {
        $useNameOnly = in_array($facet, ['glam_type', 'media_type'], true);
        return [
            'facet_type' => $isAllVariant ? $facet . '_all' : $facet,
            'term_id' => $useNameOnly ? 0 : (int) $row->facet_id,
            'term_name' => (string) ($row->facet_name ?? ''),
            'count' => (int) $row->cnt,
            'created_at' => $now,
        ];
    }
}
