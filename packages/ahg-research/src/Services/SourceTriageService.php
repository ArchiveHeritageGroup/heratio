<?php

/**
 * SourceTriageService - Heratio ahg-research
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
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgResearch\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1227 - Research OS Stage 5: Source Triage + an HONEST read-status.
 *
 * Over a project's evidence/sources (bibliography entries + collection items), this service
 * provides a triage board: each source carries a triage category and a read-status that the
 * researcher sets by hand. The system NEVER fakes a read-status - generating an AI preview,
 * opening a source, or anything else leaves read_status untouched. Only an explicit human
 * action moves a source to 'read' / 'deeply-read'.
 *
 * The triage rows live in the sidecar table research_source_triage and key back to the source
 * by (source_type, source_id); the bibliography/collection tables are never altered. Every read
 * is Schema::hasTable-guarded and wrapped so a missing table degrades to an empty board, never a
 * 500.
 */
class SourceTriageService
{
    public const TABLE = 'research_source_triage';

    /**
     * Triage categories (dropdown-style allow-list, NOT a MySQL ENUM). The board lets the
     * researcher pick one of these per source; anything outside the list is rejected.
     */
    public const CATEGORIES = [
        'essential'       => 'Essential',
        'useful'          => 'Useful',
        'background'      => 'Background',
        'contested'       => 'Contested',
        'weak'            => 'Weak',
        'duplicate'       => 'Duplicate',
        'excluded'        => 'Excluded',
        'read-later'      => 'Read later',
        'method-source'   => 'Method source',
        'theory-source'   => 'Theory source',
        'evidence-source' => 'Evidence source',
    ];

    /**
     * Read-status allow-list. The system never auto-marks a source 'read'; the researcher
     * sets this honestly. 'previewed' means "an AI preview exists / glanced at", which is
     * deliberately distinct from any of the genuine human reading states.
     */
    public const READ_STATUSES = [
        'unread'      => 'Unread',
        'previewed'   => 'Previewed',
        'skimmed'     => 'Skimmed',
        'read'        => 'Read',
        'deeply-read' => 'Deeply read',
    ];

    public const SOURCE_TYPES = ['bibliography_entry', 'collection_item'];

    /** Idempotent label shown with every AI preview. Single source of truth. */
    public const AI_PREVIEW_LABEL = 'AI preview - not human verified';

    public function categories(): array
    {
        return self::CATEGORIES;
    }

    public function readStatuses(): array
    {
        return self::READ_STATUSES;
    }

    public function isValidCategory(?string $value): bool
    {
        return $value === null || $value === '' || array_key_exists($value, self::CATEGORIES);
    }

    public function isValidReadStatus(?string $value): bool
    {
        return is_string($value) && array_key_exists($value, self::READ_STATUSES);
    }

    public function isValidSourceType(?string $value): bool
    {
        return is_string($value) && in_array($value, self::SOURCE_TYPES, true);
    }

    private function tableReady(): bool
    {
        try {
            return Schema::hasTable(self::TABLE);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * The project's sources (bibliography entries + collection items), each joined to its
     * triage row (if any). Returns a flat list of plain arrays ready for the board view.
     * Never throws - a missing table or query error yields an empty list.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getBoard(int $projectId): array
    {
        $sources = array_merge(
            $this->bibliographySources($projectId),
            $this->collectionSources($projectId)
        );
        if (! $sources) {
            return [];
        }

        $triage = $this->triageMap($projectId);

        foreach ($sources as &$s) {
            $key = $s['source_type'] . ':' . $s['source_id'];
            $t = $triage[$key] ?? null;
            $s['triage_category'] = $t->triage_category ?? null;
            $s['read_status']     = $t->read_status ?? 'unread';
            $s['notes']           = $t->notes ?? '';
            $s['ai_preview']      = $t->ai_preview ?? null;
            $s['ai_preview_at']   = $t->ai_preview_at ?? null;
            $s['triage_updated_at'] = $t->updated_at ?? null;
        }
        unset($s);

        return $sources;
    }

    /** @return array<string,object> keyed by "type:id" */
    private function triageMap(int $projectId): array
    {
        if (! $this->tableReady()) {
            return [];
        }
        try {
            $rows = DB::table(self::TABLE)->where('project_id', $projectId)->get();
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] triage map load failed: ' . $e->getMessage());

            return [];
        }
        $map = [];
        foreach ($rows as $r) {
            $map[$r->source_type . ':' . $r->source_id] = $r;
        }

        return $map;
    }

    /** Bibliography entries belonging to this project (via research_bibliography.project_id). */
    private function bibliographySources(int $projectId): array
    {
        try {
            if (! Schema::hasTable('research_bibliography_entry') || ! Schema::hasTable('research_bibliography')) {
                return [];
            }
            $rows = DB::table('research_bibliography_entry as e')
                ->join('research_bibliography as b', 'b.id', '=', 'e.bibliography_id')
                ->where('b.project_id', $projectId)
                ->select(
                    'e.id',
                    'e.title',
                    'e.authors',
                    'e.date',
                    'e.entry_type',
                    'b.name as group_name'
                )
                ->orderBy('e.sort_order')
                ->orderBy('e.id')
                ->get();
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] triage bibliography load failed: ' . $e->getMessage());

            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'source_type' => 'bibliography_entry',
                'source_id'   => (int) $r->id,
                'title'       => trim((string) ($r->title ?? '')) !== '' ? (string) $r->title : '(untitled entry)',
                'subtitle'    => trim((string) ($r->authors ?? '')) . (($r->date ?? '') ? ' (' . $r->date . ')' : ''),
                'kind'        => (string) ($r->entry_type ?? 'reference'),
                'group_name'  => (string) ($r->group_name ?? ''),
            ];
        }

        return $out;
    }

    /** Collection items belonging to this project (via research_collection.project_id). */
    private function collectionSources(int $projectId): array
    {
        try {
            if (! Schema::hasTable('research_collection_item') || ! Schema::hasTable('research_collection')) {
                return [];
            }
            $rows = DB::table('research_collection_item as i')
                ->join('research_collection as c', 'c.id', '=', 'i.collection_id')
                ->leftJoin('information_object_i18n as io', function ($j) {
                    $j->on('io.id', '=', 'i.object_id')->where('io.culture', '=', 'en');
                })
                ->where('c.project_id', $projectId)
                ->select(
                    'i.id',
                    'i.object_type',
                    'i.reference_code',
                    'i.tags',
                    'io.title as io_title',
                    'c.name as group_name'
                )
                ->orderBy('i.sort_order')
                ->orderBy('i.id')
                ->get();
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] triage collection load failed: ' . $e->getMessage());

            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $title = trim((string) ($r->io_title ?? ''));
            if ($title === '') {
                $title = trim((string) ($r->reference_code ?? '')) !== ''
                    ? (string) $r->reference_code
                    : '(catalogue item #' . (int) $r->id . ')';
            }
            $out[] = [
                'source_type' => 'collection_item',
                'source_id'   => (int) $r->id,
                'title'       => $title,
                'subtitle'    => trim((string) ($r->reference_code ?? '')),
                'kind'        => (string) ($r->object_type ?? 'information_object'),
                'group_name'  => (string) ($r->group_name ?? ''),
            ];
        }

        return $out;
    }

    /** True if (source_type, source_id) is a real source under this project. Guards writes. */
    public function sourceBelongsToProject(int $projectId, string $sourceType, int $sourceId): bool
    {
        try {
            if ($sourceType === 'bibliography_entry') {
                if (! Schema::hasTable('research_bibliography_entry') || ! Schema::hasTable('research_bibliography')) {
                    return false;
                }

                return DB::table('research_bibliography_entry as e')
                    ->join('research_bibliography as b', 'b.id', '=', 'e.bibliography_id')
                    ->where('b.project_id', $projectId)
                    ->where('e.id', $sourceId)
                    ->exists();
            }
            if ($sourceType === 'collection_item') {
                if (! Schema::hasTable('research_collection_item') || ! Schema::hasTable('research_collection')) {
                    return false;
                }

                return DB::table('research_collection_item as i')
                    ->join('research_collection as c', 'c.id', '=', 'i.collection_id')
                    ->where('c.project_id', $projectId)
                    ->where('i.id', $sourceId)
                    ->exists();
            }
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] triage ownership check failed: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Set the triage category for a source. Only touches triage_category - never the read-status.
     * Empty string clears the category back to null.
     */
    public function setCategory(int $projectId, string $sourceType, int $sourceId, ?string $category, ?int $researcherId): bool
    {
        if (! $this->isValidSourceType($sourceType) || ! $this->isValidCategory($category)) {
            return false;
        }
        $category = ($category === '' ) ? null : $category;

        return $this->upsert($projectId, $sourceType, $sourceId, ['triage_category' => $category], $researcherId);
    }

    /**
     * Set the read-status for a source. This is the ONLY path that writes read_status, and it
     * only fires from an explicit researcher action - the system never calls it on the user's
     * behalf. A value outside the allow-list is rejected.
     */
    public function setReadStatus(int $projectId, string $sourceType, int $sourceId, string $readStatus, ?int $researcherId): bool
    {
        if (! $this->isValidSourceType($sourceType) || ! $this->isValidReadStatus($readStatus)) {
            return false;
        }

        return $this->upsert($projectId, $sourceType, $sourceId, ['read_status' => $readStatus], $researcherId);
    }

    /** Save free-text notes against a source. */
    public function setNotes(int $projectId, string $sourceType, int $sourceId, ?string $notes, ?int $researcherId): bool
    {
        if (! $this->isValidSourceType($sourceType)) {
            return false;
        }
        $notes = $notes === null ? null : mb_substr($notes, 0, 5000);

        return $this->upsert($projectId, $sourceType, $sourceId, ['notes' => $notes], $researcherId);
    }

    /**
     * Generate (or refresh) the optional AI structured preview for a source and store it. The
     * preview is ALWAYS surfaced with the AI_PREVIEW_LABEL by the view. This routine deliberately
     * does NOT change read_status - an AI preview is not human reading. Any AI call goes through
     * the LlmService abstraction, which routes to the AHG gateway; it is never wired to a node
     * port. AI is optional: failure leaves the board fully usable.
     *
     * @return array{ok:bool, preview?:string, error?:string}
     */
    public function generateAiPreview(int $projectId, string $sourceType, int $sourceId, ?int $researcherId): array
    {
        if (! $this->isValidSourceType($sourceType)) {
            return ['ok' => false, 'error' => 'Unknown source type.'];
        }
        if (! $this->sourceBelongsToProject($projectId, $sourceType, $sourceId)) {
            return ['ok' => false, 'error' => 'Source not found in this project.'];
        }

        $source = $this->describeSource($sourceType, $sourceId);
        if ($source === '') {
            return ['ok' => false, 'error' => 'Nothing to preview for this source.'];
        }

        $prompt = "You are a research assistant triaging archival sources. From ONLY the metadata "
            . "below, produce a short structured preview to help a researcher decide how to use this "
            . "source. Use these labelled lines and nothing else:\n"
            . "Summary: one or two neutral sentences.\n"
            . "Likely relevance: what kinds of questions this might help with.\n"
            . "Caveats: what is unknown or cannot be judged from metadata alone.\n"
            . "Do not invent facts, dates, names or contents that are not in the metadata. If the "
            . "metadata is thin, say so plainly.\n\nSOURCE METADATA:\n" . $source;

        try {
            $preview = trim((string) app(\AhgAiServices\Services\LlmService::class)
                ->complete($prompt, ['max_tokens' => 400, 'temperature' => 0.2]));
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] triage AI preview failed: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'The AI preview service is unavailable right now.'];
        }

        if ($preview === '') {
            return ['ok' => false, 'error' => 'The AI preview came back empty.'];
        }

        // Store the preview + timestamp. read_status is intentionally untouched here.
        $this->upsert($projectId, $sourceType, $sourceId, [
            'ai_preview'    => mb_substr($preview, 0, 8000),
            'ai_preview_at' => now(),
        ], $researcherId);

        return ['ok' => true, 'preview' => $preview, 'label' => self::AI_PREVIEW_LABEL];
    }

    /** Build a compact metadata string for the AI preview from the source's own row. */
    private function describeSource(string $sourceType, int $sourceId): string
    {
        try {
            if ($sourceType === 'bibliography_entry') {
                $r = DB::table('research_bibliography_entry')->where('id', $sourceId)->first();
                if (! $r) {
                    return '';
                }
                $parts = array_filter([
                    'Title: ' . ($r->title ?? ''),
                    'Authors: ' . ($r->authors ?? ''),
                    'Date: ' . ($r->date ?? ''),
                    'Type: ' . ($r->entry_type ?? ''),
                    'Publisher: ' . ($r->publisher ?? ''),
                    'Container: ' . ($r->container_title ?? ''),
                    'Archive: ' . ($r->archive_name ?? ''),
                    'Notes: ' . ($r->notes ?? ''),
                ], fn ($p) => trim(substr($p, strpos($p, ':') + 1)) !== '');

                return implode("\n", $parts);
            }

            $r = DB::table('research_collection_item as i')
                ->leftJoin('information_object_i18n as io', function ($j) {
                    $j->on('io.id', '=', 'i.object_id')->where('io.culture', '=', 'en');
                })
                ->where('i.id', $sourceId)
                ->select('i.*', 'io.title as io_title', 'io.scope_and_content')
                ->first();
            if (! $r) {
                return '';
            }
            $parts = array_filter([
                'Title: ' . ($r->io_title ?? ''),
                'Reference code: ' . ($r->reference_code ?? ''),
                'Type: ' . ($r->object_type ?? ''),
                'Tags: ' . ($r->tags ?? ''),
                'Scope and content: ' . trim(strip_tags((string) ($r->scope_and_content ?? ''))),
                'Notes: ' . ($r->notes ?? ''),
            ], fn ($p) => trim(substr($p, strpos($p, ':') + 1)) !== '');

            return mb_substr(implode("\n", $parts), 0, 4000);
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] triage describeSource failed: ' . $e->getMessage());

            return '';
        }
    }

    /**
     * Upsert one triage row keyed by (project_id, source_type, source_id). Only the supplied
     * columns are written; everything else is left as-is. Returns false (never throws) on any
     * DB error so the board keeps working.
     */
    private function upsert(int $projectId, string $sourceType, int $sourceId, array $values, ?int $researcherId): bool
    {
        if (! $this->tableReady()) {
            return false;
        }
        try {
            $values['updated_by'] = $researcherId;
            $values['updated_at'] = now();

            $existing = DB::table(self::TABLE)
                ->where('project_id', $projectId)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->first();

            if ($existing) {
                DB::table(self::TABLE)->where('id', $existing->id)->update($values);
            } else {
                DB::table(self::TABLE)->insert(array_merge([
                    'project_id'  => $projectId,
                    'source_type' => $sourceType,
                    'source_id'   => $sourceId,
                    'read_status' => 'unread',
                ], $values));
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] triage upsert failed: ' . $e->getMessage());

            return false;
        }
    }
}
