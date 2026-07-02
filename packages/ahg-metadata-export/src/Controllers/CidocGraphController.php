<?php

/**
 * CidocGraphController - PUBLIC bulk download of the whole-collection CIDOC-CRM
 * (ISO 21127) Turtle dataset at GET /data/cidoc-crm.ttl.
 *
 * This is the dataset-level public surface for the Open Memory Protocol
 * open-data line (#1204), the bulk companion to the per-entity CIDOC-CRM
 * endpoints under /admin/metadata-export. It is unauthenticated open data:
 * published records only, CORS-open, read-only.
 *
 * Two serving modes, in order:
 *   1. If a pre-built dump exists at config('heratio.storage_path')
 *      .'/cidoc-graph/cidoc-crm.ttl' (the artisan ahg:export-cidoc-graph
 *      output), it is STREAMED straight off disk - no per-request DB work,
 *      so a large catalogue costs nothing at request time. This is the
 *      intended production path: run the command on a schedule, serve the file.
 *   2. Otherwise a BOUNDED dump is generated on the fly, hard-capped at
 *      self::ON_THE_FLY_CAP published records, reusing CidocCrmSerializer one
 *      record at a time and streaming the Turtle as it is produced. The cap
 *      protects the request from an unbounded full-catalogue serialisation; a
 *      capped response carries an X-Open-Data-Truncated header and a Turtle
 *      comment telling the client to use the scheduled dump for the full graph.
 *
 * Both modes emit a single shared @prefix block followed by concatenated triple
 * bodies, exactly like the command, so cross-entity #crm-object fragments join
 * into one graph.
 *
 * Catch-all safety: the path is the TWO-segment "/data/cidoc-crm.ttl". The
 * archival-record slug catch-all in ahg-information-object-manage matches a
 * SINGLE path segment with no dot (^[a-z0-9][a-z0-9-]*$), so it can never
 * capture a two-segment, dotted path. The route is also registered at the root
 * of this package's routes file with the bare `web` group.
 *
 * Read-only: every query is a SELECT through CidocCrmSerializer; this controller
 * never writes the database (and only reads the dump file - it never writes it;
 * the command owns writing). No INSERT/UPDATE/DELETE/ALTER.
 *
 * Phase of issue #1197 / north-star #1204. The epics stay OPEN.
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

namespace AhgMetadataExport\Controllers;

use AhgMetadataExport\Services\Exporters\CidocCrmSerializer;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CidocGraphController extends Controller
{
    /** Publication-status gate (status table; AtoM term ids). */
    private const STATUS_TYPE_PUBLICATION = 158;
    private const PUBLICATION_STATUS_PUBLISHED = 160;

    /** Hard cap for the on-the-fly fallback (records). The pre-built dump has no
     *  cap; this only bounds the no-dump request path so it can never run away. */
    private const ON_THE_FLY_CAP = 2000;

    /** Id page size for the on-the-fly keyset cursor. */
    private const BATCH = 500;

    /**
     * CORS preflight for the open-data bulk surface.
     */
    public function options(): StreamedResponse
    {
        return new StreamedResponse(function () {}, 204, $this->corsHeaders() + [
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Accept, Content-Type',
            'Access-Control-Max-Age' => '86400',
        ]);
    }

    /**
     * GET /data/cidoc-crm.ttl - stream the most-recent dump, or a bounded
     * on-the-fly graph. Always Turtle, always published-only, always CORS-open.
     */
    public function download(Request $request): StreamedResponse
    {
        $culture = (string) $request->query('culture', app()->getLocale() ?: 'en');
        $dumpPath = $this->dumpPath();

        if (is_file($dumpPath) && is_readable($dumpPath) && filesize($dumpPath) > 0) {
            return $this->streamFile($dumpPath);
        }

        return $this->streamOnTheFly($culture);
    }

    // -----------------------------------------------------------------
    // Mode 1: stream the pre-built dump straight off disk.
    // -----------------------------------------------------------------

    private function streamFile(string $path): StreamedResponse
    {
        $size = (int) filesize($path);
        $mtime = (int) filemtime($path);

        $headers = $this->corsHeaders() + [
            'Content-Type' => 'text/turtle; charset=UTF-8',
            'Content-Length' => (string) $size,
            'Content-Disposition' => 'inline; filename="cidoc-crm.ttl"',
            'Last-Modified' => gmdate('D, d M Y H:i:s', $mtime).' GMT',
            'X-Open-Data' => 'true',
            'X-Open-Data-Source' => 'prebuilt-dump',
        ];

        return new StreamedResponse(function () use ($path) {
            $fh = fopen($path, 'rb');
            if ($fh === false) {
                return;
            }
            while (! feof($fh)) {
                $chunk = fread($fh, 1 << 16); // 64 KiB
                if ($chunk === false) {
                    break;
                }
                echo $chunk;
                @ob_flush();
                @flush();
            }
            fclose($fh);
        }, 200, $headers);
    }

    // -----------------------------------------------------------------
    // Mode 2: bounded on-the-fly generation (hard cap, streamed).
    // -----------------------------------------------------------------

    private function streamOnTheFly(string $culture): StreamedResponse
    {
        $headers = $this->corsHeaders() + [
            'Content-Type' => 'text/turtle; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="cidoc-crm.ttl"',
            'X-Open-Data' => 'true',
            'X-Open-Data-Source' => 'on-the-fly',
            'X-Open-Data-Cap' => (string) self::ON_THE_FLY_CAP,
        ];

        $cap = self::ON_THE_FLY_CAP;
        $batch = self::BATCH;

        return new StreamedResponse(function () use ($culture, $cap, $batch) {
            // Shared prefix block, written once.
            echo '@prefix rdf: <'.CidocCrmSerializer::NS_RDF."> .\n";
            echo '@prefix rdfs: <'.CidocCrmSerializer::NS_RDFS."> .\n";
            echo '@prefix xsd: <'.CidocCrmSerializer::NS_XSD."> .\n";
            echo '@prefix crm: <'.CidocCrmSerializer::NS_CRM."> .\n";
            echo '@prefix ecrm: <'.CidocCrmSerializer::NS_ECRM."> .\n\n";
            echo '# Heratio combined CIDOC-CRM dataset (on-the-fly, capped at '.$cap.' records).'."\n";
            echo '# For the FULL graph, fetch the scheduled dump produced by'."\n";
            echo '#   php artisan ahg:export-cidoc-graph'."\n\n";

            if (! Schema::hasTable('information_object') || ! Schema::hasTable('status')) {
                return;
            }

            $serializer = new CidocCrmSerializer();
            $count = 0;
            $lastId = 0;

            while ($count < $cap) {
                $remaining = $cap - $count;
                $page = min($batch, $remaining);
                $ids = $this->nextPublishedIdBatch($lastId, $page);
                if (empty($ids)) {
                    break;
                }

                foreach ($ids as $id) {
                    $lastId = $id;
                    if ($count >= $cap) {
                        break 2;
                    }
                    $ttl = $serializer->serializeRecord($id, $culture, CidocCrmSerializer::FORMAT_TURTLE, true);
                    if ($ttl === '') {
                        continue;
                    }
                    echo $this->stripPrefixBlock($ttl);
                    $count++;
                }

                @ob_flush();
                @flush();
            }
        }, 200, $headers);
    }

    /**
     * @return int[]
     */
    private function nextPublishedIdBatch(int $lastId, int $page): array
    {
        return DB::table('status')
            ->where('type_id', self::STATUS_TYPE_PUBLICATION)
            ->where('status_id', self::PUBLICATION_STATUS_PUBLISHED)
            ->where('object_id', '>', max(1, $lastId)) // root id 1 excluded
            // #1384/#1389 — exclude ICIP/TK + ODRL-restricted records from the graph
            ->whereNotIn('object_id', app(\AhgCore\Services\DisclosureGate::class)->restrictedIds())
            ->orderBy('object_id')
            ->limit($page)
            ->pluck('object_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Strip a per-record serializer's own @prefix block so only the one shared
     * block at the top survives. Mirrors ExportCidocGraphCommand::stripPrefixBlock.
     */
    private function stripPrefixBlock(string $ttl): string
    {
        $lines = preg_split('/\r\n|\n|\r/', $ttl);
        $i = 0;
        $n = count($lines);
        while ($i < $n) {
            $line = ltrim($lines[$i]);
            if ($line === '' || str_starts_with($line, '@prefix') || str_starts_with($line, '@base')) {
                $i++;
                continue;
            }
            break;
        }

        return rtrim(implode("\n", array_slice($lines, $i)), "\n")."\n\n";
    }

    private function dumpPath(): string
    {
        $base = rtrim((string) config('heratio.storage_path', base_path('uploads')), '/');

        return $base.'/cidoc-graph/cidoc-crm.ttl';
    }

    /**
     * Permissive open-data CORS, matching the other /data/* surfaces.
     */
    private function corsHeaders(): array
    {
        return [
            'Access-Control-Allow-Origin' => '*',
            'Vary' => 'Accept',
        ];
    }
}
