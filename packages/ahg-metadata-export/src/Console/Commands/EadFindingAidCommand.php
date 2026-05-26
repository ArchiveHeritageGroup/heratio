<?php

/**
 * EadFindingAidCommand - artisan ead:finding-aid {ioId} {--out=}
 *
 * Generates a print-friendly PDF finding aid for an information object
 * and its descendants. Layout follows the Library of Congress finding-
 * aid conventions: title block, repository, abstract / scope, dates,
 * extent, biographical / historical note, scope and content note,
 * arrangement, access and use restrictions, related material, then
 * the container list (descendants in MPTT order).
 *
 * Renders Markdown-ish HTML via dompdf (already required by Heratio
 * via composer.json - "dompdf/dompdf": "^3.1"). Falls back to writing
 * an HTML file when dompdf is missing so CI / smoke tests still work.
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
 */

namespace AhgMetadataExport\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EadFindingAidCommand extends Command
{
    protected $signature = 'ead:finding-aid {ioId : Information-object id} {--out= : Output PDF path (default: storage/app/finding-aids/<id>.pdf)} {--culture=en}';

    protected $description = 'Generate a print-friendly PDF finding aid for an IO and its descendants (Library of Congress style).';

    public function handle(): int
    {
        $ioId = (int) $this->argument('ioId');
        $culture = (string) $this->option('culture');

        if (! Schema::hasTable('information_object')) {
            $this->error('information_object schema not present - cannot build finding aid.');
            return self::FAILURE;
        }

        $io = $this->fetchIo($ioId, $culture);
        if (! $io) {
            $this->error('Information object #'.$ioId.' not found (or no i18n row for culture '.$culture.').');
            return self::FAILURE;
        }

        $repository = $this->fetchRepository($io, $culture);
        $events = $this->fetchEvents($io);
        $creators = $this->fetchCreators($io, $culture);
        $descendants = $this->fetchDescendants($io, $culture);

        $html = $this->renderHtml($io, $repository, $events, $creators, $descendants);

        $out = (string) ($this->option('out') ?: storage_path('app/finding-aids/'.$ioId.'.pdf'));
        @mkdir(dirname($out), 0775, true);

        if (! class_exists(\Dompdf\Dompdf::class)) {
            // No dompdf - drop the styled HTML alongside so the operator
            // can still print or convert it manually. Useful for CI.
            $htmlPath = preg_replace('/\.pdf$/i', '.html', $out);
            file_put_contents($htmlPath, $html);
            $this->warn('dompdf/dompdf not installed - wrote HTML form to '.$htmlPath);
            return self::SUCCESS;
        }

        try {
            $dompdf = new \Dompdf\Dompdf([
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
            ]);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            file_put_contents($out, $dompdf->output());
            $this->info('Wrote finding aid: '.$out);
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('PDF render failed: '.$e->getMessage());
            return self::FAILURE;
        }
    }

    private function fetchIo(int $ioId, string $culture)
    {
        return DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', $culture);
            })
            ->leftJoin('object as o', 'o.id', '=', 'io.id')
            ->where('io.id', $ioId)
            ->select([
                'io.id', 'io.identifier', 'io.repository_id', 'io.lft', 'io.rgt',
                'i18n.title', 'i18n.scope_and_content', 'i18n.extent_and_medium',
                'i18n.archival_history', 'i18n.acquisition',
                'i18n.access_conditions', 'i18n.reproduction_conditions',
                'i18n.arrangement', 'i18n.related_units_of_description',
                'i18n.finding_aids', 'i18n.physical_characteristics',
                'i18n.location_of_originals', 'i18n.location_of_copies',
                'o.created_at', 'o.updated_at',
            ])
            ->first();
    }

    private function fetchRepository($io, string $culture)
    {
        if (empty($io->repository_id)) {
            return null;
        }
        return DB::table('repository')
            ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
            ->where('repository.id', $io->repository_id)
            ->where('actor_i18n.culture', $culture)
            ->select('repository.id', 'actor_i18n.authorized_form_of_name as name')
            ->first();
    }

    private function fetchEvents($io)
    {
        return DB::table('event')
            ->where('event.object_id', $io->id)
            ->select('event.start_date', 'event.end_date', 'event.type_id')
            ->get();
    }

    private function fetchCreators($io, string $culture)
    {
        return DB::table('event')
            ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
            ->where('event.object_id', $io->id)
            ->where('event.type_id', 111)
            ->where('actor_i18n.culture', $culture)
            ->whereNotNull('event.actor_id')
            ->select('actor_i18n.authorized_form_of_name as name')
            ->distinct()
            ->get();
    }

    private function fetchDescendants($io, string $culture)
    {
        return DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', $culture);
            })
            ->where('io.lft', '>', $io->lft)
            ->where('io.rgt', '<', $io->rgt)
            ->orderBy('io.lft')
            ->select(
                'io.id', 'io.identifier', 'io.lft', 'io.rgt', 'io.level_of_description_id',
                'i18n.title', 'i18n.scope_and_content', 'i18n.extent_and_medium'
            )
            ->get();
    }

    private function renderHtml($io, $repository, $events, $creators, $descendants): string
    {
        $title = $this->h($io->title);
        $repoName = $this->h($repository->name ?? '');
        $identifier = $this->h($io->identifier ?? ('IO #'.$io->id));
        $dates = $this->formatDates($events);
        $creatorsList = $creators->isNotEmpty()
            ? implode(', ', $creators->pluck('name')->map(fn ($n) => $this->h($n))->all())
            : '';
        $extent = $this->h($io->extent_and_medium ?? '');
        $abstract = $this->h($io->scope_and_content ?? '');

        $css = <<<'CSS'
            @page { margin: 1.6cm 1.8cm; }
            body { font-family: 'DejaVu Sans', sans-serif; font-size: 11pt; line-height: 1.5; color: #1a1a1a; }
            h1 { font-size: 22pt; margin: 0 0 0.3em 0; border-bottom: 2px solid #333; padding-bottom: 0.2em; }
            h2 { font-size: 14pt; margin: 1.4em 0 0.4em 0; color: #5b1a1a; border-bottom: 1px solid #cccccc; padding-bottom: 0.15em; }
            h3 { font-size: 11pt; margin: 0.8em 0 0.2em 0; color: #333; }
            .title-block { margin-bottom: 1.8em; }
            .meta { background: #f5f5f5; padding: 0.6em 0.9em; border-left: 4px solid #5b1a1a; margin: 1em 0; }
            .meta dt { font-weight: bold; float: left; clear: left; width: 9em; }
            .meta dd { margin-left: 9.5em; margin-bottom: 0.25em; }
            .container-list { margin-top: 0.6em; }
            .container-list .lvl { margin-left: 1.2em; }
            .container-list .lvl-1 { margin-left: 0; font-weight: bold; }
            .container-list .lvl-2 { margin-left: 1.2em; }
            .container-list .lvl-3 { margin-left: 2.4em; }
            .container-list .lvl-4 { margin-left: 3.6em; }
            .container-list .lvl-deep { margin-left: 4.8em; }
            .container-list .unitid { font-family: 'DejaVu Sans Mono', monospace; color: #555; font-size: 9pt; }
            footer { margin-top: 3em; padding-top: 0.8em; border-top: 1px solid #ccc; font-size: 9pt; color: #666; }
            CSS;

        $html = '<!doctype html><html><head><meta charset="UTF-8"><title>'.$title.' - Finding Aid</title><style>'.$css.'</style></head><body>';

        // Title block
        $html .= '<div class="title-block">';
        $html .= '<h1>'.$title.'</h1>';
        if ($repoName !== '') {
            $html .= '<div><em>A finding aid to the collection at</em><br><strong>'.$repoName.'</strong></div>';
        }
        $html .= '</div>';

        // Summary block (LoC style: Title, Creator, Dates, Extent, Identifier, Repository)
        $html .= '<div class="meta"><dl>';
        $html .= '<dt>Identifier</dt><dd>'.$identifier.'</dd>';
        if ($creatorsList !== '') {
            $html .= '<dt>Creator</dt><dd>'.$creatorsList.'</dd>';
        }
        if ($dates !== '') {
            $html .= '<dt>Dates</dt><dd>'.$this->h($dates).'</dd>';
        }
        if ($extent !== '') {
            $html .= '<dt>Extent</dt><dd>'.$extent.'</dd>';
        }
        if ($repoName !== '') {
            $html .= '<dt>Repository</dt><dd>'.$repoName.'</dd>';
        }
        $html .= '</dl></div>';

        // Notes in LoC order
        if ($abstract !== '') {
            $html .= '<h2>Scope and Content</h2><div>'.nl2br($abstract).'</div>';
        }
        if (! empty($io->archival_history)) {
            $html .= '<h2>Custodial History</h2><div>'.nl2br($this->h($io->archival_history)).'</div>';
        }
        if (! empty($io->acquisition)) {
            $html .= '<h2>Immediate Source of Acquisition</h2><div>'.nl2br($this->h($io->acquisition)).'</div>';
        }
        if (! empty($io->arrangement)) {
            $html .= '<h2>Arrangement</h2><div>'.nl2br($this->h($io->arrangement)).'</div>';
        }
        if (! empty($io->access_conditions)) {
            $html .= '<h2>Conditions Governing Access</h2><div>'.nl2br($this->h($io->access_conditions)).'</div>';
        }
        if (! empty($io->reproduction_conditions)) {
            $html .= '<h2>Conditions Governing Use</h2><div>'.nl2br($this->h($io->reproduction_conditions)).'</div>';
        }
        if (! empty($io->physical_characteristics)) {
            $html .= '<h2>Physical Characteristics and Technical Requirements</h2><div>'.nl2br($this->h($io->physical_characteristics)).'</div>';
        }
        if (! empty($io->related_units_of_description)) {
            $html .= '<h2>Related Materials</h2><div>'.nl2br($this->h($io->related_units_of_description)).'</div>';
        }
        if (! empty($io->finding_aids)) {
            $html .= '<h2>Other Finding Aids</h2><div>'.nl2br($this->h($io->finding_aids)).'</div>';
        }
        if (! empty($io->location_of_originals)) {
            $html .= '<h2>Location of Originals</h2><div>'.nl2br($this->h($io->location_of_originals)).'</div>';
        }
        if (! empty($io->location_of_copies)) {
            $html .= '<h2>Existence and Location of Copies</h2><div>'.nl2br($this->h($io->location_of_copies)).'</div>';
        }

        if ($descendants->isNotEmpty()) {
            $html .= '<h2>Container List</h2>';
            $html .= '<div class="container-list">';
            // Approximate depth by counting open lft/rgt parents already visited.
            $stack = [];
            foreach ($descendants as $d) {
                while (! empty($stack) && $d->rgt > $stack[count($stack) - 1]) {
                    array_pop($stack);
                }
                $depth = count($stack) + 1;
                $cls = $depth <= 4 ? 'lvl-'.$depth : 'lvl-deep';
                $unitid = $d->identifier ? '<span class="unitid">'.$this->h($d->identifier).'</span> ' : '';
                $extent = $d->extent_and_medium ? ' <em>('.$this->h($d->extent_and_medium).')</em>' : '';
                $html .= '<div class="'.$cls.'">'.$unitid.$this->h($d->title ?: '(untitled)').$extent.'</div>';
                if ($d->rgt > $d->lft + 1) {
                    $stack[] = $d->rgt;
                }
            }
            $html .= '</div>';
        }

        $html .= '<footer>Generated '.gmdate('Y-m-d H:i').' UTC by Heratio '.$this->h(config('app.name', 'Heratio')).' EAD finding-aid generator.</footer>';
        $html .= '</body></html>';
        return $html;
    }

    private function formatDates($events): string
    {
        $ranges = [];
        foreach ($events as $e) {
            if ($e->start_date && $e->end_date && $e->start_date !== $e->end_date) {
                $ranges[] = substr((string) $e->start_date, 0, 10).' / '.substr((string) $e->end_date, 0, 10);
            } elseif ($e->start_date) {
                $ranges[] = substr((string) $e->start_date, 0, 10);
            }
        }
        return implode('; ', array_unique($ranges));
    }

    private function h(?string $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
