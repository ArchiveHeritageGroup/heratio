<?php

/**
 * FieldAlertService - Heratio ahg-research
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
 * heratio#1235 - Research OS Stage 3: per-project Living Field Alerts.
 *
 * Watches the works a project cites (by DOI) and raises an alert when one of
 * them is RETRACTED, has been UPDATED (correction / erratum / new version), or
 * has a NEW RELATED work worth knowing about. The cited DOIs are sourced
 * READ-ONLY from the project's bibliography entries
 * (research_bibliography_entry.doi joined to research_bibliography.project_id);
 * researchers may also add a watch by hand.
 *
 * The polling (see scanProject / scanWatch) goes to the PUBLIC scholarly APIs
 * Crossref (https://api.crossref.org) and OpenAlex (https://api.openalex.org)
 * over Laravel's Http client. These are public bibliographic services, NOT AI
 * services, so they are called DIRECTLY - never through the AHG AI gateway.
 * Every outbound call has a short timeout and a descriptive User-Agent, is
 * wrapped in its own try/catch, and a failure simply yields no new alerts (the
 * build and tests never depend on the network).
 *
 * Every DB query is Schema::hasTable-guarded and wrapped in try/catch so the
 * feature degrades to an empty state rather than ever throwing a 500.
 */
class FieldAlertService
{
    public const WATCH_TABLE = 'research_field_watch';
    public const ALERT_TABLE = 'research_field_alert';

    /** Crossref REST API base (public, no key needed). */
    private const CROSSREF_BASE = 'https://api.crossref.org';

    /** OpenAlex API base (public, no key needed). */
    private const OPENALEX_BASE = 'https://api.openalex.org';

    /** Hard per-request timeout (seconds) so a slow API can never hang a run. */
    private const HTTP_TIMEOUT = 8;

    /** A descriptive User-Agent is polite-pool etiquette for both APIs. */
    private const USER_AGENT = 'Heratio-Research-FieldAlerts/1.0 (https://theahg.co.za; mailto:johan@theahg.co.za)';

    /**
     * Canonical alert_type list. alert_type is a VARCHAR holding one of these
     * codes - never a MySQL ENUM.
     *
     * @var array<string,array{label:string,color:string,icon:string}>
     */
    public const TYPES = [
        'retraction'  => ['label' => 'Retraction',   'color' => '#dc3545', 'icon' => 'ban'],
        'update'      => ['label' => 'Update',        'color' => '#fd7e14', 'icon' => 'pen-to-square'],
        'new_related' => ['label' => 'New related',   'color' => '#0d6efd', 'icon' => 'diagram-project'],
    ];

    /** Valid alert_type codes. @return array<int,string> */
    public function typeCodes(): array
    {
        return array_keys(self::TYPES);
    }

    /**
     * Resolve an alert_type code to display meta, tolerating unknown codes.
     *
     * @return array{label:string,color:string,icon:string}
     */
    public function typeMeta(?string $code): array
    {
        if ($code !== null && isset(self::TYPES[$code])) {
            return self::TYPES[$code];
        }

        return [
            'label' => $code ? ucfirst(str_replace('_', ' ', $code)) : 'Update',
            'color' => '#6c757d',
            'icon'  => 'bell',
        ];
    }

    // =========================================================================
    // Cited-DOI sourcing (READ-ONLY)
    // =========================================================================

    /**
     * The DOIs a project cites, read READ-ONLY from its bibliography entries.
     * No writes; returns an empty array on any failure.
     *
     * @return array<int,array{doi:string,title:?string,source_ref:string}>
     */
    public function citedWorks(int $projectId): array
    {
        try {
            if (! Schema::hasTable('research_bibliography') || ! Schema::hasTable('research_bibliography_entry')) {
                return [];
            }

            $rows = DB::table('research_bibliography_entry as e')
                ->join('research_bibliography as b', 'e.bibliography_id', '=', 'b.id')
                ->where('b.project_id', $projectId)
                ->whereNotNull('e.doi')
                ->where('e.doi', '<>', '')
                ->get(['e.doi', 'e.title']);

            $out = [];
            $seen = [];
            foreach ($rows as $r) {
                $doi = $this->normaliseDoi((string) $r->doi);
                if ($doi === '' || isset($seen[$doi])) {
                    continue;
                }
                $seen[$doi] = true;
                $out[] = [
                    'doi'        => $doi,
                    'title'      => $r->title !== null ? (string) $r->title : null,
                    'source_ref' => 'bibliography',
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Ensure every cited DOI has a watch row for this project (read-only source,
     * additive writes to the NEW watch table only). Returns the number of new
     * watches created. Never throws.
     */
    public function seedWatchesFromBibliography(int $projectId): int
    {
        $created = 0;
        try {
            if (! Schema::hasTable(self::WATCH_TABLE)) {
                return 0;
            }

            foreach ($this->citedWorks($projectId) as $work) {
                $exists = DB::table(self::WATCH_TABLE)
                    ->where('project_id', $projectId)
                    ->where('doi', $work['doi'])
                    ->exists();
                if ($exists) {
                    continue;
                }

                DB::table(self::WATCH_TABLE)->insert([
                    'project_id' => $projectId,
                    'doi'        => $work['doi'],
                    'title'      => $work['title'] !== null ? mb_substr($work['title'], 0, 500) : null,
                    'source_ref' => $work['source_ref'],
                    'added_by'   => 'auto:bibliography',
                    'created_at' => now(),
                ]);
                $created++;
            }
        } catch (\Throwable $e) {
            // Best-effort seed; leave whatever already exists.
        }

        return $created;
    }

    // =========================================================================
    // Watch list management (NEW tables)
    // =========================================================================

    /** @return array<int,object> */
    public function listWatches(int $projectId): array
    {
        try {
            if (! Schema::hasTable(self::WATCH_TABLE)) {
                return [];
            }

            return DB::table(self::WATCH_TABLE)
                ->where('project_id', $projectId)
                ->orderByRaw('(doi IS NULL OR doi = "") ASC')
                ->orderBy('id', 'desc')
                ->get()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Manually add a watch. Returns the new id, or null on failure. */
    public function addWatch(int $projectId, ?string $doi, ?string $title, ?string $addedBy): ?int
    {
        try {
            if (! Schema::hasTable(self::WATCH_TABLE)) {
                return null;
            }

            $doi = $doi !== null ? $this->normaliseDoi($doi) : '';
            if ($doi !== '') {
                $dup = DB::table(self::WATCH_TABLE)
                    ->where('project_id', $projectId)
                    ->where('doi', $doi)
                    ->first();
                if ($dup) {
                    return (int) $dup->id;
                }
            }

            return (int) DB::table(self::WATCH_TABLE)->insertGetId([
                'project_id' => $projectId,
                'doi'        => $doi !== '' ? $doi : null,
                'title'      => $title ? mb_substr($title, 0, 500) : null,
                'source_ref' => 'manual',
                'added_by'   => $addedBy ? mb_substr($addedBy, 0, 255) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Remove a watch scoped to its project. Returns true on success. */
    public function removeWatch(int $projectId, int $watchId): bool
    {
        try {
            if (! Schema::hasTable(self::WATCH_TABLE)) {
                return false;
            }

            return DB::table(self::WATCH_TABLE)
                ->where('project_id', $projectId)
                ->where('id', $watchId)
                ->delete() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @return array<int,object> All watch rows across all projects (for the cron sweep). */
    public function allWatches(?int $projectId = null): array
    {
        try {
            if (! Schema::hasTable(self::WATCH_TABLE)) {
                return [];
            }

            $q = DB::table(self::WATCH_TABLE)
                ->whereNotNull('doi')
                ->where('doi', '<>', '');
            if ($projectId !== null) {
                $q->where('project_id', $projectId);
            }

            return $q->orderBy('id')->get()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    // =========================================================================
    // Alert reads
    // =========================================================================

    /**
     * Alerts for a project, newest first, optionally filtered by alert_type.
     *
     * @return array<int,object>
     */
    public function listAlerts(int $projectId, ?string $type = null): array
    {
        try {
            if (! Schema::hasTable(self::ALERT_TABLE)) {
                return [];
            }

            $q = DB::table(self::ALERT_TABLE)->where('project_id', $projectId);

            if ($type !== null && $type !== '' && in_array($type, $this->typeCodes(), true)) {
                $q->where('alert_type', $type);
            }

            // Retractions float to the top, then by recency.
            return $q->orderByRaw("CASE WHEN alert_type = 'retraction' THEN 0 ELSE 1 END")
                ->orderByRaw('COALESCE(detected_at, created_at) DESC')
                ->orderBy('id', 'desc')
                ->get()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @return array<string,int> Per-type counts for the filter chips. */
    public function countsByType(int $projectId): array
    {
        try {
            if (! Schema::hasTable(self::ALERT_TABLE)) {
                return [];
            }

            return DB::table(self::ALERT_TABLE)
                ->where('project_id', $projectId)
                ->groupBy('alert_type')
                ->selectRaw('alert_type, COUNT(*) AS n')
                ->pluck('n', 'alert_type')
                ->map(fn ($n) => (int) $n)
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Unread alert count for a project (drives the badge). */
    public function unreadCount(int $projectId): int
    {
        try {
            if (! Schema::hasTable(self::ALERT_TABLE)) {
                return 0;
            }

            return (int) DB::table(self::ALERT_TABLE)
                ->where('project_id', $projectId)
                ->where('is_read', 0)
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Mark a single alert read. Returns true on success. */
    public function markRead(int $projectId, int $alertId): bool
    {
        try {
            if (! Schema::hasTable(self::ALERT_TABLE)) {
                return false;
            }

            return DB::table(self::ALERT_TABLE)
                ->where('project_id', $projectId)
                ->where('id', $alertId)
                ->update(['is_read' => 1]) >= 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Mark every alert for a project read. Returns true on success. */
    public function markAllRead(int $projectId): bool
    {
        try {
            if (! Schema::hasTable(self::ALERT_TABLE)) {
                return false;
            }

            return DB::table(self::ALERT_TABLE)
                ->where('project_id', $projectId)
                ->where('is_read', 0)
                ->update(['is_read' => 1]) >= 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // =========================================================================
    // Polling (the cron heart) - PUBLIC Crossref / OpenAlex, DIRECT (not the AI
    // gateway). Resilient: short timeout, try/catch per work, continue on
    // failure, never throw, never duplicate an existing alert.
    // =========================================================================

    /**
     * Scan every watch in a project (or one project) and insert any new alerts.
     * Returns a summary of what was found. Never throws.
     *
     * @return array{watches:int,alerts:int,errors:int}
     */
    public function scanProject(int $projectId): array
    {
        // Make sure the bibliography DOIs have watches before scanning.
        $this->seedWatchesFromBibliography($projectId);

        $watches = $this->allWatches($projectId);
        $alerts  = 0;
        $errors  = 0;

        foreach ($watches as $watch) {
            try {
                $alerts += $this->scanWatch($watch);
            } catch (\Throwable $e) {
                $errors++;
            }
        }

        return ['watches' => count($watches), 'alerts' => $alerts, 'errors' => $errors];
    }

    /**
     * Scan a single watch row: check Crossref + OpenAlex for retraction /
     * update / new-related signals and insert any alerts not already present.
     * Returns the number of NEW alerts inserted. Resilient per call; never
     * throws.
     */
    public function scanWatch(object $watch): int
    {
        $doi = isset($watch->doi) ? $this->normaliseDoi((string) $watch->doi) : '';
        if ($doi === '') {
            return 0;
        }

        $projectId = (int) ($watch->project_id ?? 0);
        $watchId   = (int) ($watch->id ?? 0);
        $inserted  = 0;

        $crossref = $this->fetchCrossref($doi);
        $openalex = $this->fetchOpenAlex($doi);

        // --- Retraction (most important) -------------------------------------
        $retraction = $this->detectRetraction($crossref, $openalex);
        if ($retraction !== null) {
            $inserted += $this->raiseAlert($projectId, $watchId, 'retraction', $retraction['title'], $retraction['detail'], $retraction['url']);
        }

        // --- Updates (corrections, errata, new versions) ----------------------
        foreach ($this->detectUpdates($crossref) as $upd) {
            $inserted += $this->raiseAlert($projectId, $watchId, 'update', $upd['title'], $upd['detail'], $upd['url']);
        }

        // --- New related work -------------------------------------------------
        foreach ($this->detectNewRelated($openalex, $doi) as $rel) {
            $inserted += $this->raiseAlert($projectId, $watchId, 'new_related', $rel['title'], $rel['detail'], $rel['url']);
        }

        // Best-effort: record that we checked this watch (NEW table only).
        try {
            if (Schema::hasTable(self::WATCH_TABLE) && $watchId > 0) {
                DB::table(self::WATCH_TABLE)->where('id', $watchId)->update(['last_checked_at' => now()]);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $inserted;
    }

    /**
     * Insert an alert if an equivalent one (same project + type + url, or same
     * project + type + title when no url) does not already exist. Returns 1 if
     * inserted, 0 otherwise. Never throws.
     */
    private function raiseAlert(int $projectId, ?int $watchId, string $type, ?string $title, ?string $detail, ?string $url): int
    {
        try {
            if ($projectId <= 0 || ! Schema::hasTable(self::ALERT_TABLE)) {
                return 0;
            }
            if (! in_array($type, $this->typeCodes(), true)) {
                $type = 'update';
            }

            $dupQ = DB::table(self::ALERT_TABLE)
                ->where('project_id', $projectId)
                ->where('alert_type', $type);

            if ($url !== null && $url !== '') {
                $dupQ->where('url', $url);
            } else {
                $dupQ->whereNull('url')->where('title', $title);
            }

            if ($dupQ->exists()) {
                return 0;
            }

            DB::table(self::ALERT_TABLE)->insert([
                'project_id'  => $projectId,
                'watch_id'    => $watchId ?: null,
                'alert_type'  => $type,
                'title'       => $title !== null ? mb_substr($title, 0, 500) : null,
                'detail'      => $detail,
                'url'         => $url !== null ? mb_substr($url, 0, 1000) : null,
                'is_read'     => 0,
                'detected_at' => now(),
                'created_at'  => now(),
            ]);

            return 1;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // =========================================================================
    // Outbound HTTP - DIRECT to public APIs. Short timeout + descriptive UA.
    // Each returns a decoded array, or [] on any failure (network-independent).
    // =========================================================================

    /** @return array<string,mixed> Crossref /works/{doi} message, or []. */
    private function fetchCrossref(string $doi): array
    {
        try {
            $resp = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->acceptJson()
                ->timeout(self::HTTP_TIMEOUT)
                ->get(self::CROSSREF_BASE . '/works/' . rawurlencode($doi));

            if (! $resp->successful()) {
                return [];
            }

            $msg = $resp->json('message');
            $this->recordEnrichmentInference('crossref', $doi, self::CROSSREF_BASE . '/works/' . rawurlencode($doi), is_array($msg) ? $msg : []);
            return is_array($msg) ? $msg : [];
        } catch (\Throwable $e) {
            Log::debug('FieldAlerts Crossref fetch failed', ['doi' => $doi, 'error' => $e->getMessage()]);
            return [];
        }
    }

    /** @return array<string,mixed> OpenAlex work record, or []. */
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
            $this->recordEnrichmentInference('openalex', $doi, self::OPENALEX_BASE . '/works/doi:' . rawurlencode($doi), is_array($json) ? $json : []);
            return is_array($json) ? $json : [];
        } catch (\Throwable $e) {
            Log::debug('FieldAlerts OpenAlex fetch failed', ['doi' => $doi, 'error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Record a bibliographic-enrichment call (Crossref/OpenAlex) to the
     * provenance log (ahg_ai_inference via InferenceService), #1326. These are
     * public, deterministic APIs - NOT routed through the AHG AI gateway - but
     * every external enrichment must still leave an auditable inference row.
     * Best-effort: never breaks the enrichment that triggered it.
     *
     * @param array<string,mixed> $payload
     */
    private function recordEnrichmentInference(string $source, string $doi, string $endpoint, array $payload): void
    {
        try {
            app(\AhgProvenanceAi\Services\InferenceService::class)->record(
                new \AhgProvenanceAi\DTO\InferenceRecord(
                    serviceName: 'ENRICHMENT',
                    modelName: $source,
                    modelVersion: 'public-api',
                    inputHash: hash('sha256', $doi),
                    outputHash: hash('sha256', json_encode($payload) ?: ''),
                    targetEntityType: 'doi',
                    targetEntityId: 0,
                    targetField: 'bibliographic_enrichment',
                    endpoint: $endpoint,
                    inputExcerpt: mb_substr($doi, 0, 500),
                )
            );
        } catch (\Throwable $e) {
            // Provenance logging is best-effort; never break enrichment.
        }
    }

    // =========================================================================
    // Signal detection (pure functions over decoded API payloads)
    // =========================================================================

    /**
     * Detect a retraction from either source. Crossref marks retractions with
     * an "update-to" relation of type "retraction", and many retracted works
     * carry a type/subtype of "retraction" or an "is-retracted" flag in their
     * relations. OpenAlex exposes is_retracted on the work.
     *
     * @return array{title:string,detail:string,url:?string}|null
     */
    private function detectRetraction(array $crossref, array $openalex): ?array
    {
        $title = $this->workTitle($crossref, $openalex);

        // OpenAlex explicit flag.
        if (! empty($openalex['is_retracted'])) {
            return [
                'title'  => $title,
                'detail' => 'OpenAlex marks this cited work as RETRACTED. Treat its findings as withdrawn and review every claim that relies on it.',
                'url'    => $this->workUrl($crossref, $openalex),
            ];
        }

        // Crossref update-to / relation signals.
        foreach (($crossref['update-to'] ?? []) as $u) {
            $label = strtolower((string) ($u['type'] ?? ''));
            if (str_contains($label, 'retract') || str_contains($label, 'withdraw')) {
                return [
                    'title'  => $title,
                    'detail' => 'Crossref reports a retraction/withdrawal notice for this cited work. Review every claim that relies on it.',
                    'url'    => $this->workUrl($crossref, $openalex),
                ];
            }
        }
        if (isset($crossref['relation']['is-retracted-by']) || isset($crossref['relation']['has-retraction'])) {
            return [
                'title'  => $title,
                'detail' => 'Crossref links a retraction notice to this cited work. Review every claim that relies on it.',
                'url'    => $this->workUrl($crossref, $openalex),
            ];
        }

        return null;
    }

    /**
     * Detect updates (corrections, errata, new versions) from Crossref's
     * "updated-by" relations.
     *
     * @return array<int,array{title:string,detail:string,url:?string}>
     */
    private function detectUpdates(array $crossref): array
    {
        $out = [];
        foreach (($crossref['updated-by'] ?? []) as $u) {
            $label = (string) ($u['type'] ?? 'update');
            // A retraction "updated-by" is handled separately; skip it here.
            if (str_contains(strtolower($label), 'retract') || str_contains(strtolower($label), 'withdraw')) {
                continue;
            }
            $doi = (string) ($u['DOI'] ?? '');
            $human = ucfirst(str_replace('_', ' ', $label));
            $out[] = [
                'title'  => $human . ' published for a cited work',
                'detail' => 'Crossref reports a "' . $label . '" for one of this project\'s cited works'
                    . ($doi !== '' ? ' (DOI ' . $doi . ')' : '') . '. Check whether it affects how you cite it.',
                'url'    => $doi !== '' ? 'https://doi.org/' . $doi : null,
            ];
        }

        return $out;
    }

    /**
     * Detect a small set of NEW related works via OpenAlex's related_works
     * list. We surface up to a few so the panel stays useful without flooding.
     *
     * @return array<int,array{title:string,detail:string,url:?string}>
     */
    private function detectNewRelated(array $openalex, string $sourceDoi): array
    {
        $related = $openalex['related_works'] ?? [];
        if (! is_array($related) || $related === []) {
            return [];
        }

        $out = [];
        foreach (array_slice($related, 0, 3) as $url) {
            if (! is_string($url) || $url === '') {
                continue;
            }
            $out[] = [
                'title'  => 'New related work for a cited source',
                'detail' => 'OpenAlex links a related work to a source this project cites (DOI ' . $sourceDoi . '). It may be worth reading.',
                'url'    => $url,
            ];
        }

        return $out;
    }

    /** Best human title from either payload. */
    private function workTitle(array $crossref, array $openalex): string
    {
        $t = $crossref['title'][0] ?? ($openalex['title'] ?? ($openalex['display_name'] ?? null));
        $t = is_string($t) ? trim($t) : '';
        return $t !== '' ? $t : 'A cited work';
    }

    /** Best canonical URL from either payload. */
    private function workUrl(array $crossref, array $openalex): ?string
    {
        if (! empty($crossref['DOI'])) {
            return 'https://doi.org/' . $crossref['DOI'];
        }
        if (! empty($openalex['doi']) && is_string($openalex['doi'])) {
            return $openalex['doi']; // OpenAlex stores the full https://doi.org/... form
        }
        if (! empty($openalex['id']) && is_string($openalex['id'])) {
            return $openalex['id'];
        }
        return null;
    }

    /**
     * Normalise a DOI to a bare, lower-cased "10.xxxx/..." form, stripping any
     * doi.org URL prefix and a leading "doi:".
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

        // A valid DOI starts with "10." - reject anything else so we never query
        // the APIs with junk.
        if (! preg_match('#^10\.\S+/\S+#', $doi)) {
            return '';
        }

        return strtolower($doi);
    }
}
