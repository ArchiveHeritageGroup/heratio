<?php

/**
 * ResearchWorkspaceFileController - Controller for Heratio
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



namespace AhgResearch\Controllers;

use App\Http\Controllers\Controller;
use AhgResearch\Controllers\Concerns\ResearchControllerHelpers;
use AhgResearch\Concerns\LogsResearchActivity;
use AhgResearch\Services\ResearchQuotaService;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ResearchWorkspaceFileController - researcher workspace file uploads (#1325).
 *
 * Lets a researcher upload, list, download and delete files attached to a
 * research workspace they own or are an accepted member of. All writes are
 * gated by ResearchQuotaService (storage on upload, downloads on fetch) and
 * leave a human-action trail via the LogsResearchActivity trait.
 *
 * Files are stored under config('heratio.storage_path').'/research/workspace/'
 * <workspaceId>/ with sanitised, de-duped names and a sha256 checksum. The DB
 * facade is used throughout (no Eloquent) to match the package style.
 */
class ResearchWorkspaceFileController extends Controller
{
    use ResearchControllerHelpers;
    use LogsResearchActivity;

    protected ResearchService $service;

    protected ResearchQuotaService $quota;

    public function __construct(ResearchService $service, ResearchQuotaService $quota)
    {
        $this->service = $service;
        $this->quota = $quota;
    }

    /**
     * List the files attached to a workspace the researcher can access.
     */
    public function index(int $workspaceId)
    {
        $researcher = $this->getResearcherOrRedirect();
        if (! is_object($researcher) || ! isset($researcher->id)) {
            return $researcher; // redirect response
        }

        if (! $this->canAccess($workspaceId, (int) $researcher->id)) {
            abort(403);
        }

        $workspace = DB::table('research_workspace')->where('id', $workspaceId)->first();
        if (! $workspace) {
            abort(404);
        }

        $files = DB::table('research_workspace_file')
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();

        $storage = $this->quota->checkStorage((int) $researcher->id, 0);
        $isOwner = (int) $workspace->owner_id === (int) $researcher->id;

        return view('research::research.workspace-files', array_merge(
            $this->getSidebarData('workspaces'),
            compact('researcher', 'workspace', 'workspaceId', 'files', 'storage', 'isOwner')
        ));
    }

    /**
     * Handle a file upload into the workspace storage directory.
     */
    public function store(Request $request, int $workspaceId)
    {
        $researcher = $this->getResearcherOrRedirect();
        if (! is_object($researcher) || ! isset($researcher->id)) {
            return $researcher; // redirect response
        }

        if (! $this->canAccess($workspaceId, (int) $researcher->id)) {
            abort(403);
        }

        $request->validate([
            'file' => 'required|file',
        ]);

        $upload = $request->file('file');
        $size = (int) $upload->getSize();

        $check = $this->quota->checkStorage((int) $researcher->id, $size);
        if (! $check['allowed']) {
            return redirect()->route('research.workspace.files', $workspaceId)
                ->with('error', $check['message'] ?? 'Storage quota exceeded.');
        }

        // Resolve + create the workspace storage directory.
        $dir = rtrim((string) config('heratio.storage_path'), '/')
            . '/research/workspace/' . $workspaceId . '/';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        // Sanitise and de-dupe the filename.
        $original = $upload->getClientOriginalName();
        $safe = preg_replace('/[^A-Za-z0-9_.-]/', '_', $original);
        if ($safe === '' || $safe === null) {
            $safe = 'file_' . date('YmdHis');
        }
        $fileName = $safe;
        $dest = $dir . $fileName;
        if (file_exists($dest)) {
            $ext = pathinfo($safe, PATHINFO_EXTENSION);
            $base = $ext !== '' ? substr($safe, 0, -(strlen($ext) + 1)) : $safe;
            $i = 1;
            do {
                $fileName = $base . '_' . $i . ($ext !== '' ? '.' . $ext : '');
                $dest = $dir . $fileName;
                $i++;
            } while (file_exists($dest));
        }

        $mime = $upload->getClientMimeType();
        $upload->move($dir, $fileName);

        $checksum = @hash_file('sha256', $dest) ?: null;

        $id = DB::table('research_workspace_file')->insertGetId([
            'workspace_id'  => $workspaceId,
            'project_id'    => null,
            'researcher_id' => (int) $researcher->id,
            'file_name'     => $fileName,
            'file_path'     => $dest,
            'file_size'     => $size,
            'mime_type'     => $mime,
            'checksum'      => $checksum,
            'checksum_type' => 'sha256',
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        $this->logResearchActivity('create', 'workspace_file', (int) $id, $fileName, ['workspace_id' => $workspaceId], null);

        $redirect = redirect()->route('research.workspace.files', $workspaceId)
            ->with('success', 'File uploaded.');
        if (! empty($check['warn']) && ! empty($check['message'])) {
            $redirect->with('warning', $check['message']);
        }

        return $redirect;
    }

    /**
     * Stream a workspace file back to the researcher (download-quota gated).
     */
    public function download(int $workspaceId, int $fileId)
    {
        $researcher = $this->getResearcherOrRedirect();
        if (! is_object($researcher) || ! isset($researcher->id)) {
            return $researcher; // redirect response
        }

        if (! $this->canAccess($workspaceId, (int) $researcher->id)) {
            abort(403);
        }

        $file = DB::table('research_workspace_file')
            ->where('id', $fileId)
            ->where('workspace_id', $workspaceId)
            ->first();
        if (! $file) {
            abort(404);
        }

        if (! is_string($file->file_path) || ! file_exists($file->file_path)) {
            return redirect()->route('research.workspace.files', $workspaceId)
                ->with('error', 'File is missing from storage.');
        }

        $check = $this->quota->checkDownload((int) $researcher->id);
        if (! $check['allowed']) {
            return redirect()->route('research.workspace.files', $workspaceId)
                ->with('error', $check['message'] ?? 'Download quota exceeded.');
        }

        $this->quota->logDownload((int) $researcher->id, null, $fileId, $file->file_name);

        return response()->download($file->file_path, $file->file_name);
    }

    /**
     * Delete a workspace file (owner only) from disk and the table.
     */
    public function destroy(int $workspaceId, int $fileId)
    {
        $researcher = $this->getResearcherOrRedirect();
        if (! is_object($researcher) || ! isset($researcher->id)) {
            return $researcher; // redirect response
        }

        if (! $this->isOwner($workspaceId, (int) $researcher->id)) {
            abort(403);
        }

        $file = DB::table('research_workspace_file')
            ->where('id', $fileId)
            ->where('workspace_id', $workspaceId)
            ->first();
        if (! $file) {
            abort(404);
        }

        if (is_string($file->file_path) && file_exists($file->file_path)) {
            @unlink($file->file_path);
        }

        DB::table('research_workspace_file')
            ->where('id', $fileId)
            ->where('workspace_id', $workspaceId)
            ->delete();

        $this->logResearchActivity('delete', 'workspace_file', $fileId, $file->file_name, ['workspace_id' => $workspaceId], null);

        return redirect()->route('research.workspace.files', $workspaceId)
            ->with('success', 'File deleted.');
    }

    // --- internals --------------------------------------------------------

    /**
     * The researcher owns the workspace OR is an accepted member of it.
     */
    private function canAccess(int $workspaceId, int $researcherId): bool
    {
        if ($this->isOwner($workspaceId, $researcherId)) {
            return true;
        }

        return DB::table('research_workspace_member')
            ->where('workspace_id', $workspaceId)
            ->where('researcher_id', $researcherId)
            ->where('status', 'accepted')
            ->exists();
    }

    /**
     * The researcher is the workspace owner.
     */
    private function isOwner(int $workspaceId, int $researcherId): bool
    {
        return DB::table('research_workspace')
            ->where('id', $workspaceId)
            ->where('owner_id', $researcherId)
            ->exists();
    }
}
