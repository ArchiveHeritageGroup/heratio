<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgRdm\Services;

use AhgResearch\Services\OdrlService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Enforce a dataset's human-gate disposition (#1341): apply ODRL access/embargo
 * policies, and on open release mint a DataCite DOI. Thin orchestration over
 * ahg-research OdrlService + ahg-doi-manage DoiService - no new policy or DOI
 * machinery.
 *
 * Policies are written against the dataset's container IO AND each child file
 * IO (target_type 'archival_description'), so OdrlPolicyMiddleware gates the
 * public IO show. Re-applying a disposition first clears the dataset's prior
 * rdm policies so the state always reflects the latest decision.
 */
class DatasetReleaseService
{
    /**
     * @return array{disposition:string, doi?:?string, policies?:int, embargo_until?:string}
     */
    public function apply(int $datasetId, string $disposition, ?int $userId, ?string $embargoUntil = null): array
    {
        $ds = DB::table('rdm_dataset')->where('id', $datasetId)->first();
        if (! $ds) {
            throw new \RuntimeException("Dataset {$datasetId} not found.");
        }

        $ioIds = $this->datasetIoIds($datasetId, (int) $ds->io_parent_id);
        $this->clearPolicies($ioIds);

        $out = ['disposition' => $disposition];

        // Access policies: 'release' is open (policies already cleared above);
        // restrict/de-identify -> indefinite prohibition; embargo -> until a date.
        if ($disposition !== 'release') {
            $dateTo = null;
            if ($disposition === 'embargo') {
                $dateTo = $embargoUntil ?: now()->addYear()->format('Y-m-d H:i:s');
                $out['embargo_until'] = $dateTo;
            }
            $this->restrict($ioIds, $dateTo, $userId);
            $out['policies'] = count($ioIds) * 2; // use + reproduce per IO
        }

        // Mint a DOI for ANY finalised disposition - a restricted/embargoed dataset
        // is still a citable record (public metadata, gated files); access is
        // controlled by the ODRL policies above, independently of the DOI.
        $out['doi'] = $this->mintDoi($datasetId, (int) $ds->io_parent_id);

        return $out;
    }

    /** Container IO + every deposited file's child IO. */
    private function datasetIoIds(int $datasetId, int $containerIo): array
    {
        $ids = DB::table('rdm_dataset_file')->where('dataset_id', $datasetId)
            ->pluck('io_id')->map(fn ($v) => (int) $v)->all();
        array_unshift($ids, $containerIo);

        return array_values(array_unique(array_filter($ids)));
    }

    /** Remove the dataset's existing ODRL policies (these IOs are rdm-owned). */
    private function clearPolicies(array $ioIds): void
    {
        $odrl = app(OdrlService::class);
        foreach ($ioIds as $io) {
            foreach ($odrl->getPoliciesForTarget('archival_description', $io) as $p) {
                if (isset($p->id)) {
                    $odrl->deletePolicy((int) $p->id);
                }
            }
        }
    }

    /** Prohibition on use + reproduce; optional date_to = embargo-until. */
    private function restrict(array $ioIds, ?string $dateTo, ?int $userId): void
    {
        $odrl = app(OdrlService::class);
        $constraints = $dateTo ? ['date_to' => $dateTo] : null;
        foreach ($ioIds as $io) {
            foreach (['use', 'reproduce'] as $action) {
                $odrl->createPolicy([
                    'target_type'      => 'archival_description',
                    'target_id'        => $io,
                    'policy_type'      => 'prohibition',
                    'action_type'      => $action,
                    'constraints_json' => $constraints,
                    'created_by'       => $userId,
                ]);
            }
        }
    }

    /**
     * Mint a DataCite DOI for the dataset's container IO. Uses DoiService in
     * dry-run (real prefix/suffix, no external call) when a DataCite config
     * exists - so dev never registers real DOIs - and falls back to a draft
     * test-prefix DOI otherwise. Idempotent (returns the existing DOI).
     */
    private function mintDoi(int $datasetId, int $containerIo): ?string
    {
        $existing = DB::table('rdm_dataset')->where('id', $datasetId)->value('doi');
        if (! empty($existing)) {
            return $existing;
        }

        $doi = null;
        try {
            if (class_exists(\AhgDoiManage\Services\DoiService::class)
                && DB::table('ahg_doi_config')->where('is_active', 1)->exists()) {
                // dry-run: builds the configured DOI string without the DataCite
                // HTTP call (safe on dev). Production swaps the false -> live mint.
                $r = app(\AhgDoiManage\Services\DoiService::class)->mint($containerIo, null, true);
                if (! empty($r['success']) && ! empty($r['doi'])) {
                    $doi = $r['doi'];
                }
            }
        } catch (\Throwable $e) {
            Log::info('[DatasetRelease] DOI mint fell back to draft: '.$e->getMessage());
        }

        if (! $doi) {
            $doi = '10.5072/heratio.dataset.'.$datasetId; // DataCite reserved test prefix
        }

        DB::table('rdm_dataset')->where('id', $datasetId)->update(['doi' => $doi, 'updated_at' => now()]);

        return $doi;
    }
}
