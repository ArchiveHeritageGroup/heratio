<?php

/**
 * AnnotationTargetResolver - Service for Heratio
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
 */

namespace AhgAnnotations\Services;

use AhgCore\Services\AttachedDigitalObjectService;
use Illuminate\Support\Facades\DB;

/**
 * Map an annotation target (canvas) IRI back to the information object it
 * is drawn on. Three canvas shapes exist:
 *
 *   1. {baseUrl}/iiif-manifest/{slug}/canvas/{n}   - server-built manifests
 *      (IiifCollectionService::buildSingleCanvasV3).
 *   2. {imageUrl}/canvas/{n}                        - the inline manifests the
 *      record viewer builds (ahg-iiif-viewer.js), where imageUrl is a
 *      digital_object file URL (/uploads/...: master, reference or
 *      thumbnail, primary or attached).
 *   3. {baseUrl}/privacy/redacted-asset/{slug}[/{do}]/canvas/{n} - the burnt-in
 *      derivative a non-admin sees for an image with redaction regions.
 *
 * Returns null when nothing resolves; callers must fail safe.
 */
class AnnotationTargetResolver
{
    public static function resolveIoId(string $iri): ?int
    {
        if ($iri === '') {
            return null;
        }

        // Drop selector fragment (#xywh=...) and query string before matching.
        $path = parse_url($iri, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            $path = $iri;
        }

        // Shapes 1 and 3 carry the record slug. Delimiter is ~ because the
        // character class contains a literal '#'.
        if (preg_match('~/(?:iiif-manifest|privacy/redacted-asset)/([^/?#]+)~', $path, $m)) {
            $slug = rawurldecode($m[1]);
            if ($slug === '') {
                return null;
            }
            $objectId = DB::table('slug')->where('slug', $slug)->value('object_id');

            return $objectId ? (int) $objectId : null;
        }

        // Shape 2: a file URL with /canvas/{n} appended.
        $file = preg_replace('~/canvas/[^/]*$~', '', $path);
        if ($file === null || $file === $path) {
            return null;
        }
        $file = rawurldecode($file);
        $name = basename($file);
        $dir = rtrim(dirname($file), '/');
        if ($name === '' || $dir === '' || $dir === '.') {
            return null;
        }

        $do = DB::table('digital_object')
            ->whereIn('path', [$dir.'/', $dir])
            ->where('name', $name)
            ->first(['id', 'object_id', 'parent_id']);
        if (! $do) {
            return null;
        }

        // A derivative (reference / thumbnail) belongs to its master.
        $master = $do->parent_id
            ? DB::table('digital_object')->where('id', $do->parent_id)->first(['id', 'object_id'])
            : $do;
        if (! $master) {
            return null;
        }

        if ($master->object_id
            && DB::table('information_object')->where('id', $master->object_id)->exists()) {
            return (int) $master->object_id;
        }

        // Attached (additional) objects have no object_id; the link table
        // names the record they belong to.
        if (AttachedDigitalObjectService::available()) {
            $ioId = DB::table(AttachedDigitalObjectService::TABLE)
                ->where('digital_object_id', $master->id)
                ->value('information_object_id');

            return $ioId ? (int) $ioId : null;
        }

        return null;
    }
}
