<?php

/**
 * HarrisMatrixController - stratigraphic report and interchange - #1483.
 *
 * Copyright (C) 2026 Johan Pieterse
 * The Archive Heritage Group (Pty) Ltd
 *
 * This file is part of Heratio. Licensed under the GNU AGPL v3.
 */

namespace AhgHarrisMatrix\Controllers;

use AhgArchaeology\Services\ArchaeologyService;
use AhgHarrisMatrix\Services\HarrisMatrixService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class HarrisMatrixController extends Controller
{
    public function __construct(
        private readonly HarrisMatrixService $harris,
        private readonly ArchaeologyService $archaeology
    ) {
    }

    /** The consistency report for one site. */
    public function report(int $siteId)
    {
        $site = $this->archaeology->site($siteId);
        abort_unless($site, 404);

        $report = $this->harris->consistencyReport($siteId);

        return view('ahg-harris-matrix::report', [
            'site' => $site,
            'findings' => $report['findings'],
            'checked' => $report['checked'],
        ]);
    }

    /** GraphViz DOT of the reduced matrix. */
    public function exportDot(int $siteId)
    {
        abort_unless($this->archaeology->site($siteId), 404);

        return response($this->harris->exportDot($siteId), 200, [
            'Content-Type' => 'text/vnd.graphviz; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="harris-site-'.$siteId.'.dot"',
        ]);
    }

    /** Harris Matrix Data Package as JSON. */
    public function exportDataPackage(int $siteId)
    {
        abort_unless($this->archaeology->site($siteId), 404);

        return response()->json($this->harris->exportDataPackage($siteId), 200, [
            'Content-Disposition' => 'attachment; filename="harris-site-'.$siteId.'-datapackage.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Import an LST file.
     *
     * GET previews, POST commits. A preview first because an LST names units by
     * label and the labels have to be matched to contexts that already exist -
     * a silent import that quietly dropped every unmatched unit would be worse
     * than no import at all.
     */
    public function importLst(Request $request, int $siteId)
    {
        $site = $this->archaeology->site($siteId);
        abort_unless($site, 404);

        $parsed = null;
        $unmatched = [];
        $matched = 0;
        $committed = null;

        if ($request->hasFile('lst')) {
            $parsed = $this->harris->parseLst((string) file_get_contents($request->file('lst')->getRealPath()));

            if ($parsed['error'] === null) {
                $contexts = $this->archaeology->contextsForSite($siteId)
                    ->keyBy(fn ($c) => (string) $c->context_number);

                foreach ($parsed['units'] as $unit) {
                    if (! $contexts->has($unit)) {
                        $unmatched[] = $unit;
                    }
                }
                $matched = count($parsed['units']) - count($unmatched);

                if ($request->isMethod('post') && $request->boolean('commit')) {
                    // Shared apply path with the CSV import. It routes every row
                    // through ArchaeologyService::addRelationship(), so the
                    // self-relation check and the CYCLE GUARD apply to an LST
                    // import exactly as they do to typed entry. The previous
                    // hand-rolled insert here wrote straight to the table and
                    // could therefore record a stratigraphic loop that the form
                    // itself would have refused.
                    $committed = $this->harris->importRelationshipRows($siteId, $parsed['rows'], true);
                }
            }
        }

        return view('ahg-harris-matrix::import-lst', compact(
            'site', 'siteId', 'parsed', 'unmatched', 'matched', 'committed'
        ));
    }

    /**
     * PHASER four-column relationship CSV for one site.
     */
    public function exportPhaserCsv(int $siteId)
    {
        abort_unless($this->archaeology->site($siteId), 404);

        return response($this->harris->exportPhaserCsv($siteId), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="harris-site-'.$siteId.'-relationships.csv"',
        ]);
    }

    /** A ready-to-fill relationship CSV template. */
    public function relationshipTemplate()
    {
        return response($this->harris->relationshipCsvTemplate(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="harris-relationships-template.csv"',
        ]);
    }

    /**
     * Import a PHASER relationship CSV.
     *
     * Uploading previews; the commit is a second, explicit step. The preview is
     * a REAL run inside a transaction that is rolled back, so the counts and
     * warnings it shows are the ones a commit would produce - not an estimate
     * from a different code path that could disagree with it.
     */
    public function importRelationships(Request $request, int $siteId)
    {
        $site = $this->archaeology->site($siteId);
        abort_unless($site, 404);

        $parsed = null;
        $result = null;
        $committed = false;

        if ($request->hasFile('csv')) {
            $request->validate([
                'csv' => 'required|file|mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel|max:4096',
            ]);

            $parsed = $this->harris->parsePhaserCsv(
                (string) file_get_contents($request->file('csv')->getRealPath()),
                (string) ($site->site_number ?? '') !== '' ? (string) $site->site_number : null
            );

            if ($parsed['error'] === null) {
                $committed = $request->isMethod('post') && $request->boolean('commit');
                $result = $this->harris->importRelationshipRows($siteId, $parsed['rows'], $committed);
            }
        }

        return view('ahg-harris-matrix::import-relationships', compact(
            'site', 'siteId', 'parsed', 'result', 'committed'
        ));
    }
}
