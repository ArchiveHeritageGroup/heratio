<?php

/**
 * WebArchiveController - Heratio ahg-scan
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
 * @copyright Plain Sailing Information Systems
 */

namespace AhgScan\Controllers;

use AhgScan\Services\WebArchiveCaptureService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin surface for single-page web archiving (WARC 1.1).
 *
 * Every action is empty-state safe: if the table is not yet installed, the
 * pages render an informative notice rather than throwing a 500.
 */
class WebArchiveController extends Controller
{
    public function __construct(protected WebArchiveCaptureService $service)
    {
    }

    /** List captures + the submit-URL form. */
    public function index()
    {
        $installed = $this->installed();

        $captures = collect();
        if ($installed) {
            $captures = DB::table('web_archive_capture')
                ->orderByDesc('id')
                ->limit(100)
                ->get();
        }

        return view('ahg-scan::admin.web-archive.index', [
            'installed' => $installed,
            'captures' => $captures,
            'storageHint' => rtrim((string) config('heratio.storage_path'), '/').'/web-archive',
        ]);
    }

    /** Handle the submit-URL form: capture, then redirect back with a notice. */
    public function store(Request $request)
    {
        if (! $this->installed()) {
            return redirect()->route('web-archive.index')
                ->with('error', 'The web-archive store is not installed yet. Reload this page to trigger auto-install.');
        }

        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2048', 'url'],
        ]);

        $id = $this->service->capture($validated['url'], optional($request->user())->id);

        if ($id === null) {
            return redirect()->route('web-archive.index')
                ->with('error', 'Capture could not be recorded.');
        }

        $row = DB::table('web_archive_capture')->find($id);
        if ($row && $row->status === 'captured') {
            return redirect()->route('web-archive.show', $id)
                ->with('notice', 'Captured to WARC.');
        }

        return redirect()->route('web-archive.show', $id)
            ->with('error', 'Capture recorded as failed: '.($row->error ?? 'unknown error'));
    }

    /** Per-capture detail: row metadata + parsed WARC headers + download link. */
    public function show($id)
    {
        if (! $this->installed()) {
            return redirect()->route('web-archive.index')
                ->with('error', 'The web-archive store is not installed yet.');
        }

        $capture = DB::table('web_archive_capture')->find((int) $id);
        if ($capture === null) {
            return redirect()->route('web-archive.index')
                ->with('error', 'Capture not found.');
        }

        $warcHeaders = [];
        $warcExists = false;
        if ($capture->warc_path && is_file($capture->warc_path) && is_readable($capture->warc_path)) {
            $warcExists = true;
            $warcHeaders = $this->parseWarcHeaders($capture->warc_path);
        }

        return view('ahg-scan::admin.web-archive.show', [
            'capture' => $capture,
            'warcHeaders' => $warcHeaders,
            'warcExists' => $warcExists,
        ]);
    }

    /** Stream the WARC file as a download. */
    public function download($id)
    {
        if (! $this->installed()) {
            abort(404);
        }

        $capture = DB::table('web_archive_capture')->find((int) $id);
        if ($capture === null || ! $capture->warc_path || ! is_file($capture->warc_path)) {
            abort(404);
        }

        return response()->download($capture->warc_path, basename($capture->warc_path), [
            'Content-Type' => 'application/warc',
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function installed(): bool
    {
        try {
            return Schema::hasTable('web_archive_capture');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Read just the named WARC header fields from each record's framing block
     * (the lines before the first blank line of each record). Bounded read so a
     * large WARC does not load fully into memory; for a single-response WARC the
     * relevant headers are always near the top.
     */
    protected function parseWarcHeaders(string $path): array
    {
        $records = [];
        try {
            $fh = @fopen($path, 'rb');
            if ($fh === false) {
                return [];
            }

            $current = null;
            $inHeaders = false;
            $maxLines = 4000; // safety bound
            $read = 0;

            while (($line = fgets($fh)) !== false && $read < $maxLines) {
                $read++;
                $line = rtrim($line, "\r\n");

                if ($line === 'WARC/1.1') {
                    if ($current !== null) {
                        $records[] = $current;
                    }
                    $current = [];
                    $inHeaders = true;

                    continue;
                }

                if ($current === null) {
                    continue;
                }

                if ($inHeaders) {
                    if ($line === '') {
                        // end of this record's header block; skip the block body
                        $inHeaders = false;

                        continue;
                    }
                    $pos = strpos($line, ':');
                    if ($pos !== false) {
                        $name = trim(substr($line, 0, $pos));
                        $value = trim(substr($line, $pos + 1));
                        $current[$name] = $value;
                    }
                }
            }

            if ($current !== null && ! empty($current)) {
                $records[] = $current;
            }

            fclose($fh);
        } catch (\Throwable $e) {
            return $records;
        }

        return $records;
    }
}
