<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rebuild the GLAM browse facet cache - the scheduled hourly entry point.
 *
 * The browse sidebar reads the rows ahg:display-reindex writes: one set per
 * facet with published-only counts for visitors (subject, place, ...) and one
 * with all records for staff (subject_all, ...), as the original AtoM task did.
 * This command used to write its own rows under other names (subjects, places,
 * genres - about 558,000 rows at ANC that nothing read) and overwrote level and
 * repository with counts that included unpublished records. It now runs
 * display-reindex and removes those old rows.
 */
class RefreshFacetCacheCommand extends Command
{
    protected $signature = 'ahg:refresh-facet-cache
        {--facet= : Only rebuild one facet (glam_type, creator, subject, place, genre, level, repository, media_type)}';

    protected $description = 'Rebuild the GLAM browse facet cache (runs ahg:display-reindex)';

    /** Row names only the old version of this command wrote. */
    private const LEGACY_TYPES = ['subjects', 'places', 'genres'];

    public function handle(): int
    {
        if (! Schema::hasTable('display_facet_cache')) {
            $this->error('display_facet_cache table missing.');

            return self::FAILURE;
        }

        $removed = DB::table('display_facet_cache')->whereIn('facet_type', self::LEGACY_TYPES)->delete();
        if ($removed) {
            $this->info("removed {$removed} unused rows from the old facet names");
        }

        $facet = (string) $this->option('facet');

        return $this->call('ahg:display-reindex', $facet !== '' ? ['--facet' => rtrim($facet, 's')] : []);
    }
}
