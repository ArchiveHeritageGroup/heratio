<?php

/**
 * BiblioBfController — BIBFRAME 2.0 export controller for Heratio.
 *
 * Provides a human landing page and a machine-readable Turtle/JSON-LD export
 * endpoint for bibliographic materials serialised via BibframeSerializer.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
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

namespace AhgBiblioBf\Controllers;

use AhgBiblioBf\Services\BibframeSerializer;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BiblioBfController extends Controller
{
    public function __construct(private readonly BibframeSerializer $serializer)
    {
    }

    /**
     * Human landing page — lists the format, vocabulary references, and sample
     * query lines operators can crib for testing.
     */
    public function index(Request $request): Response
    {
        $appUrl = config('app.url', rtrim($request->url(), '/'));

        return new Response(view('ahg-biblio-bf::index', compact('appUrl')));
    }

    /**
     * Preview endpoint — returns Turtle for the slug specified by ?slug=.
     * Used by the metadata-export dashboard to show a live preview before
     * committing to a download.
     */
    public function preview(Request $request): Response
    {
        $slug = $request->query('slug', '');
        if ($slug === '') {
            return new Response('slug parameter required', 422, ['Content-Type' => 'text/plain']);
        }

        $objectId = $this->resolveSlug($slug);
        if ($objectId === null) {
            return new Response("slug '{$slug}' not found", 404, ['Content-Type' => 'text/plain']);
        }

        $culture = $request->query('culture', app()->getLocale());
        $output = $this->serializer->serializeRecord($objectId, $culture);

        if ($output === '') {
            return new Response('Record not available in this culture', 404, ['Content-Type' => 'text/plain']);
        }

        return new Response($output, 200, [
            'Content-Type' => 'text/turtle; charset=utf-8',
        ]);
    }

    /**
     * Download endpoint — returns Turtle for ?slug= as a file attachment.
     */
    public function download(Request $request): StreamedResponse
    {
        $slug = $request->query('slug', '');
        $objectId = $this->resolveSlug($slug);
        if ($objectId === null) {
            abort(404, "slug '{$slug}' not found");
        }

        $culture = $request->query('culture', app()->getLocale());
        $ttl = $this->serializer->serializeRecord($objectId, $culture);

        return $this->streamTurtle("bibframe-{$slug}.ttl", $ttl, $slug);
    }

    /**
     * Streaming Turtle export for the record identified by $slug.
     * Writes directly to the output buffer so large record sets can be
     * streamed without holding the full document in memory.
     */
    private function streamTurtle(string $filename, string $ttl, string $slug): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($ttl) {
            echo $ttl;
        }, 200, [
            'Content-Type' => 'text/turtle; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);

        return $response;
    }

    /**
     * Resolve a slug to an information_object.id.
     * Returns null when the slug is unknown or when the slug table is absent
     * (fresh installs, CI environments without DB).
     */
    private function resolveSlug(string $slug): ?int
    {
        if ($slug === '' || ! Schema::hasTable('slug')) {
            return null;
        }

        $row = DB::table('slug')
            ->where('slug', $slug)
            ->whereNull('serialized_object_id')
            ->select('object_id')
            ->first();

        return $row ? (int) $row->object_id : null;
    }
}
