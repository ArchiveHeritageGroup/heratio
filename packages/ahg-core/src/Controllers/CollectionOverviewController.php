<?php

/**
 * CollectionOverviewController - Heratio ahg-core
 *
 * Public "Collection at a glance" page: a positive, visitor-facing snapshot of how
 * large and how rich the PUBLISHED collection is. It is the welcoming, outward
 * counterpart to the admin data-quality dashboard (which shows gaps): this page
 * celebrates what the collection holds.
 *
 * The controller is a thin wrapper around the read-only CollectionOverviewService.
 * It computes deep-link URLs into the public GLAM browse for each breakdown row,
 * but ONLY when the browse route exists (Route::has) - so the page degrades to
 * plain text labels rather than dead links when ahg-display is not installed.
 *
 * This surface is PUBLIC (no auth) and READ-ONLY. It makes no AI calls and no DB
 * writes.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Controllers;

use AhgCore\Services\CollectionOverviewService;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;

class CollectionOverviewController extends Controller
{
    public function __construct(private CollectionOverviewService $service)
    {
    }

    /**
     * Render the public overview. Every figure comes from the service (already
     * null-safe); here we only decorate the breakdown rows with a browse deep-link
     * where one can be generated, and pass a `browse_url` base for the digital and
     * entity cards. A total of zero (or a service-level error) renders the calm
     * "still being catalogued" empty-state in the view.
     */
    public function index()
    {
        $data = $this->service->overview();

        // Is the public GLAM browse available? If so we can deep-link breakdown
        // rows into it with the matching filter; if not, the view shows plain text.
        $hasBrowse = Route::has('glam.browse');
        $browseBase = null;
        if ($hasBrowse) {
            try {
                $browseBase = route('glam.browse');
            } catch (\Throwable $e) {
                $hasBrowse = false;
                $browseBase = null;
            }
        }

        // Decorate each breakdown with a deep-link where the matching browse filter
        // exists. The GLAM browse accepts: level=<level_of_description_id>,
        // repo=<repository_id>, hasDigital=1, startDate / endDate (YYYY) range.
        $byLevel = $this->linkRows(
            $data['by_level'],
            fn ($row) => $row['id'] !== null ? ['level' => $row['id']] : null,
            $hasBrowse,
            $browseBase
        );

        $byRepository = $this->linkRows(
            $data['by_repository'],
            fn ($row) => $row['id'] !== null ? ['repo' => $row['id']] : null,
            $hasBrowse,
            $browseBase
        );

        $byCentury = $this->linkRows(
            $data['by_century'],
            fn ($row) => ['startDate' => $row['from'], 'endDate' => $row['to']],
            $hasBrowse,
            $browseBase
        );

        // Digital-coverage deep-links: "with a digital object" maps to hasDigital=1.
        // The IIIF / 3D sub-coverages have no dedicated browse filter, so they stay
        // as plain figures (honest: we do not invent a link we cannot fulfil).
        $digitalUrl = null;
        if ($hasBrowse && $browseBase !== null && ($data['digital']['any'] ?? 0) > 0) {
            $digitalUrl = $this->buildBrowseUrl($browseBase, ['hasDigital' => 1]);
        }

        return view('ahg-core::collection-overview.index', [
            'total'         => (int) ($data['total'] ?? 0),
            'byLevel'       => $byLevel,
            'byRepository'  => $byRepository,
            'byCentury'     => $byCentury,
            'digital'       => $data['digital'] ?? [],
            'digitalUrl'    => $digitalUrl,
            'entities'      => $data['entities'] ?? [],
            'generatedAt'   => $data['generated_at'] ?? null,
            'hasError'      => (bool) ($data['error'] ?? false),
            'hasBrowse'     => $hasBrowse,
        ]);
    }

    /**
     * Attach a `url` key to each breakdown row when (a) the browse route exists and
     * (b) the per-row params resolver returns a non-null filter set. Rows whose
     * resolver yields null (e.g. "Not specified" with no id) keep no url and render
     * as plain text. Pure decoration - never throws.
     *
     * @param  array<int,array>  $rows
     * @param  callable(array):?array  $paramsFor  row -> browse query params, or null
     * @return array<int,array>
     */
    private function linkRows(array $rows, callable $paramsFor, bool $hasBrowse, ?string $browseBase): array
    {
        foreach ($rows as &$row) {
            $row['url'] = null;
            if (! $hasBrowse || $browseBase === null) {
                continue;
            }
            $params = $paramsFor($row);
            if (! is_array($params) || empty($params)) {
                continue;
            }
            $row['url'] = $this->buildBrowseUrl($browseBase, $params);
        }
        unset($row);

        return $rows;
    }

    /** Append query params to the browse base URL, preserving any it already has. */
    private function buildBrowseUrl(string $base, array $params): string
    {
        $sep = str_contains($base, '?') ? '&' : '?';

        return $base.$sep.http_build_query($params);
    }
}
