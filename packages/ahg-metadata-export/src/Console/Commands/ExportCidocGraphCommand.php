<?php

/**
 * ExportCidocGraphCommand - artisan ahg:export-cidoc-graph
 *
 * Streams the WHOLE published catalogue into ONE combined CIDOC-CRM (ISO 21127)
 * Turtle dataset file - a whole-collection dump that joins every per-record,
 * per-actor and per-term graph the metadata-export package already serialises
 * into a single connected graph.
 *
 * It is the dataset-level companion to the per-entity CIDOC-CRM downloads
 * (CidocCrmSerializer / CidocCrmActorSerializer / CidocCrmTermSerializer): the
 * SAME serializers are reused verbatim, one entity at a time, and their output
 * is concatenated under a single shared @prefix block. Because each serializer
 * mints stable fragment IRIs off the public entity URL (a record's
 * `<base>/<slug>#crm-object`, an actor's `<base>/actor/<id>`, a term's place /
 * type node), the cross-entity references - a record's P50 has current keeper,
 * an actor's P14 carried out by, a term's appellation - all resolve to nodes
 * that appear elsewhere in the same file. One dump, one graph.
 *
 * Streaming + bounding: published information_object ids are walked in ascending
 * id batches with a keyset cursor (WHERE object_id > :last ORDER BY object_id
 * LIMIT :batch), so the whole catalogue is never held in memory - only one batch
 * of ids plus the Turtle for one entity at a time. The output is streamed to
 * disk through a file handle as each entity is rendered. A --limit caps the
 * record count for smoke runs; --batch tunes the id page size.
 *
 * Where it lands: config('heratio.storage_path').'/cidoc-graph/' - never a
 * hardcoded path. The default filename is `cidoc-crm.ttl` (idempotent overwrite)
 * so the public bulk-download route can stream "the most recent dump" off a
 * stable name; --out overrides it.
 *
 * Published gate: the same gate every CRM surface uses - status.type_id = 158
 * AND status.status_id = 160, synthetic root id 1 excluded. Actors and terms
 * (opt-in via --actors / --terms) are emitted with publicOnly = true so their
 * linked-record lists never leak an unpublished title.
 *
 * Read-only: every query is a SELECT; the only write is the dump file under the
 * configured storage path. No INSERT/UPDATE/DELETE/ALTER, ever.
 *
 * Phase of issue #1197 / north-star #1204 (Unified G/L/A/M knowledge graph -
 * RiC + CIDOC-CRM + KM; the Open Memory Protocol open-data line). The epics stay
 * OPEN.
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

namespace AhgMetadataExport\Console\Commands;

use AhgMetadataExport\Services\Exporters\CidocCrmActorSerializer;
use AhgMetadataExport\Services\Exporters\CidocCrmSerializer;
use AhgMetadataExport\Services\Exporters\CidocCrmTermSerializer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExportCidocGraphCommand extends Command
{
    protected $signature = 'ahg:export-cidoc-graph
        {--out= : Output .ttl path (default: {heratio.storage_path}/cidoc-graph/cidoc-crm.ttl)}
        {--culture=en : i18n culture for labels}
        {--batch=500 : Id page size for the keyset cursor (streaming, never the whole catalogue in memory)}
        {--limit=0 : Cap the number of published records exported (0 = no cap; for smoke runs)}
        {--actors : Also append every actor that produced a published record (as E21/E40/E74/E39 nodes)}
        {--terms : Also append every subject / place term cited by a published record (as E55 Type / E53 Place nodes)}';

    protected $description = 'Stream the whole published catalogue into ONE combined CIDOC-CRM (ISO 21127) Turtle dataset (reuses the per-record / actor / term serializers).';

    /** Publication-status gate (status table; AtoM term ids) - identical to the
     *  per-entity CRM serializers. */
    private const STATUS_TYPE_PUBLICATION = 158;
    private const PUBLICATION_STATUS_PUBLISHED = 160;

    /** Creation event type id (AtoM "Creation") - used to find producing actors. */
    private const EVENT_TYPE_CREATION = 111;

    /** Access-point taxonomies the records exporter actually references as
     *  #crm-subject / #crm-place nodes (subjects + places). */
    private const TAXONOMY_SUBJECT = 35;
    private const TAXONOMY_PLACE   = 42;

    /** #1388 - ids of records under a restricted community protocol; excluded from the graph. Memoised across batches. */
    private ?array $protocolRestrictedIds = null;

    public function handle(): int
    {
        if (! Schema::hasTable('information_object') || ! Schema::hasTable('status')) {
            $this->error('information_object / status schema not present - cannot build the CIDOC-CRM graph.');
            return self::FAILURE;
        }

        $culture = (string) $this->option('culture');
        $batch   = max(1, (int) $this->option('batch'));
        $limit   = max(0, (int) $this->option('limit'));
        $withActors = (bool) $this->option('actors');
        $withTerms  = (bool) $this->option('terms');

        $out = (string) ($this->option('out') ?: $this->defaultOutPath());
        $dir = dirname($out);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error('Could not create output directory: '.$dir);
            return self::FAILURE;
        }

        // Stream to a temp file in the same directory, then atomically rename
        // over the target so a reader never sees a half-written dump (idempotent
        // overwrite).
        $tmp = $out.'.'.getmypid().'.tmp';
        $fh = @fopen($tmp, 'wb');
        if ($fh === false) {
            $this->error('Could not open output file for writing: '.$tmp);
            return self::FAILURE;
        }

        $recordSerializer = new CidocCrmSerializer();
        $actorSerializer  = $withActors ? new CidocCrmActorSerializer() : null;
        $termSerializer   = $withTerms ? new CidocCrmTermSerializer() : null;

        $this->info('Building combined CIDOC-CRM graph (Turtle) ...');
        $this->line('  output:  '.$out);
        $this->line('  culture: '.$culture.'   batch: '.$batch.($limit ? '   limit: '.$limit : '').($withActors ? '   +actors' : '').($withTerms ? '   +terms' : ''));

        // ---- Shared prefix block (written ONCE) -------------------------------
        fwrite($fh, $this->prefixBlock());
        fwrite($fh, $this->headerComment($out, $culture, $withActors, $withTerms));

        $recordCount = 0;
        $emptyCount  = 0;
        $lastId = 0;

        // Collect producing-actor ids and cited-term ids as we stream records,
        // so the optional actor/term passes only touch entities that actually
        // appear in the published graph (bounded by the published corpus).
        $actorIds = [];
        $termIds  = [];

        while (true) {
            $ids = $this->nextPublishedIdBatch($lastId, $batch);
            if (empty($ids)) {
                break;
            }

            foreach ($ids as $id) {
                $lastId = $id;

                if ($limit > 0 && $recordCount >= $limit) {
                    break 2;
                }

                $ttl = $recordSerializer->serializeRecord($id, $culture, CidocCrmSerializer::FORMAT_TURTLE, true);
                if ($ttl === '') {
                    $emptyCount++;
                    continue;
                }

                fwrite($fh, $this->stripPrefixBlock($ttl));
                $recordCount++;

                if ($withActors || $withTerms) {
                    $this->collectLinks($id, $actorIds, $termIds, $withActors, $withTerms);
                }
            }

            // The id page is exhausted; let the cursor advance to the next page.
        }

        // ---- Optional: actor nodes -------------------------------------------
        $actorCount = 0;
        if ($withActors && $actorSerializer !== null) {
            fwrite($fh, "\n# ---- Actors (producers of published records) ----\n\n");
            ksort($actorIds);
            foreach (array_keys($actorIds) as $actorId) {
                $ttl = $actorSerializer->serializeActor((int) $actorId, $culture, CidocCrmActorSerializer::FORMAT_TURTLE, true);
                if ($ttl === '') {
                    continue;
                }
                fwrite($fh, $this->stripPrefixBlock($ttl));
                $actorCount++;
            }
        }

        // ---- Optional: term / place nodes ------------------------------------
        $termCount = 0;
        if ($withTerms && $termSerializer !== null) {
            fwrite($fh, "\n# ---- Terms and places (cited by published records) ----\n\n");
            ksort($termIds);
            foreach (array_keys($termIds) as $termId) {
                $ttl = $termSerializer->serializeTerm((int) $termId, $culture, CidocCrmTermSerializer::FORMAT_TURTLE, true);
                if ($ttl === '') {
                    continue;
                }
                fwrite($fh, $this->stripPrefixBlock($ttl));
                $termCount++;
            }
        }

        fflush($fh);
        fclose($fh);

        if (! @rename($tmp, $out)) {
            @unlink($tmp);
            $this->error('Could not move temp dump into place: '.$out);
            return self::FAILURE;
        }

        $bytes = is_file($out) ? (int) filesize($out) : 0;

        // ---- Loud, accounted summary -----------------------------------------
        $this->newLine();
        $this->info('CIDOC-CRM graph written.');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Published records exported', number_format($recordCount)],
                ['Records skipped (gate / no i18n)', number_format($emptyCount)],
                ['Actor nodes appended', $withActors ? number_format($actorCount) : '(not requested)'],
                ['Term / place nodes appended', $withTerms ? number_format($termCount) : '(not requested)'],
                ['Culture', $culture],
                ['Output file', $out],
                ['File size', number_format($bytes).' bytes ('.$this->humanBytes($bytes).')'],
            ]
        );

        return self::SUCCESS;
    }

    // -----------------------------------------------------------------
    // Streaming id enumeration (keyset cursor, never the whole set in memory).
    // -----------------------------------------------------------------

    /**
     * The next ascending-id page of PUBLISHED information_object ids after
     * $lastId. Keyset pagination keeps memory flat regardless of catalogue size.
     *
     * @return int[]
     */
    private function nextPublishedIdBatch(int $lastId, int $batch): array
    {
        // #1388 - community-protocol-restricted ids, resolved once and reused per batch.
        $this->protocolRestrictedIds ??= \AhgCore\Services\TermProtocolService::restrictedRecordIds();

        $query = DB::table('status')
            ->where('type_id', self::STATUS_TYPE_PUBLICATION)
            ->where('status_id', self::PUBLICATION_STATUS_PUBLISHED)
            ->where('object_id', '>', max(1, $lastId)) // root id 1 excluded
            // #1384/#1389 — exclude ICIP/TK + ODRL-restricted records from the graph
            ->whereNotIn('object_id', app(\AhgCore\Services\DisclosureGate::class)->restrictedIds());

        // #1388 — and community-protocol-restricted records.
        if (! empty($this->protocolRestrictedIds)) {
            $query->whereNotIn('object_id', $this->protocolRestrictedIds);
        }

        return $query
            ->orderBy('object_id')
            ->limit($batch)
            ->pluck('object_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Record the producing actors and cited subject/place terms for one
     * published record, so the optional --actors / --terms passes are bounded by
     * the published graph. Read-only SELECTs; the ids are accumulated as map
     * keys to dedupe.
     */
    private function collectLinks(int $ioId, array &$actorIds, array &$termIds, bool $withActors, bool $withTerms): void
    {
        if ($withActors && Schema::hasTable('event')) {
            $rows = DB::table('event')
                ->where('object_id', $ioId)
                ->where('type_id', self::EVENT_TYPE_CREATION)
                ->whereNotNull('actor_id')
                ->pluck('actor_id');
            foreach ($rows as $aid) {
                $actorIds[(int) $aid] = true;
            }
        }

        if ($withTerms && Schema::hasTable('object_term_relation') && Schema::hasTable('term')) {
            $rows = DB::table('object_term_relation')
                ->join('term', 'object_term_relation.term_id', '=', 'term.id')
                ->where('object_term_relation.object_id', $ioId)
                ->whereIn('term.taxonomy_id', [self::TAXONOMY_SUBJECT, self::TAXONOMY_PLACE])
                ->pluck('object_term_relation.term_id');
            foreach ($rows as $tid) {
                $termIds[(int) $tid] = true;
            }
        }
    }

    // -----------------------------------------------------------------
    // Turtle assembly helpers.
    // -----------------------------------------------------------------

    /**
     * The single shared @prefix block for the combined document. Mirrors the
     * per-entity serializers' prefixes exactly (rdf / rdfs / xsd / crm / ecrm)
     * so the concatenated triple bodies resolve against it.
     */
    private function prefixBlock(): string
    {
        return '@prefix rdf: <'.CidocCrmSerializer::NS_RDF."> .\n"
            .'@prefix rdfs: <'.CidocCrmSerializer::NS_RDFS."> .\n"
            .'@prefix xsd: <'.CidocCrmSerializer::NS_XSD."> .\n"
            .'@prefix crm: <'.CidocCrmSerializer::NS_CRM."> .\n"
            .'@prefix ecrm: <'.CidocCrmSerializer::NS_ECRM."> .\n\n";
    }

    private function headerComment(string $out, string $culture, bool $withActors, bool $withTerms): string
    {
        $parts = ['records'];
        if ($withActors) {
            $parts[] = 'actors';
        }
        if ($withTerms) {
            $parts[] = 'terms';
        }

        return '# Heratio combined CIDOC-CRM (ISO 21127) dataset dump.'."\n"
            .'# Generated '.gmdate('Y-m-d H:i:s').' UTC for culture "'.$culture.'".'."\n"
            .'# Contents: '.implode(' + ', $parts).'. Published records only'."\n"
            .'# (status type 158 / status 160, root id 1 excluded). One graph; the'."\n"
            .'# per-entity #crm-object / actor / term IRIs join across the file.'."\n\n";
    }

    /**
     * Remove a per-entity serializer's own @prefix block (the leading run of
     * @prefix / @base declarations and the blank lines around them) so only ONE
     * shared block survives at the top of the combined document. The serializers
     * always emit exactly five @prefix lines + a blank line; this strips that
     * run defensively regardless of count.
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

        $body = implode("\n", array_slice($lines, $i));

        // Ensure each entity block is separated by a blank line in the output.
        return rtrim($body, "\n")."\n\n";
    }

    private function defaultOutPath(): string
    {
        $base = rtrim((string) config('heratio.storage_path', base_path('uploads')), '/');

        return $base.'/cidoc-graph/cidoc-crm.ttl';
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $val = (float) $bytes;
        while ($val >= 1024 && $i < count($units) - 1) {
            $val /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) $bytes.' '.$units[$i] : number_format($val, 2).' '.$units[$i]);
    }
}
