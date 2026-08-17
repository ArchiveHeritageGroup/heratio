<?php

/**
 * Decide which model-viewer AR modes a page may honestly advertise (#1469).
 *
 * Apple Quick Look - the only AR path on iPhone and iPad - will not open a GLB.
 * It needs a USDZ, handed to model-viewer as `ios-src`. Listing `quick-look` in
 * `ar-modes` without one is worse than omitting it: model-viewer shows the AR
 * button, iOS declines the GLB, and the mode list falls through to `webxr`,
 * which Safari does not implement. The visitor gets a button that does nothing.
 *
 * Five views advertised `webxr scene-viewer quick-look` unconditionally while no
 * USDZ was ever supplied, so AR worked on Android and silently failed on iOS.
 *
 * There is no GLB -> USDZ conversion on this host - that needs Apple's tooling -
 * but a USDZ can be UPLOADED alongside the GLB, which the uploader, the 3D
 * registry and MediaDerivativeService already accept. So the rule is: advertise
 * quick-look only when a USDZ backs it, and pass that file as `ios-src`.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio, licensed under the GNU AGPL v3 or later.
 */

namespace AhgCore\Support;

use AhgCore\Services\DigitalObjectService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ArModes
{
    /** Android/WebXR modes, always safe to advertise. */
    public const BASE = 'webxr scene-viewer';

    /** With a USDZ behind it, iOS Quick Look becomes real. */
    public const WITH_IOS = 'webxr scene-viewer quick-look';

    /**
     * The URL of a USDZ attached to this information object, if one exists.
     *
     * Returns null on any failure - a missing table, an unreadable row, no USDZ.
     * A page must never 500 over an optional AR extra.
     */
    public static function usdzUrl(?int $informationObjectId): ?string
    {
        if (! $informationObjectId) {
            return null;
        }

        try {
            if (! Schema::hasTable('digital_object')) {
                return null;
            }

            // The link column is `object_id`, NOT `information_object_id` - there is
            // no such column. Digital objects are a CTI child of `object`, and
            // object_id points at the description they belong to. A derivative
            // hangs off its master via parent_id, so a master-level USDZ has
            // parent_id NULL; either is acceptable here, newest first.
            $row = DB::table('digital_object')
                ->where('object_id', $informationObjectId)
                ->where('name', 'like', '%.usdz')
                ->orderByDesc('id')
                ->first();

            return $row ? DigitalObjectService::getUrl($row) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * A USDZ sitting beside a 3D model's own file, e.g. `scene.glb` -> `scene.usdz`.
     *
     * The `object_3d_model` rows carry an uploads-relative `file_path` rather than
     * a digital_object id, so this is the sibling-file equivalent of usdzUrl().
     */
    public static function usdzBesideFile(?string $uploadsRelativePath): ?string
    {
        $p = trim((string) $uploadsRelativePath);
        if ($p === '') {
            return null;
        }

        $candidate = preg_replace('/\.(glb|gltf)$/i', '.usdz', $p);
        if ($candidate === null || $candidate === $p) {
            return null;   // not a GLB/GLTF path, so there is no sibling to look for
        }

        // `object_3d_model.file_path` is stored inconsistently: some rows carry a
        // leading `/uploads/`, others are already relative to it (`r/kenya/...`).
        // Normalise to uploads-relative so this does not build `uploads/uploads/...`.
        $rel = ltrim($candidate, '/');
        if (str_starts_with($rel, 'uploads/')) {
            $rel = substr($rel, 8);
        }

        $base = rtrim((string) config('heratio.uploads_path', config('heratio.storage_path').'/uploads'), '/');
        if (! is_file($base.'/'.$rel)) {
            return null;
        }

        return '/uploads/'.$rel;
    }

    /** The ar-modes attribute value: quick-look only when a USDZ backs it. */
    public static function modes(?string $usdzUrl): string
    {
        return $usdzUrl ? self::WITH_IOS : self::BASE;
    }
}
