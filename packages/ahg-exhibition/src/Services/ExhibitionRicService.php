<?php

/**
 * ExhibitionRicService - Heratio ahg-exhibition
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgExhibition\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1195 - publish an Exhibition Space (the digital twin) into the RiC graph as a
 * first-class `rico:Activity`, tied to its displayed objects via `rico:includes`
 * (inverse `rico:isIncludedIn`). The exhibition then appears alongside archival activities in
 * the RiC graph, and each placed object's cross-collection connections list the exhibitions it
 * featured in. Writes go through the RiC entity engine (ahg-ric) - we call it, never edit it.
 *
 * heratio#1218 (from #1214 item 3) - emit RICHER RiC relations for the exhibition Activity so
 * the graph carries who took part, where it happened, and when:
 *   - had participant : Activity -> Agent  via `rico:isOrWasPerformedBy`
 *                       (inverse `rico:performsOrPerformed`). RiC-O has no distinct
 *                       "hadParticipant"; participation in an Activity is expressed through
 *                       the performance relation, so curators / designers / organisers of an
 *                       exhibition are modelled as agents that performed the Activity.
 *   - took place at   : Activity -> Place  via `rico:hasOrHadLocation`
 *                       (inverse `rico:isOrWasLocationOf`). This is the canonical RiC-O
 *                       location relation; its domain is open (rico:Thing) so it applies to an
 *                       Activity locating its venue.
 *   - has date        : the Activity's begin/end dates (RiC-O `ric:beginningDate` /
 *                       `ric:endDate` as date-typed attributes). This schema models a date as a
 *                       literal attribute on `ric_activity` (start_date / end_date), not as a
 *                       separate rico:Date instance, so "has date" is honoured by writing the
 *                       exhibition's opening / closing dates onto the Activity and stamping each
 *                       emitted relation row with the same date range. No rico:Date entity table
 *                       exists and creating one would be a new table, which is out of scope.
 *
 * The relation predicates above are NOT hard-coded here: they are resolved from the
 * `ric_relation_type` dropdown by RicEntityService::createRelation(), which reads
 * metadata['predicate'] / metadata['inverse'] for the dropdown code (defaulting to
 * `rico:isAssociatedWith`). We pass the dropdown codes below; the engine supplies the CURIEs.
 */
class ExhibitionRicService
{
    /** RiC relation-type dropdown code for "Activity includes Thing". */
    private const INCLUDES = 'includes';

    /**
     * heratio#1218 dropdown codes whose ric_relation_type metadata carries the canonical
     * RiC-O predicate + inverse. We pass the code; RicEntityService resolves the CURIE.
     */
    private const PERFORMED_BY = 'performed_by';        // rico:isOrWasPerformedBy  (Activity -> Agent)

    private const HAD_LOCATION = 'has_or_had_location'; // rico:hasOrHadLocation     (Activity -> Place)

    /** Whether the RiC engine is available to publish into. */
    public function available(): bool
    {
        return class_exists(\AhgRic\Services\RicEntityService::class)
            && Schema::hasTable('ric_activity');
    }

    /**
     * Create/refresh the Activity for a space and link its placed objects.
     *
     * @return array{ok:bool, activity_id:int, linked:int, already:int, objects:int, participants:int, venues:int, dated:bool, error?:string}
     */
    public function syncSpace(object $space): array
    {
        $out = ['ok' => false, 'activity_id' => 0, 'linked' => 0, 'already' => 0, 'objects' => 0, 'participants' => 0, 'venues' => 0, 'dated' => false, 'error' => ''];
        if (! $this->available()) {
            $out['error'] = 'The RiC graph engine is not available on this install.';

            return $out;
        }

        $ric = app(\AhgRic\Services\RicEntityService::class);

        // Distinct, real placed objects in this space (skip building-level corridor markers).
        $objectIds = DB::table('ahg_exhibition_placement')
            ->where('exhibition_space_id', $space->id)
            ->whereNotNull('information_object_id')
            ->where(function ($q) { $q->whereNull('wall_or_zone')->orWhere('wall_or_zone', '!=', 'corridor'); })
            ->distinct()->pluck('information_object_id')->map(fn ($v) => (int) $v)->all();
        $out['objects'] = count($objectIds);

        // Run dates from the placements, if any.
        $dates = DB::table('ahg_exhibition_placement')
            ->where('exhibition_space_id', $space->id)
            ->selectRaw('MIN(starts_at) as s, MAX(ends_at) as e')->first();

        // heratio#1218 - the catalogue exhibition(s) this space's placements belong to. The
        // participant / venue / authoritative date data lives on the `exhibition` row, linked
        // through ahg_exhibition_placement.exhibition_id. When the link is absent (NULL) we
        // simply have no richer data and emit nothing extra - the #1195 behaviour is unchanged.
        $exhibition = $this->primaryExhibitionForSpace((int) $space->id);

        // Prefer the catalogue exhibition's opening/closing dates as the Activity's "has date";
        // fall back to the placement-derived run dates (the #1195 source).
        $startDate = ($exhibition->opening_date ?? null) ?: ($dates->s ?? null);
        $endDate = ($exhibition->closing_date ?? null) ?: ($exhibition->actual_closing_date ?? null) ?: ($dates->e ?? null);

        $payload = [
            'name' => $space->name,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'date_display' => $this->dateDisplay($startDate, $endDate),
            'description' => 'Exhibition space (digital twin) published from Heratio.',
        ];

        try {
            $activityId = (int) ($space->ric_activity_id ?? 0);
            if ($activityId > 0 && $ric->getActivityById($activityId)) {
                $ric->updateActivity($activityId, $payload);
            } else {
                $activityId = (int) $ric->createActivity($payload);
                DB::table('ahg_exhibition_space')->where('id', $space->id)->update(['ric_activity_id' => $activityId, 'updated_at' => now()]);
            }
            $out['activity_id'] = $activityId;
            $out['dated'] = (bool) ($startDate || $endDate);

            // Already-linked objects (this activity -> object), to keep re-sync idempotent.
            $existing = DB::table('relation')->where('subject_id', $activityId)
                ->whereIn('object_id', $objectIds ?: [0])->pluck('object_id')->map(fn ($v) => (int) $v)->all();

            foreach ($objectIds as $ioId) {
                if (in_array($ioId, $existing, true)) {
                    $out['already']++;

                    continue;
                }
                $ric->createRelation($activityId, $ioId, self::INCLUDES);
                $out['linked']++;
            }

            // heratio#1218 - richer relations from the catalogue exhibition, when one is linked.
            if ($exhibition) {
                $out['participants'] = $this->syncParticipants($ric, $activityId, $exhibition, $startDate, $endDate);
                $out['venues'] = $this->syncVenue($ric, $activityId, $exhibition, $startDate, $endDate);
            }

            $out['ok'] = true;
        } catch (\Throwable $e) {
            Log::warning('[ahg-exhibition] RiC sync failed: '.$e->getMessage());
            $out['error'] = 'Could not publish to the RiC graph.';
        }

        return $out;
    }

    /**
     * heratio#1218 - "had participant" relations. Curator / designer / organiser of the
     * catalogue exhibition become rico:Agent entities the Activity isOrWasPerformedBy.
     * Each participant name is resolved to an existing Agent or created; the relation is
     * deduped by (activity, agent, performed_by) so re-publishing never duplicates.
     *
     * @return int number of NEW participant relations emitted
     */
    private function syncParticipants(\AhgRic\Services\RicEntityService $ric, int $activityId, object $exhibition, ?string $startDate, ?string $endDate): int
    {
        $emitted = 0;

        // Real, named participants only. curator_id may reference an existing actor; prefer it.
        $curatorLinkedByFk = false;
        if (! empty($exhibition->curator_id)) {
            $agentId = (int) $exhibition->curator_id;
            // Only honour the FK if it points at a real actor row.
            if (DB::table('actor')->where('id', $agentId)->exists()) {
                $curatorLinkedByFk = true;
                $emitted += $this->linkRelation($ric, $activityId, $agentId, self::PERFORMED_BY, $startDate, $endDate) ? 1 : 0;
            }
        }

        $names = array_filter(array_map(fn ($n) => $n !== null ? trim($n) : '', [
            $curatorLinkedByFk ? '' : ($exhibition->curator_name ?? ''), // skip name if FK already linked
            $exhibition->designer_name ?? '',
            $exhibition->organized_by ?? '',
        ]), fn ($n) => $n !== '');

        foreach (array_unique($names) as $name) {
            $agentId = $this->resolveAgent($ric, $name);
            if ($agentId > 0) {
                $emitted += $this->linkRelation($ric, $activityId, $agentId, self::PERFORMED_BY, $startDate, $endDate) ? 1 : 0;
            }
        }

        return $emitted;
    }

    /**
     * heratio#1218 - "took place at" relation. The catalogue exhibition's venue becomes a
     * rico:Place the Activity hasOrHadLocation. Resolved from exhibition_venue (via venue_id)
     * when present, otherwise from the denormalised venue_* columns on the exhibition row.
     * Deduped by (activity, place, has_or_had_location).
     *
     * @return int number of NEW venue relations emitted (0 or 1)
     */
    private function syncVenue(\AhgRic\Services\RicEntityService $ric, int $activityId, object $exhibition, ?string $startDate, ?string $endDate): int
    {
        $venueName = null;
        $address = null;

        if (! empty($exhibition->venue_id) && Schema::hasTable('exhibition_venue')) {
            $venue = DB::table('exhibition_venue')->where('id', (int) $exhibition->venue_id)->first();
            if ($venue) {
                $venueName = $venue->name ?? null;
                $address = $this->joinNonEmpty([
                    $venue->address_line1 ?? null, $venue->address_line2 ?? null,
                    $venue->city ?? null, $venue->province_state ?? null,
                    $venue->postal_code ?? null, $venue->country ?? null,
                ]);
            }
        }

        if (! $venueName) {
            $venueName = $exhibition->venue_name ?? null;
            $address = $this->joinNonEmpty([
                $exhibition->venue_address ?? null,
                $exhibition->venue_city ?? null,
                $exhibition->venue_country ?? null,
            ]);
        }

        $venueName = $venueName !== null ? trim($venueName) : null;
        if (! $venueName) {
            return 0; // no venue data -> skip cleanly
        }

        $placeId = $this->resolvePlace($ric, $venueName, $address);
        if ($placeId <= 0) {
            return 0;
        }

        return $this->linkRelation($ric, $activityId, $placeId, self::HAD_LOCATION, $startDate, $endDate) ? 1 : 0;
    }

    /**
     * The catalogue exhibition a space's placements belong to. Uses the most common non-null
     * exhibition_id among this space's placements. Returns null when no placement carries one.
     */
    private function primaryExhibitionForSpace(int $spaceId): ?object
    {
        if (! Schema::hasTable('exhibition') || ! Schema::hasTable('ahg_exhibition_placement')) {
            return null;
        }

        $exhibitionId = DB::table('ahg_exhibition_placement')
            ->where('exhibition_space_id', $spaceId)
            ->whereNotNull('exhibition_id')
            ->select('exhibition_id', DB::raw('COUNT(*) as c'))
            ->groupBy('exhibition_id')
            ->orderByDesc('c')
            ->value('exhibition_id');

        if (! $exhibitionId) {
            return null;
        }

        return DB::table('exhibition')->where('id', (int) $exhibitionId)->first();
    }

    /**
     * Resolve an existing Agent by authorised name (current culture) or create one.
     * Returns the agent (actor) id, or 0 on failure.
     */
    private function resolveAgent(\AhgRic\Services\RicEntityService $ric, string $name): int
    {
        $existing = DB::table('actor')
            ->join('actor_i18n', function ($j) {
                $j->on('actor.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', app()->getLocale());
            })
            ->where('actor_i18n.authorized_form_of_name', $name)
            ->value('actor.id');

        if ($existing) {
            return (int) $existing;
        }

        return (int) $ric->createAgent(['name' => $name]);
    }

    /**
     * Resolve an existing Place by name (current culture) or create one.
     * Returns the place id, or 0 on failure.
     */
    private function resolvePlace(\AhgRic\Services\RicEntityService $ric, string $name, ?string $address): int
    {
        $existing = DB::table('ric_place')
            ->join('ric_place_i18n', function ($j) {
                $j->on('ric_place.id', '=', 'ric_place_i18n.id')
                    ->where('ric_place_i18n.culture', '=', app()->getLocale());
            })
            ->where('ric_place_i18n.name', $name)
            ->value('ric_place.id');

        if ($existing) {
            return (int) $existing;
        }

        return (int) $ric->createPlace(array_filter([
            'name' => $name,
            'address' => $address,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    /**
     * Emit one Activity -> target relation with the given dropdown code, idempotently.
     * Returns true if a NEW relation was created, false if one already existed.
     */
    private function linkRelation(\AhgRic\Services\RicEntityService $ric, int $activityId, int $targetId, string $code, ?string $startDate, ?string $endDate): bool
    {
        if ($targetId <= 0 || $targetId === $activityId) {
            return false;
        }

        $already = DB::table('relation')
            ->join('ric_relation_meta', 'relation.id', '=', 'ric_relation_meta.relation_id')
            ->where('relation.subject_id', $activityId)
            ->where('relation.object_id', $targetId)
            ->where('ric_relation_meta.dropdown_code', $code)
            ->exists();

        if ($already) {
            return false;
        }

        $ric->createRelation($activityId, $targetId, $code, array_filter([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ], fn ($v) => $v !== null && $v !== ''));

        return true;
    }

    private function joinNonEmpty(array $parts): ?string
    {
        $clean = array_filter(array_map(fn ($p) => $p !== null ? trim((string) $p) : '', $parts), fn ($p) => $p !== '');

        return $clean ? implode(', ', $clean) : null;
    }

    private function dateDisplay(?string $start, ?string $end): ?string
    {
        $s = $start ? substr($start, 0, 10) : null;
        $e = $end ? substr($end, 0, 10) : null;
        if ($s && $e) {
            return $s === $e ? $s : "{$s} - {$e}";
        }

        return $s ?: $e;
    }
}
