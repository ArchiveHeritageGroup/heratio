<?php
/**
 * Heratio - public BROWSE of provenance-verified records (issue #1209, north star).
 *
 * The verifiable corpus, made walkable. Where TrustDashboardService rolls the
 * whole collection into a handful of aggregate numbers, and
 * AuthenticityReportService answers "is THIS record verifiable?" for a single
 * record, THIS service answers the in-between question a researcher, journalist
 * or auditor actually asks:
 *
 *   "Show me the published records here that DO carry content credentials, so I
 *    can pick one and check it."
 *
 * It is a pure READ-ONLY, BOUNDED, PAGINATED reader over the signals that
 * already exist:
 *
 *   - ahg_c2pa_provenance  (which published records carry content credentials,
 *                           and which of those are C2PA-signed / verifiable)
 *   - information_object + information_object_i18n + slug  (record identity)
 *   - status               (the PUBLISHED gate: type_id=158 / status_id=160)
 *
 * Every page is a single small, indexed, GROUP-BY query over a bounded LIMIT/
 * OFFSET window - never a full-catalogue scan. The root row
 * (information_object.id=1) is always excluded, and only PUBLISHED records are
 * ever listed (the same gate the public GLAM browse, the trust dashboard and the
 * per-record authenticity report use), so a draft/embargoed record can never
 * leak. It writes nothing, runs no AI, re-verifies nothing, and runs no live
 * signature crypto: each record's per-row "verified" hint comes from the cached
 * sign_status column, and the authoritative live re-verify stays on the
 * per-record /authenticity page this list links to.
 *
 * Every table is Schema::hasTable-guarded and every query block is try/catch
 * wrapped, so an install without the c2pa layer (or an unreachable DB) yields an
 * honest empty page rather than a 500.
 *
 * Honest framing is a hard requirement of this surface. The list never claims a
 * record is "true": a badge says only that the record carries content
 * credentials, that they are signed, or that the cached signature last checked
 * out - and the standing caveat (content credentials attest to a file's HISTORY,
 * not to the truthfulness of what the source depicts) travels with the page.
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
 * Bounded, paginated, read-only browse over the provenance-verified corpus.
 * Owns no table; reads ahg_c2pa_provenance (joined to the published IO set) and
 * returns a fixed page shape the controller + view + JSON never have to branch
 * on for missing keys.
 */
final class VerifiedRecordsService
{
    /** Publication status taxonomy: status.type_id for "publication status". */
    private const PUBLICATION_STATUS_TYPE_ID = 158;

    /** status.status_id value that means "Published" (same gate as the GLAM browse). */
    private const PUBLISHED_STATUS_ID = 160;

    /** Synthetic AtoM/Qubit root information object; never a real, public record. */
    private const ROOT_IO_ID = 1;

    /** Default page size. Bounded; never the whole catalogue. */
    public const DEFAULT_PER_PAGE = 24;

    /** Hard ceiling on page size so a hand-crafted ?per_page= can never widen the scan. */
    private const MAX_PER_PAGE = 96;

    /**
     * The honest, plain-language filters offered on the browse. Each maps to a
     * single cheap predicate over the cached provenance columns.
     */
    public const FILTERS = ['all', 'has-credentials', 'signed', 'verified'];

    /** Sign-status values that mean a cached signature did NOT check out. */
    private const FAILED_SIGN_STATUSES = ['invalid', 'failed', 'tampered', 'error'];

    /**
     * One short, plain-language caveat, kept identical to the trust dashboard so
     * the honest framing can never drift between the two public surfaces.
     */
    public const HONEST_CAVEAT = TrustDashboardService::HONEST_CAVEAT;

    /**
     * Build one bounded page of provenance-verified published records.
     *
     * @param  string $filter one of self::FILTERS; anything else falls back to 'all'
     * @param  int    $page   1-based page number (clamped to >= 1)
     * @param  int    $perPage page size (clamped to [1, MAX_PER_PAGE])
     * @return array{
     *     filter: string,
     *     filters: list<array{key: string, label: string, active: bool}>,
     *     page: int,
     *     per_page: int,
     *     total: int,
     *     last_page: int,
     *     from: int,
     *     to: int,
     *     has_prev: bool,
     *     has_next: bool,
     *     records: list<array<string,mixed>>,
     *     layer_installed: bool,
     *     caveat: string,
     *     generated_at: string
     * }
     */
    public function page(string $filter = 'all', int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): array
    {
        $filter  = in_array($filter, self::FILTERS, true) ? $filter : 'all';
        $page    = max(1, $page);
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPage));

        $out = [
            'filter'          => $filter,
            'filters'         => $this->filterMeta($filter),
            'page'            => $page,
            'per_page'        => $perPage,
            'total'           => 0,
            'last_page'       => 1,
            'from'            => 0,
            'to'              => 0,
            'has_prev'        => false,
            'has_next'        => false,
            'records'         => [],
            'layer_installed' => false,
            'caveat'          => self::HONEST_CAVEAT,
            'generated_at'    => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        if (!$this->tableExists('ahg_c2pa_provenance')
            || !$this->tableExists('information_object')
            || !$this->tableExists('status')) {
            // Layer (or core) not present: honest empty page, never a 500.
            return $out;
        }
        $out['layer_installed'] = true;

        try {
            // Total distinct published records matching the filter - the only
            // count we run, and it is a single indexed COUNT(DISTINCT ...).
            $total = (int) $this->matchingRecordsQuery($filter)
                ->distinct()
                ->count('p.information_object_id');

            $out['total']     = $total;
            $out['last_page'] = max(1, (int) ceil($total / $perPage));

            if ($total === 0) {
                return $out;
            }

            // Clamp the requested page to the available range so an out-of-band
            // ?page= can never run an empty OFFSET past the end.
            $page         = min($page, $out['last_page']);
            $out['page']  = $page;
            $offset       = ($page - 1) * $perPage;

            $out['records']  = $this->loadPage($filter, $offset, $perPage);
            $out['from']     = $offset + 1;
            $out['to']       = $offset + count($out['records']);
            $out['has_prev'] = $page > 1;
            $out['has_next'] = $page < $out['last_page'];
        } catch (Throwable $e) {
            Log::warning('c2pa verified-records: page build failed', [
                'filter' => $filter,
                'err'    => $e->getMessage(),
            ]);

            // Reset to the honest empty page shape (layer is installed but the
            // read faulted); never surface a 500 on a public trust surface.
            $out['total']     = 0;
            $out['last_page'] = 1;
            $out['records']   = [];
            $out['from']      = 0;
            $out['to']        = 0;
            $out['has_prev']  = false;
            $out['has_next']  = false;
        }

        return $out;
    }

    /* ----------------------------------------------------------------- *
     * Query construction.
     * ----------------------------------------------------------------- */

    /**
     * Base query over ahg_c2pa_provenance restricted to PUBLISHED, non-root
     * information objects, with the chosen filter applied. Returned as a fresh
     * builder the caller can clone, count, or page. The published gate is an
     * EXISTS sub-select against status so it stays one cheap predicate.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    private function matchingRecordsQuery(string $filter)
    {
        $q = DB::table('ahg_c2pa_provenance as p')
            ->where('p.information_object_id', '<>', self::ROOT_IO_ID)
            ->whereExists(function ($w) {
                $w->select(DB::raw(1))
                    ->from('status as s')
                    ->whereColumn('s.object_id', 'p.information_object_id')
                    ->where('s.type_id', self::PUBLICATION_STATUS_TYPE_ID)
                    ->where('s.status_id', self::PUBLISHED_STATUS_ID);
            });

        return $this->applyFilter($q, $filter);
    }

    /**
     * Narrow a provenance query to the chosen filter. 'all' / 'has-credentials'
     * is every record that carries any content credentials (the table itself);
     * 'signed' requires a bound manifest; 'verified' additionally requires the
     * cached sign_status NOT to be a known-failed marker. All cheap predicates.
     *
     * @param  \Illuminate\Database\Query\Builder $q
     * @return \Illuminate\Database\Query\Builder
     */
    private function applyFilter($q, string $filter)
    {
        switch ($filter) {
            case 'signed':
                $q->whereNotNull('p.manifest_id');
                break;
            case 'verified':
                $q->whereNotNull('p.manifest_id')
                    ->whereNotIn(DB::raw('LOWER(p.sign_status)'), self::FAILED_SIGN_STATUSES);
                break;
            case 'has-credentials':
            case 'all':
            default:
                // Every row in ahg_c2pa_provenance is, by definition, a content
                // credential, so no extra predicate is needed.
                break;
        }

        return $q;
    }

    /**
     * Load one bounded window of distinct published records that match the
     * filter, newest-credential first, each with its per-row credential summary.
     * A single GROUP BY over the LIMIT/OFFSET window joined to identity tables.
     *
     * @return list<array<string,mixed>>
     */
    private function loadPage(string $filter, int $offset, int $limit): array
    {
        // First, the bounded set of information_object ids for this page, ordered
        // by most-recent credential. Aggregated so one record appears once even
        // when it carries several provenance rows.
        $ids = $this->matchingRecordsQuery($filter)
            ->select('p.information_object_id', DB::raw('MAX(p.updated_at) as last_credential_at'))
            ->groupBy('p.information_object_id')
            ->orderByDesc('last_credential_at')
            ->orderByDesc('p.information_object_id')
            ->offset($offset)
            ->limit($limit)
            ->get();

        if ($ids->isEmpty()) {
            return [];
        }

        $ioIds = $ids->pluck('information_object_id')->map(fn ($v) => (int) $v)->all();

        $summaries = $this->credentialSummaries($ioIds);
        $titles    = $this->titles($ioIds);
        $slugs     = $this->slugs($ioIds);
        $idents    = $this->identifiers($ioIds);

        $out = [];
        foreach ($ids as $row) {
            $ioId = (int) $row->information_object_id;
            $sum  = $summaries[$ioId] ?? ['credentials' => 0, 'signed' => 0, 'failed' => 0];

            $signed   = (int) $sum['signed'];
            $failed   = (int) $sum['failed'];
            $verified = max(0, $signed - $failed);

            $badge = $this->badge($signed, $verified, $failed);
            $ref   = $slugs[$ioId] ?? (string) $ioId;

            $out[] = [
                'information_object_id' => $ioId,
                'title'                 => $titles[$ioId] ?? null,
                'identifier'            => $idents[$ioId] ?? null,
                'slug'                  => $slugs[$ioId] ?? null,
                'credentials'           => (int) $sum['credentials'],
                'signed'                => $signed,
                'verified'              => $verified,
                'failed'                => $failed,
                'badge'                 => $badge['key'],
                'badge_label'           => $badge['label'],
                'badge_class'           => $badge['class'],
                'last_credential_at'    => is_string($row->last_credential_at ?? null)
                    ? $row->last_credential_at
                    : null,
                'authenticity_url'      => $this->safeUrl('/authenticity/' . $ref),
                'authenticity_json_url' => $this->safeUrl('/authenticity/' . $ioId . '.json'),
            ];
        }

        return $out;
    }

    /**
     * Per-record credential / signed / failed counts for the page's id window.
     * One GROUP BY over the bounded id list; no per-record loop of queries.
     *
     * @param  list<int> $ioIds
     * @return array<int,array{credentials:int,signed:int,failed:int}>
     */
    private function credentialSummaries(array $ioIds): array
    {
        if ($ioIds === []) {
            return [];
        }

        $failedList = "'" . implode("','", self::FAILED_SIGN_STATUSES) . "'";

        $rows = DB::table('ahg_c2pa_provenance')
            ->select(
                'information_object_id',
                DB::raw('COUNT(*) as credentials'),
                DB::raw('SUM(manifest_id IS NOT NULL) as signed'),
                DB::raw("SUM(manifest_id IS NOT NULL AND LOWER(sign_status) IN ($failedList)) as failed"),
            )
            ->whereIn('information_object_id', $ioIds)
            ->groupBy('information_object_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->information_object_id] = [
                'credentials' => (int) $r->credentials,
                'signed'      => (int) $r->signed,
                'failed'      => (int) $r->failed,
            ];
        }

        return $out;
    }

    /**
     * en-preferred titles for the page's id window. One bounded query.
     *
     * @param  list<int> $ioIds
     * @return array<int,?string>
     */
    private function titles(array $ioIds): array
    {
        if ($ioIds === [] || !$this->tableExists('information_object_i18n')) {
            return [];
        }

        // en first, then any other culture, so each id resolves to one title.
        $rows = DB::table('information_object_i18n')
            ->whereIn('id', $ioIds)
            ->orderByRaw("culture = 'en' DESC")
            ->get(['id', 'culture', 'title']);

        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r->id;
            if (!array_key_exists($id, $out) && ($r->title ?? null) !== null && $r->title !== '') {
                $out[$id] = (string) $r->title;
            }
        }

        return $out;
    }

    /**
     * Slugs for the page's id window. One bounded query.
     *
     * @param  list<int> $ioIds
     * @return array<int,?string>
     */
    private function slugs(array $ioIds): array
    {
        if ($ioIds === [] || !$this->tableExists('slug')) {
            return [];
        }

        $rows = DB::table('slug')->whereIn('object_id', $ioIds)->get(['object_id', 'slug']);

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->object_id] = (string) $r->slug;
        }

        return $out;
    }

    /**
     * Human identifiers for the page's id window. One bounded query.
     *
     * @param  list<int> $ioIds
     * @return array<int,?string>
     */
    private function identifiers(array $ioIds): array
    {
        if ($ioIds === []) {
            return [];
        }

        $rows = DB::table('information_object')->whereIn('id', $ioIds)->get(['id', 'identifier']);

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->id] = ($r->identifier ?? null) !== null && $r->identifier !== ''
                ? (string) $r->identifier
                : null;
        }

        return $out;
    }

    /* ----------------------------------------------------------------- *
     * Honest per-record badge - derived from cached counts only.
     * ----------------------------------------------------------------- */

    /**
     * Map a record's cached signed/verified/failed counts to a single honest
     * badge. No live crypto: "Verified" here means the cached signature last
     * checked out, and the per-record /authenticity page is where the live
     * re-verify happens. The badge never claims the content is "true".
     *
     * @return array{key:string,label:string,class:string}
     */
    private function badge(int $signed, int $verified, int $failed): array
    {
        if ($failed > 0) {
            return ['key' => 'failed', 'label' => 'Signature check failed', 'class' => 'bg-danger'];
        }
        if ($signed > 0 && $verified > 0) {
            return ['key' => 'verified', 'label' => 'Signed and verified', 'class' => 'bg-success'];
        }
        if ($signed > 0) {
            return ['key' => 'signed', 'label' => 'Signed', 'class' => 'bg-primary'];
        }

        return ['key' => 'recorded', 'label' => 'Content credentials recorded', 'class' => 'bg-secondary'];
    }

    /* ----------------------------------------------------------------- *
     * Filter metadata + helpers.
     * ----------------------------------------------------------------- */

    /**
     * Filter chips for the view, with the active one marked. Labels are honest
     * and plain; the view wraps them with __() for translation.
     *
     * @return list<array{key:string,label:string,active:bool}>
     */
    private function filterMeta(string $active): array
    {
        $labels = [
            'all'             => 'All credentialed',
            'has-credentials' => 'Has credentials',
            'signed'          => 'Signed',
            'verified'        => 'Verified',
        ];

        $out = [];
        foreach (self::FILTERS as $key) {
            $out[] = [
                'key'    => $key,
                'label'  => $labels[$key] ?? $key,
                'active' => $key === $active,
            ];
        }

        return $out;
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

    private function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }
}
