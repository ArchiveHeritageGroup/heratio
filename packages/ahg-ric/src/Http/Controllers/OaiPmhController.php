<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * OAI-PMH v2.0 endpoint. Implements the six verbs (Identify,
 * ListMetadataFormats, ListSets, ListIdentifiers, ListRecords, GetRecord)
 * over information_object. Supported metadata prefixes:
 *   - oai_dc    (Dublin Core — mapped from IO title/identifier/scope/creator)
 *   - rico_ld   (RiC-O JSON-LD wrapped in CDATA — non-standard but useful)
 *
 * Spec: https://www.openarchives.org/OAI/openarchivesprotocol.html
 */

namespace AhgRic\Http\Controllers;

use App\Http\Controllers\Controller;
use AhgRic\Services\RicSerializationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class OaiPmhController extends Controller
{
    private const PAGE_SIZE = 100;
    private const GRANULARITY = 'YYYY-MM-DDThh:mm:ssZ';
    private const PROTOCOL_VERSION = '2.0';
    private const SUPPORTED_PREFIXES = ['oai_dc', 'rico_ld'];

    private RicSerializationService $serializer;

    public function __construct()
    {
        $this->serializer = new RicSerializationService();
    }

    /** GET or POST /api/ric/v1/oai */
    public function handle(Request $request): Response
    {
        $verb = $request->input('verb');
        $base = $request->url();
        $now  = gmdate('Y-m-d\TH:i:s\Z');

        try {
            $content = match ($verb) {
                'Identify'             => $this->identify($base),
                'ListMetadataFormats'  => $this->listMetadataFormats($request),
                'ListSets'             => $this->listSets($request),
                'ListIdentifiers'      => $this->listRecords($request, headersOnly: true),
                'ListRecords'          => $this->listRecords($request, headersOnly: false),
                'GetRecord'            => $this->getRecord($request),
                null, ''               => $this->oaiError('badVerb', 'Missing "verb" argument.'),
                default                => $this->oaiError('badVerb', "Unknown verb: {$verb}"),
            };
        } catch (\InvalidArgumentException $e) {
            // oaiError() throws InvalidArgumentException with the error element
            $content = $e->getMessage();
        }

        $paramAttrs = $this->paramAttrs($request);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/" '
             . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
             . 'xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/ '
             . 'http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd">' . "\n"
             . "  <responseDate>{$now}</responseDate>\n"
             . "  <request{$paramAttrs}>" . htmlspecialchars($base, ENT_XML1) . "</request>\n"
             . $content
             . "</OAI-PMH>\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    // ----- Verbs ---------------------------------------------------

    private function identify(string $base): string
    {
        $earliest = DB::table('information_object as io')
            ->join('object as o', 'io.id', '=', 'o.id')
            ->min('o.created_at');
        $earliest = $earliest ? gmdate('Y-m-d\TH:i:s\Z', strtotime($earliest)) : '1970-01-01T00:00:00Z';
        $host = parse_url($base, PHP_URL_HOST) ?: 'openric';
        $admin = htmlspecialchars(env('OPENRIC_ADMIN_EMAIL', 'admin@' . $host), ENT_XML1);
        $name = htmlspecialchars(env('OPENRIC_REPOSITORY_NAME', 'OpenRiC Reference API'), ENT_XML1);

        return <<<XML
  <Identify>
    <repositoryName>{$name}</repositoryName>
    <baseURL>{$base}</baseURL>
    <protocolVersion>2.0</protocolVersion>
    <adminEmail>{$admin}</adminEmail>
    <earliestDatestamp>{$earliest}</earliestDatestamp>
    <deletedRecord>no</deletedRecord>
    <granularity>YYYY-MM-DDThh:mm:ssZ</granularity>
    <description>
      <oai-identifier xmlns="http://www.openarchives.org/OAI/2.0/oai-identifier"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai-identifier
          http://www.openarchives.org/OAI/2.0/oai-identifier.xsd">
        <scheme>oai</scheme>
        <repositoryIdentifier>{$host}</repositoryIdentifier>
        <delimiter>:</delimiter>
        <sampleIdentifier>oai:{$host}:1</sampleIdentifier>
      </oai-identifier>
    </description>
  </Identify>
XML;
    }

    private function listMetadataFormats(Request $request): string
    {
        // If identifier given, confirm it exists
        $ident = $request->input('identifier');
        if ($ident) {
            $id = $this->parseOaiIdentifier($ident);
            if (!$id || !DB::table('information_object')->where('id', $id)->exists()) {
                $this->oaiError('idDoesNotExist', "Unknown identifier: {$ident}");
            }
        }
        return <<<XML
  <ListMetadataFormats>
    <metadataFormat>
      <metadataPrefix>oai_dc</metadataPrefix>
      <schema>http://www.openarchives.org/OAI/2.0/oai_dc.xsd</schema>
      <metadataNamespace>http://www.openarchives.org/OAI/2.0/oai_dc/</metadataNamespace>
    </metadataFormat>
    <metadataFormat>
      <metadataPrefix>rico_ld</metadataPrefix>
      <schema>https://www.ica.org/standards/RiC/ontology</schema>
      <metadataNamespace>https://www.ica.org/standards/RiC/ontology#</metadataNamespace>
    </metadataFormat>
  </ListMetadataFormats>
XML;
    }

    private function listSets(Request $request): string
    {
        // Sets = top-level (fonds-level) information_objects.
        $fonds = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', 'en');
            })
            ->whereNull('io.parent_id')
            ->select('io.id', 'i18n.title')
            ->orderBy('io.id')
            ->limit(500)
            ->get();

        if ($fonds->isEmpty()) {
            $this->oaiError('noSetHierarchy', 'This repository does not expose sets.');
        }

        $items = '';
        foreach ($fonds as $f) {
            $spec = "fonds:{$f->id}";
            $name = htmlspecialchars($f->title ?? "Fonds {$f->id}", ENT_XML1);
            $items .= "    <set>\n      <setSpec>{$spec}</setSpec>\n      <setName>{$name}</setName>\n    </set>\n";
        }
        return "  <ListSets>\n{$items}  </ListSets>";
    }

    private function listRecords(Request $request, bool $headersOnly): string
    {
        $prefix = $request->input('metadataPrefix');
        if ($request->input('resumptionToken')) {
            [$prefix, $offset, $set, $from, $until] = $this->decodeToken($request->input('resumptionToken'));
        } else {
            if (!$prefix) $this->oaiError('badArgument', 'metadataPrefix is required.');
            if (!in_array($prefix, self::SUPPORTED_PREFIXES)) {
                $this->oaiError('cannotDisseminateFormat', "Unsupported metadataPrefix: {$prefix}");
            }
            $offset = 0;
            $set = $request->input('set');
            $from = $request->input('from');
            $until = $request->input('until');
        }

        $q = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', 'en');
            })
            ->join('object as o', 'io.id', '=', 'o.id')
            ->select('io.id', 'io.identifier', 'io.parent_id', 'i18n.title', 'i18n.scope_and_content',
                    'o.created_at', 'o.updated_at');

        if ($set) {
            // Only "fonds:N" supported — descendants of fonds N via parent chain
            if (preg_match('/^fonds:(\d+)$/', $set, $m)) {
                $fondsId = (int) $m[1];
                $fonds = DB::table('information_object')->where('id', $fondsId)->first(['lft', 'rgt']);
                if (!$fonds) $this->oaiError('badArgument', "Unknown set: {$set}");
                $q->whereBetween('io.lft', [$fonds->lft, $fonds->rgt]);
            } else {
                $this->oaiError('badArgument', "Unsupported set spec: {$set}");
            }
        }
        if ($from)  $q->where('o.updated_at', '>=', $this->parseOaiDate($from));
        if ($until) $q->where('o.updated_at', '<=', $this->parseOaiDate($until));

        $total = $q->count();
        $rows = $q->orderBy('io.id')->offset($offset)->limit(self::PAGE_SIZE)->get();

        if ($rows->isEmpty()) $this->oaiError('noRecordsMatch', 'No records match the query.');

        $host = parse_url($request->url(), PHP_URL_HOST) ?: 'openric';
        $items = '';
        foreach ($rows as $r) {
            $identifier = "oai:{$host}:{$r->id}";
            $datestamp = gmdate('Y-m-d\TH:i:s\Z', strtotime($r->updated_at ?? 'now'));
            $setSpec = $r->parent_id === null ? "      <setSpec>fonds:{$r->id}</setSpec>\n" : '';
            $header = "      <header>\n"
                    . "        <identifier>{$identifier}</identifier>\n"
                    . "        <datestamp>{$datestamp}</datestamp>\n"
                    . $setSpec
                    . "      </header>\n";

            if ($headersOnly) {
                $items .= "    <record>\n{$header}    </record>\n";
            } else {
                $meta = $this->renderMetadata($r, $prefix);
                $items .= "    <record>\n{$header}      <metadata>\n{$meta}\n      </metadata>\n    </record>\n";
            }
        }

        $wrapper = $headersOnly ? 'ListIdentifiers' : 'ListRecords';
        $resumption = '';
        $nextOffset = $offset + self::PAGE_SIZE;
        if ($nextOffset < $total) {
            $token = $this->encodeToken($prefix, $nextOffset, $set, $from, $until);
            $resumption = "    <resumptionToken completeListSize=\"{$total}\" cursor=\"{$offset}\">{$token}</resumptionToken>\n";
        } elseif ($offset > 0) {
            $resumption = "    <resumptionToken completeListSize=\"{$total}\" cursor=\"{$offset}\"/>\n";
        }

        return "  <{$wrapper}>\n{$items}{$resumption}  </{$wrapper}>";
    }

    private function getRecord(Request $request): string
    {
        $ident  = $request->input('identifier');
        $prefix = $request->input('metadataPrefix');
        if (!$ident)  $this->oaiError('badArgument', 'identifier is required.');
        if (!$prefix) $this->oaiError('badArgument', 'metadataPrefix is required.');
        if (!in_array($prefix, self::SUPPORTED_PREFIXES)) {
            $this->oaiError('cannotDisseminateFormat', "Unsupported metadataPrefix: {$prefix}");
        }

        $id = $this->parseOaiIdentifier($ident);
        if (!$id) $this->oaiError('idDoesNotExist', "Unknown identifier: {$ident}");

        $row = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', 'en');
            })
            ->join('object as o', 'io.id', '=', 'o.id')
            ->where('io.id', $id)
            ->select('io.id', 'io.identifier', 'io.parent_id', 'i18n.title', 'i18n.scope_and_content',
                    'o.created_at', 'o.updated_at')
            ->first();
        if (!$row) $this->oaiError('idDoesNotExist', "Record {$id} not found.");

        $host = parse_url($request->url(), PHP_URL_HOST) ?: 'openric';
        $identifier = "oai:{$host}:{$row->id}";
        $datestamp = gmdate('Y-m-d\TH:i:s\Z', strtotime($row->updated_at ?? 'now'));
        $meta = $this->renderMetadata($row, $prefix);
        $setSpec = $row->parent_id === null ? "      <setSpec>fonds:{$row->id}</setSpec>\n" : '';

        return "  <GetRecord>\n"
             . "    <record>\n"
             . "      <header>\n"
             . "        <identifier>{$identifier}</identifier>\n"
             . "        <datestamp>{$datestamp}</datestamp>\n"
             . $setSpec
             . "      </header>\n"
             . "      <metadata>\n{$meta}\n      </metadata>\n"
             . "    </record>\n"
             . "  </GetRecord>";
    }

    // ----- Metadata renderers --------------------------------------

    private function renderMetadata(object $row, string $prefix): string
    {
        if ($prefix === 'oai_dc') return $this->renderOaiDc($row);
        if ($prefix === 'rico_ld') return $this->renderRicoLd($row);
        return '';
    }

    private function renderOaiDc(object $row): string
    {
        $esc = fn($s) => htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $title = $esc($row->title ?? '');
        $ident = $esc($row->identifier ?? '');
        $desc  = $esc(strip_tags($row->scope_and_content ?? ''));
        $date  = $row->updated_at ? $esc(substr((string) $row->updated_at, 0, 10)) : '';

        return '        <oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"' . "\n"
             . '          xmlns:dc="http://purl.org/dc/elements/1.1/"' . "\n"
             . '          xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . "\n"
             . '          xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/' . "\n"
             . '          http://www.openarchives.org/OAI/2.0/oai_dc.xsd">' . "\n"
             . "          <dc:title>{$title}</dc:title>\n"
             . ($ident ? "          <dc:identifier>{$ident}</dc:identifier>\n" : '')
             . ($desc  ? "          <dc:description>{$desc}</dc:description>\n" : '')
             . ($date  ? "          <dc:date>{$date}</dc:date>\n" : '')
             . '          <dc:type>Archival description</dc:type>' . "\n"
             . '        </oai_dc:dc>';
    }

    private function renderRicoLd(object $row): string
    {
        try {
            $ric = $this->serializer->serializeRecord((int) $row->id);
            $json = json_encode($ric, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            // Wrap in CDATA to escape JSON cleanly
            return "        <rico:Record xmlns:rico=\"https://www.ica.org/standards/RiC/ontology#\">\n"
                 . "          <rico:jsonld><![CDATA[{$json}]]></rico:jsonld>\n"
                 . "        </rico:Record>";
        } catch (\Throwable $e) {
            return "        <!-- rico_ld serialization failed: " . htmlspecialchars($e->getMessage(), ENT_XML1) . " -->";
        }
    }

    // ----- Helpers -------------------------------------------------

    private function parseOaiIdentifier(string $ident): ?int
    {
        if (preg_match('/^oai:[^:]+:(\d+)$/', $ident, $m)) return (int) $m[1];
        return null;
    }

    private function parseOaiDate(string $d): string
    {
        // Accept YYYY-MM-DD or full timestamp; return MySQL-compatible
        $t = strtotime($d);
        return $t ? date('Y-m-d H:i:s', $t) : $d;
    }

    private function encodeToken(string $prefix, int $offset, ?string $set, ?string $from, ?string $until): string
    {
        return base64_encode(json_encode(compact('prefix', 'offset', 'set', 'from', 'until')));
    }

    private function decodeToken(string $token): array
    {
        $decoded = json_decode(base64_decode($token), true) ?: [];
        return [
            $decoded['prefix'] ?? 'oai_dc',
            (int) ($decoded['offset'] ?? 0),
            $decoded['set'] ?? null,
            $decoded['from'] ?? null,
            $decoded['until'] ?? null,
        ];
    }

    private function paramAttrs(Request $request): string
    {
        $out = '';
        foreach (['verb', 'identifier', 'metadataPrefix', 'set', 'from', 'until', 'resumptionToken'] as $k) {
            $v = $request->input($k);
            if ($v !== null && $v !== '') {
                $out .= ' ' . $k . '="' . htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES) . '"';
            }
        }
        return $out;
    }

    /** Throws to short-circuit into the catch block in handle(). */
    private function oaiError(string $code, string $message): never
    {
        $xml = "  <error code=\"{$code}\">" . htmlspecialchars($message, ENT_XML1) . "</error>\n";
        throw new \InvalidArgumentException($xml);
    }
}
