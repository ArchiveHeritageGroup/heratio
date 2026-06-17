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

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReconstructionService
{
    /** Allowed montage styles. The DB default + dropdown seed agree on these. */
    public const STYLES = ['assembly', 'timelapse'];

    /** Image extensions we accept for an uploaded evidence layer. */
    private const IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];

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

    /**
     * One reconstruction row (the lost place) enriched with the lost-place record
     * title, the linked space slug + name and the montage style. Null if absent.
     * heratio#1219 - the player page (reconstruction.show) reads this.
     */
    public function getById(int $id): ?object
    {
        if (! Schema::hasTable('ahg_lost_place_reconstruction')) {
            return null;
        }

        $hasStyle = Schema::hasColumn('ahg_lost_place_reconstruction', 'montage_style');

        $row = DB::table('ahg_lost_place_reconstruction as lpr')
            ->leftJoin('ahg_exhibition_space as es', 'es.id', '=', 'lpr.exhibition_space_id')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('ioi.id', '=', 'lpr.information_object_id')->where('ioi.culture', '=', 'en');
            })
            ->where('lpr.id', $id)
            ->select(
                'lpr.id', 'lpr.information_object_id', 'lpr.exhibition_space_id',
                'lpr.note', 'lpr.created_at',
                DB::raw($hasStyle ? 'lpr.montage_style as montage_style' : "'assembly' as montage_style"),
                'ioi.title as record_title',
                'es.slug as space_slug', 'es.name as space_name'
            )
            ->first();

        if ($row && (! isset($row->montage_style) || ! in_array($row->montage_style, self::STYLES, true))) {
            $row->montage_style = 'assembly';
        }

        return $row;
    }

    /**
     * Ordered rebuild stages for a reconstruction. Empty array when the table is
     * missing or there are no stages yet (the player degrades to an empty state).
     */
    public function stagesFor(int $reconstructionId): array
    {
        if (! Schema::hasTable('ahg_reconstruction_stage')) {
            return [];
        }

        return DB::table('ahg_reconstruction_stage')
            ->where('reconstruction_id', $reconstructionId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->all();
    }

    /** A single stage row by its id, or null. */
    public function getStage(int $stageId): ?object
    {
        if (! Schema::hasTable('ahg_reconstruction_stage')) {
            return null;
        }

        return DB::table('ahg_reconstruction_stage')->where('id', $stageId)->first();
    }

    /**
     * Add a rebuild stage. An optional uploaded evidence image is stored under
     * {uploads_path}/reconstructions/stages/ with a generated filename (the client
     * filename is never trusted); its stored path is persisted in image_path.
     * Returns the new stage id.
     */
    public function addStage(int $reconstructionId, array $data, ?UploadedFile $image = null): int
    {
        $stored = $this->storeImage($image);

        $sortOrder = $data['sort_order'] ?? null;
        if ($sortOrder === null || $sortOrder === '') {
            $sortOrder = (int) (DB::table('ahg_reconstruction_stage')
                ->where('reconstruction_id', $reconstructionId)
                ->max('sort_order') ?? 0) + 10;
        }

        return (int) DB::table('ahg_reconstruction_stage')->insertGetId([
            'reconstruction_id' => $reconstructionId,
            'sort_order' => (int) $sortOrder,
            'caption' => $this->clean($data['caption'] ?? null, 255),
            'body' => $this->clean($data['body'] ?? null, 65000),
            'date_display' => $this->clean($data['date_display'] ?? null, 64),
            'image_path' => $stored,
            'image_url' => $stored ? null : $this->clean($data['image_url'] ?? null, 1024),
            'opacity' => $this->clampOpacity($data['opacity'] ?? 1.0),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Update a rebuild stage. A new uploaded image replaces image_path (and clears
     * image_url). Without a new upload, image_url may be edited directly.
     */
    public function updateStage(int $stageId, array $data, ?UploadedFile $image = null): void
    {
        $update = [
            'caption' => $this->clean($data['caption'] ?? null, 255),
            'body' => $this->clean($data['body'] ?? null, 65000),
            'date_display' => $this->clean($data['date_display'] ?? null, 64),
            'opacity' => $this->clampOpacity($data['opacity'] ?? 1.0),
            'updated_at' => now(),
        ];

        if (array_key_exists('sort_order', $data) && $data['sort_order'] !== null && $data['sort_order'] !== '') {
            $update['sort_order'] = (int) $data['sort_order'];
        }

        $stored = $this->storeImage($image);
        if ($stored !== null) {
            $update['image_path'] = $stored;
            $update['image_url'] = null;
        } elseif (array_key_exists('image_url', $data)) {
            $update['image_url'] = $this->clean($data['image_url'], 1024);
        }

        DB::table('ahg_reconstruction_stage')->where('id', $stageId)->update($update);
    }

    /**
     * heratio#1206 - persist the curator-confirmed AI evidence-layer metadata for a
     * stage as JSON (or clear it with null). No-op when the optional metadata column
     * has not been added yet, so the annotator is purely additive.
     */
    public function saveStageMetadata(int $stageId, ?array $metadata): void
    {
        if (! Schema::hasColumn('ahg_reconstruction_stage', 'metadata')) {
            return;
        }
        DB::table('ahg_reconstruction_stage')->where('id', $stageId)->update([
            'metadata' => $metadata !== null && $metadata !== [] ? json_encode($metadata) : null,
            'updated_at' => now(),
        ]);
    }

    /** Delete a single stage (and best-effort remove its uploaded image). */
    public function deleteStage(int $stageId): void
    {
        $row = $this->getStage($stageId);
        DB::table('ahg_reconstruction_stage')->where('id', $stageId)->delete();

        if ($row && ! empty($row->image_path)) {
            $abs = $this->absoluteStagePath($row->image_path);
            if ($abs !== null && is_file($abs)) {
                @unlink($abs);
            }
        }
    }

    /**
     * Persist a new stage ordering. $orderedIds is the desired display order;
     * each id is restamped to 10, 20, 30 ... (only rows belonging to this
     * reconstruction are touched).
     */
    public function reorderStages(int $reconstructionId, array $orderedIds): void
    {
        $order = 10;
        foreach ($orderedIds as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            DB::table('ahg_reconstruction_stage')
                ->where('id', $id)
                ->where('reconstruction_id', $reconstructionId)
                ->update(['sort_order' => $order, 'updated_at' => now()]);
            $order += 10;
        }
    }

    /** Set the per-reconstruction default montage style (validated against STYLES). */
    public function setStyle(int $reconstructionId, string $style): void
    {
        if (! in_array($style, self::STYLES, true)) {
            $style = 'assembly';
        }
        if (! Schema::hasColumn('ahg_lost_place_reconstruction', 'montage_style')) {
            return;
        }
        DB::table('ahg_lost_place_reconstruction')
            ->where('id', $reconstructionId)
            ->update(['montage_style' => $style]);
    }

    /**
     * Resolve the absolute on-disk path for a stage's stored image, or null if the
     * stored path escapes the reconstructions/stages directory (defence in depth).
     */
    public function absoluteStagePath(?string $imagePath): ?string
    {
        if ($imagePath === null || trim($imagePath) === '') {
            return null;
        }
        $base = $this->stageDir();
        $name = basename($imagePath);
        if ($name === '' || $name === '.' || $name === '..') {
            return null;
        }
        $abs = $base.'/'.$name;
        $real = realpath($abs);
        $realBase = realpath($base);
        if ($real === false || $realBase === false || strncmp($real, $realBase, strlen($realBase)) !== 0) {
            return null;
        }

        return $real;
    }

    /** Directory uploaded stage images live in. */
    private function stageDir(): string
    {
        return rtrim((string) config('heratio.uploads_path'), '/').'/reconstructions/stages';
    }

    /**
     * Move an uploaded image into the stage directory under a generated filename
     * and return the stored path, or null when no usable upload was supplied.
     * Client filenames are never trusted.
     */
    private function storeImage(?UploadedFile $image): ?string
    {
        if ($image === null || ! $image->isValid()) {
            return null;
        }

        $ext = strtolower($image->getClientOriginalExtension() ?: $image->guessExtension() ?: 'jpg');
        if (! in_array($ext, self::IMAGE_EXTS, true)) {
            $ext = 'jpg';
        }

        $dir = $this->stageDir();
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $filename = 'stage-'.date('Ymd').'-'.bin2hex(random_bytes(8)).'.'.$ext;
        $image->move($dir, $filename);

        return $dir.'/'.$filename;
    }

    /** Trim + length-cap a free-text value; null when blank. */
    private function clean($value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }

    /** Clamp opacity into the 0.00-1.00 range stored in DECIMAL(4,2). */
    private function clampOpacity($value): float
    {
        $v = is_numeric($value) ? (float) $value : 1.0;

        return max(0.0, min(1.0, $v));
    }
}
