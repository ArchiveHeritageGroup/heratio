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

/**
 * heratio#1195 - publish an Exhibition Space (the digital twin) into the RiC graph as a
 * first-class `rico:Activity`, tied to its displayed objects via `rico:includes`
 * (inverse `rico:isIncludedIn`). The exhibition then appears alongside archival activities in
 * the RiC graph, and each placed object's cross-collection connections list the exhibitions it
 * featured in. Writes go through the RiC entity engine (ahg-ric) - we call it, never edit it.
 */
class ExhibitionRicService
{
    /** RiC relation-type dropdown code for "Activity includes Thing". */
    private const INCLUDES = 'includes';

    /** Whether the RiC engine is available to publish into. */
    public function available(): bool
    {
        return class_exists(\AhgRic\Services\RicEntityService::class)
            && \Illuminate\Support\Facades\Schema::hasTable('ric_activity');
    }

    /**
     * Create/refresh the Activity for a space and link its placed objects.
     *
     * @return array{ok:bool, activity_id:int, linked:int, already:int, objects:int, error?:string}
     */
    public function syncSpace(object $space): array
    {
        $out = ['ok' => false, 'activity_id' => 0, 'linked' => 0, 'already' => 0, 'objects' => 0, 'error' => ''];
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
        $payload = [
            'name' => $space->name,
            'start_date' => $dates->s ?? null,
            'end_date' => $dates->e ?? null,
            'date_display' => $this->dateDisplay($dates->s ?? null, $dates->e ?? null),
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
            $out['ok'] = true;
        } catch (\Throwable $e) {
            Log::warning('[ahg-exhibition] RiC sync failed: '.$e->getMessage());
            $out['error'] = 'Could not publish to the RiC graph.';
        }

        return $out;
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
