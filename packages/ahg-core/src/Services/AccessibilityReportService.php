<?php

/**
 * AccessibilityReportService - Heratio ahg-core
 *
 * heratio#1211 north-star ("every museum for everyone"), accessibility slice: a
 * read-only DIGITAL ACCESSIBILITY coverage report. It answers, honestly and
 * cheaply, "how much of the PUBLISHED collection is reachable by a visitor who
 * relies on alternative text, captions / subtitles, transcripts, or a language
 * other than the catalogue's primary one?"
 *
 * It is a HEURISTIC coverage report, NOT a WCAG conformance audit. It scores a
 * handful of accessibility-relevant METADATA signals that Heratio actually stores
 * and reports the proportion of published content that carries them. Where the
 * schema has no place to record a signal (e.g. a dedicated image alt-text column),
 * the area is graded "Not measured" and a gap recommendation is shown - the report
 * never invents coverage it cannot evidence. The relevant success criteria are
 * cited where applicable (WCAG 2.1 AA: 1.1.1 Non-text Content, 1.2.2 Captions,
 * 1.2.3/1.2.5 Audio Description / Media Alternative, 3.1.1/3.1.2 Language).
 *
 * What it measures (cheap aggregate COUNTs only - NO per-record loops):
 *   - IMAGE digital objects on published records that carry a text alternative -
 *     EITHER a genuine human-authored entry in the dedicated image_alt_text store
 *     (the heratio#1211 curation slice), OR - as a fallback - the embedded IPTC/XMP
 *     caption in digital_object_metadata.description - vs those that carry neither.
 *     The curated store is the real WCAG 1.1.1 signal; the caption is a fallback.
 *   - AUDIO/VIDEO digital objects (on published records) that have at least one
 *     active caption / subtitle track (media_caption_track) vs those without.
 *   - AUDIO/VIDEO digital objects that have a transcript (media_transcription)
 *     vs those without.
 *   - 3D models (object_3d_model) that carry alt text vs those without - the one
 *     surrogate type that DOES have a dedicated alt_text column.
 *   - PUBLISHED records available to read in more than one language
 *     (information_object_i18n cultures with a real title).
 *
 * Each figure is a grouped/aggregate COUNT, each query Schema::hasTable /
 * hasColumn-guarded and wrapped in its own try/catch, so a missing table or
 * column yields a "Not measured" area rather than a 500. The service performs NO
 * writes, runs NO ALTER, and makes NO AI calls.
 *
 * Published = a `status` row with type_id 158 (publication status) and status_id
 * 160 (published). The synthetic root description (id 1) is excluded.
 *
 * Jurisdiction-neutral / international: no country-specific assumptions; WCAG is a
 * global standard and is used here as a neutral reference grid only.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccessibilityReportService
{
    /** Publication-status taxonomy: status.type_id of a publication-status row. */
    private const STATUS_TYPE_PUBLICATION = 158;

    /** status.status_id meaning "published". */
    private const STATUS_PUBLISHED = 160;

    /** Synthetic root information object - never a real description. */
    private const ROOT_ID = 1;

    /** Coverage-level bands, mapped from a coverage percentage. */
    public const LEVEL_NOT_MEASURED = -1;
    public const LEVEL_NONE = 0;
    public const LEVEL_LOW = 1;
    public const LEVEL_PARTIAL = 2;
    public const LEVEL_GOOD = 3;
    public const LEVEL_STRONG = 4;

    /**
     * Filename extensions that mark an image digital object, used as a cheap
     * fallback when mime_type is absent. Matched with a case-insensitive LIKE.
     *
     * @var array<int,string>
     */
    private const EXT_IMAGE = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'tif', 'tiff', 'jp2', 'bmp', 'heic'];

    /**
     * Filename extensions that mark an audio/video digital object.
     *
     * @var array<int,string>
     */
    private const EXT_AV = ['mp4', 'mov', 'avi', 'mkv', 'webm', 'm4v', 'mpg', 'mpeg', 'mp3', 'wav', 'm4a', 'flac', 'ogg', 'aac', 'wma'];

    /**
     * Build the full accessibility coverage snapshot.
     *
     * @return array{
     *     framework:string,
     *     framework_note:string,
     *     total_published:int,
     *     areas: array<int, array{
     *         key:string, name:string, subtitle:string, wcag:string,
     *         measured:bool, with:int, total:int, pct:float,
     *         level:int, level_name:string, evidence:string, gap:string
     *     }>,
     *     overall_level:int,
     *     overall_level_name:string,
     *     generated_at:string,
     *     error:bool
     * }
     */
    public function snapshot(): array
    {
        $total = $this->countPublished();

        $areas = [
            $this->imageAltArea(),
            $this->captionsArea(),
            $this->transcriptArea(),
            $this->threeDAltArea(),
            $this->multilingualArea($total),
        ];

        // Overall level = the lowest MEASURED area level (accessibility is only as
        // strong as its weakest measured area). Unmeasured areas do not drag the
        // overall down - they are honestly excluded, not scored as zero.
        $measuredLevels = [];
        foreach ($areas as $a) {
            if ($a['measured']) {
                $measuredLevels[] = (int) $a['level'];
            }
        }
        $overall = empty($measuredLevels) ? self::LEVEL_NOT_MEASURED : min($measuredLevels);

        return [
            'framework'          => 'WCAG 2.1 AA (heuristic coverage, not a conformance audit)',
            'framework_note'     => 'This is a heuristic coverage report over the accessibility-relevant metadata Heratio stores. It is not a WCAG conformance audit, which also requires a full review of the running interface (keyboard operability, contrast, focus order, etc). Figures cover PUBLISHED content only.',
            'total_published'    => $total,
            'areas'              => $areas,
            'overall_level'      => $overall,
            'overall_level_name' => $this->levelName($overall),
            'generated_at'       => now()->toDateTimeString(),
            'error'              => false,
        ];
    }

    // ---------------------------------------------------------------------
    // Areas
    // ---------------------------------------------------------------------

    /**
     * Image alternative text (WCAG 1.1.1 Non-text Content).
     *
     * A published image counts as having a text alternative if EITHER a curator has
     * authored a genuine alt-text row in the dedicated image_alt_text store (the
     * heratio#1211 curation slice - human-authored, the real WCAG 1.1.1 signal), OR -
     * as a fallback - it carries an embedded IPTC/XMP caption in
     * digital_object_metadata.description. The two are OR-ed in a single bounded
     * aggregate. Either source can be absent independently: if neither the curation
     * store nor the metadata caption is queryable, the area is "Not measured".
     */
    private function imageAltArea(): array
    {
        $key = 'image_alt';
        $name = 'Image alternative text';
        $subtitle = 'Published image surrogates that carry a text alternative (curated entry or embedded caption)';
        $wcag = 'WCAG 2.1 - 1.1.1 Non-text Content (A)';

        $hasAltStore = Schema::hasTable('image_alt_text');
        $hasCaption = Schema::hasTable('digital_object_metadata')
            && Schema::hasColumn('digital_object_metadata', 'description');

        if (! Schema::hasTable('digital_object') || ! Schema::hasTable('information_object')
            || (! $hasAltStore && ! $hasCaption)) {
            return $this->notMeasured(
                $key, $name, $subtitle, $wcag,
                'No place to record image alternative text is available to measure (neither the curated alt-text store nor an embedded caption field).',
                'Use the alt-text curation worklist (/admin/alt-text) to author a genuine text alternative for each published image, so screen-reader users get a real non-text alternative (WCAG 1.1.1).'
            );
        }

        try {
            $total = $this->countPublishedImageObjects();
            if ($total <= 0) {
                return $this->emptyAreaButMeasured(
                    $key, $name, $subtitle, $wcag,
                    'No published image surrogates were found to assess.',
                    'When images are published, author a text alternative for each via the alt-text curation worklist so screen-reader users have a non-text alternative (WCAG 1.1.1).'
                );
            }

            // OR-in the new curated store AND the legacy caption proxy as two
            // whereExists legs, so the count stays a single bounded aggregate (no
            // per-row loop). Each leg is guarded so an absent source simply drops out.
            $with = (int) DB::table('digital_object as d')
                ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'd.object_id')
                ->where($this->imageWhere())
                ->where(function ($w) use ($hasAltStore, $hasCaption) {
                    if ($hasAltStore) {
                        $w->orWhereExists(function ($q) {
                            $q->select(DB::raw(1))
                                ->from('image_alt_text as a')
                                ->whereColumn('a.digital_object_id', 'd.id')
                                ->whereRaw("TRIM(COALESCE(a.alt_text,'')) <> ''");
                        });
                    }
                    if ($hasCaption) {
                        $w->orWhereExists(function ($q) {
                            $q->select(DB::raw(1))
                                ->from('digital_object_metadata as m')
                                ->whereColumn('m.digital_object_id', 'd.id')
                                ->whereNotNull('m.description')
                                ->whereRaw("TRIM(COALESCE(m.description,'')) <> ''");
                        });
                    }
                })
                ->distinct()
                ->count('d.id');

            $pct = $this->pct($with, $total);

            // How many published images carry GENUINE curated alt text (the new store),
            // so the evidence + recommendation reflect the curation surface honestly.
            $curated = 0;
            if ($hasAltStore) {
                $curated = (int) DB::table('digital_object as d')
                    ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'd.object_id')
                    ->join('image_alt_text as a', 'a.digital_object_id', '=', 'd.id')
                    ->where($this->imageWhere())
                    ->whereRaw("TRIM(COALESCE(a.alt_text,'')) <> ''")
                    ->distinct()
                    ->count('d.id');
            }

            $evidence = $this->evidenceFrac($with, $total, 'published image surrogate(s) carry a text alternative (a curated alt-text entry, or - as a fallback - the embedded IPTC/XMP caption)');
            if ($curated > 0) {
                $evidence .= ' '.number_format($curated).' of these now have genuine human-authored alternative text from the alt-text curation surface.';
            }

            $gap = 'Keep curating: open the alt-text worklist (/admin/alt-text) to author a genuine text alternative for the remaining published images (WCAG 1.1.1). Curated entries are the real signal; the embedded IPTC/XMP caption is only a fallback.';

            return $this->scoredArea(
                $key, $name, $subtitle, $wcag, $with, $total, $pct,
                $evidence,
                $gap
            );
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] accessibility imageAltArea failed: '.$e->getMessage());

            return $this->notMeasured($key, $name, $subtitle, $wcag,
                'The image alternative-text coverage could not be computed right now.',
                'Author alternative text for published images via the curation worklist (/admin/alt-text) (WCAG 1.1.1).');
        }
    }

    /**
     * Captions / subtitles for audio-visual content (WCAG 1.2.2 Captions).
     *
     * Counts AV digital objects (on published records) that have at least one
     * ACTIVE caption or subtitle track in media_caption_track.
     */
    private function captionsArea(): array
    {
        $key = 'captions';
        $name = 'Captions and subtitles';
        $subtitle = 'Published audio / video surrogates with at least one caption or subtitle track';
        $wcag = 'WCAG 2.1 - 1.2.2 Captions (A)';

        if (! Schema::hasTable('digital_object') || ! Schema::hasTable('information_object')
            || ! Schema::hasTable('media_caption_track')) {
            return $this->notMeasured($key, $name, $subtitle, $wcag,
                'Heratio is not storing caption or subtitle tracks (no media_caption_track table), so caption coverage cannot be measured.',
                'Enable caption / subtitle tracks for audio-visual surrogates so deaf and hard-of-hearing visitors can follow the content (WCAG 1.2.2).');
        }

        try {
            $total = $this->countPublishedAvObjects();
            if ($total <= 0) {
                return $this->emptyAreaButMeasured($key, $name, $subtitle, $wcag,
                    'No published audio or video surrogates were found to assess.',
                    'When audio-visual content is published, add a caption or subtitle track for each item (WCAG 1.2.2).');
            }

            $activeClause = Schema::hasColumn('media_caption_track', 'active');

            $with = (int) DB::table('digital_object as d')
                ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'd.object_id')
                ->where($this->avWhere())
                ->whereExists(function ($q) use ($activeClause) {
                    $q->select(DB::raw(1))
                        ->from('media_caption_track as c')
                        ->whereColumn('c.digital_object_id', 'd.id')
                        ->whereIn('c.track_type', ['caption', 'subtitle']);
                    if ($activeClause) {
                        $q->where('c.active', 1);
                    }
                })
                ->distinct()
                ->count('d.id');

            $pct = $this->pct($with, $total);

            return $this->scoredArea($key, $name, $subtitle, $wcag, $with, $total, $pct,
                $this->evidenceFrac($with, $total, 'published audio / video surrogate(s) carry at least one caption or subtitle track'),
                'Add a caption or subtitle track to the remaining published audio-visual surrogates so the spoken content is available as synchronised text (WCAG 1.2.2).');
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] accessibility captionsArea failed: '.$e->getMessage());

            return $this->notMeasured($key, $name, $subtitle, $wcag,
                'Caption coverage could not be computed right now.',
                'Add caption / subtitle tracks to audio-visual surrogates (WCAG 1.2.2).');
        }
    }

    /**
     * Transcripts for audio-visual content (WCAG 1.2.3 / 1.2.5 - text alternative
     * / audio description). A full transcript is a recognised media alternative.
     *
     * Counts AV digital objects (on published records) that have a transcript row
     * in media_transcription with real text.
     */
    private function transcriptArea(): array
    {
        $key = 'transcripts';
        $name = 'Transcripts';
        $subtitle = 'Published audio / video surrogates with a text transcript';
        $wcag = 'WCAG 2.1 - 1.2.3 / 1.2.5 Media Alternative (A / AA)';

        if (! Schema::hasTable('digital_object') || ! Schema::hasTable('information_object')
            || ! Schema::hasTable('media_transcription')) {
            return $this->notMeasured($key, $name, $subtitle, $wcag,
                'Heratio is not storing transcripts (no media_transcription table), so transcript coverage cannot be measured.',
                'Capture a text transcript for audio-visual surrogates as a full media alternative (WCAG 1.2.3 / 1.2.5).');
        }

        try {
            $total = $this->countPublishedAvObjects();
            if ($total <= 0) {
                return $this->emptyAreaButMeasured($key, $name, $subtitle, $wcag,
                    'No published audio or video surrogates were found to assess.',
                    'When audio-visual content is published, capture a transcript for each item (WCAG 1.2.3 / 1.2.5).');
            }

            $hasFullText = Schema::hasColumn('media_transcription', 'full_text');

            $with = (int) DB::table('digital_object as d')
                ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'd.object_id')
                ->where($this->avWhere())
                ->whereExists(function ($q) use ($hasFullText) {
                    $q->select(DB::raw(1))
                        ->from('media_transcription as t')
                        ->whereColumn('t.digital_object_id', 'd.id');
                    if ($hasFullText) {
                        $q->whereNotNull('t.full_text')
                            ->whereRaw("TRIM(COALESCE(t.full_text,'')) <> ''");
                    }
                })
                ->distinct()
                ->count('d.id');

            $pct = $this->pct($with, $total);

            return $this->scoredArea($key, $name, $subtitle, $wcag, $with, $total, $pct,
                $this->evidenceFrac($with, $total, 'published audio / video surrogate(s) have a text transcript'),
                'Capture a transcript for the remaining published audio-visual surrogates so the content is fully available as text (WCAG 1.2.3 / 1.2.5).');
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] accessibility transcriptArea failed: '.$e->getMessage());

            return $this->notMeasured($key, $name, $subtitle, $wcag,
                'Transcript coverage could not be computed right now.',
                'Capture transcripts for audio-visual surrogates (WCAG 1.2.3 / 1.2.5).');
        }
    }

    /**
     * 3D model alternative text (WCAG 1.1.1 Non-text Content). 3D models are the
     * one surrogate type with a dedicated alt_text column (object_3d_model), so
     * this is a direct, honest measure rather than a proxy. Scoped to models on
     * published records.
     */
    private function threeDAltArea(): array
    {
        $key = 'model_alt';
        $name = '3D model alternative text';
        $subtitle = 'Published 3D models that carry alternative text';
        $wcag = 'WCAG 2.1 - 1.1.1 Non-text Content (A)';

        if (! Schema::hasTable('object_3d_model') || ! Schema::hasTable('information_object')
            || ! Schema::hasColumn('object_3d_model', 'alt_text')) {
            return $this->notMeasured($key, $name, $subtitle, $wcag,
                'No 3D-model alternative-text field is available to measure.',
                'Record alternative text for each published 3D model (WCAG 1.1.1).');
        }

        try {
            $total = (int) DB::table('object_3d_model as m')
                ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'm.object_id')
                ->count();

            if ($total <= 0) {
                return $this->emptyAreaButMeasured($key, $name, $subtitle, $wcag,
                    'No published 3D models were found to assess.',
                    'When 3D models are published, record alternative text for each (WCAG 1.1.1).');
            }

            $with = (int) DB::table('object_3d_model as m')
                ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'm.object_id')
                ->whereNotNull('m.alt_text')
                ->whereRaw("TRIM(COALESCE(m.alt_text,'')) <> ''")
                ->count();

            $pct = $this->pct($with, $total);

            return $this->scoredArea($key, $name, $subtitle, $wcag, $with, $total, $pct,
                $this->evidenceFrac($with, $total, 'published 3D model(s) carry alternative text'),
                'Record alternative text for the remaining published 3D models so non-visual users get a text alternative (WCAG 1.1.1).');
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] accessibility threeDAltArea failed: '.$e->getMessage());

            return $this->notMeasured($key, $name, $subtitle, $wcag,
                '3D-model alternative-text coverage could not be computed right now.',
                'Record alternative text for published 3D models (WCAG 1.1.1).');
        }
    }

    /**
     * Multilingual reach (WCAG 3.1.1 / 3.1.2 Language). Published records that can
     * be read in MORE THAN ONE language - i.e. records whose i18n carries a real
     * title in two or more cultures. This is a discovery/equity signal: a record
     * available in only one language excludes readers of every other language.
     */
    private function multilingualArea(int $total): array
    {
        $key = 'multilingual';
        $name = 'Multilingual access';
        $subtitle = 'Published records readable in more than one language';
        $wcag = 'WCAG 2.1 - 3.1.1 / 3.1.2 Language of Page / Parts (A / AA)';

        if (! Schema::hasTable('information_object_i18n') || ! Schema::hasTable('status')) {
            return $this->notMeasured($key, $name, $subtitle, $wcag,
                'Per-language description records are not available to measure.',
                'Provide descriptions in additional languages so the collection reaches readers beyond its primary language (WCAG 3.1).');
        }

        try {
            if ($total <= 0) {
                return $this->emptyAreaButMeasured($key, $name, $subtitle, $wcag,
                    'No published records were found to assess.',
                    'As records are published, add titles in additional languages to widen multilingual reach (WCAG 3.1).');
            }

            // Per published record: how many distinct cultures carry a real title.
            $perRecord = DB::table('information_object_i18n as i')
                ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'i.id')
                ->whereNotNull('i.culture')
                ->whereRaw("TRIM(i.culture) <> ''")
                ->whereNotNull('i.title')
                ->whereRaw("TRIM(COALESCE(i.title,'')) <> ''")
                ->groupBy('i.id')
                ->select(['i.id', DB::raw('COUNT(DISTINCT i.culture) as langs')]);

            $with = (int) DB::query()
                ->fromSub($perRecord, 'r')
                ->where('r.langs', '>', 1)
                ->count();

            $pct = $this->pct($with, $total);

            return $this->scoredArea($key, $name, $subtitle, $wcag, $with, $total, $pct,
                $this->evidenceFrac($with, $total, 'published record(s) are readable in more than one language'),
                'Add titles (and, ideally, descriptive prose) in additional languages so more of the collection is readable beyond its primary language (WCAG 3.1). The per-record on-demand translation surface complements, but does not replace, authored multilingual metadata.');
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] accessibility multilingualArea failed: '.$e->getMessage());

            return $this->notMeasured($key, $name, $subtitle, $wcag,
                'Multilingual coverage could not be computed right now.',
                'Provide descriptions in additional languages (WCAG 3.1).');
        }
    }

    // ---------------------------------------------------------------------
    // Counting helpers (cheap aggregates only)
    // ---------------------------------------------------------------------

    /**
     * Total PUBLISHED, non-root information objects.
     *
     * SQL: SELECT COUNT(DISTINCT object_id) FROM status
     *      WHERE type_id=158 AND status_id=160 AND object_id > 1
     */
    public function countPublished(): int
    {
        if (! Schema::hasTable('status')) {
            return 0;
        }
        try {
            return (int) DB::table('status')
                ->where('type_id', self::STATUS_TYPE_PUBLICATION)
                ->where('status_id', self::STATUS_PUBLISHED)
                ->where('object_id', '>', self::ROOT_ID)
                ->distinct()
                ->count('object_id');
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] accessibility countPublished failed: '.$e->getMessage());

            return 0;
        }
    }

    /** Reusable subquery: object_ids of every published, non-root record. */
    private function publishedIdSub()
    {
        return DB::table('status')
            ->select('object_id')
            ->where('type_id', self::STATUS_TYPE_PUBLICATION)
            ->where('status_id', self::STATUS_PUBLISHED)
            ->where('object_id', '>', self::ROOT_ID);
    }

    /** Published IMAGE digital objects (distinct surrogate count). */
    private function countPublishedImageObjects(): int
    {
        return (int) DB::table('digital_object as d')
            ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'd.object_id')
            ->where($this->imageWhere())
            ->distinct()
            ->count('d.id');
    }

    /** Published AUDIO/VIDEO digital objects (distinct surrogate count). */
    private function countPublishedAvObjects(): int
    {
        return (int) DB::table('digital_object as d')
            ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'd.object_id')
            ->where($this->avWhere())
            ->distinct()
            ->count('d.id');
    }

    /**
     * WHERE closure selecting IMAGE digital objects: mime_type LIKE 'image/%' OR
     * (no mime type) a known image filename extension. Kept as a single bounded
     * OR so the surrounding query stays one aggregate.
     */
    private function imageWhere(): \Closure
    {
        return function ($w) {
            $w->where('d.mime_type', 'like', 'image/%')
                ->orWhere(function ($x) {
                    foreach (self::EXT_IMAGE as $ext) {
                        $x->orWhereRaw('LOWER(d.name) LIKE ?', ['%.'.strtolower($ext)]);
                    }
                });
        };
    }

    /**
     * WHERE closure selecting AUDIO/VIDEO digital objects: mime_type LIKE 'audio/%'
     * OR 'video/%' OR a known AV filename extension.
     */
    private function avWhere(): \Closure
    {
        return function ($w) {
            $w->where('d.mime_type', 'like', 'audio/%')
                ->orWhere('d.mime_type', 'like', 'video/%')
                ->orWhere(function ($x) {
                    foreach (self::EXT_AV as $ext) {
                        $x->orWhereRaw('LOWER(d.name) LIKE ?', ['%.'.strtolower($ext)]);
                    }
                });
        };
    }

    // ---------------------------------------------------------------------
    // Area builders / scoring
    // ---------------------------------------------------------------------

    /**
     * Build a scored, measured area from a with/total/pct triple.
     *
     * @return array<string,mixed>
     */
    private function scoredArea(
        string $key, string $name, string $subtitle, string $wcag,
        int $with, int $total, float $pct, string $evidence, string $gap
    ): array {
        $level = $this->levelForCoverage($pct);

        return [
            'key'        => $key,
            'name'       => $name,
            'subtitle'   => $subtitle,
            'wcag'       => $wcag,
            'measured'   => true,
            'with'       => $with,
            'total'      => $total,
            'pct'        => $pct,
            'level'      => $level,
            'level_name' => $this->levelName($level),
            'evidence'   => $evidence,
            // Once an area is at full coverage there is no remaining gap to report.
            'gap'        => $pct >= 100.0 ? '' : $gap,
        ];
    }

    /**
     * An area that COULD be measured but has zero applicable content (e.g. no
     * published audio-visual surrogates at all). Excluded from the overall score:
     * coverage of an empty set is N/A, not a failure. The evidence/gap are kept
     * neutral and forward-looking.
     *
     * @return array<string,mixed>
     */
    private function emptyAreaButMeasured(
        string $key, string $name, string $subtitle, string $wcag,
        string $evidence, string $gap
    ): array {
        return [
            'key'        => $key,
            'name'       => $name,
            'subtitle'   => $subtitle,
            'wcag'       => $wcag,
            'measured'   => false, // nothing applicable -> excluded from overall
            'with'       => 0,
            'total'      => 0,
            'pct'        => 0.0,
            'level'      => self::LEVEL_NOT_MEASURED,
            'level_name' => $this->levelName(self::LEVEL_NOT_MEASURED),
            'evidence'   => $evidence,
            'gap'        => $gap,
        ];
    }

    /**
     * An area that cannot be measured at all (missing table / column / error).
     * Excluded from the overall score; shown with an honest evidence + gap.
     *
     * @return array<string,mixed>
     */
    private function notMeasured(
        string $key, string $name, string $subtitle, string $wcag,
        string $evidence, string $gap
    ): array {
        return [
            'key'        => $key,
            'name'       => $name,
            'subtitle'   => $subtitle,
            'wcag'       => $wcag,
            'measured'   => false,
            'with'       => 0,
            'total'      => 0,
            'pct'        => 0.0,
            'level'      => self::LEVEL_NOT_MEASURED,
            'level_name' => $this->levelName(self::LEVEL_NOT_MEASURED),
            'evidence'   => $evidence,
            'gap'        => $gap,
        ];
    }

    /** Map a coverage percentage to a 0..4 level band. */
    private function levelForCoverage(float $pct): int
    {
        return match (true) {
            $pct >= 95.0 => self::LEVEL_STRONG,
            $pct >= 75.0 => self::LEVEL_GOOD,
            $pct >= 40.0 => self::LEVEL_PARTIAL,
            $pct > 0.0   => self::LEVEL_LOW,
            default      => self::LEVEL_NONE,
        };
    }

    /** Human label for a level band. */
    private function levelName(int $level): string
    {
        return match ($level) {
            self::LEVEL_STRONG  => 'Strong',
            self::LEVEL_GOOD    => 'Good',
            self::LEVEL_PARTIAL => 'Partial',
            self::LEVEL_LOW     => 'Low',
            self::LEVEL_NONE    => 'None yet',
            default             => 'Not measured',
        };
    }

    /** A standard "X of Y (pct%) ..." evidence sentence. */
    private function evidenceFrac(int $with, int $total, string $tail): string
    {
        return number_format($with).' of '.number_format($total).' ('.$this->pct($with, $total).'%) '.$tail.'.';
    }

    /** Safe percentage (0-100, one decimal). Zero base -> 0.0, never divide-by-zero. */
    private function pct(int $part, int $base): float
    {
        if ($base <= 0) {
            return 0.0;
        }

        return round(($part / $base) * 100, 1);
    }
}
