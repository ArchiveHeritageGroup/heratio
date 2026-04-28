<?php

/**
 * MetadataExportCommand — export descriptions in GLAM standards.
 *
 * Renders one file per IO per format, using lightweight XML/JSON
 * serialisers that mirror the structure of AtoM's standards-templates.
 * The full external-namespace XSDs are deliberately not validated here —
 * downstream consumers (research portal, OAI, RIC) re-validate.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MetadataExportCommand extends Command
{
    protected $signature = 'ahg:metadata-export
        {--format=all : Export format (ead3, lido, marc21, rico, premis, bibframe, all)}
        {--slug= : Information object slug}
        {--repository= : Repository slug}
        {--output=/tmp : Output directory}
        {--include-children : Include child descriptions}
        {--include-digital-objects : Include digital object metadata}
        {--include-drafts : Include draft records}
        {--list : List available export formats}';

    protected $description = 'Export metadata in GLAM standards (EAD3, LIDO, MARC21, RIC-O, PREMIS, BIBFRAME)';

    protected array $formats = ['ead3', 'lido', 'marc21', 'rico', 'premis', 'bibframe'];

    public function handle(): int
    {
        if ($this->option('list')) {
            foreach ($this->formats as $f) $this->line("  - {$f}");
            return self::SUCCESS;
        }

        $format = (string) $this->option('format');
        $formats = $format === 'all' ? $this->formats : [$format];
        foreach ($formats as $f) {
            if (! in_array($f, $this->formats, true)) {
                $this->error("unknown format: {$f}");
                return self::FAILURE;
            }
        }

        $out = rtrim((string) $this->option('output'), '/');
        if (! is_dir($out)) @mkdir($out, 0775, true);

        $rows = $this->collectIos();
        $this->info("exporting {$rows->count()} IOs in [" . implode(',', $formats) . "]");

        $written = 0;
        foreach ($rows as $r) {
            foreach ($formats as $f) {
                $body = match ($f) {
                    'ead3'     => $this->renderEad3($r),
                    'lido'     => $this->renderLido($r),
                    'marc21'   => $this->renderMarc21($r),
                    'rico'     => $this->renderRiCo($r),
                    'premis'   => $this->renderPremis($r),
                    'bibframe' => $this->renderBibframe($r),
                };
                $ext = $f === 'rico' ? 'jsonld' : 'xml';
                file_put_contents("{$out}/{$r->id}.{$f}.{$ext}", $body);
                $written++;
            }
        }
        if (Schema::hasTable('metadata_export_log')) {
            DB::table('metadata_export_log')->insert([
                'format' => implode(',', $formats),
                'count' => $written,
                'output_path' => $out,
                'created_at' => now(),
            ]);
        }
        $this->info("written={$written} -> {$out}");
        return self::SUCCESS;
    }

    protected function collectIos(): \Illuminate\Support\Collection
    {
        $q = DB::table('information_object as i')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('i18n.id', '=', 'i.id')->where('i18n.culture', '=', 'en');
            })
            ->select('i.id', 'i.identifier', 'i.repository_id', 'i.lft', 'i.rgt',
                'i18n.title', 'i18n.scope_and_content', 'i18n.extent_and_medium');

        if ($slug = $this->option('slug')) {
            $q->join('slug as s', 's.object_id', '=', 'i.id')->where('s.slug', $slug);
            $base = $q->first();
            if (! $base) return collect();
            if ($this->option('include-children')) {
                return DB::table('information_object as i')
                    ->leftJoin('information_object_i18n as i18n', function ($j) {
                        $j->on('i18n.id', '=', 'i.id')->where('i18n.culture', '=', 'en');
                    })
                    ->whereBetween('i.lft', [$base->lft, $base->rgt])
                    ->select('i.id', 'i.identifier', 'i.repository_id', 'i.lft', 'i.rgt',
                        'i18n.title', 'i18n.scope_and_content', 'i18n.extent_and_medium')
                    ->get();
            }
            return collect([$base]);
        }
        if ($repoSlug = $this->option('repository')) {
            $repoId = DB::table('repository as r')->join('slug as s', 's.object_id', '=', 'r.id')
                ->where('s.slug', $repoSlug)->value('r.id');
            $q->where('i.repository_id', $repoId);
        }
        return $q->limit(2000)->get();
    }

    protected function renderEad3(object $r): string
    {
        $t = e($r->title ?: '(untitled)');
        $sc = e($r->scope_and_content ?? '');
        return "<?xml version='1.0' encoding='UTF-8'?>\n<ead xmlns='http://ead3.archivists.org/schema/'><archdesc level='collection'><did><unitid>" . e($r->identifier ?? '') . "</unitid><unittitle>{$t}</unittitle></did><scopecontent><p>{$sc}</p></scopecontent></archdesc></ead>";
    }
    protected function renderLido(object $r): string
    {
        $t = e($r->title ?: '(untitled)');
        return "<?xml version='1.0' encoding='UTF-8'?>\n<lido xmlns='http://www.lido-schema.org'><lidoRecID>{$r->id}</lidoRecID><descriptiveMetadata><objectIdentificationWrap><titleWrap><titleSet><appellationValue>{$t}</appellationValue></titleSet></titleWrap></objectIdentificationWrap></descriptiveMetadata></lido>";
    }
    protected function renderMarc21(object $r): string
    {
        $t = e($r->title ?: '(untitled)');
        return "<?xml version='1.0' encoding='UTF-8'?>\n<record xmlns='http://www.loc.gov/MARC21/slim'><leader>     ngm a22     uu 4500</leader><controlfield tag='001'>{$r->id}</controlfield><datafield tag='245' ind1='1' ind2='0'><subfield code='a'>{$t}</subfield></datafield></record>";
    }
    protected function renderRiCo(object $r): string
    {
        return json_encode([
            '@context' => 'https://www.ica.org/standards/RiC/ontology',
            '@id' => 'urn:ric:io:' . $r->id,
            '@type' => 'rico:Record',
            'rico:hasIdentifier' => $r->identifier,
            'rico:title' => $r->title,
            'rico:scopeAndContent' => $r->scope_and_content,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    protected function renderPremis(object $r): string
    {
        return "<?xml version='1.0' encoding='UTF-8'?>\n<premis xmlns='http://www.loc.gov/premis/v3' version='3.0'><object><objectIdentifier><objectIdentifierType>local</objectIdentifierType><objectIdentifierValue>{$r->id}</objectIdentifierValue></objectIdentifier></object></premis>";
    }
    protected function renderBibframe(object $r): string
    {
        $t = e($r->title ?: '(untitled)');
        return "<?xml version='1.0' encoding='UTF-8'?>\n<rdf:RDF xmlns:rdf='http://www.w3.org/1999/02/22-rdf-syntax-ns#' xmlns:bf='http://id.loc.gov/ontologies/bibframe/'><bf:Work rdf:about='urn:heratio:io:{$r->id}'><bf:title><bf:Title><bf:mainTitle>{$t}</bf:mainTitle></bf:Title></bf:title></bf:Work></rdf:RDF>";
    }
}
