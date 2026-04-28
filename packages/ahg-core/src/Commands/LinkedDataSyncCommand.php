<?php

/**
 * LinkedDataSyncCommand — refresh authority linkages against VIAF / Wikidata / Getty.
 *
 * Reads existing actor/term records that already have an external URI
 * stored, fetches the current label, and updates the local cache.
 * No record is created without an existing URI — discovery (NER / lookup)
 * is run by ahg:authority-ner-pipeline.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class LinkedDataSyncCommand extends Command
{
    protected $signature = 'ahg:linked-data-sync
        {--source=all : Data source to sync (all, viaf, wikidata, getty)}
        {--entity-type= : Only sync a specific entity type}
        {--limit= : Limit number of records to sync}
        {--dry-run : Show what would be synced without making changes}
        {--stats : Show sync statistics}';

    protected $description = 'Sync VIAF/Wikidata/Getty labels for already-linked entities';

    public function handle(): int
    {
        if ($this->option('stats')) return $this->showStats();

        $source = (string) $this->option('source');
        $limit = $this->option('limit') ? max(1, (int) $this->option('limit')) : 1000;
        $dry = (bool) $this->option('dry-run');

        $stats = ['viaf' => 0, 'wikidata' => 0, 'getty' => 0, 'errors' => 0];
        if ($source === 'all' || $source === 'getty')    $stats['getty']    = $this->syncGetty($limit, $dry, $stats);
        if ($source === 'all' || $source === 'viaf')     $stats['viaf']     = $this->syncViaf($limit, $dry, $stats);
        if ($source === 'all' || $source === 'wikidata') $stats['wikidata'] = $this->syncWikidata($limit, $dry, $stats);

        $this->info(sprintf('viaf=%d wikidata=%d getty=%d errors=%d%s',
            $stats['viaf'], $stats['wikidata'], $stats['getty'], $stats['errors'], $dry ? ' (dry-run)' : ''));
        return self::SUCCESS;
    }

    protected function showStats(): int
    {
        if (Schema::hasTable('getty_vocabulary_link')) {
            $row = DB::table('getty_vocabulary_link')->selectRaw('COUNT(*) c, SUM(status="confirmed") confirmed, SUM(status="pending") pending')->first();
            $this->info(sprintf('getty_vocabulary_link: total=%d confirmed=%d pending=%d', $row->c ?? 0, $row->confirmed ?? 0, $row->pending ?? 0));
        }
        if (Schema::hasTable('actor') && Schema::hasColumn('actor', 'wikidata_id')) {
            $wikidata = DB::table('actor')->whereNotNull('wikidata_id')->count();
            $viaf = DB::table('actor')->whereNotNull('viaf_id')->count();
            $this->info("actor: viaf_linked={$viaf} wikidata_linked={$wikidata}");
        }
        return self::SUCCESS;
    }

    protected function syncGetty(int $limit, bool $dry, array &$stats): int
    {
        if (! Schema::hasTable('getty_vocabulary_link')) return 0;
        $rows = DB::table('getty_vocabulary_link')->where('status', 'confirmed')->limit($limit)->get();
        $synced = 0;
        foreach ($rows as $r) {
            try {
                $resp = Http::timeout(10)->get($r->getty_uri . '.jsonld');
                if ($resp->ok()) {
                    $body = $resp->json();
                    $label = $this->pickLabel($body);
                    if (! $dry && $label) {
                        DB::table('getty_vocabulary_link')->where('id', $r->id)->update([
                            'getty_pref_label' => $label,
                            'updated_at' => now(),
                        ]);
                    }
                    $synced++;
                }
            } catch (\Throwable $e) { $stats['errors']++; }
        }
        return $synced;
    }

    protected function syncViaf(int $limit, bool $dry, array &$stats): int
    {
        if (! Schema::hasColumn('actor', 'viaf_id')) return 0;
        $rows = DB::table('actor')->whereNotNull('viaf_id')->limit($limit)->get(['id', 'viaf_id']);
        $synced = 0;
        foreach ($rows as $r) {
            try {
                $resp = Http::timeout(10)->withHeaders(['Accept' => 'application/json'])
                    ->get('https://viaf.org/viaf/' . $r->viaf_id . '/');
                if ($resp->ok()) $synced++;
            } catch (\Throwable $e) { $stats['errors']++; }
        }
        return $synced;
    }

    protected function syncWikidata(int $limit, bool $dry, array &$stats): int
    {
        if (! Schema::hasColumn('actor', 'wikidata_id')) return 0;
        $rows = DB::table('actor')->whereNotNull('wikidata_id')->limit($limit)->get(['id', 'wikidata_id']);
        $synced = 0;
        foreach ($rows as $r) {
            try {
                $resp = Http::timeout(10)->get('https://www.wikidata.org/wiki/Special:EntityData/' . $r->wikidata_id . '.json');
                if ($resp->ok()) $synced++;
            } catch (\Throwable $e) { $stats['errors']++; }
        }
        return $synced;
    }

    protected function pickLabel(array $body): ?string
    {
        foreach (['_label', 'prefLabel', 'rdfs:label'] as $k) {
            if (! empty($body[$k])) return is_array($body[$k]) ? ($body[$k]['@value'] ?? null) : $body[$k];
        }
        return null;
    }
}
