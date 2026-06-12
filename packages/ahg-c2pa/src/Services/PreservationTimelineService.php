<?php
/**
 * Heratio - public per-record PRESERVATION-TIMELINE consolidator (issue #1244,
 * building on the #1201 provenance epic).
 *
 * The honest, read-only read surface over the PREMIS-style preservation stores
 * owned by the (locked) ahg-preservation package. Where InferenceProvenanceService
 * answers "which AI inferences touched this record's metadata?" and
 * AuthenticityReportService answers "is this record's provenance chain signed?",
 * THIS service answers a third, distinct question:
 *
 *   "What is the recorded digital-preservation lifecycle of this published
 *    record's digital objects - ingest, fixity checks, format identification,
 *    migrations / normalisations, virus scans - in the order they happened,
 *    each with its outcome and the agent or tool responsible?"
 *
 * It is a pure READ-ONLY aggregator. It owns no preservation table, writes
 * nothing, runs no preservation action, and re-verifies nothing. ahg-preservation
 * remains the sole owner / writer of these tables (preservation_event,
 * preservation_fixity_check, preservation_object_format, preservation_virus_scan,
 * preservation_format_conversion). Every fact shown is something already recorded
 * there. If a store is absent on an older install, that source simply contributes
 * no events; the page degrades to the dignified "no preservation events recorded
 * yet" state rather than erroring.
 *
 * Honest framing is a hard requirement. The page never claims a file is "safe"
 * or "authentic" - only that the listed preservation steps were recorded, with
 * their recorded outcome and responsible agent. Absence of events is reported as
 * absence, never invented.
 *
 * Distinct from /inference-provenance (the AI-inference layer) and from
 * /authenticity (the C2PA content-credentials / signing layer). It links to both
 * so a reader can assemble the full trust picture, but it is its own lifecycle
 * view.
 *
 * Public contract: only PUBLISHED records are reportable (the same
 * status.type_id=158 / status_id=160 gate the public GLAM browse, the
 * authenticity report, and the inference explorer use; root id=1 is never a real
 * record). An unknown OR unpublished record resolves to null so the controller
 * returns a clean 404 (HTML and JSON). Never throws.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class PreservationTimelineService
{
    /** Publication status taxonomy: status.type_id for "publication status". */
    private const PUBLICATION_STATUS_TYPE_ID = 158;

    /** status.status_id value that means "Published" (same gate as GLAM browse). */
    private const PUBLISHED_STATUS_ID = 160;

    /** The AtoM/Qubit root information object - never a real, reportable record. */
    private const ROOT_OBJECT_ID = 1;

    /** Hard cap on rendered events so an absurdly busy record can never run away. */
    private const MAX_EVENTS = 500;

    /** PREMIS-style stores owned (and written) by the locked ahg-preservation package. */
    private const EVENT_TABLE      = 'preservation_event';
    private const FIXITY_TABLE     = 'preservation_fixity_check';
    private const FORMAT_TABLE     = 'preservation_object_format';
    private const VIRUS_TABLE      = 'preservation_virus_scan';
    private const CONVERSION_TABLE = 'preservation_format_conversion';

    /** Normalised lifecycle categories (presentation only - never overclaimed). */
    public const STAGE_INGEST     = 'ingest';
    public const STAGE_FIXITY     = 'fixity';
    public const STAGE_FORMAT     = 'format';
    public const STAGE_MIGRATION  = 'migration';
    public const STAGE_VIRUS      = 'virus';
    public const STAGE_OTHER      = 'other';

    /** Normalised outcomes (presentation only). */
    public const OUTCOME_SUCCESS = 'success';
    public const OUTCOME_WARNING = 'warning';
    public const OUTCOME_FAILURE = 'failure';
    public const OUTCOME_UNKNOWN = 'unknown';

    /**
     * Build the consolidated preservation timeline for a published record
     * addressed by numeric id or (possibly multi-segment) slug.
     *
     * Returns null when the record does not exist OR is not published (the
     * controller turns null into a 404, so an unpublished record is
     * indistinguishable from a missing one - the honest public contract).
     *
     * Returns a populated report (possibly with an EMPTY events list) for a
     * resolved, published record. The empty-events case is the dignified
     * "no preservation events recorded yet" state, NOT an error.
     *
     * Never throws: any reader fault degrades to the neutral empty report for a
     * record we DID resolve, or null on a hard resolve fault.
     *
     * @return array{
     *     object: object,
     *     events: list<array<string,mixed>>,
     *     by_stage: array<string,int>,
     *     counts: array{total:int,shown:int,success:int,warning:int,failure:int,stages:int,objects:int},
     *     stages_present: list<string>,
     *     truncated: bool,
     *     summary: string,
     *     timeline_url: string,
     *     timeline_json_url: string,
     *     authenticity_url: string,
     *     inference_url: string,
     *     generated_at: string
     * }|null
     */
    public function report(string $idOrSlug): ?array
    {
        $object = $this->resolvePublished($idOrSlug);
        if ($object === null) {
            return null;
        }

        $ioId = (int) $object->id;

        // Which digital objects belong to this information object? The per-file
        // preservation detail tables key on digital_object_id, so we map the IO
        // to its digital objects first. Empty array is fine (events from the
        // information-object-keyed preservation_event still show).
        $digitalObjectIds = $this->digitalObjectIdsFor($ioId);

        $events = $this->collectEvents($ioId, $digitalObjectIds);

        // Chronological: oldest first, the natural lifecycle reading order.
        usort($events, static function (array $a, array $b): int {
            $ta = $a['sort_key'] ?? 0;
            $tb = $b['sort_key'] ?? 0;
            if ($ta === $tb) {
                return strcmp((string) ($a['stage'] ?? ''), (string) ($b['stage'] ?? ''));
            }
            return $ta <=> $tb;
        });

        $total     = count($events);
        $truncated = $total > self::MAX_EVENTS;
        if ($truncated) {
            // Keep the EARLIEST events: the lifecycle reads most usefully from
            // ingest forward. The honest "more events exist" note is rendered.
            $events = array_slice($events, 0, self::MAX_EVENTS);
        }

        $counts   = $this->counts($events, $total, count($digitalObjectIds));
        $byStage  = $this->byStage($events);
        $stages   = array_keys($byStage);

        return [
            'object'            => $object,
            'events'            => $events,
            'by_stage'          => $byStage,
            'counts'            => $counts,
            'stages_present'    => $stages,
            'truncated'         => $truncated,
            'summary'           => $this->summary($counts),
            'timeline_url'      => $this->safeUrl('/preservation-timeline/' . ($object->slug ?: $ioId)),
            'timeline_json_url' => $this->safeUrl('/preservation-timeline/' . $ioId . '.json'),
            'authenticity_url'  => $this->safeUrl('/authenticity/' . ($object->slug ?: $ioId)),
            'inference_url'     => $this->safeUrl('/inference-provenance/' . ($object->slug ?: $ioId)),
            'generated_at'      => gmdate('Y-m-d\TH:i:s\Z'),
        ];
    }

    /* ----------------------------------------------------------------- *
     * Event collection - read-only across the preservation stores.
     * ----------------------------------------------------------------- */

    /**
     * Merge every preservation event for this record into one normalised list.
     * Each source is independently guarded: a missing table contributes nothing
     * and never aborts the merge.
     *
     * @param  list<int> $digitalObjectIds
     * @return list<array<string,mixed>>
     */
    private function collectEvents(int $ioId, array $digitalObjectIds): array
    {
        $events = [];

        foreach ($this->loadPremisEvents($ioId, $digitalObjectIds) as $e) {
            $events[] = $e;
        }
        foreach ($this->loadFixityChecks($digitalObjectIds) as $e) {
            $events[] = $e;
        }
        foreach ($this->loadFormatIdentifications($digitalObjectIds) as $e) {
            $events[] = $e;
        }
        foreach ($this->loadConversions($digitalObjectIds) as $e) {
            $events[] = $e;
        }
        foreach ($this->loadVirusScans($digitalObjectIds) as $e) {
            $events[] = $e;
        }

        return $events;
    }

    /**
     * The canonical PREMIS event log (preservation_event). Keyed primarily on the
     * information object; we also pick up rows linked only to one of this record's
     * digital objects. Pure read.
     *
     * @param  list<int> $digitalObjectIds
     * @return list<array<string,mixed>>
     */
    private function loadPremisEvents(int $ioId, array $digitalObjectIds): array
    {
        if (! $this->tableExists(self::EVENT_TABLE)) {
            return [];
        }

        try {
            $rows = DB::table(self::EVENT_TABLE)
                ->where(function ($q) use ($ioId, $digitalObjectIds) {
                    $q->where('information_object_id', $ioId);
                    if (! empty($digitalObjectIds)) {
                        $q->orWhereIn('digital_object_id', $digitalObjectIds);
                    }
                })
                ->orderBy('event_datetime')
                ->limit(self::MAX_EVENTS + 1)
                ->get();
        } catch (Throwable $e) {
            Log::warning('c2pa preservation-timeline: premis-event load failed', [
                'information_object_id' => $ioId,
                'err'                   => $e->getMessage(),
            ]);
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $stage   = $this->classifyEventType((string) ($r->event_type ?? ''));
            $outcome = $this->normaliseOutcome((string) ($r->event_outcome ?? ''));

            $out[] = $this->shape(
                stage:   $stage,
                label:   $this->humaniseEventType((string) ($r->event_type ?? '')),
                outcome: $outcome,
                when:    $r->event_datetime ?? null,
                agent:   $this->agentLabel($r->linking_agent_value ?? null, $r->linking_agent_type ?? null),
                detail:  $this->trimOrNull($r->event_outcome_detail ?? null) ?? $this->trimOrNull($r->event_detail ?? null),
                doId:    $r->digital_object_id ?? null,
                source:  __('Preservation event log'),
            );
        }

        return $out;
    }

    /**
     * Fixity (checksum) verifications (preservation_fixity_check). One row per
     * verification of one digital object. Pure read.
     *
     * @param  list<int> $digitalObjectIds
     * @return list<array<string,mixed>>
     */
    private function loadFixityChecks(array $digitalObjectIds): array
    {
        if (empty($digitalObjectIds) || ! $this->tableExists(self::FIXITY_TABLE)) {
            return [];
        }

        try {
            $rows = DB::table(self::FIXITY_TABLE)
                ->whereIn('digital_object_id', $digitalObjectIds)
                ->orderBy('checked_at')
                ->limit(self::MAX_EVENTS + 1)
                ->get();
        } catch (Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $outcome = $this->normaliseFixityStatus((string) ($r->status ?? ''));
            $algo    = strtoupper(trim((string) ($r->algorithm ?? '')));
            $detail  = $this->trimOrNull($r->error_message ?? null);
            if ($detail === null && $algo !== '') {
                $detail = __(':algo checksum verified', ['algo' => $algo]);
            }

            $out[] = $this->shape(
                stage:   self::STAGE_FIXITY,
                label:   $algo !== '' ? __('Fixity check (:algo)', ['algo' => $algo]) : __('Fixity check'),
                outcome: $outcome,
                when:    $r->checked_at ?? null,
                agent:   $this->agentLabel($r->checked_by ?? null, null),
                detail:  $detail,
                doId:    $r->digital_object_id ?? null,
                source:  __('Fixity-check log'),
            );
        }

        return $out;
    }

    /**
     * Format identifications (preservation_object_format): a file's recorded
     * format / PUID and the tool that identified it. Pure read.
     *
     * @param  list<int> $digitalObjectIds
     * @return list<array<string,mixed>>
     */
    private function loadFormatIdentifications(array $digitalObjectIds): array
    {
        if (empty($digitalObjectIds) || ! $this->tableExists(self::FORMAT_TABLE)) {
            return [];
        }

        try {
            $rows = DB::table(self::FORMAT_TABLE)
                ->whereIn('digital_object_id', $digitalObjectIds)
                ->orderBy('identification_date')
                ->limit(self::MAX_EVENTS + 1)
                ->get();
        } catch (Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $name = $this->trimOrNull($r->format_name ?? null);
            $ver  = $this->trimOrNull($r->format_version ?? null);
            $puid = $this->trimOrNull($r->puid ?? null);
            $mime = $this->trimOrNull($r->mime_type ?? null);
            $warn = $this->trimOrNull($r->warning ?? null);

            $label = $name !== null
                ? __('Format identified: :name', ['name' => $ver !== null ? $name . ' ' . $ver : $name])
                : __('Format identified');

            $bits = [];
            if ($puid !== null) { $bits[] = 'PUID ' . $puid; }
            if ($mime !== null) { $bits[] = $mime; }
            if ($warn !== null) { $bits[] = $warn; }

            $out[] = $this->shape(
                stage:   self::STAGE_FORMAT,
                label:   $label,
                outcome: $warn !== null ? self::OUTCOME_WARNING : self::OUTCOME_SUCCESS,
                when:    $r->identification_date ?? null,
                agent:   $this->agentLabel($r->identification_tool ?? null, null),
                detail:  empty($bits) ? null : implode('  ', $bits),
                doId:    $r->digital_object_id ?? null,
                source:  __('Format-identification log'),
            );
        }

        return $out;
    }

    /**
     * Migrations / normalisations (preservation_format_conversion): a file
     * converted from one format to a preservation target. Pure read.
     *
     * @param  list<int> $digitalObjectIds
     * @return list<array<string,mixed>>
     */
    private function loadConversions(array $digitalObjectIds): array
    {
        if (empty($digitalObjectIds) || ! $this->tableExists(self::CONVERSION_TABLE)) {
            return [];
        }

        try {
            $rows = DB::table(self::CONVERSION_TABLE)
                ->whereIn('digital_object_id', $digitalObjectIds)
                ->orderByRaw('COALESCE(completed_at, started_at, created_at)')
                ->limit(self::MAX_EVENTS + 1)
                ->get();
        } catch (Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $from = $this->trimOrNull($r->source_format ?? null) ?? $this->trimOrNull($r->source_mime_type ?? null);
            $to   = $this->trimOrNull($r->target_format ?? null) ?? $this->trimOrNull($r->target_mime_type ?? null);

            if ($from !== null && $to !== null) {
                $label = __('Migration: :from to :to', ['from' => $from, 'to' => $to]);
            } elseif ($to !== null) {
                $label = __('Migration to :to', ['to' => $to]);
            } else {
                $label = __('Format migration / normalisation');
            }

            $when = $r->completed_at ?? ($r->started_at ?? ($r->created_at ?? null));
            $tool = $this->trimOrNull($r->conversion_tool ?? null);
            $tver = $this->trimOrNull($r->tool_version ?? null);

            $out[] = $this->shape(
                stage:   self::STAGE_MIGRATION,
                label:   $label,
                outcome: $this->normaliseConversionStatus((string) ($r->status ?? '')),
                when:    $when,
                agent:   $this->agentLabel($tool !== null && $tver !== null ? $tool . ' ' . $tver : $tool, null),
                detail:  $this->trimOrNull($r->error_message ?? null),
                doId:    $r->digital_object_id ?? null,
                source:  __('Migration log'),
            );
        }

        return $out;
    }

    /**
     * Virus / malware scans (preservation_virus_scan). Pure read.
     *
     * @param  list<int> $digitalObjectIds
     * @return list<array<string,mixed>>
     */
    private function loadVirusScans(array $digitalObjectIds): array
    {
        if (empty($digitalObjectIds) || ! $this->tableExists(self::VIRUS_TABLE)) {
            return [];
        }

        try {
            $rows = DB::table(self::VIRUS_TABLE)
                ->whereIn('digital_object_id', $digitalObjectIds)
                ->orderBy('scanned_at')
                ->limit(self::MAX_EVENTS + 1)
                ->get();
        } catch (Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $engine = $this->trimOrNull($r->scan_engine ?? null);
            $threat = $this->trimOrNull($r->threat_name ?? null);
            $detail = $threat !== null
                ? __('Threat detected: :t', ['t' => $threat])
                : $this->trimOrNull($r->error_message ?? null);

            $out[] = $this->shape(
                stage:   self::STAGE_VIRUS,
                label:   $engine !== null ? __('Virus scan (:engine)', ['engine' => $engine]) : __('Virus scan'),
                outcome: $this->normaliseVirusStatus((string) ($r->status ?? ''), $threat),
                when:    $r->scanned_at ?? null,
                agent:   $this->agentLabel($engine, null),
                detail:  $detail,
                doId:    $r->digital_object_id ?? null,
                source:  __('Virus-scan log'),
            );
        }

        return $out;
    }

    /* ----------------------------------------------------------------- *
     * Shaping + classification - presentation only, no verification claims.
     * ----------------------------------------------------------------- */

    /**
     * Reduce one source row to the normalised public-safe event shape used by
     * the view and the JSON companion.
     *
     * @return array<string,mixed>
     */
    private function shape(
        string $stage,
        string $label,
        string $outcome,
        $when,
        ?string $agent,
        ?string $detail,
        $doId,
        string $source,
    ): array {
        return [
            'stage'         => $stage,
            'stage_label'   => $this->stageLabel($stage),
            'label'         => $label,
            'outcome'       => $outcome,
            'outcome_label' => $this->outcomeLabel($outcome),
            'when'          => $this->isoOrNull($when),
            'when_display'  => $this->displayDate($when),
            'agent'         => $agent,
            'detail'        => $detail,
            'object_id'     => ($doId !== null && (int) $doId > 0) ? (int) $doId : null,
            'source'        => $source,
            'sort_key'      => $this->sortKey($when),
        ];
    }

    /** Map a PREMIS event_type string to a normalised lifecycle stage. */
    private function classifyEventType(string $type): string
    {
        $t = strtolower(str_replace([' ', '-'], '_', trim($type)));

        return match (true) {
            $t === '' => self::STAGE_OTHER,
            str_contains($t, 'ingest') || str_contains($t, 'capture') || str_contains($t, 'accession') => self::STAGE_INGEST,
            str_contains($t, 'fixity') || str_contains($t, 'checksum') || str_contains($t, 'message_digest') => self::STAGE_FIXITY,
            str_contains($t, 'format_identif') || str_contains($t, 'formatidentif') || str_contains($t, 'identification') => self::STAGE_FORMAT,
            str_contains($t, 'normal') || str_contains($t, 'migrat') || str_contains($t, 'conversion') || str_contains($t, 'transform') => self::STAGE_MIGRATION,
            str_contains($t, 'virus') || str_contains($t, 'malware') || str_contains($t, 'scan') => self::STAGE_VIRUS,
            default => self::STAGE_OTHER,
        };
    }

    private function humaniseEventType(string $type): string
    {
        $map = [
            'ingestion'             => __('Ingest'),
            'ingest'                => __('Ingest'),
            'fixitycheck'           => __('Fixity check'),
            'fixity_check'          => __('Fixity check'),
            'formatidentification'  => __('Format identification'),
            'format_identification' => __('Format identification'),
            'normalization'         => __('Normalisation'),
            'normalisation'         => __('Normalisation'),
            'migration'             => __('Migration'),
            'virus_check'           => __('Virus scan'),
            'viruscheck'            => __('Virus scan'),
            'virus_scan'            => __('Virus scan'),
            'file_missing'          => __('File reported missing'),
            'replication'           => __('Replication'),
        ];

        $key = strtolower(str_replace([' ', '-'], '_', trim($type)));
        if (isset($map[$key])) {
            return $map[$key];
        }
        $key2 = str_replace('_', '', $key);
        if (isset($map[$key2])) {
            return $map[$key2];
        }

        return $type === '' ? __('Preservation event') : ucfirst(strtolower(str_replace('_', ' ', $type)));
    }

    private function stageLabel(string $stage): string
    {
        return match ($stage) {
            self::STAGE_INGEST    => __('Ingest'),
            self::STAGE_FIXITY    => __('Fixity'),
            self::STAGE_FORMAT    => __('Format identification'),
            self::STAGE_MIGRATION => __('Migration / normalisation'),
            self::STAGE_VIRUS     => __('Virus scan'),
            default               => __('Other'),
        };
    }

    private function outcomeLabel(string $outcome): string
    {
        return match ($outcome) {
            self::OUTCOME_SUCCESS => __('Success'),
            self::OUTCOME_WARNING => __('Warning'),
            self::OUTCOME_FAILURE => __('Failure'),
            default               => __('Recorded'),
        };
    }

    private function normaliseOutcome(string $outcome): string
    {
        $o = strtolower(trim($outcome));

        return match (true) {
            $o === '' || $o === 'unknown' => self::OUTCOME_UNKNOWN,
            in_array($o, ['success', 'ok', 'pass', 'passed', 'valid', 'clean'], true) => self::OUTCOME_SUCCESS,
            in_array($o, ['warning', 'warn'], true) => self::OUTCOME_WARNING,
            in_array($o, ['failure', 'failed', 'fail', 'error', 'invalid', 'mismatch'], true) => self::OUTCOME_FAILURE,
            default => self::OUTCOME_UNKNOWN,
        };
    }

    private function normaliseFixityStatus(string $status): string
    {
        $s = strtolower(trim($status));

        return match (true) {
            in_array($s, ['pass', 'passed', 'ok', 'valid', 'match'], true) => self::OUTCOME_SUCCESS,
            in_array($s, ['warning', 'warn'], true) => self::OUTCOME_WARNING,
            in_array($s, ['fail', 'failed', 'mismatch', 'invalid', 'error'], true) => self::OUTCOME_FAILURE,
            default => self::OUTCOME_UNKNOWN,
        };
    }

    private function normaliseConversionStatus(string $status): string
    {
        $s = strtolower(trim($status));

        return match (true) {
            in_array($s, ['success', 'completed', 'complete', 'done', 'ok'], true) => self::OUTCOME_SUCCESS,
            in_array($s, ['warning', 'warn', 'partial'], true) => self::OUTCOME_WARNING,
            in_array($s, ['failed', 'failure', 'error'], true) => self::OUTCOME_FAILURE,
            default => self::OUTCOME_UNKNOWN, // pending / running etc.
        };
    }

    private function normaliseVirusStatus(string $status, ?string $threat): string
    {
        if ($threat !== null && trim($threat) !== '') {
            return self::OUTCOME_FAILURE;
        }
        $s = strtolower(trim($status));

        return match (true) {
            in_array($s, ['clean', 'ok', 'pass', 'passed', 'no_threat'], true) => self::OUTCOME_SUCCESS,
            in_array($s, ['warning', 'warn'], true) => self::OUTCOME_WARNING,
            in_array($s, ['infected', 'threat', 'failed', 'failure'], true) => self::OUTCOME_FAILURE,
            $s === 'error' => self::OUTCOME_WARNING, // scan could not complete - not a detection
            default => self::OUTCOME_UNKNOWN,
        };
    }

    /**
     * A trustworthy agent / tool label. We surface the recorded agent value
     * verbatim (it is an internal tool or process name like "heratio-preservation"
     * or "clamav", never a URL), falling back to the honest automated-process
     * wording when nothing is recorded.
     */
    private function agentLabel($value, $type): ?string
    {
        $v = $this->trimOrNull(is_scalar($value) ? (string) $value : null);
        if ($v === null) {
            $t = $this->trimOrNull(is_scalar($type) ? (string) $type : null);
            return $t === null || strtolower($t) === 'system'
                ? __('automated preservation process')
                : $t;
        }

        return $v;
    }

    /* ----------------------------------------------------------------- *
     * Counts + summary - derived from the merged events, never assumed.
     * ----------------------------------------------------------------- */

    /**
     * @param  list<array<string,mixed>> $events
     * @return array{total:int,shown:int,success:int,warning:int,failure:int,stages:int,objects:int}
     */
    private function counts(array $events, int $total, int $objectCount): array
    {
        $success = $warning = $failure = 0;
        $stages  = [];

        foreach ($events as $e) {
            $stages[$e['stage']] = true;
            switch ($e['outcome']) {
                case self::OUTCOME_SUCCESS: $success++; break;
                case self::OUTCOME_WARNING: $warning++; break;
                case self::OUTCOME_FAILURE: $failure++; break;
            }
        }

        return [
            'total'   => $total,
            'shown'   => count($events),
            'success' => $success,
            'warning' => $warning,
            'failure' => $failure,
            'stages'  => count($stages),
            'objects' => $objectCount,
        ];
    }

    /**
     * Event count grouped by humanised stage label, busiest first.
     *
     * @param  list<array<string,mixed>> $events
     * @return array<string,int>
     */
    private function byStage(array $events): array
    {
        $out = [];
        foreach ($events as $e) {
            $key = $e['stage_label'];
            $out[$key] = ($out[$key] ?? 0) + 1;
        }
        arsort($out);

        return $out;
    }

    /**
     * One honest plain-language sentence. Never claims a file is safe; states only
     * what preservation steps were recorded and how they turned out.
     *
     * @param  array{total:int,shown:int,success:int,warning:int,failure:int,stages:int,objects:int} $counts
     */
    private function summary(array $counts): string
    {
        if ($counts['total'] === 0) {
            return __('No preservation events have been recorded for this record yet. That does not mean anything is wrong - only that no ingest, fixity, format-identification, migration, or virus-scan step is on file for its digital objects.');
        }

        $pieces = [];
        $pieces[] = trans_choice(
            '{1}:total preservation event is recorded for this record|[2,*]:total preservation events are recorded for this record',
            $counts['total'],
            ['total' => $counts['total']]
        );

        if ($counts['stages'] > 0) {
            $pieces[] = trans_choice(
                '{1}across :n lifecycle stage|[2,*]across :n lifecycle stages',
                $counts['stages'],
                ['n' => $counts['stages']]
            );
        }

        $tail = $counts['failure'] > 0
            ? __('At least one recorded step reported a failure, shown in the timeline below; this is the recorded preservation history, not a verdict on the source itself.')
            : __('Every recorded step is shown below with its recorded outcome and responsible agent; this is the recorded preservation history, not a verdict on the source itself.');

        return implode(', ', $pieces) . '. ' . $tail;
    }

    /* ----------------------------------------------------------------- *
     * Record resolution + published gate (mirrors the sibling services).
     * ----------------------------------------------------------------- */

    /**
     * The digital_object ids belonging to this information object. digital_object
     * links to its record via digital_object.object_id. Read-only, bounded.
     *
     * @return list<int>
     */
    private function digitalObjectIdsFor(int $ioId): array
    {
        if (! $this->tableExists('digital_object')) {
            return [];
        }

        try {
            return DB::table('digital_object')
                ->where('object_id', $ioId)
                ->limit(self::MAX_EVENTS)
                ->pluck('id')
                ->map(static fn ($v) => (int) $v)
                ->filter(static fn (int $v) => $v > 0)
                ->values()
                ->all();
        } catch (Throwable $e) {
            return [];
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Resolve a PUBLISHED information object by numeric id or (possibly
     * multi-segment) slug. Returns null when the row is absent, the root object,
     * OR not published, so the public surface cannot leak a draft/embargoed
     * record. Never throws.
     */
    private function resolvePublished(string $idOrSlug): ?object
    {
        try {
            if (! $this->tableExists('information_object')) {
                return null;
            }

            $id = $this->resolveId($idOrSlug);
            if ($id === null || $id === self::ROOT_OBJECT_ID) {
                return null;
            }

            if (! $this->isPublished($id)) {
                return null;
            }

            return $this->loadObject($id);
        } catch (Throwable $e) {
            Log::warning('c2pa preservation-timeline: resolvePublished failed', [
                'reference' => $idOrSlug,
                'err'       => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Turn a numeric id or slug into an information_object id, or null. A purely
     * numeric reference is treated as an id; otherwise it is looked up in the
     * slug table (trimmed of surrounding slashes for multi-segment slugs).
     */
    private function resolveId(string $idOrSlug): ?int
    {
        $ref = trim($idOrSlug, '/');
        if ($ref === '') {
            return null;
        }

        if (ctype_digit($ref)) {
            $id = (int) $ref;

            return $id > 0 ? $id : null;
        }

        if (! $this->tableExists('slug')) {
            return null;
        }

        $row = DB::table('slug')->where('slug', $ref)->first(['object_id']);
        if ($row === null || ! isset($row->object_id)) {
            return null;
        }

        $id = (int) $row->object_id;

        return $id > 0 ? $id : null;
    }

    /**
     * The published gate: a published row has a status entry with the
     * publication type (158) and the Published status id (160). Identical
     * contract to the public GLAM browse, the authenticity report, and the
     * inference explorer.
     */
    private function isPublished(int $informationObjectId): bool
    {
        if (! $this->tableExists('status')) {
            return false;
        }

        return DB::table('status')
            ->where('object_id', $informationObjectId)
            ->where('type_id', self::PUBLICATION_STATUS_TYPE_ID)
            ->where('status_id', self::PUBLISHED_STATUS_ID)
            ->exists();
    }

    /**
     * Public-safe identity of the record: id, identifier, title (en-preferred),
     * slug. Returns null when the row vanished between the gate and the load.
     */
    private function loadObject(int $informationObjectId): ?object
    {
        $io = DB::table('information_object')
            ->where('id', $informationObjectId)
            ->first(['id', 'identifier']);
        if ($io === null) {
            return null;
        }

        $io->title = null;
        $io->slug  = null;

        if ($this->tableExists('information_object_i18n')) {
            $i18n = DB::table('information_object_i18n')
                ->where('id', $informationObjectId)
                ->orderByRaw("culture = 'en' DESC")
                ->first(['title']);
            $io->title = $i18n->title ?? null;
        }
        if ($this->tableExists('slug')) {
            $slug = DB::table('slug')->where('object_id', $informationObjectId)->first(['slug']);
            $io->slug = $slug->slug ?? null;
        }

        return $io;
    }

    /* ----------------------------------------------------------------- *
     * Small value helpers.
     * ----------------------------------------------------------------- */

    private function isoOrNull($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            $ts = strtotime((string) $value);
            return $ts === false ? null : gmdate('Y-m-d\TH:i:s\Z', $ts);
        } catch (Throwable $e) {
            return null;
        }
    }

    private function displayDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $ts = strtotime((string) $value);

        return $ts === false ? null : gmdate('Y-m-d H:i', $ts);
    }

    private function sortKey($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        $ts = strtotime((string) $value);

        return $ts === false ? 0 : $ts;
    }

    private function trimOrNull($value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $v = trim($value);

        return $v === '' ? null : $v;
    }

    private function safeUrl(string $path): string
    {
        if (function_exists('url')) {
            try {
                return (string) url($path);
            } catch (Throwable) {
                // fall through to the bare path
            }
        }

        return $path;
    }
}
