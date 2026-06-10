<?php

/**
 * ComplianceAutopilotController - Heratio ahg-privacy
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgPrivacy\Controllers;

use AhgPrivacy\Services\Article30Service;
use AhgPrivacy\Services\ComplianceAutopilotService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * heratio#1199 - compliance autopilot. Scan the catalogue for personal data and auto-draft a
 * Records of Processing Activities (Article 30) entry for the DPO to review and save.
 */
class ComplianceAutopilotController extends Controller
{
    public function __construct(
        private ComplianceAutopilotService $autopilot,
        private Article30Service $article30,
    ) {}

    public function index()
    {
        return view('privacy::autopilot');
    }

    /** Run the scan and return categories found + a pre-filled ROPA draft. */
    public function scanAjax()
    {
        $scan = $this->autopilot->scanCatalogue();

        return response()->json(['ok' => true, 'scan' => $scan, 'draft' => $this->autopilot->draftRopa($scan)]);
    }

    /** Create a ROPA entry from the (reviewed) draft. */
    public function createRopa(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'purpose' => 'nullable|string',
            'lawful_basis' => 'nullable|string',
            'categories_of_data' => 'nullable|string',
            'categories_of_subjects' => 'nullable|string',
            'recipients' => 'nullable|string',
            'retention_period' => 'nullable|string',
            'security_measures' => 'nullable|string',
        ]);

        $toList = fn (?string $s) => array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $s))));

        $activity = $this->article30->create([
            'name' => $data['name'],
            'purpose' => $data['purpose'] ?? null,
            'lawful_basis' => $data['lawful_basis'] ?? null,
            'categories_of_data' => $toList($data['categories_of_data'] ?? ''),
            'categories_of_subjects' => $toList($data['categories_of_subjects'] ?? ''),
            'recipients' => $toList($data['recipients'] ?? ''),
            'retention_period' => $data['retention_period'] ?? null,
            'security_measures' => $data['security_measures'] ?? null,
            'transfers_outside_eea' => false,
            'is_active' => true,
        ]);

        return redirect()->route('ahgprivacy.article-30.index')
            ->with('success', 'ROPA entry "'.$activity->name.'" created from the compliance autopilot draft. Review and complete it.');
    }
}
