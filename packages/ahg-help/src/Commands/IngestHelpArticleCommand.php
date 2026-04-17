<?php

/**
 * IngestHelpArticleCommand - Command for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems
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

namespace AhgHelp\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class IngestHelpArticleCommand extends Command
{
    protected $signature = 'ahg:help-ingest
        {--path= : Path to the source markdown file, relative to base_path() or absolute}
        {--slug= : Unique slug for the help article}
        {--title= : Article title (defaults to first H1 in the markdown)}
        {--category=Technical : Help category (e.g. Technical, System Administration, User Guide)}
        {--subcategory= : Optional subcategory}
        {--tags= : Comma-separated tags}
        {--related-plugin= : Related plugin slug for cross-linking}
        {--sort-order=100 : Sort order within category}
        {--unpublish : Mark the article as unpublished instead of published}';

    protected $description = 'Ingest a markdown file from docs/ into the help_article table, rendering HTML and extracting sections.';

    public function handle(): int
    {
        $pathOpt = (string) $this->option('path');
        $slug    = (string) $this->option('slug');

        if ($pathOpt === '' || $slug === '') {
            $this->error('Both --path and --slug are required.');
            return self::FAILURE;
        }

        $path = $pathOpt;
        if (!str_starts_with($path, '/')) {
            $path = base_path($path);
        }
        if (!is_file($path) || !is_readable($path)) {
            $this->error("Source file not readable: {$path}");
            return self::FAILURE;
        }

        $markdown = file_get_contents($path);
        if ($markdown === false || $markdown === '') {
            $this->error("Source file is empty: {$path}");
            return self::FAILURE;
        }

        $title = (string) $this->option('title');
        if ($title === '') {
            if (preg_match('/^\s*#\s+(.+?)\s*$/m', $markdown, $m)) {
                $title = trim($m[1]);
            } else {
                $title = basename($path, '.md');
            }
        }

        $converter = new GithubFlavoredMarkdownConverter([
            'html_input'         => 'allow',
            'allow_unsafe_links' => false,
        ]);
        $html = (string) $converter->convert($markdown);
        $html = $this->addHeadingIds($html);

        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
        $wordCount = $text === '' ? 0 : str_word_count($text);

        $sections = $this->extractSections($markdown);
        $tocJson  = json_encode(array_map(
            fn ($s) => ['level' => $s['level'], 'text' => $s['heading'], 'anchor' => $s['anchor']],
            $sections
        ), JSON_UNESCAPED_SLASHES);

        $sourceFile = $this->relativeSourceFile($path);
        $now        = Carbon::now();

        $payload = [
            'title'          => $title,
            'category'       => (string) $this->option('category'),
            'subcategory'    => $this->option('subcategory') ?: null,
            'source_file'    => $sourceFile,
            'body_markdown'  => $markdown,
            'body_html'      => $html,
            'body_text'      => $text,
            'toc_json'       => $tocJson,
            'word_count'     => $wordCount,
            'sort_order'     => (int) $this->option('sort-order'),
            'is_published'   => $this->option('unpublish') ? 0 : 1,
            'related_plugin' => $this->option('related-plugin') ?: null,
            'tags'           => $this->option('tags') ?: null,
            'updated_at'     => $now,
        ];

        $existing = DB::table('help_article')->where('slug', $slug)->first();
        if ($existing) {
            DB::table('help_article')->where('id', $existing->id)->update($payload);
            $articleId = (int) $existing->id;
            $this->info("Updated help article #{$articleId} ({$slug})");
        } else {
            $articleId = (int) DB::table('help_article')->insertGetId(array_merge($payload, [
                'slug'       => $slug,
                'created_at' => $now,
            ]));
            $this->info("Inserted help article #{$articleId} ({$slug})");
        }

        DB::table('help_section')->where('article_id', $articleId)->delete();
        foreach ($sections as $i => $s) {
            DB::table('help_section')->insert([
                'article_id' => $articleId,
                'heading'    => $s['heading'],
                'anchor'     => $s['anchor'],
                'level'      => $s['level'],
                'body_text'  => $s['body_text'],
                'sort_order' => $i,
            ]);
        }
        $this->info('Section count: ' . count($sections));

        return self::SUCCESS;
    }

    /**
     * Walk the markdown line-by-line, emit a section per H2..H4 with its following body.
     */
    private function extractSections(string $markdown): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $markdown) ?: [];
        $sections = [];
        $current  = null;

        foreach ($lines as $line) {
            if (preg_match('/^(#{2,4})\s+(.+?)\s*$/', $line, $m)) {
                if ($current !== null) {
                    $current['body_text'] = trim($current['body_text']);
                    $sections[] = $current;
                }
                $level   = strlen($m[1]);
                $heading = trim($m[2]);
                $current = [
                    'heading'   => $heading,
                    'anchor'    => $this->slugify($heading),
                    'level'     => $level,
                    'body_text' => '',
                ];
                continue;
            }
            if ($current !== null) {
                $current['body_text'] .= $line . "\n";
            }
        }

        if ($current !== null) {
            $current['body_text'] = trim($current['body_text']);
            $sections[] = $current;
        }
        return $sections;
    }

    private function slugify(string $s): string
    {
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        return trim($s, '-');
    }

    /**
     * Walk rendered HTML and add id="<slug>" to every h1..h6 that doesn't already have one.
     * Anchor slug matches the TOC slugify() rule so TOC links resolve.
     */
    private function addHeadingIds(string $html): string
    {
        return preg_replace_callback(
            '/<(h[1-6])\b([^>]*)>(.*?)<\/\1>/is',
            function (array $m): string {
                $tag   = $m[1];
                $attrs = $m[2];
                $inner = $m[3];
                if (preg_match('/\bid\s*=/i', $attrs)) {
                    return $m[0];
                }
                $text   = trim(html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $anchor = $this->slugify($text);
                if ($anchor === '') {
                    return $m[0];
                }
                return "<{$tag} id=\"{$anchor}\"{$attrs}>{$inner}</{$tag}>";
            },
            $html
        ) ?? $html;
    }

    private function relativeSourceFile(string $absolute): string
    {
        $base = base_path() . '/';
        if (str_starts_with($absolute, $base)) {
            return substr($absolute, strlen($base));
        }
        return $absolute;
    }
}
