<?php

/**
 * PreservationController - Controller for Heratio
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



namespace AhgInformationObjectManage\Controllers;

use AhgInformationObjectManage\Services\PreservationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Migrated from /usr/share/nginx/archive/atom-ahg-plugins/ahgPreservationPlugin/
 */
class PreservationController extends Controller
{
    protected PreservationService $service;

    public function __construct()
    {
        $this->service = new PreservationService();
    }

    /**
     * Show preservation packages for an IO. Optionally renders inline detail
     * panels for ?view={id} (read-only) or ?edit={id} (editable form). The
     * detail view loads files (preservation_package_object) + audit events
     * (preservation_package_event) for that package.
     *
     * ?download={id} is reserved for the BagIt/zip export flow which is a
     * separate job (uses the OAIS packager); for now it short-circuits with
     * an info flash so users see the action acknowledged.
     */
    public function index(Request $request, string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $aips = $this->service->getAipsForObject($io->id);
        $premisObjects = $this->service->getPremisObjects($io->id);

        $viewPackage = null;     // populated when ?view={id}
        $editPackage = null;     // populated when ?edit={id}
        $packageFiles = collect();
        $packageEvents = collect();

        $viewId = (int) $request->query('view', 0);
        $editId = (int) $request->query('edit', 0);
        $downloadId = (int) $request->query('download', 0);

        if ($downloadId > 0) {
            // Real export is the OAIS packager's job - separate sub-issue.
            // For now surface a clear acknowledgement so the click registers.
            $pkg = $this->service->getPackage($downloadId);
            if ($pkg && $pkg->status === 'exported' && !empty($pkg->export_path) && is_file($pkg->export_path)) {
                return response()->download($pkg->export_path, basename($pkg->export_path));
            }
            return redirect()
                ->route('io.preservation', ['slug' => $slug])
                ->with('error', 'Package #' . $downloadId . ' has no exported file yet. Run the BagIt export job first.');
        }

        if ($viewId > 0) {
            $viewPackage = $this->service->getPackage($viewId);
            if ($viewPackage) {
                $packageFiles  = $this->service->getPackageFiles($viewId);
                $packageEvents = $this->service->getPackageEvents($viewId);
            }
        } elseif ($editId > 0) {
            $editPackage = $this->service->getPackage($editId);
            if ($editPackage) {
                $packageFiles  = $this->service->getPackageFiles($editId);
                $packageEvents = $this->service->getPackageEvents($editId);
            }
        }

        return view('ahg-io-manage::preservation.index', [
            'io'            => $io,
            'aips'          => $aips,
            'premisObjects' => $premisObjects,
            'viewPackage'   => $viewPackage,
            'editPackage'   => $editPackage,
            'packageFiles'  => $packageFiles,
            'packageEvents' => $packageEvents,
        ]);
    }

    /**
     * POST /preservation/{slug}/{id}/update — saves the edit form for a
     * single preservation_package row. Whitelisted to name / description /
     * status so users can't poke at uuid / type / checksums.
     */
    public function updatePackage(Request $request, string $slug, int $id)
    {
        $io = $this->getIO($slug);
        if (!$io) abort(404);

        // Sanity: confirm the package is actually linked to this IO so we
        // don't accept ?edit=999 from any URL slug.
        $belongs = DB::table('preservation_package_object as ppo')
            ->join('digital_object as do', 'do.id', '=', 'ppo.digital_object_id')
            ->where('ppo.package_id', $id)
            ->where('do.object_id', $io->id)
            ->exists();
        if (!$belongs) {
            return back()->with('error', 'Package #' . $id . ' is not linked to this record.');
        }

        $ok = $this->service->updatePackage($id, $request->only(['name', 'description', 'status']));

        // Audit-trail event so the change is visible in the package timeline.
        if ($ok) {
            try {
                DB::table('preservation_package_event')->insert([
                    'package_id'     => $id,
                    'event_type'     => 'update',
                    'event_outcome'  => 'success',
                    'event_detail'   => 'Package metadata updated via /preservation/' . $slug . ' edit form.',
                    'event_datetime' => now(),
                    'agent_type'     => 'user',
                    'agent_value'    => (string) (auth()->id() ?? 'anonymous'),
                ]);
            } catch (\Throwable $e) { /* table may not exist on minimal installs */ }
        }

        return redirect()
            ->route('io.preservation', ['slug' => $slug, 'view' => $id])
            ->with($ok ? 'success' : 'error', $ok ? 'Package updated.' : 'Nothing changed (no recognised fields supplied).');
    }

    /**
     * Create a preservation package from the index-page modal form.
     *
     * Persists a row to `preservation_package` and links every digital object
     * attached to the IO via `preservation_package_object`. The package starts
     * in `draft` status; downstream jobs (export, manifest generation) are not
     * triggered here - that's the OAIS packager's job. This handler only
     * accepts the create-and-record step the modal form is wired to.
     */
    public function createPackage(Request $request, string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $type = strtolower((string) $request->input('package_type', 'AIP'));
        if (!in_array($type, ['sip', 'aip', 'dip'], true)) {
            return back()->with('error', 'Invalid package type.');
        }

        $name = trim((string) $request->input('package_name', ''));
        if ($name === '') {
            $name = strtoupper($type) . ' for ' . ($io->title ?? $slug) . ' (' . now()->format('Y-m-d H:i') . ')';
        }

        $now = now();
        $packageId = DB::table('preservation_package')->insertGetId([
            'uuid'              => (string) Str::uuid(),
            'name'              => $name,
            'package_type'      => $type,
            'status'            => 'draft',
            'package_format'    => 'bagit',
            'manifest_algorithm'=> 'sha256',
            'object_count'      => 0,
            'total_size'        => 0,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        // Link every digital object on the IO to the new package.
        $dos = DB::table('digital_object')
            ->where('object_id', $io->id)
            ->get(['id', 'name', 'byte_size', 'mime_type']);

        $totalSize = 0; $count = 0;
        foreach ($dos as $do) {
            DB::table('preservation_package_object')->insert([
                'package_id'         => $packageId,
                'digital_object_id'  => (int) $do->id,
                'relative_path'      => 'data/' . ($do->name ?? ('do_' . $do->id)),
                'file_name'          => (string) ($do->name ?? ('do_' . $do->id)),
                'file_size'          => $do->byte_size ?? null,
                'mime_type'          => $do->mime_type ?? null,
                'object_role'        => 'payload',
                'sequence'           => ++$count,
                'added_at'           => $now,
            ]);
            $totalSize += (int) ($do->byte_size ?? 0);
        }

        // Roll up the totals so the index view's stats cards are accurate.
        DB::table('preservation_package')->where('id', $packageId)->update([
            'object_count' => $count,
            'total_size'   => $totalSize,
            'updated_at'   => now(),
        ]);

        // Audit-trail / PREMIS event hook would land here when ahg-audit-trail
        // gains a preservation channel; for now we log a single create event.
        try {
            DB::table('preservation_package_event')->insert([
                'package_id'     => $packageId,
                'event_type'     => 'creation',
                'event_outcome'  => 'success',
                'event_detail'   => 'Package created via /preservation/' . $slug . ' modal form.',
                'event_datetime' => $now,
                'agent_type'     => 'user',
                'agent_value'    => (string) (auth()->id() ?? 'anonymous'),
            ]);
        } catch (\Throwable $e) {
            // preservation_package_event may not exist on minimal installs; non-fatal.
        }

        return redirect()
            ->route('io.preservation', ['slug' => $slug])
            ->with('success', strtoupper($type) . ' package "' . $name . '" created with ' . $count . ' object' . ($count === 1 ? '' : 's') . '.');
    }

    /**
     * POST /preservation/{slug}/{id}/export — BagIt-export an existing
     * preservation_package row. Reuses the linked preservation_package_object
     * rows + their digital_object source files; writes a BagIt 1.0 zip to
     * storage/app/preservation/<uuid>.zip and stamps export_path + status.
     *
     * Belongs-to-this-IO check prevents cross-record export.
     */
    public function exportPackage(Request $request, string $slug, int $id)
    {
        $io = $this->getIO($slug);
        if (!$io) abort(404);

        $belongs = DB::table('preservation_package_object as ppo')
            ->join('digital_object as do', 'do.id', '=', 'ppo.digital_object_id')
            ->where('ppo.package_id', $id)
            ->where('do.object_id', $io->id)
            ->exists();
        if (!$belongs) {
            return back()->with('error', 'Package #' . $id . ' is not linked to this record.');
        }

        $result = $this->service->exportPackage($id);
        return redirect()
            ->route('io.preservation', ['slug' => $slug, 'view' => $id])
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Resolve IO from slug.
     */
    private function getIO(string $slug): ?object
    {
        $culture = app()->getLocale();

        return DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', $culture);
            })
            ->join('slug as s', 's.object_id', '=', 'io.id')
            ->where('s.slug', $slug)
            ->select('io.id', 'i18n.title', 's.slug')
            ->first();
    }
}
