<?php

/**
 * PortableExportController - Controller for Heratio
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

namespace AhgPortableExport\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PortableExportController extends Controller
{
    public function index()
    {
        $culture = app()->getLocale();

        // Repositories for the Step 1 dropdown — name comes from actor_i18n
        $repositories = DB::table('repository')
            ->join('actor_i18n', function ($j) use ($culture) {
                $j->on('repository.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $culture);
            })
            ->select(['repository.id', 'actor_i18n.authorized_form_of_name as name'])
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->get();

        // Past exports for the bottom table (only when the table exists)
        $exports = collect();
        if (Schema::hasTable('portable_export')) {
            $exports = DB::table('portable_export')
                ->orderByDesc('created_at')
                ->limit(25)
                ->get();
        }

        return view('ahg-portable-export::index', [
            'repositories' => $repositories,
            'exports' => $exports,
        ]);
    }

    public function export(Request $request)
    {
        // Legacy POST handler kept for backward compatibility with the old form.
        // The new wizard hits apiStart() instead.
        return $this->apiStart($request);
    }

    public function import(Request $request)
    {
        return view('ahg-portable-export::import');
    }

    public function download(Request $request)
    {
        $id = (int) $request->query('id');
        if (!$id || !Schema::hasTable('portable_export')) {
            abort(404);
        }
        $export = DB::table('portable_export')->where('id', $id)->first();
        if (!$export || empty($export->output_path) || !file_exists($export->output_path)) {
            abort(404, 'Export file not found');
        }
        return response()->download($export->output_path);
    }

    // ── API endpoints used by the wizard JS ─────────────────────────

    public function apiStart(Request $request): JsonResponse
    {
        $data = $request->all();
        $title = $data['title'] ?? 'Untitled Export';
        $scope = $data['scope_type'] ?? $data['scope'] ?? 'all';
        $mode  = $data['mode'] ?? 'read_only';

        if (!Schema::hasTable('portable_export')) {
            return response()->json([
                'success' => false,
                'error' => 'portable_export table does not exist — install the package schema first.',
            ], 500);
        }

        $id = DB::table('portable_export')->insertGetId([
            'user_id' => auth()->id() ?? 0,
            'title' => $title,
            'scope_type' => $scope,
            'scope_repository_id' => $data['repository_id'] ?? null,
            'scope_slug' => $data['scope_slug'] ?? null,
            'mode' => $mode,
            'culture' => $data['culture'] ?? app()->getLocale(),
            'include_masters' => !empty($data['include_masters']),
            'include_thumbnails' => !empty($data['include_thumbnails']),
            'include_references' => !empty($data['include_references']),
            'branding' => json_encode([
                'title' => $data['branding_title'] ?? null,
                'subtitle' => $data['branding_subtitle'] ?? null,
                'footer' => $data['branding_footer'] ?? null,
            ]),
            'entity_types' => $data['entity_types'] ?? null,
            'status' => 'pending',
            'progress' => 0,
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'export_id' => $id,
            'message' => 'Export queued.',
        ]);
    }

    public function apiProgress(Request $request): JsonResponse
    {
        $id = (int) $request->query('id');
        if (!$id || !Schema::hasTable('portable_export')) {
            return response()->json(['status' => 'unknown']);
        }
        $row = DB::table('portable_export')->where('id', $id)->first();
        if (!$row) {
            return response()->json(['status' => 'unknown']);
        }
        return response()->json([
            'status' => $row->status,
            'progress' => (int) ($row->progress ?? 0),
            'output_size' => (int) ($row->output_size ?? 0),
            'total_descriptions' => (int) ($row->total_descriptions ?? 0),
            'total_objects' => (int) ($row->total_objects ?? 0),
            'error_message' => $row->error_message ?? null,
        ]);
    }

    public function apiEstimate(Request $request): JsonResponse
    {
        $scope = $request->query('scope_type', 'all');
        $repoId = (int) $request->query('repository_id');
        $slug = $request->query('scope_slug');

        $ioQuery = DB::table('information_object')->where('id', '!=', 1);
        if ($scope === 'repository' && $repoId) {
            $ioQuery->where('repository_id', $repoId);
        }
        if ($scope === 'fonds' && $slug) {
            $ancestor = DB::table('information_object as io')
                ->join('slug', 'io.id', '=', 'slug.object_id')
                ->where('slug.slug', $slug)
                ->select('io.lft', 'io.rgt')
                ->first();
            if ($ancestor) {
                $ioQuery->whereBetween('lft', [$ancestor->lft, $ancestor->rgt]);
            }
        }

        $descriptions = (clone $ioQuery)->count();
        $authorities = DB::table('actor')->count();
        $taxonomies = DB::table('taxonomy')->count();
        $accessions = DB::table('accession')->count();
        $physicalObjects = DB::table('physical_object')->count();
        $digitalObjects = DB::table('digital_object')->count();
        $repositories = DB::table('repository')->count();

        $estBytes = $descriptions * 5_000 + $digitalObjects * 250_000;
        $estMb = $estBytes / 1048576;
        $estSize = $estMb >= 1024 ? round($estMb / 1024, 1) . ' GB' : round($estMb, 1) . ' MB';
        $estDuration = max(1, (int) round($descriptions / 200 + $digitalObjects / 100));

        return response()->json([
            'descriptions' => $descriptions,
            'authorities' => $authorities,
            'taxonomies' => $taxonomies,
            'accessions' => $accessions,
            'physical_objects' => $physicalObjects,
            'repositories' => $repositories,
            'digital_objects' => ['count' => $digitalObjects],
            'estimated_package_size' => $estSize,
            'estimated_duration_minutes' => $estDuration,
        ]);
    }

    public function apiFondsSearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        if (strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $culture = app()->getLocale();
        $results = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', $culture);
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.parent_id', 1) // top-level fonds/collections
            ->where(function ($w) use ($q) {
                $w->where('ioi.title', 'LIKE', '%' . $q . '%')
                  ->orWhere('io.identifier', 'LIKE', '%' . $q . '%');
            })
            ->select(['ioi.title', 'io.identifier', 'slug.slug'])
            ->limit(15)
            ->get();

        return response()->json(['results' => $results]);
    }

    public function apiDelete(Request $request): JsonResponse
    {
        $id = (int) $request->input('id');
        if (!$id || !Schema::hasTable('portable_export')) {
            return response()->json(['success' => false, 'error' => 'Not found'], 404);
        }
        $row = DB::table('portable_export')->where('id', $id)->first();
        if ($row && !empty($row->output_path) && file_exists($row->output_path)) {
            @unlink($row->output_path);
        }
        DB::table('portable_export')->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    public function apiToken(Request $request): JsonResponse
    {
        $id = (int) $request->input('id');
        if (!$id) {
            return response()->json(['success' => false, 'error' => 'Missing id'], 400);
        }
        $token = bin2hex(random_bytes(16));
        $expiresHours = (int) $request->input('expires_hours', 168);
        $maxDownloads = $request->input('max_downloads');

        if (Schema::hasTable('portable_export_share_token')) {
            DB::table('portable_export_share_token')->insert([
                'export_id' => $id,
                'token' => $token,
                'expires_at' => now()->addHours($expiresHours),
                'max_downloads' => $maxDownloads ?: null,
                'download_count' => 0,
                'created_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'download_url' => url('/portable-export/share/' . $token),
        ]);
    }
}
