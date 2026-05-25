<?php

/**
 * DoiService — DOI lifecycle integration with DataCite REST API.
 *
 * Wraps the canonical mint/update/verify/deactivate flow plus queue
 * processing, ported from atom-ahg-plugins/ahgDoiPlugin/lib/Services/DoiService.php
 * (1427 lines on the AtoM side; this is a focused Laravel re-implementation
 * covering the operational surface that ahg-core's artisan commands need).
 *
 * Tables:
 *   ahg_doi          — one row per IO with a DOI assigned (state machine)
 *   ahg_doi_config   — DataCite credentials + prefix + suffix pattern (per-repo)
 *   ahg_doi_queue    — async work items (mint/update/verify/etc)
 *   ahg_doi_log      — append-only history of state transitions and errors
 *
 * @copyright 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgDoiManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class DoiService
{
    /**
     * Load active config row for a repository (or the global default if
     * repository_id is null in the row). Returns the first active row when
     * no repository-scoped match exists.
     */
    public function configFor(?int $repositoryId): ?object
    {
        $q = DB::table('ahg_doi_config')->where('is_active', 1);
        if ($repositoryId !== null) {
            $row = (clone $q)->where('repository_id', $repositoryId)->first();
            if ($row) return $row;
        }
        return $q->whereNull('repository_id')->first();
    }

    /**
     * Build the DOI suffix from the configured pattern.
     *   {repository_code}/{year}/{object_id}
     */
    public function buildDoiSuffix(object $config, int $objectId, string $repoCode = 'h'): string
    {
        $year = (string) date('Y');
        $suffix = str_replace(
            ['{repository_code}', '{year}', '{object_id}'],
            [$repoCode, $year, (string) $objectId],
            (string) $config->suffix_pattern
        );
        // DataCite requires URL-safe; strip anything else.
        return preg_replace('/[^A-Za-z0-9._\/-]/', '-', $suffix);
    }

    /**
     * Queue a mint (or other action) for an IO. Idempotent: refuses to
     * enqueue duplicate pending work for the same IO+action pair.
     */
    public function enqueue(int $objectId, string $action = 'mint', int $priority = 100): int
    {
        $exists = DB::table('ahg_doi_queue')
            ->where('information_object_id', $objectId)
            ->where('action', $action)
            ->whereIn('status', ['pending', 'in_progress'])
            ->exists();
        if ($exists) return 0;

        return (int) DB::table('ahg_doi_queue')->insertGetId([
            'information_object_id' => $objectId,
            'action'                => $action,
            'status'                => 'pending',
            'priority'              => $priority,
            'created_at'            => now(),
            'scheduled_at'          => now(),
        ]);
    }

    /**
     * Pull the next batch of pending queue rows, ordered by priority + age.
     * Caller is expected to mark each row as in_progress before processing.
     */
    public function nextBatch(int $limit = 50): \Illuminate\Support\Collection
    {
        return DB::table('ahg_doi_queue')
            ->where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->orderBy('priority')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Build a DataCite Kernel-4 metadata payload (JSON:API form) for an IO.
     *
     * Phase 1 enrichment (#654, 2026-05-25): in addition to the minimum
     * required attributes (title, creator, publisher, year, resourceType,
     * url) we now emit:
     *   - descriptions[]    — scope_and_content as Abstract
     *   - subjects[]        — taxonomy 35 (Subject) access points
     *   - dates[]           — start/end from event table, dateType=Created
     *   - language          — i.source_culture
     *   - publicationYear   — derived from earliest event start_date (falls
     *                          back to current year when no events exist)
     *
     * Follow-up phases will add Creator ORCID + ROR + RelatedIdentifier +
     * GeoLocation + FundingReference + DataCite Events API integration.
     */
    public function buildMetadata(int $objectId, object $config, string $doi): array
    {
        $row = DB::connection('atom')->table('information_object as i')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('i.id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
            })
            ->where('i.id', $objectId)
            ->select('i.id', 'i.identifier', 'i.source_culture',
                    'i18n.title', 'i18n.scope_and_content',
                    'i18n.archival_history', 'i18n.acquisition')
            ->first();

        $title = $row->title ?? ('Information object ' . $objectId);
        $publisher = $config->default_publisher ?: 'The Archive and Heritage Group';
        $resourceType = $config->default_resource_type ?: 'Text';

        // ----- Phase 1 enrichment -----

        // Descriptions: prefer scope_and_content as Abstract. Add
        // archival_history + acquisition as additional Other descriptions
        // when present (DataCite supports multi-description per record).
        $descriptions = [];
        $stripTagsClean = static function (?string $s): string {
            return trim(preg_replace('/\s+/', ' ', strip_tags((string) $s)));
        };
        if (!empty($row->scope_and_content)) {
            $descriptions[] = [
                'description'      => $stripTagsClean($row->scope_and_content),
                'descriptionType'  => 'Abstract',
            ];
        }
        if (!empty($row->archival_history)) {
            $descriptions[] = [
                'description'      => $stripTagsClean($row->archival_history),
                'descriptionType'  => 'Other',
                'descriptionTypeGeneral' => 'CustodialHistory',
            ];
        }
        if (!empty($row->acquisition)) {
            $descriptions[] = [
                'description'      => $stripTagsClean($row->acquisition),
                'descriptionType'  => 'Other',
                'descriptionTypeGeneral' => 'AcquisitionInfo',
            ];
        }

        // Subjects from taxonomy 35 (Subject access points)
        $subjects = DB::connection('atom')->table('object_term_relation as r')
            ->join('term as t', 'r.term_id', '=', 't.id')
            ->join('term_i18n as ti', function ($j) {
                $j->on('t.id', '=', 'ti.id')->where('ti.culture', '=', 'en');
            })
            ->where('r.object_id', $objectId)
            ->where('t.taxonomy_id', 35)
            ->whereNotNull('ti.name')
            ->where('ti.name', '!=', '')
            ->select('ti.name')
            ->get()
            ->map(function ($s) {
                return [
                    'subject'         => (string) $s->name,
                    'subjectScheme'   => 'AHG Subjects',
                ];
            })
            ->all();

        // Dates from event table — keep earliest start_date for publicationYear
        $events = DB::connection('atom')->table('event as e')
            ->leftJoin('event_i18n as ei', function ($j) {
                $j->on('e.id', '=', 'ei.id')->where('ei.culture', '=', 'en');
            })
            ->where('e.object_id', $objectId)
            ->whereIn('e.type_id', [111, 114])  // Creation + Publication event types
            ->select('e.type_id', 'e.start_date', 'e.end_date', 'ei.date as date_display')
            ->get();

        $dates = [];
        $earliestYear = null;
        foreach ($events as $ev) {
            $dateValue = null;
            if ($ev->start_date && $ev->end_date && $ev->start_date !== $ev->end_date) {
                $dateValue = $ev->start_date . '/' . $ev->end_date;  // DataCite range syntax
            } elseif ($ev->start_date) {
                $dateValue = (string) $ev->start_date;
            } elseif ($ev->date_display) {
                $dateValue = trim((string) $ev->date_display);
            }
            if ($dateValue) {
                $dateType = ((int) $ev->type_id === 114) ? 'Issued' : 'Created';
                $dates[] = ['date' => $dateValue, 'dateType' => $dateType];
                // Track earliest year for publicationYear
                if ($ev->start_date) {
                    $year = (int) substr((string) $ev->start_date, 0, 4);
                    if ($year > 0 && ($earliestYear === null || $year < $earliestYear)) {
                        $earliestYear = $year;
                    }
                }
            }
        }
        $publicationYear = $earliestYear ?: (int) date('Y');

        // Language — from source_culture (ISO 639-1 2-letter code)
        $language = !empty($row->source_culture) ? (string) $row->source_culture : null;

        // Compose the final attributes block. Only include enrichment keys
        // when their data is non-empty so DataCite doesn't reject the
        // record on a "subjects must be non-empty array" validation.
        $attributes = [
            'doi'             => $doi,
            'titles'          => [['title' => $title]],
            'creators'        => [['name' => $publisher]],
            'publisher'       => $publisher,
            'publicationYear' => $publicationYear,
            'types'           => ['resourceTypeGeneral' => $resourceType],
            'url'             => rtrim(config('app.url', 'http://localhost'), '/') . '/informationobject/' . $objectId,
            'event'           => 'publish',
        ];
        if (!empty($descriptions)) {
            $attributes['descriptions'] = $descriptions;
        }
        if (!empty($subjects)) {
            $attributes['subjects'] = $subjects;
        }
        if (!empty($dates)) {
            $attributes['dates'] = $dates;
        }
        if ($language) {
            $attributes['language'] = $language;
        }
        if (!empty($row->identifier)) {
            $attributes['alternateIdentifiers'] = [[
                'alternateIdentifier'     => (string) $row->identifier,
                'alternateIdentifierType' => 'Local',
            ]];
        }

        return [
            'data' => [
                'id'         => $doi,
                'type'       => 'dois',
                'attributes' => $attributes,
            ],
        ];
    }

    /**
     * Mint a DOI for an IO. Reserves the DOI string, calls DataCite, persists
     * the row in ahg_doi, logs the action.
     *
     * @return array{success:bool, doi:?string, error:?string}
     */
    public function mint(int $objectId, ?int $repositoryId = null, bool $dryRun = false): array
    {
        try {
            $config = $this->configFor($repositoryId);
            if (! $config) {
                return ['success' => false, 'doi' => null, 'error' => 'no active ahg_doi_config row'];
            }

            // Idempotency: existing minted DOI is a no-op.
            $existing = DB::table('ahg_doi')->where('information_object_id', $objectId)->first();
            if ($existing && $existing->status !== 'tombstone') {
                return ['success' => true, 'doi' => $existing->doi, 'error' => null];
            }

            $suffix = $this->buildDoiSuffix($config, $objectId);
            $doi    = rtrim($config->datacite_prefix, '/') . '/' . ltrim($suffix, '/');
            $payload = $this->buildMetadata($objectId, $config, $doi);

            if ($dryRun) {
                return ['success' => true, 'doi' => $doi, 'error' => 'dry-run'];
            }

            $resp = $this->dataciteRequest($config, 'POST', '/dois', $payload);
            if (! $resp['ok']) {
                $this->log($objectId, null, 'mint', null, null, ['error' => $resp['error']]);
                return ['success' => false, 'doi' => null, 'error' => $resp['error']];
            }

            $rowId = DB::table('ahg_doi')->insertGetId([
                'information_object_id' => $objectId,
                'doi'                   => $doi,
                'status'                => 'findable',
                'minted_at'             => now(),
                'datacite_response'     => json_encode($resp['body']),
                'metadata_json'         => json_encode($payload),
                'last_sync_at'          => now(),
                'created_at'            => now(),
            ]);
            $this->log($objectId, $rowId, 'mint', null, 'findable', ['doi' => $doi]);
            return ['success' => true, 'doi' => $doi, 'error' => null];
        } catch (Throwable $e) {
            return ['success' => false, 'doi' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verify a DOI's existence + state on DataCite, refresh ahg_doi.last_sync_at.
     */
    public function verify(string $doi): array
    {
        $row = DB::table('ahg_doi')->where('doi', $doi)->first();
        if (! $row) return ['success' => false, 'error' => 'unknown DOI'];
        $config = $this->configFor(null);
        if (! $config) return ['success' => false, 'error' => 'no config'];

        $resp = $this->dataciteRequest($config, 'GET', '/dois/' . urlencode($doi));
        if (! $resp['ok']) {
            $this->log($row->information_object_id, $row->id, 'verify', $row->status, $row->status, ['error' => $resp['error']]);
            return ['success' => false, 'error' => $resp['error']];
        }
        $state = $resp['body']['data']['attributes']['state'] ?? $row->status;
        DB::table('ahg_doi')->where('id', $row->id)->update([
            'status'        => $state,
            'last_sync_at'  => now(),
        ]);
        $this->log($row->information_object_id, $row->id, 'verify', $row->status, $state, []);
        return ['success' => true, 'state' => $state];
    }

    /**
     * Update DataCite metadata for an existing DOI.
     */
    public function update(string $doi): array
    {
        $row = DB::table('ahg_doi')->where('doi', $doi)->first();
        if (! $row) return ['success' => false, 'error' => 'unknown DOI'];
        $config = $this->configFor(null);
        if (! $config) return ['success' => false, 'error' => 'no config'];
        $payload = $this->buildMetadata((int) $row->information_object_id, $config, $doi);

        $resp = $this->dataciteRequest($config, 'PUT', '/dois/' . urlencode($doi), $payload);
        if (! $resp['ok']) {
            $this->log($row->information_object_id, $row->id, 'update', $row->status, $row->status, ['error' => $resp['error']]);
            return ['success' => false, 'error' => $resp['error']];
        }
        DB::table('ahg_doi')->where('id', $row->id)->update([
            'metadata_json' => json_encode($payload),
            'last_sync_at'  => now(),
        ]);
        $this->log($row->information_object_id, $row->id, 'update', $row->status, $row->status, []);
        return ['success' => true];
    }

    /**
     * Tombstone (deactivate) a DOI — keeps the identifier resolvable but flips
     * its event to "hide". DataCite preserves the metadata as a tombstone page.
     */
    public function deactivate(string $doi, string $reason = 'admin tombstone'): array
    {
        $row = DB::table('ahg_doi')->where('doi', $doi)->first();
        if (! $row) return ['success' => false, 'error' => 'unknown DOI'];
        $config = $this->configFor(null);
        if (! $config) return ['success' => false, 'error' => 'no config'];

        $payload = ['data' => ['type' => 'dois', 'attributes' => ['event' => 'hide']]];
        $resp = $this->dataciteRequest($config, 'PUT', '/dois/' . urlencode($doi), $payload);
        if (! $resp['ok']) return ['success' => false, 'error' => $resp['error']];

        DB::table('ahg_doi')->where('id', $row->id)->update([
            'status'        => 'tombstone',
            'last_sync_at'  => now(),
        ]);
        $this->log($row->information_object_id, $row->id, 'deactivate', $row->status, 'tombstone', ['reason' => $reason]);
        return ['success' => true];
    }

    /**
     * Process N pending queue rows. Called by ahg:doi-process-queue.
     */
    public function processQueue(int $limit = 50, bool $dryRun = false): array
    {
        $rows = $this->nextBatch($limit);
        $ok = 0; $fail = 0;
        foreach ($rows as $r) {
            DB::table('ahg_doi_queue')->where('id', $r->id)->update([
                'status'     => 'in_progress',
                'started_at' => now(),
                'attempts'   => $r->attempts + 1,
            ]);
            try {
                $result = match ($r->action) {
                    'mint'       => $this->mint((int) $r->information_object_id, null, $dryRun),
                    'update'     => $this->updateByObject((int) $r->information_object_id),
                    'verify'     => $this->verifyByObject((int) $r->information_object_id),
                    'deactivate' => $this->deactivateByObject((int) $r->information_object_id),
                    default      => ['success' => false, 'error' => 'unknown action: ' . $r->action],
                };
            } catch (Throwable $e) {
                $result = ['success' => false, 'error' => $e->getMessage()];
            }

            if ($result['success']) {
                DB::table('ahg_doi_queue')->where('id', $r->id)->update([
                    'status'        => 'completed',
                    'completed_at'  => now(),
                ]);
                $ok++;
            } else {
                $status = ($r->attempts + 1) >= $r->max_attempts ? 'failed' : 'pending';
                DB::table('ahg_doi_queue')->where('id', $r->id)->update([
                    'status'      => $status,
                    'last_error'  => substr($result['error'] ?? 'unknown', 0, 65535),
                    'started_at'  => null,
                    // Backoff: linear minutes per attempt.
                    'scheduled_at'=> now()->addMinutes(5 * ($r->attempts + 1)),
                ]);
                $fail++;
            }
        }
        return ['ok' => $ok, 'fail' => $fail, 'processed' => $rows->count()];
    }

    public function reportSummary(): array
    {
        return [
            'total'      => (int) DB::table('ahg_doi')->count(),
            'by_status'  => DB::table('ahg_doi')->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n','status')->toArray(),
            'queue'      => DB::table('ahg_doi_queue')->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n','status')->toArray(),
            'last_log'   => DB::table('ahg_doi_log')->orderByDesc('id')->limit(20)->get(),
        ];
    }

    // --- helpers ----------------------------------------------------------

    public function updateByObject(int $oid): array
    {
        $row = DB::table('ahg_doi')->where('information_object_id', $oid)->first();
        return $row ? $this->update($row->doi) : ['success' => false, 'error' => 'no DOI for object'];
    }

    public function verifyByObject(int $oid): array
    {
        $row = DB::table('ahg_doi')->where('information_object_id', $oid)->first();
        return $row ? $this->verify($row->doi) : ['success' => false, 'error' => 'no DOI for object'];
    }

    public function deactivateByObject(int $oid): array
    {
        $row = DB::table('ahg_doi')->where('information_object_id', $oid)->first();
        return $row ? $this->deactivate($row->doi) : ['success' => false, 'error' => 'no DOI for object'];
    }

    protected function dataciteRequest(object $config, string $method, string $path, array $body = []): array
    {
        $url = rtrim($config->datacite_url, '/') . $path;
        try {
            $req = Http::withBasicAuth($config->datacite_repo_id, (string) $config->datacite_password)
                ->acceptJson()
                ->timeout(30);
            if ($method === 'GET') {
                $resp = $req->get($url);
            } else {
                $resp = $req->withBody(json_encode($body), 'application/vnd.api+json')->send($method, $url);
            }
            if ($resp->successful()) {
                return ['ok' => true, 'body' => $resp->json(), 'error' => null];
            }
            return ['ok' => false, 'body' => $resp->json(), 'error' => 'HTTP ' . $resp->status() . ': ' . $resp->body()];
        } catch (Throwable $e) {
            Log::warning('DataCite request failed: ' . $e->getMessage());
            return ['ok' => false, 'body' => null, 'error' => $e->getMessage()];
        }
    }

    protected function log(?int $oid, ?int $doiId, string $action, ?string $before, ?string $after, array $details = []): void
    {
        try {
            DB::table('ahg_doi_log')->insert([
                'doi_id'                => $doiId,
                'information_object_id' => $oid,
                'action'                => $action,
                'status_before'         => $before,
                'status_after'          => $after,
                'details'               => json_encode($details),
                'performed_at'          => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('ahg_doi_log insert failed: ' . $e->getMessage());
        }
    }
}
