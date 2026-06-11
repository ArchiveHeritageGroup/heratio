<?php
/**
 * Heratio - public per-record INFERENCE-PROVENANCE consolidator (issue #1201).
 *
 * The honest read surface over the AI-inference provenance foundation
 * (ahg_ai_inference + ahg_ai_override, shipped for issue #61 / ADR-0002). Where
 * AuthenticityReportService answers "is this record's provenance chain signed?"
 * and ProvenanceTraceService walks the C2PA digitisation chain, THIS service
 * answers a different, narrower question:
 *
 *   "Which AI inferences contributed to THIS published record's metadata, with
 *    which model, through which gateway, when - and did a human stay
 *    accountable for the result?"
 *
 * It is a pure READ-ONLY aggregator. It owns no table, writes nothing, runs no
 * AI, signs nothing, and re-verifies nothing. Every fact comes from the
 * existing ahg_ai_inference rows (one per AI write: description / NER / HTR /
 * translation / condition scan / LLM) and their ahg_ai_override corrections
 * (one per human reviewer change). If the inference table is absent on an older
 * install, it degrades to the dignified "no AI inference recorded" state rather
 * than erroring.
 *
 * Honest framing is a hard requirement. The page never claims an inference was
 * "correct" - only that it was recorded, by which model/gateway, when, and
 * whether a human accepted, corrected, or rejected it. An AI step with no human
 * override is shown as "AI-suggested, not yet reviewed", never as "verified".
 *
 * Public contract: only PUBLISHED records are reportable (the same
 * status.type_id=158 / status_id=160 gate the public GLAM browse and the
 * authenticity report use). An unknown OR unpublished record resolves to null
 * so the controller returns a clean 404 (HTML and JSON). Never throws.
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

final class InferenceProvenanceService
{
    /** Publication status taxonomy: status.type_id for "publication status". */
    private const PUBLICATION_STATUS_TYPE_ID = 158;

    /** status.status_id value that means "Published" (same gate as GLAM browse). */
    private const PUBLISHED_STATUS_ID = 160;

    /** The dedicated AI-inference provenance tables (issue #61 / ADR-0002). */
    private const INFERENCE_TABLE = 'ahg_ai_inference';
    private const OVERRIDE_TABLE  = 'ahg_ai_override';

    /** Per-inference human-accountability states. Never overclaimed. */
    public const REVIEW_ACCEPTED  = 'accepted';   // a human applied/kept the AI output
    public const REVIEW_CORRECTED = 'corrected';  // a human changed the AI output
    public const REVIEW_REJECTED  = 'rejected';   // a human rejected the AI output
    public const REVIEW_PENDING   = 'pending';    // AI-suggested, not yet reviewed

    /**
     * Build the consolidated inference-provenance report for a published record
     * addressed by numeric id or (possibly multi-segment) slug.
     *
     * Returns null when the record does not exist OR is not published (the
     * controller turns null into a 404, so an unpublished record is
     * indistinguishable from a missing one - the honest public contract).
     *
     * Returns a populated report (possibly with an EMPTY inferences list) for a
     * resolved, published record. The empty-inferences case is the dignified
     * "no AI inference recorded for this record" state, NOT an error.
     *
     * Never throws: any reader fault degrades to the neutral empty report for a
     * record we DID resolve, or null on a hard resolve fault.
     *
     * @return array{
     *     object: object,
     *     available: bool,
     *     inferences: list<array<string,mixed>>,
     *     by_service: array<string,int>,
     *     counts: array{total:int,reviewed:int,corrected:int,rejected:int,pending:int,services:int,models:int},
     *     models: list<string>,
     *     summary: string,
     *     report_url: string,
     *     report_json_url: string,
     *     authenticity_url: string,
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
        $available = $this->inferenceStoreAvailable();

        $inferences = $available ? $this->loadInferences($ioId) : [];

        $counts    = $this->counts($inferences);
        $byService = $this->byService($inferences);
        $models    = $this->distinctModels($inferences);

        return [
            'object'           => $object,
            'available'        => $available,
            'inferences'       => $inferences,
            'by_service'       => $byService,
            'counts'           => $counts,
            'models'           => $models,
            'summary'          => $this->summary($available, $counts, count($models)),
            'report_url'       => $this->safeUrl('/inference-provenance/' . ($object->slug ?: $ioId)),
            'report_json_url'  => $this->safeUrl('/inference-provenance/' . $ioId . '.json'),
            'authenticity_url' => $this->safeUrl('/authenticity/' . ($object->slug ?: $ioId)),
            'generated_at'     => gmdate('Y-m-d\TH:i:s\Z'),
        ];
    }

    /* ----------------------------------------------------------------- *
     * Inference loading - read-only over ahg_ai_inference + ahg_ai_override.
     * ----------------------------------------------------------------- */

    /**
     * Load every AI inference recorded against this information object, newest
     * first, each enriched with its human-override outcome (if any). Pure read.
     *
     * @return list<array<string,mixed>>
     */
    private function loadInferences(int $ioId): array
    {
        try {
            $rows = DB::table(self::INFERENCE_TABLE)
                ->where('target_entity_type', 'information_object')
                ->where('target_entity_id', $ioId)
                ->orderByDesc('occurred_at')
                ->get();
        } catch (Throwable $e) {
            Log::warning('c2pa inference-provenance: load failed; neutral report', [
                'information_object_id' => $ioId,
                'err'                   => $e->getMessage(),
            ]);

            return [];
        }

        if ($rows->isEmpty()) {
            return [];
        }

        $overrides = $this->loadOverrides($rows->pluck('id')->all());

        $out = [];
        foreach ($rows as $r) {
            $ov = $overrides[(int) $r->id] ?? null;
            $out[] = $this->shapeInference($r, $ov);
        }

        return $out;
    }

    /**
     * The latest override per inference id, keyed by inference id. One inference
     * may have several overrides (corrected then re-corrected); we keep the most
     * recent so the displayed outcome reflects the current state.
     *
     * @param  list<int> $inferenceIds
     * @return array<int,object>
     */
    private function loadOverrides(array $inferenceIds): array
    {
        if (empty($inferenceIds) || ! $this->tableExists(self::OVERRIDE_TABLE)) {
            return [];
        }

        try {
            $rows = DB::table(self::OVERRIDE_TABLE)
                ->whereIn('inference_id', $inferenceIds)
                ->orderBy('inference_id')
                ->orderByDesc('occurred_at')
                ->get();
        } catch (Throwable $e) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $iid = (int) $row->inference_id;
            // First row per inference wins (already ordered newest-first).
            if (! isset($map[$iid])) {
                $map[$iid] = $row;
            }
        }

        return $map;
    }

    /**
     * Reduce one inference row + its override to a public-safe, honest shape.
     * No input/output content is exposed - only the model identity, the gateway
     * endpoint, the field touched, the timing, and the human-accountability
     * outcome. Hashes/excerpts deliberately stay server-side.
     *
     * @return array<string,mixed>
     */
    private function shapeInference(object $r, ?object $override): array
    {
        [$reviewState, $reviewer, $reviewedAt, $reason] = $this->reviewOutcome($override);

        return [
            'service'        => $this->humaniseService((string) ($r->service_name ?? '')),
            'service_code'   => (string) ($r->service_name ?? ''),
            'model'          => (string) ($r->model_name ?? __('unknown model')),
            'model_version'  => $this->cleanVersion((string) ($r->model_version ?? '')),
            'gateway'        => $this->gatewayLabel($r->endpoint ?? null),
            'field'          => $this->humaniseField((string) ($r->target_field ?? '')),
            'field_code'     => (string) ($r->target_field ?? ''),
            'standard'       => $r->standard ?? null,
            'confidence'     => $this->confidencePercent($r->confidence ?? null),
            'occurred_at'    => $this->isoOrNull($r->occurred_at ?? null),
            'triggered_by'   => $this->userLabel($r->user_id ?? null),
            'review_state'   => $reviewState,
            'review_label'   => $this->reviewLabel($reviewState),
            'reviewer'       => $reviewer,
            'reviewed_at'    => $reviewedAt,
            'review_reason'  => $reason,
        ];
    }

    /**
     * Interpret the override row into an honest human-accountability outcome.
     * No override at all -> "pending" (AI-suggested, not yet reviewed).
     *
     * @return array{0:string,1:?string,2:?string,3:?string} [state, reviewer, reviewedAtIso, reason]
     */
    private function reviewOutcome(?object $override): array
    {
        if ($override === null) {
            return [self::REVIEW_PENDING, null, null, null];
        }

        $status = strtolower((string) ($override->status ?? 'applied'));
        $reviewer  = $this->userLabel($override->reviewer_user_id ?? null);
        $reviewed  = $this->isoOrNull($override->occurred_at ?? null);
        $reason    = $this->trimOrNull($override->reason ?? null);

        // A "rejected" override means the human threw the AI output out. An
        // "applied" / "superseded" override where the value actually changed is
        // a correction; where it was kept verbatim it is an acceptance. We can
        // tell the two apart by comparing the snapshots when both are present.
        if ($status === 'rejected') {
            return [self::REVIEW_REJECTED, $reviewer, $reviewed, $reason];
        }

        $orig = (string) ($override->original_value ?? '');
        $set  = (string) ($override->override_value ?? '');
        if ($orig !== '' && $set !== '' && $orig === $set) {
            return [self::REVIEW_ACCEPTED, $reviewer, $reviewed, $reason];
        }

        return [self::REVIEW_CORRECTED, $reviewer, $reviewed, $reason];
    }

    /* ----------------------------------------------------------------- *
     * Counts + summary - all derived from the loaded rows, never assumed.
     * ----------------------------------------------------------------- */

    /**
     * @param  list<array<string,mixed>> $inferences
     * @return array{total:int,reviewed:int,corrected:int,rejected:int,pending:int,services:int,models:int}
     */
    private function counts(array $inferences): array
    {
        $reviewed = $corrected = $rejected = $pending = 0;
        $services = [];

        foreach ($inferences as $i) {
            $services[$i['service_code']] = true;
            switch ($i['review_state']) {
                case self::REVIEW_ACCEPTED:
                    $reviewed++;
                    break;
                case self::REVIEW_CORRECTED:
                    $corrected++;
                    $reviewed++;
                    break;
                case self::REVIEW_REJECTED:
                    $rejected++;
                    $reviewed++;
                    break;
                default:
                    $pending++;
            }
        }

        return [
            'total'     => count($inferences),
            'reviewed'  => $reviewed,
            'corrected' => $corrected,
            'rejected'  => $rejected,
            'pending'   => $pending,
            'services'  => count($services),
            'models'    => count($this->distinctModels($inferences)),
        ];
    }

    /**
     * Inference count grouped by humanised service name (description, NER, ...).
     *
     * @param  list<array<string,mixed>> $inferences
     * @return array<string,int>
     */
    private function byService(array $inferences): array
    {
        $out = [];
        foreach ($inferences as $i) {
            $key = $i['service'];
            $out[$key] = ($out[$key] ?? 0) + 1;
        }
        arsort($out);

        return $out;
    }

    /**
     * Distinct "model name version" identifiers across the inferences.
     *
     * @param  list<array<string,mixed>> $inferences
     * @return list<string>
     */
    private function distinctModels(array $inferences): array
    {
        $seen = [];
        foreach ($inferences as $i) {
            $label = trim($i['model'] . ($i['model_version'] ? ' ' . $i['model_version'] : ''));
            if ($label !== '') {
                $seen[$label] = true;
            }
        }

        return array_keys($seen);
    }

    /**
     * One honest plain-language sentence. Never claims correctness; states only
     * what was recorded and the human-accountability posture.
     *
     * @param  array{total:int,reviewed:int,corrected:int,rejected:int,pending:int,services:int,models:int} $counts
     */
    private function summary(bool $available, array $counts, int $modelCount): string
    {
        if (! $available) {
            return __('No AI inference provenance store is configured on this system, so no automated steps can be shown for this record.');
        }

        if ($counts['total'] === 0) {
            return __('No AI inference has been recorded for this record. Its metadata was either entered by hand or pre-dates inference logging - either way, no automated step is on file for it.');
        }

        $pieces = [];
        $pieces[] = trans_choice(
            '{1}:total AI inference is recorded for this record|[2,*]:total AI inferences are recorded for this record',
            $counts['total'],
            ['total' => $counts['total']]
        );

        if ($modelCount > 0) {
            $pieces[] = trans_choice(
                '{1}produced by :n model|[2,*]produced by :n models',
                $modelCount,
                ['n' => $modelCount]
            );
        }

        $tail = $counts['pending'] > 0
            ? __('A human curator remains accountable: some steps are still shown as suggestions awaiting review, and any human correction or rejection is recorded alongside the original AI output.')
            : __('A human curator reviewed every recorded step; each correction or rejection is logged alongside the original AI output.');

        return implode(', ', $pieces) . '. ' . $tail;
    }

    /* ----------------------------------------------------------------- *
     * Humanising helpers - presentation only, no verification claims.
     * ----------------------------------------------------------------- */

    private function humaniseService(string $code): string
    {
        $map = [
            'NER'         => __('Named-entity recognition'),
            'HTR'         => __('Handwritten-text recognition'),
            'OCR'         => __('Optical character recognition'),
            'TRANSLATION' => __('Machine translation'),
            'LLM'         => __('Language-model description'),
            'DONUT'       => __('Document understanding'),
            'CONDITION'   => __('Condition assessment'),
            'SUMMARY'     => __('Summarisation'),
        ];

        $u = strtoupper(trim($code));
        if ($u === '') {
            return __('AI processing');
        }

        return $map[$u] ?? ucfirst(strtolower(str_replace('_', ' ', $code)));
    }

    /**
     * Turn a target_field into a readable label, dropping the @culture suffix
     * that translation fields carry (e.g. "scope_and_content@af").
     */
    private function humaniseField(string $field): string
    {
        if ($field === '') {
            return __('metadata');
        }

        $culture = null;
        if (str_contains($field, '@')) {
            [$field, $culture] = explode('@', $field, 2);
        }

        $label = ucfirst(str_replace('_', ' ', $field));

        return $culture ? $label . ' (' . strtoupper($culture) . ')' : $label;
    }

    /**
     * A trustworthy, host-free label for where the inference ran. We surface
     * only the host (and the recognised AHG gateway by name) - never the full
     * URL with any query string - so the page does not leak internal paths.
     */
    private function gatewayLabel($endpoint): ?string
    {
        if (! is_string($endpoint) || trim($endpoint) === '') {
            return null;
        }

        $host = parse_url($endpoint, PHP_URL_HOST) ?: null;
        if ($host === null) {
            return null;
        }

        if (stripos($host, 'ai.theahg.co.za') !== false) {
            return __('AHG AI gateway');
        }

        return $host;
    }

    private function cleanVersion(string $v): string
    {
        $v = trim($v);

        return ($v === '' || strtolower($v) === 'unknown') ? '' : $v;
    }

    private function confidencePercent($confidence): ?int
    {
        if ($confidence === null || $confidence === '') {
            return null;
        }
        $f = (float) $confidence;
        if ($f < 0) {
            return null;
        }

        return (int) round(min(1.0, $f) * 100);
    }

    private function reviewLabel(string $state): string
    {
        return match ($state) {
            self::REVIEW_ACCEPTED  => __('Reviewed and kept by a curator'),
            self::REVIEW_CORRECTED => __('Corrected by a curator'),
            self::REVIEW_REJECTED  => __('Rejected by a curator'),
            default                => __('AI-suggested, not yet reviewed'),
        };
    }

    /**
     * Resolve a user id to a display label, falling back to the honest
     * "automated / batch process" wording for the NULL (cron/batch) case.
     */
    private function userLabel($userId): ?string
    {
        if ($userId === null || (int) $userId <= 0) {
            return __('automated / batch process');
        }

        try {
            if (! $this->tableExists('user')) {
                return __('a system user');
            }
            $name = DB::table('user')->where('id', (int) $userId)->value('username');

            return ($name !== null && $name !== '') ? (string) $name : __('a system user');
        } catch (Throwable $e) {
            return __('a system user');
        }
    }

    private function isoOrNull($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return gmdate('Y-m-d\TH:i:s\Z', strtotime((string) $value));
        } catch (Throwable $e) {
            return null;
        }
    }

    private function trimOrNull($value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $v = trim($value);

        return $v === '' ? null : $v;
    }

    /* ----------------------------------------------------------------- *
     * Resolution + published gate (mirrors AuthenticityReportService).
     * ----------------------------------------------------------------- */

    /**
     * True when the dedicated inference store exists. An older install that
     * never ran ahg-provenance-ai's install.sql degrades cleanly to the
     * "no AI inference recorded" state instead of erroring.
     */
    private function inferenceStoreAvailable(): bool
    {
        return $this->tableExists(self::INFERENCE_TABLE);
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
     * multi-segment) slug. Returns null when the row is absent OR not published,
     * so the public surface cannot leak a draft/embargoed record. Never throws.
     */
    private function resolvePublished(string $idOrSlug): ?object
    {
        try {
            if (! $this->tableExists('information_object')) {
                return null;
            }

            $id = $this->resolveId($idOrSlug);
            if ($id === null) {
                return null;
            }

            if (! $this->isPublished($id)) {
                return null;
            }

            return $this->loadObject($id);
        } catch (Throwable $e) {
            Log::warning('c2pa inference-provenance: resolvePublished failed', [
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
     * contract to the public GLAM browse and the authenticity report, so a
     * record is reportable here exactly when it is publicly browsable.
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
