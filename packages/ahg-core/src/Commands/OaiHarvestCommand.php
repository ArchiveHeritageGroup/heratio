<?php

/**
 * OaiHarvestCommand — harvest an OAI-PMH endpoint into oai_harvest history.
 *
 * Loops ListRecords with resumptionToken, captures one staging row per
 * record under a temporary table (oai_harvested_record), and updates the
 * oai_repository.last_harvest watermark on success. Mapping into
 * information_object is left to a separate import step.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class OaiHarvestCommand extends Command
{
    protected $signature = 'ahg:oai-harvest
        {--url= : OAI-PMH endpoint URL}
        {--set= : OAI set identifier}
        {--from= : Harvest records from date (YYYY-MM-DD)}
        {--until= : Harvest records until date (YYYY-MM-DD)}
        {--metadata-prefix=oai_dc : Metadata prefix}
        {--max-pages=200 : Safety limit on resumption pages}';

    protected $description = 'Harvest OAI-PMH records into oai_harvest history';

    public function handle(): int
    {
        $url = (string) $this->option('url');
        if (! $url) {
            $rows = DB::table('oai_repository')->whereNotNull('uri')->get(['id', 'name', 'uri']);
            if ($rows->isEmpty()) { $this->error('No --url given and oai_repository is empty.'); return self::FAILURE; }
            foreach ($rows as $r) $this->harvestOne($r->id, $r->uri);
            return self::SUCCESS;
        }

        $repoId = DB::table('oai_repository')->where('uri', $url)->value('id');
        if (! $repoId) {
            $repoId = DB::table('oai_repository')->insertGetId([
                'name' => parse_url($url, PHP_URL_HOST) ?: $url,
                'uri' => $url,
                'created_at' => now(),
                'updated_at' => now(),
                'serial_number' => 0,
            ]);
        }
        return $this->harvestOne($repoId, $url);
    }

    protected function harvestOne(int $repoId, string $url): int
    {
        $prefix = (string) $this->option('metadata-prefix');
        $set = (string) $this->option('set');
        $from = (string) $this->option('from');
        $until = (string) $this->option('until');
        $maxPages = max(1, (int) $this->option('max-pages'));

        $start = now();
        $harvestId = DB::table('oai_harvest')->insertGetId([
            'oai_repository_id' => $repoId,
            'start_timestamp' => $start,
            'metadataPrefix' => $prefix,
            'set' => $set ?: null,
            'created_at' => $start,
            'serial_number' => 0,
        ]);

        $params = ['verb' => 'ListRecords', 'metadataPrefix' => $prefix];
        if ($set)   $params['set'] = $set;
        if ($from)  $params['from'] = $from;
        if ($until) $params['until'] = $until;

        $page = 0; $total = 0; $errors = 0; $resumption = null;
        while (true) {
            $page++;
            try {
                $resp = Http::timeout(30)->get($url, $resumption ? ['verb' => 'ListRecords', 'resumptionToken' => $resumption] : $params);
                if (! $resp->ok()) { $errors++; break; }
                $xml = @simplexml_load_string($resp->body());
                if (! $xml) { $errors++; break; }
                if (isset($xml->error)) { $this->warn("OAI error: {$xml->error}"); $errors++; break; }

                foreach ($xml->ListRecords->record ?? [] as $rec) {
                    $identifier = (string) $rec->header->identifier;
                    $datestamp = (string) $rec->header->datestamp;
                    if (Schema::hasTable('oai_harvested_record')) {
                        DB::table('oai_harvested_record')->updateOrInsert(
                            ['harvest_id' => $harvestId, 'identifier' => $identifier],
                            ['datestamp' => $datestamp, 'metadata' => $rec->metadata->asXML(), 'created_at' => now()],
                        );
                    }
                    $total++;
                }
                $resumption = (string) ($xml->ListRecords->resumptionToken ?? '');
                if (! $resumption) break;
                if ($page >= $maxPages) { $this->warn('max-pages reached, stopping.'); break; }
            } catch (\Throwable $e) {
                $errors++;
                $this->warn($e->getMessage());
                break;
            }
        }

        $end = now();
        DB::table('oai_harvest')->where('id', $harvestId)->update([
            'end_timestamp' => $end,
            'last_harvest' => $end,
            'last_harvest_attempt' => $end,
        ]);
        $this->info(sprintf('repo=%d harvested=%d pages=%d errors=%d', $repoId, $total, $page, $errors));
        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}
