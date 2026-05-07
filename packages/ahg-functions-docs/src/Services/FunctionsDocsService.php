<?php

/**
 * FunctionsDocsService - Reads /opt/ai/km/auto_functions_kb*.md
 * and turns them into a paginated, browseable catalogue.
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
 *
 * Source files (regenerated every ~10 min by /opt/ai/km/build_functions_kb*.py
 * via systemd timer - never modify in place, always re-read at request time):
 *
 *   /opt/ai/km/auto_functions_kb.md          PHP   (~2.3 MB,  ~830 H1 sections, classes)
 *   /opt/ai/km/auto_functions_kb_js.md       JS    (~4   KB)
 *   /opt/ai/km/auto_functions_kb_blade.md    Blade (~390 KB,  H1 sections = file paths)
 *   /opt/ai/km/auto_functions_kb_py.md       Py    (~24  KB)
 *   /opt/ai/km/auto_functions_kb_routes.md   Routes(~510 KB,  H1 sections = controllers)
 */

namespace AhgFunctionsDocs\Services;

use Illuminate\Support\Facades\Cache;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class FunctionsDocsService
{
    /** Catalogue kind -> source file path on the host. */
    public const FILES = [
        'php'    => '/opt/ai/km/auto_functions_kb.md',
        'js'     => '/opt/ai/km/auto_functions_kb_js.md',
        'blade'  => '/opt/ai/km/auto_functions_kb_blade.md',
        'py'     => '/opt/ai/km/auto_functions_kb_py.md',
        'routes' => '/opt/ai/km/auto_functions_kb_routes.md',
    ];

    /** Display metadata per kind. */
    public const META = [
        'php'    => ['label' => 'PHP classes & methods',  'icon' => 'bi-filetype-php',   'group_label' => 'class',      'paginate' => true,  'page_size' => 40],
        'js'     => ['label' => 'JavaScript modules',     'icon' => 'bi-filetype-js',    'group_label' => 'file',       'paginate' => false, 'page_size' => 0],
        'blade'  => ['label' => 'Blade templates',        'icon' => 'bi-filetype-html',  'group_label' => 'template',   'paginate' => true,  'page_size' => 60],
        'py'     => ['label' => 'Python helpers',         'icon' => 'bi-filetype-py',    'group_label' => 'module',     'paginate' => false, 'page_size' => 0],
        'routes' => ['label' => 'HTTP routes',            'icon' => 'bi-signpost-2',     'group_label' => 'controller', 'paginate' => true,  'page_size' => 30],
    ];

    /** Cache TTL for parsed markdown sections (seconds). */
    private const CACHE_TTL = 300;

    /**
     * Catalogue summary for the index page: kind + label + path + size + section count + mtime.
     * One pass over the 5 files, keyed on file mtime so a regen automatically invalidates.
     */
    public function index(): array
    {
        $out = [];
        foreach (self::FILES as $kind => $path) {
            $out[$kind] = $this->summary($kind);
        }
        return $out;
    }

    /** Per-kind summary (label, icon, mtime, byte size, H1 count, status). */
    public function summary(string $kind): array
    {
        $path = self::FILES[$kind] ?? null;
        $meta = self::META[$kind]   ?? null;
        if (!$path || !$meta) {
            return ['kind' => $kind, 'available' => false, 'reason' => 'unknown kind'];
        }
        if (!is_readable($path)) {
            return [
                'kind'      => $kind,
                'available' => false,
                'reason'    => 'source file unreadable: ' . $path,
                'label'     => $meta['label'],
                'icon'      => $meta['icon'],
                'paginate'  => $meta['paginate'],
            ];
        }

        $mtime = filemtime($path) ?: 0;
        $size  = filesize($path)  ?: 0;
        $cacheKey = 'fnDocs:summary:' . $kind . ':' . $mtime;

        $count = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($path) {
            return $this->countH1($path);
        });

        return [
            'kind'           => $kind,
            'available'      => true,
            'label'          => $meta['label'],
            'icon'           => $meta['icon'],
            'group_label'    => $meta['group_label'],
            'paginate'       => $meta['paginate'],
            'page_size'      => $meta['page_size'],
            'path'           => $path,
            'byte_size'      => $size,
            'mtime'          => $mtime,
            'section_count'  => $count,
        ];
    }

    /** Count H1 (^# ) headings in a file. Streamed line-by-line to keep memory low for the 2.3MB PHP file. */
    private function countH1(string $path): int
    {
        $n = 0;
        $fh = @fopen($path, 'r');
        if (!$fh) {
            return 0;
        }
        try {
            while (($line = fgets($fh)) !== false) {
                if (isset($line[0]) && $line[0] === '#' && isset($line[1]) && $line[1] === ' ') {
                    $n++;
                }
            }
        } finally {
            fclose($fh);
        }
        return $n;
    }

    /**
     * Parse the file into a list of H1 sections.
     * Each section: ['title' => 'class FQN / file path / controller', 'body' => 'remaining markdown', 'anchor' => 'slug'].
     * Cached by file mtime so the regen pipeline auto-invalidates.
     */
    public function sections(string $kind): array
    {
        $path = self::FILES[$kind] ?? null;
        if (!$path || !is_readable($path)) {
            return [];
        }
        $mtime = filemtime($path) ?: 0;
        $cacheKey = 'fnDocs:sections:' . $kind . ':' . $mtime;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($path) {
            $raw = @file_get_contents($path);
            if ($raw === false) {
                return [];
            }
            return $this->splitOnH1($raw);
        });
    }

    /** Split raw markdown on H1 boundaries. The intro (everything before the first H1) becomes its own pseudo-section. */
    private function splitOnH1(string $raw): array
    {
        $lines = preg_split('/\R/u', $raw);
        $sections = [];
        $intro = [];
        $current = null;

        foreach ($lines as $line) {
            // H1 = "# Title" but NOT "## Title". Match a line that starts with "# " and the 3rd char is not "#".
            if (isset($line[0]) && $line[0] === '#' && isset($line[1]) && $line[1] === ' ') {
                if ($current !== null) {
                    $sections[] = $current;
                }
                $title = trim(substr($line, 2));
                $current = [
                    'title'  => $title,
                    'anchor' => $this->anchor($title),
                    'body'   => '',
                    'body_lines' => [],
                ];
            } elseif ($current === null) {
                $intro[] = $line;
            } else {
                $current['body_lines'][] = $line;
            }
        }
        if ($current !== null) {
            $sections[] = $current;
        }

        // Compact body lines into strings.
        foreach ($sections as &$s) {
            $s['body'] = trim(implode("\n", $s['body_lines']));
            unset($s['body_lines']);
        }
        unset($s);

        return [
            'intro'    => trim(implode("\n", $intro)),
            'sections' => $sections,
        ];
    }

    /** Slug for an in-page anchor. */
    private function anchor(string $title): string
    {
        $slug = strtolower($title);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'section';
    }

    /**
     * Render a slice of sections as HTML.
     * Returns: ['intro_html', 'sections' => [['title','anchor','body_html'], ...], 'page', 'pages', 'total'].
     */
    public function render(string $kind, int $page = 1, ?string $filter = null): array
    {
        $parsed = $this->sections($kind);
        $meta   = self::META[$kind] ?? null;
        if (!$meta) {
            return ['intro_html' => '', 'sections' => [], 'page' => 1, 'pages' => 1, 'total' => 0, 'filtered' => 0];
        }

        $all = $parsed['sections'] ?? [];
        $intro = $parsed['intro'] ?? '';

        // Filter by case-insensitive substring on title.
        if ($filter !== null && $filter !== '') {
            $needle = mb_strtolower($filter);
            $all = array_values(array_filter($all, function ($s) use ($needle) {
                return mb_strpos(mb_strtolower($s['title']), $needle) !== false;
            }));
        }

        $total = count($all);

        // Paginate or return all.
        if ($meta['paginate'] && $meta['page_size'] > 0) {
            $size = $meta['page_size'];
            $pages = max(1, (int) ceil($total / $size));
            $page = max(1, min($pages, $page));
            $slice = array_slice($all, ($page - 1) * $size, $size);
        } else {
            $pages = 1;
            $page = 1;
            $slice = $all;
        }

        // Convert each section's body via GFM. For the routes catalogue, the
        // body contains backtick-delimited HTTP method / path tokens and pipe
        // tables don't appear, but GFM handles those equally.
        $converter = new GithubFlavoredMarkdownConverter([
            'html_input'         => 'escape',
            'allow_unsafe_links' => false,
        ]);

        $rendered = [];
        foreach ($slice as $s) {
            $rendered[] = [
                'title'     => $s['title'],
                'anchor'    => $s['anchor'],
                'body_html' => (string) $converter->convert($s['body']),
            ];
        }

        $introHtml = $intro !== '' ? (string) $converter->convert($intro) : '';

        return [
            'intro_html' => $introHtml,
            'sections'   => $rendered,
            'page'       => $page,
            'pages'      => $pages,
            'total'      => $total,
            'filtered'   => $filter !== null && $filter !== '' ? $total : 0,
            'toc'        => array_map(function ($s) {
                return ['title' => $s['title'], 'anchor' => $s['anchor']];
            }, $slice),
        ];
    }
}
