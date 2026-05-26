<?php

/**
 * DataciteEventsService - DataCite Events API client.
 *
 * Issue #654 Phase 3. Implements the JSON-API client for
 * https://api.datacite.org/events (or https://api.test.datacite.org/events
 * in test mode), per https://support.datacite.org/docs/eventdata-guide.
 *
 * Heratio uses the Events API to register events that happen TO a DOI:
 *   - view events (a record was viewed)
 *   - download events (a digital object was downloaded)
 *   - citation events (a relatedIdentifier IsReferencedBy was recorded)
 *   - relation events (any RelatedIdentifier relation - PartOf, etc.)
 *
 * Submission is queued via RegisterDataciteEventJob so a burst of views or
 * downloads cannot exceed the named limiter cap (queue.rate_limits
 * .datacite_events, default 30/min). Idempotency is enforced at the
 * ahg_datacite_event table via a UNIQUE on dedupe_hash =
 * sha256(subjectDoi|relationTypeId|objectId|sourceId), so a repeat call for
 * the same logical event upserts the existing row instead of re-submitting.
 *
 * Auth: DataCite's Events API accepts the same Bearer JWT issued from the
 * DataCite Fabrica console (ahg_settings.datacite_api_token). Where no
 * Bearer token is configured the service falls back to the basic-auth
 * credentials in ahg_doi_config that DoiService already uses for minting.
 * That fallback is documented as a transition path; long-term operators
 * should issue a Bearer token from Fabrica.
 *
 * @copyright 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgDoiManage\Services;

use AhgDoiManage\Jobs\RegisterDataciteEventJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class DataciteEventsService
{
    /**
     * Build the JSON-API payload, deduplicate-or-insert the row, then queue
     * a RegisterDataciteEventJob. Returns true if the event is queued (or
     * was already submitted), false if the inputs are invalid.
     *
     * @param  string      $subjectDoi      DOI on the Heratio side (the subject of the event).
     * @param  string      $relationTypeId  e.g. 'references', 'is-referenced-by',
     *                                      'unique-dataset-investigations-regular' (Counter),
     *                                      'total-dataset-investigations-regular'.
     * @param  string      $objectId        target id (other DOI, URL, etc.)
     * @param  string      $objectIdType    'doi' | 'url' | 'uri' | 'other'
     * @param  string      $source          source-id for the event,
     *                                      e.g. 'heratio-archive', 'heratio-counter'
     * @param  array|null  $extra           any extra top-level attributes (occurred-at, total, etc.)
     */
    public function register(
        string $subjectDoi,
        string $relationTypeId,
        string $objectId,
        string $objectIdType = 'doi',
        string $source = 'heratio-archive',
        ?array $extra = null,
    ): bool {
        $subjectDoi    = $this->normaliseDoi($subjectDoi);
        $relationTypeId = trim($relationTypeId);
        $objectId      = trim($objectId);
        $source        = trim($source) ?: 'heratio-archive';
        $objectIdType  = strtolower(trim($objectIdType)) ?: 'doi';

        if ($subjectDoi === '' || $relationTypeId === '' || $objectId === '') {
            return false;
        }

        $hash = hash('sha256', strtolower($subjectDoi).'|'.$relationTypeId.'|'.strtolower($objectId).'|'.$source);

        if (! Schema::hasTable('ahg_datacite_event')) {
            // Schema not present yet (CI / first-boot race); log and refuse.
            Log::info('datacite_events.skip_no_table', ['hash' => $hash]);

            return false;
        }

        $payload = $this->buildPayload(
            subjectDoi: $subjectDoi,
            relationTypeId: $relationTypeId,
            objectId: $objectId,
            objectIdType: $objectIdType,
            source: $source,
            extra: is_array($extra) ? $extra : [],
        );

        try {
            $existing = DB::table('ahg_datacite_event')->where('dedupe_hash', $hash)->first();
            if ($existing && $existing->state === 'sent') {
                // Already submitted - nothing to do.
                return true;
            }

            if ($existing) {
                DB::table('ahg_datacite_event')->where('id', $existing->id)->update([
                    'payload_json' => json_encode($payload['data']['attributes'] ?? $payload),
                    'updated_at'   => now(),
                ]);
                $eventId = (int) $existing->id;
            } else {
                $eventId = (int) DB::table('ahg_datacite_event')->insertGetId([
                    'dedupe_hash'     => $hash,
                    'subj_id'         => $subjectDoi,
                    'relation_type_id' => $relationTypeId,
                    'obj_id'          => $objectId,
                    'obj_id_type'     => $objectIdType,
                    'source_id'       => $source,
                    'payload_json'    => json_encode($payload['data']['attributes'] ?? $payload),
                    'state'           => 'pending',
                    'attempts'        => 0,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('datacite_events.insert_failed', [
                'hash'  => $hash,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        try {
            RegisterDataciteEventJob::dispatch($eventId);
        } catch (Throwable $e) {
            // Even if dispatch fails (sync driver hiccup, etc.) the row is in
            // the table - the operator can run doi:events-flush later.
            Log::warning('datacite_events.dispatch_failed', [
                'event_id' => $eventId,
                'error'    => $e->getMessage(),
            ]);
        }

        return true;
    }

    /**
     * Synchronously POST one ahg_datacite_event row to the DataCite Events
     * API. Invoked by RegisterDataciteEventJob and the doi:events-flush
     * artisan command. Returns true on HTTP 2xx, false otherwise.
     */
    public function submit(int $eventId): bool
    {
        $row = DB::table('ahg_datacite_event')->where('id', $eventId)->first();
        if (! $row) {
            return false;
        }
        if ($row->state === 'sent') {
            return true;
        }

        $payload = $this->payloadFromRow($row);
        $url = $this->endpoint().'/events';

        try {
            $req = Http::acceptJson()->timeout(20);
            $bearer = $this->bearerToken();
            if ($bearer !== null) {
                $req = $req->withToken($bearer);
            } else {
                // Transition fallback - basic-auth from minting credentials.
                $cfg = DB::table('ahg_doi_config')->where('is_active', 1)->first();
                if ($cfg && ! empty($cfg->datacite_repo_id) && ! empty($cfg->datacite_password)) {
                    $req = $req->withBasicAuth(
                        (string) $cfg->datacite_repo_id,
                        (string) $cfg->datacite_password,
                    );
                }
            }

            $resp = $req->withBody(json_encode($payload), 'application/vnd.api+json')
                ->post($url);

            $ok = $resp->successful();
            DB::table('ahg_datacite_event')->where('id', $eventId)->update([
                'state'           => $ok ? 'sent' : 'failed',
                'attempts'        => (int) ($row->attempts ?? 0) + 1,
                'response_status' => $resp->status(),
                'response_body'   => mb_substr((string) $resp->body(), 0, 4000),
                'last_error'      => $ok ? null : 'HTTP '.$resp->status(),
                'submitted_at'    => $ok ? now() : $row->submitted_at,
                'updated_at'      => now(),
            ]);

            if (! $ok) {
                Log::warning('datacite_events.submit_failed', [
                    'event_id' => $eventId,
                    'status'   => $resp->status(),
                    'body'     => mb_substr((string) $resp->body(), 0, 500),
                ]);
            }

            return $ok;
        } catch (Throwable $e) {
            DB::table('ahg_datacite_event')->where('id', $eventId)->update([
                'state'      => 'failed',
                'attempts'   => (int) ($row->attempts ?? 0) + 1,
                'last_error' => $e->getMessage(),
                'updated_at' => now(),
            ]);
            Log::warning('datacite_events.submit_exception', [
                'event_id' => $eventId,
                'error'    => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Endpoint root (no /events suffix). config('datacite.test_mode') wins
     * over the legacy ahg_doi_config.environment column; in either case the
     * fallback is api.datacite.org so production stays the default.
     */
    public function endpoint(): string
    {
        $testMode = (bool) config('datacite.test_mode', false);
        if (! $testMode && Schema::hasTable('ahg_doi_config')) {
            $cfg = DB::table('ahg_doi_config')->where('is_active', 1)->first();
            if ($cfg && isset($cfg->environment) && $cfg->environment === 'test') {
                $testMode = true;
            }
        }

        return $testMode
            ? 'https://api.test.datacite.org'
            : 'https://api.datacite.org';
    }

    /**
     * Bearer token from ahg_settings.datacite_api_token, or null if none
     * has been configured.
     */
    public function bearerToken(): ?string
    {
        if (! Schema::hasTable('ahg_settings')) {
            return null;
        }
        $row = DB::table('ahg_settings')
            ->where('setting_key', 'datacite_api_token')
            ->first();
        if (! $row || empty($row->setting_value)) {
            return null;
        }

        return (string) $row->setting_value;
    }

    /**
     * Build the JSON-API request body the DataCite Events API expects.
     * See https://support.datacite.org/docs/eventdata-guide#submitting-events
     */
    public function buildPayload(
        string $subjectDoi,
        string $relationTypeId,
        string $objectId,
        string $objectIdType,
        string $source,
        array $extra = [],
    ): array {
        $eventUuid = (string) Str::uuid();

        $attributes = [
            'subj-id'          => $this->canonicalDoiUri($subjectDoi),
            'obj-id'           => $objectIdType === 'doi'
                ? $this->canonicalDoiUri($objectId)
                : $objectId,
            'relation-type-id' => $relationTypeId,
            'source-id'        => $source,
            'occurred-at'      => Carbon::now('UTC')->toIso8601String(),
        ];

        // Optional fields per the spec.
        foreach (['message-action', 'source-token', 'license', 'total'] as $optKey) {
            if (isset($extra[$optKey])) {
                $attributes[$optKey] = $extra[$optKey];
            }
        }
        if (isset($extra['occurred_at'])) {
            $attributes['occurred-at'] = (string) $extra['occurred_at'];
        }

        return [
            'data' => [
                'id'         => $eventUuid,
                'type'       => 'events',
                'attributes' => $attributes,
            ],
        ];
    }

    protected function payloadFromRow(object $row): array
    {
        $attrs = [];
        if (! empty($row->payload_json)) {
            $decoded = json_decode((string) $row->payload_json, true);
            if (is_array($decoded)) {
                // Stored as the attributes object alone; wrap.
                $attrs = $decoded;
            }
        }

        if (empty($attrs)) {
            $attrs = [
                'subj-id'          => $this->canonicalDoiUri($row->subj_id),
                'obj-id'           => ($row->obj_id_type === 'doi')
                    ? $this->canonicalDoiUri($row->obj_id)
                    : $row->obj_id,
                'relation-type-id' => $row->relation_type_id,
                'source-id'        => $row->source_id,
                'occurred-at'      => Carbon::now('UTC')->toIso8601String(),
            ];
        }

        return [
            'data' => [
                'id'         => (string) Str::uuid(),
                'type'       => 'events',
                'attributes' => $attrs,
            ],
        ];
    }

    protected function normaliseDoi(string $doi): string
    {
        $doi = trim($doi);
        // Strip URL prefixes - DataCite stores subj-id as a https://doi.org/... URI
        // but we keep the bare DOI in the row for human-readable indexes.
        $doi = preg_replace('#^https?://(?:dx\.)?doi\.org/#i', '', $doi);
        $doi = preg_replace('#^doi:#i', '', $doi);

        return (string) $doi;
    }

    protected function canonicalDoiUri(string $doi): string
    {
        $doi = $this->normaliseDoi($doi);
        if ($doi === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $doi)) {
            return $doi;
        }

        return 'https://doi.org/'.$doi;
    }
}
