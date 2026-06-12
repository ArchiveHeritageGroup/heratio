<?php
/**
 * Heratio - public, catalogue-wide TRANSPARENCY REPORT aggregator (issue #1209).
 *
 * The PUBLIC counterpart to the operator-only admin trust console
 * (/admin/trust-console) and to the per-record trust dossier (/trust-dossier).
 * Where the trust console is the institution's internal "where are our gaps"
 * view and the dossier answers "what can we attest about THIS record", this
 * service answers, for any visitor, the single honest question:
 *
 *   "Across everything we have published, what can - and cannot - we yet attest
 *    about our collection?"
 *
 * It is a pure READ-ONLY aggregator over signals that already exist, scoped to
 * the PUBLISHED catalogue (status.type_id=158 / status_id=160, synthetic root
 * information_object.id=1 excluded - the same gate the GLAM browse and every
 * other public c2pa surface use). It writes nothing, runs no AI, performs no
 * preservation action, and re-verifies nothing. Every figure is a cheap
 * aggregate COUNT / EXISTS - no per-row scan of the whole catalogue. Every
 * table is Schema::hasTable-guarded and every query block is try/catch-wrapped,
 * so a fresh install (or an unreachable DB) yields a calm zero-state shape
 * rather than a 500.
 *
 * Five dimensions, each a headline number + an honest share:
 *
 *   1. Content credentials - published records / master files carrying a C2PA
 *      manifest. REUSED verbatim from TrustDashboardService (single source of
 *      truth with the /trust dashboard); not recomputed here.
 *   2. AI provenance - published records with at least one logged AI inference
 *      (ahg_ai_inference). Also REUSED from TrustDashboardService.
 *   3. Integrity - published master files with a fixity (checksum) baseline on
 *      file, and the verified share, from preservation_fixity_check joined to
 *      the published master set. NEW aggregate.
 *   4. Preservation - published digital objects with at least one recorded
 *      PREMIS preservation event (preservation_event). NEW aggregate.
 *   5. Accessibility - published image surrogates carrying a human-authored
 *      text alternative in the dedicated image_alt_text store. NEW aggregate,
 *      using the same image-selection heuristic as ahg-core's
 *      AccessibilityReportService (the genuine WCAG 1.1.1 signal only - the
 *      curated store, not the IPTC/XMP caption fallback).
 *
 * Honest framing is a hard requirement. Each dimension carries a one-line
 * statement that frames the number without overclaiming, and gaps are always
 * shown as gaps. "Signed" / "has a fixity baseline" / "has alt text" is never
 * dressed up as more than it is.
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

final class TransparencyReportService
{
    /** Publication status taxonomy: status.type_id for "publication status". */
    private const PUBLICATION_STATUS_TYPE_ID = 158;

    /** status.status_id value meaning "Published" (same gate as the GLAM browse). */
    private const PUBLISHED_STATUS_ID = 160;

    /** Synthetic AtoM/Qubit root information object; never a real, public record. */
    private const ROOT_IO_ID = 1;

    /** digital_object.usage_id for a master file (taxonomy 47); mirrors TrustDashboardService. */
    private const USAGE_MASTER = 140;

    /**
     * Image filename extensions used to classify surrogates that carry no mime
     * type. Mirrors ahg-core AccessibilityReportService::EXT_IMAGE so the public
     * transparency figure agrees with the admin accessibility report.
     *
     * @var list<string>
     */
    private const EXT_IMAGE = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'tif', 'tiff', 'jp2', 'bmp', 'heic'];

    /**
     * One short, plain-language standing caveat. Reused verbatim by the view and
     * the JSON so the honest framing can never drift between the two surfaces.
     */
    public const HONEST_CAVEAT = 'This is what we can - and cannot yet - attest about our collection. '
        . 'Each figure below counts only what we have actually recorded. A gap means a signal has not '
        . 'been captured yet, not that anything is wrong. Authenticity, integrity and preservation '
        . 'signals describe how a file was handled; they do not vouch for the truth of what a source depicts.';

    public function __construct(private TrustDashboardService $trust)
    {
    }

    /**
     * Whole-catalogue transparency snapshot for the public report.
     *
     * The shape is fixed and always fully populated (every dimension present,
     * even at zero), so the controller + view never have to branch on missing
     * keys. Every number is scoped to PUBLISHED records (root id=1 excluded).
     *
     * @return array{
     *     generated_at: string,
     *     caveat: string,
     *     published_records: int,
     *     published_masters: int,
     *     content_credentials: array<string,mixed>,
     *     ai_provenance: array<string,mixed>,
     *     integrity: array<string,mixed>,
     *     preservation: array<string,mixed>,
     *     accessibility: array<string,mixed>,
     *     has_any_signal: bool
     * }
     */
    public function snapshot(): array
    {
        // Reuse the trust dashboard's fully-guarded snapshot for the two layers
        // it already owns, so the content-credentials + AI numbers are identical
        // to the /trust page (one source of truth). Guard the call itself so a
        // fault there degrades only those two dimensions.
        $trust = $this->safeTrustSnapshot();

        $publishedRecords = (int) ($trust['content_credentials']['published_records'] ?? 0);
        $publishedMasters = (int) ($trust['content_credentials']['masters_total'] ?? 0);

        $cc = $this->contentCredentialsDimension($trust, $publishedRecords, $publishedMasters);
        $ai = $this->aiProvenanceDimension($trust, $publishedRecords);
        $integrity     = $this->integrityDimension($publishedMasters);
        $preservation  = $this->preservationDimension();
        $accessibility = $this->accessibilityDimension();

        $hasAny = $cc['count'] > 0
            || $ai['count'] > 0
            || $integrity['count'] > 0
            || $preservation['count'] > 0
            || $accessibility['count'] > 0;

        return [
            'generated_at'        => gmdate('Y-m-d\TH:i:s\Z'),
            'caveat'              => self::HONEST_CAVEAT,
            'published_records'   => $publishedRecords,
            'published_masters'   => $publishedMasters,
            'content_credentials' => $cc,
            'ai_provenance'       => $ai,
            'integrity'           => $integrity,
            'preservation'        => $preservation,
            'accessibility'       => $accessibility,
            'has_any_signal'      => $hasAny,
        ];
    }

    /* ----------------------------------------------------------------- *
     * Reused dimensions (content credentials + AI provenance).
     * ----------------------------------------------------------------- */

    /**
     * Content-credentials dimension, projected from the reused trust snapshot.
     * Headline = published records carrying any content credential; share = of
     * all published records. Master-file signing detail is carried alongside.
     *
     * @param  array<string,mixed> $trust
     * @return array<string,mixed>
     */
    private function contentCredentialsDimension(array $trust, int $publishedRecords, int $publishedMasters): array
    {
        $cc = is_array($trust['content_credentials'] ?? null) ? $trust['content_credentials'] : [];

        $withCreds = (int) ($cc['records_with_credentials'] ?? 0);

        return [
            'key'          => 'content_credentials',
            'count'        => $withCreds,
            'total'        => $publishedRecords,
            'share_pct'    => $this->share($withCreds, $publishedRecords),
            'unit'         => 'records',
            'installed'    => (bool) ($cc['layer_installed'] ?? false),
            // Extra colour, all already computed by the trust service.
            'masters_total'  => $publishedMasters,
            'masters_signed' => (int) ($cc['masters_signed'] ?? 0),
            'masters_pct'    => (float) ($cc['coverage_pct'] ?? 0.0),
            'records_signed' => (int) ($cc['records_signed'] ?? 0),
            'verified'       => (int) ($cc['signed_verified'] ?? 0),
            'failed'         => (int) ($cc['signed_failed'] ?? 0),
        ];
    }

    /**
     * AI-provenance dimension, projected from the reused trust snapshot.
     * Headline = published records with at least one logged AI inference; share
     * = of all published records. This is the institution being OPEN about where
     * AI touched the record, not a claim that the AI was right.
     *
     * @param  array<string,mixed> $trust
     * @return array<string,mixed>
     */
    private function aiProvenanceDimension(array $trust, int $publishedRecords): array
    {
        $ai = is_array($trust['ai_inference'] ?? null) ? $trust['ai_inference'] : [];

        $recordsWithAi = (int) ($ai['records_with_ai'] ?? 0);

        return [
            'key'             => 'ai_provenance',
            'count'           => $recordsWithAi,
            'total'           => $publishedRecords,
            'share_pct'       => $this->share($recordsWithAi, $publishedRecords),
            'unit'            => 'records',
            'installed'       => (bool) ($ai['layer_installed'] ?? false),
            'inferences_total'=> (int) ($ai['inferences_total'] ?? 0),
            'reviewed'        => (int) ($ai['reviewed'] ?? 0),
            'reviewed_pct'    => (float) ($ai['reviewed_pct'] ?? 0.0),
        ];
    }

    /* ----------------------------------------------------------------- *
     * New dimensions (integrity + preservation + accessibility).
     * ----------------------------------------------------------------- */

    /**
     * Integrity dimension. Headline = PUBLISHED master files that have a fixity
     * (checksum) baseline on record (>= 1 row in preservation_fixity_check);
     * share = of all published master files. We also surface the VERIFIED share:
     * of those with a baseline, how many had no failing check on record. Two
     * cheap aggregates, no per-row crypto re-check (that is a preservation
     * worker's job).
     *
     * @return array<string,mixed>
     */
    private function integrityDimension(int $publishedMasters): array
    {
        $out = [
            'key'          => 'integrity',
            'count'        => 0,
            'total'        => $publishedMasters,
            'share_pct'    => 0.0,
            'unit'         => 'master files',
            'installed'    => false,
            'verified'     => 0,
            'verified_pct' => 0.0,
        ];

        if (! $this->tableExists('preservation_fixity_check')
            || ! $this->tableExists('digital_object')
            || ! $this->tableExists('status')) {
            return $out;
        }
        $out['installed'] = true;

        try {
            // Master files (on published records) that carry at least one fixity
            // check. EXISTS keeps it a single bounded predicate over the
            // published-master set.
            $withBaseline = (int) $this->publishedMasterQuery()
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('preservation_fixity_check as f')
                        ->whereColumn('f.digital_object_id', 'd.id');
                })
                ->count();

            $out['count'] = $withBaseline;

            // Of those, how many had NO failing check on record (treated as the
            // verified baseline). A failure marker counts the file out. Cheap
            // EXISTS for the failure condition, no live checksum re-computation.
            $failedClause = function ($q) {
                $q->select(DB::raw(1))
                    ->from('preservation_fixity_check as f')
                    ->whereColumn('f.digital_object_id', 'd.id')
                    ->whereIn(DB::raw('LOWER(COALESCE(f.status, ""))'), ['fail', 'failed', 'invalid', 'mismatch', 'error', 'tampered']);
            };

            $out['verified'] = (int) $this->publishedMasterQuery()
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('preservation_fixity_check as f')
                        ->whereColumn('f.digital_object_id', 'd.id');
                })
                ->whereNotExists($failedClause)
                ->count();
        } catch (Throwable $e) {
            Log::warning('c2pa transparency: integrity dimension failed', ['err' => $e->getMessage()]);

            return $out;
        }

        $out['share_pct'] = $this->share($out['count'], $publishedMasters);
        if ($out['count'] > 0) {
            $out['verified_pct'] = $this->share($out['verified'], $out['count']);
        }

        return $out;
    }

    /**
     * Preservation dimension. Headline = PUBLISHED master digital objects that
     * have at least one recorded PREMIS preservation event (preservation_event);
     * share = of all published master files. A single EXISTS against
     * preservation_event (keyed on the digital object) over the published-master
     * set, plus a bounded total-events count.
     *
     * @return array<string,mixed>
     */
    private function preservationDimension(): array
    {
        $publishedMasters = $this->publishedMasterCount();

        $out = [
            'key'          => 'preservation',
            'count'        => 0,
            'total'        => $publishedMasters,
            'share_pct'    => 0.0,
            'unit'         => 'objects',
            'installed'    => false,
            'events_total' => 0,
        ];

        if (! $this->tableExists('preservation_event')
            || ! $this->tableExists('digital_object')
            || ! $this->tableExists('status')) {
            return $out;
        }
        $out['installed'] = true;

        try {
            $withEvents = (int) $this->publishedMasterQuery()
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('preservation_event as e')
                        ->whereColumn('e.digital_object_id', 'd.id');
                })
                ->count();

            $out['count'] = $withEvents;

            // Total recorded preservation events on published material (a single
            // COUNT bounded by a published-master EXISTS, no grouping).
            $out['events_total'] = (int) DB::table('preservation_event as e')
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('digital_object as d')
                        ->whereColumn('d.id', 'e.digital_object_id')
                        ->whereNull('d.parent_id')
                        ->where('d.object_id', '<>', self::ROOT_IO_ID)
                        ->where(function ($w) {
                            $w->where('d.usage_id', self::USAGE_MASTER)->orWhereNull('d.usage_id');
                        })
                        ->whereExists(function ($s) {
                            $s->select(DB::raw(1))
                                ->from('status as st')
                                ->whereColumn('st.object_id', 'd.object_id')
                                ->where('st.type_id', self::PUBLICATION_STATUS_TYPE_ID)
                                ->where('st.status_id', self::PUBLISHED_STATUS_ID);
                        });
                })
                ->count();
        } catch (Throwable $e) {
            Log::warning('c2pa transparency: preservation dimension failed', ['err' => $e->getMessage()]);

            return $out;
        }

        $out['share_pct'] = $this->share($out['count'], $publishedMasters);

        return $out;
    }

    /**
     * Accessibility dimension. Headline = PUBLISHED image surrogates that carry a
     * genuine human-authored text alternative in the dedicated image_alt_text
     * store; share = of all published image surrogates. This is the real WCAG
     * 1.1.1 signal (the curated store), matching ahg-core's
     * AccessibilityReportService - the embedded IPTC/XMP caption fallback is
     * deliberately NOT counted here, to avoid overclaiming.
     *
     * @return array<string,mixed>
     */
    private function accessibilityDimension(): array
    {
        $out = [
            'key'          => 'accessibility',
            'count'        => 0,
            'total'        => 0,
            'share_pct'    => 0.0,
            'unit'         => 'images',
            'installed'    => false,
        ];

        if (! $this->tableExists('digital_object')
            || ! $this->tableExists('status')) {
            return $out;
        }

        try {
            $totalImages = (int) $this->publishedImageQuery()->distinct()->count('d.id');
            $out['total'] = $totalImages;

            if ($this->tableExists('image_alt_text')) {
                $out['installed'] = true;

                $withAlt = (int) $this->publishedImageQuery()
                    ->whereExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('image_alt_text as a')
                            ->whereColumn('a.digital_object_id', 'd.id')
                            ->whereRaw('TRIM(COALESCE(a.alt_text, "")) <> ""');
                    })
                    ->distinct()
                    ->count('d.id');

                $out['count'] = min($withAlt, $totalImages);
            }
        } catch (Throwable $e) {
            Log::warning('c2pa transparency: accessibility dimension failed', ['err' => $e->getMessage()]);

            return $out;
        }

        $out['share_pct'] = $this->share($out['count'], $out['total']);

        return $out;
    }

    /* ----------------------------------------------------------------- *
     * Shared denominators + guarded query builders.
     * ----------------------------------------------------------------- */

    /**
     * Base query over master digital objects (parentless, master/unset usage)
     * belonging to PUBLISHED, non-root records. Returned aliased as `d` so the
     * dimension builders can attach an EXISTS sub-select cheaply.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    private function publishedMasterQuery()
    {
        return DB::table('digital_object as d')
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
            });
    }

    /**
     * Base query over IMAGE digital objects on PUBLISHED, non-root records.
     * Image selection mirrors ahg-core AccessibilityReportService: mime_type
     * LIKE 'image/%' OR a known image filename extension. Aliased as `d`.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    private function publishedImageQuery()
    {
        return DB::table('digital_object as d')
            ->where('d.object_id', '<>', self::ROOT_IO_ID)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('status as s')
                    ->whereColumn('s.object_id', 'd.object_id')
                    ->where('s.type_id', self::PUBLICATION_STATUS_TYPE_ID)
                    ->where('s.status_id', self::PUBLISHED_STATUS_ID);
            })
            ->where(function ($w) {
                $w->where('d.mime_type', 'like', 'image/%')
                    ->orWhere(function ($x) {
                        foreach (self::EXT_IMAGE as $ext) {
                            $x->orWhereRaw('LOWER(d.name) LIKE ?', ['%.' . strtolower($ext)]);
                        }
                    });
            });
    }

    /**
     * Count master digital objects belonging to PUBLISHED records (root id=1
     * excluded). Standalone helper for dimensions that need the denominator
     * before building their EXISTS query.
     */
    private function publishedMasterCount(): int
    {
        if (! $this->tableExists('digital_object') || ! $this->tableExists('status')) {
            return 0;
        }

        try {
            return (int) $this->publishedMasterQuery()->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Call TrustDashboardService::snapshot() without letting a fault escape. The
     * trust service is already fully guarded; this is belt-and-braces so the
     * reused two dimensions degrade to empty rather than breaking the report.
     *
     * @return array<string,mixed>
     */
    private function safeTrustSnapshot(): array
    {
        try {
            return $this->trust->snapshot();
        } catch (Throwable $e) {
            Log::warning('c2pa transparency: trust snapshot threw; degrading two dimensions', ['err' => $e->getMessage()]);

            return [
                'content_credentials' => [],
                'ai_inference'        => [],
            ];
        }
    }

    /** A bounded, rounded share percentage. Returns 0.0 for an empty denominator. */
    private function share(int $part, int $base): float
    {
        if ($base <= 0) {
            return 0.0;
        }

        return round(min(100, max(0, $part / $base * 100)), 1);
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
