<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    /**
     * Hosts that render the marketing landing page rather than the operational
     * one. Keep in step with the identical list in header.blade.php and
     * ahg-admin-menu.blade.php - when these disagree, the navbar and the page
     * body render for different modes.
     */
    private const MARKETING_HOSTS = [
        'heratio.org',
        'www.heratio.org',
        'heratio.theahg.co.za',
    ];

    /**
     * Homepage — migrated from ahgThemeB5Plugin homeSuccess.php.
     * 2-column layout: sidebar (static pages menu, browse-by, popular this week)
     * + main content (featured collection carousel, static page HTML content).
     */
    public function index()
    {
        // Resolve culture from the active locale, with fallback (matches the
        // service-layer pattern from WithCultureFallback). See review item #6.
        $culture = (string) app()->getLocale();
        $fallback = (string) config('app.fallback_locale', 'en');

        // Get homepage static page content (current culture, then fallback)
        $page = DB::table('static_page')
            ->join('static_page_i18n', 'static_page.id', '=', 'static_page_i18n.id')
            ->join('slug', 'static_page.id', '=', 'slug.object_id')
            ->where('slug.slug', 'home')
            ->whereIn('static_page_i18n.culture', array_unique([$culture, $fallback]))
            ->orderByRaw('FIELD(static_page_i18n.culture, ?, ?)', [$culture, $fallback])
            ->select('static_page.id', 'static_page_i18n.title', 'static_page_i18n.content')
            ->first();

        // Marketing-mode short-circuit. Used on the vendor's own sites to render
        // the product pitch - vision, pillars, sectors, standards, CTAs - and the
        // two 3D digital-twin walkthrough links, without the body-level
        // operational chrome (sidebar Browse-by, sidebar Popular, marketplace
        // hero block, featured carousel, the big feature wall). The navbar login
        // dropdown is kept because these sites are themselves the demo - seeded
        // with real data, and the demo account works here as everywhere else, so
        // prospects can log in and explore.
        //
        // The host list must match header.blade.php and ahg-admin-menu.blade.php,
        // which v1.154.382 updated for the move to heratio.org but which left
        // this check behind. The split meant heratio.org rendered the
        // institutional homepage while its navbar behaved as marketing, so the
        // digital-twin walkthrough links disappeared from the landing page.
        //
        // Default is institutional mode (the existing behaviour), so any
        // institution that installs Heratio gets a working operational homepage
        // out of the box.
        $mode = config('heratio.homepage_mode')
            ?? (in_array(request()->getHost(), self::MARKETING_HOSTS, true) ? 'marketing' : 'institutional');
        if ($mode === 'marketing') {
            // Latest published demo-site articles for the landing Articles strip.
            $latestArticles = [];
            try {
                $latestArticles = (new \AhgArticles\Services\BlogService())->listPublished(3);
            } catch (\Throwable $e) {
                // blog_post may not exist on a non-demo install; degrade silently.
            }

            return view('home-marketing', compact('page', 'latestArticles'));
        }

        // Browse menu items (children of the Browse menu)
        $browseMenuId = DB::table('menu')->where('name', 'browse')->value('id');
        $browseItems = collect();
        if ($browseMenuId) {
            $bq = DB::table('menu')
                ->leftJoin('menu_i18n as mi_cur', function ($j) use ($culture) {
                    $j->on('menu.id', '=', 'mi_cur.id')->where('mi_cur.culture', '=', $culture);
                })
                ->leftJoin('menu_i18n as mi_fb', function ($j) use ($fallback) {
                    $j->on('menu.id', '=', 'mi_fb.id')->where('mi_fb.culture', '=', $fallback);
                })
                ->where('menu.parent_id', $browseMenuId)
                ->select('menu.path', DB::raw('COALESCE(mi_cur.label, mi_fb.label) AS label'));
            // #1333 read-swap: order by the closure sibling-order (COALESCE falls back to lft).
            $browseItems = app(\AhgCore\Services\HierarchyQueryService::class)
                ->applySiblingOrder($bq, 'menu', 'menu.id', 'menu.lft')->get();
        }

        // Static pages menu — children of the "staticPagesMenu" menu item.
        // Renders Favorites / Feedback / Cart links (not actual static-page content).
        $staticPagesMenuId = DB::table('menu')->where('name', 'staticPagesMenu')->value('id');
        $staticPages = collect();
        if ($staticPagesMenuId) {
            $sq = DB::table('menu')
                ->leftJoin('menu_i18n as mi_cur', function ($j) use ($culture) {
                    $j->on('menu.id', '=', 'mi_cur.id')->where('mi_cur.culture', '=', $culture);
                })
                ->leftJoin('menu_i18n as mi_fb', function ($j) use ($fallback) {
                    $j->on('menu.id', '=', 'mi_fb.id')->where('mi_fb.culture', '=', $fallback);
                })
                ->where('menu.parent_id', $staticPagesMenuId)
                ->select(DB::raw('COALESCE(mi_cur.label, mi_fb.label) AS title'), 'menu.path as slug');
            // #1333 read-swap: order by the closure sibling-order (COALESCE falls back to lft).
            $staticPages = app(\AhgCore\Services\HierarchyQueryService::class)
                ->applySiblingOrder($sq, 'menu', 'menu.id', 'menu.lft')->get();
        }

        // Popular this week (from access_log)
        $popularThisWeek = collect();
        try {
            $popularThisWeek = DB::table('access_log')
                ->select('object_id', DB::raw('COUNT(*) as visits'))
                ->where('access_date', '>=', now()->subDays(7))
                ->groupBy('object_id')
                ->orderByDesc('visits')
                ->limit(10)
                ->get()
                ->map(function ($row) use ($culture, $fallback) {
                    $obj = DB::table('object')->where('id', $row->object_id)->first();
                    if (! $obj) {
                        return null;
                    }

                    $slug = DB::table('slug')->where('object_id', $row->object_id)->value('slug');
                    $title = null;
                    $module = null;

                    // Resolve a localised title with culture fallback, picking the
                    // i18n table off class_name. Mirrors the entity-service pattern.
                    $i18nTable = match ($obj->class_name) {
                        'QubitInformationObject' => 'information_object_i18n',
                        'QubitRepository' => 'repository_i18n',
                        'QubitActor' => 'actor_i18n',
                        default => null,
                    };
                    $titleColumn = $obj->class_name === 'QubitInformationObject' ? 'title' : 'authorized_form_of_name';
                    $module = match ($obj->class_name) {
                        'QubitInformationObject' => 'informationobject',
                        'QubitRepository' => 'repository',
                        'QubitActor' => 'actor',
                        default => null,
                    };
                    if ($i18nTable) {
                        $title = DB::table($i18nTable)
                            ->where('id', $row->object_id)
                            ->whereIn('culture', array_unique([$culture, $fallback]))
                            ->orderByRaw('FIELD(culture, ?, ?)', [$culture, $fallback])
                            ->value($titleColumn);
                    }

                    if (! $title || ! $slug) {
                        return null;
                    }

                    return (object) [
                        'title' => $title,
                        'slug' => $slug,
                        'visits' => $row->visits,
                        'module' => $module,
                    ];
                })
                ->filter();
        } catch (\Exception $e) {
            // access_log may not have data
        }

        // Featured collection carousel
        $carousel = $this->getFeaturedCarousel();

        return view('home', compact(
            'page',
            'browseItems',
            'staticPages',
            'popularThisWeek',
            'carousel'
        ));
    }

    /**
     * Build featured collection carousel data.
     * Migrated from ahgIiifPlugin _featuredCollection.php
     */
    protected function getFeaturedCarousel(): array
    {
        try {
            $culture = (string) app()->getLocale();
            $fallback = (string) config('app.fallback_locale', 'en');

            // Load settings
            $settings = DB::table('iiif_viewer_settings')
                ->whereIn('setting_key', [
                    'homepage_collection_id',
                    'homepage_collection_enabled',
                    'homepage_carousel_height',
                    'homepage_carousel_autoplay',
                    'homepage_carousel_interval',
                    'homepage_show_captions',
                    'homepage_max_items',
                ])
                ->pluck('setting_value', 'setting_key')
                ->all();

            if (($settings['homepage_collection_enabled'] ?? '1') !== '1') {
                return [];
            }

            $collectionId = $settings['homepage_collection_id'] ?? null;
            if (! $collectionId) {
                return [];
            }

            $collection = DB::table('iiif_collection')->where('id', $collectionId)->first();
            if (! $collection) {
                return [];
            }

            $maxItems = (int) ($settings['homepage_max_items'] ?? 12);
            $height = $settings['homepage_carousel_height'] ?? '450px';
            $autoplay = ($settings['homepage_carousel_autoplay'] ?? '1') === '1';
            $interval = (int) ($settings['homepage_carousel_interval'] ?? 5000);
            $showCaptions = ($settings['homepage_show_captions'] ?? '1') === '1';

            $USAGE_REFERENCE = 141;
            $USAGE_THUMBNAIL = 142;
            $MEDIA_IMAGE = 136;
            $MEDIA_AUDIO = 135;
            $MEDIA_VIDEO = 138;
            $imageMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/tiff', 'image/jp2', 'image/webp', 'image/bmp'];

            // Get collection items. Title resolves via current culture with
            // fallback (COALESCE) so non-en locales get a localised label.
            $items = DB::table('iiif_collection_item as ci')
                ->leftJoin('information_object as io', 'ci.object_id', '=', 'io.id')
                ->leftJoin('information_object_i18n as i18n_cur', function ($join) use ($culture) {
                    $join->on('io.id', '=', 'i18n_cur.id')->where('i18n_cur.culture', '=', $culture);
                })
                ->leftJoin('information_object_i18n as i18n_fb', function ($join) use ($fallback) {
                    $join->on('io.id', '=', 'i18n_fb.id')->where('i18n_fb.culture', '=', $fallback);
                })
                ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
                ->leftJoin('digital_object as do', function ($join) {
                    $join->on('io.id', '=', 'do.object_id')->whereNull('do.parent_id');
                })
                ->where('ci.collection_id', $collection->id)
                ->whereNotNull('do.id')
                ->select(
                    'ci.id as item_id',
                    'ci.label as custom_label',
                    'io.id as object_id',
                    'io.identifier',
                    DB::raw('COALESCE(i18n_cur.title, i18n_fb.title) AS title'),
                    'slug.slug',
                    'do.id as digital_object_id',
                    'do.name as filename',
                    'do.path as filepath',
                    'do.mime_type',
                    'do.media_type_id'
                )
                ->orderBy('ci.sort_order')
                ->limit($maxItems * 2)
                ->get();

            if ($items->isEmpty()) {
                return [];
            }

            // Get image derivatives
            $doIds = $items->pluck('digital_object_id')->toArray();
            $derivatives = DB::table('digital_object')
                ->whereIn('parent_id', $doIds)
                ->whereIn('usage_id', [$USAGE_REFERENCE, $USAGE_THUMBNAIL])
                ->where(function ($query) use ($imageMimeTypes) {
                    $query->whereIn('mime_type', $imageMimeTypes)
                        ->orWhere('mime_type', 'LIKE', 'image/%');
                })
                ->select('id', 'parent_id', 'name', 'path', 'usage_id', 'mime_type')
                ->get()
                ->groupBy('parent_id');

            // Operator-overridable base URL: settings.siteBaseUrl wins when
            // set (useful behind reverse proxies whose APP_URL differs from
            // the public URL); otherwise falls back to config('app.url').
            $baseUrl = \AhgCore\Support\GlobalSettings::siteBaseUrl();
            $slides = [];

            foreach ($items as $item) {
                if (count($slides) >= $maxItems) {
                    break;
                }

                $doDerivatives = $derivatives->get($item->digital_object_id, collect());
                $reference = $doDerivatives->firstWhere('usage_id', $USAGE_REFERENCE);
                $thumbnail = $doDerivatives->firstWhere('usage_id', $USAGE_THUMBNAIL);

                $isImage = $item->media_type_id == $MEDIA_IMAGE;
                $isAudio = $item->media_type_id == $MEDIA_AUDIO;
                $isVideo = $item->media_type_id == $MEDIA_VIDEO;
                $hasImageDerivative = $reference || $thumbnail;

                // Skip non-displayable items
                if (($isAudio || $isVideo) && ! $hasImageDerivative) {
                    continue;
                }
                if (! $isImage && ! $isAudio && ! $isVideo) {
                    continue;
                }

                $imageLarge = null;
                $imageThumb = null;

                if ($reference) {
                    $imageLarge = rtrim($reference->path, '/').'/'.$reference->name;
                }
                if ($thumbnail) {
                    $imageThumb = rtrim($thumbnail->path, '/').'/'.$thumbnail->name;
                }

                // IIIF fallback for images without derivatives
                $iiifFormats = ['image/jpeg', 'image/png', 'image/gif', 'image/tiff', 'image/jp2'];
                if (! $imageLarge && $isImage && in_array($item->mime_type, $iiifFormats)) {
                    $imagePath = ltrim($item->filepath, '/');
                    $cantaloupeId = str_replace('/', '_SL_', $imagePath).$item->filename;
                    $imageLarge = "{$baseUrl}/iiif/2/{$cantaloupeId}/full/1200,/0/default.jpg";
                    if (! $imageThumb) {
                        $imageThumb = "{$baseUrl}/iiif/2/{$cantaloupeId}/full/200,/0/default.jpg";
                    }
                }

                if (! $imageLarge && $imageThumb) {
                    $imageLarge = $imageThumb;
                }
                if (! $imageLarge) {
                    continue;
                }
                if (! $imageThumb) {
                    $imageThumb = $imageLarge;
                }

                $slides[] = [
                    'id' => $item->object_id,
                    'title' => $item->custom_label ?: $item->title ?: 'Untitled',
                    'identifier' => $item->identifier,
                    'slug' => $item->slug,
                    'image_large' => $imageLarge,
                    'image_thumb' => $imageThumb,
                    'link' => '/'.$item->slug,
                    'media_type' => $isImage ? 'image' : ($isVideo ? 'video' : ($isAudio ? 'audio' : 'other')),
                ];
            }

            if (empty($slides)) {
                return [];
            }

            return [
                'collection' => $collection,
                'slides' => $slides,
                'height' => $height,
                'autoplay' => $autoplay,
                'interval' => $interval,
                'showCaptions' => $showCaptions,
            ];
        } catch (\Exception $e) {
            return [];
        }
    }
}
