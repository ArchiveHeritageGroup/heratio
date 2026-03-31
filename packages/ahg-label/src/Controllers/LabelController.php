<?php

/**
 * LabelController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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



namespace AhgLabel\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LabelController extends Controller
{
    /**
     * Show label printing page for a single entity identified by slug.
     */
    public function index(string $slug)
    {
        $culture = app()->getLocale() ?? 'en';

        // Resolve slug to object
        $slugRow = DB::table('slug')->where('slug', $slug)->first();
        if (!$slugRow) {
            abort(404);
        }

        $objectId = $slugRow->object_id;

        // Determine entity class
        $className = DB::table('object')->where('id', $objectId)->value('class_name');

        // Load entity data based on class
        $entity = null;
        $entityType = 'information_object';

        if ($className === 'QubitInformationObject') {
            $entity = DB::table('information_object')
                ->where('id', $objectId)
                ->first();
            $entityType = 'information_object';
        } elseif (in_array($className, ['QubitActor', 'QubitRepository'])) {
            $entity = DB::table('actor')
                ->where('id', $objectId)
                ->first();
            $entityType = 'actor';
        } elseif ($className === 'QubitAccession') {
            $entity = DB::table('accession')
                ->where('id', $objectId)
                ->first();
            $entityType = 'accession';
        } else {
            // Fallback: try information_object
            $entity = DB::table('information_object')
                ->where('id', $objectId)
                ->first();
        }

        if (!$entity) {
            abort(404);
        }

        // Get title from i18n table
        $title = '';
        $identifier = '';

        if ($entityType === 'information_object') {
            $i18n = DB::table('information_object_i18n')
                ->where('id', $objectId)
                ->where('culture', $culture)
                ->first();
            $title = $i18n->title ?? '';
            $identifier = $entity->identifier ?? '';
        } elseif ($entityType === 'actor') {
            $i18n = DB::table('actor_i18n')
                ->where('id', $objectId)
                ->where('culture', $culture)
                ->first();
            $title = $i18n->authorized_form_of_name ?? '';
            $identifier = $entity->description_identifier ?? '';
        } elseif ($entityType === 'accession') {
            $title = $entity->identifier ?? '';
            $identifier = $entity->identifier ?? '';
        }

        // Decode HTML entities in title
        $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');

        // Detect sector from display_object_config
        $sector = 'archive';
        $sectorConfig = DB::table('display_object_config')
            ->where('object_id', $objectId)
            ->value('object_type');
        if ($sectorConfig) {
            $sector = $sectorConfig;
        }

        // Build barcode sources
        $barcodeSources = [];

        // 1. Identifier (always available)
        if (!empty($identifier)) {
            $barcodeSources['identifier'] = [
                'label' => 'Identifier',
                'value' => $identifier,
            ];
        }

        // Library item fields (only for information objects)
        if ($entityType === 'information_object') {
            $libraryItem = DB::table('library_item')
                ->where('information_object_id', $objectId)
                ->first();

            if ($libraryItem) {
                if (!empty($libraryItem->isbn)) {
                    $barcodeSources['isbn'] = [
                        'label' => 'ISBN',
                        'value' => $libraryItem->isbn,
                    ];
                    $sector = 'library';
                }

                if (!empty($libraryItem->issn)) {
                    $barcodeSources['issn'] = [
                        'label' => 'ISSN',
                        'value' => $libraryItem->issn,
                    ];
                }

                if (!empty($libraryItem->lccn)) {
                    $barcodeSources['lccn'] = [
                        'label' => 'LCCN',
                        'value' => $libraryItem->lccn,
                    ];
                }

                if (!empty($libraryItem->openlibrary_id)) {
                    $barcodeSources['openlibrary'] = [
                        'label' => 'OpenLibrary ID',
                        'value' => $libraryItem->openlibrary_id,
                    ];
                }

                if (!empty($libraryItem->barcode)) {
                    $barcodeSources['barcode'] = [
                        'label' => 'Barcode',
                        'value' => $libraryItem->barcode,
                    ];
                }

                if (!empty($libraryItem->call_number)) {
                    $barcodeSources['call_number'] = [
                        'label' => 'Call Number',
                        'value' => $libraryItem->call_number,
                    ];
                }
            }

            // Museum metadata (accession_number equivalent from museum_metadata is not present,
            // but we check object_type from display_object_config for sector detection)
        }

        // Accession number for accession entities
        if ($entityType === 'accession' && !empty($entity->identifier)) {
            $barcodeSources['accession'] = [
                'label' => 'Accession Number',
                'value' => $entity->identifier,
            ];
        }

        // Title as last option
        if (!empty($title)) {
            $barcodeSources['title'] = [
                'label' => 'Title',
                'value' => $title,
            ];
        }

        // Default barcode data: prefer isbn > issn > barcode > accession > identifier > title
        $defaultBarcodeData = '';
        $preferredOrder = ['isbn', 'issn', 'barcode', 'accession', 'identifier', 'title'];
        foreach ($preferredOrder as $key) {
            if (!empty($barcodeSources[$key]['value'])) {
                $defaultBarcodeData = $barcodeSources[$key]['value'];
                break;
            }
        }

        // Repository name
        $repositoryName = '';
        if ($entityType === 'information_object') {
            $repoId = $entity->repository_id ?? null;
            // Walk up tree if no direct repository
            if (!$repoId && isset($entity->parent_id) && $entity->parent_id > 1) {
                $ancestorId = $entity->parent_id;
                $maxDepth = 50;
                while ($ancestorId && $ancestorId > 1 && $maxDepth-- > 0) {
                    $ancestor = DB::table('information_object')
                        ->where('id', $ancestorId)
                        ->select('repository_id', 'parent_id')
                        ->first();
                    if (!$ancestor) {
                        break;
                    }
                    if ($ancestor->repository_id) {
                        $repoId = $ancestor->repository_id;
                        break;
                    }
                    $ancestorId = $ancestor->parent_id;
                }
            }
            if ($repoId) {
                $repositoryName = DB::table('actor_i18n')
                    ->where('id', $repoId)
                    ->where('culture', $culture)
                    ->value('authorized_form_of_name') ?? '';
            }
        }

        // QR URL
        $qrUrl = url('/' . $slug);

        // Sector labels
        $sectorLabels = [
            'library' => 'Library Item',
            'archive' => 'Archival Record',
            'museum'  => 'Museum Object',
            'gallery' => 'Gallery Artwork',
        ];
        $sectorLabel = $sectorLabels[$sector] ?? 'Record';

        return view('label::index', compact(
            'slug',
            'title',
            'identifier',
            'entityType',
            'objectId',
            'barcodeSources',
            'defaultBarcodeData',
            'repositoryName',
            'qrUrl',
            'sector',
            'sectorLabel'
        ));
    }

    /**
     * Generate a label with selected options (POST).
     */
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'slug'           => 'required|string|max:255',
            'barcode_source' => 'required|string|max:500',
            'label_size'     => 'required|in:200,300,400',
            'show_barcode'   => 'sometimes|boolean',
            'show_qr'        => 'sometimes|boolean',
            'show_title'     => 'sometimes|boolean',
            'show_repo'      => 'sometimes|boolean',
        ]);

        // Redirect back to label page with selected options as query params
        return redirect()->route('ahglabel.index', ['slug' => $validated['slug']])
            ->with('label_options', $validated);
    }

    /**
     * Batch print labels for multiple objects (POST).
     */
    public function batchPrint(Request $request)
    {
        $validated = $request->validate([
            'slugs'          => 'required|array|min:1|max:100',
            'slugs.*'        => 'required|string|max:255',
            'barcode_source' => 'sometimes|string|max:50',
            'label_size'     => 'sometimes|in:200,300,400',
            'show_barcode'   => 'sometimes|boolean',
            'show_qr'        => 'sometimes|boolean',
            'show_title'     => 'sometimes|boolean',
            'show_repo'      => 'sometimes|boolean',
        ]);

        $culture = app()->getLocale() ?? 'en';
        $labels = [];

        foreach ($validated['slugs'] as $slug) {
            $slugRow = DB::table('slug')->where('slug', $slug)->first();
            if (!$slugRow) {
                continue;
            }

            $objectId = $slugRow->object_id;
            $className = DB::table('object')->where('id', $objectId)->value('class_name');

            $title = '';
            $identifier = '';

            if ($className === 'QubitInformationObject') {
                $io = DB::table('information_object')->where('id', $objectId)->first();
                $i18n = DB::table('information_object_i18n')
                    ->where('id', $objectId)
                    ->where('culture', $culture)
                    ->first();
                $title = html_entity_decode($i18n->title ?? '', ENT_QUOTES, 'UTF-8');
                $identifier = $io->identifier ?? '';

                // Check library item for barcode sources
                $libraryItem = DB::table('library_item')
                    ->where('information_object_id', $objectId)
                    ->first();
                if ($libraryItem && !empty($libraryItem->isbn)) {
                    $identifier = $libraryItem->isbn;
                } elseif ($libraryItem && !empty($libraryItem->barcode)) {
                    $identifier = $libraryItem->barcode;
                }

                // Repository
                $repoName = '';
                $repoId = $io->repository_id ?? null;
                if ($repoId) {
                    $repoName = DB::table('actor_i18n')
                        ->where('id', $repoId)
                        ->where('culture', $culture)
                        ->value('authorized_form_of_name') ?? '';
                }
            } elseif (in_array($className, ['QubitActor', 'QubitRepository'])) {
                $i18n = DB::table('actor_i18n')
                    ->where('id', $objectId)
                    ->where('culture', $culture)
                    ->first();
                $title = $i18n->authorized_form_of_name ?? '';
                $actor = DB::table('actor')->where('id', $objectId)->first();
                $identifier = $actor->description_identifier ?? '';
                $repoName = '';
            } elseif ($className === 'QubitAccession') {
                $acc = DB::table('accession')->where('id', $objectId)->first();
                $title = $acc->identifier ?? '';
                $identifier = $acc->identifier ?? '';
                $repoName = '';
            } else {
                continue;
            }

            // Override barcode source if specified
            $barcodeData = $identifier ?: $title;
            if (!empty($validated['barcode_source']) && $validated['barcode_source'] === 'title') {
                $barcodeData = $title;
            }

            $labels[] = [
                'slug'       => $slug,
                'title'      => $title,
                'identifier' => $identifier,
                'barcodeData' => $barcodeData,
                'qrUrl'      => url('/' . $slug),
                'repository' => $repoName ?? '',
            ];
        }

        $labelSize = $validated['label_size'] ?? '300';
        $showBarcode = $validated['show_barcode'] ?? true;
        $showQr = $validated['show_qr'] ?? true;
        $showTitle = $validated['show_title'] ?? true;
        $showRepo = $validated['show_repo'] ?? true;

        return view('label::batch', compact(
            'labels',
            'labelSize',
            'showBarcode',
            'showQr',
            'showTitle',
            'showRepo'
        ));
    }
}
