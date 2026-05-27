<?php

/**
 * OaiPmhController - Controller for Heratio
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

namespace AhgOai\Controllers;

use AhgMetadataExport\Services\Exporters\Ead2002Serializer;
use AhgMetadataExport\Services\Exporters\Ead3Serializer;
use AhgMetadataExport\Services\Exporters\MarcxmlSerializer;
use AhgMetadataExport\Services\Exporters\ModsSerializer;
use AhgMetadataExport\Services\IptcFallbackResolver;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * OAI-PMH 2.0 endpoint controller.
 *
 * Implements the six OAI-PMH verbs: Identify, ListMetadataFormats,
 * ListSets, ListIdentifiers, ListRecords, GetRecord.
 *
 * Responses are XML per the OAI-PMH 2.0 specification.
 */
class OaiPmhController extends Controller
{
    /**
     * OAI-PMH error messages keyed by error code.
     */
    private const ERRORS = [
        'badArgument' => 'The request includes illegal arguments, is missing required arguments, includes a repeated argument, or values for arguments have an illegal syntax.',
        'badResumptionToken' => 'The value of the resumptionToken argument is invalid or expired.',
        'badVerb' => 'Value of the verb argument is not a legal OAI-PMH verb, the verb argument is missing, or the verb argument is repeated.',
        'cannotDisseminateFormat' => 'The metadata format identified by the value given for the metadataPrefix argument is not supported by the item or by the repository.',
        'idDoesNotExist' => 'The value of the identifier argument is unknown or illegal in this repository.',
        'noRecordsMatch' => 'The combination of the values of the from, until, set and metadataPrefix arguments results in an empty list.',
        'noMetadataFormats' => 'There are no metadata formats available for the specified item.',
        'noSetHierarchy' => 'The repository does not support sets.',
    ];

    /**
     * Valid OAI-PMH verbs.
     */
    private const VERBS = [
        'Identify', 'ListMetadataFormats', 'ListSets',
        'ListIdentifiers', 'ListRecords', 'GetRecord',
    ];

    /**
     * Allowed and mandatory parameters per verb.
     */
    private const VERB_PARAMS = [
        'Identify' => ['allowed' => ['verb'], 'mandatory' => ['verb']],
        'ListMetadataFormats' => ['allowed' => ['verb', 'identifier'], 'mandatory' => ['verb']],
        'ListSets' => ['allowed' => ['verb', 'resumptionToken'], 'mandatory' => ['verb']],
        'ListIdentifiers' => ['allowed' => ['verb', 'metadataPrefix', 'from', 'until', 'set', 'resumptionToken'], 'mandatory' => ['verb', 'metadataPrefix']],
        'ListRecords' => ['allowed' => ['verb', 'metadataPrefix', 'from', 'until', 'set', 'resumptionToken'], 'mandatory' => ['verb', 'metadataPrefix']],
        'GetRecord' => ['allowed' => ['verb', 'identifier', 'metadataPrefix'], 'mandatory' => ['verb', 'identifier', 'metadataPrefix']],
    ];

    /**
     * Default page size for resumption tokens. Overridden at runtime by
     * ahg_settings.resumption_token_limit when set; see pageSize().
     */
    private const PAGE_SIZE = 100;

    /**
     * Supported OAI-PMH metadataPrefix values, each mapped to its
     * advertised schema + namespace for ListMetadataFormats. The dispatch
     * to the per-format serializer happens in renderMetadata().
     */
    private const METADATA_FORMATS = [
        'oai_dc' => [
            'schema' => 'http://www.openarchives.org/OAI/2.0/oai_dc.xsd',
            'namespace' => 'http://www.openarchives.org/OAI/2.0/oai_dc/',
        ],
        'oai_ead' => [
            'schema' => 'http://www.loc.gov/ead/ead.xsd',
            'namespace' => 'urn:isbn:1-931666-22-9',
        ],
        'oai_ead3' => [
            'schema' => 'https://www.loc.gov/ead/ead3.xsd',
            'namespace' => 'http://ead3.archivists.org/schema/',
        ],
        'mods' => [
            'schema' => 'http://www.loc.gov/standards/mods/v3/mods-3-5.xsd',
            'namespace' => 'http://www.loc.gov/mods/v3',
        ],
        'marcxml' => [
            'schema' => 'http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd',
            'namespace' => 'http://www.loc.gov/MARC21/slim',
        ],
    ];

    /**
     * Read a setting from the i18n `setting` table (scope='oai', the same
     * shape SettingsService::getOaiSettings + setSetting use) with a fallback
     * default. Cached per-request via a static lookup table so we don't hit
     * the DB on every XML element render. The 7 keys this honours are the
     * audit's: oai_authentication_enabled, oai_repository_code,
     * oai_repository_identifier, oai_admin_emails, sample_oai_identifier,
     * resumption_token_limit, oai_additional_sets_enabled.
     */
    private function setting(string $key, $default = null)
    {
        static $cache = null;
        if ($cache === null) {
            $cache = DB::table('setting as s')
                ->join('setting_i18n as si', 'si.id', '=', 's.id')
                ->where('s.scope', 'oai')
                ->where('si.culture', 'en')
                ->pluck('si.value', 's.name')
                ->all();
        }
        $v = $cache[$key] ?? null;

        return ($v === null || $v === '') ? $default : $v;
    }

    /** Resumption-token page size from settings, falling back to PAGE_SIZE. */
    private function pageSize(): int
    {
        $v = (int) $this->setting('resumption_token_limit', self::PAGE_SIZE);

        return $v > 0 ? $v : self::PAGE_SIZE;
    }

    /**
     * Repository identifier published in OAI Identify + used to compose
     * record identifiers. Reads ahg_settings.oai_repository_identifier
     * (operator-configured) and falls back to the request host so behaviour
     * is unchanged for installs that haven't filled the setting in.
     */
    private function getRepositoryIdentifier(): string
    {
        return (string) $this->setting('oai_repository_identifier', request()->getHost());
    }

    /**
     * Format an OAI identifier from an oai_local_identifier. When
     * oai_repository_code is set, it's prefixed onto the local id so a
     * federated harvester can distinguish records originating from this
     * repository even if multiple repos share the same hostname (multi-
     * tenancy / proxied setups). Format:
     *   oai:{repositoryIdentifier}:{repository_code}-{oai_local_id}
     * If no repository code is set, the dash and prefix are dropped:
     *   oai:{repositoryIdentifier}:{oai_local_id}
     * keeping the legacy shape for installs that haven't configured it.
     */
    private function formatOaiIdentifier(int $oaiLocalId): string
    {
        $code = trim((string) $this->setting('oai_repository_code', ''));
        $localPart = $code !== '' ? $code.'-'.$oaiLocalId : (string) $oaiLocalId;

        return 'oai:'.$this->getRepositoryIdentifier().':'.$localPart;
    }

    /**
     * Parse the oai_local_identifier from an OAI identifier string.
     */
    private function parseOaiIdentifier(string $identifier): ?int
    {
        // Format: oai:{repositoryIdentifier}:{oai_local_identifier}
        if (preg_match('/^oai:.+:(\d+)$/', $identifier, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Get ISO 8601 UTC date string.
     */
    private function getDate(?string $date = null): string
    {
        if ($date === null || $date === '') {
            return gmdate('Y-m-d\TH:i:s\Z');
        }

        return gmdate('Y-m-d\TH:i:s\Z', strtotime($date));
    }

    /**
     * Main dispatcher: reads `verb` GET parameter and routes to the correct method.
     */
    public function handle(Request $request): Response
    {
        // Optional API-key gate. When ahg_settings.oai_authentication_enabled
        // is '1', refuse harvest attempts that don't carry an oai-scope API
        // key. Honours the same X-API-Key / Authorization: Bearer / api=
        // shapes the rest of the public API uses (ahg-api package). Default
        // off so anonymous OAI harvesting keeps working out of the box.
        if ((string) $this->setting('oai_authentication_enabled', '0') === '1') {
            $key = $request->header('X-API-Key')
                ?: ($request->bearerToken() ?: $request->input('api'));
            $valid = false;
            if ($key && \Illuminate\Support\Facades\Schema::hasTable('ahg_api_key')) {
                // ahg_api_key shape (per the central API key store): api_key
                // (the secret), scopes (JSON array of strings), is_active,
                // expires_at. A key counts as valid for OAI when active,
                // not yet expired, and either has no scopes restriction
                // (NULL or empty array, treat as wildcard) or contains
                // 'oai' / '*' in scopes.
                $row = DB::table('ahg_api_key')
                    ->where('api_key', $key)
                    ->where('is_active', 1)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->select('scopes')
                    ->first();
                if ($row) {
                    $scopes = json_decode((string) ($row->scopes ?? '[]'), true);
                    if (! is_array($scopes) || empty($scopes)) {
                        $valid = true; // unrestricted key
                    } else {
                        $valid = in_array('oai', $scopes, true) || in_array('*', $scopes, true);
                    }
                }
            }
            if (! $valid) {
                // OAI-PMH has no native auth verb; surface as an HTTP 401
                // with a short text body so harvesters fail fast.
                return new Response('OAI authentication required (X-API-Key, Bearer, or ?api= with oai scope).', 401, ['Content-Type' => 'text/plain']);
            }
        }

        // Per OAI-PMH 2.0 spec section 3.4 both GET and POST are valid;
        // POST passes parameters in the body as application/x-www-form-urlencoded.
        // $request->all() unifies query string + form body so the same
        // dispatch path handles either transport. CSRF is excepted for /oai
        // in bootstrap/app.php so harvesters without a token can POST.
        $allParams = $request->all();
        // Drop any framework-injected fields that aren't OAI args.
        unset($allParams['_token'], $allParams['_method']);

        $verb = $allParams['verb'] ?? null;

        // Check verb is present and valid
        if (empty($verb) || ! in_array($verb, self::VERBS)) {
            return $this->errorResponse($request, 'badVerb');
        }

        // If resumptionToken is present, decode and apply its parameters
        $params = $allParams;
        if (isset($params['resumptionToken'])) {
            $decoded = $this->decodeResumptionToken($params['resumptionToken']);
            if ($decoded === false) {
                return $this->errorResponse($request, 'badResumptionToken');
            }
            // Apply token params (they override query params except verb)
            $params = array_merge($params, $decoded);
            // When resumptionToken is present, the only other allowed param is verb
            $queryKeys = array_keys($allParams);
            $nonTokenKeys = array_diff($queryKeys, ['verb', 'resumptionToken']);
            if (count($nonTokenKeys) > 0) {
                return $this->errorResponse($request, 'badArgument');
            }
        } else {
            // Validate allowed/mandatory parameters
            $queryKeys = array_keys($allParams);
            $verbConfig = self::VERB_PARAMS[$verb];

            foreach ($queryKeys as $key) {
                if (! in_array($key, $verbConfig['allowed'])) {
                    return $this->errorResponse($request, 'badArgument');
                }
            }

            foreach ($verbConfig['mandatory'] as $mandatoryKey) {
                if (! in_array($mandatoryKey, $queryKeys)) {
                    return $this->errorResponse($request, 'badArgument');
                }
            }
        }

        // Check metadataPrefix is one of the registered formats.
        $metadataPrefix = $params['metadataPrefix'] ?? null;
        if ($metadataPrefix !== null && $metadataPrefix !== '' && ! isset(self::METADATA_FORMATS[$metadataPrefix])) {
            return $this->errorResponse($request, 'cannotDisseminateFormat');
        }

        // Validate date parameters
        foreach (['from', 'until'] as $dateParam) {
            if (isset($params[$dateParam]) && $params[$dateParam] !== '' && ! $this->isValidDate($params[$dateParam])) {
                return $this->errorResponse($request, 'badArgument');
            }
        }

        // Dispatch to verb handler
        switch ($verb) {
            case 'Identify':
                return $this->identify($request);
            case 'ListMetadataFormats':
                return $this->listMetadataFormats($request);
            case 'ListSets':
                return $this->listSets($request, $params);
            case 'ListIdentifiers':
                return $this->listIdentifiers($request, $params);
            case 'ListRecords':
                return $this->listRecords($request, $params);
            case 'GetRecord':
                return $this->getRecord($request, $params);
            default:
                return $this->errorResponse($request, 'badVerb');
        }
    }

    /**
     * Identify verb: repository identification.
     */
    private function identify(Request $request): Response
    {
        $earliestDatestamp = DB::table('object')->min('updated_at');
        $earliestDatestamp = $this->getDate($earliestDatestamp);

        $repositoryName = config('app.name', 'Heratio');
        $baseUrl = $request->url();
        // OAI Identify allows multiple <adminEmail> elements - the spec is a
        // 'one or more' (1+) cardinality. ahg_settings.oai_admin_emails is a
        // comma-separated list (the form input is a single textbox); split,
        // trim, dedupe. Falls back to mail.from.address so existing installs
        // that haven't configured the setting still produce a valid Identify
        // response.
        $adminEmailsRaw = (string) $this->setting('oai_admin_emails', '');
        $adminEmails = array_values(array_unique(array_filter(array_map('trim', explode(',', $adminEmailsRaw)))));
        if (empty($adminEmails)) {
            $adminEmails = [config('mail.from.address', 'admin@'.$request->getHost())];
        }
        // Sample identifier shown in <sampleIdentifier>. ahg_settings holds
        // the operator's chosen example local-id (e.g. '100002'). When set
        // we use it directly; when not, we still emit the legacy '100002'
        // hardcode so the Identify response stays well-formed.
        $sampleLocalId = (int) $this->setting('sample_oai_identifier', 100002);
        if ($sampleLocalId <= 0) {
            $sampleLocalId = 100002;
        }

        $xml = $this->xmlHeader($request);
        $xml .= '  <Identify>'."\n";
        $xml .= '    <repositoryName>'.$this->esc($repositoryName).'</repositoryName>'."\n";
        $xml .= '    <baseURL>'.$this->esc($baseUrl).'</baseURL>'."\n";
        $xml .= '    <protocolVersion>2.0</protocolVersion>'."\n";
        foreach ($adminEmails as $email) {
            $xml .= '    <adminEmail>'.$this->esc($email).'</adminEmail>'."\n";
        }
        $xml .= '    <earliestDatestamp>'.$earliestDatestamp.'</earliestDatestamp>'."\n";
        // "transient" advertises that we keep tombstones for deleted records
        // for an indefinite-but-not-guaranteed-forever period. Backed by the
        // oai_deleted_record table populated via `php artisan oai:mark-deleted`.
        // Falls back to "no" if the table is missing so a half-installed
        // upgrade keeps producing valid Identify responses.
        $deletedPolicy = \Illuminate\Support\Facades\Schema::hasTable('oai_deleted_record') ? 'transient' : 'no';
        $xml .= '    <deletedRecord>'.$deletedPolicy.'</deletedRecord>'."\n";
        $xml .= '    <granularity>YYYY-MM-DDThh:mm:ssZ</granularity>'."\n";
        $xml .= '    <description>'."\n";
        $xml .= '      <oai-identifier xmlns="http://www.openarchives.org/OAI/2.0/oai-identifier"'."\n";
        $xml .= '                      xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'."\n";
        $xml .= '                      xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai-identifier http://www.openarchives.org/OAI/2.0/oai-identifier.xsd">'."\n";
        $xml .= '        <scheme>oai</scheme>'."\n";
        $xml .= '        <repositoryIdentifier>'.$this->esc($this->getRepositoryIdentifier()).'</repositoryIdentifier>'."\n";
        $xml .= '        <delimiter>:</delimiter>'."\n";
        $xml .= '        <sampleIdentifier>'.$this->formatOaiIdentifier($sampleLocalId).'</sampleIdentifier>'."\n";
        $xml .= '      </oai-identifier>'."\n";
        $xml .= '    </description>'."\n";

        // <friends> container — OAI-PMH friends.xsd lists other OAI repos this
        // server knows about. Sourced from ahg-federation's federation_peer
        // table: active peers with peer_type='oai_pmh' and a base_url.
        // Empty <friends> is permitted; we just omit the element when the
        // federation_peer table is absent or holds no active OAI peers.
        $friends = $this->getOaiFriends();
        if (! empty($friends)) {
            $xml .= '    <description>'."\n";
            $xml .= '      <friends xmlns="http://www.openarchives.org/OAI/2.0/friends/"'."\n";
            $xml .= '               xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'."\n";
            $xml .= '               xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/friends/ http://www.openarchives.org/OAI/2.0/friends.xsd">'."\n";
            foreach ($friends as $url) {
                $xml .= '        <baseURL>'.$this->esc($url).'</baseURL>'."\n";
            }
            $xml .= '      </friends>'."\n";
            $xml .= '    </description>'."\n";
        }

        $xml .= '  </Identify>'."\n";
        $xml .= $this->xmlFooter();

        return $this->xmlResponse($xml);
    }

    /**
     * Return base URLs of known OAI-PMH peers from ahg-federation.
     * Empty array when the federation table is missing or no active peers.
     */
    private function getOaiFriends(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('federation_peer')) {
            return [];
        }

        return DB::table('federation_peer')
            ->where('peer_type', 'oai_pmh')
            ->where('is_active', 1)
            ->whereNotNull('base_url')
            ->where('base_url', '!=', '')
            ->pluck('base_url')
            ->all();
    }

    /**
     * ListMetadataFormats verb: return supported metadata formats.
     *
     * Per OAI-PMH 2.0, when called with an identifier argument the response
     * is the subset of formats available for that specific record. Heratio
     * supports the same format set for every record (no per-record limits),
     * so the identifier argument is validated (idDoesNotExist if unknown)
     * but the format list itself does not vary.
     */
    private function listMetadataFormats(Request $request): Response
    {
        // If identifier is supplied, validate it exists - per spec we should
        // return idDoesNotExist otherwise.
        $identifier = $request->input('identifier');
        if ($identifier !== null && $identifier !== '') {
            $oaiLocalId = $this->parseOaiIdentifier($identifier);
            if ($oaiLocalId === null) {
                return $this->errorResponse($request, 'idDoesNotExist');
            }
            $exists = DB::table('information_object')
                ->where('oai_local_identifier', $oaiLocalId)
                ->exists();
            if (! $exists) {
                return $this->errorResponse($request, 'idDoesNotExist');
            }
        }

        $xml = $this->xmlHeader($request);
        $xml .= '  <ListMetadataFormats>'."\n";
        foreach (self::METADATA_FORMATS as $prefix => $spec) {
            $xml .= '    <metadataFormat>'."\n";
            $xml .= '      <metadataPrefix>'.$prefix.'</metadataPrefix>'."\n";
            $xml .= '      <schema>'.$this->esc($spec['schema']).'</schema>'."\n";
            $xml .= '      <metadataNamespace>'.$this->esc($spec['namespace']).'</metadataNamespace>'."\n";
            $xml .= '    </metadataFormat>'."\n";
        }
        $xml .= '  </ListMetadataFormats>'."\n";
        $xml .= $this->xmlFooter();

        return $this->xmlResponse($xml);
    }

    /**
     * Render the metadata body for a record in the requested format.
     * Dublin Core stays inline (renderDublinCore) because it has been hand-
     * tuned for the OAI envelope; the other 4 formats delegate to the
     * ahg-metadata-export serializers which return self-contained XML.
     * Output is indented 8 spaces to align with the surrounding <metadata>.
     */
    private function renderMetadata(object $record, string $metadataPrefix): string
    {
        if ($metadataPrefix === 'oai_dc') {
            return $this->renderDublinCore($record);
        }

        $body = '';
        $culture = $record->source_culture ?? 'en';
        switch ($metadataPrefix) {
            case 'oai_ead':
                $body = (new Ead2002Serializer)->serializeRecord((int) $record->id, $culture, true);
                break;
            case 'oai_ead3':
                $body = (new Ead3Serializer)->serializeRecord((int) $record->id, $culture, true);
                break;
            case 'mods':
                $body = (new ModsSerializer)->serializeRecord((int) $record->id, $culture);
                break;
            case 'marcxml':
                $body = (new MarcxmlSerializer)->serializeRecord((int) $record->id, $culture);
                break;
        }

        if ($body === '') {
            return '';
        }

        // Indent each line by 8 spaces so the body sits inside <metadata>.
        $lines = explode("\n", $body);
        $padded = array_map(fn ($line) => $line === '' ? '' : '        '.$line, $lines);

        return implode("\n", $padded)."\n";
    }

    /**
     * ListSets verb: return available sets (top-level collections).
     */
    private function listSets(Request $request, array $params): Response
    {
        $cursor = (int) ($params['cursor'] ?? 0);
        $pageSize = $this->pageSize();

        // Default behaviour: top-level collections only (IOs whose
        // parent_id = root (id=1) AND publication status = published).
        // When ahg_settings.oai_additional_sets_enabled='1' we drop the
        // parent_id=1 filter so harvesters can subscribe to descendant
        // collections too. Useful for federation harvesters that want
        // sub-fonds-level granularity. Default off because it can balloon
        // the set count from ~10 to thousands.
        $query = DB::table('information_object as io')
            ->join('object as o', 'io.id', '=', 'o.id')
            ->join('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', 'en');
            })
            ->join('status as st', function ($join) {
                $join->on('io.id', '=', 'st.object_id')
                    ->where('st.type_id', '=', 158);
            })
            ->where('st.status_id', '=', 160)
            ->select('io.id', 'io.oai_local_identifier', 'ioi.title')
            ->orderBy('io.id');

        if ((string) $this->setting('oai_additional_sets_enabled', '0') !== '1') {
            $query->where('io.parent_id', '=', 1);
        }

        $totalCount = $query->count();
        $sets = $query->offset($cursor)->limit($pageSize)->get();

        if ($sets->isEmpty() && $cursor === 0) {
            return $this->errorResponse($request, 'noSetHierarchy');
        }

        $xml = $this->xmlHeader($request);
        $xml .= '  <ListSets>'."\n";

        foreach ($sets as $set) {
            $xml .= '    <set>'."\n";
            $xml .= '      <setSpec>'.$this->formatOaiIdentifier($set->oai_local_identifier).'</setSpec>'."\n";
            $xml .= '      <setName>'.$this->esc($set->title ?? '').'</setName>'."\n";
            $xml .= '    </set>'."\n";
        }

        $remaining = $totalCount - $cursor - $sets->count();
        if ($remaining > 0) {
            $token = $this->encodeResumptionToken([
                'cursor' => $cursor + $pageSize,
            ]);
            $xml .= '    <resumptionToken>'.$token.'</resumptionToken>'."\n";
        }

        $xml .= '  </ListSets>'."\n";
        $xml .= $this->xmlFooter();

        return $this->xmlResponse($xml);
    }

    /**
     * ListIdentifiers verb: list record identifiers with datestamps.
     *
     * Streams live records first (phase=live), then transitions to deleted-
     * record tombstones (phase=deleted) via resumptionToken. Harvesters that
     * honour resumption walk all pages and end up with: every live id +
     * every tombstone in the date range. The phase key lives inside the
     * resumption token, not as a user-facing arg.
     */
    private function listIdentifiers(Request $request, array $params): Response
    {
        $phase = $params['phase'] ?? 'live';
        $cursor = (int) ($params['cursor'] ?? 0);
        $from = $params['from'] ?? null;
        $until = $params['until'] ?? null;
        $set = $params['set'] ?? null;
        $metadataPrefix = $params['metadataPrefix'] ?? 'oai_dc';

        $pageSize = $this->pageSize();

        if ($phase === 'deleted') {
            return $this->listIdentifiersDeleted($request, $cursor, $from, $until, $set, $metadataPrefix, $pageSize);
        }

        $query = $this->buildRecordQuery($from, $until, $set);
        $totalCount = $query->count();
        $records = $query->offset($cursor)->limit($pageSize)->get();

        // No live records and no tombstones means noRecordsMatch. With
        // tombstones present, transition straight to the deleted phase.
        if ($records->isEmpty()) {
            $tombstones = $this->getTombstones($from, $until, 0, 1);
            if (empty($tombstones)) {
                return $this->errorResponse($request, 'noRecordsMatch');
            }

            return $this->listIdentifiersDeleted($request, 0, $from, $until, $set, $metadataPrefix, $pageSize);
        }

        $xml = $this->xmlHeader($request);
        $xml .= '  <ListIdentifiers>'."\n";

        foreach ($records as $record) {
            $xml .= $this->renderHeader($record);
        }

        $remaining = $totalCount - $cursor - $records->count();
        if ($remaining > 0) {
            $token = $this->encodeResumptionToken([
                'phase' => 'live',
                'cursor' => $cursor + $pageSize,
                'metadataPrefix' => $metadataPrefix,
                'from' => $from ?? '',
                'until' => $until ?? '',
                'set' => $set ?? '',
            ]);
            $xml .= '    <resumptionToken>'.$token.'</resumptionToken>'."\n";
        } else {
            // Live records exhausted — bridge to deleted-records phase if
            // any tombstones fall in this from/until range.
            $hasTombstones = ! empty($this->getTombstones($from, $until, 0, 1));
            if ($hasTombstones) {
                $token = $this->encodeResumptionToken([
                    'phase' => 'deleted',
                    'cursor' => 0,
                    'metadataPrefix' => $metadataPrefix,
                    'from' => $from ?? '',
                    'until' => $until ?? '',
                    'set' => $set ?? '',
                ]);
                $xml .= '    <resumptionToken>'.$token.'</resumptionToken>'."\n";
            }
        }

        $xml .= '  </ListIdentifiers>'."\n";
        $xml .= $this->xmlFooter();

        return $this->xmlResponse($xml);
    }

    /**
     * Render a page of tombstone headers (status="deleted") for ListIdentifiers.
     */
    private function listIdentifiersDeleted(Request $request, int $cursor, ?string $from, ?string $until, ?string $set, string $metadataPrefix, int $pageSize): Response
    {
        $tombstones = $this->getTombstones($from, $until, $cursor, $pageSize);
        if (empty($tombstones)) {
            return $this->errorResponse($request, 'noRecordsMatch');
        }

        $xml = $this->xmlHeader($request);
        $xml .= '  <ListIdentifiers>'."\n";

        foreach ($tombstones as $t) {
            $xml .= $this->renderTombstoneHeader((int) $t->oai_local_identifier, (string) $t->deleted_at, 4);
        }

        $totalDeleted = $this->countTombstones($from, $until);
        $remaining = $totalDeleted - $cursor - count($tombstones);
        if ($remaining > 0) {
            $token = $this->encodeResumptionToken([
                'phase' => 'deleted',
                'cursor' => $cursor + $pageSize,
                'metadataPrefix' => $metadataPrefix,
                'from' => $from ?? '',
                'until' => $until ?? '',
                'set' => $set ?? '',
            ]);
            $xml .= '    <resumptionToken>'.$token.'</resumptionToken>'."\n";
        }

        $xml .= '  </ListIdentifiers>'."\n";
        $xml .= $this->xmlFooter();

        return $this->xmlResponse($xml);
    }

    /**
     * ListRecords verb: list full records. Same live -> deleted phase
     * transition as listIdentifiers; for the deleted phase we emit only
     * the header (status="deleted") with no <metadata> wrapper per spec.
     */
    private function listRecords(Request $request, array $params): Response
    {
        $phase = $params['phase'] ?? 'live';
        $cursor = (int) ($params['cursor'] ?? 0);
        $from = $params['from'] ?? null;
        $until = $params['until'] ?? null;
        $set = $params['set'] ?? null;
        $metadataPrefix = $params['metadataPrefix'] ?? 'oai_dc';

        $pageSize = $this->pageSize();

        if ($phase === 'deleted') {
            return $this->listRecordsDeleted($request, $cursor, $from, $until, $set, $metadataPrefix, $pageSize);
        }

        $query = $this->buildRecordQuery($from, $until, $set);
        $totalCount = $query->count();
        $records = $query->offset($cursor)->limit($pageSize)->get();

        if ($records->isEmpty()) {
            $tombstones = $this->getTombstones($from, $until, 0, 1);
            if (empty($tombstones)) {
                return $this->errorResponse($request, 'noRecordsMatch');
            }

            return $this->listRecordsDeleted($request, 0, $from, $until, $set, $metadataPrefix, $pageSize);
        }

        $xml = $this->xmlHeader($request);
        $xml .= '  <ListRecords>'."\n";

        foreach ($records as $record) {
            $xml .= '    <record>'."\n";
            $xml .= $this->renderHeader($record, 6);
            $xml .= '      <metadata>'."\n";
            $xml .= $this->renderMetadata($record, $metadataPrefix);
            $xml .= '      </metadata>'."\n";
            $xml .= '    </record>'."\n";
        }

        $remaining = $totalCount - $cursor - $records->count();
        if ($remaining > 0) {
            $token = $this->encodeResumptionToken([
                'phase' => 'live',
                'cursor' => $cursor + $pageSize,
                'metadataPrefix' => $metadataPrefix,
                'from' => $from ?? '',
                'until' => $until ?? '',
                'set' => $set ?? '',
            ]);
            $xml .= '    <resumptionToken>'.$token.'</resumptionToken>'."\n";
        } else {
            $hasTombstones = ! empty($this->getTombstones($from, $until, 0, 1));
            if ($hasTombstones) {
                $token = $this->encodeResumptionToken([
                    'phase' => 'deleted',
                    'cursor' => 0,
                    'metadataPrefix' => $metadataPrefix,
                    'from' => $from ?? '',
                    'until' => $until ?? '',
                    'set' => $set ?? '',
                ]);
                $xml .= '    <resumptionToken>'.$token.'</resumptionToken>'."\n";
            }
        }

        $xml .= '  </ListRecords>'."\n";
        $xml .= $this->xmlFooter();

        return $this->xmlResponse($xml);
    }

    /**
     * Render a page of tombstone records (status="deleted") for ListRecords.
     * Per spec, deleted records have only a <header> — no <metadata> wrapper.
     */
    private function listRecordsDeleted(Request $request, int $cursor, ?string $from, ?string $until, ?string $set, string $metadataPrefix, int $pageSize): Response
    {
        $tombstones = $this->getTombstones($from, $until, $cursor, $pageSize);
        if (empty($tombstones)) {
            return $this->errorResponse($request, 'noRecordsMatch');
        }

        $xml = $this->xmlHeader($request);
        $xml .= '  <ListRecords>'."\n";

        foreach ($tombstones as $t) {
            $xml .= '    <record>'."\n";
            $xml .= $this->renderTombstoneHeader((int) $t->oai_local_identifier, (string) $t->deleted_at, 6);
            $xml .= '    </record>'."\n";
        }

        $totalDeleted = $this->countTombstones($from, $until);
        $remaining = $totalDeleted - $cursor - count($tombstones);
        if ($remaining > 0) {
            $token = $this->encodeResumptionToken([
                'phase' => 'deleted',
                'cursor' => $cursor + $pageSize,
                'metadataPrefix' => $metadataPrefix,
                'from' => $from ?? '',
                'until' => $until ?? '',
                'set' => $set ?? '',
            ]);
            $xml .= '    <resumptionToken>'.$token.'</resumptionToken>'."\n";
        }

        $xml .= '  </ListRecords>'."\n";
        $xml .= $this->xmlFooter();

        return $this->xmlResponse($xml);
    }

    /**
     * GetRecord verb: retrieve a single record by OAI identifier.
     */
    private function getRecord(Request $request, array $params): Response
    {
        $identifier = $params['identifier'] ?? '';
        $metadataPrefix = $params['metadataPrefix'] ?? 'oai_dc';

        $oaiLocalId = $this->parseOaiIdentifier($identifier);
        if ($oaiLocalId === null) {
            return $this->errorResponse($request, 'idDoesNotExist');
        }

        // Tombstone check first — a deleted record exists in OAI's view
        // even though it no longer exists in information_object. Return
        // <header status="deleted"> with no metadata per spec.
        $tomb = $this->getTombstone($oaiLocalId);
        if ($tomb !== null) {
            $xml = $this->xmlHeader($request);
            $xml .= '  <GetRecord>'."\n";
            $xml .= '    <record>'."\n";
            $xml .= $this->renderTombstoneHeader($oaiLocalId, (string) $tomb->deleted_at, 6);
            $xml .= '    </record>'."\n";
            $xml .= '  </GetRecord>'."\n";
            $xml .= $this->xmlFooter();

            return $this->xmlResponse($xml);
        }

        $record = DB::table('information_object as io')
            ->join('object as o', 'io.id', '=', 'o.id')
            ->join('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', 'en');
            })
            ->join('status as st', function ($join) {
                $join->on('io.id', '=', 'st.object_id')
                    ->where('st.type_id', '=', 158);
            })
            ->where('io.oai_local_identifier', '=', $oaiLocalId)
            ->where('st.status_id', '=', 160)
            ->where('io.parent_id', '!=', null)
            ->select(
                'io.id',
                'io.oai_local_identifier',
                'io.identifier',
                'io.level_of_description_id',
                'io.repository_id',
                'io.source_culture',
                'io.lft',
                'io.rgt',
                'io.parent_id',
                'o.updated_at',
                'ioi.title',
                'ioi.scope_and_content',
                'ioi.access_conditions',
                'ioi.location_of_originals',
                'ioi.extent_and_medium',
                'ioi.archival_history',
                'ioi.acquisition',
                'ioi.appraisal',
                'ioi.accruals',
                'ioi.arrangement',
                'ioi.reproduction_conditions',
                'ioi.physical_characteristics',
                'ioi.finding_aids',
                'ioi.location_of_copies',
                'ioi.related_units_of_description',
                'ioi.rules',
                'ioi.sources',
                'ioi.revision_history'
            )
            ->first();

        if (! $record) {
            return $this->errorResponse($request, 'idDoesNotExist');
        }

        $xml = $this->xmlHeader($request);
        $xml .= '  <GetRecord>'."\n";
        $xml .= '    <record>'."\n";
        $xml .= $this->renderHeader($record, 6);
        $xml .= '      <metadata>'."\n";
        $xml .= $this->renderMetadata($record, $metadataPrefix);
        $xml .= '      </metadata>'."\n";
        $xml .= '    </record>'."\n";
        $xml .= '  </GetRecord>'."\n";
        $xml .= $this->xmlFooter();

        return $this->xmlResponse($xml);
    }

    // -----------------------------------------------------------------------
    // Helper methods
    // -----------------------------------------------------------------------

    /**
     * Build the base query for published information objects.
     */
    private function buildRecordQuery(?string $from, ?string $until, ?string $set)
    {
        $query = DB::table('information_object as io')
            ->join('object as o', 'io.id', '=', 'o.id')
            ->join('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', 'en');
            })
            ->join('status as st', function ($join) {
                $join->on('io.id', '=', 'st.object_id')
                    ->where('st.type_id', '=', 158);
            })
            ->where('st.status_id', '=', 160)
            ->whereNotNull('io.parent_id')
            ->where('io.parent_id', '!=', 0)
            ->select(
                'io.id',
                'io.oai_local_identifier',
                'io.identifier',
                'io.level_of_description_id',
                'io.repository_id',
                'io.source_culture',
                'io.lft',
                'io.rgt',
                'io.parent_id',
                'o.updated_at',
                'ioi.title',
                'ioi.scope_and_content',
                'ioi.access_conditions',
                'ioi.location_of_originals',
                'ioi.extent_and_medium',
                'ioi.archival_history',
                'ioi.acquisition',
                'ioi.appraisal',
                'ioi.accruals',
                'ioi.arrangement',
                'ioi.reproduction_conditions',
                'ioi.physical_characteristics',
                'ioi.finding_aids',
                'ioi.location_of_copies',
                'ioi.related_units_of_description',
                'ioi.rules',
                'ioi.sources',
                'ioi.revision_history'
            )
            ->orderBy('io.id');

        if ($from) {
            $query->where('o.updated_at', '>=', $this->mysqlDate($from));
        }

        if ($until) {
            $query->where('o.updated_at', '<=', $this->mysqlDate($until));
        }

        if ($set) {
            // Set is an OAI identifier pointing to a collection; get its lft/rgt
            $setOaiId = $this->parseOaiIdentifier($set);
            if ($setOaiId !== null) {
                $collection = DB::table('information_object')
                    ->where('oai_local_identifier', $setOaiId)
                    ->select('lft', 'rgt')
                    ->first();

                if ($collection) {
                    $query->where('io.lft', '>=', $collection->lft);
                    $query->where('io.rgt', '<=', $collection->rgt);
                }
            }
        }

        return $query;
    }

    /**
     * Render a <header> element for a record.
     */
    private function renderHeader(object $record, int $indent = 4): string
    {
        $pad = str_repeat(' ', $indent);
        $xml = $pad.'<header>'."\n";
        $xml .= $pad.'  <identifier>'.$this->formatOaiIdentifier($record->oai_local_identifier).'</identifier>'."\n";
        $xml .= $pad.'  <datestamp>'.$this->getDate($record->updated_at).'</datestamp>'."\n";

        // Find the collection root (top-level ancestor) for setSpec
        $collectionRoot = $this->getCollectionRoot($record);
        if ($collectionRoot) {
            $xml .= $pad.'  <setSpec>'.$this->formatOaiIdentifier($collectionRoot->oai_local_identifier).'</setSpec>'."\n";
        }

        $xml .= $pad.'</header>'."\n";

        return $xml;
    }

    /**
     * Render a tombstone <header status="deleted"> for an oai_local_identifier.
     * Per OAI-PMH 2.0 deleted-record records have only the header — no
     * setSpec (we don't track which set a deleted record belonged to) and
     * no <metadata> wrapper. The datestamp is the deletion timestamp.
     */
    private function renderTombstoneHeader(int $oaiLocalId, string $deletedAt, int $indent = 4): string
    {
        $pad = str_repeat(' ', $indent);
        $xml = $pad.'<header status="deleted">'."\n";
        $xml .= $pad.'  <identifier>'.$this->formatOaiIdentifier($oaiLocalId).'</identifier>'."\n";
        $xml .= $pad.'  <datestamp>'.$this->getDate($deletedAt).'</datestamp>'."\n";
        $xml .= $pad.'</header>'."\n";

        return $xml;
    }

    /**
     * Return a single tombstone row or null when no tombstone exists.
     */
    private function getTombstone(int $oaiLocalId): ?object
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('oai_deleted_record')) {
            return null;
        }

        return DB::table('oai_deleted_record')
            ->where('oai_local_identifier', $oaiLocalId)
            ->select('oai_local_identifier', 'deleted_at', 'reason')
            ->first();
    }

    /**
     * Page of tombstones filtered by deleted_at in from/until range.
     */
    private function getTombstones(?string $from, ?string $until, int $cursor, int $limit): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('oai_deleted_record')) {
            return [];
        }
        $q = DB::table('oai_deleted_record')
            ->select('oai_local_identifier', 'deleted_at')
            ->orderBy('deleted_at')
            ->orderBy('oai_local_identifier');
        if ($from) {
            $q->where('deleted_at', '>=', $this->mysqlDate($from));
        }
        if ($until) {
            $q->where('deleted_at', '<=', $this->mysqlDate($until));
        }

        return $q->offset($cursor)->limit($limit)->get()->all();
    }

    private function countTombstones(?string $from, ?string $until): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('oai_deleted_record')) {
            return 0;
        }
        $q = DB::table('oai_deleted_record');
        if ($from) {
            $q->where('deleted_at', '>=', $this->mysqlDate($from));
        }
        if ($until) {
            $q->where('deleted_at', '<=', $this->mysqlDate($until));
        }

        return (int) $q->count();
    }

    /**
     * Render Dublin Core metadata for a record.
     */
    private function renderDublinCore(object $record): string
    {
        $xml = '        <oai_dc:dc'."\n";
        $xml .= '            xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"'."\n";
        $xml .= '            xmlns:dc="http://purl.org/dc/elements/1.1/"'."\n";
        $xml .= '            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'."\n";
        $xml .= '            xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/'."\n";
        $xml .= '            http://www.openarchives.org/OAI/2.0/oai_dc.xsd">'."\n";

        // dc:title
        if (! empty($record->title)) {
            $xml .= '          <dc:title>'.$this->esc($record->title).'</dc:title>'."\n";
        }

        // dc:creator - from events table (type_id=111 = Creation), with
        // IPTC By-line fallback (issue #752): if the ISAD(G) author slot
        // is empty AND dam_iptc_metadata.creator carries a value, emit
        // that instead so harvesters see the extracted attribution.
        $creators = DB::table('event as e')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('e.actor_id', '=', 'ai.id')
                    ->where('ai.culture', '=', 'en');
            })
            ->where('e.object_id', '=', $record->id)
            ->where('e.type_id', '=', 111)
            ->whereNotNull('e.actor_id')
            ->select('ai.authorized_form_of_name')
            ->get();

        $canonicalCreators = $creators
            ->pluck('authorized_form_of_name')
            ->filter()
            ->map(static fn ($v) => (string) $v)
            ->all();
        $resolver = new IptcFallbackResolver();
        foreach ($resolver->resolveCreatorsWithCanonical((int) $record->id, $canonicalCreators) as $name) {
            $xml .= '          <dc:creator>'.$this->esc($name).'</dc:creator>'."\n";
        }

        // dc:subject - from object_term_relation joined to subject
        // taxonomy terms, with IPTC Keywords fallback (issue #752).
        $subjects = DB::table('object_term_relation as otr')
            ->join('term as t', 'otr.term_id', '=', 't.id')
            ->join('term_i18n as ti', function ($join) {
                $join->on('t.id', '=', 'ti.id')
                    ->where('ti.culture', '=', 'en');
            })
            ->where('otr.object_id', '=', $record->id)
            ->where('t.taxonomy_id', '=', 35) // Subject taxonomy
            ->select('ti.name')
            ->get();

        $canonicalSubjects = $subjects
            ->pluck('name')
            ->filter()
            ->map(static fn ($v) => (string) $v)
            ->all();
        foreach ($resolver->resolveSubjectsWithCanonical((int) $record->id, $canonicalSubjects) as $name) {
            $xml .= '          <dc:subject>'.$this->esc($name).'</dc:subject>'."\n";
        }

        // dc:description
        if (! empty($record->scope_and_content)) {
            $xml .= '          <dc:description>'.$this->esc(strip_tags($record->scope_and_content)).'</dc:description>'."\n";
        }

        // dc:publisher — from events (publishers)
        $publishers = DB::table('event as e')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('e.actor_id', '=', 'ai.id')
                    ->where('ai.culture', '=', 'en');
            })
            ->where('e.object_id', '=', $record->id)
            ->where('e.type_id', '=', 114) // Publication event type
            ->whereNotNull('e.actor_id')
            ->select('ai.authorized_form_of_name')
            ->get();

        foreach ($publishers as $publisher) {
            if (! empty($publisher->authorized_form_of_name)) {
                $xml .= '          <dc:publisher>'.$this->esc($publisher->authorized_form_of_name).'</dc:publisher>'."\n";
            }
        }

        // dc:contributor — from events (contribution)
        $contributors = DB::table('event as e')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('e.actor_id', '=', 'ai.id')
                    ->where('ai.culture', '=', 'en');
            })
            ->where('e.object_id', '=', $record->id)
            ->where('e.type_id', '=', 113) // Contribution event type
            ->whereNotNull('e.actor_id')
            ->select('ai.authorized_form_of_name')
            ->get();

        foreach ($contributors as $contributor) {
            if (! empty($contributor->authorized_form_of_name)) {
                $xml .= '          <dc:contributor>'.$this->esc($contributor->authorized_form_of_name).'</dc:contributor>'."\n";
            }
        }

        // dc:date — from events (creation dates)
        $dates = DB::table('event')
            ->where('object_id', '=', $record->id)
            ->where('type_id', '=', 111)
            ->select('start_date', 'end_date')
            ->get();

        foreach ($dates as $date) {
            if (! empty($date->start_date)) {
                $dateStr = $date->start_date;
                if (! empty($date->end_date) && $date->end_date !== $date->start_date) {
                    $dateStr .= '/'.$date->end_date;
                }
                $xml .= '          <dc:date>'.$this->esc($dateStr).'</dc:date>'."\n";
            }
        }

        // Also check event_i18n.date for free-text dates
        $eventDates = DB::table('event as e')
            ->join('event_i18n as ei', function ($join) {
                $join->on('e.id', '=', 'ei.id')
                    ->where('ei.culture', '=', 'en');
            })
            ->where('e.object_id', '=', $record->id)
            ->where('e.type_id', '=', 111)
            ->whereNotNull('ei.date')
            ->where('ei.date', '!=', '')
            ->select('ei.date')
            ->get();

        if ($dates->isEmpty()) {
            foreach ($eventDates as $eventDate) {
                $xml .= '          <dc:date>'.$this->esc($eventDate->date).'</dc:date>'."\n";
            }
        }

        // dc:type — level of description
        if (! empty($record->level_of_description_id)) {
            $levelTerm = DB::table('term_i18n')
                ->where('id', '=', $record->level_of_description_id)
                ->where('culture', '=', 'en')
                ->value('name');

            if ($levelTerm) {
                $xml .= '          <dc:type>'.$this->esc($levelTerm).'</dc:type>'."\n";
            }
        }

        // dc:format — from extent_and_medium
        if (! empty($record->extent_and_medium)) {
            $xml .= '          <dc:format>'.$this->esc(strip_tags($record->extent_and_medium)).'</dc:format>'."\n";
        }

        // dc:identifier — URL and reference code
        $slug = DB::table('slug')
            ->where('object_id', '=', $record->id)
            ->value('slug');

        if ($slug) {
            $xml .= '          <dc:identifier>'.$this->esc(url('/'.$slug)).'</dc:identifier>'."\n";
        }

        if (! empty($record->identifier)) {
            $xml .= '          <dc:identifier>'.$this->esc($record->identifier).'</dc:identifier>'."\n";
        }

        // dc:source — location of originals
        if (! empty($record->location_of_originals)) {
            $xml .= '          <dc:source>'.$this->esc(strip_tags($record->location_of_originals)).'</dc:source>'."\n";
        }

        // dc:language
        if (! empty($record->source_culture)) {
            $xml .= '          <dc:language>'.$this->esc($record->source_culture).'</dc:language>'."\n";
        }

        // dc:relation — repository
        if (! empty($record->repository_id)) {
            $repo = DB::table('actor_i18n')
                ->where('id', '=', $record->repository_id)
                ->where('culture', '=', 'en')
                ->value('authorized_form_of_name');

            $repoSlug = DB::table('slug')
                ->where('object_id', '=', $record->repository_id)
                ->value('slug');

            if ($repoSlug) {
                $xml .= '          <dc:relation>'.$this->esc(url('/'.$repoSlug)).'</dc:relation>'."\n";
            }
            if ($repo) {
                $xml .= '          <dc:relation>'.$this->esc($repo).'</dc:relation>'."\n";
            }
        }

        // dc:coverage — places from object_term_relation (place taxonomy = 42)
        $places = DB::table('object_term_relation as otr')
            ->join('term as t', 'otr.term_id', '=', 't.id')
            ->join('term_i18n as ti', function ($join) {
                $join->on('t.id', '=', 'ti.id')
                    ->where('ti.culture', '=', 'en');
            })
            ->where('otr.object_id', '=', $record->id)
            ->where('t.taxonomy_id', '=', 42) // Place taxonomy
            ->select('ti.name')
            ->get();

        foreach ($places as $place) {
            if (! empty($place->name)) {
                $xml .= '          <dc:coverage>'.$this->esc($place->name).'</dc:coverage>'."\n";
            }
        }

        // dc:rights - per issue #752 the canonical field is ISAD(G) 3.4.2
        // reproduction_conditions; fall through to access_conditions for
        // back-compat with previous OAI-DC harvests, then to the IPTC
        // Copyright Notice when both ISAD fields are blank.
        $canonicalRights = null;
        if (! empty($record->reproduction_conditions)) {
            $canonicalRights = strip_tags((string) $record->reproduction_conditions);
        } elseif (! empty($record->access_conditions)) {
            $canonicalRights = strip_tags((string) $record->access_conditions);
        }
        $rightsOut = $resolver->resolveRightsWithCanonical((int) $record->id, $canonicalRights);
        if ($rightsOut !== null && $rightsOut !== '') {
            $xml .= '          <dc:rights>'.$this->esc($rightsOut).'</dc:rights>'."\n";
        }

        $xml .= '        </oai_dc:dc>'."\n";

        return $xml;
    }

    /**
     * Find the collection root (top-level ancestor under the root node) for a record.
     */
    private function getCollectionRoot(object $record): ?object
    {
        // Walk up parent_id chain to find the top-level collection (parent_id = 1)
        // Use lft/rgt: find the ancestor whose parent_id = 1 and lft <= record.lft and rgt >= record.rgt
        $root = DB::table('information_object')
            ->where('parent_id', '=', 1)
            ->where('lft', '<=', $record->lft)
            ->where('rgt', '>=', $record->rgt)
            ->select('id', 'oai_local_identifier')
            ->first();

        // If the record itself is top-level
        if (! $root && $record->parent_id == 1) {
            return (object) [
                'oai_local_identifier' => $record->oai_local_identifier,
            ];
        }

        return $root;
    }

    /**
     * Encode a resumption token from an associative array.
     */
    private function encodeResumptionToken(array $data): string
    {
        return base64_encode(json_encode($data));
    }

    /**
     * Decode a resumption token. Returns false on failure.
     */
    private function decodeResumptionToken(string $token): array|false
    {
        $json = base64_decode($token, true);
        if ($json === false) {
            return false;
        }

        $data = json_decode($json, true);
        if (! is_array($data)) {
            return false;
        }

        return $data;
    }

    /**
     * Validate an ISO 8601 date string (YYYY-MM-DD or YYYY-MM-DDThh:mm:ssZ).
     */
    private function isValidDate(string $date): bool
    {
        $parts = explode('-', $date);
        if (count($parts) !== 3) {
            return false;
        }

        // If time is part of the date, validate it
        if ($T_pos = strpos($parts[2], 'T')) {
            $time = substr($parts[2], $T_pos);
            $parts[2] = substr($parts[2], 0, $T_pos);
            if (! preg_match('/^T(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]Z$/i', $time)) {
                return false;
            }
        }

        if (! @checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
            return false;
        }

        return true;
    }

    /**
     * Convert an ISO 8601 UTC date to MySQL datetime format.
     */
    private function mysqlDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        return date('Y-m-d H:i:s', strtotime($date));
    }

    /**
     * Escape special XML characters.
     */
    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Generate the XML envelope header.
     */
    private function xmlHeader(Request $request): string
    {
        $date = $this->getDate();
        $requestUrl = $request->url();

        // Build request attributes string. Use input() so the same envelope
        // works for both GET (query string) and POST (form body) requests
        // per OAI-PMH 2.0 spec section 3.4. Filter out framework noise.
        $attrs = '';
        $reqParams = $request->all();
        unset($reqParams['_token'], $reqParams['_method']);
        foreach ($reqParams as $key => $value) {
            if (! is_scalar($value)) {
                continue;
            }
            $attrs .= ' '.$key.'="'.$this->esc((string) $value).'"';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"'."\n";
        $xml .= '         xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'."\n";
        $xml .= '         xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd">'."\n";
        $xml .= '  <responseDate>'.$date.'</responseDate>'."\n";
        $xml .= '  <request'.$attrs.'>'.$this->esc($requestUrl).'</request>'."\n";

        return $xml;
    }

    /**
     * Generate the XML envelope footer.
     */
    private function xmlFooter(): string
    {
        return '</OAI-PMH>'."\n";
    }

    /**
     * Build and return an XML error response.
     */
    private function errorResponse(Request $request, string $errorCode): Response
    {
        $errorMsg = self::ERRORS[$errorCode] ?? 'Unknown error.';

        $xml = $this->xmlHeader($request);
        $xml .= '  <error code="'.$errorCode.'">'.$this->esc($errorMsg).'</error>'."\n";
        $xml .= $this->xmlFooter();

        return $this->xmlResponse($xml);
    }

    /**
     * Return an XML response with the correct content type.
     */
    private function xmlResponse(string $xml): Response
    {
        return new Response($xml, 200, [
            'Content-Type' => 'text/xml; charset=UTF-8',
        ]);
    }
}
