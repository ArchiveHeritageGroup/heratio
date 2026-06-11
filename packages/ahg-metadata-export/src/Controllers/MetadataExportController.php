<?php

/**
 * MetadataExportController - Controller for Heratio
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

namespace AhgMetadataExport\Controllers;

use AhgMetadataExport\Services\Exporters\CidocCrmSerializer;
use AhgMetadataExport\Services\Exporters\DacsSerializer;
use AhgMetadataExport\Services\Exporters\DublinCoreQualifiedSerializer;
use AhgMetadataExport\Services\Exporters\ModsSerializer;
use AhgMetadataExport\Services\Exporters\RadSerializer;
use AhgMetadataExport\Services\Importers\DacsXmlImporter;
use AhgMetadataExport\Services\Importers\RadXmlImporter;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class MetadataExportController extends Controller
{
    /**
     * Metadata export dashboard — list available formats.
     */
    public function index()
    {
        $formats = [
            'dc' => ['name' => 'Dublin Core (RDF/XML)', 'icon' => 'bi-file-earmark-code'],
            'dcterms' => ['name' => 'Dublin Core Qualified (dcterms)', 'icon' => 'bi-file-earmark-code'],
            'mods' => ['name' => 'MODS (XML)', 'icon' => 'bi-file-earmark-code'],
            'rad' => ['name' => 'RAD (Canadian) XML', 'icon' => 'bi-file-earmark-code'],
            'dacs' => ['name' => 'DACS (US) XML', 'icon' => 'bi-file-earmark-code'],
            'ead' => ['name' => 'EAD 2002 (XML)', 'icon' => 'bi-file-earmark-code'],
            'eac' => ['name' => 'EAC-CPF (XML)', 'icon' => 'bi-file-earmark-code'],
            'eac2' => ['name' => 'EAC-CPF 2.0 (XML)', 'icon' => 'bi-file-earmark-code'],
            'ead4' => ['name' => 'EAD 4 (XML)', 'icon' => 'bi-file-earmark-code'],
            'eac-f' => ['name' => 'EAC-F Functions (XML)', 'icon' => 'bi-file-earmark-code'],
            'eag' => ['name' => 'EAG 3.0 (XML)', 'icon' => 'bi-file-earmark-code'],
            'json-ld' => ['name' => 'JSON-LD', 'icon' => 'bi-braces'],
            'turtle' => ['name' => 'Turtle (TTL)', 'icon' => 'bi-file-earmark-text'],
            'ntriples' => ['name' => 'N-Triples', 'icon' => 'bi-file-earmark-text'],
            'cidoc-crm' => ['name' => 'CIDOC-CRM (Turtle / RDF/XML)', 'icon' => 'bi-diagram-3'],
        ];

        $repositories = DB::table('repository')
            ->join('actor_i18n', function ($join) {
                $join->on('repository.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', 'en');
            })
            ->select('repository.id', 'actor_i18n.authorized_form_of_name as name')
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->get();

        return view('ahg-metadata-export::index', compact('formats', 'repositories'));
    }

    /**
     * Preview a single record in the selected metadata format.
     */
    public function preview(Request $request)
    {
        $slug = $request->query('slug', '');
        $format = $request->query('format', 'dc');

        return view('ahg-metadata-export::preview', compact('slug', 'format'));
    }

    /**
     * Bulk export page.
     */
    public function bulk(Request $request)
    {
        $format = $request->query('format', 'dc');
        $formatInfo = ['name' => strtoupper($format)];

        $repositories = DB::table('repository')
            ->join('actor_i18n', function ($join) {
                $join->on('repository.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', 'en');
            })
            ->select('repository.id', 'actor_i18n.authorized_form_of_name as authorizedFormOfName', 'repository.id')
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->get();

        return view('ahg-metadata-export::bulk', compact('format', 'formatInfo', 'repositories'));
    }

    /**
     * Download a single information_object in DC qualified / MODS / RAD /
     * DACS XML. The four endpoints share this dispatcher so the view
     * layer only needs one named route + format param. #662 Phase 3.
     */
    public function downloadStandard(Request $request, string $format)
    {
        $ioId = (int) $request->query('io', 0);
        $culture = (string) $request->query('culture', app()->getLocale() ?: 'en');
        if ($ioId < 1) {
            abort(400, 'Missing io parameter');
        }

        $xml = match ($format) {
            'dcterms' => (new DublinCoreQualifiedSerializer())->serializeRecord($ioId, $culture),
            'mods' => '<?xml version="1.0" encoding="UTF-8"?>'."\n".(new ModsSerializer())->serializeRecord($ioId, $culture),
            'rad' => '<?xml version="1.0" encoding="UTF-8"?>'."\n".(new RadSerializer())->serializeRecord($ioId, $culture),
            'dacs' => '<?xml version="1.0" encoding="UTF-8"?>'."\n".(new DacsSerializer())->serializeRecord($ioId, $culture),
            default => null,
        };

        if ($xml === null || $xml === '') {
            abort(404, 'No record produced for format '.$format);
        }

        $filename = sprintf('heratio-%s-%d.xml', $format, $ioId);

        return new Response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Download a single information_object as a CIDOC-CRM (ISO 21127) RDF
     * document. Twin to downloadStandard but RDF rather than XML-envelope:
     * the graph is emitted as Turtle (default) or RDF/XML depending on the
     * ?rdf format hint (ttl | rdf | rdfxml), and the ahgmetadataexport.cidoc
     * route exposes a .ttl / .rdf path variant via the same handler.
     *
     * Part of issue #1197 (unified G/L/A/M knowledge graph). Read-only:
     * delegates to CidocCrmSerializer which never writes the database.
     */
    public function downloadCidocCrm(Request $request, ?string $ext = null)
    {
        $ioId = (int) $request->query('io', 0);
        $culture = (string) $request->query('culture', app()->getLocale() ?: 'en');
        if ($ioId < 1) {
            abort(400, 'Missing io parameter');
        }

        // Format precedence: path extension (.ttl/.rdf) > ?rdf= query > default ttl.
        $hint = strtolower((string) ($ext ?: $request->query('rdf', 'ttl')));
        $isRdfXml = in_array($hint, ['rdf', 'rdfxml', 'rdf+xml', 'xml'], true);

        $format = $isRdfXml ? CidocCrmSerializer::FORMAT_RDFXML : CidocCrmSerializer::FORMAT_TURTLE;

        // Operator endpoint (web+auth middleware) - emit even unpublished
        // records for staff review. publicOnly stays false here; the
        // serializer's published-records gate is reserved for any future
        // unauthenticated Linked Data surface.
        $body = (new CidocCrmSerializer())->serializeRecord($ioId, $culture, $format, false);

        if ($body === '') {
            abort(404, 'No record produced for CIDOC-CRM export');
        }

        if ($isRdfXml) {
            $contentType = 'application/rdf+xml; charset=UTF-8';
            $filename = sprintf('heratio-cidoc-crm-%d.rdf', $ioId);
        } else {
            $contentType = 'text/turtle; charset=UTF-8';
            $filename = sprintf('heratio-cidoc-crm-%d.ttl', $ioId);
        }

        return new Response($body, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Two-phase XML import for the per-standard sidecar tables. format =
     * "rad" or "dacs". Use ?dryRun=1 (default) for preview, dryRun=0 to
     * commit. Operators authenticate via the route's web+auth middleware.
     */
    public function importStandard(Request $request, string $format)
    {
        if (! in_array($format, ['rad', 'dacs'], true)) {
            abort(404);
        }

        $xml = '';
        if ($request->hasFile('xml_file')) {
            $xml = (string) file_get_contents($request->file('xml_file')->getRealPath());
        } elseif ($request->filled('xml')) {
            $xml = (string) $request->input('xml');
        }
        if ($xml === '') {
            return response()->json(['error' => 'No XML payload supplied'], 422);
        }

        $culture = (string) $request->input('culture', app()->getLocale() ?: 'en');
        $write = $request->input('dryRun') === '0' || $request->input('dryRun') === 0 || $request->boolean('commit');

        $importer = $format === 'rad' ? new RadXmlImporter() : new DacsXmlImporter();
        $results = $write ? $importer->commit($xml, $culture) : $importer->preview($xml, $culture);

        return response()->json([
            'format' => $format,
            'committed' => $write,
            'records' => $results,
        ]);
    }
}
