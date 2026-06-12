<?php

/**
 * UnionHarvestController - the PUBLIC harvest API over the union catalogue
 * (#1203 slice). Lets partner / aggregator systems pull the shared discovery
 * records out of the federated GLAM network.
 *
 *   GET /union-catalogue/harvest        paginated JSON harvest (CORS-open)
 *   GET /union-catalogue/harvest.xml    OAI-DC-style ListRecords XML of the page
 *
 * Mounted under /union-catalogue/ rather than /federation/harvest because that
 * path is already taken by the F3 admin harvest-client page (auth+admin gated,
 * named federation.harvest, in the locked FederationController). /union-
 * catalogue/* is the public read home and matches the sibling /union-catalogue
 * search route.
 *
 * Both surfaces page over federation_union_record (records contributed by
 * ENABLED members only), shaping each row as Dublin-Core-ish fields:
 *   identifier (record_ref), title, type (level), date (dates),
 *   source (member + repository), url (source permalink). Pagination metadata
 *   travels alongside (page, per_page, total, last_page, next). Two optional
 *   filters are honoured:
 *
 *     ?member=<id>   restrict to one contributing institution
 *     ?from=<stamp>  incremental harvest from an indexed_at lower bound
 *     ?page / ?per_page  paging (per_page bounded, default 100, hard cap 500)
 *
 * Both routes are two-segment (/federation/harvest, dotted .xml), so the locked
 * single-segment /{slug} catch-all in ahg-information-object-manage does not
 * intercept them. Anonymous-readable on purpose - this is a public harvest
 * surface. Never 500s: the service is Schema::hasTable-guarded and the empty
 * state is a valid empty harvest.
 *
 * Fresh code under #1203 - read-only over the union tables, completely separate
 * from the locked F3 SharePoint FederationController / FederatedSearchService /
 * Connectors. The next-page url is built with url() so it never hardcodes a
 * host.
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

namespace AhgFederation\Controllers;

use AhgFederation\Services\UnionHarvestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class UnionHarvestController extends Controller
{
    public function __construct(private UnionHarvestService $service)
    {
    }

    /**
     * JSON harvest. CORS-open paginated list of union records as DC-ish fields
     * plus pagination metadata (page, per_page, total, last_page, next).
     */
    public function json(Request $request): JsonResponse
    {
        $params = $this->params($request);
        $result = $this->service->harvest(
            $params['page'],
            $params['per_page'],
            $params['member'],
            $params['from']
        );

        $payload = [
            'harvest' => 'glam-federation',
            'format' => 'dc-json',
            'total' => $result['total'],
            'count' => $result['count'],
            'page' => $result['page'],
            'per_page' => $result['per_page'],
            'last_page' => $result['last_page'],
            'member' => $result['member'],
            'from' => $result['from'],
            'next' => $this->nextUrl($request, $result),
            'records' => $result['records'],
        ];

        return response()
            ->json($payload)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type');
    }

    /**
     * OAI-DC-style ListRecords XML for the same page. A resumptionToken (the
     * next page number) is emitted while more pages remain, mirroring OAI-PMH
     * incremental harvesting. Well-formed and namespaced; all values escaped.
     */
    public function xml(Request $request): Response
    {
        $params = $this->params($request);
        $result = $this->service->harvest(
            $params['page'],
            $params['per_page'],
            $params['member'],
            $params['from']
        );

        $xml = $this->renderXml($request, $result);

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=utf-8')
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type');
    }

    /** Parse + sanitise the request query into harvest parameters. */
    protected function params(Request $request): array
    {
        $member = $request->query('member');
        $perPage = $request->query('per_page');

        return [
            'page' => max(1, (int) $request->query('page', 1)),
            'per_page' => ($perPage === null || $perPage === '') ? null : (int) $perPage,
            'member' => ($member === null || $member === '') ? null : (int) $member,
            'from' => $this->stringOrNull($request->query('from')),
        ];
    }

    protected function stringOrNull($v): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = trim((string) $v);

        return $v === '' ? null : $v;
    }

    /**
     * Build the next-page url preserving the active filters, or null on the
     * last page. Uses url() so the host is never hardcoded.
     */
    protected function nextUrl(Request $request, array $result): ?string
    {
        if ($result['page'] >= $result['last_page']) {
            return null;
        }

        $query = [
            'page' => $result['page'] + 1,
            'per_page' => $result['per_page'],
        ];
        if ($result['member'] !== null) {
            $query['member'] = $result['member'];
        }
        if ($result['from'] !== null) {
            $query['from'] = $result['from'];
        }

        // Reuse the current path (so /federation/harvest and .xml each link to
        // their own next page) and keep the host out of the literal string.
        return url($request->path()).'?'.http_build_query($query);
    }

    /**
     * Render the OAI-DC ListRecords document. Hand-built with a controlled
     * structure and full escaping so output is always well-formed.
     */
    protected function renderXml(Request $request, array $result): string
    {
        $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $responseDate = gmdate('Y-m-d\TH:i:s\Z');
        $requestUrl = $e(url($request->path()));

        $lines = [];
        $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $lines[] = '<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"'
            .' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            .' xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/'
            .' http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd">';
        $lines[] = '  <responseDate>'.$responseDate.'</responseDate>';
        $lines[] = '  <request verb="ListRecords" metadataPrefix="oai_dc">'.$requestUrl.'</request>';

        if (empty($result['records'])) {
            // A valid empty harvest: an OAI noRecordsMatch error, never a fault.
            $lines[] = '  <error code="noRecordsMatch">No union records match the harvest request.</error>';
            $lines[] = '</OAI-PMH>';

            return implode("\n", $lines)."\n";
        }

        $lines[] = '  <ListRecords>';

        foreach ($result['records'] as $rec) {
            $identifier = $e($rec['identifier'] ?? '');
            $datestamp = $e($rec['datestamp'] ?? $responseDate);

            $lines[] = '    <record>';
            $lines[] = '      <header>';
            $lines[] = '        <identifier>'.$identifier.'</identifier>';
            $lines[] = '        <datestamp>'.$datestamp.'</datestamp>';
            $lines[] = '      </header>';
            $lines[] = '      <metadata>';
            $lines[] = '        <oai_dc:dc'
                .' xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"'
                .' xmlns:dc="http://purl.org/dc/elements/1.1/"'
                .' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
                .' xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/'
                .' http://www.openarchives.org/OAI/2.0/oai_dc.xsd">';

            if (! empty($rec['title'])) {
                $lines[] = '          <dc:title>'.$e($rec['title']).'</dc:title>';
            }
            if (! empty($rec['type'])) {
                $lines[] = '          <dc:type>'.$e($rec['type']).'</dc:type>';
            }
            if (! empty($rec['date'])) {
                $lines[] = '          <dc:date>'.$e($rec['date']).'</dc:date>';
            }
            if (! empty($rec['source'])) {
                $lines[] = '          <dc:source>'.$e($rec['source']).'</dc:source>';
            }
            if (! empty($rec['identifier'])) {
                $lines[] = '          <dc:identifier>'.$e($rec['identifier']).'</dc:identifier>';
            }
            if (! empty($rec['url'])) {
                $lines[] = '          <dc:identifier>'.$e($rec['url']).'</dc:identifier>';
            }

            $lines[] = '        </oai_dc:dc>';
            $lines[] = '      </metadata>';
            $lines[] = '    </record>';
        }

        // resumptionToken = the next page number while more pages remain.
        if ($result['page'] < $result['last_page']) {
            $cursor = ($result['page'] - 1) * $result['per_page'];
            $token = $e((string) ($result['page'] + 1));
            $lines[] = '    <resumptionToken completeListSize="'.(int) $result['total']
                .'" cursor="'.(int) $cursor.'">'.$token.'</resumptionToken>';
        }

        $lines[] = '  </ListRecords>';
        $lines[] = '</OAI-PMH>';

        return implode("\n", $lines)."\n";
    }
}
