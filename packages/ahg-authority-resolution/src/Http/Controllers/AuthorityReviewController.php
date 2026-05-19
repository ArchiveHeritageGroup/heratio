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
use AhgAuthorityResolution\Services\AuthorityCreator;
use AhgAuthorityResolution\Services\DecisionRecorder;
use AhgAuthorityResolution\Services\FieldProvenanceWriter;
use AhgAuthorityResolution\Services\Lookup\PrefillEngine;
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

        $q = DB::table('ahg_mention as m')
            ->join('ahg_ner_entity as n', 'n.id', '=', 'm.ner_entity_id')
            ->leftJoin(DB::raw('(SELECT mention_id, COUNT(*) AS c FROM ahg_mention_candidate GROUP BY mention_id) AS cc'), 'cc.mention_id', '=', 'm.id')
            ->select('m.id', 'm.entity_type', 'm.state', 'm.object_id', 'm.promoted_at',
                     'n.entity_value', 'n.confidence', DB::raw('COALESCE(cc.c, 0) AS candidate_count'));

        if ($state !== '' && $state !== 'any') {
            $q->where('m.state', $state);
        }
        if ($entityType !== '') {
            $q->where('m.entity_type', $entityType);
        }
        if ($objectId > 0) {
            $q->where('m.object_id', $objectId);
        }

        $rows = $q->orderBy('m.id')->limit(200)->get();

        $counts = DB::table('ahg_mention')
            ->select('state', DB::raw('COUNT(*) AS c'))
            ->groupBy('state')
            ->pluck('c', 'state')
            ->all();

        return view('auth-res::queue', [
            'rows' => $rows,
            'counts' => $counts,
            'filterEntityType' => $entityType,
            'filterObjectId' => $objectId,
            'filterState' => $state,
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
        $candidates = DB::table('ahg_mention_candidate')
            ->where('mention_id', $mention)
            ->orderByDesc('composite_score')
            ->orderBy('rank_position')
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
        ]);
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
