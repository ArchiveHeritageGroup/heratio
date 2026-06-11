<?php
/**
 * Heratio - record-level provenance trace (provenance roadmap trace-endpoint
 * slice, building on issues #1201 and #1209).
 *
 * Aggregates the content-credentials provenance of EVERY digital object that
 * belongs to one archival record (information object) into a single,
 * time-ordered trace of events - capture / digitisation, edits, AI-inference
 * steps and signature / verification status - and reduces it to one
 * record-level authenticity summary (verified / partially / unsigned /
 * invalid). It answers the "show me everything that ever happened to this"
 * question for an entire record, not just one file.
 *
 * It is a pure aggregator: it reuses ProvenanceRecordService for every read +
 * verification (listForObject / listForDigitalObject / verifyRecord) and never
 * re-reads a manifest off disk, never shells out to c2patool, and never
 * reimplements signing or verification. Every path is resilient: an unknown
 * record returns a null-object trace the caller can 404 on; a record with no
 * digital objects or no provenance returns a dignified empty trace (NOT an
 * error); any reader fault degrades to the empty/neutral state and is logged.
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

/**
 * The record-level trace builder. Distinct from ProvenanceRecordService (which
 * owns the table + signing + per-record verification) and from
 * VerifyObjectController (which collapses ONE digital object to a verdict):
 * this service fans out across all of a record's digital objects, flattens
 * their provenance into one chronological trace, and computes the whole-record
 * authenticity summary.
 */
final class ProvenanceTraceService
{
    /** Whole-record authenticity verdicts. */
    public const SUMMARY_VERIFIED  = 'verified';   // every signed entry verifies; at least one is signed
    public const SUMMARY_PARTIAL   = 'partially';  // some entries verify, others are unsigned (no failures)
    public const SUMMARY_UNSIGNED  = 'unsigned';   // provenance exists but nothing is signed
    public const SUMMARY_INVALID   = 'invalid';    // at least one signed entry failed verification (tampered)
    public const SUMMARY_NONE      = 'none';        // no provenance recorded for this record at all

    /**
     * Per-digital-object roll-up states (mirror the three human-facing states
     * of VerifyObjectController, kept local so this service does not depend on
     * that controller's private constants).
     */
    public const STATE_VERIFIED = 'verified';
    public const STATE_INVALID  = 'invalid';
    public const STATE_ABSENT   = 'absent';

    /** Per-event types in the flattened trace. */
    public const TYPE_CAPTURE   = 'capture';
    public const TYPE_EDIT      = 'edit';
    public const TYPE_AI        = 'ai-inference';
    public const TYPE_SIGNATURE = 'signature';

    /** digital_object.usage_id for a master file (taxonomy 47). */
    private const USAGE_MASTER = 140;

    public function __construct(private ProvenanceRecordService $service)
    {
    }

    /**
     * Build the full record-level trace for one information object.
     *
     * Always returns a well-formed array. When the record is unknown the
     * returned 'object' is null (the caller decides to 404). When the record
     * exists but has no digital objects or no provenance, 'events' is empty and
     * 'summary' is SUMMARY_NONE - a dignified "no provenance recorded yet"
     * state, never an error.
     *
     * @return array{
     *     object: object|null,
     *     summary: string,
     *     summary_reason: string,
     *     counts: array{
     *         digital_objects: int,
     *         records: int,
     *         signed: int,
     *         verified: int,
     *         invalid: int,
     *         events: int,
     *         captures: int,
     *         edits: int,
     *         ai: int
     *     },
     *     events: list<array<string,mixed>>,
     *     groups: list<array<string,mixed>>,
     *     generated_at: string
     * }
     */
    public function trace(int $informationObjectId): array
    {
        $object = $this->loadObject($informationObjectId);
        if ($object === null) {
            return $this->emptyTrace(null, self::SUMMARY_NONE, 'Record not found.');
        }

        try {
            $digitalObjects = $this->digitalObjectsFor($informationObjectId);
        } catch (Throwable $e) {
            Log::warning('c2pa trace: digital-object lookup failed; empty trace', [
                'information_object_id' => $informationObjectId,
                'err'                   => $e->getMessage(),
            ]);

            return $this->emptyTrace($object, self::SUMMARY_NONE, 'No provenance recorded yet.');
        }

        $events   = [];
        $groups   = [];
        $signed   = 0;
        $verified = 0;
        $invalid  = 0;
        $records  = 0;

        foreach ($digitalObjects as $do) {
            $group = $this->traceForDigitalObject($object, $do);

            $records  += $group['counts']['records'];
            $signed   += $group['counts']['signed'];
            $verified += $group['counts']['verified'];
            $invalid  += $group['counts']['invalid'];

            foreach ($group['events'] as $ev) {
                $events[] = $ev;
            }

            // A digital object with no provenance is still listed (dignified
            // empty group) so the record header is honest about what exists.
            $groups[] = [
                'digital_object_id' => $group['digital_object_id'],
                'name'              => $group['name'],
                'mime_type'         => $group['mime_type'],
                'state'             => $group['state'],
                'verify_url'        => $group['verify_url'],
                'counts'            => $group['counts'],
                'events'            => $group['events'],
            ];
        }

        // One global chronological order across every digital object, oldest
        // first so the trace reads as a single record-level timeline.
        $events = $this->sortEvents($events);

        $captures = $this->countType($events, self::TYPE_CAPTURE);
        $edits    = $this->countType($events, self::TYPE_EDIT);
        $ai       = $this->countType($events, self::TYPE_AI);

        [$summary, $reason] = $this->summarise($records, $signed, $verified, $invalid);

        return [
            'object'         => $object,
            'summary'        => $summary,
            'summary_reason' => $reason,
            'counts'         => [
                'digital_objects' => count($digitalObjects),
                'records'         => $records,
                'signed'          => $signed,
                'verified'        => $verified,
                'invalid'         => $invalid,
                'events'          => count($events),
                'captures'        => $captures,
                'edits'           => $edits,
                'ai'              => $ai,
            ],
            'events'       => $events,
            'groups'       => $groups,
            'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
    }

    /**
     * Build the trace contribution of a single digital object: its provenance
     * records, each verified live, flattened into per-event rows and collapsed
     * to that object's own state. Never throws.
     *
     * @param object $do a digital_object identity row (id, name, mime_type)
     * @return array{
     *     digital_object_id:int,
     *     name:?string,
     *     mime_type:?string,
     *     state:string,
     *     verify_url:string,
     *     counts:array{records:int,signed:int,verified:int,invalid:int},
     *     events:list<array<string,mixed>>
     * }
     */
    private function traceForDigitalObject(object $object, object $do): array
    {
        $digitalObjectId = (int) $do->id;
        $name      = $this->cleanStr($do->name ?? null);
        $mime      = $this->cleanStr($do->mime_type ?? null);
        $verifyUrl = $this->safeUrl('/verify/' . $digitalObjectId);

        $base = [
            'digital_object_id' => $digitalObjectId,
            'name'              => $name,
            'mime_type'         => $mime,
            'state'             => self::STATE_ABSENT,
            'verify_url'        => $verifyUrl,
            'counts'            => ['records' => 0, 'signed' => 0, 'verified' => 0, 'invalid' => 0],
            'events'            => [],
        ];

        try {
            $records = $this->service->listForDigitalObject($digitalObjectId);
        } catch (Throwable $e) {
            Log::info('c2pa trace: listForDigitalObject failed; empty group', [
                'digital_object_id' => $digitalObjectId,
                'err'               => $e->getMessage(),
            ]);

            return $base;
        }

        if ($records === []) {
            return $base;
        }

        $events   = [];
        $signed   = 0;
        $verified = 0;
        $invalid  = 0;

        foreach ($records as $record) {
            $isSigned = ($record->manifest_id ?? null) !== null;

            $verification = ['status' => 'unsigned', 'ok' => false, 'errors' => [], 'manifest' => null, 'kid' => null];
            try {
                $verification = $this->service->verifyRecord((int) $record->id);
            } catch (Throwable $e) {
                Log::info('c2pa trace: verifyRecord threw; entry marked invalid', [
                    'provenance_id' => $record->id ?? null,
                    'err'           => $e->getMessage(),
                ]);
                $verification = ['status' => 'failed', 'ok' => false, 'errors' => [$e->getMessage()], 'manifest' => null, 'kid' => null];
            }

            $status = (string) ($verification['status'] ?? 'unsigned');
            if ($isSigned) {
                $signed++;
                $status === 'verified' ? $verified++ : $invalid++;
            }

            foreach ($this->eventsForRecord($object, $do, $record, $verification, $isSigned, $status) as $ev) {
                $events[] = $ev;
            }
        }

        if ($invalid > 0) {
            $state = self::STATE_INVALID;
        } elseif ($verified > 0) {
            $state = self::STATE_VERIFIED;
        } else {
            $state = self::STATE_ABSENT;
        }

        return [
            'digital_object_id' => $digitalObjectId,
            'name'              => $name,
            'mime_type'         => $mime,
            'state'             => $state,
            'verify_url'        => $verifyUrl,
            'counts'            => ['records' => count($records), 'signed' => $signed, 'verified' => $verified, 'invalid' => $invalid],
            'events'            => $this->sortEvents($events),
        ];
    }

    /**
     * Flatten one provenance record into its constituent timeline events. A
     * record yields:
     *   - one capture event (the digitisation itself),
     *   - one edit event per c2pa.edited action in the signed manifest that is
     *     NOT an AI step,
     *   - one ai-inference event per AI action / recorded inference step,
     *   - one signature event carrying the live verification verdict.
     *
     * Manifest actions are preferred (they carry the signed 'when' + software
     * agent); the raw inference_steps column is used as a fallback only when no
     * manifest action describes them (e.g. an unsigned record), so AI steps are
     * never double-counted.
     *
     * @param array<string,mixed> $verification
     * @return list<array<string,mixed>>
     */
    private function eventsForRecord(
        object $object,
        object $do,
        object $record,
        array $verification,
        bool $isSigned,
        string $status,
    ): array {
        $provenanceId    = (int) ($record->id ?? 0);
        $digitalObjectId = (int) $do->id;
        $doName          = $this->cleanStr($do->name ?? null);

        $events = [];

        // --- The capture / digitisation event (the spine of the record). ---
        $capturedAt = $this->cleanStr($record->captured_at ?? null);
        $capturedBy = $this->cleanStr($record->captured_by ?? null);
        $device     = $this->cleanStr($record->capture_device ?? null);
        $software   = $this->cleanStr($record->capture_software ?? null);
        $tool       = trim((string) $device . ($device !== null && $software !== null ? ' / ' : '') . (string) $software) ?: null;

        $captureWhen = $capturedAt ?? $this->createdAt($record);

        $events[] = $this->event(
            type: self::TYPE_CAPTURE,
            when: $captureWhen,
            sortWhen: $this->sortKey($captureWhen, $provenanceId, 0),
            summary: $doName !== null
                ? 'Digitised / captured "' . $doName . '"'
                : 'Digitised / captured',
            actor: $capturedBy,
            tool: $tool,
            digitalObjectId: $digitalObjectId,
            digitalObjectName: $doName,
            provenanceId: $provenanceId,
            verifyState: $this->entryVerifyState($isSigned, $status),
            extra: array_filter([
                'content_fingerprint' => $this->cleanStr($record->asset_sha256 ?? null),
                'notes'               => $this->cleanStr($record->notes ?? null),
            ], static fn ($v) => $v !== null),
        );

        // --- Manifest-derived edit / AI events (signed, ordered). ---
        $haveManifestAi = false;
        $actions = $this->manifestActions($verification['manifest'] ?? null);
        foreach ($actions as $idx => $action) {
            $name = (string) ($action['action'] ?? '');
            // The first 'created' action IS the capture above; skip it here so
            // we do not emit a duplicate capture event.
            if ($name === 'c2pa.created') {
                continue;
            }

            $params  = is_array($action['parameters'] ?? null) ? $action['parameters'] : [];
            $isAi    = str_contains($name, 'ai-')
                || isset($params['model_id'])
                || isset($params['inferenceStep']);
            $when    = $this->cleanStr($action['when'] ?? null);
            $swAgent = $this->softwareAgent($action['softwareAgent'] ?? null);

            if ($isAi) {
                $haveManifestAi = true;
                $events[] = $this->event(
                    type: self::TYPE_AI,
                    when: $when,
                    sortWhen: $this->sortKey($when ?? $captureWhen, $provenanceId, 10 + (int) $idx),
                    summary: $this->aiSummary($params),
                    actor: null,
                    tool: $swAgent ?? $this->cleanStr($params['model_id'] ?? null),
                    digitalObjectId: $digitalObjectId,
                    digitalObjectName: $doName,
                    provenanceId: $provenanceId,
                    verifyState: $this->entryVerifyState($isSigned, $status),
                    extra: $this->scalarParams($params),
                );
            } else {
                $events[] = $this->event(
                    type: self::TYPE_EDIT,
                    when: $when,
                    sortWhen: $this->sortKey($when ?? $captureWhen, $provenanceId, 10 + (int) $idx),
                    summary: $this->editSummary($name),
                    actor: null,
                    tool: $swAgent,
                    digitalObjectId: $digitalObjectId,
                    digitalObjectName: $doName,
                    provenanceId: $provenanceId,
                    verifyState: $this->entryVerifyState($isSigned, $status),
                    extra: $this->scalarParams($params),
                );
            }
        }

        // --- Fallback AI events from the raw inference_steps column, only when
        // the signed manifest did not already describe AI steps (avoid double
        // counting). Covers unsigned records that still recorded steps. ---
        if (!$haveManifestAi) {
            foreach ($this->decodeSteps($record) as $i => $step) {
                if (!is_array($step)) {
                    continue;
                }
                $model = $this->cleanStr($step['model_id'] ?? null);
                $stepName = $this->cleanStr($step['step'] ?? null);
                $events[] = $this->event(
                    type: self::TYPE_AI,
                    when: $capturedAt,
                    sortWhen: $this->sortKey($captureWhen, $provenanceId, 50 + (int) $i),
                    summary: 'AI processing step' . ($stepName !== null ? ': ' . $stepName : ''),
                    actor: null,
                    tool: $model,
                    digitalObjectId: $digitalObjectId,
                    digitalObjectName: $doName,
                    provenanceId: $provenanceId,
                    verifyState: $this->entryVerifyState($isSigned, $status),
                    extra: array_filter([
                        'model_id'      => $model,
                        'model_version' => $this->cleanStr($step['model_version'] ?? null),
                        'output_sha256' => $this->cleanStr($step['output_sha256'] ?? null),
                    ], static fn ($v) => $v !== null),
                );
            }
        }

        // --- The signature / verification event (the verdict for this record). ---
        $sigWhen = $this->cleanStr($record->updated_at ?? null) ?? $this->createdAt($record);
        if ($isSigned) {
            $sigSummary = $status === 'verified'
                ? 'Signed content credentials verified'
                : 'Signed content credentials could not be verified';
            $sigState = $status === 'verified' ? 'verified' : 'invalid';
        } else {
            $sigSummary = 'No signed content credentials for this record';
            $sigState = 'unsigned';
        }

        $events[] = $this->event(
            type: self::TYPE_SIGNATURE,
            when: $sigWhen,
            // Signature event sorts AFTER the actions of the same record.
            sortWhen: $this->sortKey($sigWhen, $provenanceId, 9000),
            summary: $sigSummary,
            actor: $this->cleanStr($verification['kid'] ?? null),
            tool: null,
            digitalObjectId: $digitalObjectId,
            digitalObjectName: $doName,
            provenanceId: $provenanceId,
            verifyState: $sigState,
            extra: array_filter([
                'key_id' => $this->cleanStr($verification['kid'] ?? null),
                'errors' => $this->errorString($verification['errors'] ?? null),
            ], static fn ($v) => $v !== null),
        );

        return $events;
    }

    /**
     * The verify state stamped onto a content event (capture/edit/ai) based on
     * the record's signature: verified, invalid (signed but failed), or
     * unsigned (recorded but not signed).
     */
    private function entryVerifyState(bool $isSigned, string $status): string
    {
        if (!$isSigned) {
            return 'unsigned';
        }

        return $status === 'verified' ? 'verified' : 'invalid';
    }

    /**
     * Assemble one normalised trace event. Centralised so every event has the
     * same shape for both the page and the JSON endpoint.
     *
     * @param array<string,string> $extra
     * @return array<string,mixed>
     */
    private function event(
        string $type,
        ?string $when,
        string $sortWhen,
        string $summary,
        ?string $actor,
        ?string $tool,
        int $digitalObjectId,
        ?string $digitalObjectName,
        int $provenanceId,
        string $verifyState,
        array $extra = [],
    ): array {
        return [
            'type'                => $type,
            'when'                => $when,
            'summary'             => $summary,
            'actor'               => $actor,
            'tool'                => $tool,
            'digital_object_id'   => $digitalObjectId,
            'digital_object_name' => $digitalObjectName,
            'provenance_id'       => $provenanceId,
            'verification_status' => $verifyState,
            'detail'              => $extra,
            '_sort'               => $sortWhen,
        ];
    }

    /* ----------------------------------------------------------------- *
     * Whole-record authenticity summary.
     * ----------------------------------------------------------------- */

    /**
     * Reduce the aggregate counts to one record-level verdict.
     *
     *   - no records at all            -> none      (dignified empty state)
     *   - any signed entry FAILED      -> invalid   (a tamper anywhere is loud)
     *   - all signed entries verify, AND
     *       nothing is unsigned        -> verified  (fully signed + clean)
     *   - some verify, some unsigned   -> partially (mixed but no failures)
     *   - nothing signed at all        -> unsigned  (documented, never signed)
     *
     * @return array{0:string,1:string}
     */
    private function summarise(int $records, int $signed, int $verified, int $invalid): array
    {
        if ($records === 0) {
            return [self::SUMMARY_NONE, 'No provenance has been recorded for this record yet.'];
        }
        if ($invalid > 0) {
            return [
                self::SUMMARY_INVALID,
                'One or more signed provenance entries could not be verified. Treat the affected steps with caution.',
            ];
        }
        if ($signed === 0) {
            return [
                self::SUMMARY_UNSIGNED,
                'Provenance is recorded for this record, but none of it carries a signature on this host.',
            ];
        }
        // Everything that is signed verifies. If every record is signed, the
        // record is fully verified; otherwise it is partially signed.
        if ($verified >= $records) {
            return [self::SUMMARY_VERIFIED, 'Every provenance entry is signed and its signature checks out.'];
        }

        return [
            self::SUMMARY_PARTIAL,
            'The signed parts of this record verify; some provenance entries are recorded but unsigned.',
        ];
    }

    /* ----------------------------------------------------------------- *
     * Digital-object + record identity lookups.
     * ----------------------------------------------------------------- */

    /**
     * The set of digital objects whose provenance belongs to this record. We
     * take the union of:
     *   - master rows (parentless / usage 140) under the IO, and
     *   - any digital object that already has a provenance record for this IO
     * so a record signed against a non-master still appears. Keyed by id, so
     * each object appears once. Ordered by id for a stable grouping.
     *
     * @return list<object>
     */
    private function digitalObjectsFor(int $informationObjectId): array
    {
        $byId = [];

        if (Schema::hasTable('digital_object')) {
            $masters = DB::table('digital_object')
                ->where('object_id', $informationObjectId)
                ->whereNull('parent_id')
                ->where(function ($w) {
                    $w->where('usage_id', self::USAGE_MASTER)->orWhereNull('usage_id');
                })
                ->orderBy('id')
                ->get(['id', 'object_id', 'name', 'mime_type']);
            foreach ($masters as $m) {
                $byId[(int) $m->id] = $m;
            }
        }

        // Pull in any digital object referenced by a provenance row for this IO
        // that we have not already listed (covers non-master / derivative
        // bindings), so no recorded provenance is silently dropped.
        if (Schema::hasTable('ahg_c2pa_provenance')) {
            $extraIds = DB::table('ahg_c2pa_provenance')
                ->where('information_object_id', $informationObjectId)
                ->whereNotNull('digital_object_id')
                ->distinct()
                ->pluck('digital_object_id');

            $missing = [];
            foreach ($extraIds as $id) {
                $id = (int) $id;
                if ($id > 0 && !isset($byId[$id])) {
                    $missing[] = $id;
                }
            }
            if ($missing !== [] && Schema::hasTable('digital_object')) {
                $rows = DB::table('digital_object')
                    ->whereIn('id', $missing)
                    ->get(['id', 'object_id', 'name', 'mime_type']);
                foreach ($rows as $r) {
                    $byId[(int) $r->id] = $r;
                }
            }
        }

        ksort($byId);

        return array_values($byId);
    }

    /**
     * Public-safe identity of the information object: id, identifier, title
     * (en-preferred), slug. Returns null when the record does not exist.
     */
    private function loadObject(int $informationObjectId): ?object
    {
        if ($informationObjectId <= 0 || !Schema::hasTable('information_object')) {
            return null;
        }

        try {
            $io = DB::table('information_object')
                ->where('id', $informationObjectId)
                ->first(['id', 'identifier']);
            if ($io === null) {
                return null;
            }

            $io->title = null;
            $io->slug  = null;

            if (Schema::hasTable('information_object_i18n')) {
                $i18n = DB::table('information_object_i18n')
                    ->where('id', $informationObjectId)
                    ->orderByRaw("culture = 'en' DESC")
                    ->first(['title']);
                $io->title = $i18n->title ?? null;
            }
            if (Schema::hasTable('slug')) {
                $slug = DB::table('slug')->where('object_id', $informationObjectId)->first(['slug']);
                $io->slug = $slug->slug ?? null;
            }

            return $io;
        } catch (Throwable $e) {
            Log::warning('c2pa trace: loadObject failed', [
                'information_object_id' => $informationObjectId,
                'err'                   => $e->getMessage(),
            ]);

            return null;
        }
    }

    /* ----------------------------------------------------------------- *
     * Manifest / step readers (tolerant, forward-compatible).
     * ----------------------------------------------------------------- */

    /**
     * Pull the ordered c2pa.actions.v2 action list out of a decoded manifest.
     * Tolerant of partial / unknown manifests. Returns [] when none.
     *
     * @param mixed $manifest
     * @return list<array<string,mixed>>
     */
    private function manifestActions(mixed $manifest): array
    {
        if (!is_array($manifest)) {
            return [];
        }
        $assertions = $manifest['assertions'] ?? null;
        if (!is_array($assertions)) {
            return [];
        }
        $out = [];
        foreach ($assertions as $a) {
            if (!is_array($a)) {
                continue;
            }
            if (!str_starts_with((string) ($a['label'] ?? ''), 'c2pa.actions')) {
                continue;
            }
            $data    = is_array($a['data'] ?? null) ? $a['data'] : [];
            $actions = is_array($data['actions'] ?? null) ? $data['actions'] : [];
            foreach ($actions as $action) {
                if (is_array($action)) {
                    $out[] = $action;
                }
            }
        }

        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function decodeSteps(object $record): array
    {
        if (!isset($record->inference_steps) || !is_string($record->inference_steps) || $record->inference_steps === '') {
            return [];
        }
        $decoded = json_decode($record->inference_steps, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function softwareAgent(mixed $sw): ?string
    {
        if (!is_array($sw)) {
            return $this->cleanStr($sw);
        }
        $name = $this->cleanStr($sw['name'] ?? null);
        $ver  = $this->cleanStr($sw['version'] ?? null);

        return trim((string) $name . ($ver !== null ? ' ' . $ver : '')) ?: null;
    }

    private function editSummary(string $action): string
    {
        return match ($action) {
            'c2pa.edited'           => 'Edited',
            'c2pa.placed', 'placed' => 'Placed into this asset',
            'c2pa.opened'           => 'Opened',
            'c2pa.converted'        => 'Converted',
            'c2pa.cropped'          => 'Cropped',
            'c2pa.resized'          => 'Resized',
            default                 => 'Edited (' . ucfirst(str_replace(['c2pa.', '-', '_'], ['', ' ', ' '], $action)) . ')',
        };
    }

    /**
     * @param array<string,mixed> $params
     */
    private function aiSummary(array $params): string
    {
        $step  = $this->cleanStr($params['inferenceStep'] ?? null);
        $model = $this->cleanStr($params['model_id'] ?? null);
        if ($step !== null) {
            return 'AI inference: ' . $step . ($model !== null ? ' (' . $model . ')' : '');
        }
        if ($model !== null) {
            return 'AI inference (' . $model . ')';
        }

        return 'AI inference step';
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,string>
     */
    private function scalarParams(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if (is_scalar($v) && (string) $v !== '') {
                $out[(string) $k] = is_bool($v) ? ($v ? 'yes' : 'no') : (string) $v;
            }
        }

        return $out;
    }

    /* ----------------------------------------------------------------- *
     * Ordering + small helpers.
     * ----------------------------------------------------------------- */

    /**
     * Sort the flattened events into one stable chronological order (oldest
     * first) using the precomputed '_sort' key, which encodes the timestamp
     * plus a per-record action index so events without a real timestamp still
     * keep their natural within-record order.
     *
     * @param list<array<string,mixed>> $events
     * @return list<array<string,mixed>>
     */
    private function sortEvents(array $events): array
    {
        usort($events, static fn (array $a, array $b): int => strcmp((string) ($a['_sort'] ?? ''), (string) ($b['_sort'] ?? '')));

        // Strip the internal sort key from the public shape.
        return array_map(static function (array $e): array {
            unset($e['_sort']);

            return $e;
        }, $events);
    }

    /**
     * Build a lexicographically-sortable key: ISO timestamp (or a far-future
     * sentinel when unknown, so undated events sink to the end), then a
     * zero-padded record id and action index for deterministic ties.
     */
    private function sortKey(?string $when, int $provenanceId, int $order): string
    {
        $ts = null;
        if (is_string($when) && trim($when) !== '') {
            $t = strtotime($when);
            if ($t !== false) {
                $ts = gmdate('Y-m-d\TH:i:s', $t);
            }
        }
        // Unknown timestamps sort last (still grouped per record + order).
        $tsKey = $ts ?? '9999-12-31T23:59:59';

        return $tsKey . '|' . str_pad((string) $provenanceId, 12, '0', STR_PAD_LEFT)
            . '|' . str_pad((string) $order, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param list<array<string,mixed>> $events
     */
    private function countType(array $events, string $type): int
    {
        $n = 0;
        foreach ($events as $e) {
            if (($e['type'] ?? null) === $type) {
                $n++;
            }
        }

        return $n;
    }

    private function createdAt(object $record): ?string
    {
        return $this->cleanStr($record->created_at ?? null);
    }

    private function errorString(mixed $errors): ?string
    {
        if (!is_array($errors) || $errors === []) {
            return null;
        }
        $parts = [];
        foreach ($errors as $e) {
            if (is_scalar($e) && (string) $e !== '') {
                $parts[] = (string) $e;
            }
        }

        return $parts === [] ? null : implode('; ', $parts);
    }

    /**
     * The dignified empty trace - a record with no provenance, NOT an error.
     *
     * @return array{
     *     object: object|null,
     *     summary: string,
     *     summary_reason: string,
     *     counts: array{digital_objects:int,records:int,signed:int,verified:int,invalid:int,events:int,captures:int,edits:int,ai:int},
     *     events: list<array<string,mixed>>,
     *     groups: list<array<string,mixed>>,
     *     generated_at: string
     * }
     */
    private function emptyTrace(?object $object, string $summary, string $reason): array
    {
        return [
            'object'         => $object,
            'summary'        => $summary,
            'summary_reason' => $reason,
            'counts'         => [
                'digital_objects' => 0,
                'records'         => 0,
                'signed'          => 0,
                'verified'        => 0,
                'invalid'         => 0,
                'events'          => 0,
                'captures'        => 0,
                'edits'           => 0,
                'ai'              => 0,
            ],
            'events'       => [],
            'groups'       => [],
            'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
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

    private function cleanStr(mixed $v): ?string
    {
        if (!is_scalar($v)) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
