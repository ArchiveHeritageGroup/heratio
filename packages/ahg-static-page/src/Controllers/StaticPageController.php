<?php

namespace AhgStaticPage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class StaticPageController extends Controller
{
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
