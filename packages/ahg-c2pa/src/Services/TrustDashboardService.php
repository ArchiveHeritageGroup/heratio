<?php
/**
 * Heratio - public collection-wide TRUST DASHBOARD aggregator (issue #1209, north star).
 *
 * The "truth anchor" seen from orbit. Where AuthenticityReportService answers
 * "is THIS record verifiable?" and AuthenticityStatsService answers the
 * institution-level CONTENT-CREDENTIALS coverage question, this service answers
 * the broader public question the trust dashboard poses:
 *
 *   "Across everything published here, how much carries verifiable content
 *    credentials, how much is cryptographically signed, how much verifies, and
 *    how much of our metadata involved AI - with a human kept accountable?"
 *
 * It is a pure READ-ONLY aggregator over the signals that already exist:
 *
 *   - ahg_c2pa_provenance / ahg_c2pa_manifest  (content credentials / C2PA signing)
 *   - digital_object                           (the master-file denominator)
 *   - ahg_ai_inference / ahg_ai_override       (AI-inference coverage + review)
 *   - information_object + status              (the PUBLISHED denominator / gate)
 *
 * Every figure is scoped to PUBLISHED records only (status.type_id=158 /
 * status_id=160 - the same gate the public GLAM browse and the per-record
 * authenticity report use), and the synthetic root row (information_object.id=1)
 * is always excluded. It writes nothing, runs no AI, re-verifies nothing, and
 * performs only cheap aggregate COUNT / GROUP BY queries (no per-record loops,
 * no live signature crypto). Every table is Schema::hasTable-guarded and every
 * query block is try/catch-wrapped, so an install without the c2pa or
 * inference layer (or an unreachable DB) yields an honest zero/empty-state
 * shape rather than a 500.
 *
 * Honest framing is a hard requirement of this surface. The headline numbers
 * are always paired, in the view and in the JSON, with the standing caveat that
 * content credentials attest to a FILE's history - how it was captured and
 * handled - and NOT to the truthfulness of what the source itself depicts or
 * claims. "Signed" is never dressed up as "true".
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

final class TrustDashboardService
{
    /** Publication status taxonomy: status.type_id for "publication status". */
    private const PUBLICATION_STATUS_TYPE_ID = 158;

    /** status.status_id value that means "Published" (same gate as the GLAM browse). */
    private const PUBLISHED_STATUS_ID = 160;

    /** Synthetic AtoM/Qubit root information object; never a real, public record. */
    private const ROOT_IO_ID = 1;

    /** digital_object.usage_id for a master file (taxonomy 47); mirrors AuthenticityStatsService. */
    private const USAGE_MASTER = 140;

    /**
     * One short, plain-language caveat. Reused verbatim by the view + the JSON so
     * the honest framing can never drift between the two surfaces.
     */
    public const HONEST_CAVEAT = 'Content credentials attest to a file\'s history - how it was captured and handled - '
        . 'not that the content itself is true, accurate, or complete. A signed record is a verifiable record, '
        . 'not a guarantee of what the source depicts or claims.';

    /**
     * Whole-collection trust snapshot for the public dashboard.
     *
     * Every number is scoped to PUBLISHED records (root id=1 excluded). The shape
     * is fixed and always populated, so the controller + view never have to
     * branch on missing keys.
     *
     * @return array{
     *     generated_at: string,
     *     caveat: string,
     *     content_credentials: array<string,mixed>,
     *     ai_inference: array<string,mixed>,
     *     has_any_signal: bool
     * }
     */
    public function snapshot(): array
    {
        $publishedRecords = $this->publishedRecordCount();

        $cc = $this->contentCredentials($publishedRecords);
        $ai = $this->aiInference($publishedRecords);

        $hasAny = ($cc['records_with_credentials'] > 0)
            || ($cc['masters_signed'] > 0)
            || ($ai['records_with_ai'] > 0);

        return [
            'generated_at'        => gmdate('Y-m-d\TH:i:s\Z'),
            'caveat'              => self::HONEST_CAVEAT,
            'content_credentials' => $cc,
            'ai_inference'        => $ai,
            'has_any_signal'      => $hasAny,
        ];
    }

    /* ----------------------------------------------------------------- *
     * Content-credentials half.
     * ----------------------------------------------------------------- */

    /**
     * @return array{
     *     layer_installed: bool, can_sign: bool, reason: ?string,
     *     published_records: int, records_with_credentials: int, records_signed: int,
     *     masters_total: int, masters_signed: int, masters_unsigned: int,
     *     signed_verified: int, signed_failed: int, manifests_total: int,
     *     coverage_pct: float, credentials_pct: float, verified_pct: float,
     *     last_signed_at: ?string, issuers: int
     * }
     */
    private function contentCredentials(int $publishedRecords): array
    {
        $out = [
            'layer_installed'          => false,
            'can_sign'                 => $this->canSign(),
            'reason'                   => null,
            'published_records'        => $publishedRecords,
            'records_with_credentials' => 0,
            'records_signed'           => 0,
            'masters_total'            => 0,
            'masters_signed'           => 0,
            'masters_unsigned'         => 0,
            'signed_verified'          => 0,
            'signed_failed'            => 0,
            'manifests_total'          => 0,
            'coverage_pct'             => 0.0,
            'credentials_pct'          => 0.0,
            'verified_pct'             => 0.0,
            'last_signed_at'           => null,
            'issuers'                  => 0,
        ];

        if (!$this->tableExists('ahg_c2pa_provenance')) {
            $out['reason'] = 'not-installed';

            return $out;
        }
        $out['layer_installed'] = true;

        // Denominator: master digital objects on PUBLISHED records.
        $out['masters_total'] = $this->publishedMasterCount();

        try {
            // Published provenance rows, joined to the published IO set so a
            // draft/embargoed record never inflates the public figures.
            $prov = $this->publishedProvenanceQuery();

            // Distinct PUBLISHED records that carry ANY content credentials.
            $out['records_with_credentials'] = (int) (clone $prov)
                ->distinct()
                ->count('p.information_object_id');

            // Distinct PUBLISHED records with a SIGNED credential bound.
            $out['records_signed'] = (int) (clone $prov)
                ->whereNotNull('p.manifest_id')
                ->distinct()
                ->count('p.information_object_id');

            // Distinct PUBLISHED master files covered by a signed credential.
            $out['masters_signed'] = (int) (clone $prov)
                ->whereNotNull('p.digital_object_id')
                ->whereNotNull('p.manifest_id')
                ->distinct()
                ->count('p.digital_object_id');

            $out['masters_unsigned'] = max(0, $out['masters_total'] - $out['masters_signed']);

            // Verified-vs-failed split from the cached sign_status only (cheap
            // GROUP BY). We never re-run signature crypto on a dashboard load -
            // the per-record /authenticity page does the live re-verify.
            [$verified, $failed]    = $this->signedSplit($prov, $out['masters_signed']);
            $out['signed_verified'] = $verified;
            $out['signed_failed']   = $failed;

            $out['last_signed_at'] = $this->lastSignedAt();
        } catch (Throwable $e) {
            Log::warning('c2pa trust dashboard: content-credentials half failed', ['err' => $e->getMessage()]);
            $out['reason'] = 'unavailable';

            return $out;
        }

        // Manifest-level detail (optional table): how many distinct signing keys.
        if ($this->tableExists('ahg_c2pa_manifest')) {
            try {
                $out['manifests_total'] = (int) DB::table('ahg_c2pa_manifest')->count();
                $out['issuers']         = (int) DB::table('ahg_c2pa_manifest')
                    ->whereNotNull('kid')
                    ->where('kid', '<>', '')
                    ->distinct()
                    ->count('kid');
            } catch (Throwable) {
                // leave manifest detail at its defaults
            }
        }

        if ($out['masters_total'] > 0) {
            $out['coverage_pct'] = round($out['masters_signed'] / $out['masters_total'] * 100, 1);
        }
        if ($publishedRecords > 0) {
            $out['credentials_pct'] = round($out['records_with_credentials'] / $publishedRecords * 100, 1);
        }
        if ($out['masters_signed'] > 0) {
            $out['verified_pct'] = round($out['signed_verified'] / $out['masters_signed'] * 100, 1);
        }

        if ($out['records_with_credentials'] === 0 && $out['reason'] === null) {
            $out['reason'] = $publishedRecords > 0 ? 'no-credentials-yet' : 'no-records';
        }

        return $out;
    }

    /**
     * A base query over ahg_c2pa_provenance, restricted to provenance rows that
     * belong to a PUBLISHED, non-root information object. Returned as a fresh
     * builder the caller can clone + refine. The published gate is enforced with
     * an EXISTS sub-select against status so it stays a single cheap predicate.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    private function publishedProvenanceQuery()
    {
        return DB::table('ahg_c2pa_provenance as p')
            ->where('p.information_object_id', '<>', self::ROOT_IO_ID)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('status as s')
                    ->whereColumn('s.object_id', 'p.information_object_id')
                    ->where('s.type_id', self::PUBLICATION_STATUS_TYPE_ID)
                    ->where('s.status_id', self::PUBLISHED_STATUS_ID);
            });
    }

    /**
     * Verified-vs-failed split of the signed master files, derived from the
     * cached sign_status column only. Any explicit failure marker counts as
     * failed; everything else (the normal 'signed' state) counts as verifiable.
     * No live crypto - that is the per-record page's job.
     *
     * @param  \Illuminate\Database\Query\Builder $prov fresh published-provenance query
     * @return array{0:int,1:int} [verified, failed]
     */
    private function signedSplit($prov, int $signedMasters): array
    {
        if ($signedMasters <= 0) {
            return [0, 0];
        }

        try {
            $rows = (clone $prov)
                ->whereNotNull('p.digital_object_id')
                ->whereNotNull('p.manifest_id')
                ->select('p.sign_status', DB::raw('COUNT(DISTINCT p.digital_object_id) as c'))
                ->groupBy('p.sign_status')
                ->get();

            $failed = 0;
            foreach ($rows as $row) {
                $status = strtolower((string) ($row->sign_status ?? ''));
                if (in_array($status, ['invalid', 'failed', 'tampered', 'error'], true)) {
                    $failed += (int) $row->c;
                }
            }

            $failed   = min($failed, $signedMasters);
            $verified = max(0, $signedMasters - $failed);

            return [$verified, $failed];
        } catch (Throwable) {
            // Honest fallback: treat all signed masters as verifiable.
            return [$signedMasters, 0];
        }
    }

    /**
     * Most recent signing timestamp across signed provenance rows on published
     * records. Read-only; one indexed ORDER BY ... LIMIT 1.
     */
    private function lastSignedAt(): ?string
    {
        try {
            $row = $this->publishedProvenanceQuery()
                ->whereNotNull('p.manifest_id')
                ->orderByDesc('p.updated_at')
                ->first(['p.updated_at', 'p.created_at']);
        } catch (Throwable) {
            return null;
        }

        if ($row === null) {
            return null;
        }

        $ts = $row->updated_at ?? $row->created_at ?? null;

        return is_string($ts) && $ts !== '' ? $ts : null;
    }

    /* ----------------------------------------------------------------- *
     * AI-inference half.
     * ----------------------------------------------------------------- */

    /**
     * @return array{
     *     layer_installed: bool, published_records: int, records_with_ai: int,
     *     inferences_total: int, reviewed: int, pending: int,
     *     ai_coverage_pct: float, reviewed_pct: float
     * }
     */
    private function aiInference(int $publishedRecords): array
    {
        $out = [
            'layer_installed'   => false,
            'published_records' => $publishedRecords,
            'records_with_ai'   => 0,
            'inferences_total'  => 0,
            'reviewed'          => 0,
            'pending'           => 0,
            'ai_coverage_pct'   => 0.0,
            'reviewed_pct'      => 0.0,
        ];

        if (!$this->tableExists('ahg_ai_inference')) {
            return $out;
        }
        $out['layer_installed'] = true;

        try {
            // Base query: inference rows targeting a PUBLISHED, non-root IO.
            $infer = DB::table('ahg_ai_inference as i')
                ->where('i.target_entity_type', 'information_object')
                ->where('i.target_entity_id', '<>', self::ROOT_IO_ID)
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('status as s')
                        ->whereColumn('s.object_id', 'i.target_entity_id')
                        ->where('s.type_id', self::PUBLICATION_STATUS_TYPE_ID)
                        ->where('s.status_id', self::PUBLISHED_STATUS_ID);
                });

            $out['inferences_total'] = (int) (clone $infer)->count();
            $out['records_with_ai']  = (int) (clone $infer)->distinct()->count('i.target_entity_id');

            // Reviewed = inferences that carry at least one human override
            // (accepted / corrected / rejected). Pending = the remainder. A
            // single EXISTS against ahg_ai_override keeps this O(1) in joins.
            if ($this->tableExists('ahg_ai_override') && $out['inferences_total'] > 0) {
                $out['reviewed'] = (int) (clone $infer)
                    ->whereExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('ahg_ai_override as o')
                            ->whereColumn('o.inference_id', 'i.id');
                    })
                    ->count();
            }
            $out['pending'] = max(0, $out['inferences_total'] - $out['reviewed']);
        } catch (Throwable $e) {
            Log::warning('c2pa trust dashboard: ai-inference half failed', ['err' => $e->getMessage()]);

            return $out;
        }

        if ($publishedRecords > 0) {
            $out['ai_coverage_pct'] = round($out['records_with_ai'] / $publishedRecords * 100, 1);
        }
        if ($out['inferences_total'] > 0) {
            $out['reviewed_pct'] = round($out['reviewed'] / $out['inferences_total'] * 100, 1);
        }

        return $out;
    }

    /* ----------------------------------------------------------------- *
     * Denominators + capability + guards.
     * ----------------------------------------------------------------- */

    /**
     * Count PUBLISHED information objects (root id=1 excluded) - the denominator
     * for the "% of records ..." figures. One cheap COUNT over status joined to
     * the published gate.
     */
    private function publishedRecordCount(): int
    {
        if (!$this->tableExists('status')) {
            return 0;
        }

        try {
            return (int) DB::table('status')
                ->where('type_id', self::PUBLICATION_STATUS_TYPE_ID)
                ->where('status_id', self::PUBLISHED_STATUS_ID)
                ->where('object_id', '<>', self::ROOT_IO_ID)
                ->distinct()
                ->count('object_id');
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Count master digital objects belonging to PUBLISHED records (root id=1
     * excluded) - the denominator for the "% of files signed" figure. Mirrors
     * the master selection used by AuthenticityStatsService + the backfill
     * command (parentless rows whose usage is "master" (140) or unset).
     */
    private function publishedMasterCount(): int
    {
        if (!$this->tableExists('digital_object') || !$this->tableExists('status')) {
            return 0;
        }

        try {
            return (int) DB::table('digital_object as d')
                ->whereNull('d.parent_id')
                ->where('d.object_id', '<>', self::ROOT_IO_ID)
                ->where(function ($w) {
                    $w->where('d.usage_id', self::USAGE_MASTER)->orWhereNull('d.usage_id');
                })
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('status as s')
                        ->whereColumn('s.object_id', 'd.object_id')
                        ->where('s.type_id', self::PUBLICATION_STATUS_TYPE_ID)
                        ->where('s.status_id', self::PUBLISHED_STATUS_ID);
                })
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Whether this host can cryptographically sign at all (ext-sodium). Kept
     * local so the read-only dashboard has no dependency on the write/sign path.
     */
    private function canSign(): bool
    {
        return extension_loaded('sodium') || function_exists('sodium_crypto_sign');
    }

    private function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }
}
