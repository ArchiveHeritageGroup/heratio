<?php

/**
 * MasterAccess - Heratio ahg-core
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1748 / heratio#1762: "Protect originals". When the protect_masters
 * setting is on, the original (master) of an image or PDF is served only to
 *  - staff (administrators, editors, contributors),
 *  - users granted Read Master on the record or its repository, or
 *  - a user whose access request covering the record was approved and has
 *    not expired (access_request + access_request_scope, descendants included
 *    when the request says so).
 * Everyone else gets the preview: the reference copy and thumbnail.
 *
 * ponytail: video, audio and 3D masters are not protected - their players
 * need the original. Lower-quality renditions for those are heratio#1755.
 */
class MasterAccess
{
    public const SETTING = 'protect_masters';

    private static array $memo = [];

    public static function enabled(): bool
    {
        return in_array(strtolower((string) AhgSettingsService::get(self::SETTING, '0')), ['1', 'true', 'on', 'yes'], true);
    }

    /** Is this master of a type the setting protects (images and PDFs)? */
    public static function protects(?object $master): bool
    {
        $mime = strtolower((string) ($master->mime_type ?? ''));

        return str_starts_with($mime, 'image/') || $mime === 'application/pdf';
    }

    /**
     * May the current (or given) user fetch the original of record $ioId?
     * Answers for the protected case; callers check enabled()/protects() first.
     */
    public static function mayFetch(int $ioId, ?object $user = null): bool
    {
        $user = $user ?? auth()->user();
        if (! $user || $ioId <= 1) {
            return false;
        }
        $key = ($user->id ?? 0).':'.$ioId;
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }

        $ok = AclService::isAdministrator($user) || AclService::isEditor($user) || AclService::isContributor($user)
            || AclService::check((object) ['id' => $ioId], 'readMaster', $user)
            || self::releasedTo((int) $user->id, $ioId);

        return self::$memo[$key] = $ok;
    }

    /**
     * The record page's rule for handing a viewer the original: unchanged
     * (signed in) unless protection applies to this master.
     */
    public static function viewerMaySeeOriginal(int $ioId, ?object $master): bool
    {
        if (! auth()->check()) {
            return false;
        }
        if (! self::enabled() || ! self::protects($master)) {
            return true;
        }

        return self::mayFetch($ioId);
    }

    /** True when the viewer is held to the preview because of this setting. */
    public static function previewOnly(int $ioId, ?object $master): bool
    {
        return $master && self::enabled() && self::protects($master) && ! self::mayFetch($ioId);
    }

    /** Has $userId an approved, unexpired access request covering $ioId? */
    public static function releasedTo(int $userId, int $ioId): bool
    {
        if (! Schema::hasTable('access_request') || ! Schema::hasTable('access_request_scope')) {
            return false;
        }
        $ancestors = DB::table('information_object as io')
            ->join('information_object as anc', function ($j) {
                $j->whereColumn('anc.lft', '<=', 'io.lft')->whereColumn('anc.rgt', '>=', 'io.rgt');
            })
            ->where('io.id', $ioId)->where('anc.id', '!=', $ioId)
            ->pluck('anc.id')->map('intval')->all();

        return DB::table('access_request as ar')
            ->join('access_request_scope as s', 's.request_id', '=', 'ar.id')
            ->where('ar.user_id', $userId)
            ->where('ar.status', 'approved')
            ->where(fn ($q) => $q->whereNull('ar.expires_at')->orWhere('ar.expires_at', '>', now()))
            ->where('s.object_type', 'information_object')
            ->where(function ($q) use ($ioId, $ancestors) {
                $q->where('s.object_id', $ioId);
                if ($ancestors) {
                    $q->orWhere(fn ($w) => $w->where('s.include_descendants', 1)->whereIn('s.object_id', $ancestors));
                }
            })
            ->exists();
    }

    /**
     * The master digital object a /uploads path or IIIF identifier points at,
     * or null when it is a derivative, an unknown file, or not tied to a record.
     */
    public static function masterForPath(string $path): ?object
    {
        $path = '/'.ltrim(str_replace('_SL_', '/', rawurldecode(strtok($path, '?'))), '/');
        $name = basename($path);
        $dir = rtrim(dirname($path), '/').'/';
        if ($name === '' || ! Schema::hasTable('digital_object')) {
            return null;
        }

        return DB::table('digital_object')
            ->whereNull('parent_id')->whereNotNull('object_id')
            ->where('name', $name)
            ->where('path', $dir)
            ->select('id', 'object_id', 'mime_type', 'name', 'path')
            ->first();
    }

    /** Gate check for a file request: should this path be refused? */
    public static function refusesPath(string $path): bool
    {
        if ($path === '' || ! self::enabled()) {
            return false;
        }
        $master = self::masterForPath($path);

        return $master !== null && self::protects($master) && ! self::mayFetch((int) $master->object_id);
    }

    public static function forget(): void
    {
        self::$memo = [];
    }
}
