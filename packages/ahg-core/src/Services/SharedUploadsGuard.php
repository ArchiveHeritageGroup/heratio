<?php

/**
 * SharedUploadsGuard - Heratio ahg-core
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * heratio#1734: the uploads folder can be shared with another system's
 * catalogue (on heratio.org, PSIS uses the same folder as its uploads/r).
 * Before Heratio moves a file to the trash, deletes it or purges it, this
 * says whether that is safe.
 *
 * Off unless the other system's database is configured (SHARED_UPLOADS_DB_*
 * in .env, connection 'shared_uploads'). When on, a file inside the uploads
 * folder is touched only when
 *   1. it sits in a numeric folder (Heratio's own: <uploads>/[r/]<id>/...),
 *      never a repository-slug folder the other system writes; and
 *   2. the other system's digital_object table does not reference it.
 * If that database cannot be read, the answer is no (fail closed).
 * Files outside the uploads folder are not shared and always allowed.
 */
class SharedUploadsGuard
{
    public const CONNECTION = 'shared_uploads';

    public static function enabled(): bool
    {
        return (string) config('database.connections.'.self::CONNECTION.'.database', '') !== '';
    }

    /** Null when the file may be moved or deleted; otherwise the reason it may not. */
    public static function refuse(string $file): ?string
    {
        if (! self::enabled()) {
            return null;
        }
        $rel = self::relative($file);
        if ($rel === null) {
            return null; // not in the shared folder
        }
        if (! preg_match('#^\d+/#', $rel)) {
            return 'not in a Heratio numeric folder: '.$rel;
        }
        try {
            $dir = dirname($rel);
            $used = DB::connection(self::CONNECTION)->table('digital_object')
                ->whereIn('path', ['/uploads/r/'.$dir.'/', 'uploads/r/'.$dir.'/', '/uploads/r/'.$dir])
                ->where('name', basename($rel))->exists();
        } catch (\Throwable $e) {
            Log::warning('[shared-uploads] cannot check '.$rel.': '.$e->getMessage());

            return 'the shared catalogue could not be checked';
        }

        return $used ? 'still used by the shared catalogue: '.$rel : null;
    }

    /** Path below the shared uploads folder (without a leading r/), or null when outside it. */
    private static function relative(string $file): ?string
    {
        $uploads = rtrim((string) config('heratio.uploads_path', ''), '/');
        if ($uploads === '') {
            return null;
        }
        $real = realpath($file) ?: $file;
        foreach (array_filter([realpath($uploads.'/r'), realpath($uploads), $uploads.'/r', $uploads]) as $base) {
            if (str_starts_with($real, $base.'/')) {
                $rel = substr($real, strlen($base) + 1);

                return str_starts_with($rel, 'r/') ? substr($rel, 2) : $rel;
            }
        }

        return null;
    }
}
