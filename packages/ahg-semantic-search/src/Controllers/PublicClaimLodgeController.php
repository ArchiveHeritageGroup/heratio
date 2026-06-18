<?php

/**
 * PublicClaimLodgeController - Heratio
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

namespace AhgSemanticSearch\Controllers;

use AhgSemanticSearch\Services\DisplacedHeritageService;
use AhgSemanticSearch\Services\RepatriationClaimService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * #1207 self-service public claim lodging. Lets an origin community (or their
 * representative) lodge a repatriation claim DIRECTLY about an object in the
 * public displaced-heritage register, without any staff account. Item-scoped:
 * you reach this form from a traced object, so a claim always attaches to a real
 * register entry. The submission lands as a normal 'registered' claim with NO
 * staff author (created_by = null) and a notes marker flagging its public origin,
 * which fires the standard staff notification (RepatriationClaimService::register
 * -> RepatriationNotifier) so staff review it. Spam is held off with a honeypot,
 * a minimum-dwell check and route-level throttling - no third-party captcha and
 * no account required, which is the whole point of "before any staff account
 * exists". Tone is neutral throughout: a claim is a documented request and its
 * status, never a legal determination.
 */
class PublicClaimLodgeController extends Controller
{
    public function __construct(
        private RepatriationClaimService $service = new RepatriationClaimService,
    ) {}

    /**
     * Public lodging form for one traced object. The object must currently be in
     * the displaced-heritage register; otherwise we send the visitor back to the
     * register rather than let them lodge against an unknown id.
     */
    public function form(Request $request, int $item)
    {
        if (! $this->service->available()) {
            abort(404);
        }

        $traced = $this->tracedItem($item);
        if ($traced === null) {
            return redirect()
                ->route('displaced-heritage.index')
                ->with('error', __('That object is not currently in the displaced-heritage register, so a claim cannot be lodged against it here.'));
        }

        return view('ahg-semantic-search::repatriation.lodge', [
            'item' => $item,
            'traced' => $traced,
            'originPlace' => (string) ($traced['origin']['value'] ?? $traced['origin_region'] ?? ''),
            'currentHolder' => (string) ($traced['holding']['value'] ?? $traced['holding_region'] ?? ''),
            'title' => (string) ($traced['title'] ?? __('this object')),
            'disclaimer' => RepatriationClaimService::DISCLAIMER,
            // Server render time for the minimum-dwell anti-bot check.
            'renderedAt' => time(),
        ]);
    }

    /**
     * Handle a public submission. Accept-and-drop on a tripped honeypot / too-fast
     * submission (never coach a bot with an error); otherwise validate and register.
     */
    public function submit(Request $request, int $item): RedirectResponse
    {
        if (! $this->service->available()) {
            abort(404);
        }

        // Honeypot: a hidden "website" field real users never fill.
        if (trim((string) $request->input('website', '')) !== '') {
            return redirect()->route('repatriation.lodge.thanks');
        }

        // Minimum-dwell: a submission that arrives implausibly fast is automated.
        $renderedAt = (int) $request->input('rendered_at', 0);
        if ($renderedAt > 0 && (time() - $renderedAt) < 3) {
            return redirect()->route('repatriation.lodge.thanks');
        }

        $traced = $this->tracedItem($item);
        if ($traced === null) {
            return redirect()
                ->route('displaced-heritage.index')
                ->with('error', __('That object is not currently in the displaced-heritage register, so a claim cannot be lodged against it here.'));
        }

        $validated = $request->validate([
            'claimant_community' => ['required', 'string', 'max:512'],
            'claimant_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'origin_place' => ['nullable', 'string', 'max:512'],
            'evidence_summary' => ['nullable', 'string', 'max:20000'],
            'consent' => ['accepted'],
        ]);

        // Compose a contact string the notifier can read an email out of, while
        // keeping the submitter's self-identified name when given.
        $name = trim((string) ($validated['claimant_name'] ?? ''));
        $contact = $name !== ''
            ? $name.' <'.$validated['contact_email'].'>'
            : (string) $validated['contact_email'];

        $marker = '[Public self-service submission lodged via the displaced-heritage register on '
            .now()->toDateString().'. Submitter contact: '.$contact.'. Pending staff review.]';

        $data = [
            'item_ref' => $item,
            'claimant_community' => $validated['claimant_community'],
            'origin_place' => $validated['origin_place']
                ?? (string) ($traced['origin']['value'] ?? $traced['origin_region'] ?? ''),
            'current_holder' => (string) ($traced['holding']['value'] ?? $traced['holding_region'] ?? ''),
            'claim_status' => 'registered',
            'evidence_summary' => $validated['evidence_summary'] ?? null,
            'contact' => $contact,
            'notes' => $marker,
        ];

        // created_by = null: a public submission has no staff author. This (plus
        // the notes marker) is how staff tell a self-lodged claim from one they
        // registered. register() fires the staff notification + a claimant receipt.
        $id = $this->service->register($data, null);

        if ($id === null) {
            return back()
                ->withInput()
                ->with('error', __('Sorry, your claim could not be recorded just now. Please try again shortly.'));
        }

        return redirect()->route('repatriation.lodge.thanks');
    }

    /** Confirmation page after a successful submission. */
    public function thanks()
    {
        return view('ahg-semantic-search::repatriation.lodge-thanks', [
            'disclaimer' => RepatriationClaimService::DISCLAIMER,
        ]);
    }

    /**
     * Read-only lookup of one traced item from the displaced-heritage register.
     * Returns null when the item is not currently traced. Never throws.
     *
     * @return array<string,mixed>|null
     */
    private function tracedItem(int $itemRef): ?array
    {
        if ($itemRef <= 0) {
            return null;
        }

        try {
            $report = (new DisplacedHeritageService)->scan(['limit' => 0]);
            $records = is_array($report['records'] ?? null) ? $report['records'] : [];
            foreach ($records as $r) {
                if ((int) ($r['id'] ?? 0) === $itemRef) {
                    return $r;
                }
            }
        } catch (\Throwable $e) {
            Log::info('[repatriation] public lodge traced-item lookup failed for '.$itemRef.': '.$e->getMessage());
        }

        return null;
    }
}
