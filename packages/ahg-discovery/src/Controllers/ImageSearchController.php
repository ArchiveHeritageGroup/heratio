<?php

/**
 * ImageSearchController - heratio#1272. Search by picture or by describing
 * one. The page is public like the rest of discovery; results are gated per
 * viewer inside ImageSearchStrategy.
 *
 *   GET  /discovery/image                       page
 *   GET  /discovery/image/search?q=...          text-to-image
 *   GET  /discovery/image/search?similar=<io>   images like this record's image
 *   POST /discovery/image/search  (image file)  image-to-image; the upload is not stored
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgDiscovery\Controllers;

use AhgCore\Services\DigitalObjectService;
use AhgDiscovery\Services\Search\ImageSearchStrategy;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImageSearchController extends Controller
{
    public function __construct(protected ImageSearchStrategy $images) {}

    public function index()
    {
        abort_unless($this->images->isEnabled(), 404);

        return view('ahg-discovery::image-search');
    }

    public function search(Request $request): JsonResponse
    {
        abort_unless($this->images->isEnabled(), 404);

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:500'],
            'similar' => ['nullable', 'integer', 'min:2'],
            'image' => ['nullable', 'file', 'image', 'max:10240'], // ponytail: fixed 10 MB query image
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $limit = (int) ($data['limit'] ?? 24);

        if ($request->hasFile('image')) {
            $mode = 'image';
            $file = $request->file('image');
            $hits = $this->images->searchByImageBytes((string) file_get_contents($file->getRealPath()), $limit);
        } elseif (! empty($data['similar'])) {
            $mode = 'similar';
            $hits = $this->images->searchSimilarToRecord((int) $data['similar'], $limit);
        } elseif (trim((string) ($data['q'] ?? '')) !== '') {
            $mode = 'text';
            $hits = $this->images->searchByText((string) $data['q'], $limit);
        } else {
            return response()->json(['success' => false, 'message' => __('Choose an image or describe what you are looking for.')], 422);
        }

        if ($hits === [] && $mode !== 'similar' && $this->images->embedderUnavailable()) {
            return response()->json(['success' => false, 'mode' => $mode,
                'message' => __('Image search is not available right now. Please try again later.')], 503);
        }

        return response()->json([
            'success' => true,
            'mode' => $mode,
            'total' => count($hits),
            'results' => array_map(fn ($h) => $h + [
                'url' => $h['slug'] ? url('/'.$h['slug']) : null,
                'thumbnail' => $this->thumbnail($h['object_id']),
            ], $hits),
        ]);
    }

    private function thumbnail(int $ioId): ?string
    {
        $objects = DigitalObjectService::getForObject($ioId);

        return $objects['master'] ? (DigitalObjectService::getThumbnailUrl($objects) ?: null) : null;
    }
}
