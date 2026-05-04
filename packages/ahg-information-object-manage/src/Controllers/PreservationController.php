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
     * Show preservation packages for an IO.
     */
    public function index(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        // Get AIPs linked to this object
        $aips = $this->service->getAipsForObject($io->id);

        // Get PREMIS objects
        $premisObjects = $this->service->getPremisObjects($io->id);

        return view('ahg-io-manage::preservation.index', [
            'io'            => $io,
            'aips'          => $aips,
            'premisObjects' => $premisObjects,
        ]);
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
                'package_id'   => $packageId,
                'event_type'   => 'creation',
                'event_outcome'=> 'success',
                'event_detail' => 'Package created via /preservation/' . $slug . ' modal form.',
                'created_at'   => $now,
            ]);
        } catch (\Throwable $e) {
            // preservation_package_event may not exist on minimal installs; non-fatal.
        }

        return redirect()
            ->route('io.preservation', ['slug' => $slug])
            ->with('success', strtoupper($type) . ' package "' . $name . '" created with ' . $count . ' object' . ($count === 1 ? '' : 's') . '.');
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
