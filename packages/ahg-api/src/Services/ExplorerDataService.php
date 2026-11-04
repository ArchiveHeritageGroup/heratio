<?php

/**
 * ExplorerDataService - the records and names behind the relationship
 * explorer: each record with its subjects, places, genres and people, and each
 * name with the records it appears on (spellings that differ only in case,
 * punctuation, accents or a leading "The" grouped as one).
 *
 * Shared by the offline export (ahg:explorer-export, heratio#1524) and the live
 * "Visual graph" of a GLAM search, which serve the same page.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgApi\Services;

use AhgApi\Services\GraphExplorerService as G;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExplorerDataService
{
    /** taxonomy id => explorer type */
    private const TAXONOMIES = [G::TAXONOMY_SUBJECT => 'subject', G::TAXONOMY_PLACE => 'place', G::TAXONOMY_GENRE => 'genre'];

    /**
     * @param  list<int>  $ids  records, in the order to list them
     * @param  int  $min  leave out names on fewer records than this
     * @param  string  $hide  comma-separated general words kept off the starting list and the map
     * @param  string  $year  prefer a date in this year when a record has several
     * @return array{records: list<array>, entities: list<array>}
     */
    public static function build(array $ids, string $base, int $min = 1, string $hide = '', string $year = ''): array
    {
        $base = rtrim($base, '/');
        $records = [];
        $mentions = []; // "type|key" => [printed form => [record ids]]
        foreach (array_chunk($ids, 1000) as $chunk) {
            foreach (self::records($chunk) as $r) {
                $records[$r->id] = ['id' => (string) $r->id, 'title' => trim(html_entity_decode(strip_tags((string) $r->title), ENT_QUOTES)), 'ref' => (string) $r->identifier,
                    'date' => '', 'url' => $r->slug ? "{$base}/{$r->slug}" : null, 'ents' => []];
            }
            foreach (self::dates($chunk, $year) as $id => $date) {
                if (isset($records[$id])) {
                    $records[$id]['date'] = $date;
                }
            }
            foreach (self::names($chunk) as [$id, $type, $name]) {
                $name = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $name)));
                $key = self::groupKey($name);
                if ($key !== '' && isset($records[$id])) {
                    $mentions["$type|$key"][$name][$id] = true;
                }
            }
        }

        $entities = [];
        $min = max(1, $min);
        $hide = array_flip(array_map(self::groupKey(...), explode(',', $hide)));
        foreach ($mentions as $ek => $forms) {
            $recs = array_keys(array_replace(...array_values($forms)));
            if (count($recs) < $min) {
                continue;
            }
            uksort($forms, fn ($a, $b) => count($forms[$b]) <=> count($forms[$a]) ?: strcmp($a, $b));
            [$type, $key] = explode('|', $ek, 2);
            $id = "$type:$key";
            $entities[] = ['id' => $id, 'type' => $type, 'name' => (string) array_key_first($forms),
                'variants' => array_map('strval', array_keys($forms)), 'recs' => array_map('strval', $recs)]
                + (isset($hide[$key]) ? ['hidden' => true] : []);
            foreach ($recs as $rid) {
                $records[$rid]['ents'][] = $id;
            }
        }

        return ['records' => array_values($records), 'entities' => $entities];
    }

    /**
     * The explorer page with its data and script inlined, for serving from the
     * site. $live adds the catalogue search and "load more" (see index.html).
     */
    public static function page(array $data, ?array $live = null): string
    {
        $json = fn ($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

        // Live on a site, the page takes the site's theme colours (header,
        // header text, links) in place of the export's fixed palette.
        $theme = $live !== null && \Illuminate\Support\Facades\Route::has('settings.dynamic-css')
            ? '<link rel="stylesheet" href="'.e(route('settings.dynamic-css').'?v='.(@filemtime(public_path('css/ahg-theme-dynamic.css')) ?: 0)).'">'
                .'<style>:root { --head: var(--ahg-header-bg, #10373E); --headtext: var(--ahg-header-text, #F2EDE6);'
                .' --primary: var(--ahg-header-text, #E1B34F); --accent: var(--ahg-link-color, #653332); }'
                // the site's link colour is for light backgrounds; header links take the header text colour
                .' header a { color: var(--headtext) !important; }</style>'."\n"
            : '';

        return str_replace(
            ['</head>', '<script src="cytoscape.min.js"></script>', '<script src="data.js"></script>'],
            [$theme.'</head>', '<script src="'.e(asset('vendor/cytoscape/cytoscape.min.js')).'"></script>',
                '<script>window.DATA = '.$json($data).'; window.LIVE = '.$json($live).';</script>'],
            file_get_contents(self::template()));
    }

    public static function template(): string
    {
        return __DIR__.'/../../resources/explorer/index.html';
    }

    /** Group spellings that differ only in case, punctuation, accents or a leading "The". */
    public static function groupKey(string $name): string
    {
        $k = Str::lower(Str::ascii(trim($name)));
        $k = preg_replace('/^the\s+/', '', $k);

        return preg_replace('/[^a-z0-9]+/', '', $k);
    }

    private static function records(array $ids)
    {
        $culture = config('app.fallback_locale', 'en');

        return DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i', fn ($j) => $j->on('i.id', '=', 'io.id')->where('i.culture', $culture))
            ->leftJoin('information_object_i18n as s', fn ($j) => $j->on('s.id', '=', 'io.id')->on('s.culture', '=', 'io.source_culture'))
            ->leftJoin('slug', 'slug.object_id', '=', 'io.id')
            ->whereIn('io.id', $ids)
            ->orderByRaw('FIELD(io.id, '.implode(',', array_map('intval', $ids)).')')
            ->get(['io.id', 'io.identifier', DB::raw('COALESCE(i.title, s.title) as title'), 'slug.slug']);
    }

    /** @return array<int,string> record id => display date (with $year, one in that year), else the start date */
    private static function dates(array $ids, string $year): array
    {
        $out = [];
        $rows = DB::table('event as e')->leftJoin('event_i18n as ei', 'ei.id', '=', 'e.id')->whereIn('e.object_id', $ids)
            ->orderBy('e.id')->get(['e.object_id', 'e.start_date', 'ei.date']);
        foreach ($rows as $r) {
            $d = $r->start_date ? substr((string) $r->start_date, 0, 10) : trim((string) $r->date);
            $id = (int) $r->object_id;
            if ($d !== '' && (! isset($out[$id]) || ($year !== '' && ! str_contains($out[$id], $year) && str_contains($d, $year)))) {
                $out[$id] = $d;
            }
        }

        return $out;
    }

    /** @return iterable<array{0:int,1:string,2:string}> [record id, type, printed name] */
    private static function names(array $ids): iterable
    {
        $culture = config('app.fallback_locale', 'en');
        $terms = DB::table('object_term_relation as r')->join('term as t', 't.id', '=', 'r.term_id')
            ->leftJoin('term_i18n as ti', fn ($j) => $j->on('ti.id', '=', 't.id')->where('ti.culture', $culture))
            ->leftJoin('term_i18n as ts', fn ($j) => $j->on('ts.id', '=', 't.id')->on('ts.culture', '=', 't.source_culture'))
            ->whereIn('r.object_id', $ids)->whereIn('t.taxonomy_id', array_keys(self::TAXONOMIES))
            ->get(['r.object_id', 't.taxonomy_id', DB::raw('COALESCE(ti.name, ts.name) as name')]);
        foreach ($terms as $r) {
            yield [(int) $r->object_id, self::TAXONOMIES[(int) $r->taxonomy_id], (string) $r->name];
        }

        // People and bodies: name access points and creators.
        $actor = fn ($q, string $col) => $q->join('actor as a', 'a.id', '=', $col)
            ->leftJoin('actor_i18n as ai', fn ($j) => $j->on('ai.id', '=', 'a.id')->where('ai.culture', $culture))
            ->leftJoin('actor_i18n as ac', fn ($j) => $j->on('ac.id', '=', 'a.id')->on('ac.culture', '=', 'a.source_culture'));
        $name = DB::raw('COALESCE(ai.authorized_form_of_name, ac.authorized_form_of_name) as name');
        $named = $actor(DB::table('relation as rl'), 'rl.object_id')->where('rl.type_id', 161)->whereIn('rl.subject_id', $ids)
            ->get([DB::raw('rl.subject_id as rid'), $name]);
        $creators = $actor(DB::table('event as e'), 'e.actor_id')->whereIn('e.object_id', $ids)
            ->get([DB::raw('e.object_id as rid'), $name]);
        foreach ($named->concat($creators) as $r) {
            yield [(int) $r->rid, 'name', (string) $r->name];
        }
    }
}
