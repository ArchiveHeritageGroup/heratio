<?php

/**
 * OaiPmhService - OAI-PMH 2.0 XML serialisation for published archival records.
 *
 * Deepens north-star #1204 ("the world heritage graph / open memory protocol")
 * by making the published corpus harvestable through the long-established
 * OAI-PMH 2.0 protocol, so library/archive aggregators (and crawling agents)
 * can ingest the descriptions without bespoke integration. It complements the
 * Linked-Data graph endpoint (GraphController): the graph is for entity-level
 * crawling, OAI-PMH is for bulk metadata harvesting.
 *
 * This service is a PURE serialiser: it turns plain PHP arrays (assembled by
 * the controller from the database) into well-formed OAI-PMH 2.0 XML. It
 * performs NO database access and mutates nothing. The controller owns the
 * publication-status gate and all DB reads; this service owns only the XML.
 *
 * Metadata format: oai_dc (simple Dublin Core), the OAI-PMH mandatory format.
 *
 * Every text value is XML-escaped here, so a caller can pass raw titles /
 * descriptions without pre-escaping. Every public builder returns a complete,
 * well-formed XML document (UTF-8) with the OAI-PMH envelope, the echoed
 * <request> element and a <responseDate>. Error conditions are emitted as
 * proper OAI <error> elements (HTTP 200 per the spec is the caller's job).
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

namespace AhgApi\Services;

class OaiPmhService
{
    /** OAI-PMH protocol version this endpoint speaks. */
    public const PROTOCOL_VERSION = '2.0';

    /** UTC datestamp granularity advertised by Identify. */
    public const GRANULARITY = 'YYYY-MM-DDThh:mm:ssZ';

    /** OAI-PMH envelope namespace. */
    private const NS_OAI = 'http://www.openarchives.org/OAI/2.0/';

    private const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    private const SCHEMA_OAI = 'http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd';

    /** oai_dc metadata format namespaces. */
    private const NS_OAI_DC = 'http://www.openarchives.org/OAI/2.0/oai_dc/';

    private const NS_DC = 'http://purl.org/dc/elements/1.1/';

    private const SCHEMA_OAI_DC = 'http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd';

    // -----------------------------------------------------------------
    // Identify
    // -----------------------------------------------------------------

    /**
     * Build the Identify response.
     *
     * @param  array{
     *     repositoryName:string,
     *     baseUrl:string,
     *     adminEmail:string,
     *     earliestDatestamp:string,
     *     request:array<string,string>
     * }  $info
     */
    public function identify(array $info): string
    {
        $body = '  <Identify>'."\n";
        $body .= '    <repositoryName>'.$this->esc($info['repositoryName']).'</repositoryName>'."\n";
        $body .= '    <baseURL>'.$this->esc($info['baseUrl']).'</baseURL>'."\n";
        $body .= '    <protocolVersion>'.self::PROTOCOL_VERSION.'</protocolVersion>'."\n";
        $body .= '    <adminEmail>'.$this->esc($info['adminEmail']).'</adminEmail>'."\n";
        $body .= '    <earliestDatestamp>'.$this->esc($info['earliestDatestamp']).'</earliestDatestamp>'."\n";
        $body .= '    <deletedRecord>no</deletedRecord>'."\n";
        $body .= '    <granularity>'.self::GRANULARITY.'</granularity>'."\n";
        $body .= '  </Identify>'."\n";

        return $this->envelope($info['request'], $body);
    }

    // -----------------------------------------------------------------
    // ListMetadataFormats
    // -----------------------------------------------------------------

    /**
     * Build a ListMetadataFormats response advertising oai_dc.
     *
     * @param  array<string,string>  $request  echoed request attributes
     */
    public function listMetadataFormats(array $request): string
    {
        $body = '  <ListMetadataFormats>'."\n";
        $body .= '    <metadataFormat>'."\n";
        $body .= '      <metadataPrefix>oai_dc</metadataPrefix>'."\n";
        $body .= '      <schema>http://www.openarchives.org/OAI/2.0/oai_dc.xsd</schema>'."\n";
        $body .= '      <metadataNamespace>'.self::NS_OAI_DC.'</metadataNamespace>'."\n";
        $body .= '    </metadataFormat>'."\n";
        $body .= '  </ListMetadataFormats>'."\n";

        return $this->envelope($request, $body);
    }

    // -----------------------------------------------------------------
    // ListIdentifiers / ListRecords
    // -----------------------------------------------------------------

    /**
     * Build a ListIdentifiers response (headers only).
     *
     * @param  array<string,string>            $request          echoed request attributes
     * @param  array<int,array<string,mixed>>  $records          record arrays (see recordXml)
     * @param  string|null                     $resumptionToken  opaque token for next page, or null
     */
    public function listIdentifiers(array $request, array $records, ?string $resumptionToken = null): string
    {
        $body = '  <ListIdentifiers>'."\n";
        foreach ($records as $record) {
            $body .= $this->headerXml($record);
        }
        $body .= $this->resumptionTokenXml($resumptionToken);
        $body .= '  </ListIdentifiers>'."\n";

        return $this->envelope($request, $body);
    }

    /**
     * Build a ListRecords response (full oai_dc records).
     *
     * @param  array<string,string>            $request
     * @param  array<int,array<string,mixed>>  $records
     * @param  string|null                     $resumptionToken
     */
    public function listRecords(array $request, array $records, ?string $resumptionToken = null): string
    {
        $body = '  <ListRecords>'."\n";
        foreach ($records as $record) {
            $body .= $this->recordXml($record);
        }
        $body .= $this->resumptionTokenXml($resumptionToken);
        $body .= '  </ListRecords>'."\n";

        return $this->envelope($request, $body);
    }

    // -----------------------------------------------------------------
    // GetRecord
    // -----------------------------------------------------------------

    /**
     * Build a GetRecord response for a single record.
     *
     * @param  array<string,string>  $request
     * @param  array<string,mixed>   $record
     */
    public function getRecord(array $request, array $record): string
    {
        $body = '  <GetRecord>'."\n";
        $body .= $this->recordXml($record);
        $body .= '  </GetRecord>'."\n";

        return $this->envelope($request, $body);
    }

    // -----------------------------------------------------------------
    // Errors
    // -----------------------------------------------------------------

    /**
     * Build an OAI error response. The OAI-PMH spec mandates HTTP 200 even for
     * protocol errors; the caller sets the status code. When the error is a
     * badVerb / badArgument the echoed request carries NO verb/arguments (per
     * spec); the caller decides which request attributes to pass.
     *
     * @param  array<string,string>  $request  echoed request attributes
     * @param  string                $code     OAI error code (e.g. badVerb)
     * @param  string                $message  human-readable detail
     */
    public function error(array $request, string $code, string $message): string
    {
        $body = '  <error code="'.$this->esc($code).'">'.$this->esc($message).'</error>'."\n";

        return $this->envelope($request, $body);
    }

    // -----------------------------------------------------------------
    // Internal builders
    // -----------------------------------------------------------------

    /**
     * Wrap a body fragment in the OAI-PMH envelope with responseDate and the
     * echoed <request> element.
     *
     * @param  array<string,string>  $request
     */
    private function envelope(array $request, string $body): string
    {
        $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $out .= '<OAI-PMH xmlns="'.self::NS_OAI.'"'."\n";
        $out .= '         xmlns:xsi="'.self::NS_XSI.'"'."\n";
        $out .= '         xsi:schemaLocation="'.self::SCHEMA_OAI.'">'."\n";
        $out .= '  <responseDate>'.$this->esc($this->utcNow()).'</responseDate>'."\n";
        $out .= $this->requestXml($request);
        $out .= $body;
        $out .= '</OAI-PMH>'."\n";

        return $out;
    }

    /**
     * The echoed <request> element. The base URL is the element text; the verb
     * and other arguments become attributes. Per the OAI spec, when a request
     * is in error because of a bad verb or bad argument, the attributes are
     * omitted - the caller passes only what should be echoed.
     *
     * @param  array<string,string>  $request
     */
    private function requestXml(array $request): string
    {
        $base = (string) ($request['baseUrl'] ?? '');
        $attrs = '';
        foreach ($request as $key => $value) {
            if ($key === 'baseUrl' || $value === null || $value === '') {
                continue;
            }
            $attrs .= ' '.$this->esc((string) $key).'="'.$this->esc((string) $value).'"';
        }

        if ($base === '') {
            return '  <request'.$attrs.'/>'."\n";
        }

        return '  <request'.$attrs.'>'.$this->esc($base).'</request>'."\n";
    }

    /**
     * One <header> element. A record marked deleted would carry
     * status="deleted" (we never set it - deletedRecord=no - but the builder
     * stays honest).
     *
     * @param  array<string,mixed>  $record
     */
    private function headerXml(array $record): string
    {
        $out = '    <header>'."\n";
        $out .= '      <identifier>'.$this->esc((string) ($record['identifier'] ?? '')).'</identifier>'."\n";
        $out .= '      <datestamp>'.$this->esc((string) ($record['datestamp'] ?? '')).'</datestamp>'."\n";
        foreach ((array) ($record['sets'] ?? []) as $set) {
            if ($set === null || $set === '') {
                continue;
            }
            $out .= '      <setSpec>'.$this->esc((string) $set).'</setSpec>'."\n";
        }
        $out .= '    </header>'."\n";

        return $out;
    }

    /**
     * One full <record>: header + oai_dc metadata block.
     *
     * @param  array<string,mixed>  $record
     */
    private function recordXml(array $record): string
    {
        $out = '    <record>'."\n";
        // headerXml indents at list depth; a record's header sits one level
        // deeper, so re-indent its lines. XML is whitespace-insensitive here,
        // but tidy output aids debugging.
        $out .= $this->reindentHeader($this->headerXml($record));
        $out .= '      <metadata>'."\n";
        $out .= $this->oaiDcXml($record);
        $out .= '      </metadata>'."\n";
        $out .= '    </record>'."\n";

        return $out;
    }

    /**
     * The oai_dc Dublin Core metadata block for a record. Only non-empty fields
     * are emitted. Multi-valued DC fields (dc:subject, dc:type, dc:date) accept
     * arrays.
     *
     * @param  array<string,mixed>  $record
     */
    private function oaiDcXml(array $record): string
    {
        $out = '        <oai_dc:dc xmlns:oai_dc="'.self::NS_OAI_DC.'"'."\n";
        $out .= '                   xmlns:dc="'.self::NS_DC.'"'."\n";
        $out .= '                   xmlns:xsi="'.self::NS_XSI.'"'."\n";
        $out .= '                   xsi:schemaLocation="'.self::SCHEMA_OAI_DC.'">'."\n";

        $out .= $this->dcElements('title', $record['title'] ?? null);
        $out .= $this->dcElements('creator', $record['creators'] ?? null);
        $out .= $this->dcElements('subject', $record['subjects'] ?? null);
        $out .= $this->dcElements('description', $record['description'] ?? null);
        $out .= $this->dcElements('publisher', $record['publisher'] ?? null);
        $out .= $this->dcElements('date', $record['dates'] ?? null);
        $out .= $this->dcElements('type', $record['types'] ?? null);
        $out .= $this->dcElements('identifier', $record['identifiers'] ?? null);
        $out .= $this->dcElements('language', $record['languages'] ?? null);
        $out .= $this->dcElements('rights', $record['rights'] ?? null);

        $out .= '        </oai_dc:dc>'."\n";

        return $out;
    }

    /**
     * Render zero or more <dc:{name}> elements from a scalar or array value.
     * Null / empty values are skipped, so absent DC fields simply do not appear.
     *
     * @param  mixed  $value  scalar, array of scalars, or null
     */
    private function dcElements(string $name, $value): string
    {
        if ($value === null) {
            return '';
        }

        $values = is_array($value) ? $value : [$value];
        $out = '';
        foreach ($values as $v) {
            $text = trim((string) $v);
            if ($text === '') {
                continue;
            }
            $out .= '          <dc:'.$name.'>'.$this->esc($text).'</dc:'.$name.'>'."\n";
        }

        return $out;
    }

    /**
     * Emit a <resumptionToken> when one continues the sequence. The last page
     * yields no token (an empty/closing token is optional in the spec), so we
     * only emit the element for a non-null continuation token.
     */
    private function resumptionTokenXml(?string $token): string
    {
        if ($token === null || $token === '') {
            return '';
        }

        return '    <resumptionToken>'.$this->esc($token).'</resumptionToken>'."\n";
    }

    /**
     * Re-indent the header fragment (built at list depth) to record depth.
     */
    private function reindentHeader(string $headerFragment): string
    {
        $lines = explode("\n", rtrim($headerFragment, "\n"));
        $out = '';
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $out .= '  '.$line."\n";
        }

        return $out;
    }

    /**
     * Current UTC time as an OAI granularity-compliant datestamp.
     */
    private function utcNow(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }

    /**
     * XML-escape text content. ENT_XML1 keeps the output valid XML 1.0 and
     * escapes the five predefined entities; UTF-8 is assumed throughout.
     */
    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
