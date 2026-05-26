<?php

/**
 * AnnotationContentTypeMiddleware - W3C Web Annotation Protocol header layer
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

namespace AhgAnnotations\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web Annotation Protocol (WAP) response decorator. Phase 1 of #648.
 *
 * Adds the conformance-required headers to every annotation response:
 *
 *   Content-Type: application/ld+json; profile="http://www.w3.org/ns/anno.jsonld"
 *   Link:         <http://www.w3.org/ns/ldp#Resource>; rel="type"
 *   Link:         <http://www.w3.org/TR/annotation-protocol/>; rel="http://www.w3.org/ns/ldp#constrainedBy"
 *   Accept-Post:  application/ld+json; profile="http://www.w3.org/ns/anno.jsonld" (on container endpoints)
 *   Allow:        per-route verb advertisement
 *   Vary:         Accept, Prefer
 *
 * Annotot-shaped clients (the existing HeratioAnnotationAdapter in
 * tools/mirador-build) parse JSON regardless of the Content-Type, so
 * upgrading the type does NOT break them. We also keep response bodies
 * byte-identical: only headers change.
 */
class AnnotationContentTypeMiddleware
{
    public const ANNOTATION_PROFILE = 'http://www.w3.org/ns/anno.jsonld';

    public const LDP_RESOURCE = 'http://www.w3.org/ns/ldp#Resource';

    public const LDP_BASIC_CONTAINER = 'http://www.w3.org/ns/ldp#BasicContainer';

    public const WAP_SPEC = 'http://www.w3.org/TR/annotation-protocol/';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only decorate JSON responses; bail on redirects/HTML/422 strings.
        if (! $response instanceof JsonResponse) {
            return $response;
        }

        $path = trim($request->path(), '/');
        $isContainer = str_ends_with($path, 'api/annotations/search')
            || $path === 'api/annotations'
            || str_ends_with($path, '/api/annotations');

        $contentType = 'application/ld+json; profile="'.self::ANNOTATION_PROFILE.'"';
        $response->headers->set('Content-Type', $contentType);

        // LDP type + WAP-constrainedBy on every annotation response.
        $linkHeader = '<'.self::LDP_RESOURCE.'>; rel="type"';
        if ($isContainer) {
            $linkHeader .= ', <'.self::LDP_BASIC_CONTAINER.'>; rel="type"';
        }
        $linkHeader .= ', <'.self::WAP_SPEC.'>; rel="http://www.w3.org/ns/ldp#constrainedBy"';
        $response->headers->set('Link', $linkHeader);

        // Container responses advertise Accept-Post so clients know they can
        // create new annotations into the container via POST.
        if ($isContainer) {
            $response->headers->set('Accept-Post', 'application/ld+json; profile="'.self::ANNOTATION_PROFILE.'"');
            // Containers vary their representation by Prefer (contained-iris
            // vs contained-descriptions). Cache layers must respect this.
            $response->headers->set('Vary', 'Accept, Prefer');
            // Advertise the verbs the container itself supports. Individual
            // annotation URLs advertise their own Allow line below.
            $response->headers->set('Allow', 'GET, POST, HEAD, OPTIONS');
        } else {
            $response->headers->set('Vary', 'Accept');
            $response->headers->set('Allow', 'GET, PUT, DELETE, HEAD, OPTIONS');
        }

        return $response;
    }
}
