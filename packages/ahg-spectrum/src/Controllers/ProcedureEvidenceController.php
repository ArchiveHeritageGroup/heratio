<?php

/*
 * ProcedureEvidenceController - upload / link / download / delete documented
 * evidence for any Collections Procedure flow (#1460 Phase 1).
 *
 * Copyright (C) 2026 Johan Pieterse - The Archive Heritage Group (Pty) Ltd.
 * Part of Heratio. Licensed under the GNU AGPL v3.
 */

namespace AhgSpectrum\Controllers;

use AhgSpectrum\Services\ProcedureEvidenceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcedureEvidenceController extends Controller
{
    public function __construct(private ProcedureEvidenceService $service) {}

    public function upload(Request $request)
    {
        $data = $request->validate([
            'slug' => 'required|string',
            'procedure_type' => 'required|string|max:50',
            'procedure_id' => 'nullable|integer',
            'file' => 'required|file|max:51200', // 50 MB
            'category' => 'nullable|string|max:40',
            'description' => 'nullable|string',
            'evidence_date' => 'nullable|date',
        ]);

        $objectId = $this->objectIdFromSlug($data['slug']);
        if (! $objectId) {
            return back()->with('error', 'Object not found.');
        }

        $this->service->storeUpload(
            $objectId,
            $data['procedure_type'],
            $data['procedure_id'] ?? null,
            $request->file('file'),
            $data['category'] ?? null,
            $data['description'] ?? null,
            $data['evidence_date'] ?? null,
            auth()->id()
        );

        return $this->backToProcedure($data['slug'], $data['procedure_type'], 'Evidence uploaded.');
    }

    public function link(Request $request)
    {
        $data = $request->validate([
            'slug' => 'required|string',
            'procedure_type' => 'required|string|max:50',
            'procedure_id' => 'nullable|integer',
            'digital_object_id' => 'required|integer',
            'category' => 'nullable|string|max:40',
            'description' => 'nullable|string',
            'evidence_date' => 'nullable|date',
        ]);

        $objectId = $this->objectIdFromSlug($data['slug']);
        if (! $objectId) {
            return back()->with('error', 'Object not found.');
        }

        $id = $this->service->storeLink(
            $objectId,
            $data['procedure_type'],
            $data['procedure_id'] ?? null,
            (int) $data['digital_object_id'],
            $data['category'] ?? null,
            $data['description'] ?? null,
            $data['evidence_date'] ?? null,
            auth()->id()
        );

        if ($id === null) {
            return back()->with('error', 'That digital object does not exist.');
        }

        return $this->backToProcedure($data['slug'], $data['procedure_type'], 'Evidence linked.');
    }

    public function download(int $id)
    {
        $row = $this->service->find($id);
        if (! $row) {
            abort(404);
        }

        if (($row->evidence_kind ?? '') === 'link' && ! empty($row->digital_object_id)) {
            $path = DB::table('digital_object')->where('id', $row->digital_object_id)->value('path');
            $name = DB::table('digital_object')->where('id', $row->digital_object_id)->value('name');
            if ($path) {
                return redirect(rtrim((string) $path, '/').'/'.$name);
            }
            abort(404);
        }

        $abs = $this->service->resolveAbsolutePath($row);
        if (! $abs) {
            abort(404);
        }

        return response()->download($abs, $row->original_name ?: basename($abs));
    }

    public function destroy(Request $request, int $id)
    {
        $row = $this->service->find($id);
        $ok = $this->service->delete($id, auth()->id());
        if (! $ok) {
            return back()->with('error', 'Evidence not found.');
        }

        $slug = $request->input('slug');
        if ($slug && $row) {
            return $this->backToProcedure($slug, (string) $row->procedure_type, 'Evidence removed.');
        }

        return back()->with('success', 'Evidence removed.');
    }

    private function objectIdFromSlug(string $slug): ?int
    {
        $id = DB::table('slug')->where('slug', $slug)->value('object_id');

        return $id ? (int) $id : null;
    }

    private function backToProcedure(string $slug, string $procedureType, string $message)
    {
        return redirect()
            ->route('ahgspectrum.workflow', ['slug' => $slug, 'procedure_type' => $procedureType])
            ->with('success', $message);
    }
}
