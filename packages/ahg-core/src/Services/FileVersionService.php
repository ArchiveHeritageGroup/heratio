<?php

/**
 * FileVersionService - Heratio ahg-core
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1759: replacing a master file keeps the file it replaces as a
 * numbered version (major.minor). The first replacement records the original
 * as 1.0; a minor change adds 0.1, a major one starts the next whole number.
 *
 * Kept copies live under storage_path/versions/<digital object id>/ - outside
 * the uploads folder the web server serves - so an old version is reachable
 * only through the staff download route, never by URL.
 */
class FileVersionService
{
    public const TABLE = 'ahg_file_version';

    public static function available(): bool
    {
        return Schema::hasTable(self::TABLE);
    }

    public static function dir(int $digitalObjectId): string
    {
        return rtrim((string) config('heratio.storage_path'), '/').'/versions/'.$digitalObjectId;
    }

    /** Versions of a digital object, newest first. */
    public static function list(int $digitalObjectId): \Illuminate\Support\Collection
    {
        return self::available()
            ? DB::table(self::TABLE.' as v')->leftJoin('user as u', 'u.id', '=', 'v.created_by')
                ->where('v.digital_object_id', $digitalObjectId)
                ->orderByDesc('v.version_major')->orderByDesc('v.version_minor')
                ->get(['v.*', 'u.username'])
            : collect();
    }

    /**
     * Replace the master's file with $newFile (a local path), keeping the
     * current file as a version. Returns the new current version row, or null
     * when versions are not available (the caller then replaces as before).
     */
    public static function replace(object $master, string $currentPath, string $newFile, bool $major = false, ?string $note = null, ?string $newName = null): ?object
    {
        if (! self::available() || ! is_file($currentPath)) {
            return null;
        }
        $dir = self::dir((int) $master->id);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException('cannot create '.$dir);
        }

        return DB::transaction(function () use ($master, $currentPath, $newFile, $major, $note, $newName, $dir) {
            $current = DB::table(self::TABLE)->where('digital_object_id', $master->id)->where('is_current', 1)->lockForUpdate()->first();
            if (! $current) {
                // The original becomes 1.0.
                $id = DB::table(self::TABLE)->insertGetId(self::row($master, $currentPath, 1, 0, __('Original'), (int) ($master->created_by ?? 0) ?: null) + ['is_current' => 1]);
                $current = DB::table(self::TABLE)->where('id', $id)->first();
            }
            // Keep the outgoing file.
            $kept = $dir.'/v'.$current->version_major.'.'.$current->version_minor.'-'.basename($currentPath);
            if (! @copy($currentPath, $kept)) {
                throw new \RuntimeException('cannot keep '.$currentPath);
            }
            DB::table(self::TABLE)->where('id', $current->id)->update(['is_current' => 0, 'stored_path' => $kept]);

            [$maj, $min] = $major ? [$current->version_major + 1, 0] : [$current->version_major, $current->version_minor + 1];
            if (! @copy($newFile, $currentPath)) {
                throw new \RuntimeException('cannot write '.$currentPath);
            }
            clearstatcache(true, $currentPath);
            $id = DB::table(self::TABLE)->insertGetId(self::row($master, $currentPath, $maj, $min, $note, auth()->id(), $newName) + ['is_current' => 1]);
            DB::table('digital_object')->where('id', $master->id)->update([
                'byte_size' => filesize($currentPath),
                'mime_type' => (string) (@mime_content_type($currentPath) ?: $master->mime_type),
                'checksum' => hash_file('sha256', $currentPath),
                'checksum_type' => 'sha256',
            ]);
            self::premis($master, 'replacement', 'Master replaced: version '.$maj.'.'.$min.' (was '.$current->version_major.'.'.$current->version_minor.')');

            return DB::table(self::TABLE)->where('id', $id)->first();
        });
    }

    /** Make an older version current again (as a new version, so nothing is lost). */
    public static function restore(int $versionId): ?object
    {
        $v = DB::table(self::TABLE)->where('id', $versionId)->first();
        $master = $v ? DB::table('digital_object')->where('id', $v->digital_object_id)->first() : null;
        $path = $master ? DigitalObjectService::resolveDiskPath($master) : null;
        if (! $v || $v->is_current || ! $v->stored_path || ! is_file($v->stored_path) || ! $path) {
            return null;
        }

        return self::replace($master, $path, $v->stored_path, false, __('Restored version :v', ['v' => $v->version_major.'.'.$v->version_minor]), $v->name);
    }

    private static function row(object $master, string $path, int $maj, int $min, ?string $note, ?int $by, ?string $name = null): array
    {
        return [
            'digital_object_id' => (int) $master->id,
            'version_major' => $maj,
            'version_minor' => $min,
            'name' => mb_substr($name ?? basename($path), 0, 500),
            'mime_type' => (string) (@mime_content_type($path) ?: ($master->mime_type ?? '')),
            'byte_size' => (int) @filesize($path),
            'sha256' => @hash_file('sha256', $path) ?: null,
            'note' => $note !== null ? mb_substr($note, 0, 1000) : null,
            'created_by' => $by,
            'created_at' => now(),
        ];
    }

    private static function premis(object $master, string $type, string $detail): void
    {
        if (! class_exists(\AhgPreservation\Services\PreservationService::class)) {
            return;
        }
        try {
            app(\AhgPreservation\Services\PreservationService::class)->logEvent((int) $master->id, ($master->object_id ?? null) ? (int) $master->object_id : null, $type, $detail, 'success');
        } catch (\Throwable) {
            // an event is a record of the change, not a condition for it
        }
    }
}
