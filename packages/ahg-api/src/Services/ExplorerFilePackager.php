<?php

/**
 * ExplorerFilePackager - copy records' images and PDFs into an offline
 * explorer package, linked by relative path, so it runs from a USB drive with
 * no server (heratio#1524).
 *
 * A package leaves the building, so every file is put to the same check nginx
 * applies to an anonymous visitor (/privacy/file-check): drafts, restricted,
 * redacted, unscreened and protected originals are not copied, and a record
 * whose image is refused loses its image link. Only the formats a browser
 * shows safely are taken; SVG is not, because it can carry script.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgApi\Services;

use AhgInformationObjectManage\Controllers\PrivacyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ExplorerFilePackager
{
    private const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

    /** digital_object.usage_id: reference copy first, thumbnail as the fallback. */
    private const USAGE_REFERENCE = 141;

    private const USAGE_THUMBNAIL = 142;

    /** The folders this packager owns inside a package. */
    private const DIRS = ['img', 'files'];

    /** Marks a folder as an export, so a later export may replace its files. */
    private const MARKER = '.heratio-explorer-package';

    /**
     * @param  list<array>  $records  explorer records; img and pdf are rewritten in place
     * @return array{images: int, pdfs: int, skipped: int}
     */
    public function package(array &$records, string $out): array
    {
        $stats = ['images' => 0, 'pdfs' => 0, 'skipped' => 0];
        $this->clear($out);
        foreach (self::DIRS as $dir) {
            if (is_link("$out/$dir") || (is_dir("$out/$dir") && (new \FilesystemIterator("$out/$dir"))->valid())) {
                throw new \RuntimeException("{$out}/{$dir} holds files this export did not write. Choose an empty folder.");
            }
            if (! is_dir("$out/$dir")) {
                mkdir("$out/$dir", 0775, true);
            }
        }
        file_put_contents("$out/".self::MARKER, "Written by ahg:explorer-export. img/ and files/ are replaced on every export.\n");
        $ids = array_map(fn ($r) => (int) $r['id'], $records);
        $images = $pdfs = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            $images += $this->imageRows($chunk);
            $pdfs += $this->pdfRows($chunk);
        }

        foreach ($records as &$r) {
            $id = (int) $r['id'];
            unset($r['img']); // never leave a link back to the server in an offline package
            if (isset($images[$id])) {
                $name = $this->copy($images[$id], "$out/img", (string) $id, self::IMAGE_TYPES);
                if ($name !== null) {
                    $r['img'] = "img/$name";
                    $stats['images']++;
                } else {
                    $stats['skipped']++;
                }
            }
            if (isset($pdfs[$id])) {
                $name = $this->copy($pdfs[$id], "$out/files", (string) $id, ['application/pdf' => 'pdf']);
                if ($name !== null) {
                    $r['pdf'] = "files/$name";
                    $stats['pdfs']++;
                } else {
                    $stats['skipped']++;
                }
            }
        }
        unset($r);

        return $stats;
    }

    /**
     * Remove the files of an earlier export, so a record restricted since then
     * cannot stay on the drive. Only a folder this exporter marked as its own
     * is touched; img/ or files/ that are links are refused, never followed.
     */
    public function clear(string $out): void
    {
        if (! is_file("$out/".self::MARKER)) {
            return;
        }
        foreach (self::DIRS as $dir) {
            if (is_link("$out/$dir")) {
                throw new \RuntimeException("{$out}/{$dir} is a link. Remove it and export again.");
            }
        }
        foreach (self::DIRS as $dir) {
            if (is_dir("$out/$dir") && (! File::deleteDirectory("$out/$dir") || is_dir("$out/$dir"))) {
                throw new \RuntimeException("Cannot clear {$out}/{$dir}; the earlier files are still there.");
            }
        }
        unlink("$out/".self::MARKER);
    }

    /** @return array<int, object> record id => its reference image, else its thumbnail */
    private function imageRows(array $ids): array
    {
        $rows = DB::table('digital_object as m')->join('digital_object as d', 'd.parent_id', '=', 'm.id')
            ->whereIn('m.object_id', $ids)->whereIn('d.usage_id', [self::USAGE_REFERENCE, self::USAGE_THUMBNAIL])
            ->where('d.mime_type', 'like', 'image/%')->orderBy('m.id')->orderBy('d.usage_id')
            ->get(['m.object_id', 'd.path', 'd.name']);
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->object_id] ??= $row;
        }

        return $out;
    }

    /** @return array<int, object> record id => its PDF */
    private function pdfRows(array $ids): array
    {
        $out = [];
        foreach (DB::table('digital_object')->whereIn('object_id', $ids)->where('mime_type', 'application/pdf')
            ->orderBy('id')->get(['object_id', 'path', 'name']) as $row) {
            $out[(int) $row->object_id] ??= $row;
        }

        return $out;
    }

    /** @param array<string, string> $types allowed real file type => extension */
    private function copy(object $row, string $dir, string $base, array $types): ?string
    {
        $web = '/'.ltrim(rtrim((string) $row->path, '/').'/'.$row->name, '/');
        if (! $this->releasedToPublic($web)) {
            return null;
        }
        $file = $this->resolve($web);
        if ($file === null) {
            return null;
        }
        $ext = $types[(string) (new \finfo(FILEINFO_MIME_TYPE))->file($file)] ?? null;
        if ($ext === null || ! copy($file, "$dir/$base.$ext")) {
            return null;
        }

        return "$base.$ext";
    }

    /** The upload gate's own answer for a visitor who is not signed in. */
    private function releasedToPublic(string $web): bool
    {
        if (auth()->check()) {
            return false; // the answer must be the anonymous one
        }
        $request = Request::create('/privacy/file-check');
        $request->headers->set('X-Original-URI', implode('/', array_map('rawurlencode', explode('/', $web))));
        $request->headers->set('X-Original-Path', $web);

        // The decision only: packaging a file is not a visitor's download.
        return ! app(PrivacyController::class)->refusesFile($request);
    }

    /** The file on disk, only if it really sits inside the uploads folder. */
    private function resolve(string $web): ?string
    {
        $root = realpath((string) config('heratio.uploads_path'));
        if ($root === false) {
            return null;
        }
        $relative = ltrim($web, '/');
        foreach (['uploads/r/', 'uploads/'] as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                $relative = substr($relative, strlen($prefix));
                break;
            }
        }
        $real = realpath($root.'/'.$relative);

        return $real !== false && str_starts_with($real, $root.'/') && is_file($real) ? $real : null;
    }
}
