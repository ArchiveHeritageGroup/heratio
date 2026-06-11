<?php

/**
 * InboxService - Service for Heratio
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
 * InboxService - Quick Capture Inbox (heratio#1228, ROS Stage 0).
 *
 * Frictionless capture: everything an idea-having researcher throws at the
 * portal lands in research_inbox_item with a timestamp + origin, nothing lost.
 * Triage into a project happens later (mark-triaged / archive / move-to-project).
 *
 * All reads/writes are scoped to the owning researcher_id - an item only ever
 * belongs to the researcher who captured it. Every query is Schema::hasTable
 * guarded and wrapped in try/catch so a not-yet-installed table never 500s.
 *
 * File uploads land under config('heratio.storage_path').'/research-inbox/{id}'
 * - the central Heratio storage path, never a hardcoded directory. The DB
 * stores only the path relative to that root so the install is portable.
 */
class InboxService
{
    public const TABLE = 'research_inbox_item';

    /** Sub-directory under the configured Heratio storage path for captured files. */
    private const UPLOAD_SUBDIR = 'research-inbox';

    /** Allowed values - the canonical source is the Dropdown Manager; these guard writes. */
    public const KINDS    = ['note', 'voice', 'email', 'clip', 'photo', 'file'];
    public const ORIGINS  = ['web', 'email-in', 'clipper', 'mobile'];
    public const STATUSES = ['inbox', 'triaged', 'archived'];

    /**
     * Defensive guard: the boot auto-installer creates the table, but a query
     * that races a fresh install should degrade to "empty" rather than throw.
     */
    private function tableReady(): bool
    {
        try {
            return Schema::hasTable(self::TABLE);
        } catch (\Throwable $e) {
            return false;
        }
    }

    // =========================================================================
    // READ
    // =========================================================================

    /**
     * List a researcher's inbox, newest first. Optional kind / status / project
     * filters. Returns an array of stdClass rows (never null).
     */
    public function listForResearcher(int $researcherId, array $filters = []): array
    {
        if (! $this->tableReady()) {
            return [];
        }

        try {
            $q = DB::table(self::TABLE)->where('researcher_id', $researcherId);

            if (! empty($filters['kind']) && in_array($filters['kind'], self::KINDS, true)) {
                $q->where('kind', $filters['kind']);
            }
            if (! empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
                $q->where('status', $filters['status']);
            } elseif (! array_key_exists('status', $filters)) {
                // Default view: the live inbox (exclude archived) unless a status
                // filter is explicitly supplied.
                $q->where('status', '!=', 'archived');
            }
            if (! empty($filters['project_id'])) {
                $q->where('project_id', (int) $filters['project_id']);
            }

            return $q->orderByDesc('captured_at')->orderByDesc('id')->get()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Per-status counts for the filter chips. Always returns the full keyset. */
    public function statusCounts(int $researcherId): array
    {
        $counts = ['inbox' => 0, 'triaged' => 0, 'archived' => 0];
        if (! $this->tableReady()) {
            return $counts;
        }
        try {
            $rows = DB::table(self::TABLE)
                ->where('researcher_id', $researcherId)
                ->select('status', DB::raw('COUNT(*) as c'))
                ->groupBy('status')
                ->pluck('c', 'status');
            foreach ($counts as $k => $_) {
                $counts[$k] = (int) ($rows[$k] ?? 0);
            }
        } catch (\Throwable $e) {
            // leave zeroes
        }
        return $counts;
    }

    /** Fetch a single item, scoped to its owning researcher (null if not theirs). */
    public function find(int $id, int $researcherId): ?object
    {
        if (! $this->tableReady()) {
            return null;
        }
        try {
            return DB::table(self::TABLE)
                ->where('id', $id)
                ->where('researcher_id', $researcherId)
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    // =========================================================================
    // WRITE - capture
    // =========================================================================

    /**
     * Capture an item into the inbox. Normalises kind/origin/status to the
     * allowed set, timestamps captured_at, and (optionally) stores an uploaded
     * file under the configured Heratio storage path. Returns the new row id,
     * or 0 if the table is not yet available.
     *
     * @param array              $data title/body/kind/origin/source_url/project_id
     * @param UploadedFile|null  $file optional attachment
     */
    public function capture(int $researcherId, array $data, ?UploadedFile $file = null): int
    {
        if (! $this->tableReady()) {
            return 0;
        }

        $kind   = in_array(($data['kind'] ?? 'note'), self::KINDS, true) ? $data['kind'] : 'note';
        $origin = in_array(($data['origin'] ?? 'web'), self::ORIGINS, true) ? $data['origin'] : 'web';

        $title = isset($data['title']) ? trim((string) $data['title']) : null;
        $body  = isset($data['body']) ? (string) $data['body'] : null;
        if ($title === '') {
            $title = null;
        }

        $sourceUrl = isset($data['source_url']) ? trim((string) $data['source_url']) : null;
        if ($sourceUrl === '' || ($sourceUrl !== null && ! preg_match('~^https?://~i', $sourceUrl))) {
            // Only persist a syntactically plausible http(s) URL; otherwise drop it.
            $sourceUrl = ($sourceUrl !== null && preg_match('~^https?://~i', $sourceUrl)) ? $sourceUrl : null;
        }

        $projectId = null;
        if (! empty($data['project_id'])) {
            $projectId = $this->resolveOwnedProjectId((int) $data['project_id'], $researcherId);
        }

        // We need the row id to namespace the upload directory, so insert first
        // then attach the stored file path.
        try {
            $now = date('Y-m-d H:i:s');
            $id  = (int) DB::table(self::TABLE)->insertGetId([
                'researcher_id'   => $researcherId,
                'project_id'      => $projectId,
                'kind'            => $kind,
                'title'           => $title,
                'body'            => $body,
                'origin'          => $origin,
                'source_url'      => $sourceUrl,
                'attachment_path' => null,
                'status'          => $projectId ? 'triaged' : 'inbox',
                'captured_at'     => $now,
                'created_at'      => $now,
            ]);

            if ($file instanceof UploadedFile && $file->isValid()) {
                $relPath = $this->storeAttachment($file, $id);
                if ($relPath !== null) {
                    DB::table(self::TABLE)->where('id', $id)->update(['attachment_path' => $relPath]);
                }
            }

            return $id;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Store an uploaded attachment under config('heratio.storage_path').
     * Returns the path RELATIVE to that root (so it is portable), or null on
     * failure. Never returns or stores a hardcoded absolute path.
     */
    private function storeAttachment(UploadedFile $file, int $itemId): ?string
    {
        $root = rtrim((string) config('heratio.storage_path'), '/');
        if ($root === '') {
            return null;
        }

        $relDir = self::UPLOAD_SUBDIR . '/' . $itemId;
        $absDir = $root . '/' . $relDir;

        if (! is_dir($absDir)) {
            @mkdir($absDir, 0775, true);
        }
        if (! is_dir($absDir) || ! is_writable($absDir)) {
            return null;
        }

        // Safe, collision-resistant filename; preserve the original extension.
        $ext  = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $ext  = preg_replace('~[^a-z0-9]+~', '', $ext) ?: 'bin';
        $base = Str::slug(pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'file';
        $name = $base . '-' . Str::random(8) . '.' . $ext;

        try {
            $file->move($absDir, $name);
        } catch (\Throwable $e) {
            return null;
        }

        return $relDir . '/' . $name;
    }

    /** Absolute filesystem path of an attachment, or null. Researcher-scoped via the row. */
    public function attachmentAbsolutePath(object $item): ?string
    {
        if (empty($item->attachment_path)) {
            return null;
        }
        $root = rtrim((string) config('heratio.storage_path'), '/');
        if ($root === '') {
            return null;
        }
        // Guard against any path traversal in stored data.
        $rel = ltrim(str_replace('..', '', (string) $item->attachment_path), '/');
        $abs = $root . '/' . $rel;
        return is_file($abs) ? $abs : null;
    }

    // =========================================================================
    // WRITE - triage
    // =========================================================================

    /** Mark an item triaged (researcher-scoped). Returns true on a real update. */
    public function markTriaged(int $id, int $researcherId): bool
    {
        return $this->setStatus($id, $researcherId, 'triaged');
    }

    /** Archive an item (researcher-scoped). */
    public function archive(int $id, int $researcherId): bool
    {
        return $this->setStatus($id, $researcherId, 'archived');
    }

    /** Restore an archived/triaged item back to the live inbox. */
    public function restore(int $id, int $researcherId): bool
    {
        return $this->setStatus($id, $researcherId, 'inbox');
    }

    private function setStatus(int $id, int $researcherId, string $status): bool
    {
        if (! in_array($status, self::STATUSES, true) || ! $this->tableReady()) {
            return false;
        }
        try {
            return DB::table(self::TABLE)
                ->where('id', $id)
                ->where('researcher_id', $researcherId)
                ->update(['status' => $status]) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Link an item to one of the researcher's own projects: sets project_id and
     * flips status to 'triaged'. Verifies the project is owned by the researcher
     * before linking. Returns true on success.
     */
    public function moveToProject(int $id, int $researcherId, int $projectId): bool
    {
        if (! $this->tableReady()) {
            return false;
        }
        $ownedId = $this->resolveOwnedProjectId($projectId, $researcherId);
        if ($ownedId === null) {
            return false;
        }
        try {
            return DB::table(self::TABLE)
                ->where('id', $id)
                ->where('researcher_id', $researcherId)
                ->update([
                    'project_id' => $ownedId,
                    'status'     => 'triaged',
                ]) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Return the project id only if it is owned by, or accepted-collaborated on,
     * by this researcher; null otherwise. Keeps move-to-project researcher-scoped.
     */
    private function resolveOwnedProjectId(int $projectId, int $researcherId): ?int
    {
        try {
            $exists = DB::table('research_project as p')
                ->where('p.id', $projectId)
                ->where(function ($q) use ($researcherId) {
                    $q->where('p.owner_id', $researcherId)
                      ->orWhereExists(function ($sub) use ($researcherId) {
                          $sub->select(DB::raw(1))
                              ->from('research_project_collaborator as c')
                              ->whereColumn('c.project_id', 'p.id')
                              ->where('c.researcher_id', $researcherId)
                              ->where('c.status', 'accepted');
                      });
                })
                ->exists();
            return $exists ? $projectId : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** The researcher's projects for the move-to-project picker (id + title). */
    public function projectsForPicker(int $researcherId): array
    {
        try {
            return DB::table('research_project as p')
                ->where(function ($q) use ($researcherId) {
                    $q->where('p.owner_id', $researcherId)
                      ->orWhereExists(function ($sub) use ($researcherId) {
                          $sub->select(DB::raw(1))
                              ->from('research_project_collaborator as c')
                              ->whereColumn('c.project_id', 'p.id')
                              ->where('c.researcher_id', $researcherId)
                              ->where('c.status', 'accepted');
                      });
                })
                ->orderByDesc('p.created_at')
                ->select('p.id', 'p.title')
                ->get()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    // =========================================================================
    // Dropdown options (Dropdown Manager is canonical; fall back to constants)
    // =========================================================================

    /** taxonomy => [['code'=>..,'label'=>..,'color'=>..,'icon'=>..], ...] */
    public function dropdownOptions(string $taxonomy, array $fallback): array
    {
        try {
            if (Schema::hasTable('ahg_dropdown')) {
                $rows = DB::table('ahg_dropdown')
                    ->where('taxonomy', $taxonomy)
                    ->where('is_active', 1)
                    ->orderBy('sort_order')
                    ->get(['code', 'label', 'color', 'icon']);
                if ($rows->count() > 0) {
                    return $rows->map(fn ($r) => (array) $r)->all();
                }
            }
        } catch (\Throwable $e) {
            // fall through to constant fallback
        }
        return array_map(
            fn ($code) => ['code' => $code, 'label' => ucwords(str_replace('-', ' ', $code)), 'color' => null, 'icon' => null],
            $fallback
        );
    }
}
