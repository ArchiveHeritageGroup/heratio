<?php

/**
 * DescriptionSuggestionService - Heratio
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

namespace AhgAiServices\Services;

use Illuminate\Support\Facades\DB;

/**
 * AI > Suggest review over ahg_description_suggestion (heratio#1559, #1564):
 * text proposed for a record - a field, a translation of a field, or the
 * transcript of a file - waits here until an archivist approves, edits or
 * rejects it. source_data says what approval writes:
 *
 *   {kind: field,       field, culture}  the field, in the language its current text was read in
 *   {kind: translation, field, culture}  the field in that language (row created when missing)
 *   {kind: transcript,  digital_object_id, image}  the file's "transcript" property,
 *                                         which GET /descriptions/{slug}/text and the record read
 *
 * plus evidence, sources and proposed_via, shown to the reviewer.
 */
class DescriptionSuggestionService
{
    public const FIELDS = ['title', 'scope_and_content', 'archival_history', 'extent_and_medium', 'arrangement', 'access_conditions', 'reproduction_conditions'];

    public const STATUSES = ['pending', 'approved', 'edited', 'rejected'];

    /** Suggestions with the record's title and slug and who proposed them, newest first. */
    public function paginate(string $status, int $perPage = 20)
    {
        $culture = app()->getLocale();
        $page = DB::table('ahg_description_suggestion as s')
            ->leftJoin('slug', 'slug.object_id', '=', 's.object_id')
            ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('ioi.id', '=', 's.object_id')->where('ioi.culture', $culture);
            })
            ->leftJoin('user as u', 'u.id', '=', 's.created_by')
            ->leftJoin('user as r', 'r.id', '=', 's.reviewed_by')
            ->where('s.status', $status)
            ->orderByDesc('s.id')
            ->select('s.*', 'slug.slug', 'ioi.title as record_title', 'u.username as proposer', 'r.username as reviewer')
            ->paginate($perPage)->withQueryString();
        $page->getCollection()->transform(function ($row) {
            $row->meta = json_decode((string) $row->source_data, true) ?: [];
            $row->kind = $row->meta['kind'] ?? 'field';
            $row->sources_list = array_values(array_filter((array) ($row->meta['sources'] ?? []),
                fn ($u) => is_string($u) && preg_match('#^https?://#i', $u)));

            return $row;
        });

        return $page;
    }

    public function counts(): array
    {
        return DB::table('ahg_description_suggestion')->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status')->all();
    }

    /**
     * Approve (with the reviewer's edited text, if any) or reject one pending
     * suggestion. Returns a message for the reviewer; throws on bad input.
     */
    public function decide(int $id, string $decision, ?string $editedText, ?string $notes, int $reviewerId): string
    {
        $s = DB::table('ahg_description_suggestion')->where('id', $id)->first();
        if (! $s || $s->status !== 'pending') {
            throw new \InvalidArgumentException(__('This suggestion is no longer waiting for review.'));
        }
        $done = ['reviewed_by' => $reviewerId, 'reviewed_at' => now(), 'review_notes' => $notes !== null ? mb_substr($notes, 0, 2000) : null];
        if ($decision === 'reject') {
            DB::table('ahg_description_suggestion')->where('id', $id)->update($done + ['status' => 'rejected']);

            return __('Suggestion rejected. The record is unchanged.');
        }
        if ($decision !== 'approve') {
            throw new \InvalidArgumentException(__('Choose approve or reject.'));
        }

        $meta = json_decode((string) $s->source_data, true) ?: [];
        $edited = $editedText !== null && trim($editedText) !== '' && trim($editedText) !== trim((string) $s->suggested_text);
        $text = $edited ? trim($editedText) : (string) $s->suggested_text;
        $objectId = (int) $s->object_id;

        DB::transaction(function () use ($meta, $text, $objectId, $id, $done, $edited) {
            $before = \AhgInformationObjectManage\Services\InformationObjectService::contentFingerprint($objectId);
            if (($meta['kind'] ?? 'field') === 'transcript') {
                $this->writeTranscript((int) ($meta['digital_object_id'] ?? 0), $objectId, $text);
            } else {
                $field = (string) ($meta['field'] ?? 'scope_and_content');
                if (! in_array($field, self::FIELDS, true)) {
                    throw new \InvalidArgumentException(__('This suggestion names a field that cannot be written.'));
                }
                if ($field === 'title' && mb_strlen($text) > 1024) {
                    throw new \InvalidArgumentException(__('A title may be at most 1,024 characters.'));
                }
                $culture = (string) ($meta['culture'] ?? '') ?: (string) DB::table('information_object')->where('id', $objectId)->value('source_culture');
                DB::table('information_object_i18n')->updateOrInsert(['id' => $objectId, 'culture' => $culture], [$field => $text]);
            }
            DB::table('ahg_description_suggestion')->where('id', $id)->update($done + [
                'status' => $edited ? 'edited' : 'approved',
                'edited_text' => $edited ? $text : null,
            ]);
            \AhgInformationObjectManage\Services\InformationObjectService::touchIfChanged($objectId, $before);
        });

        return __('Approved and written to the record.');
    }

    /** The file's text, as the "transcript" property (one per file, in the record's language). */
    protected function writeTranscript(int $doId, int $objectId, string $text): void
    {
        if (! DB::table('digital_object')->where('id', $doId)->where('object_id', $objectId)->exists()) {
            throw new \InvalidArgumentException(__('The file this transcript belongs to is no longer attached to the record.'));
        }
        $culture = (string) DB::table('information_object')->where('id', $objectId)->value('source_culture') ?: 'en';
        $propId = DB::table('property')->where('object_id', $doId)->where('name', 'transcript')->value('id');
        if (! $propId) {
            $propId = DB::table('property')->insertGetId([
                'object_id' => $doId, 'name' => 'transcript', 'scope' => 'Transcription reviewed in AI > Suggest review',
                'source_culture' => $culture,
            ]);
        }
        DB::table('property_i18n')->updateOrInsert(['id' => $propId, 'culture' => $culture], ['value' => $text]);
    }
}
