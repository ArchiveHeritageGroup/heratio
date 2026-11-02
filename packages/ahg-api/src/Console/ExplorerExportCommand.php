<?php

/**
 * ExplorerExportCommand - write a stand-alone relationship explorer for a
 * slice of the catalogue (heratio#1524, catalogue part): an offline folder
 * (index.html + data.js + cytoscape.min.js) that opens from a USB drive or
 * file share with no server, in the shape of the October 2026 Brenthurst
 * cuttings explorer.
 *
 * Built from catalogue data only - subjects, places, genres, name access
 * points and creators - never from transcripts or file text. Published
 * records only. Each entity is grouped by its printed form; spellings are
 * merged only where they differ in case, punctuation or a leading "The"
 * ("ANC" and "African National Congress" stay apart: merging those is a
 * cataloguing decision, not the exporter's).
 *
 * ponytail: no same-story links (needs item text) and no client branding
 * yet (needs the client's site tokens); both are later parts of #1524.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgApi\Console;

use AhgApi\Services\GraphExplorerService as G;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExplorerExportCommand extends Command
{
    protected $signature = 'ahg:explorer-export
        {--ancestor= : A collection or fonds (id or slug); the explorer covers it and everything under it}
        {--year= : Only records dated in this year}
        {--limit=8000 : Most records to include}
        {--min=2 : Leave out names that occur on fewer records than this}
        {--base-url= : Site address the record links point at (default APP_URL)}
        {--title= : Heading shown on the page}
        {--out= : Folder to write (default storage/app/explorer/<slug of the title>)}';

    protected $description = 'Export an offline relationship explorer (subjects, places, names) for a collection or a year';

    /** taxonomy id => explorer type */
    private const TAXONOMIES = [G::TAXONOMY_SUBJECT => 'subject', G::TAXONOMY_PLACE => 'place', G::TAXONOMY_GENRE => 'genre'];

    public function handle(): int
    {
        $ids = $this->scope();
        if ($ids === null) {
            return self::FAILURE;
        }
        if ($ids === []) {
            $this->error('No published records in that scope.');

            return self::FAILURE;
        }
        $this->info(count($ids).' records in scope.');

        $base = rtrim($this->option('base-url') ?: config('app.url'), '/');
        $records = [];
        $mentions = []; // "type|key" => [printed form => [record ids]]
        foreach (array_chunk($ids, 1000) as $chunk) {
            foreach ($this->records($chunk) as $r) {
                $records[$r->id] = ['id' => (string) $r->id, 'title' => trim(html_entity_decode(strip_tags((string) $r->title), ENT_QUOTES)), 'ref' => (string) $r->identifier,
                    'date' => '', 'url' => $r->slug ? "{$base}/{$r->slug}" : null, 'ents' => []];
            }
            foreach ($this->dates($chunk) as $id => $date) {
                if (isset($records[$id])) {
                    $records[$id]['date'] = $date;
                }
            }
            foreach ($this->names($chunk) as [$id, $type, $name]) {
                $name = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $name)));
                $key = self::groupKey($name);
                if ($key !== '' && isset($records[$id])) {
                    $mentions["$type|$key"][$name][$id] = true;
                }
            }
        }

        $entities = [];
        $min = max(1, (int) $this->option('min'));
        foreach ($mentions as $ek => $forms) {
            $recs = array_keys(array_replace(...array_values($forms)));
            if (count($recs) < $min) {
                continue;
            }
            uksort($forms, fn ($a, $b) => count($forms[$b]) <=> count($forms[$a]) ?: strcmp($a, $b));
            [$type, $key] = explode('|', $ek, 2);
            $id = "$type:$key";
            $entities[] = ['id' => $id, 'type' => $type, 'name' => (string) array_key_first($forms),
                'variants' => array_map('strval', array_keys($forms)), 'recs' => array_map('strval', $recs)];
            foreach ($recs as $rid) {
                $records[$rid]['ents'][] = $id;
            }
        }

        $title = $this->option('title') ?: 'Relationship explorer';
        $out = $this->option('out') ?: storage_path('app/explorer/'.Str::slug($title));
        if (! is_dir($out) && ! mkdir($out, 0775, true)) {
            $this->error("Cannot create {$out}");

            return self::FAILURE;
        }
        $data = ['site' => ['title' => $title, 'scope' => $this->scopeLabel(), 'generated' => now()->toDateString()],
            'records' => array_values($records), 'entities' => $entities];
        file_put_contents("$out/data.js", 'window.DATA = '.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).";\n");
        copy(__DIR__.'/../../resources/explorer/index.html', "$out/index.html");
        copy(public_path('vendor/cytoscape/cytoscape.min.js'), "$out/cytoscape.min.js");

        $this->info(sprintf('%d records, %d names. Written to %s - open index.html in a browser.', count($records), count($entities), $out));

        return self::SUCCESS;
    }

    /** Group spellings that differ only in case, punctuation, accents or a leading "The". */
    public static function groupKey(string $name): string
    {
        $k = Str::lower(Str::ascii($name));
        $k = preg_replace('/^the\s+/', '', $k);

        return preg_replace('/[^a-z0-9]+/', '', $k);
    }

    /** @return list<int>|null published record ids in scope, null on a bad option */
    private function scope(): ?array
    {
        $q = DB::table('information_object as io')
            ->join('status as st', fn ($j) => $j->on('st.object_id', '=', 'io.id')->where('st.type_id', G::STATUS_TYPE_PUBLICATION))
            ->where('st.status_id', G::STATUS_PUBLISHED)->where('io.id', '!=', G::ROOT_ID);

        if ($a = $this->option('ancestor')) {
            $root = DB::table('information_object')->where('id', ctype_digit((string) $a) ? (int) $a : DB::table('slug')->where('slug', $a)->value('object_id'))->first(['lft', 'rgt']);
            if (! $root) {
                $this->error("No collection {$a}.");

                return null;
            }
            $q->whereBetween('io.lft', [$root->lft, $root->rgt]);
        }
        if ($y = $this->option('year')) {
            if (! preg_match('/^\d{4}$/', (string) $y)) {
                $this->error('--year takes four digits.');

                return null;
            }
            $q->whereExists(fn ($e) => $e->from('event as e')->leftJoin('event_i18n as ei', 'ei.id', '=', 'e.id')->whereColumn('e.object_id', 'io.id')
                ->where(fn ($w) => $w->whereYear('e.start_date', (int) $y)->orWhere('ei.date', 'like', "%{$y}")));
        }
        if (! $this->option('ancestor') && ! $this->option('year')) {
            $this->error('Give --ancestor or --year (or both): the whole catalogue is too big to explore in a browser.');

            return null;
        }

        return $q->orderBy('io.lft')->limit(max(1, (int) $this->option('limit')))->pluck('io.id')->map(fn ($i) => (int) $i)->all();
    }

    private function scopeLabel(): string
    {
        return trim(($this->option('ancestor') ? 'collection '.$this->option('ancestor') : '').' '.($this->option('year') ? 'dated '.$this->option('year') : ''));
    }

    private function records(array $ids)
    {
        $culture = config('app.fallback_locale', 'en');

        return DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i', fn ($j) => $j->on('i.id', '=', 'io.id')->where('i.culture', $culture))
            ->leftJoin('information_object_i18n as s', fn ($j) => $j->on('s.id', '=', 'io.id')->on('s.culture', '=', 'io.source_culture'))
            ->leftJoin('slug', 'slug.object_id', '=', 'io.id')
            ->whereIn('io.id', $ids)
            ->get(['io.id', 'io.identifier', DB::raw('COALESCE(i.title, s.title) as title'), 'slug.slug']);
    }

    /** @return array<int,string> record id => display date (with --year, one in that year), else the start date */
    private function dates(array $ids): array
    {
        $out = [];
        $year = (string) $this->option('year');
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
    private function names(array $ids): iterable
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
