<?php

/**
 * NotebookService - Service for Heratio
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
 * NotebookService
 *
 * Private researcher notebooks: saved queries + AI outputs + pinned source
 * items in a researcher-owned scratchpad, separate from public research
 * projects. Notebooks can be promoted to a public research_project on click.
 *
 * Tables: research_notebook + research_notebook_item.
 */
class NotebookService
{
    public const ITEM_TYPES = ['saved_query', 'ai_output', 'source_pin', 'note'];

    public function listForResearcher(int $researcherId): array
    {
        return DB::table('research_notebook')
            ->where('researcher_id', $researcherId)
            ->orderBy('sort_order')
            ->orderByDesc('updated_at')
            ->get()
            ->toArray();
    }

    public function get(int $id): ?object
    {
        return DB::table('research_notebook')->where('id', $id)->first();
    }

    public function getItems(int $notebookId): array
    {
        return DB::table('research_notebook_item')
            ->where('notebook_id', $notebookId)
            ->orderByDesc('pinned')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    public function create(int $researcherId, array $data): int
    {
        return DB::table('research_notebook')->insertGetId([
            'researcher_id' => $researcherId,
            'title'         => $data['title'] ?? 'Untitled notebook',
            'summary'       => $data['summary'] ?? null,
            'cover_object_id' => $data['cover_object_id'] ?? null,
            'sort_order'    => (int) ($data['sort_order'] ?? 0),
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    public function update(int $id, array $data): bool
    {
        $allowed = ['title', 'summary', 'cover_object_id', 'sort_order'];
        $patch = array_intersect_key($data, array_flip($allowed));
        if (empty($patch)) return true;
        $patch['updated_at'] = date('Y-m-d H:i:s');
        return DB::table('research_notebook')->where('id', $id)->update($patch) >= 0;
    }

    public function delete(int $id): bool
    {
        DB::table('research_notebook_item')->where('notebook_id', $id)->delete();
        return DB::table('research_notebook')->where('id', $id)->delete() > 0;
    }

    public function addItem(int $notebookId, array $data): int
    {
        $itemType = $data['item_type'] ?? 'note';
        if (!in_array($itemType, self::ITEM_TYPES, true)) {
            $itemType = 'note';
        }

        $maxSort = (int) DB::table('research_notebook_item')->where('notebook_id', $notebookId)->max('sort_order');

        $payload = isset($data['ai_output_payload']) && is_array($data['ai_output_payload'])
            ? json_encode($data['ai_output_payload'])
            : ($data['ai_output_payload'] ?? null);

        $id = DB::table('research_notebook_item')->insertGetId([
            'notebook_id'       => $notebookId,
            'item_type'         => $itemType,
            'title'             => $data['title'] ?? null,
            'body'              => $data['body']  ?? null,
            'source_object_id'  => $data['source_object_id']  ?? null,
            'saved_search_id'   => $data['saved_search_id']   ?? null,
            'ai_output_payload' => $payload,
            'pinned'            => !empty($data['pinned']) ? 1 : 0,
            'sort_order'        => $maxSort + 1,
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        $this->touch($notebookId);
        return (int) $id;
    }

    public function updateItem(int $itemId, array $data): bool
    {
        $allowed = ['title', 'body', 'pinned', 'sort_order', 'source_object_id', 'saved_search_id'];
        $patch = array_intersect_key($data, array_flip($allowed));
        if (isset($patch['pinned'])) $patch['pinned'] = $patch['pinned'] ? 1 : 0;
        if (empty($patch)) return true;
        $patch['updated_at'] = date('Y-m-d H:i:s');
        $row = DB::table('research_notebook_item')->where('id', $itemId)->first();
        $ok = DB::table('research_notebook_item')->where('id', $itemId)->update($patch) >= 0;
        if ($row) $this->touch((int) $row->notebook_id);
        return $ok;
    }

    public function removeItem(int $itemId): bool
    {
        $row = DB::table('research_notebook_item')->where('id', $itemId)->first();
        $ok = DB::table('research_notebook_item')->where('id', $itemId)->delete() > 0;
        if ($row) $this->touch((int) $row->notebook_id);
        return $ok;
    }

    /**
     * Promote a private notebook to a public research project.
     * The notebook's title/summary become the project title/description; each
     * source_pin item becomes a collection item in a new collection under the
     * project; ai_output items are exported as a Studio artefact stub for
     * later promotion to a report.
     */
    public function promoteToProject(int $notebookId, int $researcherId): ?int
    {
        $notebook = $this->get($notebookId);
        if (!$notebook || (int) $notebook->researcher_id !== $researcherId) return null;
        if (!empty($notebook->promoted_to_project_id)) return (int) $notebook->promoted_to_project_id;

        $items = $this->getItems($notebookId);

        DB::beginTransaction();
        try {
            $projectId = DB::table('research_project')->insertGetId([
                'owner_id'    => $researcherId,
                'title'       => $notebook->title ?: 'Promoted notebook',
                'description' => $notebook->summary,
                'project_type' => 'public',
                'status'      => 'active',
                'created_at'  => date('Y-m-d H:i:s'),
            ]);

            DB::table('research_project_collaborator')->insert([
                'project_id'    => $projectId,
                'researcher_id' => $researcherId,
                'role'          => 'owner',
                'status'        => 'accepted',
                'invited_at'    => date('Y-m-d H:i:s'),
                'accepted_at'   => date('Y-m-d H:i:s'),
            ]);

            $sourcePins = array_filter($items, fn($i) => $i->item_type === 'source_pin' && !empty($i->source_object_id));
            if (!empty($sourcePins)) {
                $collectionId = DB::table('research_collection')->insertGetId([
                    'researcher_id' => $researcherId,
                    'project_id'    => $projectId,
                    'name'          => 'Promoted from notebook: ' . ($notebook->title ?: '#' . $notebookId),
                    'description'   => 'Auto-created when this notebook was promoted to a project.',
                    'is_public'     => 0,
                    'share_token'   => bin2hex(random_bytes(32)),
                    'created_at'    => date('Y-m-d H:i:s'),
                ]);

                foreach ($sourcePins as $pin) {
                    try {
                        DB::table('research_collection_item')->insert([
                            'collection_id' => $collectionId,
                            'object_id'     => (int) $pin->source_object_id,
                            'object_type'   => 'information_object',
                            'notes'         => $pin->title,
                            'created_at'    => date('Y-m-d H:i:s'),
                        ]);
                    } catch (\Throwable $e) {
                        // duplicate (unique_item) - skip
                    }
                }
            }

            DB::table('research_notebook')->where('id', $notebookId)->update([
                'promoted_to_project_id' => $projectId,
                'promoted_at'            => date('Y-m-d H:i:s'),
                'updated_at'             => date('Y-m-d H:i:s'),
            ]);

            DB::commit();
            return (int) $projectId;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function touch(int $notebookId): void
    {
        DB::table('research_notebook')->where('id', $notebookId)->update(['updated_at' => date('Y-m-d H:i:s')]);
    }
}
