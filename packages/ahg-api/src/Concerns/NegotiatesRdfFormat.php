<?php

/**
 * NegotiatesRdfFormat - shared RDF content-negotiation for API entity controllers.
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

namespace AhgApi\Concerns;

use Illuminate\Http\Request;

/**
 * Resolve the requested RDF serialization from the ?format query param
 * (falling back to the Accept header). Was copy-pasted verbatim into
 * ActorEntityController, EntityController and TermEntityController.
 */
trait NegotiatesRdfFormat
{
    protected function negotiateFormat(Request $request): string
    {
        $param = strtolower((string) $request->query('format', ''));
        if (in_array($param, ['turtle', 'ttl'], true)) {
            return 'turtle';
        }
        if (in_array($param, ['rdf', 'rdfxml', 'rdf-xml', 'rdf/xml'], true)) {
            return 'rdfxml';
        }
        if (in_array($param, ['jsonld', 'json-ld', 'json'], true)) {
            return 'jsonld';
        }
        if (in_array($param, ['html', 'page'], true)) {
            return 'html';
        }

        $accept = strtolower((string) $request->header('Accept', ''));

        if (str_contains($accept, 'text/turtle') || str_contains($accept, 'application/x-turtle')) {
            return 'turtle';
        }
        if (str_contains($accept, 'application/rdf+xml')) {
            return 'rdfxml';
        }
        if (str_contains($accept, 'application/ld+json') || str_contains($accept, 'application/json')) {
            return 'jsonld';
        }

        // A browser sends "text/html,..." (often with */*). Honour an explicit
        // text/html preference by sending the human to the record page. An
        // Accept of only */* (curl's default) falls through to the JSON-LD
        // machine default below.
        if (str_contains($accept, 'text/html') || str_contains($accept, 'application/xhtml')) {
            return 'html';
        }

        return 'jsonld';
    }
}
