<?php

declare(strict_types=1);

namespace Ahg3dModel\Controllers;

use Ahg3dModel\Services\ThreeDThumbnailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * 3D Model management controller.
 *
 * Provides browse, thumbnail generation and multi-angle generation
 * for 3D digital objects stored in the archive database.
 *
 * Ported from ahg3DModelPlugin model3dActions.
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
    // Browse
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

        // Build the base query: digital objects that are 3D models
        $baseQuery = DB::table('digital_object as do')
            ->whereNull('do.parent_id')
            ->where(function ($q) use ($extensions) {
                // Match by MIME type prefix
                $q->where('do.mime_type', 'LIKE', 'model/%');
                // Or by extension
                foreach ($extensions as $ext) {
                    $q->orWhere('do.name', 'LIKE', "%.{$ext}");
                }
            });

        // Total count
        $totalCount = (clone $baseQuery)->count();

        // Count with thumbnails (have at least one child derivative)
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

        // Fetch paginated results with related info
        $models = DB::table('digital_object as do')
            ->leftJoin('digital_object as deriv', function ($join) {
                $join->on('deriv.parent_id', '=', 'do.id');
            })
            ->leftJoin('information_object as io', 'do.object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug', function ($join) {
                $join->on('io.id', '=', 'slug.object_id');
            })
            ->whereNull('do.parent_id')
            ->where(function ($q) use ($extensions) {
                $q->where('do.mime_type', 'LIKE', 'model/%');
                foreach ($extensions as $ext) {
                    $q->orWhere('do.name', 'LIKE', "%.{$ext}");
                }
            })
            ->groupBy('do.id')
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

        // Check multi-angle directories
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

    /**
     * Generate thumbnail derivatives for a single 3D digital object.
     */
    public function generateThumbnail(int $id): RedirectResponse
    {
        $digitalObject = DB::table('digital_object')
            ->where('id', $id)
            ->whereNull('parent_id')
            ->first();

        if (!$digitalObject) {
            return redirect()
                ->route('admin.3d-models.browse')
                ->with('error', 'Digital object not found.');
        }

        if (!$this->thumbnailService->is3DModel($digitalObject->name)) {
            return redirect()
                ->route('admin.3d-models.browse')
                ->with('error', 'Not a recognised 3D model file.');
        }

        $success = $this->thumbnailService->createDerivatives($id);

        if ($success) {
            return redirect()
                ->route('admin.3d-models.browse')
                ->with('success', "Thumbnail generated for: {$digitalObject->name}");
        }

        return redirect()
            ->route('admin.3d-models.browse')
            ->with('error', "Thumbnail generation failed for: {$digitalObject->name}. Check storage/logs/3d-thumbnail.log for details.");
    }

    // ------------------------------------------------------------------
    // Generate multi-angle
    // ------------------------------------------------------------------

    /**
     * Generate 6 multi-angle renders for a single 3D digital object.
     */
    public function generateMultiAngle(int $id): RedirectResponse
    {
        $digitalObject = DB::table('digital_object')
            ->where('id', $id)
            ->whereNull('parent_id')
            ->first();

        if (!$digitalObject) {
            return redirect()
                ->route('admin.3d-models.browse')
                ->with('error', 'Digital object not found.');
        }

        if (!$this->thumbnailService->is3DModel($digitalObject->name)) {
            return redirect()
                ->route('admin.3d-models.browse')
                ->with('error', 'Not a recognised 3D model file.');
        }

        $uploadsBase = config('app.uploads_path', '/mnt/nas/heratio/archive');
        $masterPath = $uploadsBase . $digitalObject->path . $digitalObject->name;
        $outputDir = $this->thumbnailService->getMultiAngleDir($id);

        $results = $this->thumbnailService->generateMultiAngle($masterPath, $outputDir);

        if (count($results) > 0) {
            return redirect()
                ->route('admin.3d-models.browse')
                ->with('success', 'Multi-angle renders generated (' . count($results) . '/6 views) for: ' . $digitalObject->name);
        }

        return redirect()
            ->route('admin.3d-models.browse')
            ->with('error', "Multi-angle generation failed for: {$digitalObject->name}. Check storage/logs/3d-thumbnail.log for details.");
    }

    // ------------------------------------------------------------------
    // Batch thumbnails
    // ------------------------------------------------------------------

    /**
     * Batch-generate thumbnails for all 3D objects that are missing derivatives.
     */
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
            return redirect()
                ->route('admin.3d-models.browse')
                ->with('warning', $message);
        }

        if ($results['processed'] === 0) {
            return redirect()
                ->route('admin.3d-models.browse')
                ->with('info', 'No 3D objects are missing thumbnails.');
        }

        return redirect()
            ->route('admin.3d-models.browse')
            ->with('success', $message);
    }
}
