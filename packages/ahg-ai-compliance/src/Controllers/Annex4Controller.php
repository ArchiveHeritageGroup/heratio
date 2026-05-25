<?php
/**
 * Heratio - admin UI for Annex IV technical-documentation bundles.
 *
 * Lists generated documents on disk, lets an operator trigger a fresh
 * generation per service, and serves downloads. The artisan command does
 * all the heavy lifting; this controller is a thin wrapper.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAiCompliance\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class Annex4Controller extends Controller
{
    private const SERVICES = ['llm', 'htr', 'ner', 'donut', 'guardrail', 'translate'];

    public function index(): View
    {
        $dir = storage_path('ai-compliance/annex-iv');
        $byService = array_fill_keys(self::SERVICES, []);

        if (is_dir($dir)) {
            foreach (File::files($dir) as $file) {
                $name = $file->getFilename();
                if (!preg_match('/^([a-z0-9_-]+)-(\d{4}-\d{2}-\d{2})\.md$/i', $name, $m)) {
                    continue;
                }
                $svc = strtolower($m[1]);
                if (!array_key_exists($svc, $byService)) {
                    $byService[$svc] = [];
                }
                $byService[$svc][] = [
                    'name'         => $name,
                    'date'         => $m[2],
                    'size_bytes'   => $file->getSize(),
                    'modified_iso' => date(DATE_ATOM, $file->getMTime()),
                ];
            }
        }

        // Newest first inside each service.
        foreach ($byService as &$bucket) {
            usort($bucket, static fn (array $a, array $b) => strcmp($b['date'], $a['date']));
        }

        return view('ahg-ai-compliance::documentation.index', [
            'byService' => $byService,
            'services'  => self::SERVICES,
            'storeDir'  => $dir,
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $service = (string) $request->input('service', '');
        $args = [];
        if (in_array($service, self::SERVICES, true)) {
            $args['--service'] = $service;
        }

        // Run synchronously - the operator pressed "Generate now". For large
        // backfills they should use the artisan command directly so they get
        // streaming output.
        try {
            Artisan::call('ai-compliance:annex-iv', $args);
            $output = Artisan::output();
            return redirect()
                ->route('ai-compliance.documentation.index')
                ->with('success', 'Annex IV generation finished.')
                ->with('console', $output);
        } catch (\Throwable $e) {
            return redirect()
                ->route('ai-compliance.documentation.index')
                ->with('error', 'Annex IV generation failed: ' . $e->getMessage());
        }
    }

    public function show(string $filename): BinaryFileResponse
    {
        if (!preg_match('/^[a-z0-9_-]+-\d{4}-\d{2}-\d{2}\.md$/i', $filename)) {
            abort(404);
        }
        $path = storage_path('ai-compliance/annex-iv/' . $filename);
        if (!is_file($path)) {
            abort(404);
        }
        return response()->file($path, [
            'Content-Type'        => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}
