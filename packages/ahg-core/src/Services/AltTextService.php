<?php

/**
 * AltTextService - Heratio ahg-core
 *
 * heratio#1211 ("every museum for everyone"), alt-text curation slice. The
 * accessibility coverage report surfaced that published image surrogates carry
 * essentially no genuine alternative text: the catalogue has no dedicated alt-text
 * column, so the report could only proxy from the embedded IPTC/XMP caption in
 * digital_object_metadata.description. This service backs a real, human-authored
 * alternative-text store - the `image_alt_text` side table - and the curation
 * surface on top of it.
 *
 * What it does (all bounded, all guarded):
 *   - worklist(): a bounded, paginated list of PUBLISHED image digital objects that
 *     are MISSING a real alt-text entry (in the working language), each with its
 *     parent record title + slug so a curator can find the image in context.
 *   - coverage(): the cheap aggregate figure - how many published image surrogates
 *     now carry a genuine human-authored alt-text row vs the total.
 *   - one(): load a single image surrogate's context + its current alt text, for the
 *     inline add/edit form.
 *   - save(): upsert one (digital_object_id, lang) alt-text row. The ONLY write path,
 *     confined entirely to the NEW image_alt_text table. No ALTER, no AI.
 *   - objectIdsWithAltText(): the set of digital_object ids that now carry real alt
 *     text, exposed so AccessibilityReportService can OR-in this store when it scores
 *     the image-alt-text area.
 *
 * Published = a `status` row with type_id 158 (publication status) and status_id 160
 * (published); the synthetic root description (id 1) is excluded - the same gate the
 * accessibility report uses. Image detection mirrors AccessibilityReportService
 * (mime_type LIKE 'image/%' OR a known image filename extension).
 *
 * International / jurisdiction-neutral: lang-aware throughout (Afrikaans is a
 * first-class working language, not a fallback). Every query is Schema::hasTable /
 * hasColumn-guarded and wrapped in try/catch so a missing table or column yields an
 * empty, honest result rather than a 500. No country-specific assumptions.
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

class AltTextService
{
    /** Publication-status taxonomy: status.type_id of a publication-status row. */
    private const STATUS_TYPE_PUBLICATION = 158;

    /** status.status_id meaning "published". */
    private const STATUS_PUBLISHED = 160;

    /** Synthetic root information object - never a real description. */
    private const ROOT_ID = 1;

    /** The dedicated alt-text store this service owns. */
    private const TABLE = 'image_alt_text';

    /** Default working language. International: a sane default, never a lock-in. */
    public const DEFAULT_LANG = 'en';

    /** Hard ceiling on a worklist page so the query is always bounded. */
    private const MAX_PER_PAGE = 100;

    /** Max stored alt-text length (matches the form validation rule). */
    public const MAX_ALT_LEN = 2000;

    /**
     * Filename extensions that mark an image digital object - a cheap fallback when
     * mime_type is absent. Mirrors AccessibilityReportService::EXT_IMAGE.
     *
     * @var array<int,string>
     */
    private const EXT_IMAGE = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'tif', 'tiff', 'jp2', 'bmp', 'heic'];

    /**
     * Is the curation feature usable on this install (the store table exists)?
     */
    public function isAvailable(): bool
    {
        try {
            return Schema::hasTable(self::TABLE)
                && Schema::hasTable('digital_object')
                && Schema::hasTable('status');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Normalise a language code to a safe, short, lower-cased token (e.g. "EN" ->
     * "en", "af-ZA" -> "af-za"). Falls back to the default for empty / junk input.
     */
    public function normalizeLang(?string $lang): string
    {
        $lang = strtolower(trim((string) $lang));
        // Keep it conservative: letters, digits, hyphen only; bounded length.
        $lang = preg_replace('/[^a-z0-9-]/', '', $lang) ?? '';
        if ($lang === '') {
            return self::DEFAULT_LANG;
        }

        return substr($lang, 0, 16);
    }

    /**
     * Coverage of human-authored alt text across PUBLISHED image surrogates, for the
     * given working language. Returns total published images, how many carry a real
     * alt-text row in that language, and the percentage. Cheap aggregate COUNTs only.
     *
     * @return array{total:int, with:int, pct:float, lang:string}
     */
    public function coverage(string $lang = self::DEFAULT_LANG): array
    {
        $lang = $this->normalizeLang($lang);
        $empty = ['total' => 0, 'with' => 0, 'pct' => 0.0, 'lang' => $lang];

        if (! $this->isAvailable()) {
            return $empty;
        }

        try {
            $total = (int) $this->publishedImageQuery()->distinct()->count('d.id');
            if ($total <= 0) {
                return $empty;
            }

            $with = (int) $this->publishedImageQuery()
                ->join(self::TABLE.' as a', 'a.digital_object_id', '=', 'd.id')
                ->where('a.lang', $lang)
                ->whereRaw("TRIM(COALESCE(a.alt_text,'')) <> ''")
                ->distinct()
                ->count('d.id');

            return [
                'total' => $total,
                'with'  => $with,
                'pct'   => $total > 0 ? round(($with / $total) * 100, 1) : 0.0,
                'lang'  => $lang,
            ];
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] alt-text coverage failed: '.$e->getMessage());

            return $empty;
        }
    }

    /**
     * A bounded, paginated worklist of PUBLISHED image surrogates that are MISSING a
     * real alt-text entry in the working language. Ordered by record id then object
     * id so the list is stable across pages. Each row carries enough context for a
     * curator to recognise the image: parent record title + slug + the surrogate
     * filename + (when present) the embedded caption to seed the alt text.
     *
     * @return array{
     *     rows: array<int, array{
     *         digital_object_id:int, object_id:int, name:string,
     *         title:string, slug:?string, caption:string
     *     }>,
     *     page:int, per_page:int, total_missing:int, last_page:int, lang:string
     * }
     */
    public function worklist(string $lang = self::DEFAULT_LANG, int $page = 1, int $perPage = 25): array
    {
        $lang = $this->normalizeLang($lang);
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPage));
        $page = max(1, $page);
        $empty = ['rows' => [], 'page' => $page, 'per_page' => $perPage, 'total_missing' => 0, 'last_page' => 1, 'lang' => $lang];

        if (! $this->isAvailable()) {
            return $empty;
        }

        try {
            // Base: published images with NO real alt-text row in this language.
            $missing = $this->publishedImageQuery()
                ->whereNotExists(function ($q) use ($lang) {
                    $q->select(DB::raw(1))
                        ->from(self::TABLE.' as a')
                        ->whereColumn('a.digital_object_id', 'd.id')
                        ->where('a.lang', $lang)
                        ->whereRaw("TRIM(COALESCE(a.alt_text,'')) <> ''");
                });

            $totalMissing = (int) (clone $missing)->distinct()->count('d.id');
            if ($totalMissing <= 0) {
                return ['rows' => [], 'page' => 1, 'per_page' => $perPage, 'total_missing' => 0, 'last_page' => 1, 'lang' => $lang];
            }

            $lastPage = (int) max(1, (int) ceil($totalMissing / $perPage));
            $page = min($page, $lastPage);
            $offset = ($page - 1) * $perPage;

            $hasMetadata = Schema::hasTable('digital_object_metadata')
                && Schema::hasColumn('digital_object_metadata', 'description');
            $hasI18n = Schema::hasTable('information_object') && Schema::hasTable('information_object_i18n');
            $hasSlug = Schema::hasTable('slug');

            $query = $missing
                ->select(['d.id as digital_object_id', 'd.object_id', 'd.name'])
                ->orderBy('d.object_id')
                ->orderBy('d.id')
                ->offset($offset)
                ->limit($perPage);

            if ($hasI18n) {
                $query->leftJoin('information_object as io', 'io.id', '=', 'd.object_id')
                    ->leftJoin('information_object_i18n as i18n', function ($j) {
                        $j->on('i18n.id', '=', 'io.id')
                            ->on('i18n.culture', '=', 'io.source_culture');
                    })
                    ->addSelect('i18n.title as io_title');
            }
            if ($hasSlug) {
                $query->leftJoin('slug as s', 's.object_id', '=', 'd.object_id')
                    ->addSelect('s.slug as io_slug');
            }
            if ($hasMetadata) {
                $query->leftJoin('digital_object_metadata as m', 'm.digital_object_id', '=', 'd.id')
                    ->addSelect('m.description as caption');
            }

            $rows = [];
            foreach ($query->get() as $r) {
                $rows[] = [
                    'digital_object_id' => (int) $r->digital_object_id,
                    'object_id'         => (int) $r->object_id,
                    'name'              => (string) ($r->name ?? ''),
                    'title'             => trim((string) ($r->io_title ?? '')),
                    'slug'              => isset($r->io_slug) && $r->io_slug !== '' ? (string) $r->io_slug : null,
                    'caption'           => trim((string) ($r->caption ?? '')),
                ];
            }

            return [
                'rows'          => $rows,
                'page'          => $page,
                'per_page'      => $perPage,
                'total_missing' => $totalMissing,
                'last_page'     => $lastPage,
                'lang'          => $lang,
            ];
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] alt-text worklist failed: '.$e->getMessage());

            return $empty;
        }
    }

    /**
     * Load a single image surrogate's context plus its current alt text (in the given
     * language), for the inline edit form. Returns null if the surrogate is not a
     * published image we can curate (so the controller can show a clean not-found
     * state rather than a 500). NO write happens here.
     *
     * @return array{
     *     digital_object_id:int, object_id:int, name:string, title:string,
     *     slug:?string, caption:string, alt_text:string, lang:string
     * }|null
     */
    public function one(int $digitalObjectId, string $lang = self::DEFAULT_LANG): ?array
    {
        $lang = $this->normalizeLang($lang);
        if ($digitalObjectId <= 0 || ! $this->isAvailable()) {
            return null;
        }

        try {
            $hasMetadata = Schema::hasTable('digital_object_metadata')
                && Schema::hasColumn('digital_object_metadata', 'description');
            $hasI18n = Schema::hasTable('information_object') && Schema::hasTable('information_object_i18n');
            $hasSlug = Schema::hasTable('slug');

            $query = $this->publishedImageQuery()
                ->where('d.id', $digitalObjectId)
                ->select(['d.id as digital_object_id', 'd.object_id', 'd.name']);

            if ($hasI18n) {
                $query->leftJoin('information_object as io', 'io.id', '=', 'd.object_id')
                    ->leftJoin('information_object_i18n as i18n', function ($j) {
                        $j->on('i18n.id', '=', 'io.id')
                            ->on('i18n.culture', '=', 'io.source_culture');
                    })
                    ->addSelect('i18n.title as io_title');
            }
            if ($hasSlug) {
                $query->leftJoin('slug as s', 's.object_id', '=', 'd.object_id')
                    ->addSelect('s.slug as io_slug');
            }
            if ($hasMetadata) {
                $query->leftJoin('digital_object_metadata as m', 'm.digital_object_id', '=', 'd.id')
                    ->addSelect('m.description as caption');
            }

            $r = $query->first();
            if ($r === null) {
                return null;
            }

            $current = DB::table(self::TABLE)
                ->where('digital_object_id', $digitalObjectId)
                ->where('lang', $lang)
                ->value('alt_text');

            return [
                'digital_object_id' => (int) $r->digital_object_id,
                'object_id'         => (int) $r->object_id,
                'name'              => (string) ($r->name ?? ''),
                'title'             => trim((string) ($r->io_title ?? '')),
                'slug'              => isset($r->io_slug) && $r->io_slug !== '' ? (string) $r->io_slug : null,
                'caption'           => trim((string) ($r->caption ?? '')),
                'alt_text'          => trim((string) ($current ?? '')),
                'lang'              => $lang,
            ];
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] alt-text one() failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Upsert the human-authored alt text for one (digital_object_id, lang). This is
     * the ONLY write path in the service, and it writes ONLY to the image_alt_text
     * side table - never to digital_object, never an ALTER, never an AI call.
     *
     * Returns true on a successful write. A blank alt_text removes the row (clearing
     * an entry is a legitimate curation action and keeps coverage honest).
     */
    public function save(int $digitalObjectId, string $altText, string $lang, ?int $userId): bool
    {
        $lang = $this->normalizeLang($lang);
        $altText = trim($altText);
        if (mb_strlen($altText) > self::MAX_ALT_LEN) {
            $altText = mb_substr($altText, 0, self::MAX_ALT_LEN);
        }

        if ($digitalObjectId <= 0 || ! $this->isAvailable()) {
            return false;
        }

        // Only ever curate a real, published image surrogate.
        if (! $this->isPublishedImage($digitalObjectId)) {
            return false;
        }

        try {
            $now = now();

            // Blank => clear the entry rather than store an empty string.
            if ($altText === '') {
                DB::table(self::TABLE)
                    ->where('digital_object_id', $digitalObjectId)
                    ->where('lang', $lang)
                    ->delete();

                return true;
            }

            $existing = DB::table(self::TABLE)
                ->where('digital_object_id', $digitalObjectId)
                ->where('lang', $lang)
                ->first();

            if ($existing !== null) {
                DB::table(self::TABLE)
                    ->where('id', $existing->id)
                    ->update([
                        'alt_text'   => $altText,
                        'updated_by' => $userId,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table(self::TABLE)->insert([
                    'digital_object_id' => $digitalObjectId,
                    'lang'              => $lang,
                    'alt_text'          => $altText,
                    'contributed_by'    => $userId,
                    'updated_by'        => $userId,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
            }

            return true;
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] alt-text save failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * The set of digital_object ids that now carry a real, non-empty alt-text row in
     * ANY language. Exposed so AccessibilityReportService can OR-in this store when
     * it scores the image-alt-text area. Bounded by the side table's size; returns an
     * empty array (never throws) when the table is absent.
     *
     * @return array<int, true> keyed by digital_object_id for O(1) membership
     */
    public function objectIdsWithAltText(): array
    {
        try {
            if (! Schema::hasTable(self::TABLE)) {
                return [];
            }

            $ids = DB::table(self::TABLE)
                ->whereRaw("TRIM(COALESCE(alt_text,'')) <> ''")
                ->distinct()
                ->pluck('digital_object_id');

            $set = [];
            foreach ($ids as $id) {
                $set[(int) $id] = true;
            }

            return $set;
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] alt-text objectIdsWithAltText failed: '.$e->getMessage());

            return [];
        }
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    /** Reusable subquery: object_ids of every published, non-root record. */
    private function publishedIdSub()
    {
        return DB::table('status')
            ->select('object_id')
            ->where('type_id', self::STATUS_TYPE_PUBLICATION)
            ->where('status_id', self::STATUS_PUBLISHED)
            ->where('object_id', '>', self::ROOT_ID);
    }

    /**
     * Base builder over PUBLISHED image digital objects (aliased d). Callers add
     * their own select / count / join. Mirrors the accessibility report's image
     * detection so the two surfaces agree on what "an image" is.
     */
    private function publishedImageQuery()
    {
        return DB::table('digital_object as d')
            ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'd.object_id')
            ->where($this->imageWhere());
    }

    /** Is this digital_object a published image surrogate we are allowed to curate? */
    private function isPublishedImage(int $digitalObjectId): bool
    {
        try {
            return $this->publishedImageQuery()
                ->where('d.id', $digitalObjectId)
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * WHERE closure selecting IMAGE digital objects: mime_type LIKE 'image/%' OR a
     * known image filename extension. Kept as a single bounded OR.
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
}
