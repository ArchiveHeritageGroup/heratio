<?php
/**
 * Heratio - auto-provenance backfill for ingested masters (issue #1201).
 *
 * The authoritative ingest-coverage path. Every live upload path (HTTP upload,
 * the ingest wizard, the scanner) inserts the digital_object row with a raw
 * DB::table()->insert() and fires no Eloquent/domain event, so the ingest-time
 * listener cannot see them. This command closes that gap: it scans master
 * digital objects that have NO ahg_c2pa_provenance record yet and records one
 * for each (sign + sidecar + native embed when c2patool is present).
 *
 * Distinct from `ahg:c2pa-embed`, which only writes a JUMBF embed for records
 * that are ALREADY signed. This command CREATES the missing provenance records;
 * the embed step (sidecar always, JUMBF when c2patool is present) happens inside
 * ProvenanceRecordService::record() as part of that, and `ahg:c2pa-embed` can
 * still be run afterwards to (re)embed in bulk.
 *
 * Idempotent via DigitalObjectProvenanceService (skips any digital object that
 * already has a record). Dry-run by default; pass --commit to write records.
 * Schedule it daily as a safety net for any path that bypasses the listener.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Console\Commands;

use AhgC2pa\Services\DigitalObjectProvenanceService;
use AhgC2pa\Services\ProvenanceRecordService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Backfill digitisation-provenance records for ingested master digital objects
 * that have none. Mirrors the dry-run/commit + per-row loop style of
 * ahg:c2pa-embed and ahg:optimize-models.
 */
class C2paProvenanceBackfillCommand extends Command
{
    /** digital_object.usage_id for a master file (taxonomy 47). */
    private const USAGE_MASTER = 140;

    protected $signature = 'ahg:c2pa-provenance-backfill '
        . '{--id= : Restrict to one information_object id} '
        . '{--limit=0 : Max number of masters to process (0 = no limit)} '
        . '{--commit : Actually create provenance records (otherwise dry-run)}';

    protected $description = 'Record C2PA digitisation provenance for ingested master digital objects that have none';

    public function handle(
        DigitalObjectProvenanceService $bridge,
        ProvenanceRecordService $prov,
    ): int {
        if (!Schema::hasTable('digital_object') || !Schema::hasTable('ahg_c2pa_provenance')) {
            $this->error('C2PA / digital_object tables not installed; nothing to do.');

            return 1;
        }

        $cap = $prov->capability();
        $this->line('Capability: ' . $cap['summary']);
        if (!$cap['can_sign_manifest']) {
            $this->warn('ext-sodium unavailable: records will be created unsigned (sign_status=unsigned).');
        }

        // Master rows (usage_id=140 or parentless) with no provenance record.
        $q = DB::table('digital_object as d')
            ->leftJoin('ahg_c2pa_provenance as p', 'p.digital_object_id', '=', 'd.id')
            ->whereNull('p.id')
            ->whereNull('d.parent_id')
            ->where(function ($w) {
                $w->where('d.usage_id', self::USAGE_MASTER)->orWhereNull('d.usage_id');
            });
        if ($this->option('id')) {
            $q->where('d.object_id', (int) $this->option('id'));
        }
        $q->orderBy('d.id')->select(['d.id', 'd.object_id', 'd.name', 'd.path']);

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $q->limit($limit);
        }

        $rows = $q->get();
        $commit = (bool) $this->option('commit');

        $this->info(($commit ? 'COMMIT' : 'DRY-RUN') . ': ' . $rows->count() . ' master(s) without a provenance record');
        if ($rows->isEmpty()) {
            return 0;
        }

        $recorded = 0;
        $skipped = 0;
        foreach ($rows as $r) {
            // Skip externally-linked URL "masters" - no local file to sign.
            if (preg_match('#^(https?|ftp)://#i', (string) ($r->path ?? ''))) {
                $skipped++;
                continue;
            }

            if (!$commit) {
                $this->line("  do#{$r->id} io#{$r->object_id} {$r->name} -> would record");
                continue;
            }

            try {
                $id = $bridge->recordForDigitalObject((int) $r->id, 'Backfilled via ahg:c2pa-provenance-backfill');
            } catch (Throwable $e) {
                $id = null;
                $this->error("  do#{$r->id} - record threw: " . $e->getMessage());
            }
            if ($id === null) {
                $skipped++;
                continue;
            }
            $this->info("  do#{$r->id} io#{$r->object_id} {$r->name} -> provenance #{$id}");
            $recorded++;
        }

        if ($commit) {
            $this->info("Done. Recorded {$recorded}, skipped {$skipped}.");
        } else {
            $this->info('Dry-run only. Re-run with --commit to create provenance records.');
        }

        return 0;
    }
}
