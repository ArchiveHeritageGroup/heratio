<?php

/**
 * BackfillEmbeddedMetadataCommand - re-extract the FULL embedded metadata set
 * (#1106) for existing master digital objects, so records ingested before the
 * grouped `-G1 -a -u` extraction get their complete raw_metadata populated.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgCore\Console\Commands;

use AhgCore\Services\DigitalObjectService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BackfillEmbeddedMetadataCommand extends Command
{
    protected $signature = 'ahg:backfill-embedded-metadata
        {--limit=0 : Max masters to process (0 = all)}
        {--id= : Re-extract a single master digital_object id}';

    protected $description = 'Re-extract full embedded metadata for master digital objects (#1106).';

    public function handle(): int
    {
        if (! Schema::hasTable('digital_object')) {
            $this->error('digital_object table not found.');

            return self::FAILURE;
        }

        $q = DB::table('digital_object')->where('usage_id', DigitalObjectService::USAGE_MASTER);
        if ($this->option('id')) {
            $q->where('id', (int) $this->option('id'));
        }
        $q->orderBy('id');
        if (($limit = (int) $this->option('limit')) > 0) {
            $q->limit($limit);
        }

        $ids = $q->pluck('id');
        $this->info("Processing {$ids->count()} master digital object(s)…");

        $ok = 0;
        $fail = 0;
        $bar = $this->output->createProgressBar($ids->count());
        foreach ($ids as $id) {
            try {
                DigitalObjectService::extractMetadataForMaster((int) $id);
                $ok++;
            } catch (Throwable $e) {
                $fail++;
                $this->newLine();
                $this->warn("DO {$id}: ".$e->getMessage());
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info("Done. Re-extracted {$ok}, failed {$fail}.");

        return self::SUCCESS;
    }
}
