<?php

/**
 * FindingAidActionsController - ID-based POST upload + DELETE endpoints.
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
 *
 * Migrated from PSIS atom-ahg-plugins/ahgInformationObjectManagePlugin/lib/Action/
 * informationObjectFindingAidUpload/Delete (issue #742).
 *
 * Splits the historically-bundled upload/delete pair into two REST-style
 * endpoints keyed by IO id (not slug). The slug-keyed form-flow surface
 * remains in FindingAidController for the existing AtoM-parity UI; this
 * controller is the JSON-friendly twin for integrations + the tree view's
 * action buttons.
 *
 * Lock note: this controller is sibling to FindingAidController, sharing no
 * state with the show.blade.php render path. Endpoints are reachable from
 * the new tree-view page + the new modifications page + external integrators,
 * never from the show page itself.
 */

namespace AhgInformationObjectManage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FindingAidActionsController extends Controller
{
    /**
     * POST /informationobject/{id}/finding-aid
     *
     * Multipart: file=<pdf|rtf>
     * Returns JSON { ok: true, filename: ... } on success, or
     * { ok: false, error: ... }.
     */
    public function upload(Request $request, int $id): JsonResponse
    {
        $io = $this->getIO($id);
        if (!$io) {
            return response()->json(['ok' => false, 'error' => 'Information object not found'], 404);
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf,rtf|max:20480',
        ]);

        $file = $request->file('file');
        $filename = 'finding-aid-' . $io->id . '.' . $file->getClientOriginalExtension();

        $downloadsDir = public_path('downloads');
        if (!is_dir($downloadsDir)) {
            mkdir($downloadsDir, 0755, true);
        }

        $file->move($downloadsDir, $filename);

        \AhgCore\Support\AuditLog::captureMutation((int) $io->id, 'information_object', 'finding_aid_upload', [
            'data' => ['filename' => $filename, 'mime' => $file->getClientMimeType(), 'size' => $file->getSize() ?? null],
        ]);

        return response()->json([
            'ok' => true,
            'filename' => $filename,
            'url' => url('/downloads/' . $filename),
        ]);
    }

    /**
     * DELETE /informationobject/{id}/finding-aid
     *
     * Removes any finding-aid-{id}.{pdf,xml,html,rtf} from public/downloads
     * and clears the `findingAidStatus` property on the IO.
     */
    public function destroy(int $id): JsonResponse
    {
        $io = $this->getIO($id);
        if (!$io) {
            return response()->json(['ok' => false, 'error' => 'Information object not found'], 404);
        }

        $downloadsDir = public_path('downloads');
        $deletedFiles = [];
        foreach (['pdf', 'xml', 'html', 'rtf'] as $ext) {
            $path = $downloadsDir . '/finding-aid-' . $io->id . '.' . $ext;
            if (file_exists($path)) {
                @unlink($path);
                $deletedFiles[] = basename($path);
            }
        }

        // Clear findingAidStatus property
        $propIds = DB::table('property')
            ->where('object_id', $io->id)
            ->where('name', 'findingAidStatus')
            ->pluck('id');
        if ($propIds->isNotEmpty()) {
            DB::table('property_i18n')->whereIn('id', $propIds)->delete();
            DB::table('property')->whereIn('id', $propIds)->delete();
        }

        if (empty($deletedFiles)) {
            return response()->json([
                'ok' => false,
                'error' => 'No finding aid exists for this description.',
            ], 404);
        }

        \AhgCore\Support\AuditLog::captureMutation((int) $io->id, 'information_object', 'finding_aid_delete', [
            'data' => ['files' => $deletedFiles],
        ]);

        return response()->json([
            'ok' => true,
            'deleted' => $deletedFiles,
        ]);
    }

    private function getIO(int $id): ?object
    {
        return DB::table('information_object')
            ->where('id', $id)
            ->select('id')
            ->first();
    }
}
