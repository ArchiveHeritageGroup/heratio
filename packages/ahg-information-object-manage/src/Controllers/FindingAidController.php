<?php

/**
 * FindingAidController - Controller for Heratio
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

use App\Http\Controllers\Controller;
use App\Jobs\FindingAidJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FindingAidController extends Controller
{
    /**
     * Generate a finding aid (PDF) for an information object.
     * Migrated from AtoM InformationObjectGenerateFindingAidAction.
     */
    public function generate(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        // Remove existing finding aid if present (regenerate)
        $existingPath = $this->getFindingAidPath($io->id);
        if ($existingPath && file_exists($existingPath)) {
            @unlink($existingPath);
            // Also remove other formats
            $dir = dirname($existingPath);
            foreach (['xml', 'pdf', 'html', 'rtf'] as $ext) {
                $path = $dir . '/finding-aid-' . $io->id . '.' . $ext;
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
        }

        // Dispatch the finding aid generation job
        FindingAidJob::dispatch($io->id);

        \AhgCore\Support\AuditLog::captureMutation((int) $io->id, 'information_object', 'finding_aid_generate', [
            'data' => ['title' => $io->title ?? null, 'slug' => $slug, 'regenerated' => !empty($existingPath)],
        ]);

        return redirect()->route('informationobject.show', $slug)
            ->with('success', 'Finding aid generation queued for: ' . ($io->title ?? $slug));
    }

    /**
     * Show the finding aid upload form.
     */
    public function uploadForm(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        // Get repository for sidebar context menu (matching AtoM 2col layout)
        $repository = null;
        if ($io->repository_id ?? null) {
            $repository = \Illuminate\Support\Facades\DB::table('repository')
                ->join('slug', 'repository.id', '=', 'slug.object_id')
                ->leftJoin('actor_i18n', function ($j) {
                    $j->on('repository.id', '=', 'actor_i18n.id')
                      ->where('actor_i18n.culture', '=', app()->getLocale());
                })
                ->where('repository.id', $io->repository_id)
                ->select('repository.id', 'actor_i18n.authorized_form_of_name as name', 'slug.slug')
                ->first();
        }

        return view('ahg-io-manage::findingaid.upload', [
            'io' => $io,
            'repository' => $repository,
        ]);
    }

    /**
     * Process finding aid upload.
     * Migrated from AtoM InformationObjectUploadFindingAidAction.
     */
    public function upload(Request $request, string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf,rtf|max:20480',
        ]);

        $file = $request->file('file');
        $filename = 'finding-aid-' . $io->id . '.' . $file->getClientOriginalExtension();

        // Store in downloads directory (matches AtoM convention)
        $downloadsDir = public_path('downloads');
        if (!is_dir($downloadsDir)) {
            mkdir($downloadsDir, 0755, true);
        }

        $file->move($downloadsDir, $filename);

        \AhgCore\Support\AuditLog::captureMutation((int) $io->id, 'information_object', 'finding_aid_upload', [
            'data' => ['filename' => $filename, 'mime' => $file->getClientMimeType(), 'size' => $file->getSize() ?? null],
        ]);

        return redirect()->route('informationobject.show', $slug)
            ->with('success', 'Finding aid uploaded successfully.');
    }

    /**
     * Download an existing finding aid.
     */
    public function download(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        // settings.publicFindingAid (#80): when off, only authenticated
        // operators can download. When on (default), anonymous downloads
        // are allowed - matches AtoM's anonymous EAD/PDF surface.
        if (!\AhgCore\Support\GlobalSettings::publicFindingAid() && !auth()->check()) {
            abort(403);
        }

        $path = $this->getFindingAidPath($io->id);
        if (!$path || !file_exists($path)) {
            return redirect()->route('informationobject.show', $slug)
                ->with('error', 'No finding aid exists for this description.');
        }

        return response()->download($path);
    }

    /**
     * Delete an existing finding aid.
     * Migrated from AtoM InformationObjectDeleteFindingAidAction.
     */
    public function delete(Request $request, string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $downloadsDir = public_path('downloads');
        $deleted = false;
        foreach (['pdf', 'xml', 'html', 'rtf'] as $ext) {
            $path = $downloadsDir . '/finding-aid-' . $io->id . '.' . $ext;
            if (file_exists($path)) {
                @unlink($path);
                $deleted = true;
            }
        }

        // Clear the findingAidStatus property in the database
        $propIds = DB::table('property')
            ->where('object_id', $io->id)
            ->where('name', 'findingAidStatus')
            ->pluck('id');
        if ($propIds->isNotEmpty()) {
            DB::table('property_i18n')->whereIn('id', $propIds)->delete();
            DB::table('property')->whereIn('id', $propIds)->delete();
        }

        if ($deleted) {
            \AhgCore\Support\AuditLog::captureMutation((int) $io->id, 'information_object', 'finding_aid_delete', [
                'data' => ['slug' => $slug, 'title' => $io->title ?? null],
            ]);
            return redirect()->route('informationobject.show', $slug)
                ->with('success', 'Finding aid deleted successfully.');
        }

        return redirect()->route('informationobject.show', $slug)
            ->with('error', 'No finding aid exists for this description.');
    }

    private function getIO(string $slug): ?object
    {
        $slugRow = DB::table('slug')->where('slug', $slug)->first();
        if (!$slugRow) {
            return null;
        }

        $culture = app()->getLocale();

        return DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', $culture);
            })
            ->join('slug as s', 's.object_id', '=', 'io.id')
            ->where('io.id', $slugRow->object_id)
            ->select('io.id', 'io.repository_id', 'i18n.title', 's.slug')
            ->first();
    }

    private function getFindingAidPath(int $objectId): ?string
    {
        $downloadsDir = public_path('downloads');
        foreach (['pdf', 'rtf'] as $ext) {
            $path = $downloadsDir . '/finding-aid-' . $objectId . '.' . $ext;
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
