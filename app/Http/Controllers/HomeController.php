<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    /**
     * Homepage — migrated from ahgThemeB5Plugin homeSuccess.php.
     * 2-column layout: sidebar (static pages menu, browse-by, popular this week)
     * + main content (featured collection carousel, static page HTML content).
     */
    public function index()
    {
        // Get homepage static page content
        $page = DB::table('static_page')
            ->join('static_page_i18n', 'static_page.id', '=', 'static_page_i18n.id')
            ->join('slug', 'static_page.id', '=', 'slug.object_id')
            ->where('slug.slug', 'home')
            ->where('static_page_i18n.culture', 'en')
            ->select('static_page.id', 'static_page_i18n.title', 'static_page_i18n.content')
            ->first();

        // Browse menu items (children of the Browse menu)
        $browseMenuId = DB::table('menu')->where('name', 'browse')->value('id');
        $browseItems = collect();
        if ($browseMenuId) {
            $browseItems = DB::table('menu')
                ->join('menu_i18n', 'menu.id', '=', 'menu_i18n.id')
                ->where('menu_i18n.culture', 'en')
                ->where('menu.parent_id', $browseMenuId)
                ->orderBy('menu.lft')
                ->select('menu_i18n.label', 'menu.path')
                ->get();
        }

        // Static pages menu (children of the Quick Links > Static Pages menu)
        $staticPages = DB::table('static_page')
            ->join('static_page_i18n', 'static_page.id', '=', 'static_page_i18n.id')
            ->join('slug', 'static_page.id', '=', 'slug.object_id')
            ->where('static_page_i18n.culture', 'en')
            ->select('static_page_i18n.title', 'slug.slug')
            ->orderBy('static_page_i18n.title')
            ->get();

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
                ->map(function ($row) {
                    $obj = DB::table('object')->where('id', $row->object_id)->first();
                    if (!$obj) {
                        return null;
                    }

                    $slug = DB::table('slug')->where('object_id', $row->object_id)->value('slug');
                    $title = null;
                    $module = null;

                    if ($obj->class_name === 'QubitInformationObject') {
                        $title = DB::table('information_object_i18n')
                            ->where('id', $row->object_id)->where('culture', 'en')
                            ->value('title');
                        $module = 'informationobject';
                    } elseif ($obj->class_name === 'QubitRepository') {
                        $title = DB::table('repository_i18n')
                            ->where('id', $row->object_id)->where('culture', 'en')
                            ->value('authorized_form_of_name');
                        $module = 'repository';
                    } elseif ($obj->class_name === 'QubitActor') {
                        $title = DB::table('actor_i18n')
                            ->where('id', $row->object_id)->where('culture', 'en')
                            ->value('authorized_form_of_name');
                        $module = 'actor';
                    }

                    if (!$title || !$slug) {
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
            if (!$collectionId) {
                return [];
            }

            $collection = DB::table('iiif_collection')->where('id', $collectionId)->first();
            if (!$collection) {
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

            // Get collection items
            $items = DB::table('iiif_collection_item as ci')
                ->leftJoin('information_object as io', 'ci.object_id', '=', 'io.id')
                ->leftJoin('information_object_i18n as i18n', function ($join) {
                    $join->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
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
                    'i18n.title',
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

            $baseUrl = rtrim(config('app.url'), '/');
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
                if (($isAudio || $isVideo) && !$hasImageDerivative) {
                    continue;
                }
                if (!$isImage && !$isAudio && !$isVideo) {
                    continue;
                }

                $imageLarge = null;
                $imageThumb = null;

                if ($reference) {
                    $imageLarge = rtrim($reference->path, '/') . '/' . $reference->name;
                }
                if ($thumbnail) {
                    $imageThumb = rtrim($thumbnail->path, '/') . '/' . $thumbnail->name;
                }

                // IIIF fallback for images without derivatives
                $iiifFormats = ['image/jpeg', 'image/png', 'image/gif', 'image/tiff', 'image/jp2'];
                if (!$imageLarge && $isImage && in_array($item->mime_type, $iiifFormats)) {
                    $imagePath = ltrim($item->filepath, '/');
                    $cantaloupeId = str_replace('/', '_SL_', $imagePath) . $item->filename;
                    $imageLarge = "{$baseUrl}/iiif/2/{$cantaloupeId}/full/1200,/0/default.jpg";
                    if (!$imageThumb) {
                        $imageThumb = "{$baseUrl}/iiif/2/{$cantaloupeId}/full/200,/0/default.jpg";
                    }
                }

                if (!$imageLarge && $imageThumb) {
                    $imageLarge = $imageThumb;
                }
                if (!$imageLarge) {
                    continue;
                }
                if (!$imageThumb) {
                    $imageThumb = $imageLarge;
                }

                $slides[] = [
                    'id' => $item->object_id,
                    'title' => $item->custom_label ?: $item->title ?: 'Untitled',
                    'identifier' => $item->identifier,
                    'slug' => $item->slug,
                    'image_large' => $imageLarge,
                    'image_thumb' => $imageThumb,
                    'link' => '/' . $item->slug,
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
