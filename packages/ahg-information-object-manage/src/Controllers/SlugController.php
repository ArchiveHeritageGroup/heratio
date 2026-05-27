<?php

/**
 * SlugController - AJAX live slug preview for IO create/edit forms.
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
 * Migrated from PSIS atom-ahg-plugins/ahgInformationObjectManagePlugin/lib/Action/
 * slugPreviewAction.class.php (issue #742).
 *
 * Returns the slug that would be assigned if the user saved the IO with
 * the supplied title right now. Honours the conflict resolution applied
 * by InformationObjectController::store() / ::renameUpdate() so the live
 * preview matches the eventually-persisted value.
 *
 * Lock note: this controller is NEVER invoked from the show.blade.php
 * render path - only from create.blade.php / rename.blade.php /
 * edit.blade.php via XHR. Sibling to the existing TreeviewController.
 */

namespace AhgInformationObjectManage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SlugController extends Controller
{
    /**
     * GET /informationobject/slug-preview?title=...&id=...
     *
     * Returns: { "slug": "my-slug", "conflict": false, "fallback": false }
     *
     * - id (optional): the current IO id, so the lookup excludes the
     *   record itself when checking for conflicts (otherwise rename
     *   to a near-identical title would always report a conflict).
     */
    public function preview(Request $request): JsonResponse
    {
        $title = trim((string) $request->get('title', ''));
        $ownId = (int) $request->get('id', 0);

        if ($title === '') {
            return response()->json([
                'slug' => '',
                'conflict' => false,
                'fallback' => false,
            ]);
        }

        $base = Str::slug($title);
        $fallback = false;
        if ($base === '') {
            // Pure non-ASCII title - mirror the controller's "record-{id}"
            // sentinel so the preview matches what would be persisted.
            $base = $ownId > 0 ? 'record-' . $ownId : 'untitled';
            $fallback = true;
        }

        $slug = $this->resolveUnique($base, $ownId);

        return response()->json([
            'slug' => $slug,
            'conflict' => ($slug !== $base),
            'fallback' => $fallback,
        ]);
    }

    /**
     * Append -2, -3, ... until the slug is unique. Mirrors the loop in
     * InformationObjectController::store() / ::renameUpdate().
     */
    private function resolveUnique(string $base, int $ownId): string
    {
        $candidate = $base;
        $n = 2;
        while (true) {
            $query = DB::table('slug')->where('slug', $candidate);
            if ($ownId > 0) {
                $query->where('object_id', '!=', $ownId);
            }

            if (!$query->exists()) {
                return $candidate;
            }

            $candidate = $base . '-' . $n;
            $n++;

            // Defensive ceiling - if 100 collisions in a row, just
            // return the candidate; the persisting controller will
            // hash-suffix it.
            if ($n > 100) {
                return $candidate;
            }
        }
    }
}
