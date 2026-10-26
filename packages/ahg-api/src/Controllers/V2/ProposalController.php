<?php

/**
 * ProposalController - Heratio
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

namespace AhgApi\Controllers\V2;

use AhgCore\Services\AclService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Proposals for archivists to review (heratio#1559, #1565), the same contract
 * as PSIS ahgAPIPlugin so the AHG Archives MCP serves both. Nothing on a
 * record changes here: proposals wait in the review queues until a person
 * approves them.
 *
 *   POST /api/v2/descriptions/{slug}/proposals
 *     entities  [{type: PERSON|ORG|GPE|DATE, value, confidence?, evidence?, sources?[],
 *                 authority?: {dates_of_existence, history, places},
 *                 actor_id? | term_id?, link_reason?}]
 *       -> AI > NER review and the Authority Resolution queue, evidence beside them.
 *     fields     [{field, text, culture?, evidence?, sources?[]}] - a culture other than
 *                the record's is a translation; transcripts [{text, digital_object_id?,
 *                evidence?}] - the text of a file -> AI > Suggest review.
 *     relationships -> 501 until their review queue exists.
 *
 *   GET /api/v2/proposals?mine=1|0&status=&count=&start=
 *     The caller's proposals (mine=0: everyone's, administrators and editors
 *     only) and what became of them, newest first.
 */
class ProposalController extends BaseApiController
{
    private const TYPES = ['PERSON', 'ORG', 'GPE', 'DATE'];
    private const MAX = 50;
    private const PLACES_TAXONOMY_ID = 42;
    private const AUTHORITY_FIELDS = ['dates_of_existence' => 255, 'history' => 8000, 'places' => 1000];
    private const LIST_MAX = 200;
    /** information_object_i18n fields a proposal may target. */
    private const FIELDS = ['title', 'scope_and_content', 'archival_history', 'extent_and_medium', 'arrangement', 'access_conditions', 'reproduction_conditions'];

    /**
     * POST /api/v2/descriptions/{slug}/proposals
     */
    public function store(string $slug, Request $request): JsonResponse
    {
        $objectId = (int) $this->slugToId($slug);
        if (! $objectId || ! DB::table('information_object')->where('id', $objectId)->exists()) {
            return $this->error('Not Found', 'Description not found', 404);
        }
        $user = $this->apiUser($request);
        if (! $user || ! AclService::check((object) ['id' => $objectId], 'update', $user)) {
            return $this->error('Forbidden', 'Not authorised to edit this description', 403);
        }

        $entities = is_array($request->input('entities')) ? $request->input('entities') : [];
        $fields = is_array($request->input('fields')) ? $request->input('fields') : [];
        $transcripts = is_array($request->input('transcripts')) ? $request->input('transcripts') : [];
        $relationships = is_array($request->input('relationships')) ? $request->input('relationships') : [];
        if (! $entities && ! $fields && ! $transcripts && ! $relationships) {
            return $this->error('Bad Request', 'entities (a list of {type, value}), fields (a list of {field, text}), transcripts or relationships is required', 400);
        }
        // ponytail: relationship proposals answer 501 until their review screen
        // exists (heratio#1563).
        if ($relationships) {
            return $this->error('Not Implemented', 'Relationship proposals are not supported by this catalogue yet', 501);
        }
        if (count($fields) > 10) {
            return $this->error('Bad Request', 'At most 10 field proposals per request', 400);
        }
        $fieldRows = [];
        foreach (array_values($fields) as $i => $f) {
            $f = is_array($f) ? $f : [];
            $field = (string) ($f['field'] ?? '');
            $text = trim((string) ($f['text'] ?? ''));
            if (! in_array($field, self::FIELDS, true) || $text === '' || mb_strlen($text) > ($field === 'title' ? 1024 : 20000)) {
                return $this->error('Bad Request', "Field {$i}: field must be one of ".implode(', ', self::FIELDS).', text 1-20000 characters (title 1-1024)', 400);
            }
            // A culture other than the record's makes it a translation into that language.
            $culture = trim((string) ($f['culture'] ?? ''));
            if ($culture !== '' && ! preg_match('/^[a-z]{2,3}(_[A-Za-z]{2,4})?$/', $culture)) {
                return $this->error('Bad Request', "Field {$i}: culture must be a language code such as en, af or nl", 400);
            }
            $fieldRows[] = ['field' => $field, 'text' => $text, 'culture' => $culture ?: null,
                'evidence' => mb_substr(trim((string) ($f['evidence'] ?? '')), 0, 4000), 'sources' => self::sources($f['sources'] ?? [])];
        }
        if (count($transcripts) > 5) {
            return $this->error('Bad Request', 'At most 5 transcripts per request', 400);
        }
        foreach (array_values($transcripts) as $i => $t) {
            $t = is_array($t) ? $t : [];
            $text = trim((string) ($t['text'] ?? ''));
            // ahg_description_suggestion.suggested_text is a TEXT column (64 KB).
            if ($text === '' || strlen($text) > 65000) {
                return $this->error('Bad Request', "Transcript {$i}: text is required, at most 65,000 bytes; propose a long document page by page", 400);
            }
            $doId = DB::table('digital_object')->where('object_id', $objectId)->whereNull('parent_id')
                ->when(! empty($t['digital_object_id']), fn ($q) => $q->where('id', (int) $t['digital_object_id']))
                ->orderBy('id')->value('id');
            if (! $doId) {
                return $this->error('Bad Request', "Transcript {$i}: ".(empty($t['digital_object_id']) ? 'this record has no file to transcribe' : 'digital_object_id is not a file of this record'), 400);
            }
            $fieldRows[] = ['field' => 'transcript', 'text' => $text, 'culture' => null, 'digital_object_id' => (int) $doId,
                'evidence' => mb_substr(trim((string) ($t['evidence'] ?? '')), 0, 4000), 'sources' => self::sources($t['sources'] ?? [])];
        }
        if (! Schema::hasTable('ahg_ner_entity') || ($fieldRows && ! Schema::hasTable('ahg_description_suggestion'))) {
            return $this->error('Not Implemented', 'Proposals need the AI services package, whose review screens they go to', 501);
        }
        if (count($entities) > self::MAX) {
            return $this->error('Bad Request', 'At most '.self::MAX.' entities per request', 400);
        }

        $clean = [];
        foreach (array_values($entities) as $i => $e) {
            $e = is_array($e) ? $e : [];
            $type = strtoupper(trim((string) ($e['type'] ?? '')));
            $value = trim(preg_replace('/\s+/u', ' ', (string) ($e['value'] ?? '')));
            if (! in_array($type, self::TYPES, true) || $value === '' || mb_strlen($value) > 500) {
                return $this->error('Bad Request', "Entity {$i}: type must be one of ".implode(', ', self::TYPES).', value 1-500 characters', 400);
            }
            $authority = [];
            if (in_array($type, ['PERSON', 'ORG'], true) && is_array($e['authority'] ?? null)) {
                foreach (self::AUTHORITY_FIELDS as $k => $max) {
                    $v = trim((string) ($e['authority'][$k] ?? ''));
                    if ($v !== '') {
                        $authority[$k] = mb_substr($v, 0, $max);
                    }
                }
            }
            // A proposed link to an existing record, checked here so a wrong id
            // fails the request instead of reaching the reviewer.
            $linkId = (int) ($type === 'GPE' ? ($e['term_id'] ?? 0) : ($e['actor_id'] ?? 0));
            if ($linkId > 0) {
                if ($type === 'DATE') {
                    return $this->error('Bad Request', "Entity {$i}: a date cannot link to an authority record", 400);
                }
                $ok = $type === 'GPE'
                    ? DB::table('term')->where('id', $linkId)->where('taxonomy_id', self::PLACES_TAXONOMY_ID)->exists()
                    : self::actorOk($linkId);
                if (! $ok) {
                    return $this->error('Bad Request', "Entity {$i}: ".($type === 'GPE' ? 'term_id is not a place' : 'actor_id is not an authority record'), 400);
                }
                $authority['link'] = ['kind' => $type === 'GPE' ? 'term' : 'actor', 'id' => $linkId,
                    'reason' => mb_substr(trim((string) ($e['link_reason'] ?? '')), 0, 1000)];
            }
            $clean[$type."\0".mb_strtolower($value)] = [
                'type' => $type, 'value' => $value,
                'confidence' => isset($e['confidence']) ? max(0.0, min(1.0, (float) $e['confidence'])) : null,
                'evidence' => mb_substr(trim((string) ($e['evidence'] ?? '')), 0, 4000),
                'sources' => self::sources($e['sources'] ?? []),
                'authority' => $authority,
            ];
        }

        // Skip what this record already has waiting for review or linked.
        $existing = DB::table('ahg_ner_entity')->where('object_id', $objectId)
            ->whereIn('status', ['pending', 'linked', 'approved'])
            ->get(['entity_type', 'entity_value'])
            ->mapWithKeys(fn ($r) => [strtoupper($r->entity_type)."\0".mb_strtolower(trim($r->entity_value)) => true])->all();
        $new = array_diff_key($clean, $existing);

        $userId = (int) $user->id;
        $label = preg_replace('/[^a-z0-9_-]/i', '', (string) $request->input('source', 'api')) ?: 'api';
        $now = now();
        $queued = [];
        $newIds = [];
        if ($new) {
            DB::transaction(function () use ($new, $objectId, $userId, $label, $now, &$queued, &$newIds) {
                $extractionId = DB::table('ahg_ner_extraction')->insertGetId([
                    'object_id' => $objectId,
                    // Who proposed it, for the audit and GET /proposals: "glam-mcp:u900148".
                    'backend_used' => mb_substr($label.':u'.$userId, 0, 50),
                    'status' => 'completed',
                    'entity_count' => count($new),
                    'extracted_at' => $now,
                ]);
                foreach ($new as $e) {
                    $id = DB::table('ahg_ner_entity')->insertGetId([
                        'extraction_id' => $extractionId, 'object_id' => $objectId,
                        'entity_type' => $e['type'], 'entity_value' => $e['value'],
                        'confidence' => $e['confidence'] ?? 1.0, 'status' => 'pending', 'created_at' => $now,
                    ]);
                    if ($e['evidence'] !== '' || $e['sources'] || $e['authority']) {
                        DB::table('ahg_ner_entity_evidence')->insert([
                            'entity_id' => $id, 'evidence' => $e['evidence'] ?: null,
                            'sources' => $e['sources'] ? json_encode($e['sources']) : null,
                            'authority' => $e['authority'] ? json_encode($e['authority'], JSON_UNESCAPED_UNICODE) : null,
                            'created_by' => $userId, 'created_at' => $now,
                        ]);
                    }
                    $newIds[$id] = $e['authority']['link'] ?? null;
                    $queued[] = ['type' => $e['type'], 'value' => $e['value']]
                        + (isset($e['authority']['link']) ? ['linked_to' => $e['authority']['link']['kind'].':'.$e['authority']['link']['id']] : []);
                }
            });
        }

        $promoted = $newIds ? $this->promote($objectId, $newIds) : 0;
        [$fieldsQueued, $fieldsSkipped] = $fieldRows ? $this->queueFields($objectId, $fieldRows, $userId, $label) : [[], 0];

        return $this->success([
            'slug' => $slug,
            'queued' => $queued,
            'skipped_existing' => count($clean) - count($new),
            'promoted_for_resolution' => $promoted,
            'fields_queued' => $fieldsQueued,
            'fields_skipped_existing' => $fieldsSkipped,
            'relationships' => [],
            'relationships_skipped_existing' => 0,
            'review_url' => url('/ai/ner/review'),
            'description_review_url' => url('/ai/suggest/review'),
        ], ($queued || $fieldsQueued) ? 201 : 200);
    }

    /**
     * Field texts, translations and transcripts go to AI > Suggest review.
     * source_data says what approval writes: the field in the culture its
     * current text was read in, the field in another language, or the file's
     * transcript. The same text already pending for the record is skipped.
     */
    protected function queueFields(int $objectId, array $rows, int $userId, string $label): array
    {
        $queued = [];
        $skipped = 0;
        $culture = $this->culture;
        $current = (array) (DB::table('information_object_i18n')->where('id', $objectId)->where('culture', $culture)->first(self::FIELDS) ?? []);
        foreach ($rows as $f) {
            if (DB::table('ahg_description_suggestion')->where('object_id', $objectId)->where('status', 'pending')
                ->where('suggested_text', $f['text'])->exists()) {
                $skipped++;

                continue;
            }
            if ($f['field'] === 'transcript') {
                $existing = DB::table('property as p')->join('property_i18n as pi', 'pi.id', '=', 'p.id')
                    ->where('p.object_id', $f['digital_object_id'])->where('p.name', 'transcript')->value('pi.value');
                // The page image for the reviewer: the reference copy, else the master if it is an image.
                $img = DB::table('digital_object')->where('parent_id', $f['digital_object_id'])->where('usage_id', \AhgCore\Constants\TermId::DO_USAGE_REFERENCE)->first(['path', 'name'])
                    ?: DB::table('digital_object')->where('id', $f['digital_object_id'])->where('mime_type', 'like', 'image/%')->first(['path', 'name']);
                $meta = ['kind' => 'transcript', 'digital_object_id' => $f['digital_object_id'],
                    'image' => $img ? rtrim($img->path, '/').'/'.$img->name : null];
            } elseif ($f['culture'] && $f['culture'] !== $culture) {
                $existing = DB::table('information_object_i18n')->where('id', $objectId)->where('culture', $f['culture'])->value($f['field']);
                $meta = ['kind' => 'translation', 'field' => $f['field'], 'culture' => $f['culture']];
            } else {
                $existing = $current[$f['field']] ?? null;
                $meta = ['kind' => 'field', 'field' => $f['field'], 'culture' => $culture];
            }
            DB::table('ahg_description_suggestion')->insert([
                'object_id' => $objectId,
                'suggested_text' => $f['text'],
                'existing_text' => $existing === null ? null : mb_strcut((string) $existing, 0, 65000),
                'source_data' => json_encode($meta + ['evidence' => $f['evidence'], 'sources' => $f['sources'], 'proposed_via' => $label], JSON_UNESCAPED_UNICODE),
                'status' => 'pending',
                'model_used' => mb_substr($label, 0, 100),
                'created_by' => $userId ?: null,
                'created_at' => now(),
            ]);
            $queued[] = ['field' => $f['field']] + ($meta['kind'] === 'translation' ? ['culture' => $f['culture']] : []);
        }

        return [$queued, $skipped];
    }

    /**
     * Names and places go on to the Authority Resolution queue, as the NER
     * pipeline does, with candidates from the local records at once; a proposed
     * link is put first and marked as proposed. Dates stay in the NER dialog.
     */
    protected function promote(int $objectId, array $newIds): int
    {
        if (! class_exists(\AhgAuthorityResolution\Services\PromoteToMentionService::class) || ! Schema::hasTable('ahg_mention')) {
            return 0;
        }
        try {
            $promoted = app(\AhgAuthorityResolution\Services\PromoteToMentionService::class)->promoteAllForObject($objectId);
        } catch (\Throwable $e) {
            Log::warning('apiv2 proposals: promotion for object '.$objectId.' failed: '.$e->getMessage());

            return 0;
        }
        $generator = app(\AhgAuthorityResolution\Services\CandidateGeneratorService::class);
        foreach (DB::table('ahg_mention')->whereIn('ner_entity_id', array_keys($newIds))->get(['id', 'ner_entity_id']) as $m) {
            try {
                $generator->generate((int) $m->id);
                if ($link = $newIds[$m->ner_entity_id]) {
                    $this->markProposedCandidate((int) $m->id, $link);
                }
            } catch (\Throwable $e) {
                Log::warning('apiv2 proposals: candidates for mention '.$m->id.' failed: '.$e->getMessage());
            }
        }

        return $promoted;
    }

    /** The proposed record as the top candidate, flagged for the reviewer. */
    protected function markProposedCandidate(int $mentionId, array $link): void
    {
        $evidence = json_encode(['proposed' => true, 'reason' => $link['reason']], JSON_UNESCAPED_UNICODE);
        $top = (float) DB::table('ahg_mention_candidate')->where('mention_id', $mentionId)->max('composite_score');
        $row = DB::table('ahg_mention_candidate')->where('mention_id', $mentionId)->where('candidate_authority_id', $link['id'])->first();
        if ($row) {
            DB::table('ahg_mention_candidate')->where('id', $row->id)->update([
                'evidence_data' => $evidence, 'composite_score' => max(1.0, $top),
            ]);

            return;
        }
        $name = $link['kind'] === 'term'
            ? DB::table('term_i18n')->where('id', $link['id'])->orderByRaw("culture = 'en' DESC")->value('name')
            : DB::table('actor_i18n')->where('id', $link['id'])->orderByRaw("culture = 'en' DESC")->value('authorized_form_of_name');
        DB::table('ahg_mention_candidate')->insert([
            'mention_id' => $mentionId, 'rank_position' => 0,
            'candidate_source' => $link['kind'] === 'term' ? 'mysql_term' : 'mysql_actor',
            'candidate_authority_id' => $link['id'], 'candidate_display_name' => (string) $name,
            'name_similarity_score' => 0, 'evidence_signals' => null, 'evidence_data' => $evidence,
            'composite_score' => max(1.0, $top), 'computed_at' => now(),
        ]);
    }

    /**
     * GET /api/v2/proposals?mine=1|0&status=&count=&start=
     */
    public function index(Request $request): JsonResponse
    {
        $userId = (int) $this->apiUserId($request);
        $mine = (string) $request->get('mine', '1') !== '0';
        if (! $mine && ! $this->isStaff($request)) {
            return $this->error('Forbidden', "Only administrators and editors may list everyone's proposals", 403);
        }
        $status = strtolower(trim((string) $request->get('status', '')));
        // Own parameter names, as PSIS: limit/skip mean list paging elsewhere.
        $count = max(1, min((int) $request->get('count', 50), self::LIST_MAX));
        $start = max(0, (int) $request->get('start', 0));

        $items = [];
        if (Schema::hasTable('ahg_ner_entity')) {
            $hasMention = Schema::hasTable('ahg_mention');
            $q = DB::table('ahg_ner_entity as n')
                ->join('ahg_ner_extraction as x', 'x.id', '=', 'n.extraction_id')
                ->where('x.backend_used', 'like', $mine ? '%:u'.$userId : '%:u%');
            if ($hasMention) {
                $q->leftJoin('ahg_mention as m', 'm.ner_entity_id', '=', 'n.id');
            }
            // ponytail: the newest 1,000 are read and filtered here; page in SQL
            // if anyone proposes more than that.
            $rows = $q->orderByDesc('n.id')->limit(self::LIST_MAX * 5)->get(array_merge(
                ['n.id', 'n.object_id', 'n.entity_type', 'n.entity_value', 'n.status', 'n.linked_actor_id',
                    'n.reviewed_by', 'n.reviewed_at', 'n.created_at', 'x.backend_used'],
                $hasMention ? ['m.state as mention_state'] : []
            ));
            foreach ($rows as $e) {
                // The resolution queue's outcome is the more precise one once a person acted on it.
                $state = ($e->mention_state ?? null) && $e->mention_state !== 'pending' ? $e->mention_state : $e->status;
                $items[] = [
                    'kind' => 'entity', 'id' => (int) $e->id, 'object_id' => (int) $e->object_id,
                    'type' => $e->entity_type, 'value' => $e->entity_value, 'status' => (string) $state,
                    'linked_authority_id' => $e->linked_actor_id ? (int) $e->linked_actor_id : null,
                    'proposed_by' => (int) substr((string) strrchr((string) $e->backend_used, 'u'), 1) ?: null,
                    'reviewed_by' => $e->reviewed_by, 'reviewed_at' => $e->reviewed_at, 'proposed_at' => (string) $e->created_at,
                ];
            }
        }

        if (Schema::hasTable('ahg_description_suggestion')) {
            $q = DB::table('ahg_description_suggestion')->whereNotNull(DB::raw("JSON_EXTRACT(source_data, '$.proposed_via')"));
            if ($mine) {
                $q->where('created_by', $userId);
            }
            foreach ($q->orderByDesc('id')->limit(self::LIST_MAX * 5)
                ->get(['id', 'object_id', 'source_data', 'suggested_text', 'status', 'reviewed_by', 'reviewed_at', 'review_notes', 'created_by', 'created_at']) as $f) {
                $meta = json_decode((string) $f->source_data, true) ?: [];
                $items[] = [
                    'kind' => 'field', 'id' => (int) $f->id, 'object_id' => (int) $f->object_id,
                    'field' => ($meta['kind'] ?? '') === 'transcript' ? 'transcript' : ($meta['field'] ?? null),
                    'culture' => ($meta['kind'] ?? '') === 'translation' ? ($meta['culture'] ?? null) : null,
                    'value' => mb_substr((string) $f->suggested_text, 0, 300), 'status' => (string) $f->status,
                    'review_notes' => $f->review_notes,
                    'proposed_by' => $f->created_by ? (int) $f->created_by : null,
                    'reviewed_by' => $f->reviewed_by, 'reviewed_at' => $f->reviewed_at, 'proposed_at' => (string) $f->created_at,
                ];
            }
        }

        if ($status !== '') {
            $items = array_values(array_filter($items, fn ($i) => $i['status'] === $status));
        }
        usort($items, fn ($a, $b) => strcmp($b['proposed_at'], $a['proposed_at']));
        $total = count($items);
        $items = array_slice($items, $start, $count);

        // Record slug and title, and user names, for this page only.
        $objectIds = array_values(array_unique(array_column($items, 'object_id')));
        $slugs = $objectIds ? DB::table('slug')->whereIn('object_id', $objectIds)->pluck('slug', 'object_id')->all() : [];
        $titles = $objectIds ? DB::table('information_object_i18n')->whereIn('id', $objectIds)->where('culture', $this->culture)->pluck('title', 'id')->all() : [];
        $userIds = array_values(array_filter(array_unique(array_merge(array_column($items, 'reviewed_by'), array_column($items, 'proposed_by')))));
        $names = $userIds ? DB::table('user')->whereIn('id', $userIds)->pluck('username', 'id')->all() : [];
        foreach ($items as &$i) {
            $i['slug'] = $slugs[$i['object_id']] ?? null;
            $i['title'] = $titles[$i['object_id']] ?? null;
            $i['proposed_by'] = $i['proposed_by'] ? ($names[$i['proposed_by']] ?? null) : null;
            $i['reviewed_by'] = $i['reviewed_by'] ? ($names[$i['reviewed_by']] ?? null) : null;
            unset($i['object_id']);
        }
        unset($i);

        return $this->success(['total' => $total, 'count' => $count, 'start' => $start, 'results' => $items]);
    }

    /** At most ten http(s) URLs of up to 500 characters. */
    private static function sources($list): array
    {
        $out = [];
        foreach (array_slice(is_array($list) ? $list : [], 0, 10) as $u) {
            $u = trim((string) $u);
            if (mb_strlen($u) <= 500 && preg_match('#^https?://#i', $u)) {
                $out[] = $u;
            }
        }

        return $out;
    }

    /** A person, organisation or family record that a visitor may see. */
    private static function actorOk(int $id): bool
    {
        return $id > 1
            && DB::table('actor')->where('id', $id)->whereNotNull('entity_type_id')->exists()
            && AclService::isActorVisible($id);
    }
}
