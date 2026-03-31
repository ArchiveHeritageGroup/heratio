<?php

/**
 * StaticPageController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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



namespace AhgStaticPage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class StaticPageController extends Controller
{
    public function browse()
    {
        $culture = app()->getLocale();
        $pages = DB::table('static_page')
            ->join('static_page_i18n', 'static_page.id', '=', 'static_page_i18n.id')
            ->join('slug', 'static_page.id', '=', 'slug.object_id')
            ->where('static_page_i18n.culture', $culture)
            ->select('static_page.id', 'static_page_i18n.title', 'slug.slug')
            ->orderBy('static_page_i18n.title')
            ->get();

        return view('ahg-static-page::browse', compact('pages'));
    }

    public function list()
    {
        $culture = app()->getLocale();
        $pages = DB::table('static_page')
            ->join('static_page_i18n', 'static_page.id', '=', 'static_page_i18n.id')
            ->join('slug', 'static_page.id', '=', 'slug.object_id')
            ->where('static_page_i18n.culture', $culture)
            ->select('static_page.id', 'static_page_i18n.title', 'slug.slug')
            ->orderBy('static_page_i18n.title')
            ->get();

        return view('ahg-static-page::list', compact('pages'));
    }

    public function confirmDelete(string $slug)
    {
        $culture = app()->getLocale();

        $slugRow = DB::table('slug')->where('slug', $slug)->first();
        if (!$slugRow) {
            abort(404);
        }

        // Prevent deletion of protected pages
        $protectedSlugs = ['home', 'about', 'contact'];
        if (in_array($slug, $protectedSlugs)) {
            return redirect()->route('staticpage.show', $slug)
                ->with('error', __('Protected pages cannot be deleted.'));
        }

        $page = DB::table('static_page')
            ->leftJoin('static_page_i18n', function ($join) use ($culture) {
                $join->on('static_page.id', '=', 'static_page_i18n.id')
                    ->where('static_page_i18n.culture', '=', $culture);
            })
            ->where('static_page.id', $slugRow->object_id)
            ->select('static_page.id', 'static_page_i18n.title')
            ->first();

        if (!$page) {
            abort(404);
        }

        return view('ahg-static-page::delete', [
            'page' => $page,
            'slug' => $slug,
        ]);
    }

    public function destroy(string $slug)
    {
        // Prevent deletion of protected pages
        $protectedSlugs = ['home', 'about', 'contact'];

        if (in_array($slug, $protectedSlugs)) {
            return redirect()->route('staticpage.list')
                ->with('error', __('Protected pages cannot be deleted.'));
        }

        $slugRow = DB::table('slug')->where('slug', $slug)->first();
        if (!$slugRow) {
            abort(404);
        }

        $id = $slugRow->object_id;

        // Verify the static page exists
        $exists = DB::table('static_page')->where('id', $id)->exists();
        if (!$exists) {
            abort(404);
        }

        DB::transaction(function () use ($id) {
            DB::table('static_page_i18n')->where('id', $id)->delete();
            DB::table('slug')->where('object_id', $id)->delete();
            DB::table('static_page')->where('id', $id)->delete();
            DB::table('object')->where('id', $id)->delete();
        });

        return redirect()->route('staticpage.list')
            ->with('success', __('Static page deleted successfully.'));
    }

    public function create()
    {
        return view('ahg-static-page::edit', [
            'page' => null,
            'slug' => '',
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:1024',
            'slug' => 'required|string|max:255|regex:/^[^;]*$/',
            'content' => 'nullable|string',
        ]);

        $culture = app()->getLocale();

        $id = DB::transaction(function () use ($request, $culture) {
            $objectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitStaticPage',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('static_page')->insert([
                'id' => $objectId,
                'source_culture' => $culture,
            ]);

            DB::table('static_page_i18n')->insert([
                'id' => $objectId,
                'culture' => $culture,
                'title' => $request->input('title'),
                'content' => $request->input('content', ''),
            ]);

            DB::table('slug')->insert([
                'object_id' => $objectId,
                'slug' => $request->input('slug'),
            ]);

            return $objectId;
        });

        return redirect()->route('staticpage.show', $request->input('slug'))
            ->with('success', 'Page created.');
    }

    public function edit(string $slug)
    {
        $culture = app()->getLocale();

        $slugRow = DB::table('slug')->where('slug', $slug)->first();
        if (!$slugRow) {
            abort(404);
        }

        $page = DB::table('static_page')
            ->leftJoin('static_page_i18n', function ($join) use ($culture) {
                $join->on('static_page.id', '=', 'static_page_i18n.id')
                    ->where('static_page_i18n.culture', '=', $culture);
            })
            ->where('static_page.id', $slugRow->object_id)
            ->select('static_page.id', 'static_page_i18n.title', 'static_page_i18n.content')
            ->first();

        if (!$page) {
            abort(404);
        }

        // Check if slug is protected (home, about, etc.)
        $protectedSlugs = ['home', 'about', 'contact', 'privacy', 'terms'];

        return view('ahg-static-page::edit', [
            'page' => $page,
            'slug' => $slug,
            'isProtected' => in_array($slug, $protectedSlugs),
        ]);
    }

    public function update(Request $request, string $slug)
    {
        $request->validate([
            'title' => 'required|string|max:1024',
            'slug' => 'required|string|max:255|regex:/^[^;]*$/',
            'content' => 'nullable|string',
        ]);

        $culture = app()->getLocale();

        $slugRow = DB::table('slug')->where('slug', $slug)->first();
        if (!$slugRow) {
            abort(404);
        }

        $id = $slugRow->object_id;

        DB::transaction(function () use ($id, $request, $culture, $slug) {
            // Update or insert i18n
            $exists = DB::table('static_page_i18n')
                ->where('id', $id)->where('culture', $culture)->exists();

            if ($exists) {
                DB::table('static_page_i18n')
                    ->where('id', $id)->where('culture', $culture)
                    ->update([
                        'title' => $request->input('title'),
                        'content' => $request->input('content', ''),
                    ]);
            } else {
                DB::table('static_page_i18n')->insert([
                    'id' => $id,
                    'culture' => $culture,
                    'title' => $request->input('title'),
                    'content' => $request->input('content', ''),
                ]);
            }

            // Update slug if changed and not protected
            $newSlug = $request->input('slug');
            $protectedSlugs = ['home', 'about', 'contact', 'privacy', 'terms'];
            if ($newSlug !== $slug && !in_array($slug, $protectedSlugs)) {
                DB::table('slug')->where('object_id', $id)->update(['slug' => $newSlug]);
            }

            DB::table('object')->where('id', $id)->update(['updated_at' => now()]);
        });

        $finalSlug = $request->input('slug');
        $protectedSlugs = ['home', 'about', 'contact', 'privacy', 'terms'];
        if (in_array($slug, $protectedSlugs)) {
            $finalSlug = $slug;
        }

        return redirect()->route('staticpage.show', $finalSlug)
            ->with('success', 'Page updated.');
    }

    public function show(string $slug)
    {
        $culture = app()->getLocale();

        // Resolve slug to static_page
        $slugRow = DB::table('slug')
            ->where('slug', $slug)
            ->first();

        if (!$slugRow) {
            abort(404);
        }

        $page = DB::table('static_page')
            ->leftJoin('static_page_i18n', function ($join) use ($culture) {
                $join->on('static_page.id', '=', 'static_page_i18n.id')
                    ->where('static_page_i18n.culture', '=', $culture);
            })
            ->where('static_page.id', $slugRow->object_id)
            ->select([
                'static_page.id',
                'static_page.source_culture',
                'static_page_i18n.title',
                'static_page_i18n.content',
                DB::raw("'" . addslashes($slug) . "' as slug"),
            ])
            ->first();

        if (!$page) {
            abort(404);
        }

        // Fallback to source_culture if no translation found
        if (empty($page->title) && $culture !== $page->source_culture) {
            $fallback = DB::table('static_page_i18n')
                ->where('id', $page->id)
                ->where('culture', $page->source_culture)
                ->select(['title', 'content'])
                ->first();

            if ($fallback) {
                $page->title = $fallback->title;
                $page->content = $fallback->content;
            }
        }

        // Parse Markdown if enabled
        $markdownEnabled = DB::table('setting')
            ->leftJoin('setting_i18n', function ($j) {
                $j->on('setting.id', '=', 'setting_i18n.id')->where('setting_i18n.culture', '=', 'en');
            })
            ->where('setting.name', 'markdown_enabled')->whereNull('setting.scope')
            ->value('setting_i18n.value');

        if ($markdownEnabled !== '0' && !empty($page->content)) {
            // Convert literal \n to actual newlines (DB may store escaped newlines)
            $content = str_replace(['\\n', '\n'], "\n", $page->content);
            $converter = new \League\CommonMark\CommonMarkConverter([
                'html_input' => 'allow',
                'allow_unsafe_links' => false,
            ]);
            $page->content = $converter->convert($content)->getContent();
        }

        return view('ahg-static-page::show', [
            'page' => $page,
        ]);
    }
}
