<?php

/**
 * ReconstructionService - heratio#1206 "walk through what no longer exists".
 *
 * Associates a catalogue record about a lost / destroyed / no-longer-extant place
 * or building with a walkable exhibition-space digital twin that stands as its
 * virtual RECONSTRUCTION. A reconstruction IS an exhibition space (ahg-exhibition's
 * existing digital twin); this service only manages the link rows and reads the
 * record title (information_object_i18n) + the space slug (ahg_exhibition_space)
 * needed to drive the public gallery and the walkthrough route.
 *
 * FIRST SLICE: link / unlink / list. No reconstruction-specific 3D behaviour - the
 * walkthrough is the unchanged exhibition-space twin.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgExhibition\Services;

use Illuminate\Support\Facades\DB;

class ReconstructionService
{
    /**
     * Link a record (the lost place) to a reconstruction exhibition space.
     * Returns the new link row id.
     */
    public function link(int $ioId, int $spaceId, ?string $note): int
    {
        return (int) DB::table('ahg_lost_place_reconstruction')->insertGetId([
            'information_object_id' => $ioId,
            'exhibition_space_id' => $spaceId,
            'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            'created_at' => now(),
        ]);
    }

    /** Remove a single reconstruction link by its row id. */
    public function unlink(int $id): void
    {
        DB::table('ahg_lost_place_reconstruction')->where('id', $id)->delete();
    }

    /**
     * Reconstructions for a single record (the lost place), most recent first.
     * Each row carries the space slug + name so callers can build the walkthrough link.
     */
    public function forRecord(int $ioId): array
    {
        return DB::table('ahg_lost_place_reconstruction as lpr')
            ->leftJoin('ahg_exhibition_space as es', 'es.id', '=', 'lpr.exhibition_space_id')
            ->where('lpr.information_object_id', $ioId)
            ->orderByDesc('lpr.id')
            ->select(
                'lpr.id', 'lpr.information_object_id', 'lpr.exhibition_space_id',
                'lpr.note', 'lpr.created_at',
                'es.slug as space_slug', 'es.name as space_name'
            )
            ->get()
            ->all();
    }

    /**
     * Every reconstruction, enriched with the lost-place record title and the
     * space slug, for the public gallery. Most recent first.
     */
    public function all(): array
    {
        return DB::table('ahg_lost_place_reconstruction as lpr')
            ->leftJoin('ahg_exhibition_space as es', 'es.id', '=', 'lpr.exhibition_space_id')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('ioi.id', '=', 'lpr.information_object_id')->where('ioi.culture', '=', 'en');
            })
            ->orderByDesc('lpr.id')
            ->select(
                'lpr.id', 'lpr.information_object_id', 'lpr.exhibition_space_id',
                'lpr.note', 'lpr.created_at',
                'ioi.title as record_title',
                'es.slug as space_slug', 'es.name as space_name'
            )
            ->get()
            ->all();
    }
}
