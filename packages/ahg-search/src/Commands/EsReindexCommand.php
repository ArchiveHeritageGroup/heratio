<?php

/**
 * EsReindexCommand - Artisan command for Heratio
 *
 * Populates Heratio's own Elasticsearch indices from MySQL.
 *
 * Copyright (C) 2026 Johan Pieterse
 * The Archive and Heritage Group (Pty) Ltd
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace AhgSearch\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EsReindexCommand extends Command
{
    protected $signature = 'ahg:es-reindex
        {--index= : Reindex a specific index (informationobject, actor, term, repository). Omit for all.}
        {--clone-from= : Clone mapping and data from an existing prefix (e.g. archive_) instead of building from MySQL}
        {--drop : Drop and recreate the target indices before reindexing}
        {--batch=500 : Batch size for bulk indexing}';

    protected $description = 'Populate Heratio Elasticsearch indices from MySQL or clone from another prefix';

    protected string $host;
    protected string $prefix;
    protected int $batchSize;

    protected array $indexMap = [
        'informationobject' => 'qubitinformationobject',
        'actor'             => 'qubitactor',
        'term'              => 'qubitterm',
        'repository'        => 'qubitrepository',
    ];

    protected array $cultures = ['en', 'af', 'es', 'fr', 'pt', 'nl', 'zu'];

    public function handle(): int
    {
        $this->host = config('services.elasticsearch.host', 'http://localhost:9200');
        $this->prefix = config('services.elasticsearch.prefix', 'heratio_');
        $this->batchSize = (int) $this->option('batch');

        // Check ES is available
        try {
            $r = Http::timeout(5)->get($this->host);
            if (!$r->successful()) {
                $this->error("Cannot connect to Elasticsearch at {$this->host}");
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("Cannot connect to Elasticsearch at {$this->host}: " . $e->getMessage());
            return 1;
        }

        $this->info("Elasticsearch host: {$this->host}");
        $this->info("Target prefix: {$this->prefix}");

        // Determine which indices to process
        $only = $this->option('index');
        $indices = $only ? [$only => $this->indexMap[$only] ?? null] : $this->indexMap;

        if ($only && !isset($this->indexMap[$only])) {
            $this->error("Unknown index: {$only}. Valid: " . implode(', ', array_keys($this->indexMap)));
            return 1;
        }

        $cloneFrom = $this->option('clone-from');

        foreach ($indices as $key => $esName) {
            $targetIndex = $this->prefix . $esName;

            if ($cloneFrom) {
                $this->cloneIndex($cloneFrom . $esName, $targetIndex);
            } else {
                $this->reindexFromMysql($key, $targetIndex);
            }
        }

        $this->newLine();
        $this->info('Done. Run `curl -s http://localhost:9200/_cat/indices?v | grep heratio_` to verify.');

        return 0;
    }

    /**
     * Clone an index from another prefix (mapping + data) using ES _reindex API.
     */
    protected function cloneIndex(string $source, string $target): void
    {
        $this->info("Cloning {$source} → {$target}");

        // Check source exists
        $exists = Http::head("{$this->host}/{$source}");
        if ($exists->status() === 404) {
            $this->warn("  Source index {$source} does not exist, skipping.");
            return;
        }

        // Drop target if --drop
        if ($this->option('drop')) {
            $this->dropIndex($target);
        }

        // Get source mapping + settings
        $mappingResp = Http::get("{$this->host}/{$source}/_mapping");
        $settingsResp = Http::get("{$this->host}/{$source}/_settings");

        if (!$mappingResp->successful() || !$settingsResp->successful()) {
            $this->error("  Failed to get mapping/settings from {$source}");
            return;
        }

        $mapping = $mappingResp->json()[$source]['mappings'] ?? [];
        $rawSettings = $settingsResp->json()[$source]['settings']['index'] ?? [];

        // Build clean settings (strip read-only/internal fields)
        $settings = [
            'number_of_shards' => $rawSettings['number_of_shards'] ?? 4,
            'number_of_replicas' => $rawSettings['number_of_replicas'] ?? 1,
        ];
        if (!empty($rawSettings['mapping'])) {
            $settings['mapping'] = $rawSettings['mapping'];
        } else {
            $settings['mapping'] = ['total_fields' => ['limit' => 3000]];
        }
        if (!empty($rawSettings['analysis'])) {
            $settings['analysis'] = $rawSettings['analysis'];
        }

        // Create target index
        $createResp = Http::put("{$this->host}/{$target}", [
            'settings' => $settings,
            'mappings' => $mapping,
        ]);

        if (!$createResp->successful()) {
            $err = $createResp->json()['error']['reason'] ?? $createResp->body();
            if (str_contains($err, 'already exists')) {
                $this->warn("  Target {$target} already exists, reindexing into it.");
            } else {
                $this->error("  Failed to create {$target}: {$err}");
                return;
            }
        } else {
            $this->info("  Created index {$target}");
        }

        // Reindex data
        $reindexResp = Http::timeout(300)->post("{$this->host}/_reindex", [
            'source' => ['index' => $source],
            'dest' => ['index' => $target],
        ]);

        if ($reindexResp->successful()) {
            $total = $reindexResp->json()['total'] ?? 0;
            $this->info("  Reindexed {$total} documents from {$source} → {$target}");
        } else {
            $this->error("  Reindex failed: " . $reindexResp->body());
        }
    }

    /**
     * Reindex from MySQL into the target ES index.
     */
    protected function reindexFromMysql(string $type, string $targetIndex): void
    {
        $this->info("Reindexing {$type} from MySQL → {$targetIndex}");

        if ($this->option('drop')) {
            $this->dropIndex($targetIndex);
        }

        // Ensure index exists (clone mapping from archive_ if needed)
        $exists = Http::head("{$this->host}/{$targetIndex}");
        if ($exists->status() === 404) {
            $this->info("  Index {$targetIndex} doesn't exist, cloning mapping from archive_...");
            $sourceIndex = 'archive_' . $this->indexMap[$type];
            $srcExists = Http::head("{$this->host}/{$sourceIndex}");
            if ($srcExists->status() === 200) {
                $this->createIndexFromMapping($sourceIndex, $targetIndex);
            } else {
                $this->warn("  No source mapping found at {$sourceIndex}, creating with dynamic mapping.");
                Http::put("{$this->host}/{$targetIndex}");
            }
        }

        $method = 'reindex' . ucfirst($type);
        if (method_exists($this, $method)) {
            $this->$method($targetIndex);
        } else {
            $this->warn("  No MySQL reindex method for {$type}, skipping.");
        }
    }

    /**
     * Create an index by copying mapping from a source index.
     */
    protected function createIndexFromMapping(string $source, string $target): void
    {
        $mappingResp = Http::get("{$this->host}/{$source}/_mapping");
        $settingsResp = Http::get("{$this->host}/{$source}/_settings");

        $mapping = $mappingResp->json()[$source]['mappings'] ?? [];
        $rawSettings = $settingsResp->json()[$source]['settings']['index'] ?? [];

        $settings = [
            'number_of_shards' => $rawSettings['number_of_shards'] ?? 4,
            'number_of_replicas' => $rawSettings['number_of_replicas'] ?? 1,
        ];
        if (!empty($rawSettings['analysis'])) {
            $settings['analysis'] = $rawSettings['analysis'];
        }

        Http::put("{$this->host}/{$target}", [
            'settings' => $settings,
            'mappings' => $mapping,
        ]);

        $this->info("  Created {$target} with mapping from {$source}");
    }

    /**
     * Reindex information objects from MySQL.
     */
    protected function reindexInformationobject(string $index): void
    {
        $total = DB::table('information_object')->where('id', '!=', 1)->count();
        $this->info("  Found {$total} information objects");
        $bar = $this->output->createProgressBar($total);

        DB::table('information_object')
            ->where('id', '!=', 1)
            ->orderBy('id')
            ->chunk($this->batchSize, function ($rows) use ($index, $bar) {
                $ids = $rows->pluck('id')->toArray();

                // Batch-load i18n
                $i18nRows = DB::table('information_object_i18n')
                    ->whereIn('id', $ids)
                    ->get()
                    ->groupBy('id');

                // Batch-load slugs
                $slugs = DB::table('slug')
                    ->whereIn('object_id', $ids)
                    ->pluck('slug', 'object_id');

                // Batch-load status (publication)
                $statuses = DB::table('status')
                    ->whereIn('object_id', $ids)
                    ->where('type_id', 158)
                    ->pluck('status_id', 'object_id');

                // Batch-load digital objects
                $digitalObjects = DB::table('digital_object')
                    ->whereIn('information_object_id', $ids)
                    ->get()
                    ->keyBy('information_object_id');

                // Batch-load repository info
                $repoIds = $rows->pluck('repository_id')->filter()->unique()->toArray();
                $repos = [];
                if (!empty($repoIds)) {
                    $repoSlugs = DB::table('slug')->whereIn('object_id', $repoIds)->pluck('slug', 'object_id');
                    $repoI18n = DB::table('actor_i18n')->whereIn('id', $repoIds)->get()->groupBy('id');
                    foreach ($repoIds as $rid) {
                        $repos[$rid] = [
                            'id' => $rid,
                            'slug' => $repoSlugs[$rid] ?? null,
                            'identifier' => null,
                            'i18n' => $this->buildI18n($repoI18n[$rid] ?? collect(), ['authorizedFormOfName' => 'authorized_form_of_name']),
                        ];
                    }
                }

                // Batch-load creators via event table
                $creators = DB::table('event')
                    ->whereIn('information_object_id', $ids)
                    ->where('type_id', 111) // creation event
                    ->whereNotNull('actor_id')
                    ->get()
                    ->groupBy('information_object_id');

                $creatorActorIds = $creators->flatten()->pluck('actor_id')->unique()->toArray();
                $creatorI18n = [];
                if (!empty($creatorActorIds)) {
                    $creatorI18n = DB::table('actor_i18n')
                        ->whereIn('id', $creatorActorIds)
                        ->get()
                        ->groupBy('id');
                }

                // Build bulk body
                $bulk = '';
                foreach ($rows as $row) {
                    $i18nGroup = $i18nRows[$row->id] ?? collect();
                    $slug = $slugs[$row->id] ?? null;
                    $pubStatus = $statuses[$row->id] ?? null;
                    $do = $digitalObjects[$row->id] ?? null;
                    $repo = $repos[$row->repository_id] ?? null;

                    // Build creators array
                    $ioCreators = [];
                    if (isset($creators[$row->id])) {
                        foreach ($creators[$row->id] as $evt) {
                            $ci = $creatorI18n[$evt->actor_id] ?? collect();
                            $ioCreators[] = [
                                'id' => $evt->actor_id,
                                'i18n' => $this->buildI18n($ci, ['authorizedFormOfName' => 'authorized_form_of_name']),
                            ];
                        }
                    }

                    $doc = [
                        'slug' => $slug,
                        'parentId' => $row->parent_id,
                        'identifier' => $row->identifier,
                        'referenceCode' => $this->buildReferenceCode($row),
                        'levelOfDescriptionId' => $row->level_of_description_id,
                        'publicationStatusId' => $pubStatus ?? 159,
                        'hasDigitalObject' => $do !== null,
                        'createdAt' => $row->created_at ? date('Y-m-d\TH:i:s\Z', strtotime($row->created_at)) : null,
                        'updatedAt' => $row->updated_at ? date('Y-m-d\TH:i:s\Z', strtotime($row->updated_at)) : null,
                        'sourceCulture' => $row->source_culture ?? 'en',
                        'lft' => $row->lft,
                        'i18n' => $this->buildI18n($i18nGroup, [
                            'title' => 'title',
                            'scopeAndContent' => 'scope_and_content',
                            'archivalHistory' => 'archival_history',
                            'extentAndMedium' => 'extent_and_medium',
                            'accessConditions' => 'access_conditions',
                            'locationOfOriginals' => 'location_of_originals',
                            'reproductionConditions' => 'reproduction_conditions',
                            'institutionResponsibleIdentifier' => 'institution_responsible_identifier',
                        ]),
                        'repository' => $repo,
                        'creators' => $ioCreators,
                        'inheritedCreators' => [],
                    ];

                    if ($do) {
                        $doc['digitalObject'] = [
                            'mediaTypeId' => $do->media_type_id,
                            'usageId' => $do->usage_id,
                            'filename' => $do->name,
                            'thumbnailPath' => $do->path ?? null,
                            'digitalObjectAltText' => null,
                        ];
                    }

                    $bulk .= json_encode(['index' => ['_index' => $index, '_id' => $row->id]]) . "\n";
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

    /**
     * Reindex actors from MySQL.
     */
    protected function reindexActor(string $index): void
    {
        $total = DB::table('actor')->where('id', '!=', 3)->count(); // exclude ROOT
        $this->info("  Found {$total} actors");
        $bar = $this->output->createProgressBar($total);

        DB::table('actor')
            ->where('id', '!=', 3)
            ->orderBy('id')
            ->chunk($this->batchSize, function ($rows) use ($index, $bar) {
                $ids = $rows->pluck('id')->toArray();

                $i18nRows = DB::table('actor_i18n')
                    ->whereIn('id', $ids)
                    ->get()
                    ->groupBy('id');

                $slugs = DB::table('slug')
                    ->whereIn('object_id', $ids)
                    ->pluck('slug', 'object_id');

                $digitalObjects = DB::table('digital_object')
                    ->whereIn('object_id', $ids)
                    ->get()
                    ->keyBy('object_id');

                $bulk = '';
                foreach ($rows as $row) {
                    $i18nGroup = $i18nRows[$row->id] ?? collect();
                    $slug = $slugs[$row->id] ?? null;
                    $do = $digitalObjects[$row->id] ?? null;

                    $doc = [
                        'slug' => $slug,
                        'entityTypeId' => $row->entity_type_id,
                        'corporateBodyIdentifiers' => $row->corporate_body_identifiers,
                        'hasDigitalObject' => $do !== null,
                        'createdAt' => $row->created_at ? date('Y-m-d\TH:i:s\Z', strtotime($row->created_at)) : null,
                        'updatedAt' => $row->updated_at ? date('Y-m-d\TH:i:s\Z', strtotime($row->updated_at)) : null,
                        'sourceCulture' => $row->source_culture ?? 'en',
                        'i18n' => $this->buildI18n($i18nGroup, [
                            'authorizedFormOfName' => 'authorized_form_of_name',
                            'history' => 'history',
                            'places' => 'places',
                            'functions' => 'functions',
                            'mandates' => 'mandates',
                            'internalStructures' => 'internal_structures',
                            'generalContext' => 'general_context',
                        ]),
                    ];

                    if ($do) {
                        $doc['digitalObject'] = [
                            'mediaTypeId' => $do->media_type_id,
                            'usageId' => $do->usage_id,
                            'filename' => $do->name,
                            'thumbnailPath' => $do->path ?? null,
                        ];
                    }

                    $bulk .= json_encode(['index' => ['_index' => $index, '_id' => $row->id]]) . "\n";
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

    /**
     * Reindex terms from MySQL.
     */
    protected function reindexTerm(string $index): void
    {
        $total = DB::table('term')->count();
        $this->info("  Found {$total} terms");
        $bar = $this->output->createProgressBar($total);

        DB::table('term')
            ->orderBy('id')
            ->chunk($this->batchSize, function ($rows) use ($index, $bar) {
                $ids = $rows->pluck('id')->toArray();

                $i18nRows = DB::table('term_i18n')
                    ->whereIn('id', $ids)
                    ->get()
                    ->groupBy('id');

                $slugs = DB::table('slug')
                    ->whereIn('object_id', $ids)
                    ->pluck('slug', 'object_id');

                $bulk = '';
                foreach ($rows as $row) {
                    $i18nGroup = $i18nRows[$row->id] ?? collect();
                    $slug = $slugs[$row->id] ?? null;

                    $doc = [
                        'slug' => $slug,
                        'taxonomyId' => $row->taxonomy_id,
                        'code' => $row->code ?? null,
                        'isProtected' => (bool) ($row->source_culture === 'en'),
                        'createdAt' => $row->created_at ? date('Y-m-d\TH:i:s\Z', strtotime($row->created_at)) : null,
                        'updatedAt' => $row->updated_at ? date('Y-m-d\TH:i:s\Z', strtotime($row->updated_at)) : null,
                        'sourceCulture' => $row->source_culture ?? 'en',
                        'i18n' => $this->buildI18n($i18nGroup, ['name' => 'name']),
                    ];

                    $bulk .= json_encode(['index' => ['_index' => $index, '_id' => $row->id]]) . "\n";
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

    /**
     * Reindex repositories from MySQL.
     */
    protected function reindexRepository(string $index): void
    {
        $total = DB::table('repository')->where('id', '!=', 6)->count(); // exclude ROOT
        $this->info("  Found {$total} repositories");
        $bar = $this->output->createProgressBar($total);

        DB::table('repository')
            ->where('id', '!=', 6)
            ->orderBy('id')
            ->chunk($this->batchSize, function ($rows) use ($index, $bar) {
                $ids = $rows->pluck('id')->toArray();

                $actorI18n = DB::table('actor_i18n')
                    ->whereIn('id', $ids)
                    ->get()
                    ->groupBy('id');

                $repoI18n = DB::table('repository_i18n')
                    ->whereIn('id', $ids)
                    ->get()
                    ->groupBy('id');

                $slugs = DB::table('slug')
                    ->whereIn('object_id', $ids)
                    ->pluck('slug', 'object_id');

                $actors = DB::table('actor')
                    ->whereIn('id', $ids)
                    ->get()
                    ->keyBy('id');

                $contacts = DB::table('contact_information')
                    ->whereIn('actor_id', $ids)
                    ->get()
                    ->groupBy('actor_id');

                $bulk = '';
                foreach ($rows as $row) {
                    $actor = $actors[$row->id] ?? null;
                    $ai = $actorI18n[$row->id] ?? collect();
                    $slug = $slugs[$row->id] ?? null;

                    $doc = [
                        'slug' => $slug,
                        'identifier' => $row->identifier ?? ($actor->description_identifier ?? null),
                        'createdAt' => $actor && $actor->created_at ? date('Y-m-d\TH:i:s\Z', strtotime($actor->created_at)) : null,
                        'updatedAt' => $actor && $actor->updated_at ? date('Y-m-d\TH:i:s\Z', strtotime($actor->updated_at)) : null,
                        'sourceCulture' => $actor->source_culture ?? 'en',
                        'i18n' => $this->buildI18n($ai, [
                            'authorizedFormOfName' => 'authorized_form_of_name',
                            'history' => 'history',
                            'places' => 'places',
                            'mandates' => 'mandates',
                            'functions' => 'functions',
                            'generalContext' => 'general_context',
                        ]),
                    ];

                    // Contact information
                    $contactList = $contacts[$row->id] ?? collect();
                    if ($contactList->isNotEmpty()) {
                        $doc['contact_informations'] = $contactList->map(fn ($c) => [
                            'street_address' => $c->street_address ?? null,
                            'postal_code' => $c->postal_code ?? null,
                            'region' => $c->region ?? null,
                            'city' => $c->city ?? null,
                            'country_code' => $c->country_code ?? null,
                            'telephone' => $c->telephone ?? null,
                            'email' => $c->email ?? null,
                            'website' => $c->website ?? null,
                        ])->toArray();
                    }

                    $bulk .= json_encode(['index' => ['_index' => $index, '_id' => $row->id]]) . "\n";
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

    // ─────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Build i18n structure from a collection of i18n rows.
     */
    protected function buildI18n($rows, array $fieldMap): array
    {
        $i18n = ['languages' => []];

        foreach ($rows as $row) {
            $culture = $row->culture ?? 'en';
            if (!in_array($culture, $this->cultures)) {
                continue;
            }

            $fields = [];
            foreach ($fieldMap as $esField => $dbField) {
                $fields[$esField] = $row->$dbField ?? null;
            }

            // Only include culture if at least one field has a value
            if (array_filter($fields, fn ($v) => $v !== null && $v !== '')) {
                $i18n['languages'][] = $culture;
                $i18n[$culture] = $fields;
            }
        }

        $i18n['languages'] = array_unique($i18n['languages']);

        return $i18n;
    }

    /**
     * Build a reference code for an IO.
     */
    protected function buildReferenceCode($row): ?string
    {
        if ($row->identifier) {
            return $row->identifier;
        }
        return null;
    }

    /**
     * Send a bulk index request to ES.
     */
    protected function bulkIndex(string $body): void
    {
        try {
            $response = Http::timeout(60)
                ->withBody($body, 'application/x-ndjson')
                ->post("{$this->host}/_bulk");

            if (!$response->successful()) {
                $this->warn("  Bulk index warning: " . substr($response->body(), 0, 200));
            } else {
                $result = $response->json();
                if ($result['errors'] ?? false) {
                    $errCount = collect($result['items'] ?? [])
                        ->filter(fn ($item) => isset($item['index']['error']))
                        ->count();
                    if ($errCount > 0) {
                        $this->warn("  {$errCount} documents had indexing errors");
                    }
                }
            }
        } catch (\Exception $e) {
            $this->error("  Bulk index failed: " . $e->getMessage());
        }
    }

    /**
     * Drop an ES index.
     */
    protected function dropIndex(string $index): void
    {
        $resp = Http::delete("{$this->host}/{$index}");
        if ($resp->successful()) {
            $this->info("  Dropped index {$index}");
        }
    }
}
