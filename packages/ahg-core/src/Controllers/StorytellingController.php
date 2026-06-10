<?php

/**
 * StorytellingController - Heratio ahg-core
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Controllers;

use AhgCore\Services\StorySourceService;
use AhgCore\Services\StorytellingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * heratio#1202 - storytelling engine. Generate an engaging public narrative ("story of the
 * collection") from catalogue objects on a theme - optionally grounded in extra curator
 * sources (notes, a fetched URL, an uploaded document, hand-picked records) - for review,
 * publication and sharing, with source attribution on the public page.
 */
class StorytellingController extends Controller
{
    public function __construct(private StorytellingService $service, private StorySourceService $sources) {}

    public function index()
    {
        return view('ahg-core::stories', ['saved' => $this->service->listSaved()]);
    }

    public function generateAjax(Request $request)
    {
        $data = $request->validate([
            'theme' => 'nullable|string|max:200',
            'notes' => 'nullable|string|max:8000',
            'urls' => 'nullable|array|max:5',
            'urls.*' => 'url|max:500',
            'record_ids' => 'nullable|array|max:20',
            'record_ids.*' => 'integer|min:1',
            'documents' => 'nullable|array|max:5',
            'documents.*' => 'file|max:8192|mimes:pdf,txt,text,png,jpg,jpeg',
        ]);

        $theme = trim((string) ($data['theme'] ?? ''));
        $recordIds = array_values(array_unique(array_map('intval', (array) ($request->input('record_ids', [])))));
        $pieces = [];
        $attribution = [];   // [{type,label,url?}] handed back so the client returns it on save
        $warnings = [];      // per-source soft failures - shown but never abort a multi-source run

        $notes = trim((string) ($data['notes'] ?? ''));
        if ($notes !== '') {
            $pieces[] = $notes;
            $attribution[] = ['type' => 'note', 'label' => 'Curator background note'];
        }

        foreach (array_unique(array_filter((array) ($data['urls'] ?? []))) as $url) {
            $fetched = $this->sources->fetchUrlText($url);
            if ($fetched['ok']) {
                $pieces[] = $fetched['text'];
                $attribution[] = ['type' => 'url', 'label' => $fetched['title'] !== '' ? $fetched['title'] : $url, 'url' => $url];
            } else {
                $warnings[] = ($fetched['error'] ?: 'Could not fetch').': '.$url;
            }
        }

        foreach ((array) $request->file('documents', []) as $file) {
            $up = $this->sources->extractUploadText($file);
            if ($up['ok']) {
                $pieces[] = $up['text'];
                $attribution[] = ['type' => 'upload', 'label' => $file->getClientOriginalName()];
            } else {
                $warnings[] = ($up['error'] ?: 'Could not read').': '.$file->getClientOriginalName();
            }
        }

        $result = $this->service->generate($theme, 10, [
            'context' => $this->sources->assembleContext($pieces),
            'recordIds' => $recordIds,
        ]);

        // Add hand-picked records to the attribution list (by their resolved titles).
        foreach ($result['objects'] as $o) {
            if (in_array($o['id'], $recordIds, true)) {
                $attribution[] = ['type' => 'record', 'label' => $o['title']];
            }
        }
        $result['sources'] = $attribution;
        $result['source_warnings'] = $warnings;

        return response()->json($result);
    }

    /** "On this day" - a story from records dated today, falling back to this month. */
    public function onThisDayAjax()
    {
        $result = $this->service->onThisDay();
        $result['sources'] = [];   // date-derived; no external attribution
        $result['source_warnings'] = [];

        return response()->json($result);
    }

    /** Typeahead for hand-picking catalogue records to weave into a story. */
    public function searchAjax(Request $request)
    {
        $q = (string) $request->query('q', '');

        return response()->json($this->service->searchRecords($q));
    }

    /** Save (draft) or publish a reviewed story; returns its shareable URL. */
    public function saveAjax(Request $request)
    {
        $data = $request->validate([
            'id' => 'nullable|integer|min:1',
            'title' => 'required|string|max:200',
            'theme' => 'nullable|string|max:200',
            'body' => 'required|string|max:20000',
            'status' => 'nullable|in:draft,published',
            'objects' => 'nullable|array',
            'objects.*.id' => 'nullable|integer|min:1',
            'sources' => 'nullable|array|max:40',
            'sources.*.type' => 'nullable|in:note,url,upload,record',
            'sources.*.label' => 'nullable|string|max:200',
            'sources.*.url' => 'nullable|string|max:500',
        ]);

        $result = $this->service->save($data);
        if (! empty($result['ok']) && ! empty($result['slug'])) {
            $result['url'] = route('stories.show', ['slug' => $result['slug']]);
        }

        return response()->json($result);
    }

    /** Public story page. Drafts are visible only to authenticated staff. */
    public function show(string $slug)
    {
        $story = $this->service->getBySlug($slug);
        if (! $story || ($story->status !== 'published' && ! Auth::check())) {
            abort(404);
        }

        return view('ahg-core::story-show', [
            'story' => $story,
            'objects' => $this->service->storyObjects($story),
            'sources' => $this->service->storySources($story),
        ]);
    }
}
