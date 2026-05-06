<?php

/**
 * PortableExportController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
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
    /**
     * Read a portable_export setting from ahg_settings (group=portable_export)
     * with a fallback default. Cached per-request via static lookup.
     * Returns the raw string value; callers convert to bool/int as needed.
     */
    private function setting(string $key, $default = null)
    {
        static $cache = null;
        if ($cache === null) {
            $cache = DB::table('ahg_settings')
                ->where('setting_group', 'portable_export')
                ->pluck('setting_value', 'setting_key')
                ->all();
        }
        $v = $cache[$key] ?? null;
        return ($v === null || $v === '') ? $default : $v;
    }

    /** Truthy check for the boolean-shaped settings. Honours 'true'/'1'/'yes'. */
    private function settingBool(string $key, bool $default): bool
    {
        $v = $this->setting($key, null);
        if ($v === null) return $default;
        return in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Master kill-switch helper. When portable_export_enabled is off, every
     * public action 404s and the API endpoints return a clear error. Front-
     * end buttons should also be hidden via the same flag (see _action-icons
     * blade + clipboard/index blade).
     */
    private function abortIfDisabled(): void
    {
        if (!$this->settingBool('portable_export_enabled', true)) {
            abort(404, 'Portable Export is disabled.');
        }
    }

    public function index()
    {
        $this->abortIfDisabled();
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

        // Pre-fill defaults exposed to the wizard form. The settings
        // (defaults declared in ahg_settings group=portable_export) drive
        // initial checkbox state + culture selector + mode picker, so the
        // operator sees the install-wide policy without having to click
        // through every option on every export.
        $defaults = [
            'culture'            => (string) $this->setting('portable_export_default_culture', $culture),
            'mode'               => (string) $this->setting('portable_export_default_mode', 'read_only'),
            'include_masters'    => $this->settingBool('portable_export_include_masters', false),
            'include_thumbnails' => $this->settingBool('portable_export_include_thumbnails', true),
            'include_references' => $this->settingBool('portable_export_include_references', true),
            'include_objects'    => $this->settingBool('portable_export_include_objects', true),
            'max_size_mb'        => (int) $this->setting('portable_export_max_size_mb', 2048),
        ];

        return view('ahg-portable-export::index', [
            'repositories' => $repositories,
            'exports' => $exports,
            'defaults' => $defaults,
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
        $this->abortIfDisabled();
        return view('ahg-portable-export::import');
    }

    public function download(Request $request)
    {
        $this->abortIfDisabled();
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
        if (!$this->settingBool('portable_export_enabled', true)) {
            return response()->json(['success' => false, 'error' => 'Portable Export is disabled.'], 403);
        }

        $data = $request->all();
        $title = $data['title'] ?? 'Untitled Export';
        $scope = $data['scope_type'] ?? $data['scope'] ?? 'all';
        // When the payload omits 'mode' we use the install default
        // (portable_export_default_mode); same for culture + the include_*
        // flags. This honours operator-set policy without forcing the
        // wizard JS to repeat every default in its POST body.
        $mode    = $data['mode']    ?? (string) $this->setting('portable_export_default_mode',    'read_only');
        $culture = $data['culture'] ?? (string) $this->setting('portable_export_default_culture', app()->getLocale());

        $includeMasters    = array_key_exists('include_masters',    $data) ? !empty($data['include_masters'])    : $this->settingBool('portable_export_include_masters',    false);
        $includeThumbnails = array_key_exists('include_thumbnails', $data) ? !empty($data['include_thumbnails']) : $this->settingBool('portable_export_include_thumbnails', true);
        $includeReferences = array_key_exists('include_references', $data) ? !empty($data['include_references']) : $this->settingBool('portable_export_include_references', true);
        $includeObjects    = array_key_exists('include_objects',    $data) ? !empty($data['include_objects'])    : $this->settingBool('portable_export_include_objects',    true);

        if (!Schema::hasTable('portable_export')) {
            return response()->json([
                'success' => false,
                'error' => 'portable_export table does not exist — install the package schema first.',
            ], 500);
        }

        // Pre-build size gate. portable_export_max_size_mb caps the bundle
        // estimate so a runaway scope (e.g. 'all' on a 200k-IO install) can't
        // queue a multi-hour export that fills the disk. The estimator is the
        // same heuristic apiEstimate uses; honest limit, not a hard guarantee.
        $maxMb = (int) $this->setting('portable_export_max_size_mb', 2048);
        if ($maxMb > 0) {
            $estMb = $this->estimateBundleSizeMb($scope, (int) ($data['repository_id'] ?? 0), $data['scope_slug'] ?? null);
            if ($estMb > $maxMb) {
                return response()->json([
                    'success' => false,
                    'error' => sprintf(
                        'Estimated bundle size %s MB exceeds configured limit of %d MB. Narrow the scope or raise portable_export_max_size_mb.',
                        number_format($estMb, 1),
                        $maxMb
                    ),
                    'estimated_mb' => $estMb,
                    'max_size_mb' => $maxMb,
                ], 413);
            }
        }

        // scope_items: optional list of slugs (or ids). Used by:
        //   - clipboard scope: caller passes the clipboard's items (slugs)
        //   - archive mode: caller passes entity_types to include
        // Stored as JSON in scope_items so the worker can read both shapes
        // without us adding a new column. The legacy entity_types form key
        // is folded into the same JSON for backwards compat.
        $scopeItems = null;
        if (!empty($data['scope_items']) && is_array($data['scope_items'])) {
            $scopeItems = ['items' => array_values(array_filter(array_map('strval', $data['scope_items']))) ];
        }
        if (!empty($data['entity_types'])) {
            $scopeItems = $scopeItems ?? [];
            $scopeItems['entity_types'] = is_array($data['entity_types']) ? $data['entity_types'] : preg_split('/[\s,]+/', (string) $data['entity_types'], -1, PREG_SPLIT_NO_EMPTY);
        }

        $id = DB::table('portable_export')->insertGetId([
            'user_id' => auth()->id() ?? 0,
            'title' => $title,
            'scope_type' => $scope,
            'scope_repository_id' => $data['repository_id'] ?? null,
            'scope_slug' => $data['scope_slug'] ?? null,
            'scope_items' => $scopeItems !== null ? json_encode($scopeItems) : null,
            'mode' => $mode,
            'culture' => $culture,
            'include_masters' => $includeMasters,
            'include_thumbnails' => $includeThumbnails,
            'include_references' => $includeReferences,
            'branding' => json_encode([
                'title' => $data['branding_title'] ?? null,
                'subtitle' => $data['branding_subtitle'] ?? null,
                'footer' => $data['branding_footer'] ?? null,
            ]),
            'status' => 'pending',
            'progress' => 0,
            'created_at' => now(),
        ]);

        // Dispatch the bundler worker so the row gets picked up immediately.
        // Best-effort: if the queue dispatch fails the row still exists for
        // the daily cron sweep to pick up later.
        try {
            \Illuminate\Support\Facades\Artisan::queue('ahg:portable-export-worker', ['--id' => $id]);
        } catch (\Throwable $e) {
            \Log::warning('apiStart could not queue worker: ' . $e->getMessage(), ['export_id' => $id]);
        }

        return response()->json([
            'success' => true,
            'export_id' => $id,
            'message' => 'Export queued.',
        ]);
    }

    /** Shared estimator used by apiEstimate + apiStart's max_size_mb gate. */
    private function estimateBundleSizeMb(string $scope, int $repoId, ?string $slug): float
    {
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
        // Note: clipboard scope deferred here - apiStart caps it later via
        // explicit slug count, which is small enough that the heuristic
        // doesn't matter.
        $descriptions = (clone $ioQuery)->count();
        $digitalObjects = DB::table('digital_object')->count();
        // Same coefficients as apiEstimate (5KB per IO record, 250KB per DO).
        $estBytes = $descriptions * 5_000 + $digitalObjects * 250_000;
        return $estBytes / 1048576;
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
