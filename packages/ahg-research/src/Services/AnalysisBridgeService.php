<?php

/**
 * AnalysisBridgeService - Service for Heratio
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

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * AnalysisBridgeService - Research OS Stage 11 (heratio#1234).
 *
 * The Analysis Bridge does NOT run analysis engines. The value it adds is
 * PROVENANCE for results produced elsewhere - Jupyter notebooks, R scripts, QDA
 * software, statistics packages. A researcher registers a result here together
 * with its full origin (what data + version, what method/code, when it was
 * generated, and the decision drawn from it), optionally uploads the artifact,
 * and links it to the project claim(s) it supports, weakens or contextualises.
 *
 * Every read/write is Schema::hasTable-guarded and wrapped in try/catch so the
 * bridge degrades to an empty state rather than throwing a 500 during a partial
 * install. The only tables written are the NEW Analysis Bridge tables; existing
 * tables (research_assertion, research_project, ...) are read-only here.
 */
class AnalysisBridgeService
{
    /** Sub-directory (relative to config('heratio.storage_path')) for artifacts. */
    public const UPLOAD_SUBDIR = 'research-analysis';

    /** @var array<string,string> Result types surfaced in the register (VARCHAR, not ENUM). */
    public const RESULT_TYPES = [
        'chart'     => 'Chart / figure',
        'table'     => 'Table',
        'theme'     => 'Theme / coded finding',
        'statistic' => 'Statistic / test',
        'other'     => 'Other',
    ];

    /** @var array<string,string> Bootstrap badge colour per result type. */
    public const RESULT_TYPE_BADGES = [
        'chart'     => 'info',
        'table'     => 'secondary',
        'theme'     => 'success',
        'statistic' => 'primary',
        'other'     => 'dark',
    ];

    /** @var array<string,string> How a result bears on a claim. */
    public const RELATIONSHIPS = [
        'supports'       => 'Supports',
        'weakens'        => 'Weakens',
        'contextualises' => 'Contextualises',
    ];

    /** @var array<string,string> Bootstrap badge colour per relationship. */
    public const RELATIONSHIP_BADGES = [
        'supports'       => 'success',
        'weakens'        => 'danger',
        'contextualises' => 'info',
    ];

    /** @var array<string,string> Built-in coding kinds. */
    public const CODE_KINDS = [
        'theme_tag' => 'Theme tag',
        'memo'      => 'Memo',
    ];

    // =====================================================================
    // READINESS GUARDS
    // =====================================================================

    protected function resultsReady(): bool
    {
        try {
            return Schema::hasTable('research_analysis_result');
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function linksReady(): bool
    {
        try {
            return Schema::hasTable('research_analysis_result_claim');
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function codesReady(): bool
    {
        try {
            return Schema::hasTable('research_analysis_code');
        } catch (\Throwable $e) {
            return false;
        }
    }

    // =====================================================================
    // READ
    // =====================================================================

    /**
     * List registered results for a project, each carrying its linked-claim count.
     *
     * @return array<int,object>
     */
    public function listResults(int $projectId, array $filters = []): array
    {
        if (! $this->resultsReady()) {
            return [];
        }
        try {
            $q = DB::table('research_analysis_result')
                ->where('project_id', $projectId);

            if (! empty($filters['result_type'])) {
                $q->where('result_type', $filters['result_type']);
            }
            if (! empty($filters['search'])) {
                $term = '%' . $filters['search'] . '%';
                $q->where(function ($w) use ($term) {
                    $w->where('title', 'like', $term)
                      ->orWhere('method', 'like', $term)
                      ->orWhere('source_data_ref', 'like', $term);
                });
            }

            $rows = $q->orderByDesc('id')->get();

            // Batched link counts so the register stays cheap.
            $linkCounts = [];
            if ($this->linksReady() && $rows->count() > 0) {
                $linkCounts = DB::table('research_analysis_result_claim')
                    ->whereIn('result_id', $rows->pluck('id')->all())
                    ->select('result_id', DB::raw('COUNT(*) as n'))
                    ->groupBy('result_id')
                    ->pluck('n', 'result_id');
            }
            foreach ($rows as $r) {
                $r->link_count = (int) ($linkCounts[$r->id] ?? 0);
            }

            return $rows->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Count results per type for a project (filter pills + summary). */
    public function typeCounts(int $projectId): array
    {
        if (! $this->resultsReady()) {
            return [];
        }
        try {
            return DB::table('research_analysis_result')
                ->where('project_id', $projectId)
                ->select('result_type', DB::raw('COUNT(*) as n'))
                ->groupBy('result_type')
                ->pluck('n', 'result_type')->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Load one result scoped to a project. */
    public function getResult(int $projectId, int $resultId): ?object
    {
        if (! $this->resultsReady()) {
            return null;
        }
        try {
            return DB::table('research_analysis_result')
                ->where('id', $resultId)
                ->where('project_id', $projectId)
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * The claims linked to a result, resolved to a human label from the Claim
     * Ledger (research_assertion). Each row carries the relationship.
     *
     * @return array<int,object>
     */
    public function getLinkedClaims(int $resultId): array
    {
        if (! $this->linksReady()) {
            return [];
        }
        try {
            $links = DB::table('research_analysis_result_claim')
                ->where('result_id', $resultId)
                ->orderByDesc('id')
                ->get();

            if ($links->isEmpty() || ! Schema::hasTable('research_assertion')) {
                foreach ($links as $l) {
                    $l->claim_label = 'Claim #' . $l->assertion_id;
                    $l->claim_status = null;
                }
                return $links->all();
            }

            $claims = DB::table('research_assertion')
                ->whereIn('id', $links->pluck('assertion_id')->all())
                ->get()->keyBy('id');

            foreach ($links as $l) {
                $c = $claims[$l->assertion_id] ?? null;
                $l->claim_label = $c
                    ? (string) ($c->object_value ?: $c->subject_label ?: ('Claim #' . $l->assertion_id))
                    : ('Claim #' . $l->assertion_id);
                $l->claim_status = $c->status ?? null;
            }
            return $links->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Project claims selectable for linking (from the Claim Ledger). Returns an
     * empty list when research_assertion is absent so the UI degrades cleanly.
     *
     * @return array<int,object>
     */
    public function availableClaims(int $projectId): array
    {
        try {
            if (! Schema::hasTable('research_assertion')) {
                return [];
            }
            return DB::table('research_assertion')
                ->where('project_id', $projectId)
                ->select('id',
                    DB::raw("COALESCE(NULLIF(object_value,''), NULLIF(subject_label,''), CONCAT('Claim #', id)) as label"),
                    'status')
                ->orderByDesc('updated_at')
                ->limit(1000)
                ->get()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Light thematic-coding tags + memos for a project.
     *
     * @return array<string,array<int,object>>
     */
    public function getCodes(int $projectId): array
    {
        $out = ['theme_tag' => [], 'memo' => []];
        if (! $this->codesReady()) {
            return $out;
        }
        try {
            $rows = DB::table('research_analysis_code')
                ->where('project_id', $projectId)
                ->orderByDesc('id')
                ->get();
            foreach ($rows as $r) {
                $kind = isset($out[$r->kind]) ? $r->kind : 'theme_tag';
                $out[$kind][] = $r;
            }
        } catch (\Throwable $e) {
            // empty state
        }
        return $out;
    }

    // =====================================================================
    // WRITE - results
    // =====================================================================

    /**
     * Register an external result with its provenance. Returns the new id or null.
     */
    public function createResult(int $projectId, int $userId, array $data, ?UploadedFile $artifact = null): ?int
    {
        if (! $this->resultsReady()) {
            return null;
        }
        try {
            $now = now();
            $id = DB::table('research_analysis_result')->insertGetId([
                'project_id'          => $projectId,
                'result_type'         => $this->normaliseType($data['result_type'] ?? 'other'),
                'title'               => mb_substr((string) ($data['title'] ?? 'Untitled result'), 0, 500),
                'source_data_ref'     => $this->trimOrNull($data['source_data_ref'] ?? null, 1000),
                'source_data_version' => $this->trimOrNull($data['source_data_version'] ?? null, 120),
                'method'              => $this->trimOrNull($data['method'] ?? null),
                'code_ref'            => $this->trimOrNull($data['code_ref'] ?? null, 1000),
                'generated_at'        => $this->parseDate($data['generated_at'] ?? null),
                'researcher_decision' => $this->trimOrNull($data['researcher_decision'] ?? null),
                'artifact_path'       => null,
                'created_by'          => $userId,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);

            if ($artifact instanceof UploadedFile) {
                $rel = $this->storeArtifact($artifact, $projectId, (int) $id);
                if ($rel !== null) {
                    DB::table('research_analysis_result')->where('id', $id)
                        ->update(['artifact_path' => $rel]);
                }
            }

            return (int) $id;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Update a registered result's provenance metadata (project-scoped). */
    public function updateResult(int $projectId, int $resultId, array $data, ?UploadedFile $artifact = null): bool
    {
        if (! $this->resultsReady()) {
            return false;
        }
        try {
            $owned = DB::table('research_analysis_result')
                ->where('id', $resultId)->where('project_id', $projectId)->exists();
            if (! $owned) {
                return false;
            }

            $update = [
                'result_type'         => $this->normaliseType($data['result_type'] ?? 'other'),
                'title'               => mb_substr((string) ($data['title'] ?? 'Untitled result'), 0, 500),
                'source_data_ref'     => $this->trimOrNull($data['source_data_ref'] ?? null, 1000),
                'source_data_version' => $this->trimOrNull($data['source_data_version'] ?? null, 120),
                'method'              => $this->trimOrNull($data['method'] ?? null),
                'code_ref'            => $this->trimOrNull($data['code_ref'] ?? null, 1000),
                'generated_at'        => $this->parseDate($data['generated_at'] ?? null),
                'researcher_decision' => $this->trimOrNull($data['researcher_decision'] ?? null),
                'updated_at'          => now(),
            ];

            if ($artifact instanceof UploadedFile) {
                $rel = $this->storeArtifact($artifact, $projectId, $resultId);
                if ($rel !== null) {
                    $update['artifact_path'] = $rel;
                }
            }

            DB::table('research_analysis_result')
                ->where('id', $resultId)->where('project_id', $projectId)
                ->update($update);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Delete a result, its claim links and its artifact (project-scoped). */
    public function deleteResult(int $projectId, int $resultId): bool
    {
        if (! $this->resultsReady()) {
            return false;
        }
        try {
            $row = DB::table('research_analysis_result')
                ->where('id', $resultId)->where('project_id', $projectId)->first();
            if (! $row) {
                return false;
            }
            if ($this->linksReady()) {
                DB::table('research_analysis_result_claim')->where('result_id', $resultId)->delete();
            }
            // Best-effort artifact removal (stays within the storage root).
            $abs = $this->artifactAbsolutePath($row);
            if ($abs !== null && is_file($abs)) {
                @unlink($abs);
            }
            DB::table('research_analysis_result')
                ->where('id', $resultId)->where('project_id', $projectId)->delete();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // =====================================================================
    // WRITE - claim links
    // =====================================================================

    /** Link a result to a project claim with a relationship. Idempotent. */
    public function linkClaim(int $projectId, int $resultId, int $assertionId, string $relationship, ?string $note = null): bool
    {
        if (! $this->resultsReady() || ! $this->linksReady()) {
            return false;
        }
        try {
            $owned = DB::table('research_analysis_result')
                ->where('id', $resultId)->where('project_id', $projectId)->exists();
            if (! $owned) {
                return false;
            }
            // The claim must belong to the same project (read-only check).
            if (Schema::hasTable('research_assertion')) {
                $claimOk = DB::table('research_assertion')
                    ->where('id', $assertionId)->where('project_id', $projectId)->exists();
                if (! $claimOk) {
                    return false;
                }
            }
            $rel = array_key_exists($relationship, self::RELATIONSHIPS) ? $relationship : 'supports';

            $exists = DB::table('research_analysis_result_claim')
                ->where('result_id', $resultId)->where('assertion_id', $assertionId)->first();
            if ($exists) {
                DB::table('research_analysis_result_claim')->where('id', $exists->id)
                    ->update(['relationship' => $rel, 'note' => $this->trimOrNull($note)]);
                return true;
            }
            DB::table('research_analysis_result_claim')->insert([
                'result_id'    => $resultId,
                'assertion_id' => $assertionId,
                'relationship' => $rel,
                'note'         => $this->trimOrNull($note),
                'created_at'   => now(),
            ]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Remove a result-claim link (project-scoped via the parent result). */
    public function unlinkClaim(int $projectId, int $resultId, int $linkId): bool
    {
        if (! $this->resultsReady() || ! $this->linksReady()) {
            return false;
        }
        try {
            $owned = DB::table('research_analysis_result')
                ->where('id', $resultId)->where('project_id', $projectId)->exists();
            if (! $owned) {
                return false;
            }
            DB::table('research_analysis_result_claim')
                ->where('id', $linkId)->where('result_id', $resultId)->delete();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // =====================================================================
    // WRITE - light coding / memos
    // =====================================================================

    /** Add a thematic-coding tag or memo to a project. Returns the new id or null. */
    public function addCode(int $projectId, int $userId, string $kind, string $label, ?string $body = null): ?int
    {
        if (! $this->codesReady()) {
            return null;
        }
        $label = trim($label);
        if ($label === '') {
            return null;
        }
        try {
            return (int) DB::table('research_analysis_code')->insertGetId([
                'project_id' => $projectId,
                'kind'       => array_key_exists($kind, self::CODE_KINDS) ? $kind : 'theme_tag',
                'label'      => mb_substr($label, 0, 255),
                'body'       => $this->trimOrNull($body),
                'created_by' => $userId,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Delete a coding tag / memo (project-scoped). */
    public function deleteCode(int $projectId, int $codeId): bool
    {
        if (! $this->codesReady()) {
            return false;
        }
        try {
            DB::table('research_analysis_code')
                ->where('id', $codeId)->where('project_id', $projectId)->delete();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // =====================================================================
    // OPTIONAL AI (labelled) - via the AHG gateway abstraction ONLY
    // =====================================================================

    /**
     * Optional, clearly-labelled AI helper: suggest a one-line plain-language
     * caption for a registered result from its provenance fields. Routes ONLY
     * through LlmService (the AHG gateway abstraction) - never a node port. The
     * caller is responsible for labelling the output as AI-generated in the UI.
     * Returns null when AI is unavailable so the feature degrades silently.
     */
    public function suggestCaption(object $result): ?string
    {
        try {
            if (! class_exists(\AhgAiServices\Services\LlmService::class)) {
                return null;
            }
            $llm = new \AhgAiServices\Services\LlmService();
            $prompt = "You are helping an archival researcher describe an analysis result for a provenance log. "
                . "Write ONE plain-language sentence (max 30 words) summarising this result. Do not invent findings.\n\n"
                . 'Title: ' . (string) ($result->title ?? '') . "\n"
                . 'Type: ' . (string) ($result->result_type ?? '') . "\n"
                . 'Source data: ' . (string) ($result->source_data_ref ?? '') . "\n"
                . 'Method: ' . (string) ($result->method ?? '') . "\n"
                . 'Researcher decision: ' . (string) ($result->researcher_decision ?? '');
            $out = $llm->complete($prompt, ['max_tokens' => 80]);
            $out = is_string($out) ? trim($out) : '';
            return $out !== '' ? $out : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // =====================================================================
    // ARTIFACT STORAGE - config('heratio.storage_path'), traversal-guarded
    // =====================================================================

    /**
     * Store an uploaded artifact under config('heratio.storage_path').
     * Returns the path RELATIVE to that root (portable), or null on failure.
     * Never returns or stores a hardcoded absolute path.
     */
    private function storeArtifact(UploadedFile $file, int $projectId, int $resultId): ?string
    {
        $root = rtrim((string) config('heratio.storage_path'), '/');
        if ($root === '') {
            return null;
        }

        $relDir = self::UPLOAD_SUBDIR . '/' . $projectId . '/' . $resultId;
        $absDir = $root . '/' . $relDir;

        if (! is_dir($absDir)) {
            @mkdir($absDir, 0775, true);
        }
        if (! is_dir($absDir) || ! is_writable($absDir)) {
            return null;
        }

        $ext  = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $ext  = preg_replace('~[^a-z0-9]+~', '', $ext) ?: 'bin';
        $base = Str::slug(pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'artifact';
        $name = $base . '-' . Str::random(8) . '.' . $ext;

        try {
            $file->move($absDir, $name);
        } catch (\Throwable $e) {
            return null;
        }

        return $relDir . '/' . $name;
    }

    /**
     * Absolute filesystem path of a result's artifact, or null. Traversal-guarded
     * and confined to the storage root via realpath containment.
     */
    public function artifactAbsolutePath(object $result): ?string
    {
        if (empty($result->artifact_path)) {
            return null;
        }
        $root = rtrim((string) config('heratio.storage_path'), '/');
        if ($root === '') {
            return null;
        }
        // Strip any traversal sequence and leading slashes before joining.
        $rel = ltrim(str_replace('..', '', (string) $result->artifact_path), '/');
        $abs = $root . '/' . $rel;

        // Containment check: the resolved path must sit under the storage root.
        $realRoot = realpath($root);
        $realAbs  = realpath($abs);
        if ($realRoot === false || $realAbs === false) {
            return is_file($abs) ? $abs : null;
        }
        if (strpos($realAbs, $realRoot . DIRECTORY_SEPARATOR) !== 0 && $realAbs !== $realRoot) {
            return null;
        }
        return is_file($realAbs) ? $realAbs : null;
    }

    // =====================================================================
    // INTERNAL HELPERS
    // =====================================================================

    /** Keep result type within the known set, default to 'other'. */
    protected function normaliseType(?string $type): string
    {
        $type = trim((string) $type);
        return array_key_exists($type, self::RESULT_TYPES) ? $type : 'other';
    }

    /** Trim a string to null when empty, optionally cap its length. */
    protected function trimOrNull(?string $v, ?int $max = null): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        return $max !== null ? mb_substr($v, 0, $max) : $v;
    }

    /** Parse a date-ish input to 'Y-m-d H:i:s' or null. Never throws. */
    protected function parseDate(?string $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse($v)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
