<?php

/**
 * VocabSyncService - vocabulary sync between federation peers
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

namespace AhgFederation\Services;

use AhgTermTaxonomy\Services\TermService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VocabSyncService
{
    public const DIRECTION_PULL = 'pull';
    public const DIRECTION_PUSH = 'push';
    public const DIRECTION_BIDIRECTIONAL = 'bidirectional';

    public const CONFLICT_PREFER_LOCAL = 'prefer_local';
    public const CONFLICT_PREFER_REMOTE = 'prefer_remote';
    public const CONFLICT_SKIP = 'skip';
    public const CONFLICT_MERGE = 'merge';

    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const MAPPING_MATCHED = 'matched';
    public const MAPPING_CREATED = 'created';
    public const MAPPING_CONFLICT = 'conflict';
    public const MAPPING_SKIPPED = 'skipped';

    public const CHANGE_TERM_ADDED = 'term_added';
    public const CHANGE_TERM_UPDATED = 'term_updated';
    public const CHANGE_TERM_DELETED = 'term_deleted';
    public const CHANGE_TERM_MOVED = 'term_moved';
    public const CHANGE_RELATION_ADDED = 'relation_added';
    public const CHANGE_RELATION_REMOVED = 'relation_removed';

    protected TermService $termService;

    public function __construct(?TermService $termService = null)
    {
        $this->termService = $termService ?? new TermService();
    }

    // ─── EXPORT ──────────────────────────────────────────────────────────

    /**
     * Export a taxonomy with all terms + per-culture translations.
     *
     * Returns the heritage-vocab-1.0 envelope used by importTaxonomy().
     */
    public function exportTaxonomy(int $taxonomyId, ?string $culture = null): array
    {
        $culture = $culture ?: app()->getLocale();

        $taxonomy = DB::table('taxonomy as t')
            ->leftJoin('taxonomy_i18n as ti', function ($j) use ($culture) {
                $j->on('t.id', '=', 'ti.id')->where('ti.culture', '=', $culture);
            })
            ->where('t.id', $taxonomyId)
            ->select('t.id', 't.usage', 'ti.name')
            ->first();

        if (!$taxonomy) {
            throw new \RuntimeException("Taxonomy not found: $taxonomyId");
        }

        $terms = DB::table('term as t')
            ->leftJoin('term_i18n as ti', function ($j) use ($culture) {
                $j->on('t.id', '=', 'ti.id')->where('ti.culture', '=', $culture);
            })
            ->where('t.taxonomy_id', $taxonomyId)
            ->select('t.id', 't.parent_id', 't.code', 'ti.name')
            ->orderBy('t.lft')
            ->get();

        // Pull every translation for these terms in one query.
        $termIds = $terms->pluck('id')->all();
        $translationsByTerm = [];
        if (!empty($termIds)) {
            $rows = DB::table('term_i18n')->whereIn('id', $termIds)->get();
            foreach ($rows as $row) {
                $translationsByTerm[$row->id][$row->culture] = $row->name;
            }
        }

        $termMap = [];
        foreach ($terms as $term) {
            $termMap[$term->id] = [
                'id' => (int) $term->id,
                'code' => $term->code,
                'name' => $term->name,
                'parentId' => $term->parent_id,
                'translations' => $translationsByTerm[$term->id] ?? [],
                'children' => [],
            ];
        }

        // Stitch children into parents and collect the roots.
        $roots = [];
        foreach ($termMap as $id => &$term) {
            $parentId = $term['parentId'];
            if ($parentId && isset($termMap[$parentId])) {
                $termMap[$parentId]['children'][] = &$term;
            } else {
                $roots[] = &$term;
            }
        }
        unset($term);

        return [
            'taxonomy' => [
                'id' => (int) $taxonomy->id,
                'name' => $taxonomy->name,
                'usage' => $taxonomy->usage ?? null,
            ],
            'terms' => $roots,
            'termCount' => $terms->count(),
            'exportedAt' => date('c'),
            'exportFormat' => 'heritage-vocab-1.0',
        ];
    }

    // ─── IMPORT ──────────────────────────────────────────────────────────

    /**
     * Import a taxonomy from an exportTaxonomy() envelope.
     *
     * Options: conflictResolution, targetTaxonomyId, peerId, culture
     */
    public function importTaxonomy(array $data, array $options = []): VocabSyncResult
    {
        $culture = $options['culture'] ?? app()->getLocale();
        $conflictResolution = $options['conflictResolution'] ?? self::CONFLICT_SKIP;
        $targetTaxonomyId = $options['targetTaxonomyId'] ?? null;
        $peerId = $options['peerId'] ?? null;

        $stats = [
            'added' => 0,
            'updated' => 0,
            'skipped' => 0,
            'conflicts' => 0,
            'errors' => [],
        ];

        if (!$targetTaxonomyId) {
            $existing = DB::table('taxonomy_i18n')
                ->where('name', $data['taxonomy']['name'])
                ->where('culture', $culture)
                ->first();
            $targetTaxonomyId = $existing ? (int) $existing->id : $this->createTaxonomy($data['taxonomy'], $culture);
        }

        $this->importTermsRecursive(
            $data['terms'] ?? [],
            (int) $targetTaxonomyId,
            null,
            $conflictResolution,
            $culture,
            $peerId,
            $stats,
        );

        return new VocabSyncResult(
            taxonomyId: (int) $targetTaxonomyId,
            taxonomyName: $data['taxonomy']['name'] ?? '',
            direction: 'import',
            stats: $stats,
        );
    }

    protected function importTermsRecursive(
        array $terms,
        int $taxonomyId,
        ?int $parentTermId,
        string $conflictResolution,
        string $culture,
        ?int $peerId,
        array &$stats,
    ): void {
        foreach ($terms as $termData) {
            try {
                $result = $this->importTerm($termData, $taxonomyId, $parentTermId, $conflictResolution, $culture, $peerId);

                $stats[$result['statKey']] = ($stats[$result['statKey']] ?? 0) + 1;

                if (!empty($termData['children']) && $result['termId']) {
                    $this->importTermsRecursive(
                        $termData['children'],
                        $taxonomyId,
                        $result['termId'],
                        $conflictResolution,
                        $culture,
                        $peerId,
                        $stats,
                    );
                }
            } catch (\Throwable $e) {
                $stats['errors'][] = [
                    'term' => $termData['name'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }
    }

    /**
     * Import a single term. Returns {statKey, termId}.
     */
    protected function importTerm(
        array $termData,
        int $taxonomyId,
        ?int $parentTermId,
        string $conflictResolution,
        string $culture,
        ?int $peerId,
    ): array {
        $name = $termData['name'] ?? null;
        if (!$name) {
            return ['statKey' => 'skipped', 'termId' => null];
        }

        $existing = DB::table('term as t')
            ->join('term_i18n as ti', function ($j) use ($culture) {
                $j->on('t.id', '=', 'ti.id')->where('ti.culture', '=', $culture);
            })
            ->where('t.taxonomy_id', $taxonomyId)
            ->where('ti.name', $name)
            ->select('t.id')
            ->first();

        if ($existing) {
            $existingId = (int) $existing->id;

            switch ($conflictResolution) {
                case self::CONFLICT_SKIP:
                case self::CONFLICT_PREFER_LOCAL:
                    $this->recordMapping($peerId, $existingId, $termData, $taxonomyId, self::MAPPING_MATCHED);
                    return ['statKey' => 'skipped', 'termId' => $existingId];

                case self::CONFLICT_PREFER_REMOTE:
                    $this->updateTerm($existingId, $termData, $culture);
                    $this->recordMapping($peerId, $existingId, $termData, $taxonomyId, self::MAPPING_MATCHED);
                    return ['statKey' => 'updated', 'termId' => $existingId];

                case self::CONFLICT_MERGE:
                    $this->mergeTerm($existingId, $termData);
                    $this->recordMapping($peerId, $existingId, $termData, $taxonomyId, self::MAPPING_MATCHED);
                    return ['statKey' => 'updated', 'termId' => $existingId];

                default:
                    $this->recordMapping($peerId, $existingId, $termData, $taxonomyId, self::MAPPING_CONFLICT);
                    return ['statKey' => 'conflicts', 'termId' => $existingId];
            }
        }

        $newId = (int) $this->termService->create([
            'taxonomy_id' => $taxonomyId,
            'name' => $name,
            'code' => $termData['code'] ?? null,
            'parent_id' => $parentTermId,
        ], $culture);

        // Apply remaining culture translations beyond the create-culture row.
        if (!empty($termData['translations'])) {
            foreach ($termData['translations'] as $cult => $translation) {
                if ($cult === $culture) continue;
                $this->upsertTermI18n($newId, $cult, $translation);
            }
        }

        $this->recordMapping($peerId, $newId, $termData, $taxonomyId, self::MAPPING_CREATED);

        return ['statKey' => 'added', 'termId' => $newId];
    }

    protected function updateTerm(int $termId, array $termData, string $culture): void
    {
        $update = [];
        if (!empty($termData['code'])) { $update['code'] = $termData['code']; }
        if (!empty($termData['name'])) { $update['name'] = $termData['name']; }

        if ($update) {
            $this->termService->update($termId, $update, $culture);
        }

        if (!empty($termData['translations'])) {
            foreach ($termData['translations'] as $cult => $translation) {
                $this->upsertTermI18n($termId, $cult, $translation);
            }
        }
    }

    protected function mergeTerm(int $termId, array $termData): void
    {
        // Merge only adds translations missing locally. Existing values are preserved.
        if (empty($termData['translations'])) {
            return;
        }
        foreach ($termData['translations'] as $cult => $translation) {
            $exists = DB::table('term_i18n')->where('id', $termId)->where('culture', $cult)->exists();
            if (!$exists) {
                $this->upsertTermI18n($termId, $cult, $translation);
            }
        }
    }

    protected function upsertTermI18n(int $termId, string $culture, string $name): void
    {
        $exists = DB::table('term_i18n')->where('id', $termId)->where('culture', $culture)->exists();
        if ($exists) {
            DB::table('term_i18n')->where('id', $termId)->where('culture', $culture)->update(['name' => $name]);
        } else {
            DB::table('term_i18n')->insert(['id' => $termId, 'culture' => $culture, 'name' => $name]);
        }
    }

    protected function createTaxonomy(array $data, string $culture): int
    {
        return DB::transaction(function () use ($data, $culture) {
            $newId = DB::table('taxonomy')->insertGetId([
                'usage' => $data['usage'] ?? null,
                'parent_id' => null,
                'source_culture' => $culture,
            ]);

            DB::table('taxonomy_i18n')->insert([
                'id' => $newId,
                'culture' => $culture,
                'name' => $data['name'],
                'note' => $data['note'] ?? null,
            ]);

            return $newId;
        });
    }

    protected function recordMapping(?int $peerId, int $localTermId, array $termData, int $taxonomyId, string $status): void
    {
        if (!$peerId) {
            return;
        }
        $remoteId = (string) ($termData['id'] ?? '');
        if ($remoteId === '') {
            return;
        }

        DB::table('federation_term_mapping')->updateOrInsert(
            ['peer_id' => $peerId, 'local_term_id' => $localTermId],
            [
                'remote_term_id' => $remoteId,
                'remote_term_name' => $termData['name'] ?? '',
                'taxonomy_id' => $taxonomyId,
                'mapping_status' => $status,
                'last_synced_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    // ─── PEER SYNC ───────────────────────────────────────────────────────

    /**
     * Pull a vocabulary from a peer. Push is not yet wired (Heratio doesn't
     * yet expose a /api/federation/vocab/import endpoint to receive pushes).
     */
    public function syncWithPeer(int $peerId, int $taxonomyId, string $direction = self::DIRECTION_PULL): VocabSyncResult
    {
        $config = DB::table('federation_vocab_sync')
            ->where('peer_id', $peerId)
            ->where('taxonomy_id', $taxonomyId)
            ->first();
        if (!$config) {
            throw new \RuntimeException('Vocabulary sync not configured for this peer/taxonomy.');
        }

        $peer = DB::table('federation_peer')->where('id', $peerId)->first();
        if (!$peer || !$peer->is_active) {
            throw new \RuntimeException('Peer not found or inactive.');
        }

        if ($direction === self::DIRECTION_PUSH) {
            throw new \RuntimeException('Vocabulary push is not yet supported by Heratio peers.');
        }

        $sessionId = $this->startSyncSession($peerId, $taxonomyId, $direction);

        try {
            $result = $this->pullFromPeer($peer, $taxonomyId, $config->conflict_resolution);

            $this->completeSyncSession($sessionId, self::STATUS_COMPLETED, $result->stats);

            DB::table('federation_vocab_sync')
                ->where('peer_id', $peerId)
                ->where('taxonomy_id', $taxonomyId)
                ->update([
                    'last_sync_at' => now(),
                    'last_sync_status' => self::STATUS_COMPLETED,
                    'last_sync_terms_added' => $result->stats['added'],
                    'last_sync_terms_updated' => $result->stats['updated'],
                    'last_sync_conflicts' => $result->stats['conflicts'],
                    'updated_at' => now(),
                ]);

            return $result;
        } catch (\Throwable $e) {
            $this->completeSyncSession($sessionId, self::STATUS_FAILED, [], $e->getMessage());
            throw $e;
        }
    }

    protected function pullFromPeer(object $peer, int $taxonomyId, string $conflictResolution): VocabSyncResult
    {
        $url = rtrim($peer->base_url, '/') . '/api/federation/vocab/' . $taxonomyId;

        $headers = ['Accept: application/json'];
        if (!empty($peer->api_key)) {
            $headers[] = 'X-API-Key: ' . $peer->api_key;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $httpCode !== 200) {
            throw new \RuntimeException("Peer fetch failed (HTTP $httpCode): " . ($error ?: 'no body'));
        }

        $data = json_decode((string) $body, true);
        if (!is_array($data) || empty($data['taxonomy'])) {
            throw new \RuntimeException('Peer returned invalid vocabulary envelope.');
        }

        return $this->importTaxonomy($data, [
            'conflictResolution' => $conflictResolution,
            'targetTaxonomyId' => $taxonomyId,
            'peerId' => (int) $peer->id,
        ]);
    }

    protected function startSyncSession(int $peerId, int $taxonomyId, string $direction): int
    {
        return DB::table('federation_vocab_sync_log')->insertGetId([
            'peer_id' => $peerId,
            'taxonomy_id' => $taxonomyId,
            'sync_direction' => $direction,
            'started_at' => now(),
            'status' => self::STATUS_RUNNING,
            'initiated_by' => auth()->id(),
        ]);
    }

    protected function completeSyncSession(int $sessionId, string $status, array $stats = [], ?string $error = null): void
    {
        DB::table('federation_vocab_sync_log')->where('id', $sessionId)->update([
            'completed_at' => now(),
            'status' => $status,
            'terms_added' => $stats['added'] ?? 0,
            'terms_updated' => $stats['updated'] ?? 0,
            'terms_skipped' => $stats['skipped'] ?? 0,
            'conflicts' => $stats['conflicts'] ?? 0,
            'error_message' => $error,
        ]);
    }

    // ─── CHANGE TRACKING ─────────────────────────────────────────────────

    public function recordChange(
        int $taxonomyId,
        ?int $termId,
        string $changeType,
        ?string $oldValue,
        ?string $newValue,
        ?int $userId = null,
    ): int {
        return DB::table('federation_vocab_change')->insertGetId([
            'taxonomy_id' => $taxonomyId,
            'term_id' => $termId,
            'change_type' => $changeType,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'created_by' => $userId ?? auth()->id(),
            'created_at' => now(),
        ]);
    }

    public function getUnpropagatedChanges(int $taxonomyId, int $peerId): Collection
    {
        return DB::table('federation_vocab_change')
            ->where('taxonomy_id', $taxonomyId)
            ->where(function ($q) use ($peerId) {
                $q->whereNull('propagated_to_peers')
                    ->orWhereRaw('NOT JSON_CONTAINS(propagated_to_peers, ?)', [json_encode($peerId)]);
            })
            ->orderBy('created_at')
            ->get();
    }

    public function markPropagated(array $changeIds, int $peerId): void
    {
        if (empty($changeIds)) return;

        $rows = DB::table('federation_vocab_change')->whereIn('id', $changeIds)->get();
        foreach ($rows as $change) {
            $list = $change->propagated_to_peers
                ? json_decode($change->propagated_to_peers, true)
                : [];
            if (!is_array($list)) { $list = []; }
            if (!in_array($peerId, $list, true)) {
                $list[] = $peerId;
                DB::table('federation_vocab_change')
                    ->where('id', $change->id)
                    ->update(['propagated_to_peers' => json_encode($list)]);
            }
        }
    }

    // ─── CONFIG ──────────────────────────────────────────────────────────

    public function configureSyncForPeer(int $peerId, int $taxonomyId, array $settings): bool
    {
        $allowedFields = [
            'sync_direction', 'sync_enabled', 'conflict_resolution', 'sync_interval_hours',
        ];
        $data = ['updated_at' => now()];
        foreach ($allowedFields as $f) {
            if (array_key_exists($f, $settings)) {
                $data[$f] = $settings[$f];
            }
        }

        return (bool) DB::table('federation_vocab_sync')->updateOrInsert(
            ['peer_id' => $peerId, 'taxonomy_id' => $taxonomyId],
            $data,
        );
    }

    public function getSyncConfig(int $peerId, ?int $taxonomyId = null, ?string $culture = null): Collection
    {
        $culture = $culture ?: app()->getLocale();

        $q = DB::table('federation_vocab_sync as vs')
            ->join('taxonomy as t', 'vs.taxonomy_id', '=', 't.id')
            ->leftJoin('taxonomy_i18n as ti', function ($j) use ($culture) {
                $j->on('t.id', '=', 'ti.id')->where('ti.culture', '=', $culture);
            })
            ->where('vs.peer_id', $peerId)
            ->select('vs.*', 'ti.name as taxonomy_name');

        if ($taxonomyId) { $q->where('vs.taxonomy_id', $taxonomyId); }

        return $q->get();
    }

    public function getAvailableTaxonomies(?string $culture = null): Collection
    {
        $culture = $culture ?: app()->getLocale();

        return DB::table('taxonomy as t')
            ->leftJoin('taxonomy_i18n as ti', function ($j) use ($culture) {
                $j->on('t.id', '=', 'ti.id')->where('ti.culture', '=', $culture);
            })
            ->select('t.id', 'ti.name', DB::raw('(SELECT COUNT(*) FROM term WHERE taxonomy_id = t.id) as term_count'))
            ->orderBy('ti.name')
            ->get();
    }
}

class VocabSyncResult
{
    public function __construct(
        public readonly int $taxonomyId,
        public readonly string $taxonomyName,
        public readonly string $direction,
        public readonly array $stats,
    ) {}

    public function isSuccessful(): bool
    {
        return empty($this->stats['errors']);
    }

    public function getSummary(): string
    {
        return sprintf(
            '%s sync of "%s": %d added, %d updated, %d skipped, %d conflicts',
            ucfirst($this->direction),
            $this->taxonomyName,
            $this->stats['added'] ?? 0,
            $this->stats['updated'] ?? 0,
            $this->stats['skipped'] ?? 0,
            $this->stats['conflicts'] ?? 0,
        );
    }

    public function toArray(): array
    {
        return [
            'taxonomyId' => $this->taxonomyId,
            'taxonomyName' => $this->taxonomyName,
            'direction' => $this->direction,
            'stats' => $this->stats,
            'successful' => $this->isSuccessful(),
            'summary' => $this->getSummary(),
        ];
    }
}
