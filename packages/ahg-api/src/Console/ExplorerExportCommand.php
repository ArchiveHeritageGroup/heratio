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
 * Same-story links that ahg:cross-reference stored between the records are
 * included as links (with their share of wording in common), never as text.
 * --brand takes the client's colours from a saved copy of its site's page or
 * stylesheet (the CSS custom properties it declares); nothing is fetched.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgApi\Console;

use AhgApi\Services\ExplorerDataService;
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
        {--hide= : Comma-separated general words kept off the starting list and the map (still searchable)}
        {--title= : Heading shown on the page}
        {--brand= : A saved page or stylesheet of the client site; its declared colour tokens brand the explorer}
        {--out= : Folder to write (default storage/app/explorer/<slug of the title>)}';

    protected $description = 'Export an offline relationship explorer (subjects, places, names) for a collection or a year';

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

        $data = ExplorerDataService::build($ids, $this->option('base-url') ?: config('app.url'),
            (int) $this->option('min'), (string) $this->option('hide'), (string) $this->option('year'));

        $title = $this->option('title') ?: 'Relationship explorer';
        $out = $this->option('out') ?: storage_path('app/explorer/'.Str::slug($title));
        if (! is_dir($out) && ! mkdir($out, 0775, true)) {
            $this->error("Cannot create {$out}");

            return self::FAILURE;
        }
        $data = ['site' => ['title' => $title, 'scope' => $this->scopeLabel(), 'generated' => now()->toDateString()]] + $data;
        file_put_contents("$out/data.js", 'window.DATA = '.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).";\n");
        $page = file_get_contents(ExplorerDataService::template());
        if ($brand = $this->option('brand')) {
            if (! is_readable($brand)) {
                $this->error("Cannot read {$brand}");

                return self::FAILURE;
            }
            $style = ExplorerDataService::brandStyle((string) file_get_contents($brand));
            $this->line($style === '' ? 'No colour tokens found in the brand file; the default colours are kept.' : 'Branded from '.$brand.'.');
            $page = str_replace('</head>', $style.'</head>', $page);
        }
        file_put_contents("$out/index.html", $page);
        copy(public_path('vendor/cytoscape/cytoscape.min.js'), "$out/cytoscape.min.js");
        copy(dirname(ExplorerDataService::template()).'/map.js', "$out/map.js"); // the collection map view (heratio#1567)

        $this->info(sprintf('%d records, %d names. Written to %s - open index.html in a browser.', count($data['records']), count($data['entities']), $out));

        return self::SUCCESS;
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
}
