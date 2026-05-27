<?php

/**
 * TranslatePermissionController - Controller for Heratio
 *
 * Issue #744 - Admin translate ACL matrix page.
 *
 * PSIS parity surface: /admin/translatePermission. PSIS only ever served a
 * "you don't have permission" placeholder here; Heratio implements the real
 * matrix (rows = ACL groups, columns = enabled locales). A ticked cell stores
 * a grant in acl_permission with action='translate' and constants={"language":
 * "<code>"} (one row per (group, locale) pair), keeping the canonical
 * single-row group-level 'translate' grant intact when present.
 *
 * Copyright (C) 2026 Plain Sailing Information Systems
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

namespace AhgAcl\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TranslatePermissionController extends Controller
{
    /**
     * Fallback locale set when no i18n_languages setting is configured yet.
     * Covers the language codes that ship in the bundled translation files.
     */
    public const DEFAULT_LOCALES = [
        'en' => 'English',
        'fr' => 'French',
        'es' => 'Spanish',
        'de' => 'German',
        'it' => 'Italian',
        'pt' => 'Portuguese',
        'nl' => 'Dutch',
        'af' => 'Afrikaans',
    ];

    /**
     * GET /admin/translate-permissions - render the matrix.
     */
    public function index(Request $request)
    {
        $groups = $this->getGroups();
        $locales = $this->getLocales();
        $matrix = $this->buildMatrix($groups, $locales);

        return view('ahg-acl::translate-permissions', [
            'groups' => $groups,
            'locales' => $locales,
            'matrix' => $matrix,
        ]);
    }

    /**
     * POST /admin/translate-permissions - toggle a single cell.
     *
     * Body: { group_id: int, locale: string, grant: bool }
     * Returns: { ok: true, granted: bool }
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'group_id' => 'required|integer|exists:acl_group,id',
            'locale' => 'required|string|max:8|regex:/^[a-z]{2,3}(_[A-Z]{2})?$/',
            'grant' => 'required|boolean',
        ]);

        $now = now()->toDateTimeString();
        $constants = json_encode(['language' => $data['locale']]);

        $existing = DB::table('acl_permission')
            ->where('group_id', $data['group_id'])
            ->whereNull('object_id')
            ->where('action', 'translate')
            ->where('constants', $constants)
            ->first();

        if ($data['grant']) {
            if (! $existing) {
                DB::table('acl_permission')->insert([
                    'group_id' => $data['group_id'],
                    'object_id' => null,
                    'action' => 'translate',
                    'grant_deny' => 1,
                    'constants' => $constants,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'serial_number' => 0,
                ]);
            } else {
                DB::table('acl_permission')->where('id', $existing->id)->update([
                    'grant_deny' => 1,
                    'updated_at' => $now,
                ]);
            }
        } else {
            if ($existing) {
                DB::table('acl_permission')->where('id', $existing->id)->delete();
            }
        }

        return response()->json([
            'ok' => true,
            'granted' => (bool) $data['grant'],
        ]);
    }

    /**
     * Get all ACL groups for the matrix rows.
     */
    private function getGroups(): \Illuminate\Support\Collection
    {
        return DB::table('acl_group as g')
            ->leftJoin('acl_group_i18n as gi', function ($j) {
                $j->on('gi.id', '=', 'g.id')->where('gi.culture', '=', 'en');
            })
            ->select('g.id', 'gi.name')
            ->orderBy('gi.name')
            ->get();
    }

    /**
     * Look up enabled locales from the i18n_languages setting; fall back to
     * the DEFAULT_LOCALES list if it's missing or empty. Returns an
     * associative array keyed by language code.
     */
    private function getLocales(): array
    {
        $value = DB::table('setting as s')
            ->leftJoin('setting_i18n as si', function ($j) {
                $j->on('si.id', '=', 's.id')->where('si.culture', '=', 'en');
            })
            ->where('s.name', 'i18n_languages')
            ->value('si.value');

        $codes = $value ? json_decode($value, true) : null;
        if (! is_array($codes) || empty($codes)) {
            return self::DEFAULT_LOCALES;
        }

        $out = [];
        foreach ($codes as $code) {
            $out[$code] = self::DEFAULT_LOCALES[$code] ?? strtoupper($code);
        }

        return $out;
    }

    /**
     * Build a [group_id][locale] => bool lookup of existing translate-language
     * grants so the view can pre-tick the matrix in a single query.
     */
    private function buildMatrix(\Illuminate\Support\Collection $groups, array $locales): array
    {
        $groupIds = $groups->pluck('id')->all();
        if (empty($groupIds)) {
            return [];
        }

        $rows = DB::table('acl_permission')
            ->whereIn('group_id', $groupIds)
            ->whereNull('object_id')
            ->where('action', 'translate')
            ->where('grant_deny', 1)
            ->whereNotNull('constants')
            ->get(['group_id', 'constants']);

        $out = [];
        foreach ($rows as $r) {
            $decoded = json_decode($r->constants ?? '', true);
            if (! is_array($decoded) || ! isset($decoded['language'])) {
                continue;
            }
            $code = $decoded['language'];
            if (! array_key_exists($code, $locales)) {
                continue;
            }
            $out[$r->group_id][$code] = true;
        }

        return $out;
    }
}
