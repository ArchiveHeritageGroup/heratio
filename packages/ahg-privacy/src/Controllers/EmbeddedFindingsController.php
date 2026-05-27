<?php

/**
 * EmbeddedFindingsController - admin UI for ahg_pii_finding_embedded rows.
 *
 * Heratio Issue #751. Lists PII findings detected over embedded image
 * metadata (EXIF / IPTC / XMP). Supports filtering by pii_type and
 * resolution_status, plus per-row resolution actions (redact / clear /
 * escalate).
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

declare(strict_types=1);

namespace AhgPrivacy\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmbeddedFindingsController extends Controller
{
    /** Allowed resolution_status values for the update action. */
    private const ALLOWED_RESOLUTIONS = ['pending', 'redacted', 'cleared', 'escalated'];

    /**
     * GET /admin/privacy/embedded-findings - list + filter findings.
     */
    public function index(Request $request)
    {
        if (! Schema::hasTable('ahg_pii_finding_embedded')) {
            return view('privacy::embedded-findings', [
                'findings'     => collect(),
                'pagination'   => null,
                'filterType'   => '',
                'filterStatus' => '',
                'piiTypes'     => $this->loadDropdown('pii_type_embedded'),
                'resolutions'  => $this->loadDropdown('pii_resolution'),
                'summary'      => $this->emptySummary(),
                'schemaReady'  => false,
            ]);
        }

        $filterType   = (string) $request->input('pii_type', '');
        $filterStatus = (string) $request->input('resolution_status', '');

        $query = DB::table('ahg_pii_finding_embedded as f')
            ->leftJoin('digital_object as d', 'd.id', '=', 'f.digital_object_id')
            ->select(
                'f.*',
                'd.name as digital_object_name',
                'd.object_id as information_object_id'
            )
            ->orderByDesc('f.scanned_at');

        if ($filterType !== '') {
            $query->where('f.pii_type', $filterType);
        }
        if ($filterStatus !== '') {
            $query->where('f.resolution_status', $filterStatus);
        }

        $pagination = $query->paginate(50)->appends($request->query());

        return view('privacy::embedded-findings', [
            'findings'     => $pagination->getCollection(),
            'pagination'   => $pagination,
            'filterType'   => $filterType,
            'filterStatus' => $filterStatus,
            'piiTypes'     => $this->loadDropdown('pii_type_embedded'),
            'resolutions'  => $this->loadDropdown('pii_resolution'),
            'summary'      => $this->summary(),
            'schemaReady'  => true,
        ]);
    }

    /**
     * POST /admin/privacy/embedded-findings/{id}/resolve - mark a finding
     * with one of pending / redacted / cleared / escalated.
     */
    public function resolve(Request $request, int $id)
    {
        if (! Schema::hasTable('ahg_pii_finding_embedded')) {
            return redirect()->route('ahgprivacy.embedded-findings.index')
                ->with('error', __('ahg_pii_finding_embedded table is not installed yet.'));
        }

        $validated = $request->validate([
            'resolution_status' => 'required|string|in:'.implode(',', self::ALLOWED_RESOLUTIONS),
            'notes'             => 'nullable|string|max:4000',
        ]);

        $now = date('Y-m-d H:i:s');
        $userId = Auth::id();

        $updated = DB::table('ahg_pii_finding_embedded')
            ->where('id', $id)
            ->update([
                'resolution_status'  => $validated['resolution_status'],
                'notes'              => $validated['notes'] ?? null,
                'resolved_at'        => $validated['resolution_status'] === 'pending' ? null : $now,
                'resolved_by_user_id' => $validated['resolution_status'] === 'pending' ? null : $userId,
                'updated_at'         => $now,
            ]);

        if ($updated === 0) {
            return redirect()->route('ahgprivacy.embedded-findings.index')
                ->with('error', __('Finding not found.'));
        }

        return redirect()->route('ahgprivacy.embedded-findings.index')
            ->with('success', __('Finding marked as :status.', [
                'status' => $validated['resolution_status'],
            ]));
    }

    /**
     * Load a dropdown taxonomy from ahg_dropdown. Returns an array of
     * (code, label) pairs sorted by sort_order.
     *
     * @return array<int,array{code:string,label:string}>
     */
    private function loadDropdown(string $taxonomy): array
    {
        if (! Schema::hasTable('ahg_dropdown')) {
            return [];
        }
        return DB::table('ahg_dropdown')
            ->where('taxonomy', $taxonomy)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get(['code', 'label'])
            ->map(fn ($r) => ['code' => $r->code, 'label' => $r->label])
            ->all();
    }

    /**
     * Build the per-pii-type summary card data for the dashboard strip at
     * the top of the listing page.
     *
     * @return array<int,array{pii_type:string,pending:int,resolved:int,total:int}>
     */
    private function summary(): array
    {
        $rows = DB::table('ahg_pii_finding_embedded')
            ->select(
                'pii_type',
                DB::raw("SUM(CASE WHEN resolution_status = 'pending' THEN 1 ELSE 0 END) AS pending"),
                DB::raw("SUM(CASE WHEN resolution_status <> 'pending' THEN 1 ELSE 0 END) AS resolved"),
                DB::raw('COUNT(*) AS total')
            )
            ->groupBy('pii_type')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'pii_type' => (string) $r->pii_type,
                'pending'  => (int) $r->pending,
                'resolved' => (int) $r->resolved,
                'total'    => (int) $r->total,
            ];
        }
        return $out;
    }

    /**
     * Empty summary shape used when the Phase 2 schema isn't installed yet.
     *
     * @return array<int,array<string,mixed>>
     */
    private function emptySummary(): array
    {
        return [];
    }
}
