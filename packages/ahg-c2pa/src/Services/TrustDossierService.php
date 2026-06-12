<?php
/**
 * Heratio - per-record consolidated TRUST DOSSIER (issues #1209 / #1201, next slice).
 *
 * The one-stop "defence dossier" for ONE published archival record. Where the
 * three sibling per-record surfaces each answer a single trust question -
 *
 *   - AuthenticityReportService  : "is this record's provenance chain signed,
 *                                   and what can / cannot be verified about its
 *                                   content credentials?" (C2PA layer)
 *   - InferenceProvenanceService : "which AI inferences touched this record's
 *                                   metadata, with which model / gateway, and did
 *                                   a human stay accountable?" (AI-inference layer)
 *   - PreservationTimelineService: "what is the recorded PREMIS preservation
 *                                   lifecycle of this record's digital objects?"
 *                                   (preservation layer)
 *
 * - THIS service UNIFIES all three into one page + one machine companion, plus an
 * honest top-line "what can and cannot be verified about this record" statement
 * that NEVER overclaims.
 *
 * It is a pure READ-ONLY COMPOSER. It owns no table, writes nothing, signs
 * nothing, runs no AI, runs no preservation action, and - critically -
 * re-implements NONE of the three sub-services' queries or verdict logic. It
 * simply calls each sub-service's existing report() READ-ONLY and assembles the
 * result. Every authenticity / inference / preservation fact on the dossier comes
 * verbatim from the service that owns it.
 *
 * Resilience is a hard requirement. Each of the three sections is built behind
 * its own guard: if a sub-service is missing from the container, throws, or
 * degrades, that one section degrades to its own dignified empty state and the
 * dossier still renders. The page never 500s; the JSON companion never 500s.
 *
 * Published contract: the dossier resolves a record exactly when the public GLAM
 * browse and the three sibling surfaces do - via the shared
 * status.type_id=158 / status_id=160 gate. We do NOT re-derive that gate here; we
 * delegate resolution to AuthenticityReportService::report() (the C2PA layer is
 * the canonical truth-anchor front door and always present in this package). When
 * it returns null - unknown OR unpublished - the whole dossier is null and the
 * controller returns a clean 404 (HTML + JSON). An unpublished record is
 * therefore indistinguishable from a missing one, the honest public contract.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

final class TrustDossierService
{
    /** The AtoM/Qubit root information object - never a real, reportable record. */
    private const ROOT_OBJECT_ID = 1;

    /**
     * The three per-record trust services this dossier composes READ-ONLY. The
     * authenticity report is the canonical resolver / published gate (it is the
     * truth-anchor front door and is always bound in this package); the other two
     * are optional contributors whose absence degrades only their own section.
     */
    public function __construct(
        private AuthenticityReportService $authenticity,
        private InferenceProvenanceService $inference,
        private PreservationTimelineService $preservation,
    ) {
    }

    /**
     * Build the consolidated trust dossier for a published record addressed by
     * numeric id or (possibly multi-segment) slug.
     *
     * Returns null when the record does not exist OR is not published (the
     * controller turns null into a 404, so an unpublished record is
     * indistinguishable from a missing one - the honest public contract). The
     * authenticity layer is the resolver of record: its null is the dossier's
     * null. Each of the other two layers is composed behind its own guard so a
     * missing / faulting sub-service degrades only its own section.
     *
     * Never throws.
     *
     * @return array{
     *     object: object,
     *     headline: array{verdict: string, label: string, statement: string},
     *     can_verify: list<string>,
     *     cannot_verify: list<string>,
     *     sections: array{
     *         authenticity: array<string,mixed>|null,
     *         inference: array<string,mixed>|null,
     *         preservation: array<string,mixed>|null
     *     },
     *     section_status: array{authenticity: bool, inference: bool, preservation: bool},
     *     links: array<string,string>,
     *     generated_at: string
     * }|null
     */
    public function dossier(string $idOrSlug): ?array
    {
        // The authenticity layer resolves the record AND applies the published
        // gate (delegated, never re-derived here). Null = unknown / unpublished
        // -> the whole dossier is null -> 404. This is the single resolve point.
        $auth = $this->safeReport(
            fn (): ?array => $this->authenticity->report($idOrSlug),
            'authenticity',
        );
        if ($auth === null) {
            return null;
        }

        $object = $auth['object'];
        $ioId   = (int) ($object->id ?? 0);

        // The AtoM/Qubit root information object (id=1) is never a real,
        // reportable record - the preservation sibling already excludes it. We
        // honour the same contract here so the dossier can never resolve the
        // root, treating it as not-found (a clean 404).
        if ($ioId === self::ROOT_OBJECT_ID) {
            return null;
        }

        $ref = ($object->slug ?? null) ?: ($ioId > 0 ? (string) $ioId : $idOrSlug);

        // The other two layers are optional contributors. Each is guarded so a
        // missing store / faulting sub-service degrades only its own section.
        $inference    = $this->safeReport(
            fn (): ?array => $this->inference->report($idOrSlug),
            'inference',
        );
        $preservation = $this->safeReport(
            fn (): ?array => $this->preservation->report($idOrSlug),
            'preservation',
        );

        return [
            'object'         => $object,
            'headline'       => $this->headline($auth, $inference, $preservation),
            // The authenticity layer already computes the honest "what we can /
            // cannot verify" lists from the live verdict; we surface them
            // verbatim (never re-derive) as the dossier's top-line statement.
            'can_verify'     => is_array($auth['can_verify'] ?? null) ? $auth['can_verify'] : [],
            'cannot_verify'  => is_array($auth['cannot_verify'] ?? null) ? $auth['cannot_verify'] : [],
            'sections'       => [
                'authenticity' => $auth,
                'inference'    => $inference,
                'preservation' => $preservation,
            ],
            'section_status' => [
                'authenticity' => true,                  // always present (it resolved the record)
                'inference'    => $inference !== null,
                'preservation' => $preservation !== null,
            ],
            'links'          => [
                'authenticity'  => $this->safeUrl('/authenticity/' . $ref),
                'inference'     => $this->safeUrl('/inference-provenance/' . $ref),
                'preservation'  => $this->safeUrl('/preservation-timeline/' . $ref),
                'dossier'       => $this->safeUrl('/trust-dossier/' . $ref),
                'dossier_json'  => $this->safeUrl('/trust-dossier/' . ($ioId > 0 ? (string) $ioId : $ref) . '.json'),
                'record'        => ($object->slug ?? null) ? $this->safeUrl((string) $object->slug) : '',
            ],
            'generated_at'   => gmdate('Y-m-d\TH:i:s\Z'),
        ];
    }

    /* ----------------------------------------------------------------- *
     * Honest top-line - derived ONLY from the sub-services' own verdicts.
     * ----------------------------------------------------------------- */

    /**
     * The honest dossier headline. It is computed STRICTLY from the verdicts the
     * sub-services already produced; it adds no new judgement of its own and never
     * overclaims. The authenticity confidence tier is the anchor (it carries the
     * live cryptographic verdict); the AI and preservation layers contribute
     * qualifying clauses only when they have something recorded.
     *
     * @param  array<string,mixed>      $auth
     * @param  array<string,mixed>|null $inference
     * @param  array<string,mixed>|null $preservation
     * @return array{verdict: string, label: string, statement: string}
     */
    private function headline(array $auth, ?array $inference, ?array $preservation): array
    {
        $verdict = is_string($auth['confidence'] ?? null)
            ? $auth['confidence']
            : AuthenticityReportService::CONFIDENCE_NONE;
        $label = is_string($auth['confidence_label'] ?? null)
            ? $auth['confidence_label']
            : __('None recorded');

        // The anchor sentence is the authenticity layer's own honest summary - we
        // never rewrite it into something stronger.
        $statement = is_string($auth['summary'] ?? null) ? $auth['summary'] : '';

        // Qualifying clauses, each true only when its layer recorded something.
        $aiCount = (int) ($inference['counts']['total'] ?? 0);
        if ($inference !== null && $aiCount > 0) {
            $pending = (int) ($inference['counts']['pending'] ?? 0);
            $statement .= ' ' . ($pending > 0
                ? __('AI processing steps are recorded for this record, with a human curator still accountable for any steps awaiting review.')
                : __('AI processing steps are recorded for this record, each reviewed by a human curator.'));
        }

        $presTotal = (int) ($preservation['counts']['total'] ?? 0);
        if ($preservation !== null && $presTotal > 0) {
            $failures = (int) ($preservation['counts']['failure'] ?? 0);
            $statement .= ' ' . ($failures > 0
                ? __('A preservation lifecycle is recorded for its digital objects, including at least one step that reported a failure (shown in the preservation section).')
                : __('A preservation lifecycle is recorded for its digital objects, with each recorded step and its outcome shown in the preservation section.'));
        }

        return [
            'verdict'   => $verdict,
            'label'     => $label,
            'statement' => trim($statement),
        ];
    }

    /* ----------------------------------------------------------------- *
     * Guards - a faulting sub-service degrades its section, never the page.
     * ----------------------------------------------------------------- */

    /**
     * Run one sub-service report() behind a guard. A null result (unknown /
     * unpublished / no store) or a thrown fault both degrade to null for that
     * section; the dossier as a whole still renders.
     *
     * @param  callable():(array<string,mixed>|null) $build
     * @return array<string,mixed>|null
     */
    private function safeReport(callable $build, string $layer): ?array
    {
        try {
            $report = $build();

            return is_array($report) ? $report : null;
        } catch (Throwable $e) {
            Log::warning('c2pa trust-dossier: ' . $layer . ' layer threw; section degraded', [
                'layer' => $layer,
                'err'   => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function safeUrl(string $path): string
    {
        if ($path === '') {
            return '';
        }

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
