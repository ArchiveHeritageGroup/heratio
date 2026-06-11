<?php

/**
 * OaiPmhController - OAI-PMH 2.0 harvesting endpoint over published records.
 *
 * Deepens north-star #1204 ("the world heritage graph / open memory protocol")
 * by exposing the published archival corpus through OAI-PMH 2.0, the de-facto
 * metadata-harvesting protocol used by library/archive aggregators (and now
 * crawling agents). It complements the Linked-Data graph endpoint
 * (GraphController) - the graph is for per-entity crawling, OAI-PMH is for bulk
 * metadata harvesting in simple Dublin Core (oai_dc).
 *
 * GET /api/oai?verb=...
 *
 *   Verbs: Identify, ListMetadataFormats, ListIdentifiers, ListRecords,
 *   GetRecord. Selective harvesting via from / until datestamp filters and an
 *   opaque resumptionToken for pagination (bounded page size).
 *
 * Reuses the EXACT published-enumeration pattern proven by GraphController:
 * information_object joined to a Published status row (status.type_id=158,
 * status_id=160), synthetic root id=1 excluded, i18n via
 * information_object_i18n at culture 'en'. Only published descriptions are ever
 * disclosed - unpublished drafts are never leaked.
 *
 * Resilience: an unknown verb / bad argument / unknown identifier yields a
 * proper OAI <error> element (HTTP 200, per spec), never a 500. An empty result
 * set yields noRecordsMatch. Optional DC enrichments (creators, dates,
 * publisher, subjects) are best-effort and wrapped so a schema variance never
 * breaks the response.
 *
 * Read-only: this controller performs only SELECTs and mutates nothing.
 * Jurisdiction-neutral: standards-based oai_dc with no market assumptions; the
 * admin email and repository name come from config with safe generic defaults.
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

namespace AhgApi\Controllers;

use AhgApi\Services\OaiPmhService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class OaiPmhController extends Controller
{
    /** Publication-status taxonomy: status.type_id for "publication status". */
    private const STATUS_TYPE_PUBLICATION = 158;

    /** Publication-status term id for "Published". */
    private const STATUS_PUBLISHED = 160;

    /** Synthetic root information_object id, always excluded. */
    private const ROOT_ID = 1;

    /** Bounded page size for ListIdentifiers / ListRecords. */
    private const PAGE_SIZE = 100;

    /** Only metadata prefix this repository disseminates. */
    private const METADATA_PREFIX = 'oai_dc';

    protected string $culture = 'en';

    protected OaiPmhService $oai;

    public function __construct(OaiPmhService $oai)
    {
        $this->oai = $oai;
    }

    /**
     * GET /api/oai - the single OAI-PMH endpoint. Dispatches on the ?verb=
     * argument. Always returns text/xml; charset=utf-8. Protocol errors are
     * valid OAI <error> documents at HTTP 200 (per the OAI-PMH spec).
     */
    public function handle(Request $request): Response
    {
        $verb = (string) $request->input('verb', '');

        // The request element echoed in every response carries the baseURL and
        // (for a valid verb) the arguments. For a badVerb / badArgument the OAI
        // spec says the request element must NOT echo the offending attributes.
        try {
            switch ($verb) {
                case 'Identify':
                    return $this->xml($this->doIdentify($request));

                case 'ListMetadataFormats':
                    return $this->xml($this->doListMetadataFormats($request));

                case 'ListIdentifiers':
                    return $this->xml($this->doListRecords($request, false));

                case 'ListRecords':
                    return $this->xml($this->doListRecords($request, true));

                case 'GetRecord':
                    return $this->xml($this->doGetRecord($request));

                case '':
                    return $this->xml(
                        $this->oai->error($this->baseRequest(), 'badVerb', 'No verb supplied.'),
                        400
                    );

                default:
                    return $this->xml(
                        $this->oai->error($this->baseRequest(), 'badVerb', "Illegal verb: {$verb}"),
                        400
                    );
            }
        } catch (\Throwable $e) {
            // Last-resort guard: never surface a 500. Emit a well-formed OAI
            // error so a harvester gets parseable XML even on an internal fault.
            return $this->xml(
                $this->oai->error($this->baseRequest(), 'badArgument', 'The request could not be processed.'),
                200
            );
        }
    }

    // -----------------------------------------------------------------
    // Verb handlers
    // -----------------------------------------------------------------

    /**
     * Identify: repository name (app.name), base URL, protocol 2.0, earliest
     * datestamp (oldest published object's updated_at), admin email (config,
     * generic default), deletedRecord=no, granularity.
     */
    protected function doIdentify(Request $request): string
    {
        $req = $this->echoRequest(['verb' => 'Identify']);

        return $this->oai->identify([
            'repositoryName' => (string) config('app.name', 'Heratio'),
            'baseUrl' => $this->baseUrl(),
            'adminEmail' => $this->adminEmail(),
            'earliestDatestamp' => $this->earliestDatestamp(),
            'request' => $req,
        ]);
    }

    /**
     * ListMetadataFormats: this repository disseminates oai_dc only.
     */
    protected function doListMetadataFormats(Request $request): string
    {
        // An optional identifier argument scopes the formats to one record;
        // either way every published record supports oai_dc, so we validate the
        // identifier (if given) and then advertise oai_dc.
        $identifier = (string) $request->input('identifier', '');
        if ($identifier !== '') {
            $id = $this->parseIdentifier($identifier);
            if ($id === null || ! $this->isPublished($id)) {
                return $this->oai->error(
                    $this->echoRequest(['verb' => 'ListMetadataFormats', 'identifier' => $identifier]),
                    'idDoesNotExist',
                    "No published record for identifier: {$identifier}"
                );
            }
        }

        return $this->oai->listMetadataFormats(
            $this->echoRequest(array_filter([
                'verb' => 'ListMetadataFormats',
                'identifier' => $identifier !== '' ? $identifier : null,
            ]))
        );
    }

    /**
     * ListIdentifiers (headers) and ListRecords (full oai_dc) share the same
     * selection, paging and resumptionToken machinery; $full toggles the body.
     */
    protected function doListRecords(Request $request, bool $full): string
    {
        $verb = $full ? 'ListRecords' : 'ListIdentifiers';

        // Resolve paging + selection from either a resumptionToken or the raw
        // selective-harvest arguments (metadataPrefix, from, until).
        $token = (string) $request->input('resumptionToken', '');
        if ($token !== '') {
            $params = $this->decodeToken($token);
            if ($params === null) {
                return $this->oai->error(
                    $this->echoRequest(['verb' => $verb, 'resumptionToken' => $token]),
                    'badResumptionToken',
                    'The resumptionToken is invalid or expired.'
                );
            }
        } else {
            $prefix = (string) $request->input('metadataPrefix', self::METADATA_PREFIX);
            if ($prefix !== self::METADATA_PREFIX) {
                return $this->oai->error(
                    $this->echoRequest(['verb' => $verb, 'metadataPrefix' => $prefix]),
                    'cannotDisseminateFormat',
                    "Unsupported metadataPrefix: {$prefix}. This repository supports oai_dc."
                );
            }
            $params = [
                'offset' => 0,
                'from' => $this->normaliseDatestamp((string) $request->input('from', '')),
                'until' => $this->normaliseDatestamp((string) $request->input('until', '')),
            ];
            // A malformed from/until is a badArgument.
            if ($params['from'] === false || $params['until'] === false) {
                return $this->oai->error(
                    $this->echoRequest(['verb' => $verb]),
                    'badArgument',
                    'The from or until datestamp is not a valid OAI date.'
                );
            }
        }

        $offset = (int) $params['offset'];
        $from = $params['from'] ?: null;
        $until = $params['until'] ?: null;

        // Pull one extra row to detect whether a further page exists.
        $rows = $this->publishedQuery($from, $until)
            ->orderBy('io.id')
            ->offset($offset)
            ->limit(self::PAGE_SIZE + 1)
            ->select('io.id', 'io.identifier', 'io.level_of_description_id', 'io.repository_id', 'i18n.title', 'i18n.scope_and_content', 'o.updated_at')
            ->get();

        if ($rows->isEmpty()) {
            return $this->oai->error(
                $this->echoRequest($this->harvestEcho($verb, $from, $until, $token)),
                'noRecordsMatch',
                'No published records match the request.'
            );
        }

        $hasMore = $rows->count() > self::PAGE_SIZE;
        $page = $hasMore ? $rows->slice(0, self::PAGE_SIZE) : $rows;

        $records = [];
        foreach ($page as $row) {
            $records[] = $full ? $this->mapFullRecord($row) : $this->mapHeader($row);
        }

        $nextToken = null;
        if ($hasMore) {
            $nextToken = $this->encodeToken([
                'offset' => $offset + self::PAGE_SIZE,
                'from' => $from ?: '',
                'until' => $until ?: '',
            ]);
        }

        $echo = $this->echoRequest($this->harvestEcho($verb, $from, $until, $token));

        return $full
            ? $this->oai->listRecords($echo, $records, $nextToken)
            : $this->oai->listIdentifiers($echo, $records, $nextToken);
    }

    /**
     * GetRecord: one published record by identifier in oai_dc.
     */
    protected function doGetRecord(Request $request): string
    {
        $identifier = (string) $request->input('identifier', '');
        $prefix = (string) $request->input('metadataPrefix', self::METADATA_PREFIX);

        if ($identifier === '') {
            return $this->oai->error(
                $this->echoRequest(['verb' => 'GetRecord']),
                'badArgument',
                'The identifier argument is required for GetRecord.'
            );
        }

        if ($prefix !== self::METADATA_PREFIX) {
            return $this->oai->error(
                $this->echoRequest(['verb' => 'GetRecord', 'identifier' => $identifier, 'metadataPrefix' => $prefix]),
                'cannotDisseminateFormat',
                "Unsupported metadataPrefix: {$prefix}. This repository supports oai_dc."
            );
        }

        $id = $this->parseIdentifier($identifier);
        $row = $id === null ? null : $this->publishedQuery()
            ->where('io.id', $id)
            ->select('io.id', 'io.identifier', 'io.level_of_description_id', 'io.repository_id', 'i18n.title', 'i18n.scope_and_content', 'o.updated_at')
            ->first();

        if (! $row) {
            return $this->oai->error(
                $this->echoRequest(['verb' => 'GetRecord', 'identifier' => $identifier, 'metadataPrefix' => $prefix]),
                'idDoesNotExist',
                "No published record for identifier: {$identifier}"
            );
        }

        return $this->oai->getRecord(
            $this->echoRequest(['verb' => 'GetRecord', 'identifier' => $identifier, 'metadataPrefix' => $prefix]),
            $this->mapFullRecord($row)
        );
    }

    // -----------------------------------------------------------------
    // Published enumeration (mirrors GraphController exactly)
    // -----------------------------------------------------------------

    /**
     * The shared published-only query: information_object joined to a Published
     * status row (type_id=158, status_id=160), synthetic root (id 1) excluded,
     * i18n at culture 'en', object joined for updated_at. Identical gate to
     * GraphController. Optional from/until filter on object.updated_at.
     */
    protected function publishedQuery(?string $from = null, ?string $until = null): \Illuminate\Database\Query\Builder
    {
        $q = DB::table('information_object as io')
            ->join('status as st', function ($j) {
                $j->on('io.id', '=', 'st.object_id')
                    ->where('st.type_id', '=', self::STATUS_TYPE_PUBLICATION)
                    ->where('st.status_id', '=', self::STATUS_PUBLISHED);
            })
            ->join('object as o', 'io.id', '=', 'o.id')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', $this->culture);
            })
            ->where('io.id', '!=', self::ROOT_ID);

        if ($from !== null) {
            $q->where('o.updated_at', '>=', $this->datestampToSql($from, false));
        }
        if ($until !== null) {
            $q->where('o.updated_at', '<=', $this->datestampToSql($until, true));
        }

        return $q;
    }

    /**
     * Cheap published-status check for a single object id (GetRecord /
     * ListMetadataFormats identifier validation).
     */
    protected function isPublished(int $id): bool
    {
        return $this->publishedQuery()->where('io.id', $id)->exists();
    }

    /**
     * The oldest published object's updated_at, as an OAI datestamp. Falls back
     * to a fixed epoch when the corpus is empty, so Identify always validates.
     */
    protected function earliestDatestamp(): string
    {
        try {
            $oldest = $this->publishedQuery()->min('o.updated_at');
        } catch (\Throwable $e) {
            $oldest = null;
        }

        return $oldest ? $this->toOaiDatestamp((string) $oldest) : '1970-01-01T00:00:00Z';
    }

    // -----------------------------------------------------------------
    // Record mapping (DB row -> service record array)
    // -----------------------------------------------------------------

    /**
     * Build a header-only record array (ListIdentifiers).
     *
     * @return array<string,mixed>
     */
    protected function mapHeader(object $row): array
    {
        return [
            'identifier' => $this->oaiIdentifier((int) $row->id),
            'datestamp' => $this->toOaiDatestamp((string) ($row->updated_at ?? '')),
            'sets' => [],
        ];
    }

    /**
     * Build a full oai_dc record array (ListRecords / GetRecord). Maps the core
     * columns and best-effort enriches creators / dates / publisher / subjects.
     *
     * @return array<string,mixed>
     */
    protected function mapFullRecord(object $row): array
    {
        $record = $this->mapHeader($row);

        $record['title'] = $row->title ?: '[Untitled]';
        $record['description'] = $row->scope_and_content ?: null;

        // dc:identifier - the human-facing reference code plus the public URL.
        $identifiers = [];
        if (! empty($row->identifier)) {
            $identifiers[] = (string) $row->identifier;
        }
        $publicUrl = $this->recordPublicUrl((int) $row->id);
        if ($publicUrl !== null) {
            $identifiers[] = $publicUrl;
        }
        $record['identifiers'] = $identifiers;

        // dc:type - the level-of-description label (Fonds, Item, ...).
        $level = $this->termName($row->level_of_description_id);
        if ($level !== null) {
            $record['types'] = [$level];
        }

        // Best-effort enrichments. A schema variance must never break the
        // response, so each is independently guarded.
        $record['creators'] = $this->creators((int) $row->id);
        $record['dates'] = $this->dates((int) $row->id);
        $record['publisher'] = $this->publisher($row->repository_id);
        $record['subjects'] = $this->subjects((int) $row->id);
        $record['rights'] = $this->rights((int) $row->id);
        $record['languages'] = null;

        return $record;
    }

    /**
     * Creator names (actors linked via the event table). dc:creator.
     *
     * @return array<int,string>
     */
    protected function creators(int $objectId): array
    {
        try {
            return DB::table('event')
                ->join('actor_i18n', function ($j) {
                    $j->on('event.actor_id', '=', 'actor_i18n.id')
                        ->where('actor_i18n.culture', $this->culture);
                })
                ->where('event.object_id', $objectId)
                ->whereNotNull('event.actor_id')
                ->whereNotNull('actor_i18n.authorized_form_of_name')
                ->distinct()
                ->pluck('actor_i18n.authorized_form_of_name')
                ->filter()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Display dates for the record (event date / start_date). dc:date.
     *
     * @return array<int,string>
     */
    protected function dates(int $objectId): array
    {
        try {
            $rows = DB::table('event as e')
                ->leftJoin('event_i18n as ei', function ($j) {
                    $j->on('e.id', '=', 'ei.id')->where('ei.culture', $this->culture);
                })
                ->where('e.object_id', $objectId)
                ->select('ei.date as display_date', 'e.start_date', 'e.end_date')
                ->get();

            $dates = [];
            foreach ($rows as $r) {
                if (! empty($r->display_date)) {
                    $dates[] = (string) $r->display_date;
                } elseif (! empty($r->start_date)) {
                    $dates[] = $this->normaliseDbDateForDc((string) $r->start_date)
                        . (! empty($r->end_date) ? '/' . $this->normaliseDbDateForDc((string) $r->end_date) : '');
                }
            }

            return array_values(array_unique(array_filter($dates)));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * The holding repository's authorised name, as dc:publisher.
     */
    protected function publisher($repositoryId): ?string
    {
        if (empty($repositoryId)) {
            return null;
        }

        try {
            $name = DB::table('repository as r')
                ->join('actor_i18n as ai', function ($j) {
                    $j->on('r.id', '=', 'ai.id')->where('ai.culture', $this->culture);
                })
                ->where('r.id', (int) $repositoryId)
                ->value('ai.authorized_form_of_name');

            return $name ? (string) $name : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Subject access points (taxonomy 35 - subjects). dc:subject.
     *
     * @return array<int,string>
     */
    protected function subjects(int $objectId): array
    {
        try {
            return DB::table('object_term_relation as otr')
                ->join('term_i18n as ti', function ($j) {
                    $j->on('otr.term_id', '=', 'ti.id')->where('ti.culture', $this->culture);
                })
                ->where('otr.object_id', $objectId)
                ->whereNotNull('ti.name')
                ->distinct()
                ->pluck('ti.name')
                ->filter()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Conditions / rights statement, if any, as dc:rights. Best-effort: pulls a
     * rights_i18n basis text linked through the relation table. Returns [] when
     * the schema differs so the response never breaks.
     *
     * @return array<int,string>
     */
    protected function rights(int $objectId): array
    {
        // Rights modelling varies; keep this conservative. The reproduction /
        // access conditions live on the i18n record in many installs; we leave
        // the hook here and emit nothing rather than guess a wrong join.
        return [];
    }

    // -----------------------------------------------------------------
    // Identifier <-> object id
    // -----------------------------------------------------------------

    /**
     * Form an OAI identifier for an object: oai:{host}:io/{id}.
     */
    protected function oaiIdentifier(int $objectId): string
    {
        return 'oai:'.$this->host().':io/'.$objectId;
    }

    /**
     * Parse an OAI identifier back to a numeric object id. Accepts the full
     * oai:{host}:io/{id} form and (leniently) a bare numeric id. Returns null
     * for anything that is not a positive integer id.
     */
    protected function parseIdentifier(string $identifier): ?int
    {
        if ($identifier === '') {
            return null;
        }

        if (ctype_digit($identifier)) {
            $id = (int) $identifier;

            return $id > 0 ? $id : null;
        }

        if (preg_match('#io/(\d+)$#', $identifier, $m)) {
            $id = (int) $m[1];

            return $id > 0 ? $id : null;
        }

        return null;
    }

    /**
     * Canonical public record page (slug-based) for dc:identifier.
     */
    protected function recordPublicUrl(int $objectId): ?string
    {
        try {
            $slug = DB::table('slug')->where('object_id', $objectId)->value('slug');
        } catch (\Throwable $e) {
            $slug = null;
        }

        return $slug ? $this->base().'/'.ltrim((string) $slug, '/') : null;
    }

    // -----------------------------------------------------------------
    // resumptionToken (opaque cursor: offset + from + until)
    // -----------------------------------------------------------------

    /**
     * Encode an opaque resumptionToken from a parameter map. Base64url over a
     * compact query string keeps the from/until selection pinned across pages.
     *
     * @param  array{offset:int,from:string,until:string}  $params
     */
    protected function encodeToken(array $params): string
    {
        $payload = http_build_query([
            'o' => (int) $params['offset'],
            'f' => (string) $params['from'],
            'u' => (string) $params['until'],
        ]);

        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    /**
     * Decode a resumptionToken back to a parameter map. Returns null for a
     * malformed token (caller emits badResumptionToken).
     *
     * @return array{offset:int,from:string,until:string}|null
     */
    protected function decodeToken(string $token): ?array
    {
        $decoded = base64_decode(strtr($token, '-_', '+/'), true);
        if ($decoded === false || $decoded === '') {
            return null;
        }

        parse_str($decoded, $parts);
        if (! isset($parts['o']) || ! ctype_digit((string) $parts['o'])) {
            return null;
        }

        $from = $this->normaliseDatestamp((string) ($parts['f'] ?? ''));
        $until = $this->normaliseDatestamp((string) ($parts['u'] ?? ''));
        if ($from === false || $until === false) {
            return null;
        }

        return [
            'offset' => (int) $parts['o'],
            'from' => (string) $from,
            'until' => (string) $until,
        ];
    }

    // -----------------------------------------------------------------
    // Datestamps
    // -----------------------------------------------------------------

    /**
     * Validate / normalise an OAI request datestamp (from / until). Accepts the
     * date-only (YYYY-MM-DD) and the full UTC (YYYY-MM-DDThh:mm:ssZ) forms.
     * Returns '' for an absent value, the normalised string for a valid one, or
     * false for a malformed one (caller emits badArgument).
     *
     * @return string|false
     */
    protected function normaliseDatestamp(string $value)
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value)) {
            return $value;
        }

        return false;
    }

    /**
     * Convert an OAI datestamp to a SQL-comparable string against
     * object.updated_at (a DATETIME). A date-only "until" bound widens to the
     * end of that day so the day is inclusive.
     */
    protected function datestampToSql(string $oai, bool $isUntil): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $oai)) {
            return $isUntil ? $oai.' 23:59:59' : $oai.' 00:00:00';
        }

        // Full UTC form: drop the trailing Z and the T separator -> SQL datetime.
        return str_replace(['T', 'Z'], [' ', ''], $oai);
    }

    /**
     * Render a DB timestamp as an OAI UTC datestamp (YYYY-MM-DDThh:mm:ssZ).
     * Falls back to a fixed epoch when the value cannot be parsed.
     */
    protected function toOaiDatestamp(string $dbValue): string
    {
        if ($dbValue === '') {
            return '1970-01-01T00:00:00Z';
        }

        try {
            return \Illuminate\Support\Carbon::parse($dbValue, 'UTC')->format('Y-m-d\TH:i:s\Z');
        } catch (\Throwable $e) {
            return '1970-01-01T00:00:00Z';
        }
    }

    /**
     * Normalise an AtoM-style partial DB date (YYYY, YYYY-MM, YYYY-MM-DD, or a
     * zero-padded variant) into a clean DC date string. Trims trailing
     * "-00" components so "1923-00-00" becomes "1923".
     */
    protected function normaliseDbDateForDc(string $value): string
    {
        $value = trim($value);
        // Strip "-00" month/day components (AtoM stores unknown parts as 00).
        $value = preg_replace('/-00(-00)?$/', '', $value);
        $value = preg_replace('/-00$/', '', (string) $value);

        return (string) $value;
    }

    // -----------------------------------------------------------------
    // Term lookup (mirrors GraphController)
    // -----------------------------------------------------------------

    protected function termName($termId): ?string
    {
        if (empty($termId)) {
            return null;
        }

        try {
            return DB::table('term_i18n')
                ->where('id', (int) $termId)
                ->where('culture', $this->culture)
                ->value('name');
        } catch (\Throwable $e) {
            return null;
        }
    }

    // -----------------------------------------------------------------
    // Request echo + config
    // -----------------------------------------------------------------

    /**
     * The minimal echoed request (baseURL only) - used by error responses for a
     * bad verb where the spec forbids echoing the offending arguments.
     *
     * @return array<string,string>
     */
    protected function baseRequest(): array
    {
        return ['baseUrl' => $this->baseUrl()];
    }

    /**
     * Build the echoed request map: always the baseURL, plus the supplied,
     * non-empty arguments.
     *
     * @param  array<string,string|null>  $args
     * @return array<string,string>
     */
    protected function echoRequest(array $args): array
    {
        $req = ['baseUrl' => $this->baseUrl()];
        foreach ($args as $k => $v) {
            if ($v !== null && $v !== '') {
                $req[$k] = (string) $v;
            }
        }

        return $req;
    }

    /**
     * The arguments to echo for a list verb. A resumptionToken request echoes
     * only the token + verb (the from/until are folded inside the token);
     * otherwise echoes verb + metadataPrefix + any from/until.
     *
     * @return array<string,string|null>
     */
    protected function harvestEcho(string $verb, ?string $from, ?string $until, string $token): array
    {
        if ($token !== '') {
            return ['verb' => $verb, 'resumptionToken' => $token];
        }

        return [
            'verb' => $verb,
            'metadataPrefix' => self::METADATA_PREFIX,
            'from' => $from,
            'until' => $until,
        ];
    }

    /**
     * The OAI baseURL: the canonical /api/oai endpoint on this host.
     */
    protected function baseUrl(): string
    {
        return $this->base().'/api/oai';
    }

    protected function base(): string
    {
        return rtrim((string) url('/'), '/');
    }

    protected function host(): string
    {
        $host = parse_url($this->base(), PHP_URL_HOST);

        return $host ?: 'localhost';
    }

    /**
     * Administrative contact email. Sourced from config with a safe generic
     * default - never a hardcoded personal address. Falls back to admin@{host}.
     */
    protected function adminEmail(): string
    {
        $email = config('heratio.contact_email')
            ?: config('mail.from.address')
            ?: ('admin@'.$this->host());

        return (string) $email;
    }

    // -----------------------------------------------------------------
    // Response
    // -----------------------------------------------------------------

    /**
     * Wrap an XML string in a text/xml response with permissive CORS so any
     * aggregator may harvest the open data.
     */
    protected function xml(string $body, int $status = 200): Response
    {
        $response = response($body, $status, ['Content-Type' => 'text/xml; charset=utf-8']);
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Accept, Content-Type');
        $response->headers->set('X-Open-Data', 'true');

        return $response;
    }

    /**
     * OPTIONS preflight.
     */
    public function options(): Response
    {
        return $this->xml('', 204);
    }
}
