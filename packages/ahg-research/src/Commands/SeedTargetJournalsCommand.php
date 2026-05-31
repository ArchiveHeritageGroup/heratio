<?php

/**
 * SeedTargetJournalsCommand - seed the #1107 target-journal directory with the
 * DHET-accredited starter set (South-African accreditation module). Idempotent
 * (upsert by title), so safe to re-run.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgResearch\Commands;

use AhgResearch\Services\ResearchTargetJournalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SeedTargetJournalsCommand extends Command
{
    protected $signature = 'ahg:seed-target-journals';

    protected $description = 'Seed the target-journal directory with the DHET-accredited starter set (#1107).';

    public function handle(ResearchTargetJournalService $service): int
    {
        if (! Schema::hasTable('research_target_journal')) {
            $this->error('research_target_journal table is missing; run the package install SQL first.');

            return self::FAILURE;
        }
        $n = $service->seedDhetStarter();
        $this->info("Seeded/updated {$n} DHET-accredited journals.");

        return self::SUCCESS;
    }
}
