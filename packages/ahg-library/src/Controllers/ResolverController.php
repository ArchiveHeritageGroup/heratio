<?php

/**
 * ResolverController - OpenURL 1.0 link-resolver endpoint.
 *
 * Accepts OpenURL 1.0 KEV query parameters at GET /api/resolver, resolves the
 * citation against the local library catalogue, and either issues a 303
 * redirect to the single matched /library/{slug} record or returns an
 * OpenURL ContextObject XML document (application/xml) describing the
 * zero-or-many match outcome.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Controllers;

use AhgLibrary\Services\OpenUrlResolverService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ResolverController extends Controller
{
    public function __construct(private OpenUrlResolverService $resolver)
    {
    }

    /**
     * GET /api/resolver
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
     */
    public function resolve(Request $request)
    {
        $ctx = $this->resolver->parseContext($request->query());

        // Nothing usable supplied - return an empty ContextObject (HTTP 400).
        if (empty($ctx)) {
            $xml = $this->resolver->buildContextObjectXml($ctx, []);
            return new Response($xml, 400, [
                'Content-Type' => 'application/xml; charset=UTF-8',
            ]);
        }

        $outcome = $this->resolver->resolve($ctx);

        if ($outcome['status'] === 'matched' && ! empty($outcome['slug'])) {
            // 303 See Other - the canonical OpenURL "single target" response.
            return redirect(url('/library/' . $outcome['slug']), 303);
        }

        // No single match: emit the ContextObject XML with any candidates.
        $candidates = $outcome['items'] ?? [];
        $xml = $this->resolver->buildContextObjectXml($ctx, $candidates);

        $status = $outcome['status'] === 'multiple' ? 300 : 404;

        return new Response($xml, $status, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
