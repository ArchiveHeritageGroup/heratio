<?php

/**
 * ImpactTrackingService - Heratio ahg-research
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

namespace AhgResearch\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1241 - Research OS #19 (moonshot 25): Impact Tracking.
 *
 * After a project's work is PUBLISHED, Heratio tracks the downstream IMPACT of
 * that output - citations, mentions and dataset reuse. The published outputs are
 * sourced READ-ONLY from the project's research_submission rows: a submission
 * counts as published when its status is one of published|accepted AND it
 * carries a DOI (the doi column written by Publication Studio #1232). No writes
 * to research_submission ever happen here; the only writes are additive inserts
 * to the NEW research_impact_signal table.
 *
 * The polling goes to the PUBLIC bibliographic services OpenAlex
 * (https://api.openalex.org - cited_by_count plus the cited-by works list) and
 * Crossref Event Data (https://api.crossref.org / Event Data) over Laravel's
 * Http client. These are public bibliographic services, NOT AI services, so they
 * are called DIRECTLY - never through the AHG AI gateway. Every outbound call
 * has a short timeout and a descriptive User-Agent, is wrapped in its own
 * try/catch, and a failure simply yields no new signals (the build and tests
 * never depend on the network).
 *
 * Every DB query is Schema::hasTable-guarded and wrapped in try/catch so the
 * feature degrades to an empty state rather than ever throwing a 500. Inserts
 * are idempotent: the same citation/mention is never stored twice.
 */
class ImpactTrackingService
{
    public const SIGNAL_TABLE     = 'research_impact_signal';
    public const SUBMISSION_TABLE = 'research_submission';

    /** OpenAlex API base (public, no key needed). */
    private const OPENALEX_BASE = 'https://api.openalex.org';

    /** Crossref Event Data base (public, no key needed). */
    private const CROSSREF_EVENTS_BASE = 'https://api.eventdata.crossref.org/v1';

    /** Hard per-request timeout (seconds) so a slow API can never hang a run. */
    private const HTTP_TIMEOUT = 8;

    /** A descriptive User-Agent is polite-pool etiquette for both APIs. */
    private const USER_AGENT = 'Heratio-Research-ImpactTracking/1.0 (https://theahg.co.za; mailto:johan@theahg.co.za)';

    /** Submission statuses that count as "published" for impact tracking. */
    private const PUBLISHED_STATUSES = ['published', 'accepted'];

    /** How many cited-by / event rows to materialise per output per run. */
    private const MAX_CITING_WORKS = 25;
    private const MAX_EVENTS       = 25;

    /**
     * Canonical signal_type list. signal_type is a VARCHAR holding one of these
     * codes - never a MySQL ENUM.
     *
     * @var array<string,array{label:string,color:string,icon:string}>
     */
    public const TYPES = [
        'citation'      => ['label' => 'Citation',      'color' => '#0d6efd', 'icon' => 'quote-left'],
        'mention'       => ['label' => 'Mention',       'color' => '#6f42c1', 'icon' => 'comment-dots'],
        'dataset_reuse' => ['label' => 'Dataset reuse', 'color' => '#198754', 'icon' => 'database'],
        'other'         => ['label' => 'Other',         'color' => '#6c757d', 'icon' => 'star'],
    ];

    /** Valid signal_type codes. @return array<int,string> */
    public function typeCodes(): array
    {
        return array_keys(self::TYPES);
    }

    /**
     * Resolve a signal_type code to display meta, tolerating unknown codes.
     *
     * @return array{label:string,color:string,icon:string}
     */
    public function typeMeta(?string $code): array
    {
        if ($code !== null && isset(self::TYPES[$code])) {
            return self::TYPES[$code];
        }

        return [
            'label' => $code ? ucfirst(str_replace('_', ' ', $code)) : 'Other',
            'color' => '#6c757d',
            'icon'  => 'star',
        ];
    }

    // =========================================================================
    // Published-output sourcing (READ-ONLY over research_submission)
    // =========================================================================

    /**
     * The project's PUBLISHED outputs that carry a DOI, read READ-ONLY from
     * research_submission. A submission qualifies when status is published or
     * accepted AND it has a non-empty DOI. No writes; returns [] on any failure.
     *
     * @return array<int,array{submission_id:int,doi:string,title:?string}>
     */
    public function publishedOutputs(int $projectId): array
    {
        try {
            if (! Schema::hasTable(self::SUBMISSION_TABLE)) {
                return [];
            }
            // Guard against schema drift: only query columns we know exist.
            if (! Schema::hasColumn(self::SUBMISSION_TABLE, 'doi')
                || ! Schema::hasColumn(self::SUBMISSION_TABLE, 'status')
                || ! Schema::hasColumn(self::SUBMISSION_TABLE, 'project_id')) {
                return [];
            }

            $hasTitle = Schema::hasColumn(self::SUBMISSION_TABLE, 'manuscript_title');
            $hasVenue = Schema::hasColumn(self::SUBMISSION_TABLE, 'venue_name');

            $cols = ['id', 'doi'];
            if ($hasTitle) {
                $cols[] = 'manuscript_title';
            }
            if ($hasVenue) {
                $cols[] = 'venue_name';
            }

            $rows = DB::table(self::SUBMISSION_TABLE)
                ->where('project_id', $projectId)
                ->whereIn('status', self::PUBLISHED_STATUSES)
                ->whereNotNull('doi')
                ->where('doi', '<>', '')
                ->get($cols);

            $out  = [];
            $seen = [];
            foreach ($rows as $r) {
                $doi = $this->normaliseDoi((string) ($r->doi ?? ''));
                if ($doi === '') {
                    continue;
                }
                $key = $doi . '|' . (int) $r->id;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $title = null;
                if ($hasTitle && ! empty($r->manuscript_title)) {
                    $title = (string) $r->manuscript_title;
                } elseif ($hasVenue && ! empty($r->venue_name)) {
                    $title = (string) $r->venue_name;
                }

                $out[] = [
                    'submission_id' => (int) $r->id,
                    'doi'           => $doi,
                    'title'         => $title,
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Whether the project has at least one published output with a DOI. */
    public function hasPublishedOutputs(int $projectId): bool
    {
        return $this->publishedOutputs($projectId) !== [];
    }

    // =========================================================================
    // Signal reads (NEW table)
    // =========================================================================

    /**
     * Signals for a project, newest first, optionally filtered by signal_type.
     *
     * @return array<int,object>
     */
    public function listSignals(int $projectId, ?string $type = null, int $limit = 200): array
    {
        try {
            if (! Schema::hasTable(self::SIGNAL_TABLE)) {
                return [];
            }

            $q = DB::table(self::SIGNAL_TABLE)->where('project_id', $projectId);

            if ($type !== null && $type !== '' && in_array($type, $this->typeCodes(), true)) {
                $q->where('signal_type', $type);
            }

            return $q->orderByRaw('COALESCE(detected_at, created_at) DESC')
                ->orderBy('id', 'desc')
                ->limit(max(1, $limit))
                ->get()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @return array<string,int> Per-type counts for the panel chips. */
    public function countsByType(int $projectId): array
    {
        try {
            if (! Schema::hasTable(self::SIGNAL_TABLE)) {
                return [];
            }

            return DB::table(self::SIGNAL_TABLE)
                ->where('project_id', $projectId)
                ->groupBy('signal_type')
                ->selectRaw('signal_type, COUNT(*) AS n')
                ->pluck('n', 'signal_type')
                ->map(fn ($n) => (int) $n)
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Total signal count for a project. */
    public function totalCount(int $projectId): int
    {
        try {
            if (! Schema::hasTable(self::SIGNAL_TABLE)) {
                return 0;
            }

            return (int) DB::table(self::SIGNAL_TABLE)
                ->where('project_id', $projectId)
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * The headline citation total for a project: the sum of the latest
     * cited_by_count summary we recorded per published DOI. Falls back to the
     * stored citation-signal row count when no summary is present. Never throws.
     */
    public function citationCount(int $projectId): int
    {
        try {
            if (! Schema::hasTable(self::SIGNAL_TABLE)) {
                return 0;
            }

            // Prefer the explicit per-DOI cited_by_count summaries (source =
            // 'openalex-summary'), one per DOI; take the max per DOI in case a
            // DOI was scanned more than once, then sum across DOIs.
            $summaries = DB::table(self::SIGNAL_TABLE)
                ->where('project_id', $projectId)
                ->where('source', 'openalex-summary')
                ->whereNotNull('detail')
                ->get(['doi', 'detail']);

            if ($summaries->isNotEmpty()) {
                $byDoi = [];
                foreach ($summaries as $s) {
                    $n = $this->extractCountFromDetail((string) ($s->detail ?? ''));
                    $doi = (string) ($s->doi ?? '');
                    if ($n === null) {
                        continue;
                    }
                    $byDoi[$doi] = max($byDoi[$doi] ?? 0, $n);
                }
                if ($byDoi !== []) {
                    return (int) array_sum($byDoi);
                }
            }

            // Fallback: count the citation-type signal rows.
            return (int) DB::table(self::SIGNAL_TABLE)
                ->where('project_id', $projectId)
                ->where('signal_type', 'citation')
                ->whereNotNull('url')
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Pull a "Cited NN times" integer back out of a summary detail string. */
    private function extractCountFromDetail(string $detail): ?int
    {
        if (preg_match('/(\d[\d,]*)\s+time/i', $detail, $m)) {
            return (int) str_replace(',', '', $m[1]);
        }
        return null;
    }

    /** Most recent scan time across this project's signals, or null. */
    public function lastScannedAt(int $projectId): ?string
    {
        try {
            if (! Schema::hasTable(self::SIGNAL_TABLE)) {
                return null;
            }

            $row = DB::table(self::SIGNAL_TABLE)
                ->where('project_id', $projectId)
                ->orderByRaw('COALESCE(detected_at, created_at) DESC')
                ->first(['detected_at', 'created_at']);

            if (! $row) {
                return null;
            }

            return $row->detected_at ?? $row->created_at ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // =========================================================================
    // Polling (the cron heart) - PUBLIC OpenAlex / Crossref Event Data, DIRECT
    // (not the AI gateway). Resilient: short timeout, try/catch per output,
    // continue on failure, never throw, never duplicate an existing signal.
    // =========================================================================

    /**
     * Scan every published output in a project and insert any new impact
     * signals. Returns a summary of what was found. Never throws.
     *
     * @return array{outputs:int,signals:int,errors:int}
     */
    public function scanProject(int $projectId): array
    {
        $outputs = $this->publishedOutputs($projectId);
        $signals = 0;
        $errors  = 0;

        foreach ($outputs as $output) {
            try {
                $signals += $this->scanOutput($projectId, $output['submission_id'], $output['doi'], $output['title']);
            } catch (\Throwable $e) {
                $errors++;
            }
        }

        return ['outputs' => count($outputs), 'signals' => $signals, 'errors' => $errors];
    }

    /**
     * Scan a single published output (one DOI): pull OpenAlex cited_by_count +
     * cited-by works, plus Crossref Event Data mentions, and insert any signals
     * not already present. Returns the number of NEW signals inserted. Resilient
     * per call; never throws.
     */
    public function scanOutput(int $projectId, ?int $submissionId, string $doi, ?string $outputTitle = null): int
    {
        $doi = $this->normaliseDoi($doi);
        if ($doi === '' || $projectId <= 0) {
            return 0;
        }

        $inserted = 0;

        // --- OpenAlex: citation count summary + cited-by works ----------------
        $openalex = $this->fetchOpenAlex($doi);
        if ($openalex !== []) {
            $count = isset($openalex['cited_by_count']) ? (int) $openalex['cited_by_count'] : null;
            if ($count !== null) {
                // One refreshable summary row per DOI (idempotent on source+doi).
                $inserted += $this->upsertSummary(
                    $projectId,
                    $submissionId,
                    $doi,
                    'Cited ' . number_format($count) . ' ' . ($count === 1 ? 'time' : 'times') . ' (OpenAlex)',
                    'OpenAlex reports ' . number_format($count) . ' citation' . ($count === 1 ? '' : 's')
                        . ' of this published output' . ($outputTitle ? ': "' . $outputTitle . '"' : '') . '.',
                    $this->openAlexWorkUrl($openalex, $doi)
                );
            }

            foreach ($this->fetchOpenAlexCitingWorks($doi) as $citing) {
                $inserted += $this->raiseSignal(
                    $projectId,
                    $submissionId,
                    $doi,
                    'citation',
                    $citing['title'],
                    $citing['detail'],
                    $citing['url'],
                    'openalex'
                );
            }
        }

        // --- Crossref Event Data: mentions / dataset reuse --------------------
        foreach ($this->fetchCrossrefEvents($doi) as $event) {
            $inserted += $this->raiseSignal(
                $projectId,
                $submissionId,
                $doi,
                $event['signal_type'],
                $event['title'],
                $event['detail'],
                $event['url'],
                'crossref-event'
            );
        }

        return $inserted;
    }

    /**
     * Insert a signal if an equivalent one (same project + type + url, or same
     * project + type + title when no url) does not already exist. Returns 1 if
     * inserted, 0 otherwise. Never throws.
     */
    private function raiseSignal(
        int $projectId,
        ?int $submissionId,
        string $doi,
        string $type,
        ?string $title,
        ?string $detail,
        ?string $url,
        ?string $source
    ): int {
        try {
            if ($projectId <= 0 || ! Schema::hasTable(self::SIGNAL_TABLE)) {
                return 0;
            }
            if (! in_array($type, $this->typeCodes(), true)) {
                $type = 'other';
            }

            $dupQ = DB::table(self::SIGNAL_TABLE)
                ->where('project_id', $projectId)
                ->where('signal_type', $type);

            if ($url !== null && $url !== '') {
                $dupQ->where('url', $url);
            } else {
                $dupQ->whereNull('url')->where('title', $title);
            }

            if ($dupQ->exists()) {
                return 0;
            }

            DB::table(self::SIGNAL_TABLE)->insert([
                'project_id'    => $projectId,
                'submission_id' => $submissionId ?: null,
                'doi'           => mb_substr($doi, 0, 255),
                'signal_type'   => $type,
                'title'         => $title !== null ? mb_substr($title, 0, 500) : null,
                'detail'        => $detail,
                'url'           => $url !== null ? mb_substr($url, 0, 1000) : null,
                'source'        => $source !== null ? mb_substr($source, 0, 60) : null,
                'detected_at'   => now(),
                'created_at'    => now(),
            ]);

            return 1;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Upsert the per-DOI citation-count SUMMARY row (source 'openalex-summary').
     * There is at most one summary per (project, doi): when the count changes we
     * refresh the existing row in place rather than spawning duplicates. Returns
     * 1 if a NEW summary row was created, 0 if it already existed (refreshed) or
     * on failure. Never throws.
     */
    private function upsertSummary(int $projectId, ?int $submissionId, string $doi, string $title, string $detail, ?string $url): int
    {
        try {
            if ($projectId <= 0 || ! Schema::hasTable(self::SIGNAL_TABLE)) {
                return 0;
            }

            $existing = DB::table(self::SIGNAL_TABLE)
                ->where('project_id', $projectId)
                ->where('source', 'openalex-summary')
                ->where('doi', $doi)
                ->first();

            $payload = [
                'submission_id' => $submissionId ?: null,
                'signal_type'   => 'citation',
                'title'         => mb_substr($title, 0, 500),
                'detail'        => $detail,
                'url'           => $url !== null ? mb_substr($url, 0, 1000) : null,
                'detected_at'   => now(),
            ];

            if ($existing) {
                DB::table(self::SIGNAL_TABLE)->where('id', $existing->id)->update($payload);
                return 0;
            }

            DB::table(self::SIGNAL_TABLE)->insert(array_merge($payload, [
                'project_id' => $projectId,
                'doi'        => mb_substr($doi, 0, 255),
                'source'     => 'openalex-summary',
                'created_at' => now(),
            ]));

            return 1;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // =========================================================================
    // Outbound HTTP - DIRECT to public APIs. Short timeout + descriptive UA.
    // Each returns a decoded array, or [] on any failure (network-independent).
    // =========================================================================

    /** @return array<string,mixed> OpenAlex work record for the DOI, or []. */
    private function fetchOpenAlex(string $doi): array
    {
        try {
            $resp = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->acceptJson()
                ->timeout(self::HTTP_TIMEOUT)
                ->get(self::OPENALEX_BASE . '/works/doi:' . rawurlencode($doi));

            if (! $resp->successful()) {
                return [];
            }

            $json = $resp->json();
            return is_array($json) ? $json : [];
        } catch (\Throwable $e) {
            Log::debug('ImpactTracking OpenAlex fetch failed', ['doi' => $doi, 'error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * The works that CITE this DOI, via OpenAlex's cited_by filter. Returns a
     * small page of citing works for the panel. Empty on any failure.
     *
     * @return array<int,array{title:string,detail:string,url:?string}>
     */
    private function fetchOpenAlexCitingWorks(string $doi): array
    {
        try {
            // Resolve the OpenAlex work id first so we can filter cited_by.
            $work = $this->fetchOpenAlex($doi);
            $id   = isset($work['id']) && is_string($work['id']) ? $work['id'] : '';
            if ($id === '') {
                return [];
            }
            // OpenAlex ids are full URLs; the filter wants the short id.
            $shortId = preg_replace('#^https?://openalex\.org/#i', '', $id) ?? $id;

            $resp = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->acceptJson()
                ->timeout(self::HTTP_TIMEOUT)
                ->get(self::OPENALEX_BASE . '/works', [
                    'filter'   => 'cites:' . $shortId,
                    'per_page' => self::MAX_CITING_WORKS,
                    'select'   => 'id,display_name,doi,publication_year',
                ]);

            if (! $resp->successful()) {
                return [];
            }

            $results = $resp->json('results');
            if (! is_array($results)) {
                return [];
            }

            $out = [];
            foreach ($results as $r) {
                if (! is_array($r)) {
                    continue;
                }
                $title = isset($r['display_name']) && is_string($r['display_name']) ? trim($r['display_name']) : '';
                $year  = isset($r['publication_year']) ? (string) $r['publication_year'] : '';
                $url   = null;
                if (! empty($r['doi']) && is_string($r['doi'])) {
                    $url = $r['doi']; // OpenAlex stores the full https://doi.org/... form
                } elseif (! empty($r['id']) && is_string($r['id'])) {
                    $url = $r['id'];
                }

                $out[] = [
                    'title'  => $title !== '' ? $title : 'A citing work',
                    'detail' => 'Cites this published output'
                        . ($year !== '' ? ' (' . $year . ')' : '') . '. Source: OpenAlex.',
                    'url'    => $url,
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            Log::debug('ImpactTracking OpenAlex citing-works fetch failed', ['doi' => $doi, 'error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Crossref Event Data events for this DOI: blogs, news, Wikipedia, dataset
     * links and similar. Mapped to mention / dataset_reuse / other. Empty on any
     * failure.
     *
     * @return array<int,array{signal_type:string,title:string,detail:string,url:?string}>
     */
    private function fetchCrossrefEvents(string $doi): array
    {
        try {
            $resp = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->acceptJson()
                ->timeout(self::HTTP_TIMEOUT)
                ->get(self::CROSSREF_EVENTS_BASE . '/events', [
                    'obj-id' => $doi,
                    'rows'   => self::MAX_EVENTS,
                ]);

            if (! $resp->successful()) {
                return [];
            }

            $events = $resp->json('message.events');
            if (! is_array($events)) {
                return [];
            }

            $out = [];
            foreach ($events as $ev) {
                if (! is_array($ev)) {
                    continue;
                }
                $sourceId = strtolower((string) ($ev['source_id'] ?? ''));
                $subjId   = is_string($ev['subj_id'] ?? null) ? $ev['subj_id'] : '';
                $relType  = strtolower((string) ($ev['relation_type_id'] ?? ''));

                $type = $this->mapEventToSignalType($sourceId, $relType);
                $label = $this->eventSourceLabel($sourceId);

                $out[] = [
                    'signal_type' => $type,
                    'title'       => $label . ' ' . ($type === 'dataset_reuse' ? 'reuse' : 'mention'),
                    'detail'      => 'Crossref Event Data records a ' . $label . ' '
                        . ($type === 'dataset_reuse' ? 'dataset link to' : 'mention of')
                        . ' this published output'
                        . ($relType !== '' ? ' (relation: ' . $relType . ')' : '') . '.',
                    'url'         => $subjId !== '' ? $subjId : null,
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            Log::debug('ImpactTracking Crossref Event Data fetch failed', ['doi' => $doi, 'error' => $e->getMessage()]);
            return [];
        }
    }

    /** Map a Crossref Event source/relation onto one of our signal types. */
    private function mapEventToSignalType(string $sourceId, string $relType): string
    {
        if (str_contains($sourceId, 'datacite') || str_contains($relType, 'dataset') || str_contains($relType, 'supplement')) {
            return 'dataset_reuse';
        }
        if (in_array($sourceId, ['wikipedia', 'twitter', 'reddit', 'newsfeed', 'hypothesis', 'wordpressdotcom', 'stackexchange', 'f1000', 'plaudit'], true)
            || $sourceId !== '') {
            return 'mention';
        }
        return 'other';
    }

    /** Human label for a Crossref Event Data source id. */
    private function eventSourceLabel(string $sourceId): string
    {
        $map = [
            'wikipedia'       => 'Wikipedia',
            'twitter'         => 'Twitter/X',
            'reddit'         => 'Reddit',
            'newsfeed'        => 'News',
            'hypothesis'      => 'Hypothesis',
            'wordpressdotcom' => 'WordPress',
            'stackexchange'   => 'Stack Exchange',
            'datacite'        => 'DataCite',
            'f1000'           => 'F1000',
            'plaudit'         => 'Plaudit',
        ];
        if ($sourceId !== '' && isset($map[$sourceId])) {
            return $map[$sourceId];
        }
        return $sourceId !== '' ? ucfirst($sourceId) : 'Web';
    }

    /** Best canonical URL for an OpenAlex work payload. */
    private function openAlexWorkUrl(array $openalex, string $doi): ?string
    {
        if (! empty($openalex['doi']) && is_string($openalex['doi'])) {
            return $openalex['doi']; // full https://doi.org/... form
        }
        if (! empty($openalex['id']) && is_string($openalex['id'])) {
            return $openalex['id'];
        }
        return $doi !== '' ? 'https://doi.org/' . $doi : null;
    }

    /**
     * Normalise a DOI to a bare, lower-cased "10.xxxx/..." form, stripping any
     * doi.org URL prefix and a leading "doi:". Returns '' for anything that is
     * not a valid DOI so we never query the APIs with junk.
     */
    public function normaliseDoi(string $raw): string
    {
        $doi = trim($raw);
        if ($doi === '') {
            return '';
        }
        $doi = preg_replace('#^https?://(dx\.)?doi\.org/#i', '', $doi) ?? $doi;
        $doi = preg_replace('#^doi:#i', '', $doi) ?? $doi;
        $doi = trim($doi);

        if (! preg_match('#^10\.\S+/\S+#', $doi)) {
            return '';
        }

        return strtolower($doi);
    }
}
