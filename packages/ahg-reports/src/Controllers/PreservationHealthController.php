<?php

/**
 * PreservationHealthController - Controller for Heratio
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
 * Preservation Health report - a read-only, operator-facing view of the
 * operational state of the digital collection's integrity. It surfaces what
 * needs attention: objects whose fixity check failed or that have never been
 * checked; objects flagged as having a missing file; objects with no format
 * identification (PUID); and objects flagged by a virus scan. It closes with a
 * short list of the most recent preservation failures and warnings.
 *
 * This is deliberately DISTINCT from its report siblings:
 *   - the data-quality report measures descriptive (ISAD(G)) completeness;
 *   - the AI-usage report measures where AI assisted;
 *   - the catalogue-growth report measures size, growth and composition;
 *   - THIS report measures preservation integrity (what needs attention).
 *
 * Source of truth and alignment
 * ------------------------------
 * Every metric reads the canonical preservation stores OWNED BY the locked
 * ahg-preservation package. This report writes to NONE of them and runs no
 * ALTER. The integrity metric reads `preservation_fixity_check` exactly the way
 * ahg-core's FixityService reads it - the LATEST check per object (MAX(id) per
 * digital_object_id) so re-checks are not double-counted - so the two surfaces
 * agree rather than diverging. The denominator throughout is `digital_object`.
 *
 * The event-type vocabulary on this store carries BOTH a snake_case and a
 * camelCase spelling for the same concept (fixity_check / fixityCheck,
 * format_identification / formatIdentification, plus file_missing, virus_check),
 * verified by GROUP BY. Each metric therefore matches a SET of spellings rather
 * than a single literal, so neither spelling is silently missed.
 *
 * Defensiveness
 * -------------
 * Every metric is a single grouped/aggregate COUNT (or a bounded LIMITed recent
 * list) over existing tables - never a per-row PHP scan. Every probe is
 * Schema::hasTable guarded and the whole build is wrapped in try/catch, so a
 * fresh install or a missing table degrades to a calm empty state and never
 * 500s. The report counts and surfaces; it changes nothing.
 */
class PreservationHealthController extends Controller
{
    /** Canonical fixity results store (owned by ahg-preservation). Read-only. */
    private const FIXITY_TABLE = 'preservation_fixity_check';

    /** Canonical preservation-event log (owned by ahg-preservation). Read-only. */
    private const EVENT_TABLE = 'preservation_event';

    /** Canonical format-identification store (owned by ahg-preservation). Read-only. */
    private const FORMAT_TABLE = 'preservation_object_format';

    /** Canonical virus-scan log (owned by ahg-preservation). Read-only. */
    private const VIRUS_TABLE = 'preservation_virus_scan';

    /** The denominator: every digital object that could be preserved. */
    private const OBJECT_TABLE = 'digital_object';

    /** event_type spellings that mean "this file is missing". */
    private const EVENT_FILE_MISSING = ['file_missing', 'fileMissing'];

    /** Fixity status values that count as a failure. */
    private const FIXITY_FAIL_STATUSES = ['fail', 'failed', 'failure', 'mismatch', 'error'];

    /** Virus-scan status values that count as clean. */
    private const VIRUS_CLEAN_STATUSES = ['clean', 'ok', 'pass', 'passed', 'no_threat'];

    /** How many rows the recent-failures / sample lists show at most. */
    private const RECENT_LIMIT = 12;

    public function index(): View
    {
        return view('ahg-reports::preservation-health.index', $this->buildReport());
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
            'has_activity'        => false,
            'total_objects'       => 0,
            // Integrity (fixity)
            'fixity_available'    => false,
            'fixity_pass'         => 0,
            'fixity_fail'         => 0,
            'fixity_checked'      => 0,
            'fixity_unchecked'    => 0,
            'fixity_pass_pct'     => 0.0,
            'fixity_rows'         => [],
            // Missing files
            'events_available'    => false,
            'missing_files'       => 0,
            'missing_pct'         => 0.0,
            'missing_rows'        => [],
            // Format identification
            'format_available'    => false,
            'format_identified'   => 0,
            'format_unidentified' => 0,
            'format_pct'          => 0.0,
            'format_rows'         => [],
            // Virus scan
            'virus_available'     => false,
            'virus_clean'         => 0,
            'virus_flagged'       => 0,
            'virus_scanned'       => 0,
            'virus_clean_pct'     => 0.0,
            'virus_rows'          => [],
            // Recent failures / warnings
            'recent'              => [],
        ];

        try {
            if (! Schema::hasTable(self::OBJECT_TABLE)) {
                return $empty;
            }

            $total = (int) DB::table(self::OBJECT_TABLE)->count();

            $report = array_merge($empty, [
                'available'     => true,
                'total_objects' => $total,
            ]);

            $report = array_merge($report, $this->integritySection($total));
            $report = array_merge($report, $this->missingSection($total));
            $report = array_merge($report, $this->formatSection($total));
            $report = array_merge($report, $this->virusSection($total));
            $report['recent'] = $this->recentFailures();

            // If literally no preservation activity exists anywhere, the view
            // still has 'available' => true and frames it as a calm "no
            // preservation data yet" rather than an error.
            $report['has_activity'] =
                $report['fixity_checked'] > 0
                || $report['missing_files'] > 0
                || $report['format_identified'] > 0
                || ! empty($report['recent'])
                || ($report['virus_clean'] + $report['virus_flagged']) > 0;

            return $report;
        } catch (\Throwable $e) {
            // Absent column, missing table, locked table, driver error - none of
            // these should ever break the report. Degrade to empty state.
            return $empty;
        }
    }

    /**
     * Integrity from the canonical fixity store. Aligned with ahg-core's
     * FixityService: the LATEST check per object (MAX(id) per digital_object_id)
     * decides pass vs fail so re-checks are not double-counted. "Never checked"
     * is the denominator (every digital object) minus the distinct set of
     * checked objects. A single grouped aggregate plus one DISTINCT count - no
     * per-row scan. Returns the integrity slice of the report array.
     *
     * @return array<string,mixed>
     */
    private function integritySection(int $total): array
    {
        if (! Schema::hasTable(self::FIXITY_TABLE)) {
            return ['fixity_available' => false];
        }

        $t = self::FIXITY_TABLE;

        // Latest check id per object.
        $latest = DB::table($t)
            ->select('digital_object_id', DB::raw('MAX(id) as max_id'))
            ->groupBy('digital_object_id');

        // Roll up the latest-per-object statuses.
        $statusRows = DB::table($t . ' as l')
            ->joinSub($latest, 'lt', fn ($j) => $j->on('l.id', '=', 'lt.max_id'))
            ->select('l.status', DB::raw('COUNT(*) as n'))
            ->groupBy('l.status')
            ->get();

        $pass = 0;
        $fail = 0;
        foreach ($statusRows as $r) {
            $status = strtolower(trim((string) ($r->status ?? '')));
            $n      = (int) $r->n;
            if (in_array($status, self::FIXITY_FAIL_STATUSES, true)) {
                $fail += $n;
            } else {
                // Anything not a known failure (pass / passed / verified / ...)
                // is treated as a successful verification.
                $pass += $n;
            }
        }

        $checked   = $pass + $fail;
        $unchecked = max(0, $total - $this->distinctCheckedObjects($t));

        // A small breakdown for the bars: pass / fail / never checked.
        $rows = [
            [
                'label' => __('Passed (latest check)'),
                'count' => $pass,
                'pct'   => $this->pct($pass, max(1, $total)),
                'tone'  => 'success',
                'icon'  => 'shield-check',
            ],
            [
                'label' => __('Failed (latest check)'),
                'count' => $fail,
                'pct'   => $this->pct($fail, max(1, $total)),
                'tone'  => 'danger',
                'icon'  => 'shield-exclamation',
            ],
            [
                'label' => __('Never fixity-checked'),
                'count' => $unchecked,
                'pct'   => $this->pct($unchecked, max(1, $total)),
                'tone'  => 'secondary',
                'icon'  => 'question-circle',
            ],
        ];

        return [
            'fixity_available' => true,
            'fixity_pass'      => $pass,
            'fixity_fail'      => $fail,
            'fixity_checked'   => $checked,
            'fixity_unchecked' => $unchecked,
            'fixity_pass_pct'  => $this->pct($pass, max(1, $checked)),
            'fixity_rows'      => $rows,
        ];
    }

    /** Distinct digital objects that have at least one fixity check on record. */
    private function distinctCheckedObjects(string $table): int
    {
        return (int) DB::table($table)
            ->whereNotNull('digital_object_id')
            ->distinct()
            ->count('digital_object_id');
    }

    /**
     * Missing-file count from the preservation-event log: the number of objects
     * that have a "file_missing" event on record. Framed as "needs attention".
     * A single DISTINCT count over the event log - no per-row scan. Returns the
     * missing slice of the report array.
     *
     * @return array<string,mixed>
     */
    private function missingSection(int $total): array
    {
        if (! Schema::hasTable(self::EVENT_TABLE)) {
            return ['events_available' => false];
        }

        $t = self::EVENT_TABLE;

        // Distinct objects with a file_missing event (either spelling). A
        // file_missing event is the flag; one per object is what we count.
        $missing = (int) DB::table($t)
            ->whereIn('event_type', self::EVENT_FILE_MISSING)
            ->whereNotNull('digital_object_id')
            ->distinct()
            ->count('digital_object_id');

        // A tiny "needs attention" sample: the most recent file_missing events,
        // newest first, for the operator to follow up. Bounded LIMIT.
        $rows = DB::table($t)
            ->whereIn('event_type', self::EVENT_FILE_MISSING)
            ->orderByDesc('event_datetime')
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT)
            ->get(['digital_object_id', 'information_object_id', 'event_datetime', 'event_outcome_detail', 'event_detail'])
            ->map(fn ($r) => [
                'digital_object_id'     => (int) ($r->digital_object_id ?? 0),
                'information_object_id' => (int) ($r->information_object_id ?? 0),
                'when'                  => (string) ($r->event_datetime ?? ''),
                'detail'                => trim((string) ($r->event_outcome_detail ?? $r->event_detail ?? '')),
            ])
            ->all();

        return [
            'events_available' => true,
            'missing_files'    => $missing,
            'missing_pct'      => $this->pct($missing, max(1, $total)),
            'missing_rows'     => $rows,
        ];
    }

    /**
     * Format-identification coverage: how many digital objects have a format
     * record (a PUID / format name) versus how many do not. A single DISTINCT
     * count of identified objects against the digital_object denominator. An
     * object is "identified" when it has at least one preservation_object_format
     * row carrying a non-empty PUID or format name. Returns the format slice.
     *
     * @return array<string,mixed>
     */
    private function formatSection(int $total): array
    {
        if (! Schema::hasTable(self::FORMAT_TABLE)) {
            return ['format_available' => false];
        }

        $t = self::FORMAT_TABLE;

        $identified = (int) DB::table($t)
            ->whereNotNull('digital_object_id')
            ->where(function ($q) {
                $q->where(function ($qq) {
                    $qq->whereNotNull('puid')->where('puid', '<>', '');
                })->orWhere(function ($qq) {
                    $qq->whereNotNull('format_name')->where('format_name', '<>', '');
                });
            })
            ->distinct()
            ->count('digital_object_id');

        $identified   = min($identified, $total);
        $unidentified = max(0, $total - $identified);

        $rows = [
            [
                'label' => __('Format identified (has a PUID or format name)'),
                'count' => $identified,
                'pct'   => $this->pct($identified, max(1, $total)),
                'tone'  => 'success',
                'icon'  => 'file-earmark-check',
            ],
            [
                'label' => __('Not yet identified'),
                'count' => $unidentified,
                'pct'   => $this->pct($unidentified, max(1, $total)),
                'tone'  => 'warning',
                'icon'  => 'file-earmark-x',
            ],
        ];

        return [
            'format_available'    => true,
            'format_identified'   => $identified,
            'format_unidentified' => $unidentified,
            'format_pct'          => $this->pct($identified, max(1, $total)),
            'format_rows'         => $rows,
        ];
    }

    /**
     * Virus-scan posture: clean versus flagged. An object is "flagged" when its
     * most recent virus scan recorded a threat (a non-empty threat_name) or a
     * non-clean status. A single grouped aggregate over the latest scan per
     * object - no per-row scan. Returns the virus slice. Omitted entirely (the
     * view hides the card) when the table is absent.
     *
     * @return array<string,mixed>
     */
    private function virusSection(int $total): array
    {
        if (! Schema::hasTable(self::VIRUS_TABLE)) {
            return ['virus_available' => false];
        }

        $t = self::VIRUS_TABLE;

        // Latest scan id per object.
        $latest = DB::table($t)
            ->select('digital_object_id', DB::raw('MAX(id) as max_id'))
            ->groupBy('digital_object_id');

        $rows = DB::table($t . ' as v')
            ->joinSub($latest, 'lt', fn ($j) => $j->on('v.id', '=', 'lt.max_id'))
            ->select('v.status', 'v.threat_name', DB::raw('COUNT(*) as n'))
            ->groupBy('v.status', 'v.threat_name')
            ->get();

        $clean   = 0;
        $flagged = 0;
        foreach ($rows as $r) {
            $status = strtolower(trim((string) ($r->status ?? '')));
            $threat = trim((string) ($r->threat_name ?? ''));
            $n      = (int) $r->n;

            // A recorded threat name, or a status that is not a known-clean
            // value, is a flag. A bare "error" status (scan could not complete)
            // is NOT an infection but is also not clean - it falls through to
            // "flagged" so the operator notices it needs a re-scan.
            if ($threat !== '') {
                $flagged += $n;
            } elseif (in_array($status, self::VIRUS_CLEAN_STATUSES, true)) {
                $clean += $n;
            } else {
                $flagged += $n;
            }
        }

        $scanned = $clean + $flagged;

        $bars = [
            [
                'label' => __('Clean (latest scan)'),
                'count' => $clean,
                'pct'   => $this->pct($clean, max(1, $scanned)),
                'tone'  => 'success',
                'icon'  => 'shield-check',
            ],
            [
                'label' => __('Flagged or not confirmed clean'),
                'count' => $flagged,
                'pct'   => $this->pct($flagged, max(1, $scanned)),
                'tone'  => 'danger',
                'icon'  => 'bug',
            ],
        ];

        return [
            'virus_available' => true,
            'virus_clean'     => $clean,
            'virus_flagged'   => $flagged,
            'virus_scanned'   => $scanned,
            'virus_clean_pct' => $this->pct($clean, max(1, $scanned)),
            'virus_rows'      => $bars,
        ];
    }

    /**
     * The most recent preservation failures and warnings across the whole event
     * log: the newest N preservation_event rows whose outcome is failure or
     * warning, each with its type, when, the affected digital object, and a
     * short detail. A single ORDER BY + LIMIT - no per-row scan. Returns [] when
     * the table is absent or there is nothing to show.
     *
     * @return array<int,array<string,mixed>>
     */
    private function recentFailures(): array
    {
        if (! Schema::hasTable(self::EVENT_TABLE)) {
            return [];
        }

        return DB::table(self::EVENT_TABLE)
            ->whereIn('event_outcome', ['failure', 'warning'])
            ->orderByDesc('event_datetime')
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT)
            ->get([
                'event_type',
                'event_outcome',
                'event_datetime',
                'digital_object_id',
                'information_object_id',
                'event_outcome_detail',
                'event_detail',
            ])
            ->map(fn ($r) => [
                'type'                  => $this->humaniseEventType((string) ($r->event_type ?? '')),
                'outcome'               => strtolower(trim((string) ($r->event_outcome ?? ''))),
                'when'                  => (string) ($r->event_datetime ?? ''),
                'digital_object_id'     => (int) ($r->digital_object_id ?? 0),
                'information_object_id' => (int) ($r->information_object_id ?? 0),
                'detail'                => trim((string) ($r->event_outcome_detail ?? $r->event_detail ?? '')),
            ])
            ->all();
    }

    /**
     * Turn a stored event_type (either spelling) into a readable label. Maps the
     * camelCase/snake_case pairs to one human form; falls back to a title-cased
     * version of the raw token for anything unrecognised.
     */
    private function humaniseEventType(string $raw): string
    {
        // Normalise camelCase to snake_case, then lower-case, for lookup.
        $key = strtolower((string) preg_replace('/(?<!^)([A-Z])/', '_$1', $raw));

        $map = [
            'fixity_check'          => __('Fixity check'),
            'file_missing'          => __('Missing file'),
            'format_identification' => __('Format identification'),
            'virus_check'           => __('Virus scan'),
            'ingestion'             => __('Ingestion'),
            'normalization'         => __('Normalisation'),
        ];

        if (isset($map[$key])) {
            return $map[$key];
        }

        $label = trim(str_replace('_', ' ', $key));
        return $label === '' ? __('Preservation event') : ucfirst($label);
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
