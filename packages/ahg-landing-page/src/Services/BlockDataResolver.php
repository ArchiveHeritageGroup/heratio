<?php

/**
 * BlockDataResolver - Service for Heratio
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

namespace AhgLandingPage\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The per-block-type data resolver - the last of #1478.
 *
 * Every landing-page block partial reads `$data`, which the page templates take
 * from `$block->computed_data`. Nothing ever computed it, so `$data` was null on
 * every block on every page: statistics rendered no figures, recent items no
 * items, the map no locations, and browse panels no counts. Six of the eight
 * findings left on #1478 were this one absence seen from six views.
 *
 * A block's `config` declares WHAT to show - which entity, how many, which
 * repository. This turns that declaration into rows. Config-only blocks (hero
 * banner, text, spacer, search box) resolve to null and are untouched.
 *
 * TWO RULES THIS FILE EXISTS TO KEEP:
 *
 * 1. The landing page is PUBLIC. Every query here carries the same guest gate
 *    the public browse uses - published status only for anonymous visitors,
 *    plus the #1388 community-protocol exclusion unconditionally. A count is a
 *    disclosure: "1,432 records" tells a visitor how much exists whether or not
 *    they may see any of it, so the gate belongs on the COUNT as much as on the
 *    list.
 *
 * 2. It never throws. A landing page that 500s because a statistics query hit a
 *    missing table is worse than one showing no statistics, so every resolver
 *    returns an empty result on error and the block renders its own empty state.
 */
class BlockDataResolver
{
    /** Landing pages are the most-requested page on any instance; counts are stable enough to cache briefly. */
    private const CACHE_TTL = 300;

    /** status.type_id 158 = publication status, status_id 160 = published. */
    private const STATUS_TYPE_PUBLICATION = 158;
    private const STATUS_PUBLISHED = 160;

    /** digital_object.usage_id 142 = thumbnail derivative. */
    private const USAGE_THUMBNAIL = 142;

    public function resolve(?string $machineName, array $config): mixed
    {
        try {
            return match ($machineName) {
                'statistics'       => $this->statistics($config),
                'browse_panels'    => $this->browsePanels($config),
                'recent_items'     => $this->recentItems($config),
                'featured_items'   => $this->recentItems($config),
                'holdings_list'    => $this->holdingsList($config),
                'image_carousel'   => $this->imageCarousel($config),
                'map_block'        => $this->mapLocations($config),
                'map'              => $this->mapLocations($config),
                default            => null,
            };
        } catch (\Throwable $e) {
            // Rule 2. The block renders its own "nothing here" state instead.
            return null;
        }
    }

    /**
     * config: stats[] of {icon,label,entity} -> the same rows with `count` filled.
     */
    private function statistics(array $config): array
    {
        $out = [];

        foreach (($config['stats'] ?? []) as $stat) {
            $stat['count'] = $this->countEntity($stat['entity'] ?? null);
            $out[] = $stat;
        }

        return $out;
    }

    /**
     * config: panels[] of {url,icon,title,count_entity} -> the same rows with `count`.
     * The view falls back to config['panels'] when this is empty, so a panel set
     * with no countable entity still renders - it just shows no figure.
     */
    private function browsePanels(array $config): array
    {
        $out = [];

        foreach (($config['panels'] ?? []) as $panel) {
            if (! empty($panel['count_entity'])) {
                $panel['count'] = $this->countEntity($panel['count_entity']);
            }
            $out[] = $panel;
        }

        return $out;
    }

    /**
     * Published count for one entity name, cached briefly.
     *
     * Actors go through AclService::addActorVisibilityCriteria so the draft and
     * embargo rules from the authority-record work are honoured here too - an
     * embargoed authority record must not be counted on a public page.
     */
    private function countEntity(?string $entity): int
    {
        if (! $entity) {
            return 0;
        }

        return (int) Cache::remember(
            'ahg_landing_count_'.$entity.'_'.(auth()->check() ? 'auth' : 'guest'),
            self::CACHE_TTL,
            function () use ($entity) {
                switch ($entity) {
                    case 'informationobject':
                    case 'information_object':
                        return $this->publishedIoQuery()->count();

                    case 'actor':
                        if (! Schema::hasTable('actor')) {
                            return 0;
                        }
                        $q = DB::table('actor')
                            ->join('object as o', 'o.id', '=', 'actor.id')
                            ->where('o.class_name', 'QubitActor');
                        \AhgCore\Services\AclService::addActorVisibilityCriteria($q, 'actor.id');

                        return $q->count();

                    case 'repository':
                        return Schema::hasTable('repository')
                            ? DB::table('repository')->count()
                            : 0;

                    case 'term':
                        return Schema::hasTable('term')
                            ? DB::table('term')->count()
                            : 0;

                    case 'digitalobject':
                    case 'digital_object':
                        return Schema::hasTable('digital_object')
                            ? DB::table('digital_object')->whereNull('parent_id')->count()
                            : 0;

                    default:
                        return 0;
                }
            }
        );
    }

    /**
     * The public information-object query, gated exactly as the public browse
     * gates it: drafts hidden from guests, community protocols excluded always.
     * `io.id != 1` drops the tree root, which is not a record.
     */
    private function publishedIoQuery()
    {
        $q = DB::table('information_object as io')->where('io.id', '!=', 1);

        if (! auth()->check()) {
            $q->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('status as pub_st')
                    ->whereRaw('pub_st.object_id = io.id')
                    ->where('pub_st.type_id', '=', self::STATUS_TYPE_PUBLICATION)
                    ->where('pub_st.status_id', '=', self::STATUS_PUBLISHED);
            });
        }

        \AhgCore\Services\TermProtocolGate::excludeRestrictedRecords($q, 'io.id');

        return $q;
    }

    /**
     * config: {limit, entity_type} -> rows of {title, slug, created_at, thumbnail_url}.
     */
    private function recentItems(array $config): array
    {
        $limit = max(1, min((int) ($config['limit'] ?? 6), 50));
        $culture = app()->getLocale();

        $rows = $this->publishedIoQuery()
            ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->leftJoin('object as o', 'o.id', '=', 'io.id')
            ->orderByDesc('o.created_at')
            ->limit($limit)
            ->select('io.id', 'i18n.title', 's.slug', 'o.created_at')
            ->get();

        return $this->withThumbnails($rows);
    }

    /**
     * config: {limit, sort, repository_id} -> objects of {title, slug, hits}.
     * `hits` is what the view shows as a popularity figure; where no hit counter
     * exists it stays 0 rather than being faked with a random-looking number.
     */
    private function holdingsList(array $config): array
    {
        $limit = max(1, min((int) ($config['limit'] ?? 10), 50));
        $culture = app()->getLocale();

        $q = $this->publishedIoQuery()
            ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id');

        if (! empty($config['repository_id'])) {
            $q->where('io.repository_id', (int) $config['repository_id']);
        }

        if (($config['sort'] ?? 'title') === 'title') {
            $q->orderBy('i18n.title');
        } else {
            $q->orderByDesc('io.id');
        }

        return $q->limit($limit)
            ->select('io.id', 'i18n.title', 's.slug', DB::raw('0 as hits'))
            ->get()
            ->all();
    }

    /**
     * config: {limit, collection_id} -> [{image_url, title}] for descendants of a
     * collection that actually have a thumbnail. collection_id is a SLUG in the
     * seeded config, so it is resolved through the slug table rather than cast
     * to an integer - casting "mobrey-family-archive-3" to int gives 0 and
     * silently selects nothing.
     */
    private function imageCarousel(array $config): array
    {
        $limit = max(1, min((int) ($config['limit'] ?? 12), 50));
        $culture = app()->getLocale();

        $q = $this->publishedIoQuery()
            ->join('digital_object as master', function ($j) {
                $j->on('master.object_id', '=', 'io.id')->whereNull('master.parent_id');
            })
            ->join('digital_object as thumb', function ($j) {
                $j->on('thumb.parent_id', '=', 'master.id')
                    ->where('thumb.usage_id', '=', self::USAGE_THUMBNAIL);
            })
            ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            });

        $collection = $config['collection_id'] ?? null;
        if (! empty($collection)) {
            $parentId = is_numeric($collection)
                ? (int) $collection
                : (int) DB::table('slug')->where('slug', $collection)->value('object_id');

            if ($parentId) {
                $q->where(function ($w) use ($parentId) {
                    $w->where('io.parent_id', $parentId)->orWhere('io.id', $parentId);
                });
            }
        }

        return $q->limit($limit)
            ->select('i18n.title', 'thumb.path', 'thumb.name')
            ->get()
            ->map(fn ($r) => [
                'title' => $r->title ?? '',
                'image_url' => $this->assetUrl($r->path, $r->name),
            ])
            ->filter(fn ($r) => $r['image_url'] !== null)
            ->values()
            ->all();
    }

    /**
     * config: {show_all_repositories, repository_ids} -> repositories that have
     * coordinates. A repository without a latitude is not a location, so it is
     * omitted rather than plotted at (0,0) in the Gulf of Guinea.
     */
    private function mapLocations(array $config): array
    {
        if (! Schema::hasTable('repository') || ! Schema::hasTable('contact_information')) {
            return [];
        }

        $culture = app()->getLocale();

        $q = DB::table('repository as r')
            ->join('contact_information as ci', 'ci.actor_id', '=', 'r.id')
            ->leftJoin('actor_i18n as ai', function ($j) use ($culture) {
                $j->on('ai.id', '=', 'r.id')->where('ai.culture', '=', $culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'r.id')
            // city lives in contact_information_i18n, not contact_information -
            // the base table has street_address and postal_code but no city.
            ->leftJoin('contact_information_i18n as cii', function ($j) use ($culture) {
                $j->on('cii.id', '=', 'ci.id')->where('cii.culture', '=', $culture);
            })
            ->whereNotNull('ci.latitude')
            ->whereNotNull('ci.longitude');

        $ids = $config['repository_ids'] ?? [];
        if (empty($config['show_all_repositories']) && ! empty($ids)) {
            $q->whereIn('r.id', array_map('intval', (array) $ids));
        }

        return $q->select(
            'r.id',
            'ai.authorized_form_of_name as name',
            's.slug',
            'ci.latitude',
            'ci.longitude',
            'cii.city',
            'ci.street_address'
        )->get()->all();
    }

    /**
     * Attach thumbnail_url to a set of information-object rows in ONE query
     * rather than one per row - a landing page showing 12 recent items would
     * otherwise issue 12 extra queries on the most-requested page of the site.
     */
    private function withThumbnails($rows): array
    {
        $rows = $rows->all();

        if ($rows === [] || ! Schema::hasTable('digital_object')) {
            return $rows;
        }

        $ids = array_column($rows, 'id');

        $thumbs = DB::table('digital_object as master')
            ->join('digital_object as thumb', function ($j) {
                $j->on('thumb.parent_id', '=', 'master.id')
                    ->where('thumb.usage_id', '=', self::USAGE_THUMBNAIL);
            })
            ->whereIn('master.object_id', $ids)
            ->whereNull('master.parent_id')
            ->select('master.object_id', 'thumb.path', 'thumb.name')
            ->get()
            ->keyBy('object_id');

        foreach ($rows as $row) {
            $t = $thumbs->get($row->id);
            $row->thumbnail_url = $t ? $this->assetUrl($t->path, $t->name) : null;
        }

        return $rows;
    }

    private function assetUrl(?string $path, ?string $name): ?string
    {
        if (! $path || ! $name) {
            return null;
        }

        return rtrim($path, '/').'/'.$name;
    }
}
