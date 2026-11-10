<?php

/**
 * ahg:ai-classify-pages - per-page document type and privacy screening for
 * records, or for every record with a PDF under a collection (heratio#1584).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgAiServices\Commands;

use AhgAiServices\Services\PageClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AiClassifyPagesCommand extends Command
{
    protected $signature = 'ahg:ai-classify-pages {slug?* : Record slugs} {--collection= : Every record with a PDF under this record id} {--privacy-only : Skip the document type (no GPU)} {--limit=0}';

    protected $description = 'Per-page document type and privacy screening, rolled up to the record (heratio#1584)';

    public function handle(PageClassifier $pc): int
    {
        $ids = DB::table('slug')->whereIn('slug', (array) $this->argument('slug'))->pluck('object_id')->map(fn ($i) => (int) $i)->all();
        if ($root = (int) $this->option('collection')) {
            if (! DB::table('information_object')->where('id', $root)->exists()) {
                $this->error("No record {$root}.");

                return self::FAILURE;
            }
            // Closure table first: records created since have no lft/rgt bounds.
            $under = app(\AhgCore\Services\HierarchyQueryService::class)->descendantIds('information_object', $root, true);
            $q = DB::table('digital_object as d')->whereIn('d.object_id', $under)->whereNull('d.parent_id')
                ->where('d.mime_type', 'application/pdf')->distinct()->orderBy('d.object_id');
            if ($limit = (int) $this->option('limit')) {
                $q->limit($limit);
            }
            $ids = array_merge($ids, $q->pluck('d.object_id')->map(fn ($i) => (int) $i)->all());
        }
        if (! $ids) {
            $this->error('Name record slugs or --collection=<id>.');

            return self::FAILURE;
        }
        $withDoctype = $this->option('privacy-only') ? false : PageClassifier::gpuHours();
        if (! $withDoctype && ! $this->option('privacy-only')) {
            $this->warn('Outside 06:00-18:00 SAST: privacy screen only, no document types (GPUs are off).');
        }
        foreach (array_unique($ids) as $id) {
            try {
                $r = $pc->run($id, null, $withDoctype);
                $this->line(sprintf('%d: %s', $id, $r['error'] ?? sprintf('%d unit(s), flagged: %s', $r['units'], $r['flagged'] ? implode(', ', $r['flagged']) : 'none')));
            } catch (\Throwable $e) {
                $this->error("{$id}: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
