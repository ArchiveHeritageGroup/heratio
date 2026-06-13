<?php

/**
 * WritingStudioService - Service for Heratio
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

namespace AhgResearch\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * WritingStudioService - Research OS Stage 13 (epic heratio#1222).
 *
 * A per-project write-as-you-go editor connected to the Claim Ledger and the
 * project's sources. Documents (research_writing_doc) hold ordered sections
 * (research_writing_section); a save-version snapshots the whole document into
 * research_writing_version for a simple, durable version history.
 *
 * The studio is grounded in the project's own evidence: it READS the Claim
 * Ledger (research_assertion) and the bibliography (research_bibliography +
 * research_bibliography_entry) so a researcher can cite a claim or pull a
 * source straight into the prose. Those existing tables are NEVER altered and
 * only ever read. Only the three NEW research_writing_* tables are written.
 *
 * Every read/write is Schema::hasTable-guarded and wrapped in try/catch so the
 * studio degrades to an empty state rather than throwing a 500 when a table is
 * missing during a partial install.
 *
 * Optional AI drafting per section routes STRICTLY through the AHG gateway
 * abstraction (AhgAiServices\Services\LlmService) - never a direct node port.
 * It is labelled, the researcher approves it, and it is never applied
 * automatically. The studio is fully usable with AI switched off.
 */
class WritingStudioService
{
    /**
     * Document types surfaced by the studio. VARCHAR-backed (no ENUM); these are
     * the canonical values, but unknown legacy values still render.
     *
     * @var array<string,string>
     */
    public const DOC_TYPES = [
        'thesis_chapter' => 'Thesis chapter',
        'article'        => 'Journal article',
        'review'         => 'Literature review',
        'section'        => 'Section',
        'other'          => 'Other',
    ];

    /** @var array<string,string> Document lifecycle status. */
    public const STATUSES = [
        'draft'     => 'Draft',
        'in_review' => 'In review',
        'final'     => 'Final',
        'archived'  => 'Archived',
    ];

    /** @var array<string,string> Bootstrap badge colour per status. */
    public const STATUS_BADGES = [
        'draft'     => 'secondary',
        'in_review' => 'info',
        'final'     => 'success',
        'archived'  => 'dark',
    ];

    /** Label applied to any AI-generated draft. Never applied without approval. */
    public const AI_LABEL = 'AI-assisted draft (review required before use)';

    // =====================================================================
    // TABLE GUARDS
    // =====================================================================

    protected function docsReady(): bool
    {
        try {
            return Schema::hasTable('research_writing_doc');
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function sectionsReady(): bool
    {
        try {
            return Schema::hasTable('research_writing_section');
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function versionsReady(): bool
    {
        try {
            return Schema::hasTable('research_writing_version');
        } catch (\Throwable $e) {
            return false;
        }
    }

    // =====================================================================
    // DOCUMENTS
    // =====================================================================

    /**
     * List a project's writing documents, each with a section count.
     *
     * @return array<int,object>
     */
    public function listDocs(int $projectId): array
    {
        if (! $this->docsReady()) {
            return [];
        }
        try {
            $rows = DB::table('research_writing_doc')
                ->where('project_id', $projectId)
                ->orderBy('updated_at', 'desc')
                ->get();

            $counts = [];
            if ($this->sectionsReady() && $rows->count() > 0) {
                $counts = DB::table('research_writing_section')
                    ->whereIn('doc_id', $rows->pluck('id')->all())
                    ->select('doc_id', DB::raw('COUNT(*) as n'))
                    ->groupBy('doc_id')
                    ->pluck('n', 'doc_id');
            }
            foreach ($rows as $r) {
                $r->section_count = (int) ($counts[$r->id] ?? 0);
            }
            return $rows->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Load one document scoped to a project. */
    public function getDoc(int $projectId, int $docId): ?object
    {
        if (! $this->docsReady()) {
            return null;
        }
        try {
            return DB::table('research_writing_doc')
                ->where('id', $docId)
                ->where('project_id', $projectId)
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Create a document. Returns the new id, or null on failure. */
    public function createDoc(int $projectId, array $data, ?int $userId = null): ?int
    {
        if (! $this->docsReady()) {
            return null;
        }
        try {
            $now = now();
            return DB::table('research_writing_doc')->insertGetId([
                'project_id' => $projectId,
                'title'      => $this->trimTo($data['title'] ?? 'Untitled', 500),
                'doc_type'   => $this->normaliseDocType($data['doc_type'] ?? 'section'),
                'status'     => $this->normaliseStatus($data['status'] ?? 'draft'),
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Update a document's title/type/status. */
    public function updateDoc(int $projectId, int $docId, array $data): bool
    {
        if (! $this->docsReady()) {
            return false;
        }
        try {
            $update = ['updated_at' => now()];
            if (array_key_exists('title', $data)) {
                $update['title'] = $this->trimTo((string) $data['title'], 500);
            }
            if (array_key_exists('doc_type', $data)) {
                $update['doc_type'] = $this->normaliseDocType($data['doc_type']);
            }
            if (array_key_exists('status', $data)) {
                $update['status'] = $this->normaliseStatus($data['status']);
            }
            return DB::table('research_writing_doc')
                ->where('id', $docId)
                ->where('project_id', $projectId)
                ->update($update) >= 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Delete a document, its sections and its versions. */
    public function deleteDoc(int $projectId, int $docId): bool
    {
        if (! $this->docsReady()) {
            return false;
        }
        try {
            $owned = DB::table('research_writing_doc')
                ->where('id', $docId)->where('project_id', $projectId)->exists();
            if (! $owned) {
                return false;
            }
            if ($this->sectionsReady()) {
                DB::table('research_writing_section')->where('doc_id', $docId)->delete();
            }
            if ($this->versionsReady()) {
                DB::table('research_writing_version')->where('doc_id', $docId)->delete();
            }
            DB::table('research_writing_doc')
                ->where('id', $docId)->where('project_id', $projectId)->delete();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // =====================================================================
    // SECTIONS (write-as-you-go)
    // =====================================================================

    /**
     * Ordered sections for a document.
     *
     * @return array<int,object>
     */
    public function getSections(int $docId): array
    {
        if (! $this->sectionsReady()) {
            return [];
        }
        try {
            return DB::table('research_writing_section')
                ->where('doc_id', $docId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Load a single section scoped to a document. */
    public function getSection(int $docId, int $sectionId): ?object
    {
        if (! $this->sectionsReady()) {
            return null;
        }
        try {
            return DB::table('research_writing_section')
                ->where('id', $sectionId)
                ->where('doc_id', $docId)
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Add a section to a document. Returns the new section id, or null. */
    public function addSection(int $docId, array $data): ?int
    {
        if (! $this->sectionsReady()) {
            return null;
        }
        try {
            $nextSort = (int) DB::table('research_writing_section')
                ->where('doc_id', $docId)->max('sort_order');
            $id = DB::table('research_writing_section')->insertGetId([
                'doc_id'     => $docId,
                'heading'    => $this->trimTo((string) ($data['heading'] ?? ''), 500) ?: null,
                'body'       => $data['body'] ?? '',
                'sort_order' => $nextSort + 1,
                'updated_at' => now(),
            ]);
            $this->touchDoc($docId);
            return $id;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Save a section's heading/body (write-as-you-go). */
    public function saveSection(int $docId, int $sectionId, array $data): bool
    {
        if (! $this->sectionsReady()) {
            return false;
        }
        try {
            $update = ['updated_at' => now()];
            if (array_key_exists('heading', $data)) {
                $update['heading'] = $this->trimTo((string) $data['heading'], 500) ?: null;
            }
            if (array_key_exists('body', $data)) {
                $update['body'] = (string) $data['body'];
            }
            $ok = DB::table('research_writing_section')
                ->where('id', $sectionId)
                ->where('doc_id', $docId)
                ->update($update) >= 0;
            $this->touchDoc($docId);
            return $ok;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Append text to a section's body (used by cite-a-claim / pull-a-source). */
    public function appendToSection(int $docId, int $sectionId, string $text): bool
    {
        if (! $this->sectionsReady()) {
            return false;
        }
        try {
            $section = $this->getSection($docId, $sectionId);
            if (! $section) {
                return false;
            }
            $body = (string) ($section->body ?? '');
            $body = $body === '' ? $text : ($body . "\n\n" . $text);
            return $this->saveSection($docId, $sectionId, ['body' => $body]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Delete a section from a document. */
    public function deleteSection(int $docId, int $sectionId): bool
    {
        if (! $this->sectionsReady()) {
            return false;
        }
        try {
            DB::table('research_writing_section')
                ->where('id', $sectionId)
                ->where('doc_id', $docId)
                ->delete();
            $this->touchDoc($docId);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Bump the parent doc's updated_at so the list reflects recent writing. */
    protected function touchDoc(int $docId): void
    {
        try {
            if ($this->docsReady()) {
                DB::table('research_writing_doc')->where('id', $docId)
                    ->update(['updated_at' => now()]);
            }
        } catch (\Throwable $e) {
            // best-effort
        }
    }

    // =====================================================================
    // CITE A CLAIM (read-only over research_assertion)
    // =====================================================================

    /**
     * Project claims from the Claim Ledger, available to cite into the prose.
     * READ-ONLY over research_assertion - never written here.
     *
     * @return array<int,object>
     */
    public function projectClaims(int $projectId, int $limit = 200): array
    {
        try {
            if (! Schema::hasTable('research_assertion')) {
                return [];
            }
            return DB::table('research_assertion')
                ->where('project_id', $projectId)
                ->select('id', 'subject_label', 'object_value', 'object_label', 'predicate', 'status')
                ->orderBy('updated_at', 'desc')
                ->limit($limit)
                ->get()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Resolve one claim's display text (read-only). */
    public function claimText(int $projectId, int $claimId): ?string
    {
        try {
            if (! Schema::hasTable('research_assertion')) {
                return null;
            }
            $c = DB::table('research_assertion')
                ->where('id', $claimId)
                ->where('project_id', $projectId)
                ->first(['subject_label', 'object_value', 'object_label']);
            if (! $c) {
                return null;
            }
            $text = $c->object_value ?: ($c->subject_label ?: $c->object_label);
            return $text !== null ? (string) $text : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * A citable reference string for a claim. Inserted (not silently) into the
     * section the researcher chose.
     */
    public function formatClaimReference(int $projectId, int $claimId): ?string
    {
        $text = $this->claimText($projectId, $claimId);
        if ($text === null) {
            return null;
        }
        $text = trim(Str::limit($text, 600));
        return $text . ' [Claim #' . $claimId . ']';
    }

    // =====================================================================
    // PULL A SOURCE (read-only over the bibliography)
    // =====================================================================

    /**
     * Bibliography entries belonging to the project's bibliographies, available
     * to pull into the prose. READ-ONLY over research_bibliography +
     * research_bibliography_entry.
     *
     * @return array<int,object>
     */
    public function projectSources(int $projectId, int $limit = 500): array
    {
        try {
            if (! Schema::hasTable('research_bibliography_entry')
                || ! Schema::hasTable('research_bibliography')) {
                return [];
            }
            return DB::table('research_bibliography_entry as e')
                ->join('research_bibliography as b', 'e.bibliography_id', '=', 'b.id')
                ->where('b.project_id', $projectId)
                ->select(
                    'e.id', 'e.title', 'e.authors', 'e.date',
                    'e.container_title', 'e.publisher', 'e.entry_type'
                )
                ->orderBy('e.sort_order')
                ->orderBy('e.title')
                ->limit($limit)
                ->get()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * A plain reference string for a bibliography entry, scoped to the project.
     * Read-only resolve; returns null if the entry is not in this project.
     */
    public function formatSourceReference(int $projectId, int $entryId): ?string
    {
        try {
            if (! Schema::hasTable('research_bibliography_entry')
                || ! Schema::hasTable('research_bibliography')) {
                return null;
            }
            $e = DB::table('research_bibliography_entry as e')
                ->join('research_bibliography as b', 'e.bibliography_id', '=', 'b.id')
                ->where('e.id', $entryId)
                ->where('b.project_id', $projectId)
                ->select('e.title', 'e.authors', 'e.date', 'e.container_title', 'e.publisher')
                ->first();
            if (! $e) {
                return null;
            }
            $parts = [];
            if (! empty($e->authors))         { $parts[] = trim((string) $e->authors); }
            if (! empty($e->date))            { $parts[] = '(' . trim((string) $e->date) . ')'; }
            if (! empty($e->title))           { $parts[] = trim((string) $e->title) . '.'; }
            if (! empty($e->container_title)) { $parts[] = trim((string) $e->container_title) . '.'; }
            if (! empty($e->publisher))       { $parts[] = trim((string) $e->publisher) . '.'; }
            $ref = trim(implode(' ', $parts));
            if ($ref === '') {
                $ref = 'Source #' . $entryId;
            }
            return $ref . ' [Source #' . $entryId . ']';
        } catch (\Throwable $e) {
            return null;
        }
    }

    // =====================================================================
    // VERSIONS (snapshot history)
    // =====================================================================

    /**
     * Save a snapshot of the whole document (all sections) as a new version.
     * Returns the new version number, or null on failure.
     */
    public function saveVersion(int $projectId, int $docId, ?string $note = null, ?int $userId = null): ?int
    {
        if (! $this->versionsReady()) {
            return null;
        }
        try {
            $doc = $this->getDoc($projectId, $docId);
            if (! $doc) {
                return null;
            }
            $snapshot = $this->exportMarkdown($projectId, $docId);
            if ($snapshot === null) {
                return null;
            }
            $next = (int) DB::table('research_writing_version')
                ->where('doc_id', $docId)->max('version_no') + 1;
            DB::table('research_writing_version')->insert([
                'doc_id'     => $docId,
                'version_no' => $next,
                'snapshot'   => $snapshot,
                'note'       => $note !== null ? $this->trimTo($note, 1000) : null,
                'created_by' => $userId,
                'created_at' => now(),
            ]);
            return $next;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Version history for a document (newest first), without the heavy snapshot.
     *
     * @return array<int,object>
     */
    public function listVersions(int $docId): array
    {
        if (! $this->versionsReady()) {
            return [];
        }
        try {
            return DB::table('research_writing_version')
                ->where('doc_id', $docId)
                ->select('id', 'version_no', 'note', 'created_by', 'created_at')
                ->orderBy('version_no', 'desc')
                ->get()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Load one version's full snapshot, scoped to a project. */
    public function getVersion(int $projectId, int $docId, int $versionId): ?object
    {
        if (! $this->versionsReady()) {
            return null;
        }
        try {
            $doc = $this->getDoc($projectId, $docId);
            if (! $doc) {
                return null;
            }
            return DB::table('research_writing_version')
                ->where('id', $versionId)
                ->where('doc_id', $docId)
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    // =====================================================================
    // MARKDOWN EXPORT
    // =====================================================================

    /**
     * Render the document (title + ordered sections) as Markdown. Returns null
     * only when the document cannot be resolved for the project.
     */
    public function exportMarkdown(int $projectId, int $docId): ?string
    {
        $doc = $this->getDoc($projectId, $docId);
        if (! $doc) {
            return null;
        }
        $lines = [];
        $lines[] = '# ' . trim((string) ($doc->title ?? 'Untitled'));
        $lines[] = '';
        $typeLabel   = self::DOC_TYPES[$doc->doc_type ?? ''] ?? ($doc->doc_type ?? '');
        $statusLabel = self::STATUSES[$doc->status ?? ''] ?? ($doc->status ?? '');
        $meta = trim(($typeLabel !== '' ? $typeLabel : '') . ($statusLabel !== '' ? ' - ' . $statusLabel : ''), ' -');
        if ($meta !== '') {
            $lines[] = '_' . $meta . '_';
            $lines[] = '';
        }
        foreach ($this->getSections($docId) as $s) {
            $heading = trim((string) ($s->heading ?? ''));
            if ($heading !== '') {
                $lines[] = '## ' . $heading;
                $lines[] = '';
            }
            $body = trim((string) ($s->body ?? ''));
            if ($body !== '') {
                $lines[] = $body;
                $lines[] = '';
            }
        }
        return rtrim(implode("\n", $lines)) . "\n";
    }

    /** A filesystem-safe filename for the Markdown export. */
    public function exportFilename(object $doc): string
    {
        $slug = Str::slug((string) ($doc->title ?? 'document')) ?: ('document-' . ($doc->id ?? '0'));
        return 'writing-' . $slug . '.md';
    }

    // =====================================================================
    // OPTIONAL AI DRAFTING (gateway-only, labelled, never auto-applied)
    // =====================================================================

    /** Whether the AHG gateway LLM abstraction is available at all. */
    public function aiAvailable(): bool
    {
        return class_exists(\AhgAiServices\Services\LlmService::class);
    }

    /**
     * Draft prose for a section via the AHG gateway abstraction
     * (AhgAiServices\Services\LlmService) - NEVER a direct node port. The draft
     * is returned labelled and is only ever applied when the researcher
     * explicitly approves it. Returns ['ok','text','label'].
     *
     * @return array{ok:bool,text:string,label:string}
     */
    public function aiDraftSection(int $projectId, int $docId, int $sectionId, string $instruction = ''): array
    {
        $out = ['ok' => false, 'text' => '', 'label' => self::AI_LABEL];

        if (! $this->aiAvailable()) {
            return $out;
        }

        try {
            $doc     = $this->getDoc($projectId, $docId);
            $section = $this->getSection($docId, $sectionId);
            if (! $doc || ! $section) {
                return $out;
            }

            $docTitle = trim((string) ($doc->title ?? ''));
            $heading  = trim((string) ($section->heading ?? '')) ?: 'this section';
            $current  = trim((string) ($section->body ?? ''));

            // Ground the draft in the project's own claims + sources.
            $claims  = $this->projectClaims($projectId, 20);
            $sources = $this->projectSources($projectId, 20);
            $claimLines = [];
            foreach ($claims as $c) {
                $t = trim((string) ($c->object_value ?: $c->subject_label ?: ''));
                if ($t !== '') {
                    $claimLines[] = '- ' . Str::limit($t, 200) . ' [Claim #' . $c->id . ']';
                }
            }
            $sourceLines = [];
            foreach ($sources as $s) {
                $bits = array_filter([
                    trim((string) ($s->authors ?? '')),
                    $s->date ? '(' . trim((string) $s->date) . ')' : '',
                    trim((string) ($s->title ?? '')),
                ]);
                if ($bits) {
                    $sourceLines[] = '- ' . Str::limit(implode(' ', $bits), 200) . ' [Source #' . $s->id . ']';
                }
            }

            $instructionLine = $instruction !== ''
                ? "Researcher instruction: {$instruction}\n"
                : '';

            $prompt = "You are helping a researcher write the \"{$heading}\" section"
                . ($docTitle !== '' ? " of the document \"{$docTitle}\"" : '')
                . ". Keep the writing scholarly, clear, and jurisdiction-neutral. "
                . "Ground the prose strictly in the project's claims and sources below. "
                . "Do not invent facts, dates, named people, institutions, or results that are not present. "
                . "Where you draw on a claim or source, keep its [Claim #N] or [Source #N] marker so the researcher can verify it. "
                . "If the material is thin, write a short honest scaffold and mark gaps in square brackets.\n\n"
                . $instructionLine
                . "PROJECT CLAIMS:\n" . ($claimLines ? implode("\n", $claimLines) : '(none yet)') . "\n\n"
                . "PROJECT SOURCES:\n" . ($sourceLines ? implode("\n", $sourceLines) : '(none yet)') . "\n\n"
                . "CURRENT DRAFT OF THIS SECTION (may be empty):\n" . ($current !== '' ? $current : '(empty)') . "\n\n"
                . "Return only the section prose.";

            $text = (string) app(\AhgAiServices\Services\LlmService::class)
                ->complete($prompt, ['max_tokens' => 800, 'temperature' => 0.3]);
            $text = trim($text);

            $out['text'] = $text;
            $out['ok']   = $text !== '';

            // #1252 AI-use disclosure: each successful gateway generation is
            // recorded as an AI-stamped version snapshot of the document, so the
            // disclosure aggregator can detect it. The draft itself is still NEVER
            // auto-applied to the section - this only logs that AI assistance was
            // used, with the model + time. A purely manual saveVersion() leaves
            // ai_model/ai_at NULL and is therefore not disclosed as AI.
            if ($out['ok']) {
                $this->recordAiVersion($projectId, $docId, $heading);
            }
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] writing-studio aiDraftSection failed: ' . $e->getMessage());
        }

        return $out;
    }

    /**
     * #1252 - record an AI-marked version snapshot capturing that the gateway
     * drafted prose for a section. Best-effort; never throws into the caller.
     */
    protected function recordAiVersion(int $projectId, int $docId, string $heading): void
    {
        try {
            if (! $this->versionsReady()
                || ! Schema::hasColumn('research_writing_version', 'ai_at')) {
                return;
            }
            $snapshot = $this->exportMarkdown($projectId, $docId);
            if ($snapshot === null) {
                return;
            }
            $next = (int) DB::table('research_writing_version')
                ->where('doc_id', $docId)->max('version_no') + 1;
            DB::table('research_writing_version')->insert([
                'doc_id'     => $docId,
                'version_no' => $next,
                'snapshot'   => $snapshot,
                'note'       => $this->trimTo('AI draft generated for "' . $heading . '" (review required before use)', 1000),
                'created_by' => null,
                'created_at' => now(),
                'ai_model'   => $this->resolveAiModel(),
                'ai_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            // best-effort disclosure marker only.
        }
    }

    /**
     * #1252 - best-effort name of the model the AHG gateway used, read from the
     * LlmService default config. Falls back to the gateway label when the model
     * id is not exposed. NEVER contacts a node; config read only.
     */
    protected function resolveAiModel(): string
    {
        try {
            if (class_exists(\AhgAiServices\Services\LlmService::class)) {
                $cfg = (new \AhgAiServices\Services\LlmService())->getDefaultConfig();
                $model = trim((string) ($cfg->model ?? ''));
                if ($model !== '') {
                    return mb_substr($model, 0, 120);
                }
            }
        } catch (\Throwable $e) {
            // fall through to label.
        }
        return 'AHG AI gateway';
    }

    // =====================================================================
    // INTERNAL HELPERS
    // =====================================================================

    protected function normaliseDocType(?string $type): string
    {
        $type = trim((string) $type);
        return $type !== '' ? $type : 'section';
    }

    protected function normaliseStatus(?string $status): string
    {
        $status = trim((string) $status);
        return $status !== '' ? $status : 'draft';
    }

    protected function trimTo(string $value, int $max): string
    {
        $value = trim($value);
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }
}
