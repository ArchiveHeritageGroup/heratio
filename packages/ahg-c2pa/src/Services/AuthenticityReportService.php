<?php
/**
 * Heratio - per-record AUTHENTICITY REPORT consolidator (issue #1209, north star).
 *
 * The truth anchor's public face for ONE archival record. Where
 * VerifyController answers "is this record's provenance chain signed?",
 * ProvenanceTraceService answers "show me everything that ever happened to
 * this record", and AuthenticityStatsService answers the institution-level
 * coverage question, THIS service consolidates the signals that already exist
 * for a single published record into one honest, plain-language report:
 *
 *   1. Content credentials / C2PA signing  - does the record carry signed
 *      content credentials, and do they verify live? (reuses
 *      ProvenanceTraceService, which reuses ProvenanceRecordService::verifyRecord)
 *   2. Provenance-record verification result - the whole-record verdict
 *      (verified / partially / unsigned / invalid / none).
 *   3. AI-inference provenance              - whether any AI step is recorded in
 *      the signed/recorded provenance for this record, and how many.
 *
 * It is a pure READ-ONLY aggregator. It never writes, never signs, never
 * reimplements verification, never calls AI, and never re-reads a manifest off
 * disk: every authenticity fact comes from the existing services. It enforces
 * the public contract: only PUBLISHED records are reportable (the same
 * status/type_id=158/status_id=160 gate the public GLAM browse uses); an
 * unknown or unpublished record resolves to null so the controller can 404.
 *
 * Honest framing is a hard requirement of this surface. The report never
 * overclaims: a record with signed-and-verified credentials is described as
 * "what we CAN verify"; a record with no signals gets the dignified
 * "no authenticity signals recorded yet" state; an unsigned-but-recorded
 * record is never dressed up as verified. The confidence statement is computed
 * from the real verdict, not assumed.
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
 * Consolidates the existing per-record authenticity signals into one report.
 * Distinct from every other c2pa service: it owns NO table and performs NO
 * verification of its own. It resolves a published information object, asks
 * ProvenanceTraceService for the already-computed whole-record trace + verdict,
 * and reduces that to a small, honest report shape for the public page + JSON.
 */
final class AuthenticityReportService
{
    /** Publication status taxonomy: status.type_id for "publication status". */
    private const PUBLICATION_STATUS_TYPE_ID = 158;

    /** status.status_id value that means "Published" (same gate as GLAM browse). */
    private const PUBLISHED_STATUS_ID = 160;

    /** Overall confidence tiers surfaced to the public. Honest, never overclaimed. */
    public const CONFIDENCE_HIGH    = 'high';     // signed content credentials that verify live
    public const CONFIDENCE_PARTIAL = 'partial';  // some signed + verified, some unsigned (no failures)
    public const CONFIDENCE_LOW     = 'low';      // provenance recorded but nothing is signed
    public const CONFIDENCE_BROKEN  = 'broken';   // a signed entry failed verification (possible tampering)
    public const CONFIDENCE_NONE    = 'none';     // no authenticity signals recorded yet

    public function __construct(private ProvenanceTraceService $trace)
    {
    }

    /**
     * Build the consolidated authenticity report for a published record
     * addressed by numeric id or slug. Returns null when the record does not
     * exist OR is not published (the controller turns null into a 404, so an
     * unpublished record is indistinguishable from a missing one - the honest
     * public contract). Never throws: any reader fault degrades to the neutral
     * "no signals" report for a record we DID resolve, or null on a hard fault.
     *
     * @return array{
     *     object: object,
     *     confidence: string,
     *     confidence_label: string,
     *     summary: string,
     *     can_verify: list<string>,
     *     cannot_verify: list<string>,
     *     signals: array{
     *         content_credentials: array{present: bool, signed: int, verified: int, invalid: int, state: string},
     *         provenance: array{verdict: string, reason: string, records: int, digital_objects: int},
     *         ai_inference: array{present: bool, count: int}
     *     },
     *     counts: array<string,int>,
     *     trace_url: string,
     *     trace_json_url: string,
     *     badge_url: ?string,
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

        // Reuse the existing record-level trace. It already fans out across
        // every digital object, verifies each signed provenance record LIVE,
        // and computes the whole-record verdict. We never recompute any of that
        // here - we only consolidate it into the public report shape.
        $trace = $this->safeTrace($ioId);

        $counts  = is_array($trace['counts'] ?? null) ? $trace['counts'] : [];
        $verdict = is_string($trace['summary'] ?? null) ? $trace['summary'] : ProvenanceTraceService::SUMMARY_NONE;
        $reason  = is_string($trace['summary_reason'] ?? null) ? $trace['summary_reason'] : '';

        $signed   = (int) ($counts['signed'] ?? 0);
        $verified = (int) ($counts['verified'] ?? 0);
        $invalid  = (int) ($counts['invalid'] ?? 0);
        $records  = (int) ($counts['records'] ?? 0);
        $dObjects = (int) ($counts['digital_objects'] ?? 0);
        $aiCount  = (int) ($counts['ai'] ?? 0);

        $ccState = $this->contentCredentialsState($verdict, $signed, $verified, $invalid);

        [$confidence, $confidenceLabel] = $this->confidence($verdict);
        $summary = $this->summary($confidence, $verdict, $signed, $verified, $invalid, $records, $aiCount);

        return [
            'object'           => $object,
            'confidence'       => $confidence,
            'confidence_label' => $confidenceLabel,
            'summary'          => $summary,
            'can_verify'       => $this->canVerify($confidence, $verified, $signed, $aiCount),
            'cannot_verify'    => $this->cannotVerify($confidence, $signed, $records),
            'signals'          => [
                'content_credentials' => [
                    'present'  => $signed > 0 || $records > 0,
                    'signed'   => $signed,
                    'verified' => $verified,
                    'invalid'  => $invalid,
                    'state'    => $ccState,
                ],
                'provenance' => [
                    'verdict'         => $verdict,
                    'reason'          => $reason,
                    'records'         => $records,
                    'digital_objects' => $dObjects,
                ],
                'ai_inference' => [
                    'present' => $aiCount > 0,
                    'count'   => $aiCount,
                ],
            ],
            'counts'         => array_map('intval', $counts),
            'trace_url'      => $this->safeUrl('/verify/record/' . $ioId . '/trace'),
            'trace_json_url' => $this->safeUrl('/verify/record/' . $ioId . '/trace.json'),
            'badge_url'      => $this->safeUrl('/authenticity/' . $ioId . '/badge'),
            'generated_at'   => gmdate('Y-m-d\TH:i:s\Z'),
        ];
    }

    /* ----------------------------------------------------------------- *
     * Signal interpretation - all derived from the real trace verdict.
     * ----------------------------------------------------------------- */

    /**
     * The content-credentials state for the report, mirroring the three
     * human-facing verify states (verified / invalid / absent) but computed
     * from the whole-record counts so it agrees with the verdict.
     */
    private function contentCredentialsState(string $verdict, int $signed, int $verified, int $invalid): string
    {
        if ($invalid > 0) {
            return 'invalid';
        }
        if ($signed > 0 && $verified > 0) {
            return 'verified';
        }

        return 'absent';
    }

    /**
     * Map the whole-record verdict to a public confidence tier + label. This is
     * the honest framing core: confidence is NEVER assumed, only derived from
     * the live verification verdict the trace service computed.
     *
     * @return array{0:string,1:string}
     */
    private function confidence(string $verdict): array
    {
        return match ($verdict) {
            ProvenanceTraceService::SUMMARY_VERIFIED => [self::CONFIDENCE_HIGH,    'High'],
            ProvenanceTraceService::SUMMARY_PARTIAL  => [self::CONFIDENCE_PARTIAL, 'Partial'],
            ProvenanceTraceService::SUMMARY_UNSIGNED => [self::CONFIDENCE_LOW,     'Low'],
            ProvenanceTraceService::SUMMARY_INVALID  => [self::CONFIDENCE_BROKEN,  'Failed'],
            default                                  => [self::CONFIDENCE_NONE,    'None recorded'],
        };
    }

    /**
     * One plain-language sentence stating, honestly, what the report means. It
     * never claims more than the verdict supports.
     */
    private function summary(
        string $confidence,
        string $verdict,
        int $signed,
        int $verified,
        int $invalid,
        int $records,
        int $aiCount,
    ): string {
        $aiClause = $aiCount > 0
            ? ' Automated AI processing steps are recorded as part of the provenance.'
            : '';

        return match ($confidence) {
            self::CONFIDENCE_HIGH => 'This record carries signed content credentials, and every signature checks out '
                . 'when re-verified live. We can confirm how its digital files were captured and handled, '
                . 'but content credentials attest to the file\'s history - not to the truthfulness of what the '
                . 'source itself depicts or claims.' . $aiClause,
            self::CONFIDENCE_PARTIAL => 'Part of this record carries signed content credentials that verify live; '
                . 'other parts of its provenance are recorded but not signed. We can verify the signed portion '
                . 'and show the rest as documented-but-unverified.' . $aiClause,
            self::CONFIDENCE_LOW => 'Provenance is recorded for this record, but none of it is cryptographically '
                . 'signed on this system, so we cannot verify it. What is shown is documented history, not '
                . 'verified history.' . $aiClause,
            self::CONFIDENCE_BROKEN => 'One or more signed content credentials on this record did NOT verify when '
                . 're-checked. This can mean a file was altered after signing, or a key/record problem. Treat the '
                . 'affected material with caution.' . $aiClause,
            default => 'No authenticity signals have been recorded for this record yet. That does not mean anything '
                . 'is wrong - only that no content credentials or signed provenance have been captured for it so far.',
        };
    }

    /**
     * The honest "what we CAN verify" list. Each line is true only when the
     * underlying signal supports it.
     *
     * @return list<string>
     */
    private function canVerify(string $confidence, int $verified, int $signed, int $aiCount): array
    {
        $out = [];

        if ($confidence === self::CONFIDENCE_HIGH || $confidence === self::CONFIDENCE_PARTIAL) {
            $out[] = 'That the signed digital files have not been altered since they were sealed (their content '
                . 'fingerprints still match).';
            $out[] = 'The recorded capture / digitisation steps for the signed files, and the key that signed them.';
            if ($aiCount > 0) {
                $out[] = 'Which automated AI processing steps were applied, as recorded in the signed provenance.';
            }
            $out[] = 'That the signature re-verifies live, right now, on this page load (nothing is cached).';
        }

        return $out;
    }

    /**
     * The honest "what we CANNOT verify" list. This is always non-empty: there
     * is ALWAYS something content credentials cannot attest to, and saying so
     * plainly is the point of a trust anchor.
     *
     * @return list<string>
     */
    private function cannotVerify(string $confidence, int $signed, int $records): array
    {
        $out = [];

        // True for every record: provenance attests to handling, not to truth.
        $out[] = 'Whether what the source depicts or states is itself true, accurate, or complete.';
        $out[] = 'Anything that happened before the record entered this system or outside its recorded provenance.';

        if ($confidence === self::CONFIDENCE_NONE) {
            $out[] = 'Anything about how this record\'s files were captured or handled - no provenance is recorded yet.';
        } elseif ($confidence === self::CONFIDENCE_LOW) {
            $out[] = 'That the recorded provenance is unaltered - it is documented but not cryptographically signed here.';
        } elseif ($confidence === self::CONFIDENCE_PARTIAL) {
            $out[] = 'The unsigned parts of the provenance, which are shown as recorded but cannot be cryptographically verified.';
        } elseif ($confidence === self::CONFIDENCE_BROKEN) {
            $out[] = 'The integrity of the affected files: at least one signature did not re-verify.';
        }

        return $out;
    }

    /* ----------------------------------------------------------------- *
     * Resolution + published gate.
     * ----------------------------------------------------------------- */

    /**
     * Resolve a PUBLISHED information object by numeric id or (possibly
     * multi-segment) slug. Returns null when the row is absent OR not published,
     * so the public surface cannot leak a draft/embargoed record. Never throws.
     */
    private function resolvePublished(string $idOrSlug): ?object
    {
        try {
            if (!Schema::hasTable('information_object')) {
                return null;
            }

            $id = $this->resolveId($idOrSlug);
            if ($id === null) {
                return null;
            }

            if (!$this->isPublished($id)) {
                return null;
            }

            return $this->loadObject($id);
        } catch (Throwable $e) {
            Log::warning('c2pa authenticity: resolvePublished failed', [
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

        if (!Schema::hasTable('slug')) {
            return null;
        }

        $row = DB::table('slug')->where('slug', $ref)->first(['object_id']);
        if ($row === null || !isset($row->object_id)) {
            return null;
        }

        $id = (int) $row->object_id;

        return $id > 0 ? $id : null;
    }

    /**
     * The published gate: a published row has a status entry with the
     * publication type (158) and the Published status id (160). Identical
     * contract to the public GLAM browse (DisplayController::applyFilters), so a
     * record is reportable here exactly when it is publicly browsable.
     */
    private function isPublished(int $informationObjectId): bool
    {
        if (!Schema::hasTable('status')) {
            // No status table: nothing can be proven published -> withhold.
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
    }

    /**
     * Build the trace defensively. ProvenanceTraceService is already
     * fully resilient, but a hard fault must still degrade to the neutral
     * "no signals" shape rather than escape.
     *
     * @return array<string,mixed>
     */
    private function safeTrace(int $ioId): array
    {
        try {
            return $this->trace->trace($ioId);
        } catch (Throwable $e) {
            Log::warning('c2pa authenticity: trace build threw; neutral report', [
                'information_object_id' => $ioId,
                'err'                   => $e->getMessage(),
            ]);

            return [
                'summary'        => ProvenanceTraceService::SUMMARY_NONE,
                'summary_reason' => 'No provenance recorded yet.',
                'counts'         => [],
            ];
        }
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
