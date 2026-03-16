<?php

namespace App\Http\Controllers;

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
        'badArgument'              => 'The request includes illegal arguments, is missing required arguments, includes a repeated argument, or values for arguments have an illegal syntax.',
        'badResumptionToken'       => 'The value of the resumptionToken argument is invalid or expired.',
        'badVerb'                  => 'Value of the verb argument is not a legal OAI-PMH verb, the verb argument is missing, or the verb argument is repeated.',
        'cannotDisseminateFormat'  => 'The metadata format identified by the value given for the metadataPrefix argument is not supported by the item or by the repository.',
        'idDoesNotExist'           => 'The value of the identifier argument is unknown or illegal in this repository.',
        'noRecordsMatch'           => 'The combination of the values of the from, until, set and metadataPrefix arguments results in an empty list.',
        'noMetadataFormats'        => 'There are no metadata formats available for the specified item.',
        'noSetHierarchy'           => 'The repository does not support sets.',
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
        'Identify'            => ['allowed' => ['verb'], 'mandatory' => ['verb']],
        'ListMetadataFormats' => ['allowed' => ['verb', 'identifier'], 'mandatory' => ['verb']],
        'ListSets'            => ['allowed' => ['verb', 'resumptionToken'], 'mandatory' => ['verb']],
        'ListIdentifiers'     => ['allowed' => ['verb', 'metadataPrefix', 'from', 'until', 'set', 'resumptionToken'], 'mandatory' => ['verb', 'metadataPrefix']],
        'ListRecords'         => ['allowed' => ['verb', 'metadataPrefix', 'from', 'until', 'set', 'resumptionToken'], 'mandatory' => ['verb', 'metadataPrefix']],
        'GetRecord'           => ['allowed' => ['verb', 'identifier', 'metadataPrefix'], 'mandatory' => ['verb', 'identifier', 'metadataPrefix']],
    ];

    /**
     * Page size for resumption tokens.
     */
    private const PAGE_SIZE = 100;

    /**
     * Repository identifier (domain name).
     */
    private function getRepositoryIdentifier(): string
    {
        $host = request()->getHost();

        return $host;
    }

    /**
     * Format an OAI identifier from an oai_local_identifier.
     */
    private function formatOaiIdentifier(int $oaiLocalId): string
    {
        return 'oai:' . $this->getRepositoryIdentifier() . ':' . $oaiLocalId;
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
        $verb = $request->query('verb');

        // Check verb is present and valid
        if (empty($verb) || !in_array($verb, self::VERBS)) {
            return $this->errorResponse($request, 'badVerb');
        }

        // If resumptionToken is present, decode and apply its parameters
        $params = $request->query();
        if (isset($params['resumptionToken'])) {
            $decoded = $this->decodeResumptionToken($params['resumptionToken']);
            if ($decoded === false) {
                return $this->errorResponse($request, 'badResumptionToken');
            }
            // Apply token params (they override query params except verb)
            $params = array_merge($params, $decoded);
            // When resumptionToken is present, the only other allowed param is verb
            $queryKeys = array_keys($request->query());
            $nonTokenKeys = array_diff($queryKeys, ['verb', 'resumptionToken']);
            if (count($nonTokenKeys) > 0) {
                return $this->errorResponse($request, 'badArgument');
            }
        } else {
            // Validate allowed/mandatory parameters
            $queryKeys = array_keys($request->query());
            $verbConfig = self::VERB_PARAMS[$verb];

            foreach ($queryKeys as $key) {
                if (!in_array($key, $verbConfig['allowed'])) {
                    return $this->errorResponse($request, 'badArgument');
                }
            }

            foreach ($verbConfig['mandatory'] as $mandatoryKey) {
                if (!in_array($mandatoryKey, $queryKeys)) {
                    return $this->errorResponse($request, 'badArgument');
                }
            }
        }

        // Check metadataPrefix is valid (oai_dc only for now)
        $metadataPrefix = $params['metadataPrefix'] ?? null;
        if ($metadataPrefix !== null && $metadataPrefix !== '' && $metadataPrefix !== 'oai_dc') {
            return $this->errorResponse($request, 'cannotDisseminateFormat');
        }

        // Validate date parameters
        foreach (['from', 'until'] as $dateParam) {
            if (isset($params[$dateParam]) && $params[$dateParam] !== '' && !$this->isValidDate($params[$dateParam])) {
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
        $adminEmail = config('mail.from.address', 'admin@' . $request->getHost());

        $xml = $this->xmlHeader($request);
        $xml .= '  <Identify>' . "\n";
        $xml .= '    <repositoryName>' . $this->esc($repositoryName) . '</repositoryName>' . "\n";
        $xml .= '    <baseURL>' . $this->esc($baseUrl) . '</baseURL>' . "\n";
        $xml .= '    <protocolVersion>2.0</protocolVersion>' . "\n";
        $xml .= '    <adminEmail>' . $this->esc($adminEmail) . '</adminEmail>' . "\n";
        $xml .= '    <earliestDatestamp>' . $earliestDatestamp . '</earliestDatestamp>' . "\n";
        $xml .= '    <deletedRecord>no</deletedRecord>' . "\n";
        $xml .= '    <granularity>YYYY-MM-DDThh:mm:ssZ</granularity>' . "\n";
        $xml .= '    <description>' . "\n";
        $xml .= '      <oai-identifier xmlns="http://www.openarchives.org/OAI/2.0/oai-identifier"' . "\n";
        $xml .= '                      xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . "\n";
        $xml .= '                      xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai-identifier http://www.openarchives.org/OAI/2.0/oai-identifier.xsd">' . "\n";
        $xml .= '        <scheme>oai</scheme>' . "\n";
        $xml .= '        <repositoryIdentifier>' . $this->esc($this->getRepositoryIdentifier()) . '</repositoryIdentifier>' . "\n";
        $xml .= '        <delimiter>:</delimiter>' . "\n";
        $xml .= '        <sampleIdentifier>' . $this->formatOaiIdentifier(100002) . '</sampleIdentifier>' . "\n";
        $xml .= '      </oai-identifier>' . "\n";
        $xml .= '    </description>' . "\n";
        $xml .= '  </Identify>' . "\n";
        $xml .= $this->xmlFooter();

        return $this->xmlResponse($xml);
    }

    /**
     * ListMetadataFormats verb: return supported metadata formats.
     */
    private function listMetadataFormats(Request $request): Response
    {
        $xml = $this->xmlHeader($request);
        $xml .= '  <ListMetadataFormats>' . "\n";
        $xml .= '    <metadataFormat>' . "\n";
        $xml .= '      <metadataPrefix>oai_dc</metadataPrefix>' . "\n";
        $xml .= '      <schema>http://www.openarchives.org/OAI/2.0/oai_dc.xsd</schema>' . "\n";
        $xml .= '      <metadataNamespace>http://www.openarchives.org/OAI/2.0/oai_dc/</metadataNamespace>' . "\n";
        $xml .= '    </metadataFormat>' . "\n";
        $xml .= '  </ListMetadataFormats>' . "\n";
        $xml .= $this->xmlFooter();

        return $this->xmlResponse($xml);
    }

    /**
     * ListSets verb: return available sets (top-level collections).
     */
    private function listSets(Request $request, array $params): Response
    {
        $cursor = (int) ($params['cursor'] ?? 0);

        // Top-level collections: IOs whose parent_id = root (id=1) and published
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
            ->where('io.parent_id', '=', 1)
            ->where('st.status_id', '=', 160)
            ->select('io.id', 'io.oai_local_identifier', 'ioi.title')
            ->orderBy('io.id');

        $totalCount = $query->count();
        $sets = $query->offset($cursor)->limit(self::PAGE_SIZE)->get();

        if ($sets->isEmpty() && $cursor === 0) {
            return $this->errorResponse($request, 'noSetHierarchy');
        }

        $xml = $this->xmlHeader($request);
        $xml .= '  <ListSets>' . "\n";

        foreach ($sets as $set) {
            $xml .= '    <set>' . "\n";
            $xml .= '      <setSpec>' . $this->formatOaiIdentifier($set->oai_local_identifier) . '</setSpec>' . "\n";
            $xml .= '      <setName>' . $this->esc($set->title ?? '') . '</setName>' . "\n";
            $xml .= '    </set>' . "\n";
        }

        $remaining = $totalCount - $cursor - $sets->count();
        if ($remaining > 0) {
            $token = $this->encodeResumptionToken([
                'cursor' => $cursor + self::PAGE_SIZE,
            ]);
            $xml .= '    <resumptionToken>' . $token . '</resumptionToken>' . "\n";
        }

        $xml .= '  </ListSets>' . "\n";
        $xml .= $this->xmlFooter();

        return $this->xmlResponse($xml);
    }

    /**
     * ListIdentifiers verb: list record identifiers with datestamps.
     */
    private function listIdentifiers(Request $request, array $params): Response
    {
        $cursor = (int) ($params['cursor'] ?? 0);
        $from = $params['from'] ?? null;
        $until = $params['until'] ?? null;
        $set = $params['set'] ?? null;
        $metadataPrefix = $params['metadataPrefix'] ?? 'oai_dc';

        $query = $this->buildRecordQuery($from, $until, $set);
        $totalCount = $query->count();
        $records = $query->offset($cursor)->limit(self::PAGE_SIZE)->get();

        if ($records->isEmpty()) {
            return $this->errorResponse($request, 'noRecordsMatch');
        }

        $xml = $this->xmlHeader($request);
        $xml .= '  <ListIdentifiers>' . "\n";

        foreach ($records as $record) {
            $xml .= $this->renderHeader($record);
        }

        $remaining = $totalCount - $cursor - $records->count();
        if ($remaining > 0) {
            $token = $this->encodeResumptionToken([
                'cursor' => $cursor + self::PAGE_SIZE,
                'metadataPrefix' => $metadataPrefix,
                'from' => $from ?? '',
                'until' => $until ?? '',
                'set' => $set ?? '',
            ]);
            $xml .= '    <resumptionToken>' . $token . '</resumptionToken>' . "\n";
        }

        $xml .= '  </ListIdentifiers>' . "\n";
        $xml .= $this->xmlFooter();

        return $this->xmlResponse($xml);
    }

    /**
     * ListRecords verb: list full records with DC metadata.
     */
    private function listRecords(Request $request, array $params): Response
    {
        $cursor = (int) ($params['cursor'] ?? 0);
        $from = $params['from'] ?? null;
        $until = $params['until'] ?? null;
        $set = $params['set'] ?? null;
        $metadataPrefix = $params['metadataPrefix'] ?? 'oai_dc';

        $query = $this->buildRecordQuery($from, $until, $set);
        $totalCount = $query->count();
        $records = $query->offset($cursor)->limit(self::PAGE_SIZE)->get();

        if ($records->isEmpty()) {
            return $this->errorResponse($request, 'noRecordsMatch');
        }

        $xml = $this->xmlHeader($request);
        $xml .= '  <ListRecords>' . "\n";

        foreach ($records as $record) {
            $xml .= '    <record>' . "\n";
            $xml .= $this->renderHeader($record, 6);
            $xml .= '      <metadata>' . "\n";
            $xml .= $this->renderDublinCore($record);
            $xml .= '      </metadata>' . "\n";
            $xml .= '    </record>' . "\n";
        }

        $remaining = $totalCount - $cursor - $records->count();
        if ($remaining > 0) {
            $token = $this->encodeResumptionToken([
                'cursor' => $cursor + self::PAGE_SIZE,
                'metadataPrefix' => $metadataPrefix,
                'from' => $from ?? '',
                'until' => $until ?? '',
                'set' => $set ?? '',
            ]);
            $xml .= '    <resumptionToken>' . $token . '</resumptionToken>' . "\n";
        }

        $xml .= '  </ListRecords>' . "\n";
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

        if (!$record) {
            return $this->errorResponse($request, 'idDoesNotExist');
        }

        $xml = $this->xmlHeader($request);
        $xml .= '  <GetRecord>' . "\n";
        $xml .= '    <record>' . "\n";
        $xml .= $this->renderHeader($record, 6);
        $xml .= '      <metadata>' . "\n";
        $xml .= $this->renderDublinCore($record);
        $xml .= '      </metadata>' . "\n";
        $xml .= '    </record>' . "\n";
        $xml .= '  </GetRecord>' . "\n";
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
        $xml  = $pad . '<header>' . "\n";
        $xml .= $pad . '  <identifier>' . $this->formatOaiIdentifier($record->oai_local_identifier) . '</identifier>' . "\n";
        $xml .= $pad . '  <datestamp>' . $this->getDate($record->updated_at) . '</datestamp>' . "\n";

        // Find the collection root (top-level ancestor) for setSpec
        $collectionRoot = $this->getCollectionRoot($record);
        if ($collectionRoot) {
            $xml .= $pad . '  <setSpec>' . $this->formatOaiIdentifier($collectionRoot->oai_local_identifier) . '</setSpec>' . "\n";
        }

        $xml .= $pad . '</header>' . "\n";

        return $xml;
    }

    /**
     * Render Dublin Core metadata for a record.
     */
    private function renderDublinCore(object $record): string
    {
        $xml  = '        <oai_dc:dc' . "\n";
        $xml .= '            xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"' . "\n";
        $xml .= '            xmlns:dc="http://purl.org/dc/elements/1.1/"' . "\n";
        $xml .= '            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . "\n";
        $xml .= '            xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/' . "\n";
        $xml .= '            http://www.openarchives.org/OAI/2.0/oai_dc.xsd">' . "\n";

        // dc:title
        if (!empty($record->title)) {
            $xml .= '          <dc:title>' . $this->esc($record->title) . '</dc:title>' . "\n";
        }

        // dc:creator — from events table (type_id=111 = Creation)
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

        foreach ($creators as $creator) {
            if (!empty($creator->authorized_form_of_name)) {
                $xml .= '          <dc:creator>' . $this->esc($creator->authorized_form_of_name) . '</dc:creator>' . "\n";
            }
        }

        // dc:subject — from object_term_relation joined to subject taxonomy terms
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

        foreach ($subjects as $subject) {
            if (!empty($subject->name)) {
                $xml .= '          <dc:subject>' . $this->esc($subject->name) . '</dc:subject>' . "\n";
            }
        }

        // dc:description
        if (!empty($record->scope_and_content)) {
            $xml .= '          <dc:description>' . $this->esc(strip_tags($record->scope_and_content)) . '</dc:description>' . "\n";
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
            if (!empty($publisher->authorized_form_of_name)) {
                $xml .= '          <dc:publisher>' . $this->esc($publisher->authorized_form_of_name) . '</dc:publisher>' . "\n";
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
            if (!empty($contributor->authorized_form_of_name)) {
                $xml .= '          <dc:contributor>' . $this->esc($contributor->authorized_form_of_name) . '</dc:contributor>' . "\n";
            }
        }

        // dc:date — from events (creation dates)
        $dates = DB::table('event')
            ->where('object_id', '=', $record->id)
            ->where('type_id', '=', 111)
            ->select('start_date', 'end_date')
            ->get();

        foreach ($dates as $date) {
            if (!empty($date->start_date)) {
                $dateStr = $date->start_date;
                if (!empty($date->end_date) && $date->end_date !== $date->start_date) {
                    $dateStr .= '/' . $date->end_date;
                }
                $xml .= '          <dc:date>' . $this->esc($dateStr) . '</dc:date>' . "\n";
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
                $xml .= '          <dc:date>' . $this->esc($eventDate->date) . '</dc:date>' . "\n";
            }
        }

        // dc:type — level of description
        if (!empty($record->level_of_description_id)) {
            $levelTerm = DB::table('term_i18n')
                ->where('id', '=', $record->level_of_description_id)
                ->where('culture', '=', 'en')
                ->value('name');

            if ($levelTerm) {
                $xml .= '          <dc:type>' . $this->esc($levelTerm) . '</dc:type>' . "\n";
            }
        }

        // dc:format — from extent_and_medium
        if (!empty($record->extent_and_medium)) {
            $xml .= '          <dc:format>' . $this->esc(strip_tags($record->extent_and_medium)) . '</dc:format>' . "\n";
        }

        // dc:identifier — URL and reference code
        $slug = DB::table('slug')
            ->where('object_id', '=', $record->id)
            ->value('slug');

        if ($slug) {
            $xml .= '          <dc:identifier>' . $this->esc(url('/' . $slug)) . '</dc:identifier>' . "\n";
        }

        if (!empty($record->identifier)) {
            $xml .= '          <dc:identifier>' . $this->esc($record->identifier) . '</dc:identifier>' . "\n";
        }

        // dc:source — location of originals
        if (!empty($record->location_of_originals)) {
            $xml .= '          <dc:source>' . $this->esc(strip_tags($record->location_of_originals)) . '</dc:source>' . "\n";
        }

        // dc:language
        if (!empty($record->source_culture)) {
            $xml .= '          <dc:language>' . $this->esc($record->source_culture) . '</dc:language>' . "\n";
        }

        // dc:relation — repository
        if (!empty($record->repository_id)) {
            $repo = DB::table('actor_i18n')
                ->where('id', '=', $record->repository_id)
                ->where('culture', '=', 'en')
                ->value('authorized_form_of_name');

            $repoSlug = DB::table('slug')
                ->where('object_id', '=', $record->repository_id)
                ->value('slug');

            if ($repoSlug) {
                $xml .= '          <dc:relation>' . $this->esc(url('/' . $repoSlug)) . '</dc:relation>' . "\n";
            }
            if ($repo) {
                $xml .= '          <dc:relation>' . $this->esc($repo) . '</dc:relation>' . "\n";
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
            if (!empty($place->name)) {
                $xml .= '          <dc:coverage>' . $this->esc($place->name) . '</dc:coverage>' . "\n";
            }
        }

        // dc:rights
        if (!empty($record->access_conditions)) {
            $xml .= '          <dc:rights>' . $this->esc(strip_tags($record->access_conditions)) . '</dc:rights>' . "\n";
        }

        $xml .= '        </oai_dc:dc>' . "\n";

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
        if (!$root && $record->parent_id == 1) {
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
        if (!is_array($data)) {
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
            if (!preg_match('/^T(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]Z$/i', $time)) {
                return false;
            }
        }

        if (!@checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
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

        // Build request attributes string
        $attrs = '';
        foreach ($request->query() as $key => $value) {
            $attrs .= ' ' . $key . '="' . $this->esc((string) $value) . '"';
        }

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"' . "\n";
        $xml .= '         xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . "\n";
        $xml .= '         xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd">' . "\n";
        $xml .= '  <responseDate>' . $date . '</responseDate>' . "\n";
        $xml .= '  <request' . $attrs . '>' . $this->esc($requestUrl) . '</request>' . "\n";

        return $xml;
    }

    /**
     * Generate the XML envelope footer.
     */
    private function xmlFooter(): string
    {
        return '</OAI-PMH>' . "\n";
    }

    /**
     * Build and return an XML error response.
     */
    private function errorResponse(Request $request, string $errorCode): Response
    {
        $errorMsg = self::ERRORS[$errorCode] ?? 'Unknown error.';

        $xml = $this->xmlHeader($request);
        $xml .= '  <error code="' . $errorCode . '">' . $this->esc($errorMsg) . '</error>' . "\n";
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
