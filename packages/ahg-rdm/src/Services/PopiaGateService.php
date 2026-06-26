<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgRdm\Services;

use Illuminate\Support\Facades\DB;

/**
 * Human gate for the POPIA scan (#1340) - the authority. The scan only
 * suggests; a reviewer confirms/overrides every finding and applies a
 * disposition. A dataset with unresolved (or confirmed) PERSONAL/SPECIAL
 * findings CANNOT be released open - this service enforces that gate, and logs
 * every decision (local provenance + AiDisclosureService when project-linked).
 */
class PopiaGateService
{
    private const PII_CATEGORIES = ['personal', 'special_category'];
    private const DISPOSITIONS = ['restrict', 'embargo', 'de-identify', 'release'];

    /** Confirm (real PII) or dismiss (false positive) a single finding. */
    public function resolveFinding(int $findingId, string $decision, ?string $note, ?int $userId): void
    {
        $status = $decision === 'dismiss' ? 'dismissed' : 'confirmed';
        $finding = DB::table('rdm_scan_finding')->where('id', $findingId)->first();
        if (! $finding) {
            throw new \RuntimeException("Finding {$findingId} not found.");
        }

        DB::table('rdm_scan_finding')->where('id', $findingId)->update([
            'review_status' => $status,
            'reviewed_by'   => $userId,
            'reviewed_at'   => now(),
            'decision_note' => $note ? mb_substr($note, 0, 500) : null,
        ]);

        $this->logProvenance(
            (int) $finding->dataset_id,
            "finding #{$findingId} ({$finding->type}, {$finding->method}) -> {$status}",
            (string) $finding->method,
            $userId,
            "dataset:{$finding->dataset_id} finding:{$findingId}"
        );
    }

    /**
     * Apply a dataset disposition. 'release' (open) is blocked unless the gate
     * is clear (no pending and no confirmed PERSONAL/SPECIAL findings).
     *
     * @return array{disposition:string, status:string}
     */
    public function setDisposition(int $datasetId, string $disposition, ?int $userId, ?string $embargoUntil = null): array
    {
        if (! in_array($disposition, self::DISPOSITIONS, true)) {
            throw new \InvalidArgumentException("Invalid disposition '{$disposition}'.");
        }

        $gate = $this->gateStatus($datasetId);
        if ($disposition === 'release' && ! $gate['can_release']) {
            throw new \RuntimeException(
                "Open release blocked: {$gate['pending']} finding(s) still unresolved and {$gate['confirmed_pii']} "
                ."confirmed PERSONAL/SPECIAL. Resolve every finding (with none confirmed as PII) before open release, "
                ."or choose restrict / embargo / de-identify."
            );
        }

        // release (gate clear) -> publishable/open; any protective disposition -> restricted.
        $status = $disposition === 'release' ? 'published' : 'restricted';

        DB::table('rdm_dataset')->where('id', $datasetId)->update([
            'disposition'    => $disposition,
            'disposition_by' => $userId,
            'disposition_at' => now(),
            'status'         => $status,
            'updated_at'     => now(),
        ]);

        // #1341 side-effects: ODRL access/embargo on the dataset's IOs, and a
        // DataCite DOI on open release.
        $effects = app(DatasetReleaseService::class)->apply($datasetId, $disposition, $userId, $embargoUntil);

        $this->logProvenance(
            $datasetId,
            "disposition -> {$disposition} (status {$status})".(! empty($effects['doi']) ? ", doi {$effects['doi']}" : ''),
            'human-gate',
            $userId,
            "dataset:{$datasetId}"
        );

        return array_merge(['disposition' => $disposition, 'status' => $status], $effects);
    }

    /**
     * Gate state for the UI / publish guard.
     *
     * @return array{pending:int, confirmed_pii:int, dismissed:int, can_release:bool}
     */
    public function gateStatus(int $datasetId): array
    {
        $base = DB::table('rdm_scan_finding')->where('dataset_id', $datasetId)->whereIn('category', self::PII_CATEGORIES);
        $pending = (clone $base)->where('review_status', 'pending')->count();
        $confirmed = (clone $base)->where('review_status', 'confirmed')->count();
        $dismissed = (clone $base)->where('review_status', 'dismissed')->count();

        return [
            'pending'       => $pending,
            'confirmed_pii' => $confirmed,
            'dismissed'     => $dismissed,
            'can_release'   => $pending === 0 && $confirmed === 0,
        ];
    }

    /**
     * Provenance: always-on local trail lives on the finding/dataset rows; this
     * additionally writes the cross-cutting research audit via AiDisclosureService
     * when the dataset is linked to a project. Best-effort (never blocks the gate).
     */
    private function logProvenance(int $datasetId, string $purpose, ?string $model, ?int $userId, string $ref): void
    {
        try {
            $projectId = (int) DB::table('rdm_dataset')->where('id', $datasetId)->value('project_id');
            if ($projectId > 0 && class_exists(\AhgResearch\Services\AiDisclosureService::class)) {
                app(\AhgResearch\Services\AiDisclosureService::class)->addLogEntry($projectId, [
                    'tool'       => 'popia-scan',
                    'model'      => $model,
                    'purpose'    => 'RDM POPIA gate: '.$purpose,
                    'output_ref' => $ref,
                ], $userId);
            }
        } catch (\Throwable $e) {
            // non-fatal
        }
    }
}
