<?php

/**
 * RecordMergeService - merge one archival description into another.
 *
 * heratio#1533: the dedupe "merge" only marked a pair as merged and promised a
 * background task that did not exist. This moves everything the duplicate
 * (the loser) carries to the record kept (the winner) and then deletes the
 * loser:
 *
 *   - child records (the whole subtree, through the shared move),
 *   - events (creators, dates), notes, alternative titles and properties,
 *   - access points (a term the winner already has is not doubled),
 *   - relations (rights, accessions, physical objects, related records;
 *     a relation between the two records themselves is dropped),
 *   - the digital object (refused when both records have one),
 *   - custom field values the winner does not already hold.
 *
 * The winner keeps its own field values. The loser's full snapshot and old
 * slug are kept in ahg_merge_log, and the description page redirects the old
 * URL to the winner from that log.
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

namespace AhgDedupe\Services;

use AhgInformationObjectManage\Services\InformationObjectService;
use Illuminate\Support\Facades\DB;

class RecordMergeService
{
    private const ROOT_ID = 1;

    /**
     * Merge $loserId into $winnerId. Returns the ahg_merge_log id.
     *
     * @throws \DomainException when the pair cannot be merged
     */
    public function merge(int $winnerId, int $loserId, ?int $userId = null, ?int $detectionId = null, ?string $notes = null): int
    {
        $this->guard($winnerId, $loserId);

        $snapshot = class_exists(\AhgVersionControl\Services\SnapshotBuilder::class)
            ? app(\AhgVersionControl\Services\SnapshotBuilder::class)->buildForInformationObject($loserId)
            : [];
        $slugs = DB::table('slug')->where('object_id', $loserId)->pluck('slug')->all();

        return DB::transaction(function () use ($winnerId, $loserId, $userId, $detectionId, $notes, $snapshot, $slugs) {
            $moved = [];

            $children = DB::table('information_object')->where('parent_id', $loserId)->orderBy('lft')->pluck('id');
            foreach ($children as $childId) {
                InformationObjectService::moveUnder((int) $childId, $winnerId);
            }
            $moved['children'] = $children->count();

            foreach (['event', 'note', 'other_name', 'property'] as $table) {
                $moved[$table] = DB::table($table)->where('object_id', $loserId)->update(['object_id' => $winnerId]);
            }

            // Access points: skip a term the winner already carries.
            $winnerTerms = DB::table('object_term_relation')->where('object_id', $winnerId)->pluck('term_id')->all();
            $doubled = DB::table('object_term_relation')->where('object_id', $loserId)->whereIn('term_id', $winnerTerms)->pluck('id')->all();
            $this->deleteObjects('object_term_relation', $doubled);
            $moved['access_points'] = DB::table('object_term_relation')->where('object_id', $loserId)->update(['object_id' => $winnerId]);

            // Relations: drop the ones between the two records, re-point the rest.
            $between = DB::table('relation')
                ->where(fn ($q) => $q->where('subject_id', $loserId)->where('object_id', $winnerId))
                ->orWhere(fn ($q) => $q->where('subject_id', $winnerId)->where('object_id', $loserId))
                ->pluck('id')->all();
            $this->deleteObjects('relation', $between, 'relation_i18n');
            $moved['relations'] = DB::table('relation')->where('subject_id', $loserId)->update(['subject_id' => $winnerId])
                + DB::table('relation')->where('object_id', $loserId)->update(['object_id' => $winnerId]);

            $digitalObjects = DB::table('digital_object')->where('object_id', $loserId)->pluck('id')->all();
            DB::table('digital_object')->whereIn('id', $digitalObjects)->update(['object_id' => $winnerId]);

            $held = DB::table('custom_field_value')->where('object_id', $winnerId)->pluck('field_definition_id')->all();
            $moved['custom_fields'] = DB::table('custom_field_value')->where('object_id', $loserId)
                ->whereNotIn('field_definition_id', $held)->update(['object_id' => $winnerId]);

            InformationObjectService::delete($loserId);

            $logId = (int) DB::table('ahg_merge_log')->insertGetId([
                'primary_id' => $winnerId,
                'merged_id' => $loserId,
                'detection_id' => $detectionId,
                'field_choices_json' => json_encode(['kept' => 'primary', 'moved' => $moved]),
                'slugs_redirected' => json_encode($slugs),
                'digital_objects_moved' => json_encode($digitalObjects),
                'loser_snapshot_json' => json_encode($snapshot),
                'merged_by' => (int) ($userId ?? 0),
                'merged_at' => now(),
                'notes' => $notes,
            ]);

            // This pair is merged; any other pair naming the deleted record is moot.
            $pairs = DB::table('ahg_duplicate_detection')
                ->where(fn ($q) => $q->whereIn('record_a_id', [$loserId])->orWhereIn('record_b_id', [$loserId]));
            (clone $pairs)->where(fn ($q) => $q->whereIn('record_a_id', [$winnerId])->orWhereIn('record_b_id', [$winnerId]))
                ->update(['status' => 'merged', 'reviewed_by' => $userId, 'reviewed_at' => now()]);
            (clone $pairs)->where('status', '!=', 'merged')
                ->update(['status' => 'dismissed', 'reviewed_by' => $userId, 'reviewed_at' => now(), 'review_notes' => "Record {$loserId} was merged into {$winnerId}"]);

            \AhgCore\Support\AuditLog::captureMutation($winnerId, 'information_object', 'merge', [
                'data' => ['merged_id' => $loserId, 'merge_log_id' => $logId, 'moved' => $moved],
            ]);

            return $logId;
        });
    }

    /**
     * The winner of the most recent merge that retired $slug, following
     * later merges of that winner, or null. Used to redirect old URLs.
     */
    public static function redirectTarget(string $slug): ?string
    {
        try {
            for ($hops = 0; $hops < 5; $hops++) {
                $winnerId = DB::table('ahg_merge_log')
                    ->whereRaw('JSON_CONTAINS(slugs_redirected, JSON_QUOTE(?))', [$slug])
                    ->orderByDesc('id')->value('primary_id');
                if (! $winnerId) {
                    return null;
                }
                $next = DB::table('slug')->where('object_id', $winnerId)->value('slug');
                if ($next) {
                    return $next;
                }
                $slug = (string) DB::table('ahg_merge_log')->where('merged_id', $winnerId)->orderByDesc('id')
                    ->value(DB::raw('JSON_UNQUOTE(JSON_EXTRACT(slugs_redirected, "$[0]"))'));
                if ($slug === '') {
                    return null;
                }
            }
        } catch (\Throwable $e) {
            // ahg-dedupe not installed: no merge log, no redirects.
        }

        return null;
    }

    private function guard(int $winnerId, int $loserId): void
    {
        if ($winnerId === $loserId) {
            throw new \DomainException(__('A record cannot be merged into itself.'));
        }
        foreach ([$winnerId, $loserId] as $id) {
            if ($id === self::ROOT_ID || ! DB::table('information_object')->where('id', $id)->exists()) {
                throw new \DomainException(__('Record :id is not an archival description that can be merged.', ['id' => $id]));
            }
        }
        $loserSubtree = app(\AhgCore\Services\HierarchyQueryService::class)->descendantIds('information_object', $loserId, false);
        if (in_array($winnerId, array_map('intval', $loserSubtree), true)) {
            throw new \DomainException(__('The record kept is inside the duplicate\'s hierarchy; move it out first.'));
        }
        if (DB::table('digital_object')->where('object_id', $winnerId)->exists()
            && DB::table('digital_object')->where('object_id', $loserId)->exists()) {
            throw new \DomainException(__('Both records have a digital object. Remove or move one of them first.'));
        }
    }

    /** Delete rows that are also `object` rows (relation, object_term_relation). */
    private function deleteObjects(string $table, array $ids, ?string $i18n = null): void
    {
        if (! $ids) {
            return;
        }
        if ($i18n) {
            DB::table($i18n)->whereIn('id', $ids)->delete();
        }
        DB::table($table)->whereIn('id', $ids)->delete();
        DB::table('object')->whereIn('id', $ids)->delete();
    }
}
