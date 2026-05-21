<?php

/**
 * AuthorityReviewController - Heratio
 *
 * Task 5 of the AHG Authority Resolution Engine. Renders the three-region
 * review screen (mention + evidence packet on the left, ranked candidates
 * with per-dimension evidence in the middle, five action buttons on the
 * right) and handles each of the five action POSTs (link / link-different
 * / create-new / park / reject).
 *
 * A small typeahead lookup endpoint backs the "Link to different existing
 * authority" modal - it reuses the MysqlActorAdapter / MysqlTermAdapter
 * from Task 3 so we never touch the locked actor-manage or display
 * packages.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgAuthorityResolution\Http\Controllers;

use AhgAuthorityResolution\Services\Adapters\MysqlActorAdapter;
use AhgAuthorityResolution\Services\Adapters\MysqlTermAdapter;
use AhgAuthorityResolution\Services\AssignmentService;
use AhgAuthorityResolution\Services\AuthorityCreator;
use AhgAuthorityResolution\Services\DecisionRecorder;
use AhgAuthorityResolution\Services\FieldProvenanceWriter;
use AhgAuthorityResolution\Services\Lookup\PrefillEngine;
use AhgAuthorityResolution\Services\PromoteToMentionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthorityReviewController extends Controller
{
    private const PLACE_TYPES = ['GPE', 'PLACE', 'LOC', 'ISAD_PLACE'];

    /**
     * Settings keys we expose on the admin lookup page. Each source is
     * five rows (enabled, rate_limit, cache_ttl, license_note, license_url)
     * plus geonames-username for one of them.
     */
    private const LOOKUP_SOURCES = ['viaf', 'wikidata', 'geonames', 'tgn', 'gnd', 'isni', 'sagnc'];

    public function __construct(
        private DecisionRecorder $recorder,
        private MysqlActorAdapter $actorAdapter,
        private MysqlTermAdapter $termAdapter,
        private PrefillEngine $prefillEngine,
        private AuthorityCreator $authorityCreator,
        private FieldProvenanceWriter $fieldProvenanceWriter,
        private AssignmentService $assignments,
        private PromoteToMentionService $promoter,
    ) {}

    /**
     * GET /admin/authority-resolution/queue
     *
     * Pending-mentions queue. Filters: entity type, object id.
     */
    public function queue(Request $request)
    {
        $entityType = trim((string) $request->query('entity_type', ''));
        $objectId = (int) $request->query('object_id', 0);
        $state = trim((string) $request->query('state', 'pending'));

        // Build the filtered-set base query once; reused for the visible page,
        // the total count, and the "select all N matching the filter" id list.
        $applyFilters = function ($query) use ($state, $entityType, $objectId) {
            if ($state !== '' && $state !== 'any') {
                $query->where('m.state', $state);
            }
            if ($entityType !== '') {
                $query->where('m.entity_type', $entityType);
            }
            if ($objectId > 0) {
                $query->where('m.object_id', $objectId);
            }
            return $query;
        };

        // Source-object subquery: one Master digital-object row per IO. usage_id
        // 140 = "Master" in the term taxonomy. MIN(id) keeps a single row even
        // in the unlikely event of two masters on one IO.
        $masterDo = DB::table('digital_object')
            ->select('object_id', DB::raw('MIN(id) AS do_id'))
            ->where('usage_id', 140)
            ->groupBy('object_id');

        $q = DB::table('ahg_mention as m')
            ->join('ahg_ner_entity as n', 'n.id', '=', 'm.ner_entity_id')
            ->leftJoin(DB::raw('(SELECT mention_id, COUNT(*) AS c FROM ahg_mention_candidate GROUP BY mention_id) AS cc'), 'cc.mention_id', '=', 'm.id')
            ->leftJoin('information_object as io', 'io.id', '=', 'm.object_id')
            ->leftJoin('slug as s', 's.object_id', '=', 'm.object_id')
            ->leftJoinSub($masterDo, 'md', 'md.object_id', '=', 'm.object_id')
            ->leftJoin('digital_object as d', 'd.id', '=', 'md.do_id')
            ->select('m.id', 'm.entity_type', 'm.state', 'm.object_id', 'm.promoted_at',
                     'm.assigned_to_user_id', 'm.workflow_task_id',
                     'n.entity_value', 'n.confidence', DB::raw('COALESCE(cc.c, 0) AS candidate_count'),
                     's.slug as io_slug', 'io.identifier as io_identifier',
                     'd.mime_type as do_mime_type', 'd.name as do_name');
        $applyFilters($q);

        $rows = $q->orderBy('m.id')->limit(200)->get();

        // Total rows matching the current filter (drives "select all N").
        $filteredTotal = $applyFilters(DB::table('ahg_mention as m'))->count();

        // Every mention id matching the current filter - the batch-assign
        // "select all matching" link uses this to target the whole set.
        $allFilteredIds = $applyFilters(DB::table('ahg_mention as m'))
            ->orderBy('m.id')
            ->pluck('m.id')
            ->map(fn($v) => (int) $v)
            ->all();

        $counts = DB::table('ahg_mention')
            ->select('state', DB::raw('COUNT(*) AS c'))
            ->groupBy('state')
            ->pluck('c', 'state')
            ->all();

        // Assignee display names for the "Assigned to" column.
        $assigneeIds = array_values(array_unique(array_filter(array_map(
            fn($r) => (int) ($r->assigned_to_user_id ?? 0),
            $rows->all()
        ))));
        $assigneeNames = [];
        if (!empty($assigneeIds)) {
            $assigneeNames = DB::table('user')
                ->leftJoin('actor_i18n', function ($join) {
                    $join->on('user.id', '=', 'actor_i18n.id')
                        ->where('actor_i18n.culture', '=', 'en');
                })
                ->whereIn('user.id', $assigneeIds)
                ->get(['user.id', 'user.username', 'actor_i18n.authorized_form_of_name as display_name'])
                ->mapWithKeys(function ($u) {
                    $name = trim((string) ($u->display_name ?? ''))
                        ?: (trim((string) ($u->username ?? '')) ?: ('User #' . (int) $u->id));
                    return [(int) $u->id => $name];
                })
                ->all();
        }

        return view('auth-res::queue', [
            'rows' => $rows,
            'counts' => $counts,
            'filterEntityType' => $entityType,
            'filterObjectId' => $objectId,
            'filterState' => $state,
            'filteredTotal' => $filteredTotal,
            'allFilteredIds' => $allFilteredIds,
            'assigneeNames' => $assigneeNames,
            'archivists' => $this->assignments->archivists(),
        ]);
    }

    /**
     * GET /admin/authority-resolution/review/{mention}
     */
    public function show(int $mention)
    {
        $row = $this->loadMentionRow($mention);
        if (!$row) {
            abort(404, 'Mention not found');
        }

        $context = DB::table('ahg_mention_context')->where('mention_id', $mention)->first();
        // Candidate rows. LEFT JOIN slug so every candidate that points at a
        // resolved Qubit object (actor OR taxonomy term - both carry a slug
        // row) gets an authority_slug. The candidate card uses this to render
        // a working /{slug} link instead of a dead "authority #N" string.
        $candidates = DB::table('ahg_mention_candidate as mc')
            ->leftJoin('slug as authslug', 'authslug.object_id', '=', 'mc.candidate_authority_id')
            ->where('mc.mention_id', $mention)
            ->orderByDesc('mc.composite_score')
            ->orderBy('mc.rank_position')
            ->select('mc.*', 'authslug.slug as authority_slug')
            ->get();

        // Decode JSON blobs once for the view.
        $candidates = $candidates->map(function ($c) {
            $c->evidence_signals_decoded = $c->evidence_signals ? json_decode($c->evidence_signals, true) : [];
            $c->evidence_data_decoded = $c->evidence_data ? json_decode($c->evidence_data, true) : [];
            return $c;
        });

        $coOccurring = $this->jsonOrEmpty($context->co_occurring_entities ?? null);
        $nearbyDates = $this->jsonOrEmpty($context->nearby_dates ?? null);
        $nearbyPlaces = $this->jsonOrEmpty($context->nearby_places ?? null);
        $roleTokens = $this->jsonOrEmpty($context->role_language_tokens ?? null);

        // Ambiguity: how many other mentions in the SAME source object share
        // this entity_value? > 1 means the value is reused elsewhere in the
        // same document and the archivist should pay attention.
        $ambiguityCount = DB::table('ahg_mention as m')
            ->join('ahg_ner_entity as n', 'n.id', '=', 'm.ner_entity_id')
            ->where('m.object_id', $row->object_id)
            ->where('n.entity_value', $row->entity_value)
            ->count();

        // The next pending mention in queue order (used for skip-to-next links).
        $next = DB::table('ahg_mention')
            ->where('state', 'pending')
            ->where('id', '>', $mention)
            ->orderBy('id')
            ->value('id');

        // The source IO show page link uses the slug column on information_object.
        $ioSlugRow = DB::table('information_object as io')
            ->leftJoin('slug', 'slug.object_id', '=', 'io.id')
            ->where('io.id', $row->object_id)
            ->select('slug.slug')
            ->first();
        $ioSlug = $ioSlugRow->slug ?? null;

        // Assign / Workflow feature: archivist picker + current-assignee name.
        $archivists = $this->assignments->archivists();
        $currentAssigneeName = null;
        if (!empty($row->assigned_to_user_id)) {
            foreach ($archivists as $a) {
                if ((int) $a['id'] === (int) $row->assigned_to_user_id) {
                    $currentAssigneeName = $a['name'];
                    break;
                }
            }
            if ($currentAssigneeName === null) {
                // Assignee not in the eligible list (role changed, etc.).
                $u = DB::table('user')
                    ->leftJoin('actor_i18n', function ($join) {
                        $join->on('user.id', '=', 'actor_i18n.id')
                            ->where('actor_i18n.culture', '=', 'en');
                    })
                    ->where('user.id', (int) $row->assigned_to_user_id)
                    ->select('user.username', 'actor_i18n.authorized_form_of_name as display_name')
                    ->first();
                if ($u) {
                    $currentAssigneeName = trim((string) ($u->display_name ?? ''))
                        ?: (trim((string) ($u->username ?? '')) ?: ('User #' . (int) $row->assigned_to_user_id));
                }
            }
        }

        return view('auth-res::review', [
            'mention' => $row,
            'context' => $context,
            'candidates' => $candidates,
            'coOccurring' => $coOccurring,
            'nearbyDates' => $nearbyDates,
            'nearbyPlaces' => $nearbyPlaces,
            'roleTokens' => $roleTokens,
            'ambiguityCount' => $ambiguityCount,
            'nextMentionId' => $next,
            'ioSlug' => $ioSlug,
            'isPlace' => in_array($row->entity_type, self::PLACE_TYPES, true),
            'archivists' => $archivists,
            'currentAssigneeName' => $currentAssigneeName,
        ]);
    }

    /**
     * GET /admin/authority-resolution/review/{mention}/context
     *
     * "View full context" feature. Returns the full source text of the
     * mention's information object (the same IO-i18n concatenation the
     * promote pipeline runs against) plus the character / paragraph offsets
     * recorded in ahg_mention_context. The review-screen modal renders the
     * text and highlights the mention span.
     *
     * Offsets are nullable: when the on-demand backfill found no exact match
     * (or NER ran against digital-object content) ahg_mention_context carries
     * NULL offsets. The response still returns the full source_text so the
     * archivist can read the document; the frontend just omits the <mark>.
     *
     * Offset units: ahg_mention_context stores BYTE offsets (ContextDerivation
     * Service derives them with the byte-based strpos / substr family). A
     * browser's String.slice() operates on UTF-16 code units, so the raw byte
     * offsets would land mid-character on any multibyte text. We therefore
     * convert each byte offset to a UTF-16 code-unit offset here, server-side,
     * so the frontend can splice the highlight with a plain String.slice().
     */
    public function context(int $mention): JsonResponse
    {
        $row = DB::table('ahg_mention as m')
            ->join('ahg_ner_entity as n', 'n.id', '=', 'm.ner_entity_id')
            ->where('m.id', $mention)
            ->first(['m.id', 'm.object_id', 'n.entity_value']);

        if (!$row) {
            return response()->json(['ok' => false, 'error' => 'Mention not found.'], 404);
        }

        $context = DB::table('ahg_mention_context')
            ->where('mention_id', $mention)
            ->first([
                'character_offset_start',
                'character_offset_end',
                'paragraph_offset_start',
                'paragraph_offset_end',
            ]);

        $sourceText = $this->promoter->fetchSourceText((int) $row->object_id);

        $byteCol = function ($v): ?int {
            return $v !== null ? (int) $v : null;
        };

        return response()->json([
            'ok' => true,
            'source_text' => $sourceText,
            'offset_start' => $this->byteToUtf16Offset($sourceText, $byteCol($context->character_offset_start ?? null)),
            'offset_end' => $this->byteToUtf16Offset($sourceText, $byteCol($context->character_offset_end ?? null)),
            'paragraph_start' => $this->byteToUtf16Offset($sourceText, $byteCol($context->paragraph_offset_start ?? null)),
            'paragraph_end' => $this->byteToUtf16Offset($sourceText, $byteCol($context->paragraph_offset_end ?? null)),
            'entity_value' => (string) $row->entity_value,
        ]);
    }

    /**
     * Convert a byte offset into a JavaScript String index (UTF-16 code-unit
     * count of the byte-prefix). Returns null for a null input. Out-of-range
     * byte offsets are clamped to the string length so the frontend never
     * receives an offset past end-of-text.
     */
    private function byteToUtf16Offset(string $text, ?int $byteOffset): ?int
    {
        if ($byteOffset === null) {
            return null;
        }
        if ($byteOffset <= 0) {
            return 0;
        }
        $byteLen = strlen($text);
        if ($byteOffset >= $byteLen) {
            $prefix = $text;
        } else {
            $prefix = substr($text, 0, $byteOffset);
            // The byte offset may land mid-character (paragraph offsets come
            // from regex match positions). Trim any dangling continuation
            // bytes so the codepoint count below is exact.
            $prefix = mb_strcut($prefix, 0, strlen($prefix), 'UTF-8');
        }
        // UTF-16 code-unit count: one unit per BMP codepoint, two per astral
        // (>= U+10000) codepoint. mb_str_split keeps it simple and correct.
        $units = 0;
        foreach (mb_str_split($prefix, 1, 'UTF-8') as $ch) {
            $cp = mb_ord($ch, 'UTF-8');
            $units += ($cp !== false && $cp >= 0x10000) ? 2 : 1;
        }
        return $units;
    }

    /**
     * POST /admin/authority-resolution/review/{mention}/link
     */
    public function link(Request $request, int $mention)
    {
        $candidateId = (int) $request->input('candidate_id');
        if ($candidateId <= 0) {
            return back()->withErrors(['candidate_id' => 'Please select a candidate.']);
        }

        $userId = (int) (Auth::id() ?? 0);
        if ($userId <= 0) {
            abort(403);
        }

        try {
            $this->recorder->recordLink($mention, $userId, $candidateId);
        } catch (\Throwable $e) {
            return back()->withErrors(['action' => 'Link failed: ' . $e->getMessage()]);
        }

        return $this->redirectToNext($mention, 'Mention linked to authority record.');
    }

    /**
     * POST /admin/authority-resolution/review/{mention}/link-different
     */
    public function linkDifferent(Request $request, int $mention)
    {
        $authorityId = (int) $request->input('authority_id');
        if ($authorityId <= 0) {
            return back()->withErrors(['authority_id' => 'Pick an existing authority record.']);
        }
        $userId = (int) (Auth::id() ?? 0);
        if ($userId <= 0) {
            abort(403);
        }

        try {
            $this->recorder->recordLinkDifferent($mention, $userId, $authorityId);
        } catch (\Throwable $e) {
            return back()->withErrors(['action' => 'Link-different failed: ' . $e->getMessage()]);
        }

        return $this->redirectToNext($mention, 'Mention linked to a different existing authority.');
    }

    /**
     * GET /admin/authority-resolution/review/{mention}/create-new
     *
     * Task 6 sub-workflow. Calls PrefillEngine -> renders pre-filled form
     * with provenance badges on each field. External lookup only fires if
     * the corresponding source is enabled in ahg_settings.
     */
    public function createNewForm(int $mention)
    {
        $userId = (int) (Auth::id() ?? 0);
        if ($userId <= 0) {
            abort(403);
        }

        $packet = $this->prefillEngine->prefill($mention);
        if (!$packet['mention']) {
            abort(404, 'Mention not found');
        }

        $isPlace = in_array($packet['mention']->entity_type, self::PLACE_TYPES, true);
        $merged = $packet['merged_fields'];
        $provenance = $merged['_provenance'] ?? [];
        unset($merged['_provenance']);

        return view('auth-res::create-new', [
            'mention' => $packet['mention'],
            'context' => $packet['context'],
            'lookupResults' => $packet['lookup_results'],
            'mergedFields' => $merged,
            'provenance' => $provenance,
            'isPlace' => $isPlace,
        ]);
    }

    /**
     * POST /admin/authority-resolution/review/{mention}/create-new
     *
     * Validates the form, inserts the new authority via AuthorityCreator
     * (Qubit class-table-inheritance pattern), writes per-field provenance
     * to Fuseki, and records the 'create_new' decision (back-updating
     * ahg_ner_entity.linked_actor_id with the new id).
     */
    public function createNewSubmit(Request $request, int $mention)
    {
        $userId = (int) (Auth::id() ?? 0);
        if ($userId <= 0) {
            abort(403);
        }

        $row = $this->loadMentionRow($mention);
        if (!$row) {
            abort(404, 'Mention not found');
        }

        $isPlace = in_array($row->entity_type, self::PLACE_TYPES, true);

        $form = $isPlace
            ? $request->validate([
                'name' => ['required', 'string', 'min:1', 'max:1024'],
                'latitude' => ['nullable', 'numeric', 'between:-90,90'],
                'longitude' => ['nullable', 'numeric', 'between:-180,180'],
                'descriptive_standard' => ['nullable', 'string', 'max:255'],
                'provenance' => ['nullable', 'array'],
            ])
            : $request->validate([
                'authorized_form_of_name' => ['required', 'string', 'min:1', 'max:1024'],
                'dates_of_existence' => ['required', 'string', 'max:1024'],
                'history' => ['required', 'string'],
                'descriptive_standard' => ['nullable', 'string', 'max:255'],
                'provenance' => ['nullable', 'array'],
            ]);

        $culture = app()->getLocale() ?: 'en';
        $prefillProvenance = is_array($request->input('provenance')) ? $request->input('provenance') : [];

        try {
            if ($isPlace) {
                $newAuthorityId = $this->authorityCreator->createPlace($form, $userId, $culture);
                $authorityType = 'term';
            } elseif ($row->entity_type === 'ORG') {
                $newAuthorityId = $this->authorityCreator->createOrg($form, $userId, $culture);
                $authorityType = 'actor';
            } else {
                $newAuthorityId = $this->authorityCreator->createPerson($form, $userId, $culture);
                $authorityType = 'actor';
            }
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->withErrors(['action' => 'Authority creation failed: ' . $e->getMessage()]);
        }

        // Best-effort field provenance to Fuseki. Failure is logged but does
        // not block the create - the SQL insert is durable.
        try {
            $mergedForProv = $form;
            $this->fieldProvenanceWriter->writeForCreation(
                $newAuthorityId,
                $authorityType,
                $mergedForProv,
                $prefillProvenance
            );
        } catch (\Throwable $e) {
            // logged inside writer; swallow here.
        }

        try {
            $this->recorder->recordCreateNew($mention, $userId, $newAuthorityId);
        } catch (\Throwable $e) {
            return back()->withErrors(['action' => 'Decision record failed after create: ' . $e->getMessage()]);
        }

        return $this->redirectToNext($mention, "New authority record created (#{$newAuthorityId}) and linked.");
    }

    /**
     * GET /admin/authority-resolution/settings/lookup
     */
    public function settings()
    {
        $rows = DB::table('ahg_settings')
            ->where('setting_group', 'authority_resolution_lookup')
            ->orderBy('setting_key')
            ->get();

        $bySource = [];
        $cross = [];
        foreach ($rows as $r) {
            // setting_key shape: lookup.<source>.<field>  OR  lookup.<crossKey>
            $parts = explode('.', (string) $r->setting_key);
            if (count($parts) === 3) {
                [, $src, $field] = $parts;
                $bySource[$src][$field] = $r;
            } else {
                $cross[(string) $r->setting_key] = $r;
            }
        }

        $sources = self::LOOKUP_SOURCES;
        return view('auth-res::settings', [
            'sources' => $sources,
            'bySource' => $bySource,
            'cross' => $cross,
        ]);
    }

    /**
     * POST /admin/authority-resolution/settings/lookup
     */
    public function settingsSave(Request $request)
    {
        $userId = (int) (Auth::id() ?? 0);
        if ($userId <= 0) {
            abort(403);
        }

        $payload = (array) $request->input('settings', []);
        $writes = 0;
        foreach ($payload as $key => $value) {
            if (!is_string($key) || strpos($key, 'lookup.') !== 0) {
                continue;
            }
            // Cast bool checkboxes ('1'/'0' or missing) cleanly.
            $existing = DB::table('ahg_settings')->where('setting_key', $key)->first();
            if (!$existing) {
                continue;
            }
            if ($existing->setting_type === 'bool') {
                $value = (int) (bool) $value;
            } elseif ($existing->setting_type === 'int') {
                $value = (int) $value;
            } elseif ($existing->setting_type === 'json') {
                $decoded = json_decode((string) $value, true);
                $value = $decoded !== null ? json_encode($decoded, JSON_UNESCAPED_UNICODE) : $existing->setting_value;
            }
            DB::table('ahg_settings')
                ->where('setting_key', $key)
                ->update([
                    'setting_value' => (string) $value,
                    'updated_at' => now(),
                    'updated_by' => $userId,
                ]);
            $writes++;
        }

        return redirect()->route('auth-res.settings.show')
            ->with('notice', "Saved {$writes} lookup setting(s).");
    }

    /**
     * GET /admin/authority-resolution/lookup-sources/status
     *
     * JSON probe used by the settings page to show enabled / cache-size.
     */
    public function lookupSourcesStatus(): JsonResponse
    {
        $out = [];
        foreach (self::LOOKUP_SOURCES as $src) {
            $enabled = (int) (DB::table('ahg_settings')
                ->where('setting_key', 'lookup.' . $src . '.enabled')
                ->value('setting_value') ?? 0) === 1;
            $cacheSize = DB::table('ahg_authority_lookup_cache')
                ->where('source', $src)
                ->count();
            $newestCacheAt = DB::table('ahg_authority_lookup_cache')
                ->where('source', $src)
                ->max('retrieved_at');
            $out[$src] = [
                'enabled' => $enabled,
                'cache_size' => (int) $cacheSize,
                'newest_cache_at' => $newestCacheAt,
            ];
        }
        return response()->json(['sources' => $out]);
    }

    /**
     * POST /admin/authority-resolution/review/{mention}/park
     */
    public function park(Request $request, int $mention)
    {
        $reason = trim((string) $request->input('reason', ''));
        if ($reason === '') {
            return back()->withErrors(['reason' => 'Park reason is required.']);
        }
        $userId = (int) (Auth::id() ?? 0);
        if ($userId <= 0) {
            abort(403);
        }

        try {
            $this->recorder->recordPark($mention, $userId, $reason);
        } catch (\Throwable $e) {
            return back()->withErrors(['action' => 'Park failed: ' . $e->getMessage()]);
        }

        return $this->redirectToNext($mention, 'Mention parked for later review.');
    }

    /**
     * POST /admin/authority-resolution/review/{mention}/reject
     *
     * Task 9: accept an optional rejection_reason. Passed through to
     * DecisionRecorder::recordReject(), which hands it to
     * NerFeedbackService::captureFromRejection() so the next NER retraining
     * pass sees the archivist's note. Empty reasons are accepted; the
     * feedback row records "(no reason supplied)" in that case.
     */
    public function reject(Request $request, int $mention)
    {
        $userId = (int) (Auth::id() ?? 0);
        if ($userId <= 0) {
            abort(403);
        }

        $reason = trim((string) $request->input('rejection_reason', ''));

        try {
            $this->recorder->recordReject($mention, $userId, $reason !== '' ? $reason : null);
        } catch (\Throwable $e) {
            return back()->withErrors(['action' => 'Reject failed: ' . $e->getMessage()]);
        }

        return $this->redirectToNext($mention, 'Mention rejected as false positive.');
    }

    /**
     * GET /admin/authority-resolution/lookup?q=...&type=PERSON|ORG|PLACE
     *
     * Typeahead for the "Link to different existing authority" modal. Uses
     * the same MysqlActorAdapter / MysqlTermAdapter from Task 3 so all the
     * de-dup / culture-preference / taxonomy-id logic stays in one place.
     */
    public function lookup(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $type = trim((string) $request->query('type', ''));

        if ($q === '' || mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $rows = [];
        if (in_array($type, ['PERSON', 'ORG'], true)) {
            $rows = $this->actorAdapter->search($q, $type, 20);
        } elseif (in_array($type, self::PLACE_TYPES, true)) {
            $rows = $this->termAdapter->search($q, $type, 20);
        } else {
            // Fallback - search both pools, capped at 20 combined.
            $rows = array_merge(
                $this->actorAdapter->search($q, 'PERSON', 10),
                $this->termAdapter->search($q, 'GPE', 10),
            );
        }

        return response()->json(['results' => $rows]);
    }

    /**
     * Pulls everything the show() view needs about the mention row.
     */
    private function loadMentionRow(int $mention): ?object
    {
        return DB::table('ahg_mention as m')
            ->join('ahg_ner_entity as n', 'n.id', '=', 'm.ner_entity_id')
            ->leftJoin('information_object as io', 'io.id', '=', 'm.object_id')
            ->where('m.id', $mention)
            ->first([
                'm.id',
                'm.entity_type',
                'm.state',
                'm.object_id',
                'm.promoted_at',
                'm.ner_entity_id',
                'm.assigned_to_user_id',
                'm.assigned_by_user_id',
                'm.assigned_at',
                'm.workflow_task_id',
                'n.entity_value',
                'n.confidence',
                'n.linked_actor_id',
                'n.status as ner_status',
                'io.identifier as io_identifier',
            ]);
    }

    private function jsonOrEmpty(?string $json): array
    {
        if (!$json) {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function redirectToNext(int $currentMentionId, string $notice)
    {
        $next = DB::table('ahg_mention')
            ->where('state', 'pending')
            ->where('id', '>', $currentMentionId)
            ->orderBy('id')
            ->value('id');

        if ($next) {
            return redirect()->route('auth-res.review.show', ['mention' => $next])
                ->with('notice', $notice);
        }

        return redirect()->route('auth-res.queue')
            ->with('notice', $notice . ' Queue empty for the next pending mention - returned to queue.');
    }
}
