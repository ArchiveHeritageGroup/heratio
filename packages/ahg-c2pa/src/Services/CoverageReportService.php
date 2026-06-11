<?php
/**
 * Heratio - C2PA "authenticity coverage" report (deepens #1201 / #1209).
 *
 * The admin-facing companion to AuthenticityStatsService. Where the public
 * /verify front door answers "how much can be verified?" in headline terms,
 * this service answers the institution's operational question: "where are the
 * gaps, and how do I close them?". It computes the same whole-collection
 * coverage numbers PLUS a per-holding-repository breakdown, so a collections
 * manager can see at a glance which repositories carry content credentials and
 * which are still unsigned.
 *
 * Strictly read-only. Every table is guarded with Schema::hasTable and every
 * query block is wrapped in try/catch, so an install without the c2pa layer
 * (or with an empty one, or an unreachable DB) returns an honest zero-state
 * shape rather than a 500. Only cheap COUNT / GROUP BY aggregates are used.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Read-only coverage reporter over the content-credentials layer. Composes the
 * whole-institution snapshot (AuthenticityStatsService) with a per-repository
 * gap breakdown computed from cheap grouped counts.
 */
final class CoverageReportService
{
    /** digital_object.usage_id for a master file (taxonomy 47); mirrors the backfill command + AuthenticityStatsService. */
    private const USAGE_MASTER = 140;

    /** How many repository rows to surface in the breakdown table. */
    private const TOP_N = 25;

    public function __construct(private AuthenticityStatsService $stats)
    {
    }

    /**
     * Full coverage report for the admin dashboard.
     *
     * @return array{
     *     enabled: bool,
     *     reason: ?string,
     *     can_sign: bool,
     *     capability_summary: string,
     *     total_masters: int,
     *     records_total: int,
     *     records_with_credentials: int,
     *     signed_records: int,
     *     unsigned_records: int,
     *     covered_masters: int,
     *     uncovered_masters: int,
     *     coverage_pct: float,
     *     verified_records: int,
     *     invalid_records: int,
     *     verified_pct: float,
     *     manifests_total: int,
     *     last_signed_at: ?string,
     *     issuers: list<array{kid: string, count: int}>,
     *     breakdown_dimension: string,
     *     breakdown: list<array{
     *         id: ?int,
     *         label: string,
     *         masters: int,
     *         covered: int,
     *         coverage_pct: float
     *     }>,
     *     breakdown_truncated: bool
     * }
     */
    public function report(): array
    {
        // Start from the shared whole-institution snapshot so the headline
        // numbers are identical to the public /verify page (single source of
        // truth). Then layer the admin-only detail on top.
        $snapshot = $this->stats->snapshot();

        $report = [
            'enabled'                  => (bool) ($snapshot['enabled'] ?? false),
            'reason'                   => $snapshot['reason'] ?? null,
            'can_sign'                 => (bool) ($snapshot['can_sign'] ?? false),
            'capability_summary'       => (string) ($snapshot['capability_summary'] ?? ''),
            'total_masters'            => (int) ($snapshot['total_masters'] ?? 0),
            'records_total'            => (int) ($snapshot['records_total'] ?? 0),
            'records_with_credentials' => (int) ($snapshot['records_with_credentials'] ?? 0),
            'signed_records'           => (int) ($snapshot['signed_records'] ?? 0),
            'unsigned_records'         => (int) ($snapshot['unsigned_records'] ?? 0),
            'covered_masters'          => (int) ($snapshot['covered_masters'] ?? 0),
            'uncovered_masters'        => 0,
            'coverage_pct'             => (float) ($snapshot['coverage_pct'] ?? 0.0),
            'verified_records'         => 0,
            'invalid_records'          => 0,
            'verified_pct'             => 0.0,
            'manifests_total'          => (int) ($snapshot['manifests_total'] ?? 0),
            'last_signed_at'           => $snapshot['last_signed_at'] ?? null,
            'issuers'                  => $snapshot['issuers'] ?? [],
            'breakdown_dimension'      => 'repository',
            'breakdown'                => [],
            'breakdown_truncated'      => false,
        ];

        $report['uncovered_masters'] = max(0, $report['total_masters'] - $report['covered_masters']);

        // Signed-but-INVALID vs VERIFY split. The provenance table carries a
        // cached sign_status of 'signed'/'unsigned' only; whether a signed
        // record still verifies (vs has been tampered) is re-derived live per
        // record on the /verify pages. For a cheap whole-collection aggregate we
        // treat every signed record as verifiable and surface any persisted
        // failure marker if one exists, so the report never runs a per-record
        // crypto re-check (which would be O(n) signatures on a dashboard load).
        [$verified, $invalid] = $this->signedSplit($report['signed_records']);
        $report['verified_records'] = $verified;
        $report['invalid_records']  = $invalid;
        if ($report['signed_records'] > 0) {
            $report['verified_pct'] = round($verified / $report['signed_records'] * 100, 1);
        }

        // Per-repository gap breakdown. Independent of the headline numbers, so
        // a failure here degrades to an empty breakdown, never a 500.
        $report['breakdown'] = $this->repositoryBreakdown($report['breakdown_truncated']);

        return $report;
    }

    /**
     * Split the signed record total into VERIFY vs INVALID using only the
     * cached sign_status column (cheap GROUP BY). 'signed' counts as verifiable;
     * any explicit failure marker ('invalid'/'failed'/'tampered') counts as
     * invalid. We never re-run signature crypto here - that is the per-record
     * /verify path's job - so a dashboard load stays O(1) in DB work.
     *
     * @return array{0:int,1:int} [verified, invalid]
     */
    private function signedSplit(int $signedTotal): array
    {
        if ($signedTotal <= 0 || !Schema::hasTable('ahg_c2pa_provenance')) {
            return [max(0, $signedTotal), 0];
        }

        try {
            $rows = DB::table('ahg_c2pa_provenance')
                ->select('sign_status', DB::raw('COUNT(*) as c'))
                ->whereNotNull('manifest_id')
                ->groupBy('sign_status')
                ->get();

            $invalid = 0;
            foreach ($rows as $row) {
                $status = strtolower((string) ($row->sign_status ?? ''));
                if (in_array($status, ['invalid', 'failed', 'tampered', 'error'], true)) {
                    $invalid += (int) $row->c;
                }
            }

            $invalid  = min($invalid, $signedTotal);
            $verified = max(0, $signedTotal - $invalid);

            return [$verified, $invalid];
        } catch (Throwable) {
            // Honest fallback: treat all signed records as verifiable.
            return [max(0, $signedTotal), 0];
        }
    }

    /**
     * Per-holding-repository coverage breakdown (top N by master count).
     *
     * For each repository: how many MASTER digital objects it holds (the
     * denominator) and how many of those carry a signed provenance record (the
     * numerator), yielding a per-repository coverage %. The repository is
     * resolved from information_object.repository_id and named (en-preferred)
     * from actor_i18n.authorized_form_of_name. Records whose IO has no
     * repository fold into a single "Unassigned" bucket.
     *
     * Two cheap grouped queries (masters-per-repo, covered-per-repo) joined in
     * PHP - no per-row work, no signature crypto.
     *
     * @param bool $truncated set by reference when more than TOP_N repositories exist
     *
     * @return list<array{id: ?int, label: string, masters: int, covered: int, coverage_pct: float}>
     */
    private function repositoryBreakdown(bool &$truncated): array
    {
        if (!Schema::hasTable('digital_object') || !Schema::hasTable('information_object')) {
            return [];
        }

        try {
            // Denominator: master digital objects per repository.
            $masterRows = DB::table('digital_object as d')
                ->join('information_object as io', 'io.id', '=', 'd.object_id')
                ->whereNull('d.parent_id')
                ->where(function ($w) {
                    $w->where('d.usage_id', self::USAGE_MASTER)->orWhereNull('d.usage_id');
                })
                ->select('io.repository_id as repo', DB::raw('COUNT(*) as masters'))
                ->groupBy('io.repository_id')
                ->get();

            if ($masterRows->isEmpty()) {
                return [];
            }

            // Numerator: master digital objects per repository that have a
            // signed provenance record bound. Only meaningful when the c2pa
            // layer is installed; otherwise every repository shows 0 covered.
            $coveredByRepo = [];
            if (Schema::hasTable('ahg_c2pa_provenance')) {
                $coveredRows = DB::table('ahg_c2pa_provenance as p')
                    ->join('digital_object as d', 'd.id', '=', 'p.digital_object_id')
                    ->join('information_object as io', 'io.id', '=', 'd.object_id')
                    ->whereNotNull('p.manifest_id')
                    ->whereNull('d.parent_id')
                    ->where(function ($w) {
                        $w->where('d.usage_id', self::USAGE_MASTER)->orWhereNull('d.usage_id');
                    })
                    ->select('io.repository_id as repo', DB::raw('COUNT(DISTINCT d.id) as covered'))
                    ->groupBy('io.repository_id')
                    ->get();
                foreach ($coveredRows as $row) {
                    $coveredByRepo[(string) ($row->repo ?? '')] = (int) $row->covered;
                }
            }

            $names = $this->repositoryNames(
                $masterRows->pluck('repo')->filter(fn ($v) => $v !== null)->map(fn ($v) => (int) $v)->all()
            );

            $rows = [];
            foreach ($masterRows as $mr) {
                $repoId  = $mr->repo === null ? null : (int) $mr->repo;
                $masters = (int) $mr->masters;
                $covered = (int) ($coveredByRepo[(string) ($mr->repo ?? '')] ?? 0);
                $covered = min($covered, $masters);

                $rows[] = [
                    'id'           => $repoId,
                    'label'        => $repoId === null
                        ? 'Unassigned (no holding repository)'
                        : ($names[$repoId] ?? ('Repository #' . $repoId)),
                    'masters'      => $masters,
                    'covered'      => $covered,
                    'coverage_pct' => $masters > 0 ? round($covered / $masters * 100, 1) : 0.0,
                ];
            }

            // Surface the biggest holdings first - that is where the gap matters
            // most. Within a tie, the lower coverage first so the worst gaps
            // float up.
            usort($rows, static function (array $a, array $b): int {
                return $b['masters'] <=> $a['masters']
                    ?: $a['coverage_pct'] <=> $b['coverage_pct'];
            });

            if (count($rows) > self::TOP_N) {
                $truncated = true;
                $rows = array_slice($rows, 0, self::TOP_N);
            }

            return $rows;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Resolve repository ids to a display name (en-preferred) from
     * actor_i18n.authorized_form_of_name. Repositories are actors, so the name
     * lives on the actor i18n row. Missing names simply fall back to a numeric
     * label at the call site. One batched IN query; never throws.
     *
     * @param list<int> $ids
     *
     * @return array<int,string>
     */
    private function repositoryNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn ($v) => $v > 0)));
        if ($ids === [] || !Schema::hasTable('actor_i18n')) {
            return [];
        }

        try {
            $rows = DB::table('actor_i18n')
                ->whereIn('id', $ids)
                ->orderByRaw("culture = 'en' DESC")
                ->get(['id', 'authorized_form_of_name']);

            $names = [];
            foreach ($rows as $row) {
                $id = (int) $row->id;
                // en-preferred ordering means the first row seen per id wins.
                if (!isset($names[$id])) {
                    $name = trim((string) ($row->authorized_form_of_name ?? ''));
                    if ($name !== '') {
                        $names[$id] = $name;
                    }
                }
            }

            return $names;
        } catch (Throwable) {
            return [];
        }
    }
}
