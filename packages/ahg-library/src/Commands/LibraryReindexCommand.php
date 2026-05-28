<?php

/**
 * LibraryReindexCommand - Build the library_item Elasticsearch index
 *
 * Creates the index, maps it, and bulk-indexes every library_item row.
 * Run:
 *   php artisan ahg:library-reindex          (full reindex)
 *   php artisan ahg:library-reindex --drop   (drop + recreate first)
 *   php artisan ahg:library-reindex --id=5   (single item by library_item.id)
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 */

namespace AhgLibrary\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class LibraryReindexCommand extends Command
{
    protected $signature = 'ahg:library-reindex
        {--drop : Drop and recreate the index before indexing}
        {--id= : Index a single library_item by id}
        {--batch=500 : Number of rows per bulk request}';

    protected $description = 'Build the library_item Elasticsearch index from MySQL';

    protected string $host;

    protected string $prefix;

    protected string $index;

    protected int $batchSize;

    public function handle(): int
    {
        $this->host      = config('services.elasticsearch.host', 'http://localhost:9200');
        $this->prefix    = config('services.elasticsearch.prefix', 'heratio_');
        $this->index     = $this->prefix . 'library_item';
        $this->batchSize = (int) $this->option('batch');

        // Check ES is reachable
        try {
            $r = Http::timeout(5)->get($this->host);
            if (! $r->successful()) {
                $this->error("Cannot reach Elasticsearch at {$this->host}");

                return 1;
            }
        } catch (\Exception $e) {
            $this->error("Cannot reach Elasticsearch at {$this->host}: " . $e->getMessage());

            return 1;
        }

        $this->info("Elasticsearch: {$this->host}");
        $this->info("Index: {$this->index}");

        // Drop if requested
        if ($this->option('drop')) {
            $this->dropIndex();
        }

        // Ensure index exists
        if (! $this->indexExists()) {
            $this->createIndex();
        }

        // Run reindex
        $this->reindex();

        $this->newLine();
        $this->info('Done.  Verify with: curl -s "http://localhost:9200/' . $this->index . '/_count?pretty"');
        $this->info('Or check facets: curl -s "http://localhost:9200/' . $this->index . '/_search?pretty" -H "Content-Type: application/json" -d \'{"size":0,"aggs":{"material_types":{"terms":{"field":"material_type.keyword","size":10}}}}\'');

        return 0;
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Index management
    // ─────────────────────────────────────────────────────────────────────

    protected function indexExists(): bool
    {
        $r = Http::head("{$this->host}/{$this->index}");

        return $r->status() === 200;
    }

    protected function createIndex(): void
    {
        $this->info("Creating index {$this->index}…");

        $mapping = [
            'settings' => [
                'number_of_shards'   => 2,
                'number_of_replicas' => 1,
                'analysis' => [
                    'analyzer' => [
                        'title_analyzer' => [
                            'type'      => 'custom',
                            'tokenizer' => 'standard',
                            'filter'    => ['lowercase', 'asciifolding', 'porter_stem'],
                        ],
                    ],
                ],
            ],
            'mappings' => [
                'properties' => [
                    // Identifiers
                    'id'                   => ['type' => 'long'],
                    'slug'                 => ['type' => 'keyword'],
                    'isbn'                 => ['type' => 'keyword'],
                    'issn'                 => ['type' => 'keyword'],

                    // Title
                    'title'                => [
                        'type'     => 'text',
                        'analyzer'  => 'title_analyzer',
                        'fields'   => [
                            'keyword' => ['type' => 'keyword'],
                        ],
                    ],
                    'subtitle'             => ['type' => 'text'],
                    'responsibility_statement' => ['type' => 'text'],

                    // Creator / author
                    'creators'             => [
                        'type' => 'nested',
                        'properties' => [
                            'id'   => ['type' => 'long'],
                            'name' => [
                                'type'     => 'text',
                                'fields'   => ['keyword' => ['type' => 'keyword']],
                            ],
                            'role' => ['type' => 'keyword'],
                        ],
                    ],

                    // Publication
                    'publisher'            => [
                        'type'     => 'text',
                        'fields'   => ['keyword' => ['type' => 'keyword']],
                    ],
                    'publication_place'    => ['type' => 'text'],
                    'publication_date'     => ['type' => 'date', 'format' => 'yyyy-MM-dd||yyyy-MM||yyyy'],
                    'publication_year'     => ['type' => 'integer'],
                    'language'             => ['type' => 'keyword'],

                    // Physical
                    'pagination'           => ['type' => 'text'],
                    'physical_details'     => ['type' => 'text'],
                    'dimensions'           => ['type' => 'text'],
                    'accompanying_material' => ['type' => 'text'],

                    // Classification
                    'call_number'          => ['type' => 'keyword'],
                    'classification_scheme'=> ['type' => 'keyword'],

                    // Content
                    'material_type'        => ['type' => 'keyword'],
                    'summary'              => ['type' => 'text'],
                    'contents_note'        => ['type' => 'text'],
                    'series_title'         => ['type' => 'text'],
                    'series_issn'          => ['type' => 'keyword'],
                    'subjects'             => ['type' => 'text'],

                    // Digital
                    'cover_url'            => ['type' => 'keyword', 'index' => false],
                    'digital_url'          => ['type' => 'keyword', 'index' => false],
                    'digital_master_url'   => ['type' => 'keyword', 'index' => false],

                    // Availability (indexed from copy aggregation at reindex time)
                    'availability_status'  => ['type' => 'keyword'],
                    'total_copies'        => ['type' => 'integer'],
                    'available_copies'    => ['type' => 'integer'],
                    'checked_out_copies'   => ['type' => 'integer'],

                    // Popularity
                    'checkout_count'       => ['type' => 'integer'],

                    // Dates
                    'created_at'          => ['type' => 'date'],
                    'updated_at'          => ['type' => 'date'],
                    'acquisition_date'    => ['type' => 'date'],
                ],
            ],
        ];

        $r = Http::put("{$this->host}/{$this->index}", $mapping);

        if (! $r->successful()) {
            $this->error('Failed to create index: ' . $r->body());

            return;
        }

        $this->info('  Created index with mapping.');
    }

    protected function dropIndex(): void
    {
        $this->info("Dropping index {$this->index}…");
        Http::delete("{$this->host}/{$this->index}");
        $this->info('  Dropped.');
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Reindex
    // ─────────────────────────────────────────────────────────────────────

    protected function reindex(): void
    {
        $onlyId = $this->option('id') ? (int) $this->option('id') : null;

        $countQ = DB::table('library_item');
        if ($onlyId) { $countQ->where('id', $onlyId); }
        $total = $countQ->count();

        $this->info("Reindexing {$total} library items…");
        $bar = $this->output->createProgressBar($total);

        $q = DB::table('library_item')->orderBy('id');
        if ($onlyId) { $q->where('id', $onlyId); }

        $q->chunk($this->batchSize, function ($rows) use ($bar) {
            $ids = $rows->pluck('id')->toArray();

            // Batch-load IO titles (localised)
            $ioIds = $rows->pluck('information_object_id')->filter()->unique()->toArray();
            $i18nRows = [];
            if (! empty($ioIds)) {
                $rows2 = DB::table('information_object_i18n')
                    ->whereIn('id', $ioIds)
                    ->get()
                    ->groupBy('id');
                foreach ($rows2 as $ioId => $translations) {
                    $i18nRows[$ioId] = $translations;
                }
            }

            // Batch-load slugs
            $slugs = [];
            if (! empty($ioIds)) {
                $slugs = DB::table('slug')
                    ->whereIn('object_id', $ioIds)
                    ->pluck('slug', 'object_id')
                    ->toArray();
            }

            // Batch-load library_item_creator
            $libCreators = DB::table('library_item_creator')
                ->whereIn('library_item_id', $ids)
                ->orderBy('sort_order')
                ->get()
                ->groupBy('library_item_id');

            // Batch-load copy availability
            $copyAggs = DB::table('library_copy')
                ->whereIn('library_item_id', $ids)
                ->whereNull('withdrawal_date')
                ->select('library_item_id', 'status', DB::raw('COUNT(*) as cnt'))
                ->groupBy('library_item_id', 'status')
                ->get()
                ->groupBy('library_item_id');

            // Batch-load checkout counts (rolling 12 months)
            $cutoff = now()->subMonths(12)->toDateTimeString();
            $checkoutCounts = DB::table('library_checkout')
                ->whereIn('copy_id', fn ($q) => $q->from('library_copy')
                    ->whereIn('library_item_id', $ids))
                ->where('checkout_date', '>=', $cutoff)
                ->select('copy_id')
                ->get()
                ->map(fn ($row) => $row->copy_id);

            // Get copy → item map for checkout count
            $copyToItem = DB::table('library_copy')
                ->whereIn('library_item_id', $ids)
                ->pluck('id', 'id')
                ->map(fn ($id, $_) => DB::table('library_copy')->where('id', $id)->value('library_item_id'))
                ->toArray();

            $copyToItem = [];
            if (! empty($ids)) {
                $copyToItem = DB::table('library_copy')
                    ->whereIn('library_item_id', $ids)
                    ->pluck('id', 'id')
                    ->map(fn ($id) => $id)
                    ->toArray();
            }
            $copyItems = DB::table('library_copy')
                ->whereIn('library_item_id', $ids)
                ->select('id', 'library_item_id')
                ->get()
                ->groupBy('library_item_id');

            $checkoutCounts = [];
            if (! empty($ids)) {
                $checkouts = DB::table('library_checkout as c')
                    ->join('library_copy as cp', 'c.copy_id', '=', 'cp.id')
                    ->whereIn('cp.library_item_id', $ids)
                    ->where('c.checkout_date', '>=', $cutoff)
                    ->select('cp.library_item_id', DB::raw('COUNT(c.id) as cnt'))
                    ->groupBy('cp.library_item_id')
                    ->get()
                    ->pluck('cnt', 'library_item_id')
                    ->toArray();
                $checkoutCounts = $checkouts;
            }

            $bulk = '';
            foreach ($rows as $row) {
                $doc = $this->buildDoc($row, $i18nRows, $slugs, $libCreators, $copyAggs, $checkoutCounts);
                $bulk .= json_encode(['index' => ['_index' => $this->index, '_id' => $row->id]]) . "\n";
                $bulk .= json_encode($doc) . "\n";
                $bar->advance();
            }

            if ($bulk) {
                $this->bulkIndex($bulk);
            }
        });

        $bar->finish();
        $this->newLine();
    }

    protected function buildDoc($row, array $i18nRows, array $slugs, $libCreators, $copyAggs, array $checkoutCounts): array
    {
        $culture = app()->getLocale();
        $ioId    = $row->information_object_id;

        // Localised title from IO i18n
        $title = $row->title ?? null;
        if (! $title && $ioId && isset($i18nRows[$ioId])) {
            $t = collect($i18nRows[$ioId])->firstWhere('culture', $culture)
                  ?? collect($i18nRows[$ioId])->first();
            $title = $t->title ?? null;
        }

        $slug = $ioId ? ($slugs[$ioId] ?? null) : null;

        // Creators
        $creators = [];
        if (isset($libCreators[$row->id])) {
            foreach ($libCreators[$row->id] as $lc) {
                $creators[] = [
                    'id'   => $lc->actor_id,
                    'name' => $lc->name ?? ($lc->actor_id
                        ? DB::table('actor_i18n')->where('id', $lc->actor_id)->value('authorized_form_of_name')
                        : null),
                    'role' => $lc->role ?? 'author',
                ];
            }
        }

        // Availability aggregation from copies
        $total      = 0;
        $available  = 0;
        $checkedOut = 0;

        if (isset($copyAggs[$row->id])) {
            foreach ($copyAggs[$row->id] as $agg) {
                $total += (int) $agg->cnt;
                switch ($agg->status) {
                    case 'available':   $available  = (int) $agg->cnt; break;
                    case 'checked_out': $checkedOut = (int) $agg->cnt; break;
                }
            }
        }

        $availStatus = match (true) {
            $available > 0  => 'available',
            $checkedOut > 0 => 'checked_out',
            $total > 0      => 'on_hold',
            default         => 'unknown',
        };

        // Publication year
        $pubYear = null;
        if ($row->publication_date) {
            preg_match('/\d{4}/', $row->publication_date, $m);
            $pubYear = $m ? (int) $m[0] : null;
        }

        return [
            'id'                       => (int) $row->id,
            'slug'                     => $slug,
            'isbn'                     => $row->isbn ?: null,
            'issn'                     => $row->issn ?: null,
            'title'                    => $title ?? $row->title ?? 'Untitled',
            'subtitle'                 => $row->subtitle ?: null,
            'responsibility_statement' => $row->responsibility_statement ?: null,
            'creators'                => $creators,
            'publisher'                => $row->publisher ?: null,
            'publication_place'       => $row->publication_place ?: null,
            'publication_date'         => $row->publication_date ?: null,
            'publication_year'         => $pubYear,
            'language'                 => $row->language ?: null,
            'pagination'              => $row->pagination ?: null,
            'physical_details'         => $row->physical_details ?: null,
            'dimensions'               => $row->dimensions ?: null,
            'accompanying_material'   => $row->accompanying_material ?: null,
            'call_number'              => $row->call_number ?: null,
            'classification_scheme'    => $row->classification_scheme ?: null,
            'material_type'            => $row->material_type ?: 'monograph',
            'summary'                  => $row->summary ?: null,
            'contents_note'            => $row->contents_note ?: null,
            'series_title'            => $row->series_title ?: null,
            'series_issn'             => $row->series_issn ?: null,
            'subjects'                 => $row->subjects ?: null,
            'cover_url'               => $row->cover_url ?: null,
            'digital_url'             => $row->digital_url ?: null,
            'digital_master_url'      => $row->digital_master_url ?: null,
            'availability_status'      => $availStatus,
            'total_copies'            => $total,
            'available_copies'        => $available,
            'checked_out_copies'     => $checkedOut,
            'checkout_count'           => $checkoutCounts[$row->id] ?? 0,
            'created_at'              => $row->created_at ? date('Y-m-d\TH:i:s\Z', strtotime($row->created_at)) : null,
            'updated_at'              => $row->updated_at ? date('Y-m-d\TH:i:s\Z', strtotime($row->updated_at)) : null,
            'acquisition_date'       => $row->acquisition_date ? date('Y-m-d\TH:i:s\Z', strtotime($row->acquisition_date)) : null,
        ];
    }

    protected function bulkIndex(string $body): void
    {
        try {
            $response = Http::timeout(60)
                ->withBody($body, 'application/x-ndjson')
                ->post("{$this->host}/_bulk");

            if (! $response->successful()) {
                $this->warn('  Bulk warning: ' . substr($response->body(), 0, 200));
            } else {
                $result = $response->json();
                if ($result['errors'] ?? false) {
                    $errs = collect($result['items'] ?? [])
                        ->filter(fn ($item) => isset($item['index']['error']))
                        ->count();
                    if ($errs > 0) {
                        $this->warn("  {$errs} doc errors");
                    }
                }
            }
        } catch (\Exception $e) {
            $this->error('  Bulk failed: ' . $e->getMessage());
        }
    }
}
