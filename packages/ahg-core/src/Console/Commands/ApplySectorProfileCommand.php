<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgCore\Console\Commands;

use AhgCore\Services\SectorProfileService;
use AhgCore\Support\SectorProfiles;
use Illuminate\Console\Command;

/**
 * Apply a sector site profile (heratio#1331) - thin CLI wrapper over
 * SectorProfileService, shared with `bin/install --sector=` and the admin
 * "Apply sector profile" UI. Idempotent + re-applicable; defaults only.
 */
class ApplySectorProfileCommand extends Command
{
    protected $signature = 'ahg:apply-sector-profile
        {sector : Sector code: archive|museum|gallery|library|dam|research}
        {--with-sample : Also load sample content for the sector (not yet implemented)}';

    protected $description = 'Apply a sector site profile (theme + identifier mask + sector default) over the install';

    public function __construct(private SectorProfileService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $sector = strtolower(trim((string) $this->argument('sector')));

        if (! SectorProfiles::has($sector)) {
            $this->error("Unknown sector '{$sector}'. Valid: ".implode(', ', SectorProfiles::codes()));

            return self::FAILURE;
        }

        try {
            $r = $this->service->apply($sector);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Applied sector profile: {$r['label']} ({$r['sector']})");
        $this->line("  theme: {$r['theme_count']} settings");
        $this->line("  identifier mask: {$r['mask']} (enabled)");

        if ($this->option('with-sample')) {
            $this->warn('  --with-sample: sample content is not implemented yet (planned follow-up slice).');
        }

        $this->info("Sector profile '{$r['sector']}' applied. Re-run any time to switch sectors.");

        return self::SUCCESS;
    }
}
