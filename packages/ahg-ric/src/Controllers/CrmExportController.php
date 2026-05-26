<?php

/**
 * CrmExportController - Per-record CIDOC-CRM export endpoint.
 *
 * Routes:
 *   GET /admin/export/crm/{slug}       - by slug
 *   GET /admin/export/crm/id/{id}      - by numeric id (for tests +
 *                                       headless integrations)
 *
 * Content negotiation:
 *   - application/rdf+xml or no Accept header -> RDF/XML
 *   - text/turtle (or ?format=turtle)         -> Turtle
 *
 * The endpoint sits under /admin/* to stay outside the locked
 * /{slug} catch-all in ahg-information-object-manage. ACL enforcement
 * is left to the route definition (web middleware + admin group);
 * the controller itself is read-only.
 *
 * Phase 1 of issue #659 (CIDOC-CRM v7 - class/property completeness +
 * RiC <-> CRM bridge serialiser).
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

namespace AhgRic\Controllers;

use AhgRic\Crm\CrmSerializer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class CrmExportController extends Controller
{
    /**
     * Export the IO identified by `$slug` as a CIDOC-CRM document.
     */
    public function exportBySlug(Request $request, string $slug): Response
    {
        $objectId = DB::table('slug')->where('slug', $slug)->value('object_id');
        if (! $objectId) {
            return new Response('Record not found', 404, ['Content-Type' => 'text/plain']);
        }
        return $this->emit($request, (int) $objectId);
    }

    /**
     * Export the IO identified by numeric `$id`. Kept as a separate
     * action so the route file can dispatch without depending on
     * a slug-to-id middleware.
     */
    public function exportById(Request $request, int $id): Response
    {
        return $this->emit($request, $id);
    }

    /**
     * Shared dispatch: pick format from Accept / ?format=, run the
     * serializer, return the body with the right Content-Type.
     */
    protected function emit(Request $request, int $objectId): Response
    {
        $culture = (string) $request->query('culture', app()->getLocale() ?: 'en');
        $format = $this->resolveFormat($request);

        $serializer = new CrmSerializer();
        $body = $serializer->serializeRecord($objectId, $culture, $format);

        if ($body === '') {
            return new Response('Record not found in culture ' . $culture, 404, [
                'Content-Type' => 'text/plain',
            ]);
        }

        $contentType = $format === CrmSerializer::FORMAT_TURTLE
            ? 'text/turtle; charset=utf-8'
            : 'application/rdf+xml; charset=utf-8';

        $ext = $format === CrmSerializer::FORMAT_TURTLE ? 'ttl' : 'rdf';
        return new Response($body, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="cidoc-crm-' . $objectId . '.' . $ext . '"',
            'X-CRM-Version' => '7.1.3',
            'X-Bridge-Phase' => '659.phase-1',
        ]);
    }

    /**
     * Resolve the serialisation format from the request. Explicit
     * ?format= wins over Accept header; everything else falls through
     * to RDF/XML.
     */
    protected function resolveFormat(Request $request): string
    {
        $q = strtolower((string) $request->query('format', ''));
        if ($q === 'turtle' || $q === 'ttl') {
            return CrmSerializer::FORMAT_TURTLE;
        }
        if ($q === 'rdfxml' || $q === 'rdf' || $q === 'xml') {
            return CrmSerializer::FORMAT_RDFXML;
        }

        $accept = strtolower((string) $request->header('Accept', ''));
        if (str_contains($accept, 'text/turtle') || str_contains($accept, 'application/x-turtle')) {
            return CrmSerializer::FORMAT_TURTLE;
        }
        return CrmSerializer::FORMAT_RDFXML;
    }
}
