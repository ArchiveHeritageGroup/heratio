<?php

/**
 * CollaborationRealtimeService - Service for Heratio
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

/**
 * CollaborationRealtimeService
 *
 * Project-scoped collaboration with a polling fallback. No WebSocket broker
 * on this host, so the JS layer pings /research/projects/{id}/realtime/poll
 * every ~3s to fetch presence + comment deltas since a server-provided
 * cursor. When Reverb / Pusher lands we just swap the poll for a presence
 * channel subscription.
 *
 * Tables: research_collaboration_session, research_collaboration_presence,
 * research_evidence_comment.
 */
class CollaborationRealtimeService
{
    private const PRESENCE_STALE_SECONDS = 90;

    public function joinProject(int $projectId, int $researcherId, ?string $cursorTarget = null): array
    {
        $color = $this->colorFor($researcherId);

        $existing = DB::table('research_collaboration_presence')
            ->where('project_id', $projectId)
            ->where('researcher_id', $researcherId)
            ->first();

        if ($existing) {
            DB::table('research_collaboration_presence')->where('id', $existing->id)->update([
                'cursor_target' => $cursorTarget,
                'last_seen_at'  => date('Y-m-d H:i:s'),
                'user_color'    => $color,
            ]);
        } else {
            DB::table('research_collaboration_presence')->insert([
                'project_id'    => $projectId,
                'researcher_id' => $researcherId,
                'cursor_target' => $cursorTarget,
                'user_color'    => $color,
                'last_seen_at'  => date('Y-m-d H:i:s'),
            ]);
        }

        return ['color' => $color];
    }

    public function heartbeat(int $projectId, int $researcherId, ?string $cursorTarget = null): void
    {
        DB::table('research_collaboration_presence')
            ->where('project_id', $projectId)
            ->where('researcher_id', $researcherId)
            ->update([
                'cursor_target' => $cursorTarget,
                'last_seen_at'  => date('Y-m-d H:i:s'),
            ]);
    }

    public function presence(int $projectId): array
    {
        $cutoff = date('Y-m-d H:i:s', time() - self::PRESENCE_STALE_SECONDS);

        return DB::table('research_collaboration_presence as p')
            ->leftJoin('research_researcher as r', 'p.researcher_id', '=', 'r.id')
            ->where('p.project_id', $projectId)
            ->where('p.last_seen_at', '>=', $cutoff)
            ->select(
                'p.researcher_id',
                'p.cursor_target',
                'p.user_color',
                'p.last_seen_at',
                'r.first_name',
                'r.last_name'
            )
            ->orderBy('p.last_seen_at', 'desc')
            ->get()
            ->toArray();
    }

    public function postComment(int $projectId, int $authorId, array $data): int
    {
        return DB::table('research_evidence_comment')->insertGetId([
            'project_id'        => $projectId,
            'collection_id'     => $data['collection_id'] ?? null,
            'item_id'           => $data['item_id'] ?? null,
            'author_id'         => $authorId,
            'parent_comment_id' => $data['parent_comment_id'] ?? null,
            'body'              => $data['body'],
            'status'            => 'open',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
    }

    public function resolveComment(int $commentId, int $resolverId): bool
    {
        return DB::table('research_evidence_comment')->where('id', $commentId)->update([
            'status'      => 'resolved',
            'resolved_by' => $resolverId,
            'resolved_at' => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]) >= 0;
    }

    public function commentsSince(int $projectId, ?int $sinceCommentId = null): array
    {
        $q = DB::table('research_evidence_comment as c')
            ->leftJoin('research_researcher as r', 'c.author_id', '=', 'r.id')
            ->where('c.project_id', $projectId)
            ->select(
                'c.id',
                'c.collection_id',
                'c.item_id',
                'c.body',
                'c.status',
                'c.parent_comment_id',
                'c.created_at',
                'c.author_id',
                'r.first_name',
                'r.last_name'
            )
            ->orderBy('c.id');

        if ($sinceCommentId) {
            $q->where('c.id', '>', $sinceCommentId);
        }

        return $q->limit(200)->get()->toArray();
    }

    public function comments(int $projectId, ?int $collectionId = null): array
    {
        $q = DB::table('research_evidence_comment as c')
            ->leftJoin('research_researcher as r', 'c.author_id', '=', 'r.id')
            ->where('c.project_id', $projectId)
            ->select(
                'c.*',
                'r.first_name',
                'r.last_name',
                'r.email'
            )
            ->orderBy('c.created_at', 'desc');

        if ($collectionId) {
            $q->where('c.collection_id', $collectionId);
        }

        return $q->limit(200)->get()->toArray();
    }

    /**
     * Single endpoint the JS layer hits every ~3s. Returns presence,
     * latest comments since cursor, and the new cursor.
     */
    public function poll(int $projectId, ?int $sinceCommentId = null): array
    {
        $comments = $this->commentsSince($projectId, $sinceCommentId);
        $maxId = $sinceCommentId ?? 0;
        foreach ($comments as $c) {
            $maxId = max($maxId, (int) $c->id);
        }

        return [
            'presence'   => $this->presence($projectId),
            'comments'   => $comments,
            'cursor'     => $maxId,
            'server_ts'  => date('c'),
        ];
    }

    private function colorFor(int $researcherId): string
    {
        $palette = ['#4e79a7', '#f28e2b', '#e15759', '#76b7b2', '#59a14f', '#edc949', '#af7aa1', '#ff9da7', '#9c755f', '#bab0ab'];
        return $palette[$researcherId % count($palette)];
    }
}
