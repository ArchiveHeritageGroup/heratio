<?php

/**
 * SruController - HTTP entry point for the SRU 2.0 server.
 *
 * Routes:
 *   GET /sru?operation=explain
 *   GET /sru?operation=searchRetrieve&query=...&startRecord=&maximumRecords=&recordSchema=
 *
 * Returns SRU 2.0 XML responses. Federated discovery is anonymous; no auth.
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0
 */

namespace AhgZ3950\Controllers;

use AhgZ3950\Services\SruService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class SruController extends Controller
{
    public function __construct(private SruService $sru)
    {
    }

    public function handle(Request $request): Response
    {
        $operation = (string) $request->query('operation', 'explain');
        $version = (string) $request->query('version', SruService::SRU_VERSION);

        return match ($operation) {
            'searchRetrieve' => $this->searchRetrieve($request, $version),
            'explain' => $this->explain($version),
            default => $this->diagnostic('unsupportedOperation', $operation, $version),
        };
    }

    private function searchRetrieve(Request $request, string $version): Response
    {
        $query = (string) $request->query('query', '');
        $startRecord = (int) $request->query('startRecord', 1);
        $maximumRecords = (int) $request->query('maximumRecords', 10);
        $recordSchema = (string) $request->query('recordSchema', 'marcxml');
        $recordPacking = (string) $request->query('recordPacking', 'xml');

        if ($query === '') {
            return $this->diagnostic('mandatoryParameterNotSupplied', 'query', $version);
        }

        $result = $this->sru->searchRetrieve($query, $startRecord, $maximumRecords, $recordSchema);

        if ($result['diagnostic'] !== null) {
            return $this->diagnostic('queryFeatureUnsupported', $result['diagnostic'], $version);
        }

        $body = '<?xml version="1.0" encoding="UTF-8"?>';
        $body .= '<searchRetrieveResponse xmlns="http://docs.oasis-open.org/ns/search-ws/sruResponse">';
        $body .= '<version>' . htmlspecialchars($version, ENT_XML1) . '</version>';
        $body .= '<numberOfRecords>' . (int) $result['count'] . '</numberOfRecords>';
        if (!empty($result['records'])) {
            $body .= '<records>';
            $position = $startRecord;
            foreach ($result['records'] as $marc) {
                $packed = $recordPacking === 'string'
                    ? '<recordData>' . htmlspecialchars($marc, ENT_XML1) . '</recordData>'
                    : '<recordData>' . $marc . '</recordData>';

                $body .= '<record>'
                    . '<recordSchema>' . htmlspecialchars($recordSchema, ENT_XML1) . '</recordSchema>'
                    . '<recordPacking>' . htmlspecialchars($recordPacking, ENT_XML1) . '</recordPacking>'
                    . $packed
                    . '<recordPosition>' . $position . '</recordPosition>'
                    . '</record>';
                $position++;
            }
            $body .= '</records>';
        }
        $body .= '<echoedSearchRetrieveRequest>';
        $body .= '<version>' . htmlspecialchars($version, ENT_XML1) . '</version>';
        $body .= '<query>' . htmlspecialchars($query, ENT_XML1) . '</query>';
        $body .= '<startRecord>' . $startRecord . '</startRecord>';
        $body .= '<maximumRecords>' . $maximumRecords . '</maximumRecords>';
        $body .= '</echoedSearchRetrieveRequest>';
        $body .= '</searchRetrieveResponse>';

        return response($body, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function explain(string $version): Response
    {
        $body = '<?xml version="1.0" encoding="UTF-8"?>';
        $body .= '<explainResponse xmlns="http://docs.oasis-open.org/ns/search-ws/sruResponse">';
        $body .= '<version>' . htmlspecialchars($version, ENT_XML1) . '</version>';
        $body .= '<record>';
        $body .= '<recordSchema>http://explain.z3950.org/dtd/2.0/</recordSchema>';
        $body .= '<recordPacking>xml</recordPacking>';
        $body .= '<recordData>' . $this->sru->explain() . '</recordData>';
        $body .= '</record>';
        $body .= '</explainResponse>';
        return response($body, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function diagnostic(string $code, string $details, string $version): Response
    {
        $body = '<?xml version="1.0" encoding="UTF-8"?>';
        $body .= '<searchRetrieveResponse xmlns="http://docs.oasis-open.org/ns/search-ws/sruResponse">';
        $body .= '<version>' . htmlspecialchars($version, ENT_XML1) . '</version>';
        $body .= '<numberOfRecords>0</numberOfRecords>';
        $body .= '<diagnostics><diagnostic xmlns="http://docs.oasis-open.org/ns/search-ws/diagnostic">';
        $body .= '<uri>info:srw/diagnostic/1/' . htmlspecialchars($code, ENT_XML1) . '</uri>';
        $body .= '<details>' . htmlspecialchars($details, ENT_XML1) . '</details>';
        $body .= '</diagnostic></diagnostics>';
        $body .= '</searchRetrieveResponse>';
        return response($body, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
