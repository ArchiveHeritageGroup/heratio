<?php

/**
 * LostPlaceGatherService - #1323 "Lost Places" POC, increment 1 (evidence gather).
 *
 * Given a place (an AtoM place access point in the places taxonomy, which the
 * RiC graph mirrors as a rico:Place), collect every archival record linked to
 * it and the digital media those records hold, then compute a coverage metric
 * that tells a curator whether there is enough photographic evidence to attempt
 * a 3D reconstruction.
 *
 * "The place persists in the graph even when it's gone": we also report whether
 * the place exists as a rico:Place node (the deprecate-not-delete guarantee from
 * the #1319 governance pin), so a vanished place is still addressable.
 *
 * Read-only. The CLIP-based discovery of UNLINKED candidate photos (issue #1272)
 * is a later increment - this step gathers the explicitly-linked evidence set.
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

namespace AhgExhibition\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LostPlaceGatherService
{
    /** Places taxonomy id (AhgCore\Models\Taxonomy::PLACE_ID). */
    private const PLACE_TAXONOMY_ID = 42;

    /**
     * Photogrammetry / gaussian-splat coverage bands (master image count).
     * Soft, POC-level guidance - not a guarantee of reconstruction quality.
     */
    private const COVERAGE_BANDS = [
        ['min' => 40, 'level' => 'strong',       'note' => 'Dense coverage - photogrammetry / gaussian-splat viable.'],
        ['min' => 12, 'level' => 'workable',     'note' => 'Partial coverage - a rough model is achievable; expect gap-fill.'],
        ['min' => 1,  'level' => 'sparse',       'note' => 'Sparse coverage - reconstruction will be heavily inferred.'],
        ['min' => 0,  'level' => 'insufficient', 'note' => 'No linked imagery - gather more evidence (try CLIP discovery, #1272).'],
    ];

    /**
     * Resolve a place to a place-taxonomy term.
     *
     * @param  string  $query  numeric term id, or a (partial) place name
     * @return object|null  {term_id, name}
     */
    public function resolvePlace(string $query): ?object
    {
        $base = DB::table('term')
            ->leftJoin('term_i18n', function ($j) {
                $j->on('term.id', '=', 'term_i18n.id')
                    ->on('term_i18n.culture', '=', 'term.source_culture');
            })
            ->where('term.taxonomy_id', self::PLACE_TAXONOMY_ID);

        if (ctype_digit($query)) {
            $row = (clone $base)->where('term.id', (int) $query)
                ->select('term.id as term_id', 'term_i18n.name')->first();
            if ($row) {
                return $row;
            }
        }

        return (clone $base)
            ->where('term_i18n.name', 'like', '%'.$query.'%')
            ->orderByRaw('CHAR_LENGTH(COALESCE(term_i18n.name, "")) asc')
            ->select('term.id as term_id', 'term_i18n.name')
            ->first();
    }

    /**
     * Gather the full evidence set + coverage summary for a place.
     *
     * @return array{place:?array,in_ric_graph:bool,records:array,coverage:array}
     */
    public function gather(string $query, int $limit = 0): array
    {
        $place = $this->resolvePlace($query);
        if (! $place) {
            return [
                'place'        => null,
                'in_ric_graph' => false,
                'records'      => [],
                'coverage'     => $this->summarise([]),
            ];
        }

        $records = $this->recordsForPlace((int) $place->term_id, $limit);

        return [
            'place'        => ['term_id' => (int) $place->term_id, 'name' => $place->name],
            'in_ric_graph' => $this->existsInRicGraph((string) $place->name),
            'records'      => $records,
            'coverage'     => $this->summarise($records),
        ];
    }

    /**
     * Every information object linked to the place term, with per-record media
     * counts (master digital objects only).
     */
    public function recordsForPlace(int $termId, int $limit = 0): array
    {
        $q = DB::table('object_term_relation as otr')
            ->join('information_object as io', 'otr.object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('io.id', '=', 'i18n.id')->on('i18n.culture', '=', 'io.source_culture');
            })
            ->leftJoin('slug', 'slug.object_id', '=', 'io.id')
            ->where('otr.term_id', $termId)
            ->select('io.id', 'i18n.title', 'io.identifier', 'slug.slug')
            ->orderBy('io.id');

        if ($limit > 0) {
            $q->limit($limit);
        }

        $records = $q->get();
        if ($records->isEmpty()) {
            return [];
        }

        $ids = $records->pluck('id')->all();
        $media = $this->mediaCounts($ids);

        return $records->map(function ($r) use ($media) {
            $m = $media[$r->id] ?? ['image' => 0, 'document' => 0, 'other' => 0];

            return [
                'id'             => (int) $r->id,
                'title'          => $r->title ?? '(untitled)',
                'identifier'     => $r->identifier,
                'slug'           => $r->slug,
                'image_count'    => $m['image'],
                'document_count' => $m['document'],
                'media_count'    => $m['image'] + $m['document'] + $m['other'],
            ];
        })->all();
    }

    /**
     * Master digital-object counts per information object, split by media kind.
     *
     * @param  int[]  $ioIds
     * @return array<int,array{image:int,document:int,other:int}>
     */
    private function mediaCounts(array $ioIds): array
    {
        $rows = DB::table('digital_object')
            ->whereIn('object_id', $ioIds)
            ->whereNull('parent_id') // masters only (derivatives hang off a master)
            ->select('object_id', 'mime_type')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $oid = (int) $row->object_id;
            $out[$oid] ??= ['image' => 0, 'document' => 0, 'other' => 0];
            $mime = strtolower((string) $row->mime_type);
            if (str_starts_with($mime, 'image/')) {
                $out[$oid]['image']++;
            } elseif (str_starts_with($mime, 'application/pdf') || str_starts_with($mime, 'text/')) {
                $out[$oid]['document']++;
            } else {
                $out[$oid]['other']++;
            }
        }

        return $out;
    }

    /**
     * #1323 / #1272 scaffolding: discover UNLINKED candidate photos for a place
     * by visual similarity. Seeds from the place's already-linked master images
     * and asks the CLIP image index (ahg-discovery) for look-alikes that are NOT
     * yet linked to the place - loosely-described or uncatalogued media a curator
     * can then attach (provenance-tagged).
     *
     * Degrades gracefully: returns available=false when the discovery stack
     * (ahg-discovery / the archive_images Qdrant index) is absent, and an empty
     * candidate set with a note when the place has no seed imagery to match on
     * (the case that needs the #1272 .78 embed service for text/fresh-image
     * seeding - still pending).
     *
     * @return array{available:bool,seeds:int,candidates:array,note:?string}
     */
    public function discoverCandidates(int $termId, int $perSeed = 20, int $max = 50): array
    {
        $strategyClass = 'AhgDiscovery\\Services\\Search\\ImageSearchStrategy';
        if (! class_exists($strategyClass)) {
            return ['available' => false, 'seeds' => 0, 'candidates' => [], 'note' => 'ahg-discovery image search not installed.'];
        }

        $records = $this->recordsForPlace($termId);
        $linkedIds = array_map(static fn ($r) => $r['id'], $records);
        if (! $linkedIds) {
            return ['available' => true, 'seeds' => 0, 'candidates' => [], 'note' => 'No records linked to this place to seed discovery from.'];
        }

        // Master image digital objects of the linked records = the Qdrant point ids.
        $seedPointIds = DB::table('digital_object')
            ->whereIn('object_id', $linkedIds)
            ->whereNull('parent_id')
            ->where('mime_type', 'like', 'image/%')
            ->pluck('id')
            ->all();

        if (! $seedPointIds) {
            return [
                'available' => true,
                'seeds'     => 0,
                'candidates' => [],
                'note'      => 'No linked imagery to seed visual discovery; needs text/fresh-image embedding (#1272, pending).',
            ];
        }

        try {
            $strategy = app($strategyClass);
            $linkedSet = array_flip($linkedIds);
            $best = [];
            foreach ($seedPointIds as $pointId) {
                foreach ($strategy->searchByExistingObject((int) $pointId, $perSeed) as $hit) {
                    $ioId = (int) ($hit['object_id'] ?? 0);
                    if ($ioId === 0 || isset($linkedSet[$ioId])) {
                        continue; // skip self / already-linked records
                    }
                    $score = (float) ($hit['score'] ?? 0);
                    if (! isset($best[$ioId]) || $score > $best[$ioId]['score']) {
                        $best[$ioId] = [
                            'information_object_id' => $ioId,
                            'score' => $score,
                            'slug'  => $hit['slug'] ?? null,
                            'title' => $hit['title'] ?? null,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            return ['available' => false, 'seeds' => count($seedPointIds), 'candidates' => [], 'note' => 'Image index unavailable: '.$e->getMessage()];
        }

        usort($best, static fn ($a, $b) => $b['score'] <=> $a['score']);

        return [
            'available'  => true,
            'seeds'      => count($seedPointIds),
            'candidates' => array_slice(array_values($best), 0, $max),
            'note'       => null,
        ];
    }

    /** Is this place present as a rico:Place node (graph persistence / #1319)? */
    private function existsInRicGraph(string $name): bool
    {
        if ($name === '' || ! Schema::hasTable('ric_place_i18n')) {
            return false;
        }

        return DB::table('ric_place_i18n')->where('name', 'like', '%'.$name.'%')->exists();
    }

    /**
     * Coverage roll-up over a record set.
     *
     * @param  array  $records  output of recordsForPlace()
     */
    private function summarise(array $records): array
    {
        $withMedia = 0;
        $imageTotal = 0;
        $documentTotal = 0;
        foreach ($records as $r) {
            if (($r['media_count'] ?? 0) > 0) {
                $withMedia++;
            }
            $imageTotal += $r['image_count'] ?? 0;
            $documentTotal += $r['document_count'] ?? 0;
        }
        $total = count($records);

        $band = self::COVERAGE_BANDS[count(self::COVERAGE_BANDS) - 1];
        foreach (self::COVERAGE_BANDS as $b) {
            if ($imageTotal >= $b['min']) {
                $band = $b;
                break;
            }
        }

        return [
            'records_total'        => $total,
            'records_with_media'   => $withMedia,
            'records_without_media' => $total - $withMedia,
            'image_total'          => $imageTotal,
            'document_total'       => $documentTotal,
            'coverage_pct'         => $total > 0 ? (int) round($withMedia / $total * 100) : 0,
            'reconstruction_level' => $band['level'],
            'reconstruction_note'  => $band['note'],
        ];
    }
}
