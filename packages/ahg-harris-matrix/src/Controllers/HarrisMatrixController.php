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
use Illuminate\Support\Facades\DB;

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
                    $committed = $this->commit($parsed['rows'], $contexts);
                }
            }
        }

        return view('ahg-harris-matrix::import-lst', compact(
            'site', 'siteId', 'parsed', 'unmatched', 'matched', 'committed'
        ));
    }

    /**
     * Write the parsed relationships, reciprocally.
     *
     * archaeology_context_relationship stores both directions of every
     * relationship - the base module's own convention - so each row here writes
     * the pair. Rows naming a unit this site does not have are skipped and
     * counted, never invented.
     *
     * @param  array<int,array{source:string,type:string,target:string,line:int}>  $rows
     */
    private function commit(array $rows, $contexts): array
    {
        $inverse = ['above' => 'below', 'below' => 'above', 'same_as' => 'same_as'];
        $written = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $from = $contexts->get($row['source']);
            $to = $contexts->get($row['target']);
            if (! $from || ! $to || ! isset($inverse[$row['type']])) {
                $skipped++;
                continue;
            }

            foreach ([[$from->id, $to->id, $row['type']], [$to->id, $from->id, $inverse[$row['type']]]] as [$a, $b, $type]) {
                $exists = DB::table('archaeology_context_relationship')
                    ->where('context_id', $a)->where('related_context_id', $b)
                    ->where('relationship_type', $type)->exists();
                if (! $exists) {
                    DB::table('archaeology_context_relationship')->insert([
                        'context_id' => $a,
                        'related_context_id' => $b,
                        'relationship_type' => $type,
                        'note' => 'Imported from LST',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $written++;
                }
            }
        }

        return ['written' => $written, 'skipped' => $skipped];
    }
}
