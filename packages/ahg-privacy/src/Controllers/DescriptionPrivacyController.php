<?php

/**
 * DescriptionPrivacyController - admin management of field-level redaction
 * profiles on archival descriptions (#1108): view the privacy panel, set the
 * profile reason/status, add/remove per-field redactions. Every change is
 * audit-logged with field + legal basis.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems. AGPL-3.0-or-later.
 */

declare(strict_types=1);

namespace AhgPrivacy\Controllers;

use AhgPrivacy\Models\InformationObjectPrivacy;
use AhgPrivacy\Models\InformationObjectPrivacyField;
use AhgPrivacy\Models\PrivacyReason;
use AhgPrivacy\Services\PrivacyRedactionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DescriptionPrivacyController extends Controller
{
    public function __construct(private PrivacyRedactionService $service) {}

    /** Privacy panel for an IO (admin). */
    public function panel(int $id)
    {
        $io = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i', function ($j) {
                $j->on('i.id', '=', 'io.id')->where('i.culture', '=', 'en');
            })
            ->where('io.id', $id)
            ->select('io.id', 'i.title')
            ->first();
        abort_if(! $io, 404);

        $profile = $this->service->getPrivacyProfile($id);
        $activeDsar = DB::table('privacy_dsar')->where('status', 'processing')->exists();

        return view('privacy::description-privacy-panel', [
            'io' => $io,
            'profile' => $profile,
            'reasons' => PrivacyReason::orderBy('sort_order')->get(),
            'redactableFields' => $this->redactableFields(),
            'patterns' => ['email_partial', 'phone_partial', 'id_last4', 'year_only'],
            'activeDsar' => $activeDsar,
        ]);
    }

    /** Create/update the IO privacy profile (reason, status, legal basis). */
    public function saveProfile(Request $request, int $id)
    {
        $data = $request->validate([
            'privacy_reason_id' => 'required|integer|exists:privacy_reason,id',
            'redaction_status' => 'required|in:none,partial,full,pending',
            'legal_basis_reference' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
        ]);

        $profile = InformationObjectPrivacy::updateOrCreate(
            ['information_object_id' => $id],
            $data + ['applied_by' => auth()->id(), 'applied_at' => now()]
        );

        $this->service->logAccess($id, (int) auth()->id(), 'profile_saved', null, $data['legal_basis_reference'] ?? null);

        return back()->with('success', 'Privacy profile saved for description #' . $id . '.');
    }

    /** Add a per-field redaction to the profile. */
    public function addField(Request $request, int $id)
    {
        $data = $request->validate([
            'field_name' => 'required|string|max:100',
            'redaction_type' => 'required|in:full,partial,pseudonymised',
            'redaction_pattern' => 'nullable|string|max:100',
            'reason' => 'required|string|max:500',
            'is_sensitive' => 'nullable|boolean',
        ]);

        $profile = InformationObjectPrivacy::firstOrCreate(
            ['information_object_id' => $id],
            ['privacy_reason_id' => 1, 'redaction_status' => 'partial', 'applied_by' => auth()->id(), 'applied_at' => now()]
        );

        $field = InformationObjectPrivacyField::updateOrCreate(
            ['privacy_id' => $profile->id, 'field_name' => $data['field_name']],
            [
                'redaction_type' => $data['redaction_type'],
                'redaction_pattern' => $data['redaction_pattern'] ?? null,
                'reason' => $data['reason'],
                'is_sensitive' => (bool) ($data['is_sensitive'] ?? false),
            ]
        );

        $this->service->logAccess($id, (int) auth()->id(), 'field_redacted', $data['field_name'], $data['reason'], $field->id);

        return back()->with('success', 'Field "' . $data['field_name'] . '" marked for redaction.');
    }

    /** Remove a per-field redaction. */
    public function removeField(int $id, int $fieldId)
    {
        $field = InformationObjectPrivacyField::find($fieldId);
        if ($field) {
            $name = $field->field_name;
            $field->delete();
            $this->service->logAccess($id, (int) auth()->id(), 'field_unredacted', $name);
        }

        return back()->with('success', 'Field redaction removed.');
    }

    /** Metadata fields that may carry personal data (from the spec). */
    private function redactableFields(): array
    {
        return [
            'creator_birth_date', 'creator_death_date', 'creator_qualifier',
            'subject_occupation', 'subject_biography', 'related_material_note',
            'access_condition', 'scope_and_content', 'archivist_note',
        ];
    }
}
