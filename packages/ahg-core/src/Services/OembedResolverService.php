<?php

/**
 * OembedResolverService - Heratio ahg-core
 *
 * Read-only resolver that turns a public record URL into a PUBLISHED archival
 * description ready to be packaged as an oEmbed response. It is the single source
 * of the "URL -> record" mapping for OembedController.
 *
 * URL -> slug -> record (the load-bearing fact)
 * ---------------------------------------------
 * A Heratio archival-record public URL is a SINGLE-segment slug path served by
 * the /{slug} catch-all in ahg-information-object-manage, e.g.
 * https://host/title-of-object. So the resolver:
 *
 *   1. Parses the consumer-supplied `url` (must be a same-host http/https URL).
 *   2. Takes the FIRST path segment as the candidate slug (everything after the
 *      host, before the next "/", "?", "#"), lower-cased and trimmed. A leading
 *      "index.php/" (legacy) is stripped first. Known non-record prefixes
 *      (glam, admin, api, ...) are rejected up front so /admin/... never resolves.
 *   3. Looks the slug up in the `slug` table (slug.object_id = the record id) and
 *      confirms the row is a PUBLISHED, non-root information object.
 *
 * Published gate (confirmed, mirrors RecentlyAddedService / OpenSearchController)
 * -----------------------------------------------------------------------------
 * Published = a `status` row with type_id 158 (publication status) AND status_id
 * 160 (published). The synthetic root description (id 1) is excluded. A draft /
 * unknown slug resolves to null so the caller emits a clean oEmbed 404, never a
 * record the public may not see.
 *
 * Title / creator / thumbnail
 * ---------------------------
 * - Title comes from information_object_i18n joined on the record's OWN
 *   source_culture (information_object.source_culture) - the authoritative title,
 *   not a stray translation. Same canonical-culture join RecentlyAddedService uses.
 * - Creator (optional, cheap) comes from the creation `event` (event.object_id =
 *   record id, type_id 111) -> actor_i18n.authorized_form_of_name. Absence is fine.
 * - Thumbnail (optional, cheap) is the usage_id=141 THUMBNAIL child of the record's
 *   master digital object (digital_object.object_id = record id). The web path is
 *   path + name resolved through url(); no file IO, no derivative generation.
 *   digital_object carries no width/height columns on this schema, so dimensions
 *   are simply omitted (oEmbed permits a thumbnail_url without dimensions).
 *
 * Read-only and defensive: every lookup is Schema-guarded and try/caught; ANY
 * failure yields null (or a null sub-field) so the controller degrades to a clean
 * 404 / a smaller-but-valid oEmbed response, never a 500. No writes, no ALTER, no
 * new table. International: no country-specific assumptions; culture-neutral.
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
use Throwable;

class OembedResolverService
{
    /** Publication-status taxonomy: status.type_id of a publication-status row. */
    private const STATUS_TYPE_PUBLICATION = 158;

    /** status.status_id meaning "published". */
    private const STATUS_PUBLISHED = 160;

    /** Synthetic root information object - never a real description. */
    private const ROOT_ID = 1;

    /** event.type_id for a creation event (the record's creator). */
    private const EVENT_TYPE_CREATION = 111;

    /** digital_object.usage_id for a generated THUMBNAIL surrogate. */
    private const USAGE_THUMBNAIL = 141;

    /**
     * Path prefixes that are NOT archival-record slugs. The /{slug} catch-all in
     * ahg-information-object-manage excludes these; we reject them here too so a
     * consumer can never coax an /admin or /api URL into an oEmbed lookup. Kept
     * deliberately broad and lower-case.
     */
    private const NON_RECORD_PREFIXES = [
        'admin', 'api', 'glam', 'informationobject', 'actor', 'repository',
        'research', 'ingest', 'export', 'import', 'reports', 'settings',
        'clipboard', 'cart', 'favorites', 'feedback', 'loan', 'term',
        'oembed', 'opensearch', 'recent', 'explore', 'open-data',
        'collection-overview', 'accessibility-statement', 'race-against-loss',
        'ask-the-collection', 'language-coverage', 'read', 'record',
        'reading-language', 'stories', 'pointcloud', 'splat', 'object', 'tts',
        'verify', 'reconstructions', 'login', 'logout', 'register', 'password',
        'sru', 'z3950', 'oai',
    ];

    /**
     * Resolve a consumer-supplied record URL to a PUBLISHED record record-set,
     * or null when it does not map to a viewable record.
     *
     * @return array{
     *     id:int, slug:string, title:string, url:string,
     *     author_name:?string, thumbnail_url:?string
     * }|null
     */
    public function resolve(string $url): ?array
    {
        try {
            $slug = $this->slugFromUrl($url);
            if ($slug === null) {
                return null;
            }

            $record = $this->publishedRecordForSlug($slug);
            if ($record === null) {
                return null;
            }

            $id = (int) $record->id;

            return [
                'id'            => $id,
                'slug'          => $slug,
                'title'         => $this->titleOrFallback($record->title ?? null),
                'url'           => url('/'.ltrim($slug, '/')),
                'author_name'   => $this->creatorName($id),
                'thumbnail_url' => $this->thumbnailUrl($id),
            ];
        } catch (Throwable $e) {
            \Log::warning('[ahg-core] oembed resolve failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Extract the candidate record slug from a consumer URL.
     *
     * The URL must be a parseable http/https URL whose host matches this site's
     * host (an off-site URL is not ours to embed). The slug is the first path
     * segment, lower-cased; a leading "index.php/" is stripped first; a known
     * non-record prefix, an empty path, or a multi-dot/illegal slug returns null.
     */
    private function slugFromUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['path'])) {
            return null;
        }

        // Reject schemes other than http/https when a scheme is present.
        if (isset($parts['scheme']) && ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        // When a host is present it must be this site's host (case-insensitive).
        if (isset($parts['host']) && $parts['host'] !== '') {
            $ours = (string) parse_url((string) url('/'), PHP_URL_HOST);
            if ($ours !== '' && strcasecmp($parts['host'], $ours) !== 0) {
                return null;
            }
        }

        $path = ltrim((string) $parts['path'], '/');

        // Strip a single legacy "index.php/" front matter.
        if (stripos($path, 'index.php/') === 0) {
            $path = substr($path, strlen('index.php/'));
        }

        if ($path === '') {
            return null;
        }

        // First path segment only.
        $segment = explode('/', $path, 2)[0];
        $segment = strtolower(rawurldecode($segment));
        $segment = trim($segment);

        if ($segment === '') {
            return null;
        }

        // A record slug is [a-z0-9] then [a-z0-9-]* (the same shape the catch-all
        // route constrains). Anything with a dot, slash, or other char is not a slug.
        if (! preg_match('/^[a-z0-9][a-z0-9-]*$/', $segment)) {
            return null;
        }

        if (in_array($segment, self::NON_RECORD_PREFIXES, true)) {
            return null;
        }

        return $segment;
    }

    /**
     * Look up a slug and return its PUBLISHED, non-root information-object row
     * (id + own-culture title), or null. Mirrors the published gate used across
     * the public surfaces.
     *
     * SQL (essence):
     *   SELECT io.id, i.title
     *     FROM slug s
     *     JOIN information_object io ON io.id = s.object_id
     *     JOIN status st ON st.object_id = io.id
     *                   AND st.type_id = 158 AND st.status_id = 160
     *     LEFT JOIN information_object_i18n i
     *            ON i.id = io.id AND i.culture = io.source_culture
     *    WHERE s.slug = ? AND io.id > 1
     *    LIMIT 1
     */
    private function publishedRecordForSlug(string $slug)
    {
        if (! Schema::hasTable('slug')
            || ! Schema::hasTable('information_object')
            || ! Schema::hasTable('status')) {
            return null;
        }

        $hasI18n   = Schema::hasTable('information_object_i18n');
        $hasSource = Schema::hasColumn('information_object', 'source_culture');

        $q = DB::table('slug as s')
            ->join('information_object as io', 'io.id', '=', 's.object_id')
            ->join('status as st', function ($j) {
                $j->on('st.object_id', '=', 'io.id')
                    ->where('st.type_id', self::STATUS_TYPE_PUBLICATION)
                    ->where('st.status_id', self::STATUS_PUBLISHED);
            });

        if ($hasI18n) {
            $q->leftJoin('information_object_i18n as i', function ($j) use ($hasSource) {
                $j->on('i.id', '=', 'io.id');
                if ($hasSource) {
                    $j->on('i.culture', '=', 'io.source_culture');
                }
            });
        }

        $q->where('s.slug', $slug)
            ->where('io.id', '>', self::ROOT_ID);

        $select = ['io.id'];
        $select[] = $hasI18n ? 'i.title' : DB::raw('NULL as title');

        return $q->select($select)->first();
    }

    /** The record's creator name (creation event -> actor), or null. Cheap + guarded. */
    private function creatorName(int $id): ?string
    {
        if (! Schema::hasTable('event') || ! Schema::hasTable('actor_i18n')) {
            return null;
        }

        try {
            $hasSource = Schema::hasColumn('information_object', 'source_culture');

            $q = DB::table('event as e')
                ->join('actor_i18n as ai', 'ai.id', '=', 'e.actor_id')
                ->where('e.object_id', $id)
                ->where('e.type_id', self::EVENT_TYPE_CREATION)
                ->whereNotNull('e.actor_id')
                ->whereNotNull('ai.authorized_form_of_name')
                ->where('ai.authorized_form_of_name', '!=', '');

            // Prefer the record's own source culture if we can, else any culture.
            if ($hasSource) {
                $q->leftJoin('information_object as io2', 'io2.id', '=', 'e.object_id')
                    ->orderByRaw('ai.culture = io2.source_culture DESC');
            }

            $row = $q->orderBy('e.id')
                ->select('ai.authorized_form_of_name')
                ->first();

            $name = trim((string) ($row->authorized_form_of_name ?? ''));

            return $name !== '' ? $name : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Absolute thumbnail URL for the record, or null. The usage_id=141 THUMBNAIL
     * child of the record's master digital object; path + name resolved through
     * url(). No file IO.
     */
    private function thumbnailUrl(int $id): ?string
    {
        if (! Schema::hasTable('digital_object')) {
            return null;
        }

        try {
            $row = DB::table('digital_object as m')
                ->join('digital_object as t', function ($j) {
                    $j->on('t.parent_id', '=', 'm.id')
                        ->where('t.usage_id', '=', self::USAGE_THUMBNAIL);
                })
                ->where('m.object_id', $id)
                ->whereNotNull('t.name')
                ->where('t.name', '!=', '')
                ->select(['t.path', 't.name'])
                ->first();

            if ($row === null) {
                return null;
            }

            $name = trim((string) ($row->name ?? ''));
            if ($name === '') {
                return null;
            }

            $rel = rtrim((string) ($row->path ?? ''), '/').'/'.ltrim($name, '/');
            $rel = '/'.ltrim($rel, '/');

            return url($rel);
        } catch (Throwable $e) {
            return null;
        }
    }

    /** The record title, or a neutral fallback when the i18n title is empty. */
    private function titleOrFallback($raw): string
    {
        $title = trim((string) ($raw ?? ''));

        return $title !== '' ? $title : __('Untitled record');
    }
}
