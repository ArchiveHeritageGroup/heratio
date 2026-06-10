<?php
/**
 * Heratio - C2PA provenance / content-credentials HTTP layer (issue #1201).
 *
 * Lists digitisation-provenance records for an information object, records a
 * new capture-provenance entry (Ed25519-signed C2PA manifest when possible),
 * and verifies a record's content credentials on view.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Controllers;

use AhgC2pa\Services\ProvenanceRecordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class ProvenanceController extends Controller
{
    public function __construct(private ProvenanceRecordService $service)
    {
    }

    /**
     * List provenance records for an information object.
     */
    public function index(int $informationObjectId): View
    {
        $io = $this->loadObject($informationObjectId);

        return view('ahg-c2pa::provenance.index', [
            'informationObjectId' => $informationObjectId,
            'object'              => $io,
            'records'             => $this->service->listForObject($informationObjectId),
            'capability'          => $this->service->capability(),
        ]);
    }

    /**
     * Show + verify a single provenance record's content credentials.
     */
    public function show(int $informationObjectId, int $provenanceId): View
    {
        $record = $this->service->find($provenanceId);
        $verification = $this->service->verifyRecord($provenanceId);

        $inferenceSteps = [];
        if ($record !== null && is_string($record->inference_steps) && $record->inference_steps !== '') {
            $decoded = json_decode($record->inference_steps, true);
            if (is_array($decoded)) {
                $inferenceSteps = $decoded;
            }
        }

        return view('ahg-c2pa::provenance.show', [
            'informationObjectId' => $informationObjectId,
            'object'              => $this->loadObject($informationObjectId),
            'record'              => $record,
            'verification'        => $verification,
            'inferenceSteps'      => $inferenceSteps,
            'capability'          => $this->service->capability(),
        ]);
    }

    /**
     * Display the "record a digitisation" form.
     */
    public function create(int $informationObjectId): View
    {
        return view('ahg-c2pa::provenance.create', [
            'informationObjectId' => $informationObjectId,
            'object'              => $this->loadObject($informationObjectId),
            'digitalObjects'      => $this->loadDigitalObjects($informationObjectId),
            'capability'          => $this->service->capability(),
        ]);
    }

    /**
     * Persist a new digitisation-provenance record and sign a C2PA manifest.
     */
    public function store(Request $request, int $informationObjectId): RedirectResponse
    {
        $validated = $request->validate([
            'digital_object_id' => ['nullable', 'integer', 'min:1'],
            'captured_by'       => ['nullable', 'string', 'max:255'],
            'captured_at'       => ['nullable', 'date'],
            'capture_device'    => ['nullable', 'string', 'max:255'],
            'capture_software'  => ['nullable', 'string', 'max:255'],
            'notes'             => ['nullable', 'string', 'max:65535'],
        ]);

        // Resolve the asset path from the chosen digital object, if any, so
        // the manifest can hash the real file and write a sidecar.
        $assetPath = $this->resolveAssetPath($validated['digital_object_id'] ?? null, $informationObjectId);

        $id = $this->service->record($informationObjectId, [
            'digital_object_id' => $validated['digital_object_id'] ?? null,
            'captured_by'       => $validated['captured_by'] ?? null,
            'captured_at'       => $validated['captured_at'] ?? null,
            'capture_device'    => $validated['capture_device'] ?? null,
            'capture_software'  => $validated['capture_software'] ?? null,
            'notes'             => $validated['notes'] ?? null,
            'asset_path'        => $assetPath,
            'heratio_version'   => $this->heratioVersion(),
        ]);

        return redirect()
            ->route('c2pa.provenance.show', ['informationObjectId' => $informationObjectId, 'provenanceId' => $id])
            ->with('status', 'Provenance record created.');
    }

    /**
     * Raw signed manifest JSON for a record (downloadable content credentials).
     */
    public function manifestJson(int $informationObjectId, int $provenanceId)
    {
        $record = $this->service->find($provenanceId);
        if ($record === null || $record->manifest_id === null) {
            abort(404, 'No signed manifest for this record');
        }
        $row = DB::table('ahg_c2pa_manifest')->where('id', $record->manifest_id)->first(['manifest_json']);
        if ($row === null) {
            abort(404, 'Manifest row missing');
        }

        return response((string) $row->manifest_json, 200, [
            'Content-Type'        => 'application/json',
            'Content-Disposition' => 'inline; filename="c2pa-manifest-' . $provenanceId . '.json"',
        ]);
    }

    private function loadObject(int $informationObjectId): ?object
    {
        if (!Schema::hasTable('information_object')) {
            return null;
        }
        $io = DB::table('information_object')->where('id', $informationObjectId)->first(['id', 'identifier']);
        if ($io === null) {
            return null;
        }
        if (Schema::hasTable('information_object_i18n')) {
            $i18n = DB::table('information_object_i18n')
                ->where('id', $informationObjectId)
                ->orderByRaw("culture = 'en' DESC")
                ->first(['title']);
            $io->title = $i18n->title ?? null;
        }
        if (Schema::hasTable('slug')) {
            $slug = DB::table('slug')->where('object_id', $informationObjectId)->first(['slug']);
            $io->slug = $slug->slug ?? null;
        }
        return $io;
    }

    /**
     * @return list<object>
     */
    private function loadDigitalObjects(int $informationObjectId): array
    {
        if (!Schema::hasTable('digital_object')) {
            return [];
        }
        return DB::table('digital_object')
            ->where('object_id', $informationObjectId)
            ->get(['id', 'name', 'path', 'mime_type'])
            ->all();
    }

    private function resolveAssetPath(mixed $digitalObjectId, int $informationObjectId): ?string
    {
        if ($digitalObjectId === null) {
            return null;
        }
        if (!Schema::hasTable('digital_object')) {
            return null;
        }
        $do = DB::table('digital_object')
            ->where('id', (int) $digitalObjectId)
            ->where('object_id', $informationObjectId)
            ->first(['path', 'name']);
        if ($do === null) {
            return null;
        }

        $base = function_exists('config') ? (string) config('heratio.uploads_path', '') : '';
        $path = (string) ($do->path ?? '');
        $name = (string) ($do->name ?? '');
        $candidates = array_filter([
            $base !== '' ? rtrim($base, '/') . '/' . ltrim($path . $name, '/') : null,
            $base !== '' ? rtrim($base, '/') . '/' . ltrim($path, '/') : null,
            $path . $name,
            $path,
        ]);
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_readable($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    private function heratioVersion(): string
    {
        $path = base_path('version.json');
        if (is_readable($path)) {
            $data = json_decode((string) file_get_contents($path), true);
            if (is_array($data) && isset($data['version'])) {
                return (string) $data['version'];
            }
        }
        return 'unknown';
    }
}
