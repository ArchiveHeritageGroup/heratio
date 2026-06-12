<?php

/**
 * RightsReportController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */



namespace AhgReports\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Rights and Access report - a read-only, administrator-facing view of how the
 * catalogue breaks down by access status, rights/licensing coverage, and ODRL
 * policy governance. It answers an administrator's question: what is open, what
 * is restricted, and what carries a rights statement.
 *
 * This is deliberately DISTINCT from its report siblings:
 *   - the data-quality report measures descriptive (ISAD(G)) completeness;
 *   - the AI-usage report measures where AI assisted;
 *   - the catalogue-growth report measures size, growth and composition;
 *   - the preservation-health report measures preservation integrity;
 *   - THIS report measures rights, access and policy coverage.
 *
 * Three honest signals, three different stores
 * --------------------------------------------
 * 1. Publication (the baseline access signal). Stored in the `status` table:
 *    status.object_id = information_object.id, status.type_id = 158
 *    (the publication taxonomy), status.status_id = 160 (Published). This is the
 *    only access-state signal that lives on a record directly. There is NO
 *    separate access/accessibility status TYPE in the `status` table on this
 *    schema (verified by GROUP BY: type_id 158 is the only value present), so the
 *    report does not invent an "accessibility status"; publication IS the access
 *    baseline and is framed as such.
 *
 * 2. Rights coverage and copyright status. Stored in the Class-Table-Inheritance
 *    `rights` table (and `rights_i18n`). A record is linked to a rights row
 *    through the generic `relation` table: relation.subject_id =
 *    information_object.id, relation.object_id = rights.id, relation.type_id =
 *    RELATION_TYPE_RIGHT (the "Right" relation-type term, id 168 - verified
 *    against term_i18n and the live relation rows; that same term id is also used
 *    for actor-rights edges, so this report ONLY counts edges whose object side is
 *    an actual `rights` row joined to an actual information_object on the subject
 *    side, never a bare type_id match). The copyright-status breakdown reads
 *    rights.copyright_status_id resolved through term_i18n (e.g. "Under
 *    copyright", "Public domain"); a rights row with no copyright_status_id is
 *    shown honestly as "(copyright status not recorded)".
 *
 * 3. ODRL digital-rights policies. Stored in `research_rights_policy`, the store
 *    owned by ahg-research's OdrlService and enforced by OdrlPolicyMiddleware.
 *    Each policy row carries target_type (here 'archival_description' or
 *    'collection'), target_id, policy_type (permission / prohibition) and
 *    action_type - the bare ODRL action verb as stored ('use' for viewing,
 *    'reproduce' for printing, 'distribute', ...). The middleware aliases these as
 *    odrl:use / odrl:reproduce but the stored value is the bare verb, so this
 *    report matches the bare verbs. Per OdrlService's documented default, a record
 *    with NO policy is OPEN access; this report counts governed records (a
 *    distinct target_id under a policy) and frames everything else as open by
 *    default - it does NOT claim those records are restricted.
 *
 * Honesty
 * -------
 * Where a signal is absent (no rights row, no copyright status, no ODRL policy)
 * the report says so plainly rather than inferring a right it cannot see. A
 * missing policy is open by default, not unknown; a missing rights row is "no
 * rights statement recorded", not "no rights".
 *
 * Defensiveness
 * -------------
 * Every metric is a single grouped/aggregate COUNT (or a DISTINCT existence
 * count) over existing tables - never a per-row PHP scan of the catalogue. Every
 * probe is Schema::hasTable guarded and the whole build is wrapped in try/catch,
 * so a fresh install or a missing table degrades to a calm empty state and never
 * 500s. The report counts and surfaces; it writes nothing and runs no ALTER. The
 * rights vocabulary used (ODRL actions, rights statements, copyright status) is
 * jurisdiction-neutral - no single country's copyright regime is assumed.
 */
class RightsReportController extends Controller
{
    /** Synthetic root information_object id; real records sit beneath it. */
    private const ROOT_ID = 1;

    /** status.type_id for the publication-status taxonomy. */
    private const STATUS_TYPE_PUBLICATION = 158;

    /** status.status_id for "Published". */
    private const PUBLICATION_STATUS_PUBLISHED = 160;

    /** relation.type_id for the "Right" relation type (record <-> rights row). */
    private const RELATION_TYPE_RIGHT = 168;

    /** ODRL action verbs as stored on research_rights_policy.action_type. */
    private const ODRL_ACTION_USE = 'use';
    private const ODRL_ACTION_REPRODUCE = 'reproduce';

    /** How many copyright-status buckets the breakdown shows at most. */
    private const TOP_COPYRIGHT_STATUSES = 12;

    public function index(): View
    {
        return view('ahg-reports::rights-report.index', $this->buildReport());
    }

    /**
     * Assemble the whole report defensively. Any failure anywhere collapses to
     * the empty state rather than a 500.
     *
     * @return array<string,mixed>
     */
    private function buildReport(): array
    {
        $empty = [
            'available'           => false,
            'total'               => 0,
            // Publication (access baseline)
            'published'           => 0,
            'unpublished'         => 0,
            'published_pct'       => 0.0,
            'publication_rows'    => [],
            // Rights coverage
            'rights_available'    => false,
            'with_rights'         => 0,
            'without_rights'      => 0,
            'with_rights_pct'     => 0.0,
            'rights_rows'         => [],
            'copyright_rows'      => [],
            'copyright_recorded'  => false,
            // ODRL policies
            'odrl_available'      => false,
            'governed'            => 0,
            'open_default'        => 0,
            'governed_pct'        => 0.0,
            'odrl_action_rows'    => [],
            'odrl_coverage_rows'  => [],
            'odrl_policy_total'   => 0,
        ];

        try {
            if (! Schema::hasTable('information_object')) {
                return $empty;
            }

            $total = $this->realTotal();

            if ($total <= 0) {
                // No real records yet. Calm empty state, flagged available so the
                // page frames it as "nothing catalogued yet" rather than an error.
                return array_merge($empty, ['available' => true]);
            }

            $report = array_merge($empty, [
                'available' => true,
                'total'     => $total,
            ]);

            $report = array_merge($report, $this->publicationSection($total));
            $report = array_merge($report, $this->rightsSection($total));
            $report = array_merge($report, $this->odrlSection($total));

            return $report;
        } catch (\Throwable $e) {
            // Absent column, missing table, locked table, driver error - none of
            // these should ever break the report. Degrade to empty state.
            return $empty;
        }
    }

    /**
     * Publication breakdown: the access baseline. Published versus draft /
     * unpublished, as a share of all real records. One EXISTS-gated COUNT for the
     * published side; the remainder is unpublished. No per-row scan.
     *
     * @return array<string,mixed>
     */
    private function publicationSection(int $total): array
    {
        $published   = $this->publishedTotal();
        $unpublished = max(0, $total - $published);

        $rows = [
            [
                'label' => __('Published (publicly visible)'),
                'count' => $published,
                'pct'   => $this->pct($published, $total),
                'tone'  => 'success',
                'icon'  => 'eye',
            ],
            [
                'label' => __('Draft or unpublished'),
                'count' => $unpublished,
                'pct'   => $this->pct($unpublished, $total),
                'tone'  => 'secondary',
                'icon'  => 'eye-slash',
            ],
        ];

        return [
            'published'        => $published,
            'unpublished'      => $unpublished,
            'published_pct'    => $this->pct($published, $total),
            'publication_rows' => $rows,
        ];
    }

    /** Published real records (status type 158, status 160). */
    private function publishedTotal(): int
    {
        return (int) $this->realBase()
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('status as st')
                  ->whereColumn('st.object_id', 'io.id')
                  ->where('st.type_id', self::STATUS_TYPE_PUBLICATION)
                  ->where('st.status_id', self::PUBLICATION_STATUS_PUBLISHED);
            })
            ->count();
    }

    /**
     * Rights coverage: how many records carry a rights statement (a linked
     * `rights` row) versus how many do not, plus a copyright-status breakdown of
     * the rights rows that ARE linked. The link is the canonical AtoM rights
     * relation: relation.subject_id = io.id, relation.object_id = rights.id,
     * relation.type_id = RELATION_TYPE_RIGHT. A single DISTINCT-count EXISTS for
     * coverage; a single GROUP BY for the copyright-status breakdown. No per-row
     * scan. Omitted (the cards hide) when the rights or relation table is absent.
     *
     * @return array<string,mixed>
     */
    private function rightsSection(int $total): array
    {
        if (! Schema::hasTable('rights') || ! Schema::hasTable('relation')) {
            return ['rights_available' => false];
        }

        // Records with at least one linked rights row (a rights statement).
        $withRights = (int) $this->realBase()
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('relation as rel')
                  ->join('rights as rt', 'rt.id', '=', 'rel.object_id')
                  ->whereColumn('rel.subject_id', 'io.id')
                  ->where('rel.type_id', self::RELATION_TYPE_RIGHT);
            })
            ->count();

        $withRights    = min($withRights, $total);
        $withoutRights = max(0, $total - $withRights);

        $rows = [
            [
                'label' => __('Carries a rights statement'),
                'count' => $withRights,
                'pct'   => $this->pct($withRights, $total),
                'tone'  => 'success',
                'icon'  => 'patch-check',
            ],
            [
                'label' => __('No rights statement recorded'),
                'count' => $withoutRights,
                'pct'   => $this->pct($withoutRights, $total),
                'tone'  => 'warning',
                'icon'  => 'patch-question',
            ],
        ];

        return array_merge(
            [
                'rights_available' => true,
                'with_rights'      => $withRights,
                'without_rights'   => $withoutRights,
                'with_rights_pct'  => $this->pct($withRights, $total),
                'rights_rows'      => $rows,
            ],
            $this->copyrightBreakdown($withRights)
        );
    }

    /**
     * Copyright-status breakdown of the rights rows that ARE linked to records.
     * GROUP BY rights.copyright_status_id with the label resolved from term_i18n;
     * a rights row with no copyright_status_id is shown honestly as
     * "(copyright status not recorded)". Counts DISTINCT information_object ids so
     * a record with several rights rows is not double-counted. The denominator is
     * the rights-bearing record set so the shares read as "of records that carry a
     * rights statement". One grouped aggregate, no per-row scan.
     *
     * @return array<string,mixed>
     */
    private function copyrightBreakdown(int $withRights): array
    {
        $hasTerm = Schema::hasTable('term_i18n');

        $rows = DB::table('relation as rel')
            ->where('rel.type_id', self::RELATION_TYPE_RIGHT)
            ->join('rights as rt', 'rt.id', '=', 'rel.object_id')
            ->join('information_object as io', 'io.id', '=', 'rel.subject_id')
            ->where('io.id', '!=', self::ROOT_ID)
            ->where('io.parent_id', '!=', self::ROOT_ID)
            ->groupBy('rt.copyright_status_id')
            ->orderByDesc(DB::raw('COUNT(DISTINCT io.id)'))
            ->limit(self::TOP_COPYRIGHT_STATUSES)
            ->get([
                'rt.copyright_status_id as csid',
                DB::raw('COUNT(DISTINCT io.id) as n'),
            ]);

        if ($rows->isEmpty()) {
            return ['copyright_rows' => [], 'copyright_recorded' => false];
        }

        // Resolve the term labels in one batched lookup over the small id set.
        $labels = [];
        if ($hasTerm) {
            $ids = array_values(array_filter(
                $rows->pluck('csid')->all(),
                static fn ($v) => $v !== null
            ));
            if (! empty($ids)) {
                $labels = DB::table('term_i18n')
                    ->whereIn('id', $ids)
                    ->where('culture', 'en')
                    ->pluck('name', 'id')
                    ->all();
            }
        }

        $denom       = max(1, $withRights);
        $hasRecorded = false;
        $out         = [];
        foreach ($rows as $row) {
            $csid  = $row->csid;
            $count = (int) $row->n;

            if ($csid === null) {
                $label = __('(copyright status not recorded)');
            } else {
                $hasRecorded = true;
                $label       = $labels[$csid] ?? ('#' . $csid);
            }

            $out[] = [
                'label'    => $label,
                'count'    => $count,
                'pct'      => $this->pct($count, $denom),
                'is_unset' => $csid === null,
            ];
        }

        return ['copyright_rows' => $out, 'copyright_recorded' => $hasRecorded];
    }

    /**
     * ODRL policy coverage: how many records are governed by at least one ODRL
     * policy versus how many are open by default (no policy = open access, per
     * OdrlService's documented default), plus a breakdown by ODRL action verb
     * (use = viewing, reproduce = printing). Governance is counted as DISTINCT
     * target_id of policies whose target is an archival description, intersected
     * with real records so a stale policy on a deleted record is not counted. One
     * DISTINCT count for governance; one grouped aggregate for the action
     * breakdown. No per-row scan. Omitted when the policy table is absent.
     *
     * @return array<string,mixed>
     */
    private function odrlSection(int $total): array
    {
        if (! Schema::hasTable('research_rights_policy')) {
            return ['odrl_available' => false];
        }

        $policyTotal = (int) DB::table('research_rights_policy')->count();

        // Distinct real records governed by at least one policy. A policy targets
        // a record by (target_type IN archival-description senses, target_id).
        // Intersect with real information_object ids so a policy on a removed
        // record is not counted as governing a live one.
        $governed = (int) DB::table('research_rights_policy as p')
            ->whereIn('p.target_type', $this->recordTargetTypes())
            ->join('information_object as io', 'io.id', '=', 'p.target_id')
            ->where('io.id', '!=', self::ROOT_ID)
            ->where('io.parent_id', '!=', self::ROOT_ID)
            ->distinct()
            ->count('io.id');

        $governed    = min($governed, $total);
        $openDefault = max(0, $total - $governed);

        $coverageRows = [
            [
                'label' => __('Open access by default (no ODRL policy)'),
                'count' => $openDefault,
                'pct'   => $this->pct($openDefault, $total),
                'tone'  => 'success',
                'icon'  => 'unlock',
            ],
            [
                'label' => __('Governed by an ODRL policy'),
                'count' => $governed,
                'pct'   => $this->pct($governed, $total),
                'tone'  => 'info',
                'icon'  => 'shield-lock',
            ],
        ];

        return array_merge(
            [
                'odrl_available'     => true,
                'governed'           => $governed,
                'open_default'       => $openDefault,
                'governed_pct'       => $this->pct($governed, $total),
                'odrl_coverage_rows' => $coverageRows,
                'odrl_policy_total'  => $policyTotal,
            ],
            ['odrl_action_rows' => $this->odrlActionBreakdown()]
        );
    }

    /**
     * Breakdown of ODRL policies by action verb (use = viewing, reproduce =
     * printing, plus any other verb present such as distribute). One GROUP BY
     * action_type over the policy store, counting DISTINCT governed records per
     * action so the figure reads as "records governed for this action". Records
     * targeted only; a policy whose target is not a live record is excluded by the
     * same join used for governance. No per-row scan.
     *
     * @return array<int,array<string,mixed>>
     */
    private function odrlActionBreakdown(): array
    {
        $rows = DB::table('research_rights_policy as p')
            ->whereIn('p.target_type', $this->recordTargetTypes())
            ->join('information_object as io', 'io.id', '=', 'p.target_id')
            ->where('io.id', '!=', self::ROOT_ID)
            ->where('io.parent_id', '!=', self::ROOT_ID)
            ->groupBy('p.action_type')
            ->orderByDesc(DB::raw('COUNT(DISTINCT io.id)'))
            ->get([
                'p.action_type as action',
                DB::raw('COUNT(DISTINCT io.id) as n'),
            ]);

        $out = [];
        foreach ($rows as $row) {
            $action = strtolower(trim((string) ($row->action ?? '')));
            $out[]  = [
                'label' => $this->humaniseAction($action),
                'raw'   => $action,
                'count' => (int) $row->n,
                'icon'  => $this->actionIcon($action),
            ];
        }

        return $out;
    }

    /**
     * Target-type values on research_rights_policy that denote an archival
     * description (a catalogue record). OdrlPolicyMiddleware stores
     * 'archival_description'; the live data also carries 'collection' for a
     * record-rooted collection. Both resolve to a record by target_id, so both are
     * matched. Other target types (if any) are not records and are excluded.
     *
     * @return array<int,string>
     */
    private function recordTargetTypes(): array
    {
        return ['archival_description', 'collection', 'informationObject', 'information_object'];
    }

    /** Turn a stored ODRL action verb into a readable label. */
    private function humaniseAction(string $action): string
    {
        $map = [
            self::ODRL_ACTION_USE       => __('Use (viewing)'),
            self::ODRL_ACTION_REPRODUCE => __('Reproduce (printing)'),
            'distribute'                => __('Distribute'),
            'modify'                    => __('Modify'),
            'aggregate'                 => __('Aggregate'),
        ];

        if (isset($map[$action])) {
            return $map[$action];
        }

        return $action === '' ? __('(unspecified action)') : ucfirst($action);
    }

    /** A Bootstrap-icon name for a stored ODRL action verb. */
    private function actionIcon(string $action): string
    {
        return match ($action) {
            self::ODRL_ACTION_USE       => 'eye',
            self::ODRL_ACTION_REPRODUCE => 'printer',
            'distribute'                => 'share',
            'modify'                    => 'pencil',
            default                     => 'shield-lock',
        };
    }

    /** Total real records (the denominator for every share). */
    private function realTotal(): int
    {
        return (int) $this->realBase()->count();
    }

    /**
     * The real-record base query, aliased "io". Every metric builds on a fresh
     * copy so filters never leak between counts. Real records are every
     * information_object that is not the synthetic root and does not sit directly
     * under it, matching the sibling reports.
     */
    private function realBase(): \Illuminate\Database\Query\Builder
    {
        return DB::table('information_object as io')
            ->where('io.id', '!=', self::ROOT_ID)
            ->where('io.parent_id', '!=', self::ROOT_ID);
    }

    /** Percentage of $part over $whole, one decimal, guards divide-by-zero. */
    private function pct(int $part, int $whole): float
    {
        if ($whole <= 0) {
            return 0.0;
        }

        return round($part / $whole * 100, 1);
    }
}
