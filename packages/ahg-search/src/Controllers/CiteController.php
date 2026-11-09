<?php

/**
 * CiteController - "Cite these results" (heratio#1585): make a citable link for
 * a GLAM browse view or a catalogue-assistant answer, show it, and hand out the
 * citation in Chicago, APA, BibTeX or RIS.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgSearch\Controllers;

use AhgSearch\Services\BrowseCounter;
use AhgSearch\Services\ResultCitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CiteController extends Controller
{
    public function __construct(private ResultCitationService $cites)
    {
    }

    /** POST /glam/cite - cite the browse view described by `params`. */
    public function browse(Request $request)
    {
        $request->validate(['params' => 'nullable|array|max:40']);
        $cite = $this->cites->citeBrowse((array) $request->input('params', []));

        // The browse page's button is a plain form: go straight to the citation.
        return $request->wantsJson() ? $this->payload($cite) : redirect()->route('results.cite.show', $cite->code);
    }

    /** POST /glam/cite/chatbot/{message} - cite one of your own assistant answers. */
    public function chatbot(int $message): JsonResponse
    {
        $cite = $this->cites->citeChatbot($message);
        abort_if($cite === null, 404);

        return $this->payload($cite);
    }

    /** GET /glam/cite/{code} - the citation page; the results re-run under the reader's rights. */
    public function show(string $code)
    {
        $cite = $this->find($code);
        $params = (array) json_decode((string) $cite->params, true);
        ksort($params); // MySQL JSON reorders keys; keep the link stable
        $formats = array_combine(ResultCitationService::FORMATS, array_map(fn ($f) => $this->cites->format($cite, $f), ResultCitationService::FORMATS));

        return view('ahg-search::cite.show', [
            'cite' => $cite,
            'facts' => $this->cites->facts($cite),
            'formats' => $formats,
            'browseUrl' => $cite->kind === 'browse' ? route('glam.browse', $params) : null,
            'countNow' => $cite->kind === 'browse' ? app(BrowseCounter::class)->total($params) : null,
            'records' => $cite->kind === 'chatbot' ? $this->cites->visibleRecords($cite) : [],
            'currentVersion' => ResultCitationService::version(),
            'currentGeneration' => ResultCitationService::indexGeneration(),
        ]);
    }

    /** GET /glam/cite/{code}/{format} - one format as text, or a .bib / .ris file. */
    public function format(string $code, string $format)
    {
        abort_unless(in_array($format, ResultCitationService::FORMATS, true), 404);
        $cite = $this->find($code);
        $body = $this->cites->format($cite, $format);
        $headers = match ($format) {
            'bibtex' => ['Content-Type' => 'application/x-bibtex; charset=utf-8', 'Content-Disposition' => "attachment; filename=\"heratio-{$code}.bib\""],
            'ris' => ['Content-Type' => 'application/x-research-info-systems; charset=utf-8', 'Content-Disposition' => "attachment; filename=\"heratio-{$code}.ris\""],
            default => ['Content-Type' => 'text/plain; charset=utf-8'],
        };

        return response($body, 200, $headers);
    }

    private function find(string $code): object
    {
        abort_unless(preg_match('/^[a-z0-9]{8}$/', $code) === 1, 404);
        $cite = $this->cites->find($code);
        abort_if($cite === null, 404);

        return $cite;
    }

    private function payload(object $cite): JsonResponse
    {
        return response()->json([
            'code' => $cite->code,
            'url' => route('results.cite.show', $cite->code),
            'result_count' => (int) $cite->result_count,
            'formats' => array_combine(ResultCitationService::FORMATS, array_map(fn ($f) => $this->cites->format($cite, $f), ResultCitationService::FORMATS)),
        ]);
    }
}
