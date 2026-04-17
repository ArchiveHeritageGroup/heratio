<?php

/**
 * Model3dController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems.co.za
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



namespace Ahg3dModel\Controllers;

use Ahg3dModel\Services\ThreeDThumbnailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * 3D Model management controller.
 *
 * Provides browse, view, edit, upload, delete, hotspot and IIIF manifest
 * for 3D digital objects stored in the archive database.
 *
 * Ported from ahg3DModelPlugin model3dActions + model3dSettingsActions.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class Model3dController extends Controller
{
    private ThreeDThumbnailService $thumbnailService;

    public function __construct(ThreeDThumbnailService $thumbnailService)
    {
        $this->thumbnailService = $thumbnailService;
    }

    // ------------------------------------------------------------------
    // Browse (derivative-management view)
    // ------------------------------------------------------------------

    /**
     * List all 3D digital objects with thumbnail status.
     */
    public function browse(Request $request)
    {
        $perPage = 25;
        $page = max(1, (int) $request->get('page', 1));
        $offset = ($page - 1) * $perPage;

        $extensions = $this->thumbnailService->getSupportedExtensions();

        $baseQuery = DB::table('digital_object as do')
            ->whereNull('do.parent_id')
            ->where(function ($q) use ($extensions) {
                $q->where('do.mime_type', 'LIKE', 'model/%');
                foreach ($extensions as $ext) {
                    $q->orWhere('do.name', 'LIKE', "%.{$ext}");
                }
            });

        $totalCount = (clone $baseQuery)->count();

        $withThumbnails = DB::table('digital_object as do')
            ->join('digital_object as deriv', 'deriv.parent_id', '=', 'do.id')
            ->whereNull('do.parent_id')
            ->where(function ($q) use ($extensions) {
                $q->where('do.mime_type', 'LIKE', 'model/%');
                foreach ($extensions as $ext) {
                    $q->orWhere('do.name', 'LIKE', "%.{$ext}");
                }
            })
            ->distinct()
            ->count('do.id');

        $withoutThumbnails = $totalCount - $withThumbnails;

        $models = DB::table('digital_object as do')
            ->leftJoin('digital_object as deriv', 'deriv.parent_id', '=', 'do.id')
            ->leftJoin('information_object as io', 'do.object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->whereNull('do.parent_id')
            ->where(function ($q) use ($extensions) {
                $q->where('do.mime_type', 'LIKE', 'model/%');
                foreach ($extensions as $ext) {
                    $q->orWhere('do.name', 'LIKE', "%.{$ext}");
                }
            })
            ->groupBy('do.id', 'do.name', 'do.path', 'do.mime_type', 'do.byte_size', 'do.object_id', 'ioi.title', 'slug.slug')
            ->orderBy('do.id', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->select(
                'do.id',
                'do.name',
                'do.path',
                'do.mime_type',
                'do.byte_size',
                'do.object_id',
                'ioi.title as object_title',
                'slug.slug as object_slug',
                DB::raw('COUNT(deriv.id) as derivative_count'),
            )
            ->get();

        foreach ($models as $model) {
            $maDir = $this->thumbnailService->getMultiAngleDir($model->id);
            $model->has_multiangle = is_dir($maDir) && count(glob($maDir . '/*.png')) >= 6;
            $model->has_thumbnail = $model->derivative_count > 0;
            $model->format = strtoupper(pathinfo($model->name, PATHINFO_EXTENSION));
        }

        $totalPages = $totalCount > 0 ? (int) ceil($totalCount / $perPage) : 1;

        return view('ahg-3d-model::browse', [
            'models' => $models,
            'totalCount' => $totalCount,
            'withThumbnails' => $withThumbnails,
            'withoutThumbnails' => $withoutThumbnails,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    // ------------------------------------------------------------------
    // Generate thumbnail
    // ------------------------------------------------------------------

    public function generateThumbnail(int $id): RedirectResponse
    {
        $digitalObject = DB::table('digital_object')
            ->where('id', $id)
            ->whereNull('parent_id')
            ->first();

        if (!$digitalObject) {
            return redirect()->route('admin.3d-models.browse')
                ->with('error', 'Digital object not found.');
        }

        if (!$this->thumbnailService->is3DModel($digitalObject->name)) {
            return redirect()->route('admin.3d-models.browse')
                ->with('error', 'Not a recognised 3D model file.');
        }

        $success = $this->thumbnailService->createDerivatives($id);

        if ($success) {
            return redirect()->route('admin.3d-models.browse')
                ->with('success', "Thumbnail generated for: {$digitalObject->name}");
        }

        return redirect()->route('admin.3d-models.browse')
            ->with('error', "Thumbnail generation failed for: {$digitalObject->name}. Check storage/logs/3d-thumbnail.log for details.");
    }

    // ------------------------------------------------------------------
    // Generate multi-angle
    // ------------------------------------------------------------------

    public function generateMultiAngle(int $id): RedirectResponse
    {
        $digitalObject = DB::table('digital_object')
            ->where('id', $id)
            ->whereNull('parent_id')
            ->first();

        if (!$digitalObject) {
            return redirect()->route('admin.3d-models.browse')
                ->with('error', 'Digital object not found.');
        }

        if (!$this->thumbnailService->is3DModel($digitalObject->name)) {
            return redirect()->route('admin.3d-models.browse')
                ->with('error', 'Not a recognised 3D model file.');
        }

        $uploadsBase = config('heratio.uploads_path');
        $masterPath = $uploadsBase . $digitalObject->path . $digitalObject->name;
        $outputDir = $this->thumbnailService->getMultiAngleDir($id);

        $results = $this->thumbnailService->generateMultiAngle($masterPath, $outputDir);

        if (count($results) > 0) {
            return redirect()->route('admin.3d-models.browse')
                ->with('success', 'Multi-angle renders generated (' . count($results) . '/6 views) for: ' . $digitalObject->name);
        }

        return redirect()->route('admin.3d-models.browse')
            ->with('error', "Multi-angle generation failed for: {$digitalObject->name}. Check storage/logs/3d-thumbnail.log for details.");
    }

    // ------------------------------------------------------------------
    // Batch thumbnails
    // ------------------------------------------------------------------

    public function batchThumbnails(Request $request): RedirectResponse
    {
        $results = $this->thumbnailService->batchProcessExisting();

        $message = sprintf(
            'Batch thumbnail generation complete. Processed: %d, Success: %d, Failed: %d.',
            $results['processed'],
            $results['success'],
            $results['failed'],
        );

        if ($results['failed'] > 0) {
            return redirect()->route('admin.3d-models.browse')
                ->with('warning', $message);
        }

        if ($results['processed'] === 0) {
            return redirect()->route('admin.3d-models.browse')
                ->with('info', 'No 3D objects are missing thumbnails.');
        }

        return redirect()->route('admin.3d-models.browse')
            ->with('success', $message);
    }

    // ------------------------------------------------------------------
    // Index (model list from object_3d_model table)
    // ------------------------------------------------------------------

    public function index(Request $request)
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $models = DB::table('object_3d_model as m')
            ->leftJoin('object_3d_model_i18n as i18n', function ($join) {
                $join->on('m.id', '=', 'i18n.model_id')
                     ->where('i18n.culture', '=', 'en');
            })
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('m.object_id', '=', 'ioi.id')
                     ->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug', 'm.object_id', '=', 'slug.object_id')
            ->orderBy('m.created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->select(
                'm.*',
                'i18n.title as model_title',
                'i18n.description',
                'ioi.title as object_title',
                'slug.slug as object_slug'
            )
            ->get();

        $total = DB::table('object_3d_model')->count();
        $totalPages = $total > 0 ? (int) ceil($total / $limit) : 1;

        return view('ahg-3d-model::index', [
            'models' => $models,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    // ------------------------------------------------------------------
    // View single model
    // ------------------------------------------------------------------

    public function view(int $id)
    {
        $model = DB::table('object_3d_model as m')
            ->leftJoin('object_3d_model_i18n as i18n', function ($join) {
                $join->on('m.id', '=', 'i18n.model_id')
                     ->where('i18n.culture', '=', 'en');
            })
            ->where('m.id', $id)
            ->select('m.*', 'i18n.title as model_title', 'i18n.description', 'i18n.alt_text')
            ->first();

        if (!$model) {
            abort(404);
        }

        $hotspots = DB::table('object_3d_hotspot as h')
            ->leftJoin('object_3d_hotspot_i18n as i18n', function ($join) {
                $join->on('h.id', '=', 'i18n.hotspot_id')
                     ->where('i18n.culture', '=', 'en');
            })
            ->where('h.model_id', $id)
            ->where('h.is_visible', 1)
            ->orderBy('h.display_order')
            ->select('h.*', 'i18n.title as hotspot_title', 'i18n.description as hotspot_description')
            ->get();

        $object = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')
                     ->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.id', $model->object_id)
            ->select('io.id', 'ioi.title', 'slug.slug')
            ->first();

        // Log view
        $this->logAction($id, 'view');

        return view('ahg-3d-model::view', [
            'model' => $model,
            'hotspots' => $hotspots,
            'object' => $object,
        ]);
    }

    // ------------------------------------------------------------------
    // Edit model settings
    // ------------------------------------------------------------------

    public function edit(Request $request, int $id)
    {
        $model = DB::table('object_3d_model as m')
            ->leftJoin('object_3d_model_i18n as i18n', function ($join) {
                $join->on('m.id', '=', 'i18n.model_id')
                     ->where('i18n.culture', '=', 'en');
            })
            ->where('m.id', $id)
            ->select('m.*', 'i18n.title as model_title', 'i18n.description', 'i18n.alt_text')
            ->first();

        if (!$model) {
            abort(404);
        }

        $hotspots = DB::table('object_3d_hotspot as h')
            ->leftJoin('object_3d_hotspot_i18n as i18n', function ($join) {
                $join->on('h.id', '=', 'i18n.hotspot_id')
                     ->where('i18n.culture', '=', 'en');
            })
            ->where('h.model_id', $id)
            ->orderBy('h.display_order')
            ->select('h.*', 'i18n.title as hotspot_title', 'i18n.description as hotspot_description')
            ->get();

        // Handle POST
        if ($request->isMethod('post')) {
            DB::table('object_3d_model')
                ->where('id', $id)
                ->update([
                    'auto_rotate' => $request->has('auto_rotate') ? 1 : 0,
                    'rotation_speed' => (int) $request->input('rotation_speed', 30),
                    'camera_orbit' => $request->input('camera_orbit', '0deg 75deg 105%'),
                    'field_of_view' => $request->input('field_of_view', '30deg'),
                    'exposure' => (float) $request->input('exposure', 1.0),
                    'shadow_intensity' => (float) $request->input('shadow_intensity', 1.0),
                    'shadow_softness' => (float) $request->input('shadow_softness', 1.0),
                    'background_color' => $request->input('background_color', '#f5f5f5'),
                    'ar_enabled' => $request->has('ar_enabled') ? 1 : 0,
                    'ar_scale' => $request->input('ar_scale', 'auto'),
                    'ar_placement' => $request->input('ar_placement', 'floor'),
                    'is_primary' => $request->has('is_primary') ? 1 : 0,
                    'is_public' => $request->has('is_public') ? 1 : 0,
                    'updated_at' => now(),
                ]);

            DB::table('object_3d_model_i18n')
                ->updateOrInsert(
                    ['model_id' => $id, 'culture' => 'en'],
                    [
                        'title' => $request->input('title'),
                        'description' => $request->input('description'),
                        'alt_text' => $request->input('alt_text'),
                    ]
                );

            $this->logAction($id, 'update');

            return redirect()->route('admin.3d-models.edit', $id)
                ->with('success', 'Model settings updated.');
        }

        return view('ahg-3d-model::edit', [
            'model' => $model,
            'hotspots' => $hotspots,
        ]);
    }

    // ------------------------------------------------------------------
    // Embed (iframe viewer)
    // ------------------------------------------------------------------

    public function embed(int $id)
    {
        $model = DB::table('object_3d_model as m')
            ->leftJoin('object_3d_model_i18n as i18n', function ($join) {
                $join->on('m.id', '=', 'i18n.model_id')
                     ->where('i18n.culture', '=', 'en');
            })
            ->where('m.id', $id)
            ->where('m.is_public', 1)
            ->select('m.*', 'i18n.title as model_title', 'i18n.description', 'i18n.alt_text')
            ->first();

        if (!$model) {
            abort(404);
        }

        $hotspots = DB::table('object_3d_hotspot as h')
            ->leftJoin('object_3d_hotspot_i18n as i18n', function ($join) {
                $join->on('h.id', '=', 'i18n.hotspot_id')
                     ->where('i18n.culture', '=', 'en');
            })
            ->where('h.model_id', $id)
            ->where('h.is_visible', 1)
            ->orderBy('h.display_order')
            ->select('h.*', 'i18n.title as hotspot_title', 'i18n.description as hotspot_description')
            ->get();

        return view('ahg-3d-model::embed', [
            'model' => $model,
            'hotspots' => $hotspots,
        ]);
    }

    // ------------------------------------------------------------------
    // Upload
    // ------------------------------------------------------------------

    public function upload(Request $request, int $objectId)
    {
        $object = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')
                     ->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.id', $objectId)
            ->select('io.id', 'ioi.title', 'slug.slug')
            ->first();

        if (!$object) {
            abort(404);
        }

        $allowedFormats = ['glb', 'gltf', 'usdz', 'obj', 'stl', 'ply'];
        $maxFileSize = 100;

        // Get settings if table exists
        try {
            $maxSetting = DB::table('viewer_3d_settings')
                ->where('setting_key', 'max_file_size_mb')
                ->value('setting_value');
            if ($maxSetting) {
                $maxFileSize = (int) $maxSetting;
            }

            $fmtSetting = DB::table('viewer_3d_settings')
                ->where('setting_key', 'allowed_formats')
                ->value('setting_value');
            if ($fmtSetting) {
                $decoded = json_decode($fmtSetting, true);
                if (is_array($decoded)) {
                    $allowedFormats = $decoded;
                }
            }
        } catch (\Exception $e) {
            // Table may not exist yet
        }

        // Handle POST
        if ($request->isMethod('post') && $request->hasFile('model_file')) {
            $file = $request->file('model_file');
            $ext = strtolower($file->getClientOriginalExtension());

            if (!in_array($ext, $allowedFormats)) {
                return redirect()->back()
                    ->with('error', 'Invalid file format. Allowed: ' . implode(', ', $allowedFormats));
            }

            $maxBytes = $maxFileSize * 1024 * 1024;
            if ($file->getSize() > $maxBytes) {
                return redirect()->back()
                    ->with('error', "File too large. Maximum: {$maxFileSize} MB");
            }

            $uploadDir = config('heratio.uploads_path') . '/3d/' . $objectId;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
            $file->move($uploadDir, $filename);

            $mimeTypes = [
                'glb' => 'model/gltf-binary',
                'gltf' => 'model/gltf+json',
                'obj' => 'model/obj',
                'stl' => 'model/stl',
                'ply' => 'application/x-ply',
                'usdz' => 'model/vnd.usdz+zip',
            ];

            $existingCount = DB::table('object_3d_model')
                ->where('object_id', $objectId)
                ->count();

            $modelId = DB::table('object_3d_model')->insertGetId([
                'object_id' => $objectId,
                'filename' => $filename,
                'original_filename' => $file->getClientOriginalName(),
                'file_path' => '3d/' . $objectId . '/' . $filename,
                'file_size' => $file->getSize(),
                'mime_type' => $mimeTypes[$ext] ?? 'application/octet-stream',
                'format' => $ext,
                'auto_rotate' => 1,
                'rotation_speed' => 30,
                'camera_orbit' => '0deg 75deg 105%',
                'field_of_view' => '30deg',
                'exposure' => 1.0,
                'shadow_intensity' => 1.0,
                'shadow_softness' => 1.0,
                'background_color' => '#f5f5f5',
                'ar_enabled' => 1,
                'ar_scale' => 'auto',
                'ar_placement' => 'floor',
                'is_primary' => ($existingCount === 0) ? 1 : 0,
                'is_public' => $request->has('is_public') ? 1 : 0,
                'display_order' => $existingCount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $title = $request->input('title') ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            DB::table('object_3d_model_i18n')->insert([
                'model_id' => $modelId,
                'culture' => 'en',
                'title' => $title,
                'description' => $request->input('description'),
                'alt_text' => $request->input('alt_text'),
            ]);

            $this->logAction($modelId, 'upload');

            return redirect()->route('admin.3d-models.view', $modelId)
                ->with('success', '3D model uploaded successfully.');
        }

        return view('ahg-3d-model::upload', [
            'object' => $object,
            'allowedFormats' => $allowedFormats,
            'maxFileSize' => $maxFileSize,
        ]);
    }

    // ------------------------------------------------------------------
    // Delete
    // ------------------------------------------------------------------

    public function delete(Request $request, int $id): RedirectResponse
    {
        $model = DB::table('object_3d_model')->where('id', $id)->first();
        if (!$model) {
            abort(404);
        }

        $objectId = $model->object_id;

        // Delete file
        $uploadsBase = config('heratio.uploads_path');
        $filePath = $uploadsBase . '/' . $model->file_path;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        if ($model->poster_image) {
            $posterPath = $uploadsBase . '/' . $model->poster_image;
            if (file_exists($posterPath)) {
                unlink($posterPath);
            }
        }

        $this->logAction($id, 'delete');

        // Delete related records
        $hotspotIds = DB::table('object_3d_hotspot')
            ->where('model_id', $id)
            ->pluck('id');

        if ($hotspotIds->isNotEmpty()) {
            DB::table('object_3d_hotspot_i18n')->whereIn('hotspot_id', $hotspotIds)->delete();
        }
        DB::table('object_3d_hotspot')->where('model_id', $id)->delete();
        DB::table('object_3d_model_i18n')->where('model_id', $id)->delete();
        DB::table('object_3d_model')->where('id', $id)->delete();

        $slug = DB::table('slug')->where('object_id', $objectId)->value('slug');

        return redirect('/' . ($slug ?: ''))
            ->with('success', '3D model deleted.');
    }

    // ------------------------------------------------------------------
    // Settings
    // ------------------------------------------------------------------

    public function settings(Request $request)
    {
        $settingsRaw = [];
        try {
            $settingsRaw = DB::table('viewer_3d_settings')
                ->orderBy('setting_key')
                ->get()
                ->keyBy('setting_key')
                ->toArray();
        } catch (\Exception $e) {
            // Table may not exist
        }

        $triposrHealth = $this->checkTripoSRHealth($settingsRaw);

        $stats = [
            'total_models' => 0,
            'ar_enabled_models' => 0,
            'total_hotspots' => 0,
            'total_views' => 0,
            'total_ar_views' => 0,
            'storage_used' => 0,
        ];
        $formatStats = [];

        try {
            $stats['total_models'] = DB::table('object_3d_model')->count();
            $stats['ar_enabled_models'] = DB::table('object_3d_model')->where('ar_enabled', 1)->count();
            $stats['total_hotspots'] = DB::table('object_3d_hotspot')->count();
            $stats['total_views'] = DB::table('object_3d_audit_log')->where('action', 'view')->count();
            $stats['total_ar_views'] = DB::table('object_3d_audit_log')->where('action', 'ar_view')->count();
            $stats['storage_used'] = DB::table('object_3d_model')->sum('file_size');
            $formatStats = DB::table('object_3d_model')
                ->select('format', DB::raw('COUNT(*) as count'))
                ->groupBy('format')
                ->pluck('count', 'format')
                ->toArray();
        } catch (\Exception $e) {
            // Tables may not exist
        }

        // Handle POST
        if ($request->isMethod('post')) {
            $settingsToUpdate = [
                'default_viewer' => $request->input('default_viewer', 'model-viewer'),
                'enable_ar' => $request->has('enable_ar') ? '1' : '0',
                'enable_fullscreen' => $request->has('enable_fullscreen') ? '1' : '0',
                'enable_download' => $request->has('enable_download') ? '1' : '0',
                'default_background' => $request->input('default_background', '#f5f5f5'),
                'default_exposure' => $request->input('default_exposure', '1.0'),
                'default_shadow_intensity' => $request->input('default_shadow_intensity', '1.0'),
                'max_file_size_mb' => $request->input('max_file_size_mb', '100'),
                'enable_annotations' => $request->has('enable_annotations') ? '1' : '0',
                'enable_auto_rotate' => $request->has('enable_auto_rotate') ? '1' : '0',
                'rotation_speed' => $request->input('rotation_speed', '30'),
                'watermark_enabled' => $request->has('watermark_enabled') ? '1' : '0',
                'watermark_text' => $request->input('watermark_text', ''),
                'allowed_formats' => json_encode($request->input('allowed_formats', [])),
                'triposr_enabled' => $request->has('triposr_enabled') ? '1' : '0',
                'triposr_api_url' => $request->input('triposr_api_url', 'http://127.0.0.1:5050'),
                'triposr_mode' => $request->input('triposr_mode', 'local'),
                'triposr_remote_url' => $request->input('triposr_remote_url', ''),
                'triposr_timeout' => $request->input('triposr_timeout', '300'),
                'triposr_remove_bg' => $request->has('triposr_remove_bg') ? '1' : '0',
                'triposr_foreground_ratio' => $request->input('triposr_foreground_ratio', '0.85'),
                'triposr_mc_resolution' => $request->input('triposr_mc_resolution', '256'),
                'triposr_bake_texture' => $request->has('triposr_bake_texture') ? '1' : '0',
            ];

            // Don't overwrite API key with masked value
            $apiKey = $request->input('triposr_remote_api_key');
            if ($apiKey && $apiKey !== '***') {
                $settingsToUpdate['triposr_remote_api_key'] = $apiKey;
            }

            foreach ($settingsToUpdate as $key => $value) {
                try {
                    DB::table('viewer_3d_settings')
                        ->updateOrInsert(
                            ['setting_key' => $key],
                            ['setting_value' => (string) $value]
                        );
                } catch (\Exception $e) {
                    // Continue on error
                }
            }

            return redirect()->route('admin.3d-models.settings')
                ->with('success', 'Settings saved successfully.');
        }

        return view('ahg-3d-model::settings', [
            'settings' => $settingsRaw,
            'stats' => $stats,
            'formatStats' => $formatStats,
            'triposrHealth' => $triposrHealth,
        ]);
    }

    // ------------------------------------------------------------------
    // TripoSR Settings
    // ------------------------------------------------------------------

    public function triposr(Request $request)
    {
        $settingsRaw = [];
        try {
            $settingsRaw = DB::table('viewer_3d_settings')
                ->where('setting_key', 'LIKE', 'triposr_%')
                ->get()
                ->keyBy('setting_key')
                ->toArray();
        } catch (\Exception $e) {
            // Table may not exist
        }

        $health = $this->checkTripoSRHealth($settingsRaw);

        $stats = ['total_jobs' => 0, 'completed' => 0, 'failed' => 0, 'pending' => 0];
        $recentJobs = [];
        try {
            $stats['total_jobs'] = DB::table('triposr_jobs')->count();
            $stats['completed'] = DB::table('triposr_jobs')->where('status', 'completed')->count();
            $stats['failed'] = DB::table('triposr_jobs')->where('status', 'failed')->count();
            $stats['pending'] = DB::table('triposr_jobs')->whereIn('status', ['pending', 'processing'])->count();
            $recentJobs = DB::table('triposr_jobs')->orderBy('created_at', 'desc')->limit(10)->get();
        } catch (\Exception $e) {
            // Table may not exist
        }

        // Handle POST
        if ($request->isMethod('post')) {
            $triposrSettings = [
                'triposr_enabled' => $request->has('triposr_enabled') ? '1' : '0',
                'triposr_api_url' => $request->input('triposr_api_url', 'http://127.0.0.1:5050'),
                'triposr_mode' => $request->input('triposr_mode', 'local'),
                'triposr_remote_url' => $request->input('triposr_remote_url', ''),
                'triposr_timeout' => $request->input('triposr_timeout', '300'),
                'triposr_remove_bg' => $request->has('triposr_remove_bg') ? '1' : '0',
                'triposr_foreground_ratio' => $request->input('triposr_foreground_ratio', '0.85'),
                'triposr_mc_resolution' => $request->input('triposr_mc_resolution', '256'),
                'triposr_bake_texture' => $request->has('triposr_bake_texture') ? '1' : '0',
            ];

            $apiKey = $request->input('triposr_remote_api_key');
            if ($apiKey && $apiKey !== '***') {
                $triposrSettings['triposr_remote_api_key'] = $apiKey;
            }

            foreach ($triposrSettings as $key => $value) {
                try {
                    DB::table('viewer_3d_settings')
                        ->updateOrInsert(
                            ['setting_key' => $key],
                            ['setting_value' => (string) $value]
                        );
                } catch (\Exception $e) {
                    // Continue
                }
            }

            return redirect()->route('admin.3d-models.triposr')
                ->with('success', 'TripoSR settings saved successfully.');
        }

        return view('ahg-3d-model::triposr', [
            'settings' => $settingsRaw,
            'health' => $health,
            'stats' => $stats,
            'recentJobs' => $recentJobs,
        ]);
    }

    // ------------------------------------------------------------------
    // Add Hotspot (AJAX)
    // ------------------------------------------------------------------

    public function addHotspot(Request $request, int $modelId): JsonResponse
    {
        $input = $request->all();

        $maxOrder = DB::table('object_3d_hotspot')
            ->where('model_id', $modelId)
            ->max('display_order') ?? -1;

        $colors = [
            'annotation' => '#1a73e8',
            'info' => '#34a853',
            'link' => '#4285f4',
            'damage' => '#ea4335',
            'detail' => '#fbbc04',
        ];
        $hotspotType = $input['hotspot_type'] ?? 'annotation';
        $color = $colors[$hotspotType] ?? '#1a73e8';

        $hotspotId = DB::table('object_3d_hotspot')->insertGetId([
            'model_id' => $modelId,
            'hotspot_type' => $hotspotType,
            'position_x' => (float) ($input['position_x'] ?? 0),
            'position_y' => (float) ($input['position_y'] ?? 0),
            'position_z' => (float) ($input['position_z'] ?? 0),
            'normal_x' => (float) ($input['normal_x'] ?? 0),
            'normal_y' => (float) ($input['normal_y'] ?? 1),
            'normal_z' => (float) ($input['normal_z'] ?? 0),
            'color' => $color,
            'link_url' => $input['link_url'] ?? null,
            'link_target' => $input['link_target'] ?? '_blank',
            'display_order' => $maxOrder + 1,
            'is_visible' => 1,
            'created_at' => now(),
        ]);

        DB::table('object_3d_hotspot_i18n')->insert([
            'hotspot_id' => $hotspotId,
            'culture' => 'en',
            'title' => $input['title'] ?? '',
            'description' => $input['description'] ?? '',
        ]);

        $this->logAction($modelId, 'hotspot_add');

        return response()->json(['success' => true, 'id' => $hotspotId, 'color' => $color]);
    }

    // ------------------------------------------------------------------
    // Delete Hotspot (AJAX)
    // ------------------------------------------------------------------

    public function deleteHotspot(Request $request, int $hotspotId): JsonResponse
    {
        $hotspot = DB::table('object_3d_hotspot')->where('id', $hotspotId)->first();
        if (!$hotspot) {
            return response()->json(['success' => false, 'error' => 'Not found'], 404);
        }

        $this->logAction($hotspot->model_id, 'hotspot_delete');

        DB::table('object_3d_hotspot_i18n')->where('hotspot_id', $hotspotId)->delete();
        DB::table('object_3d_hotspot')->where('id', $hotspotId)->delete();

        return response()->json(['success' => true]);
    }

    // ------------------------------------------------------------------
    // IIIF 3D Manifest
    // ------------------------------------------------------------------

    public function iiifManifest(Request $request, int $id): JsonResponse
    {
        $model = DB::table('object_3d_model as m')
            ->leftJoin('object_3d_model_i18n as i18n', function ($join) {
                $join->on('m.id', '=', 'i18n.model_id')
                     ->where('i18n.culture', '=', 'en');
            })
            ->where('m.id', $id)
            ->where('m.is_public', 1)
            ->select('m.*', 'i18n.title as model_title', 'i18n.description', 'i18n.alt_text')
            ->first();

        if (!$model) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $hotspots = DB::table('object_3d_hotspot as h')
            ->leftJoin('object_3d_hotspot_i18n as i18n', function ($join) {
                $join->on('h.id', '=', 'i18n.hotspot_id')
                     ->where('i18n.culture', '=', 'en');
            })
            ->where('h.model_id', $id)
            ->where('h.is_visible', 1)
            ->orderBy('h.display_order')
            ->select('h.*', 'i18n.title as hotspot_title', 'i18n.description as hotspot_description')
            ->get();

        $baseUrl = $request->getSchemeAndHttpHost();
        $modelUrl = $baseUrl . '/uploads/' . $model->file_path;

        $manifest = [
            '@context' => [
                'http://iiif.io/api/presentation/3/context.json',
                'http://iiif.io/api/extension/3d/context.json',
            ],
            'id' => $baseUrl . '/iiif/3d/' . $id . '/manifest.json',
            'type' => 'Manifest',
            'label' => ['en' => [$model->model_title ?: 'Untitled 3D Model']],
            'metadata' => [
                ['label' => ['en' => ['Format']], 'value' => ['en' => [strtoupper($model->format)]]],
                ['label' => ['en' => ['File Size']], 'value' => ['en' => [number_format($model->file_size / 1048576, 2) . ' MB']]],
            ],
            'items' => [[
                'id' => $baseUrl . '/iiif/3d/' . $id . '/scene/1',
                'type' => 'Scene',
                'items' => [[
                    'id' => $baseUrl . '/iiif/3d/' . $id . '/annotation/1',
                    'type' => 'Annotation',
                    'motivation' => 'painting',
                    'body' => [
                        'id' => $modelUrl,
                        'type' => 'Model',
                        'format' => $model->mime_type,
                    ],
                    'target' => $baseUrl . '/iiif/3d/' . $id . '/scene/1',
                ]],
            ]],
            'extensions' => [
                'viewer' => [
                    'autoRotate' => (bool) $model->auto_rotate,
                    'rotationSpeed' => (int) $model->rotation_speed,
                    'cameraOrbit' => $model->camera_orbit,
                    'fieldOfView' => $model->field_of_view,
                    'exposure' => (float) $model->exposure,
                    'backgroundColor' => $model->background_color,
                    'arEnabled' => (bool) $model->ar_enabled,
                ],
            ],
        ];

        if ($model->description) {
            $manifest['summary'] = ['en' => [$model->description]];
        }

        if ($hotspots->isNotEmpty()) {
            $annotations = [];
            foreach ($hotspots as $hotspot) {
                $annotations[] = [
                    'id' => $baseUrl . '/iiif/3d/' . $id . '/hotspot/' . $hotspot->id,
                    'type' => 'Annotation',
                    'motivation' => 'commenting',
                    'body' => [
                        'type' => 'TextualBody',
                        'value' => ($hotspot->hotspot_title ?: '') . ($hotspot->hotspot_description ? ': ' . $hotspot->hotspot_description : ''),
                        'format' => 'text/plain',
                    ],
                    'target' => [
                        'type' => 'PointSelector',
                        'x' => (float) $hotspot->position_x,
                        'y' => (float) $hotspot->position_y,
                        'z' => (float) $hotspot->position_z,
                    ],
                ];
            }
            $manifest['annotations'] = [[
                'id' => $baseUrl . '/iiif/3d/' . $id . '/annotations/1',
                'type' => 'AnnotationPage',
                'items' => $annotations,
            ]];
        }

        return response()->json($manifest, 200, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    // ------------------------------------------------------------------
    // API: Models for object
    // ------------------------------------------------------------------

    public function apiModels(Request $request, int $objectId): JsonResponse
    {
        $models = DB::table('object_3d_model as m')
            ->leftJoin('object_3d_model_i18n as i18n', function ($join) {
                $join->on('m.id', '=', 'i18n.model_id')
                     ->where('i18n.culture', '=', 'en');
            })
            ->where('m.object_id', $objectId)
            ->where('m.is_public', 1)
            ->orderBy('m.is_primary', 'desc')
            ->orderBy('m.display_order')
            ->select('m.*', 'i18n.title as model_title', 'i18n.description', 'i18n.alt_text')
            ->get();

        return response()->json(['models' => $models], 200, [
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    // ------------------------------------------------------------------
    // API: Hotspots for model
    // ------------------------------------------------------------------

    public function apiHotspots(Request $request, int $modelId): JsonResponse
    {
        $hotspots = DB::table('object_3d_hotspot as h')
            ->leftJoin('object_3d_hotspot_i18n as i18n', function ($join) {
                $join->on('h.id', '=', 'i18n.hotspot_id')
                     ->where('i18n.culture', '=', 'en');
            })
            ->where('h.model_id', $modelId)
            ->where('h.is_visible', 1)
            ->orderBy('h.display_order')
            ->select('h.*', 'i18n.title as hotspot_title', 'i18n.description as hotspot_description')
            ->get();

        return response()->json(['hotspots' => $hotspots], 200, [
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    private function logAction(int $modelId, string $action, array $details = []): void
    {
        try {
            $model = DB::table('object_3d_model')->where('id', $modelId)->first();

            DB::table('object_3d_audit_log')->insert([
                'model_id' => $modelId,
                'object_id' => $model->object_id ?? null,
                'user_id' => auth()->id(),
                'user_name' => auth()->user()?->username ?? 'system',
                'action' => $action,
                'details' => !empty($details) ? json_encode($details) : null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Audit table may not exist, skip silently
        }
    }

    private function checkTripoSRHealth(array $settingsRaw = []): array
    {
        $apiUrl = 'http://127.0.0.1:5050';
        if (isset($settingsRaw['triposr_api_url'])) {
            $val = is_object($settingsRaw['triposr_api_url'])
                ? $settingsRaw['triposr_api_url']->setting_value
                : $settingsRaw['triposr_api_url'];
            if ($val) {
                $apiUrl = $val;
            }
        }

        try {
            $ch = curl_init($apiUrl . '/health');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error || $httpCode !== 200) {
                return ['status' => 'error', 'message' => $error ?: 'HTTP ' . $httpCode];
            }

            $data = json_decode($response, true) ?: [];
            $data['status'] = 'ok';
            return $data;
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
