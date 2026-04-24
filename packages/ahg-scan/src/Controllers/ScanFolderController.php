<?php

/**
 * ScanFolderController — Heratio ahg-scan
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgScan\Controllers;

use AhgScan\Console\ScanWatchCommand;
use AhgScan\Services\WatchedFolderService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ScanFolderController extends Controller
{
    public function __construct(protected WatchedFolderService $service) {}

    public function index()
    {
        $folders = $this->service->list();
        return view('ahg-scan::admin.scan.folders.index', compact('folders'));
    }

    public function create()
    {
        $folder = (object) [
            'id' => null, 'code' => '', 'label' => '', 'path' => '',
            'layout' => 'path', 'enabled' => 1,
            'disposition_success' => 'move', 'disposition_failure' => 'quarantine',
            'min_quiet_seconds' => config('heratio.scan.min_quiet_seconds', 10),
            'sector' => 'archive', 'standard' => 'isadg',
            'parent_id' => null, 'repository_id' => null,
            'auto_commit' => 1,
            'derivative_thumbnails' => 1, 'derivative_reference' => 1,
            'process_virus_scan' => 1, 'process_ocr' => 0,
        ];
        $parents = $this->parentOptions();
        $repositories = $this->repositoryOptions();
        return view('ahg-scan::admin.scan.folders.edit', compact('folder', 'parents', 'repositories'));
    }

    public function store(Request $request)
    {
        $data = $this->validateInput($request);
        $userId = (int) ($request->user()->id ?? 0);
        $id = $this->service->create($data, $userId);
        return redirect()->route('scan.folders.index')->with('notice', 'Watched folder created.');
    }

    public function edit(int $id)
    {
        $folder = DB::table('scan_folder as sf')
            ->leftJoin('ingest_session as s', 'sf.ingest_session_id', '=', 's.id')
            ->where('sf.id', $id)
            ->select(
                'sf.*',
                's.sector', 's.standard', 's.parent_id', 's.repository_id',
                's.auto_commit', 's.derivative_thumbnails', 's.derivative_reference',
                's.process_virus_scan', 's.process_ocr'
            )
            ->first();
        abort_unless($folder, 404);
        $parents = $this->parentOptions();
        $repositories = $this->repositoryOptions();
        return view('ahg-scan::admin.scan.folders.edit', compact('folder', 'parents', 'repositories'));
    }

    public function update(Request $request, int $id)
    {
        $data = $this->validateInput($request, $id);
        $this->service->update($id, $data);
        return redirect()->route('scan.folders.index')->with('notice', 'Watched folder updated.');
    }

    public function destroy(int $id)
    {
        $this->service->delete($id);
        return redirect()->route('scan.folders.index')->with('notice', 'Watched folder removed.');
    }

    public function runNow(int $id)
    {
        $folder = $this->service->find($id);
        abort_unless($folder, 404);
        Artisan::call('ahg:scan-watch', ['--once' => true, '--folder' => $folder->code]);
        return redirect()->route('scan.folders.index')->with('notice', "Scan run for '{$folder->code}' completed.");
    }

    protected function validateInput(Request $request, ?int $existingId = null): array
    {
        $rules = [
            'code' => 'required|string|max:64|regex:/^[a-z0-9][a-z0-9_-]*$/',
            'label' => 'required|string|max:255',
            'path' => 'required|string|max:1024',
            'layout' => 'required|in:path,flat-sidecar',
            'sector' => 'required|in:archive,library,gallery,museum',
            'standard' => 'required|string|max:32',
            'parent_id' => 'nullable|integer|min:1',
            'repository_id' => 'nullable|integer|min:1',
            'min_quiet_seconds' => 'nullable|integer|min:1|max:3600',
            'disposition_success' => 'required|in:move,delete,leave',
            'disposition_failure' => 'required|in:quarantine,leave',
            'enabled' => 'nullable|boolean',
            'auto_commit' => 'nullable|boolean',
            'spectrum_auto_enter' => 'nullable|boolean',
            'output_create_authorities' => 'nullable|boolean',
        ];
        if (!$existingId) {
            $rules['code'] .= '|unique:scan_folder,code';
        }
        $data = $request->validate($rules);

        // normalise boolean-ish checkboxes
        foreach (['enabled', 'auto_commit', 'spectrum_auto_enter', 'output_create_authorities'] as $k) {
            $data[$k] = (int) ($data[$k] ?? $request->input($k) ? 1 : 0);
        }

        return $data;
    }

    protected function parentOptions(): array
    {
        return DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', '=', 'en');
            })
            ->where('io.id', '>', 1)
            ->select('io.id', 'io.identifier', 'i18n.title')
            ->orderBy('i18n.title')
            ->limit(500)
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'label' => trim(($r->identifier ? "[{$r->identifier}] " : '') . ($r->title ?: 'Untitled')),
            ])
            ->toArray();
    }

    protected function repositoryOptions(): array
    {
        return DB::table('repository as r')
            ->leftJoin('actor_i18n as a', function ($j) {
                $j->on('a.id', '=', 'r.id')->where('a.culture', '=', 'en');
            })
            ->select('r.id', 'a.authorized_form_of_name as name')
            ->orderBy('a.authorized_form_of_name')
            ->get()
            ->map(fn($r) => ['id' => $r->id, 'label' => $r->name ?: "Repo #{$r->id}"])
            ->toArray();
    }
}
