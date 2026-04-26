<?php

/**
 * IcipController - Controller for Heratio
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



namespace AhgIcip\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IcipController extends Controller
{
    /**
     * Culture for i18n joins
     */
    protected string $culture = 'en';

    // ========================================
    // CONSTANTS
    // ========================================

    // Consent status options
    const CONSENT_STATUSES = [
        'not_required'              => 'Not Required',
        'pending_consultation'      => 'Pending Consultation',
        'consultation_in_progress'  => 'Consultation in Progress',
        'conditional_consent'       => 'Conditional Consent',
        'full_consent'              => 'Full Consent',
        'restricted_consent'        => 'Restricted Consent',
        'denied'                    => 'Denied',
        'unknown'                   => 'Unknown',
    ];

    // Consent scope options
    const CONSENT_SCOPES = [
        'preservation_only' => 'Preservation Only',
        'internal_access'   => 'Internal Access',
        'public_access'     => 'Public Access',
        'reproduction'      => 'Reproduction',
        'commercial_use'    => 'Commercial Use',
        'educational_use'   => 'Educational Use',
        'research_use'      => 'Research Use',
        'full_rights'       => 'Full Rights',
    ];

    // Australian states/territories
    const STATE_TERRITORIES = [
        'NSW'      => 'New South Wales',
        'VIC'      => 'Victoria',
        'QLD'      => 'Queensland',
        'WA'       => 'Western Australia',
        'SA'       => 'South Australia',
        'TAS'      => 'Tasmania',
        'NT'       => 'Northern Territory',
        'ACT'      => 'Australian Capital Territory',
        'External' => 'External Territories',
    ];

    // Restriction types
    const RESTRICTION_TYPES = [
        'community_permission_required' => 'Community Permission Required',
        'gender_restricted_male'        => 'Men Only (Gender Restricted)',
        'gender_restricted_female'      => 'Women Only (Gender Restricted)',
        'initiated_only'                => 'Initiated Persons Only',
        'seasonal'                      => 'Seasonal Restriction',
        'mourning_period'               => 'Mourning Period',
        'repatriation_pending'          => 'Repatriation Pending',
        'under_consultation'            => 'Under Consultation',
        'elder_approval_required'       => 'Elder Approval Required',
        'custom'                        => 'Custom Restriction',
    ];

    // Consultation types
    const CONSULTATION_TYPES = [
        'initial_contact' => 'Initial Contact',
        'consent_request' => 'Consent Request',
        'access_request'  => 'Access Request',
        'repatriation'    => 'Repatriation',
        'digitisation'    => 'Digitisation',
        'exhibition'      => 'Exhibition',
        'publication'     => 'Publication',
        'research'        => 'Research',
        'general'         => 'General',
        'follow_up'       => 'Follow Up',
    ];

    // Consultation methods
    const CONSULTATION_METHODS = [
        'in_person' => 'In Person',
        'phone'     => 'Phone',
        'video'     => 'Video Conference',
        'email'     => 'Email',
        'letter'    => 'Letter',
        'other'     => 'Other',
    ];

    // Consultation statuses
    const CONSULTATION_STATUSES = [
        'scheduled'          => 'Scheduled',
        'completed'          => 'Completed',
        'cancelled'          => 'Cancelled',
        'follow_up_required' => 'Follow Up Required',
    ];

    // ========================================
    // DASHBOARD
    // ========================================

    /**
     * ICIP Dashboard
     */
    public function dashboard()
    {
        if (!Schema::hasTable('icip_community')) {
            return view('icip::dashboard', [
                'stats'                => [],
                'pendingConsultations' => [],
                'expiringConsents'     => [],
                'recentConsultations'  => [],
                'tablesExist'          => false,
            ]);
        }

        $stats = $this->getDashboardStats();
        $pendingConsultations = $this->getPendingConsultation(10);
        $expiringConsents = $this->getExpiringConsents(90);

        $recentConsultations = DB::table('icip_consultation as c')
            ->join('icip_community as com', 'c.community_id', '=', 'com.id')
            ->select(['c.*', 'com.name as community_name'])
            ->orderBy('c.created_at', 'desc')
            ->limit(5)
            ->get();

        return view('icip::dashboard', [
            'stats'                => $stats,
            'pendingConsultations' => $pendingConsultations,
            'expiringConsents'     => $expiringConsents,
            'recentConsultations'  => $recentConsultations,
            'tablesExist'          => true,
        ]);
    }

    // ========================================
    // COMMUNITY MANAGEMENT
    // ========================================

    /**
     * List communities
     */
    public function communities(Request $request)
    {
        if (!Schema::hasTable('icip_community')) {
            return view('icip::communities', [
                'communities' => collect(),
                'states'      => self::STATE_TERRITORIES,
                'filters'     => ['state' => null, 'active_only' => '1', 'search' => null],
                'tablesExist' => false,
            ]);
        }

        $query = DB::table('icip_community')->orderBy('name');

        $state = $request->query('state');
        $activeOnly = $request->query('active_only', '1');
        $search = $request->query('search');

        if ($state) {
            $query->where('state_territory', $state);
        }
        if ($activeOnly === '1') {
            $query->where('is_active', 1);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('language_group', 'like', "%{$search}%")
                    ->orWhere('region', 'like', "%{$search}%");
            });
        }

        $communities = $query->get();

        return view('icip::communities', [
            'communities' => $communities,
            'states'      => self::STATE_TERRITORIES,
            'filters'     => [
                'state'       => $state,
                'active_only' => $activeOnly,
                'search'      => $search,
            ],
            'tablesExist' => true,
        ]);
    }

    /**
     * Add/Edit community form (GET) and save (POST)
     */
    public function communityEdit(Request $request)
    {
        $id = $request->query('id') ?? $request->input('id');
        $community = null;

        if ($id) {
            $community = DB::table('icip_community')->where('id', $id)->first();
            if (!$community) {
                abort(404, 'Community not found');
            }
        }

        if ($request->isMethod('post')) {
            $request->validate([
                'name'             => 'required|string|max:255',
                'state_territory'  => 'required|string|max:47',
            ]);

            $alternateNames = $request->input('alternate_names');
            $data = [
                'name'                       => $request->input('name'),
                'alternate_names'            => $alternateNames ? json_encode(array_filter(array_map('trim', explode(',', $alternateNames)))) : null,
                'language_group'             => $request->input('language_group'),
                'region'                     => $request->input('region'),
                'state_territory'            => $request->input('state_territory'),
                'contact_name'               => $request->input('contact_name'),
                'contact_email'              => $request->input('contact_email'),
                'contact_phone'              => $request->input('contact_phone'),
                'contact_address'            => $request->input('contact_address'),
                'native_title_reference'     => $request->input('native_title_reference'),
                'prescribed_body_corporate'  => $request->input('prescribed_body_corporate'),
                'pbc_contact_email'          => $request->input('pbc_contact_email'),
                'notes'                      => $request->input('notes'),
                'is_active'                  => $request->input('is_active', 0) ? 1 : 0,
                'updated_at'                 => now(),
            ];

            if ($id) {
                DB::table('icip_community')->where('id', $id)->update($data);
                return redirect()->route('ahgicip.communities')->with('notice', 'Community updated successfully.');
            } else {
                $data['created_by'] = auth()->id();
                $data['created_at'] = now();
                DB::table('icip_community')->insert($data);
                return redirect()->route('ahgicip.communities')->with('notice', 'Community created successfully.');
            }
        }

        return view('icip::community-edit', [
            'id'        => $id,
            'community' => $community,
            'states'    => self::STATE_TERRITORIES,
        ]);
    }

    /**
     * View community details
     */
    public function communityView(Request $request)
    {
        $id = $request->query('id');
        $community = DB::table('icip_community')->where('id', $id)->first();

        if (!$community) {
            abort(404, 'Community not found');
        }

        $consents = DB::table('icip_consent as c')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('c.information_object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->where('c.community_id', $id)
            ->select(['c.*', 'ioi.title as object_title'])
            ->orderBy('c.created_at', 'desc')
            ->limit(20)
            ->get();

        $consultations = DB::table('icip_consultation')
            ->where('community_id', $id)
            ->where('is_confidential', 0)
            ->orderBy('consultation_date', 'desc')
            ->limit(20)
            ->get();

        return view('icip::community-view', [
            'id'             => $id,
            'community'      => $community,
            'consents'       => $consents,
            'consultations'  => $consultations,
            'states'         => self::STATE_TERRITORIES,
        ]);
    }

    /**
     * Delete community (POST)
     */
    public function communityDelete(Request $request)
    {
        $id = $request->input('id');

        $linkedConsents = DB::table('icip_consent')->where('community_id', $id)->count();
        $linkedNotices = DB::table('icip_cultural_notice')->where('community_id', $id)->count();

        if ($linkedConsents > 0 || $linkedNotices > 0) {
            return redirect()->route('ahgicip.communities')
                ->with('error', 'Cannot delete community with linked records. Deactivate instead.');
        }

        DB::table('icip_community')->where('id', $id)->delete();

        return redirect()->route('ahgicip.communities')
            ->with('notice', 'Community deleted successfully.');
    }

    // ========================================
    // CONSENT MANAGEMENT
    // ========================================

    /**
     * List consent records
     */
    public function consentList(Request $request)
    {
        if (!Schema::hasTable('icip_consent')) {
            return view('icip::consent-list', [
                'consents'      => collect(),
                'statusOptions' => self::CONSENT_STATUSES,
                'communities'   => collect(),
                'filters'       => ['status' => null, 'community_id' => null],
                'tablesExist'   => false,
            ]);
        }

        $query = DB::table('icip_consent as c')
            ->leftJoin('icip_community as com', 'c.community_id', '=', 'com.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('c.information_object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('slug as s', 'c.information_object_id', '=', 's.object_id')
            ->select([
                'c.*',
                'com.name as community_name',
                'ioi.title as object_title',
                's.slug',
            ]);

        $status = $request->query('status');
        $communityId = $request->query('community_id');

        if ($status) {
            $query->where('c.consent_status', $status);
        }
        if ($communityId) {
            $query->where('c.community_id', $communityId);
        }

        $consents = $query->orderBy('c.created_at', 'desc')->get();

        $communities = DB::table('icip_community')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('icip::consent-list', [
            'consents'      => $consents,
            'statusOptions' => self::CONSENT_STATUSES,
            'communities'   => $communities,
            'filters'       => [
                'status'       => $status,
                'community_id' => $communityId,
            ],
            'tablesExist'   => true,
        ]);
    }

    /**
     * Add/Edit consent record (GET + POST)
     */
    public function consentEdit(Request $request)
    {
        $id = $request->query('id') ?? $request->input('id');
        $objectId = $request->query('object_id') ?? $request->input('object_id');
        $consent = null;

        if ($id) {
            $consent = DB::table('icip_consent')->where('id', $id)->first();
            if (!$consent) {
                abort(404, 'Consent record not found');
            }
            $objectId = $consent->information_object_id;
        }

        // Get object info if available
        $object = null;
        if ($objectId) {
            $object = DB::table('information_object as io')
                ->leftJoin('information_object_i18n as ioi', function ($join) {
                    $join->on('io.id', '=', 'ioi.id')
                        ->where('ioi.culture', '=', $this->culture);
                })
                ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
                ->where('io.id', $objectId)
                ->select(['io.id', 'io.identifier', 'ioi.title', 's.slug'])
                ->first();
        }

        $communities = DB::table('icip_community')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        if ($request->isMethod('post')) {
            $request->validate([
                'information_object_id' => 'required|integer',
                'consent_status'        => 'required|string|max:135',
            ]);

            $scopeArray = $request->input('consent_scope', []);
            $data = [
                'information_object_id' => $request->input('information_object_id'),
                'community_id'          => $request->input('community_id') ?: null,
                'consent_status'        => $request->input('consent_status'),
                'consent_scope'         => !empty($scopeArray) ? json_encode($scopeArray) : null,
                'consent_date'          => $request->input('consent_date') ?: null,
                'consent_expiry_date'   => $request->input('consent_expiry_date') ?: null,
                'consent_granted_by'    => $request->input('consent_granted_by'),
                'consent_document_path' => $request->input('consent_document_path'),
                'conditions'            => $request->input('conditions'),
                'restrictions'          => $request->input('restrictions'),
                'notes'                 => $request->input('notes'),
                'updated_at'            => now(),
            ];

            if ($id) {
                DB::table('icip_consent')->where('id', $id)->update($data);
            } else {
                $data['created_by'] = auth()->id();
                $data['created_at'] = now();
                $id = DB::table('icip_consent')->insertGetId($data);
            }

            $this->updateObjectSummary($data['information_object_id']);

            if ($object) {
                return redirect()->route('ahgicip.object-icip', ['slug' => $object->slug])
                    ->with('notice', 'Consent record saved successfully.');
            }
            return redirect()->route('ahgicip.consent-list')
                ->with('notice', 'Consent record saved successfully.');
        }

        return view('icip::consent-edit', [
            'id'            => $id,
            'consent'       => $consent,
            'object'        => $object,
            'objectId'      => $objectId,
            'statusOptions' => self::CONSENT_STATUSES,
            'scopeOptions'  => self::CONSENT_SCOPES,
            'communities'   => $communities,
        ]);
    }

    /**
     * View consent details
     */
    public function consentView(Request $request)
    {
        $id = $request->query('id');

        $consent = DB::table('icip_consent as c')
            ->leftJoin('icip_community as com', 'c.community_id', '=', 'com.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('c.information_object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('slug as s', 'c.information_object_id', '=', 's.object_id')
            ->where('c.id', $id)
            ->select([
                'c.*',
                'com.name as community_name',
                'ioi.title as object_title',
                's.slug',
            ])
            ->first();

        if (!$consent) {
            abort(404, 'Consent record not found');
        }

        return view('icip::consent-view', [
            'consent'       => $consent,
            'statusOptions' => self::CONSENT_STATUSES,
            'scopeOptions'  => self::CONSENT_SCOPES,
        ]);
    }

    // ========================================
    // CONSULTATIONS
    // ========================================

    /**
     * List consultations
     */
    public function consultations(Request $request)
    {
        if (!Schema::hasTable('icip_consultation')) {
            return view('icip::consultations', [
                'consultations' => collect(),
                'communities'   => collect(),
                'filters'       => ['type' => null, 'community_id' => null, 'status' => null],
                'tablesExist'   => false,
            ]);
        }

        $query = DB::table('icip_consultation as c')
            ->join('icip_community as com', 'c.community_id', '=', 'com.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('c.information_object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->select([
                'c.*',
                'com.name as community_name',
                'ioi.title as object_title',
            ]);

        $type = $request->query('type');
        $communityId = $request->query('community_id');
        $status = $request->query('status');

        if ($type) {
            $query->where('c.consultation_type', $type);
        }
        if ($communityId) {
            $query->where('c.community_id', $communityId);
        }
        if ($status) {
            $query->where('c.status', $status);
        }

        $consultations = $query->orderBy('c.consultation_date', 'desc')->get();

        $communities = DB::table('icip_community')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('icip::consultations', [
            'consultations'       => $consultations,
            'communities'         => $communities,
            'consultationTypes'   => self::CONSULTATION_TYPES,
            'consultationStatuses' => self::CONSULTATION_STATUSES,
            'filters'             => [
                'type'         => $type,
                'community_id' => $communityId,
                'status'       => $status,
            ],
            'tablesExist'         => true,
        ]);
    }

    /**
     * Add/Edit consultation (GET + POST)
     */
    public function consultationEdit(Request $request)
    {
        $id = $request->query('id') ?? $request->input('id');
        $objectId = $request->query('object_id') ?? $request->input('object_id');
        $consultation = null;

        if ($id) {
            $consultation = DB::table('icip_consultation')->where('id', $id)->first();
            if (!$consultation) {
                abort(404, 'Consultation not found');
            }
            $objectId = $consultation->information_object_id;
        }

        // Get object info if available
        $object = null;
        if ($objectId) {
            $object = DB::table('information_object as io')
                ->leftJoin('information_object_i18n as ioi', function ($join) {
                    $join->on('io.id', '=', 'ioi.id')
                        ->where('ioi.culture', '=', $this->culture);
                })
                ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
                ->where('io.id', $objectId)
                ->select(['io.id', 'io.identifier', 'ioi.title', 's.slug'])
                ->first();
        }

        $communities = DB::table('icip_community')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        // Get consent records for linking
        $consentRecords = collect();
        if ($objectId) {
            $consentRecords = DB::table('icip_consent')
                ->where('information_object_id', $objectId)
                ->get();
        }

        if ($request->isMethod('post')) {
            $request->validate([
                'community_id'        => 'required|integer',
                'consultation_type'   => 'required|string|max:132',
                'consultation_date'   => 'required|date',
                'consultation_method' => 'required|string|max:50',
                'summary'             => 'required|string',
            ]);

            $data = [
                'information_object_id'       => $request->input('information_object_id') ?: null,
                'community_id'                => $request->input('community_id'),
                'consultation_type'           => $request->input('consultation_type'),
                'consultation_date'           => $request->input('consultation_date'),
                'consultation_method'         => $request->input('consultation_method'),
                'location'                    => $request->input('location'),
                'attendees'                   => $request->input('attendees'),
                'community_representatives'   => $request->input('community_representatives'),
                'institution_representatives' => $request->input('institution_representatives'),
                'summary'                     => $request->input('summary'),
                'outcomes'                    => $request->input('outcomes'),
                'follow_up_date'              => $request->input('follow_up_date') ?: null,
                'follow_up_notes'             => $request->input('follow_up_notes'),
                'is_confidential'             => $request->input('is_confidential', 0) ? 1 : 0,
                'linked_consent_id'           => $request->input('linked_consent_id') ?: null,
                'status'                      => $request->input('status'),
                'updated_at'                  => now(),
            ];

            if ($id) {
                DB::table('icip_consultation')->where('id', $id)->update($data);
            } else {
                $data['created_by'] = auth()->id();
                $data['created_at'] = now();
                $id = DB::table('icip_consultation')->insertGetId($data);
            }

            // Update object summary if linked
            if ($data['information_object_id']) {
                $this->updateObjectSummary($data['information_object_id']);
            }

            return redirect()->route('ahgicip.consultations')
                ->with('notice', 'Consultation saved successfully.');
        }

        return view('icip::consultation-edit', [
            'id'                    => $id,
            'consultation'          => $consultation,
            'object'                => $object,
            'objectId'              => $objectId,
            'communities'           => $communities,
            'consentRecords'        => $consentRecords,
            'consultationTypes'     => self::CONSULTATION_TYPES,
            'consultationMethods'   => self::CONSULTATION_METHODS,
            'consultationStatuses'  => self::CONSULTATION_STATUSES,
        ]);
    }

    /**
     * View consultation details
     */
    public function consultationView(Request $request)
    {
        $id = $request->query('id');

        $consultation = DB::table('icip_consultation as c')
            ->join('icip_community as com', 'c.community_id', '=', 'com.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('c.information_object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('slug as s', 'c.information_object_id', '=', 's.object_id')
            ->where('c.id', $id)
            ->select([
                'c.*',
                'com.name as community_name',
                'ioi.title as object_title',
                's.slug',
            ])
            ->first();

        if (!$consultation) {
            abort(404, 'Consultation not found');
        }

        return view('icip::consultation-view', [
            'consultation'        => $consultation,
            'consultationTypes'   => self::CONSULTATION_TYPES,
            'consultationMethods' => self::CONSULTATION_METHODS,
            'consultationStatuses' => self::CONSULTATION_STATUSES,
        ]);
    }

    // ========================================
    // TK LABELS
    // ========================================

    /**
     * Manage TK Labels
     */
    public function tkLabels()
    {
        if (!Schema::hasTable('icip_tk_label_type')) {
            return view('icip::tk-labels', [
                'labelTypes'    => collect(),
                'appliedLabels' => collect(),
                'recentLabels'  => collect(),
                'tablesExist'   => false,
            ]);
        }

        $labelTypes = DB::table('icip_tk_label_type')
            ->orderBy('category')
            ->orderBy('display_order')
            ->get();

        $appliedLabels = DB::table('icip_tk_label as l')
            ->join('icip_tk_label_type as t', 'l.label_type_id', '=', 't.id')
            ->select([
                't.code',
                't.name',
                't.category',
                DB::raw('COUNT(*) as usage_count'),
            ])
            ->groupBy('t.code', 't.name', 't.category')
            ->orderBy('usage_count', 'desc')
            ->get();

        $recentLabels = DB::table('icip_tk_label as l')
            ->join('icip_tk_label_type as t', 'l.label_type_id', '=', 't.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('l.information_object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('slug as s', 'l.information_object_id', '=', 's.object_id')
            ->leftJoin('icip_community as c', 'l.community_id', '=', 'c.id')
            ->select([
                'l.*',
                't.code as label_code',
                't.name as label_name',
                't.category',
                'ioi.title as object_title',
                's.slug',
                'c.name as community_name',
            ])
            ->orderBy('l.created_at', 'desc')
            ->limit(20)
            ->get();

        return view('icip::tk-labels', [
            'labelTypes'    => $labelTypes,
            'appliedLabels' => $appliedLabels,
            'recentLabels'  => $recentLabels,
            'tablesExist'   => true,
        ]);
    }

    // ========================================
    // CULTURAL NOTICES
    // ========================================

    /**
     * Manage Cultural Notices
     */
    public function notices()
    {
        if (!Schema::hasTable('icip_cultural_notice_type')) {
            return view('icip::notices', [
                'noticeTypes'    => collect(),
                'appliedNotices' => collect(),
                'tablesExist'    => false,
            ]);
        }

        $noticeTypes = DB::table('icip_cultural_notice_type')
            ->orderBy('display_order')
            ->get();

        $appliedNotices = DB::table('icip_cultural_notice as n')
            ->join('icip_cultural_notice_type as t', 'n.notice_type_id', '=', 't.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('n.information_object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('slug as s', 'n.information_object_id', '=', 's.object_id')
            ->leftJoin('icip_community as c', 'n.community_id', '=', 'c.id')
            ->select([
                'n.*',
                't.code as notice_code',
                't.name as notice_name',
                't.severity',
                'ioi.title as object_title',
                's.slug',
                'c.name as community_name',
            ])
            ->orderBy('n.created_at', 'desc')
            ->limit(50)
            ->get();

        return view('icip::notices', [
            'noticeTypes'    => $noticeTypes,
            'appliedNotices' => $appliedNotices,
            'tablesExist'    => true,
        ]);
    }

    /**
     * Manage Notice Types (GET + POST)
     */
    public function noticeTypes(Request $request)
    {
        if (!Schema::hasTable('icip_cultural_notice_type')) {
            return view('icip::notice-types', [
                'noticeTypes' => collect(),
                'tablesExist' => false,
            ]);
        }

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');
            $typeId = $request->input('type_id');

            if ($action === 'add') {
                $request->validate([
                    'code' => 'required|string|max:50|unique:icip_cultural_notice_type,code',
                    'name' => 'required|string|max:255',
                ]);

                DB::table('icip_cultural_notice_type')->insert([
                    'code'                      => $request->input('code'),
                    'name'                      => $request->input('name'),
                    'description'               => $request->input('description'),
                    'default_text'              => $request->input('default_text'),
                    'severity'                  => $request->input('severity', 'info'),
                    'requires_acknowledgement'  => $request->input('requires_acknowledgement', 0) ? 1 : 0,
                    'blocks_access'             => $request->input('blocks_access', 0) ? 1 : 0,
                    'display_public'            => $request->input('display_public', 1) ? 1 : 0,
                    'display_staff'             => $request->input('display_staff', 1) ? 1 : 0,
                    'display_order'             => $request->input('display_order', 100),
                    'is_active'                 => 1,
                    'created_at'                => now(),
                ]);

                return redirect()->route('ahgicip.notice-types')
                    ->with('notice', 'Notice type added.');
            } elseif ($action === 'toggle' && $typeId) {
                $current = DB::table('icip_cultural_notice_type')
                    ->where('id', $typeId)
                    ->value('is_active');
                DB::table('icip_cultural_notice_type')
                    ->where('id', $typeId)
                    ->update(['is_active' => $current ? 0 : 1, 'updated_at' => now()]);

                return redirect()->route('ahgicip.notice-types')
                    ->with('notice', 'Notice type toggled.');
            }
        }

        $noticeTypes = DB::table('icip_cultural_notice_type')
            ->orderBy('display_order')
            ->get();

        return view('icip::notice-types', [
            'noticeTypes' => $noticeTypes,
            'tablesExist' => true,
        ]);
    }

    // ========================================
    // ACCESS RESTRICTIONS
    // ========================================

    /**
     * Manage Access Restrictions
     */
    public function restrictions()
    {
        if (!Schema::hasTable('icip_access_restriction')) {
            return view('icip::restrictions', [
                'restrictionTypes' => self::RESTRICTION_TYPES,
                'restrictions'     => collect(),
                'tablesExist'      => false,
            ]);
        }

        $restrictions = DB::table('icip_access_restriction as r')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('r.information_object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('slug as s', 'r.information_object_id', '=', 's.object_id')
            ->leftJoin('icip_community as c', 'r.community_id', '=', 'c.id')
            ->select([
                'r.*',
                'ioi.title as object_title',
                's.slug',
                'c.name as community_name',
            ])
            ->orderBy('r.created_at', 'desc')
            ->get();

        return view('icip::restrictions', [
            'restrictionTypes' => self::RESTRICTION_TYPES,
            'restrictions'     => $restrictions,
            'tablesExist'      => true,
        ]);
    }

    // ========================================
    // REPORTS
    // ========================================

    /**
     * Reports overview
     */
    public function reports()
    {
        if (!Schema::hasTable('icip_consent')) {
            return view('icip::reports', [
                'stats'              => [],
                'consentByStatus'    => collect(),
                'recordsByCommunity' => collect(),
                'tablesExist'        => false,
            ]);
        }

        $stats = $this->getDashboardStats();

        $consentByStatus = DB::table('icip_consent')
            ->select('consent_status', DB::raw('COUNT(*) as count'))
            ->groupBy('consent_status')
            ->get();

        $recordsByCommunity = DB::table('icip_consent as c')
            ->join('icip_community as com', 'c.community_id', '=', 'com.id')
            ->select([
                'com.id',
                'com.name',
                'com.state_territory',
                DB::raw('COUNT(*) as record_count'),
            ])
            ->groupBy('com.id', 'com.name', 'com.state_territory')
            ->orderBy('record_count', 'desc')
            ->get();

        return view('icip::reports', [
            'stats'              => $stats,
            'consentByStatus'    => $consentByStatus,
            'recordsByCommunity' => $recordsByCommunity,
            'statusOptions'      => self::CONSENT_STATUSES,
            'tablesExist'        => true,
        ]);
    }

    /**
     * Pending consultations report
     */
    public function reportPending()
    {
        $records = $this->getPendingConsultation(200);

        return view('icip::report-pending', [
            'records'       => $records,
            'statusOptions' => self::CONSENT_STATUSES,
        ]);
    }

    /**
     * Expiring consents report
     */
    public function reportExpiry(Request $request)
    {
        $days = (int) $request->query('days', 90);
        $records = $this->getExpiringConsents($days);

        return view('icip::report-expiry', [
            'days'    => $days,
            'records' => $records,
        ]);
    }

    /**
     * Community-specific report
     */
    public function reportCommunity(Request $request)
    {
        $id = $request->query('id');
        $community = DB::table('icip_community')->where('id', $id)->first();

        if (!$community) {
            abort(404, 'Community not found');
        }

        $consents = DB::table('icip_consent as c')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('c.information_object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('slug as s', 'c.information_object_id', '=', 's.object_id')
            ->where('c.community_id', $id)
            ->select(['c.*', 'ioi.title as object_title', 's.slug'])
            ->orderBy('c.created_at', 'desc')
            ->get();

        $consultations = DB::table('icip_consultation')
            ->where('community_id', $id)
            ->orderBy('consultation_date', 'desc')
            ->get();

        $notices = DB::table('icip_cultural_notice as n')
            ->join('icip_cultural_notice_type as t', 'n.notice_type_id', '=', 't.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('n.information_object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->where('n.community_id', $id)
            ->select(['n.*', 't.name as notice_name', 'ioi.title as object_title'])
            ->get();

        $labels = DB::table('icip_tk_label as l')
            ->join('icip_tk_label_type as t', 'l.label_type_id', '=', 't.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('l.information_object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->where('l.community_id', $id)
            ->select(['l.*', 't.name as label_name', 't.code', 'ioi.title as object_title'])
            ->get();

        return view('icip::report-community', [
            'community'      => $community,
            'consents'       => $consents,
            'consultations'  => $consultations,
            'notices'        => $notices,
            'labels'         => $labels,
            'statusOptions'  => self::CONSENT_STATUSES,
            'states'         => self::STATE_TERRITORIES,
        ]);
    }

    // ========================================
    // OBJECT-SPECIFIC ICIP
    // ========================================

    /**
     * ICIP overview for a specific object
     */
    public function objectIcip(Request $request)
    {
        $slug = $request->query('slug');
        $object = $this->getObjectBySlug($slug);

        if (!$object) {
            abort(404, 'Record not found');
        }

        $summary = DB::table('icip_object_summary')
            ->where('information_object_id', $object->id)
            ->first();

        $consents = $this->getObjectConsent($object->id);
        $notices = $this->getObjectNotices($object->id);
        $labels = $this->getObjectTKLabels($object->id);
        $restrictions = $this->getObjectRestrictions($object->id);
        $consultations = $this->getObjectConsultations($object->id);

        return view('icip::object-icip', [
            'object'           => $object,
            'summary'          => $summary,
            'consents'         => $consents,
            'notices'          => $notices,
            'labels'           => $labels,
            'restrictions'     => $restrictions,
            'consultations'    => $consultations,
            'statusOptions'    => self::CONSENT_STATUSES,
            'scopeOptions'     => self::CONSENT_SCOPES,
            'restrictionTypes' => self::RESTRICTION_TYPES,
        ]);
    }

    /**
     * Manage consent for an object (GET + POST)
     */
    public function objectConsent(Request $request)
    {
        $slug = $request->query('slug') ?? $request->input('slug');
        $object = $this->getObjectBySlug($slug);

        if (!$object) {
            abort(404, 'Record not found');
        }

        $communities = DB::table('icip_community')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $consents = $this->getObjectConsent($object->id);

        if ($request->isMethod('post')) {
            $request->validate([
                'consent_status' => 'required|string|max:135',
            ]);

            $scopeArray = $request->input('consent_scope', []);
            $data = [
                'information_object_id' => $object->id,
                'community_id'          => $request->input('community_id') ?: null,
                'consent_status'        => $request->input('consent_status'),
                'consent_scope'         => !empty($scopeArray) ? json_encode($scopeArray) : null,
                'consent_date'          => $request->input('consent_date') ?: null,
                'consent_expiry_date'   => $request->input('consent_expiry_date') ?: null,
                'consent_granted_by'    => $request->input('consent_granted_by'),
                'conditions'            => $request->input('conditions'),
                'notes'                 => $request->input('notes'),
                'created_by'            => auth()->id(),
                'created_at'            => now(),
                'updated_at'            => now(),
            ];

            DB::table('icip_consent')->insert($data);
            $this->updateObjectSummary($object->id);

            return redirect()->route('ahgicip.object-icip', ['slug' => $slug])
                ->with('notice', 'Consent record added.');
        }

        return view('icip::object-consent', [
            'object'        => $object,
            'consents'      => $consents,
            'statusOptions' => self::CONSENT_STATUSES,
            'scopeOptions'  => self::CONSENT_SCOPES,
            'communities'   => $communities,
        ]);
    }

    /**
     * Manage notices for an object (GET + POST)
     */
    public function objectNotices(Request $request)
    {
        $slug = $request->query('slug') ?? $request->input('slug');
        $object = $this->getObjectBySlug($slug);

        if (!$object) {
            abort(404, 'Record not found');
        }

        $noticeTypes = DB::table('icip_cultural_notice_type')
            ->where('is_active', 1)
            ->orderBy('display_order')
            ->get();

        $communities = DB::table('icip_community')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $notices = $this->getObjectNotices($object->id);

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');

            if ($action === 'add') {
                $request->validate([
                    'notice_type_id' => 'required|integer',
                ]);

                DB::table('icip_cultural_notice')->insert([
                    'information_object_id' => $object->id,
                    'notice_type_id'        => $request->input('notice_type_id'),
                    'custom_text'           => $request->input('custom_text') ?: null,
                    'community_id'          => $request->input('community_id') ?: null,
                    'applies_to_descendants' => $request->input('applies_to_descendants', 1) ? 1 : 0,
                    'start_date'            => $request->input('start_date') ?: null,
                    'end_date'              => $request->input('end_date') ?: null,
                    'notes'                 => $request->input('notes'),
                    'created_by'            => auth()->id(),
                    'created_at'            => now(),
                ]);

                $this->updateObjectSummary($object->id);
                return redirect()->route('ahgicip.object-notices', ['slug' => $slug])
                    ->with('notice', 'Cultural notice added.');
            } elseif ($action === 'remove') {
                DB::table('icip_cultural_notice')
                    ->where('id', $request->input('notice_id'))
                    ->where('information_object_id', $object->id)
                    ->delete();

                $this->updateObjectSummary($object->id);
                return redirect()->route('ahgicip.object-notices', ['slug' => $slug])
                    ->with('notice', 'Cultural notice removed.');
            }
        }

        return view('icip::object-notices', [
            'object'      => $object,
            'noticeTypes' => $noticeTypes,
            'communities' => $communities,
            'notices'     => $notices,
        ]);
    }

    /**
     * Manage TK labels for an object (GET + POST)
     */
    public function objectLabels(Request $request)
    {
        $slug = $request->query('slug') ?? $request->input('slug');
        $object = $this->getObjectBySlug($slug);

        if (!$object) {
            abort(404, 'Record not found');
        }

        $labelTypes = DB::table('icip_tk_label_type')
            ->where('is_active', 1)
            ->orderBy('category')
            ->orderBy('display_order')
            ->get();

        $communities = DB::table('icip_community')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $labels = $this->getObjectTKLabels($object->id);

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');

            if ($action === 'add') {
                $request->validate([
                    'label_type_id' => 'required|integer',
                ]);

                DB::table('icip_tk_label')->insertOrIgnore([
                    'information_object_id'      => $object->id,
                    'label_type_id'              => $request->input('label_type_id'),
                    'community_id'               => $request->input('community_id') ?: null,
                    'applied_by'                 => $request->input('applied_by', 'institution'),
                    'local_contexts_project_id'  => $request->input('local_contexts_project_id'),
                    'notes'                      => $request->input('notes'),
                    'created_by'                 => auth()->id(),
                    'created_at'                 => now(),
                ]);

                $this->updateObjectSummary($object->id);
                return redirect()->route('ahgicip.object-labels', ['slug' => $slug])
                    ->with('notice', 'TK Label added.');
            } elseif ($action === 'remove') {
                DB::table('icip_tk_label')
                    ->where('id', $request->input('label_id'))
                    ->where('information_object_id', $object->id)
                    ->delete();

                $this->updateObjectSummary($object->id);
                return redirect()->route('ahgicip.object-labels', ['slug' => $slug])
                    ->with('notice', 'TK Label removed.');
            }
        }

        return view('icip::object-labels', [
            'object'     => $object,
            'labelTypes' => $labelTypes,
            'communities' => $communities,
            'labels'     => $labels,
        ]);
    }

    /**
     * Manage restrictions for an object (GET + POST)
     */
    public function objectRestrictions(Request $request)
    {
        $slug = $request->query('slug') ?? $request->input('slug');
        $object = $this->getObjectBySlug($slug);

        if (!$object) {
            abort(404, 'Record not found');
        }

        $communities = DB::table('icip_community')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $restrictions = $this->getObjectRestrictions($object->id);

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');

            if ($action === 'add') {
                $request->validate([
                    'restriction_type' => 'required|string|max:198',
                ]);

                DB::table('icip_access_restriction')->insert([
                    'information_object_id'      => $object->id,
                    'restriction_type'           => $request->input('restriction_type'),
                    'community_id'               => $request->input('community_id') ?: null,
                    'start_date'                 => $request->input('start_date') ?: null,
                    'end_date'                   => $request->input('end_date') ?: null,
                    'applies_to_descendants'     => $request->input('applies_to_descendants', 1) ? 1 : 0,
                    'override_security_clearance' => $request->input('override_security_clearance', 1) ? 1 : 0,
                    'custom_restriction_text'    => $request->input('custom_restriction_text'),
                    'notes'                      => $request->input('notes'),
                    'created_by'                 => auth()->id(),
                    'created_at'                 => now(),
                ]);

                $this->updateObjectSummary($object->id);
                return redirect()->route('ahgicip.object-restrictions', ['slug' => $slug])
                    ->with('notice', 'Restriction added.');
            } elseif ($action === 'remove') {
                DB::table('icip_access_restriction')
                    ->where('id', $request->input('restriction_id'))
                    ->where('information_object_id', $object->id)
                    ->delete();

                $this->updateObjectSummary($object->id);
                return redirect()->route('ahgicip.object-restrictions', ['slug' => $slug])
                    ->with('notice', 'Restriction removed.');
            }
        }

        return view('icip::object-restrictions', [
            'object'           => $object,
            'restrictionTypes' => self::RESTRICTION_TYPES,
            'communities'      => $communities,
            'restrictions'     => $restrictions,
        ]);
    }

    /**
     * View consultations for an object
     */
    public function objectConsultations(Request $request)
    {
        $slug = $request->query('slug');
        $object = $this->getObjectBySlug($slug);

        if (!$object) {
            abort(404, 'Record not found');
        }

        $communities = DB::table('icip_community')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $consultations = $this->getObjectConsultations($object->id);

        return view('icip::object-consultations', [
            'object'              => $object,
            'communities'         => $communities,
            'consultations'       => $consultations,
            'consultationTypes'   => self::CONSULTATION_TYPES,
            'consultationMethods' => self::CONSULTATION_METHODS,
        ]);
    }

    // ========================================
    // ACKNOWLEDGEMENT (JSON)
    // ========================================

    /**
     * Record user acknowledgement of a notice (AJAX)
     */
    public function acknowledge(Request $request)
    {
        $noticeId = $request->input('notice_id');
        $userId = auth()->id();

        if (!$userId) {
            return response()->json(['success' => false, 'error' => 'Not authenticated'], 401);
        }

        $notice = DB::table('icip_cultural_notice')->where('id', $noticeId)->first();
        if (!$notice) {
            return response()->json(['success' => false, 'error' => 'Notice not found'], 404);
        }

        $alreadyAcknowledged = DB::table('icip_notice_acknowledgement')
            ->where('notice_id', $noticeId)
            ->where('user_id', $userId)
            ->exists();

        if (!$alreadyAcknowledged) {
            DB::table('icip_notice_acknowledgement')->insert([
                'notice_id'       => $noticeId,
                'user_id'         => $userId,
                'acknowledged_at' => now(),
                'ip_address'      => $request->ip(),
                'user_agent'      => substr($request->userAgent() ?? '', 0, 500),
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('notice', 'Acknowledgement recorded.');
    }

    // ========================================
    // API ENDPOINTS
    // ========================================

    /**
     * API: Get ICIP summary for an object
     */
    public function apiSummary(Request $request)
    {
        $objectId = (int) $request->query('object_id');

        $summary = DB::table('icip_object_summary')
            ->where('information_object_id', $objectId)
            ->first();

        return response()->json([
            'object_id'                 => $objectId,
            'has_icip_content'          => $summary ? (bool) $summary->has_icip_content : false,
            'consent_status'            => $summary ? $summary->consent_status : null,
            'has_cultural_notices'      => $summary ? (bool) $summary->has_cultural_notices : false,
            'has_tk_labels'             => $summary ? (bool) $summary->has_tk_labels : false,
            'has_restrictions'          => $summary ? (bool) $summary->has_restrictions : false,
            'requires_acknowledgement'  => $summary ? (bool) $summary->requires_acknowledgement : false,
            'blocks_access'             => $summary ? (bool) $summary->blocks_access : false,
        ]);
    }

    /**
     * API: Check access for an object
     */
    public function apiCheckAccess(Request $request)
    {
        $objectId = (int) $request->query('object_id');
        $userId = auth()->id();

        $access = $this->checkAccess($objectId, $userId);

        return response()->json($access);
    }

    // ========================================
    // HELPER: Get object by slug
    // ========================================

    protected function getObjectBySlug(?string $slug): ?object
    {
        if (!$slug) {
            return null;
        }

        return DB::table('information_object as io')
            ->join('slug as s', 'io.id', '=', 's.object_id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->where('s.slug', $slug)
            ->select([
                'io.id',
                'io.identifier',
                'io.level_of_description_id',
                'ioi.title',
                's.slug',
            ])
            ->first();
    }

    // ========================================
    // SERVICE METHODS (ported from ahgICIPService)
    // ========================================

    /**
     * Get consent records for an object
     */
    protected function getObjectConsent(int $objectId)
    {
        return DB::table('icip_consent as c')
            ->leftJoin('icip_community as com', 'c.community_id', '=', 'com.id')
            ->where('c.information_object_id', $objectId)
            ->select([
                'c.*',
                'com.name as community_name',
                'com.language_group',
                'com.state_territory',
            ])
            ->orderBy('c.created_at', 'desc')
            ->get();
    }

    /**
     * Get cultural notices for an object (active only, date-filtered)
     */
    protected function getObjectNotices(int $objectId)
    {
        return DB::table('icip_cultural_notice as n')
            ->join('icip_cultural_notice_type as t', 'n.notice_type_id', '=', 't.id')
            ->leftJoin('icip_community as c', 'n.community_id', '=', 'c.id')
            ->where('n.information_object_id', $objectId)
            ->where('t.is_active', 1)
            ->where(function ($query) {
                $query->whereNull('n.start_date')
                    ->orWhere('n.start_date', '<=', now()->toDateString());
            })
            ->where(function ($query) {
                $query->whereNull('n.end_date')
                    ->orWhere('n.end_date', '>=', now()->toDateString());
            })
            ->select([
                'n.*',
                't.code as notice_code',
                't.name as notice_name',
                't.default_text',
                't.icon',
                't.severity',
                't.requires_acknowledgement',
                't.blocks_access',
                't.display_public',
                't.display_staff',
                'c.name as community_name',
            ])
            ->orderBy('t.display_order')
            ->get();
    }

    /**
     * Get TK labels for an object (active types only)
     */
    protected function getObjectTKLabels(int $objectId)
    {
        return DB::table('icip_tk_label as l')
            ->join('icip_tk_label_type as t', 'l.label_type_id', '=', 't.id')
            ->leftJoin('icip_community as c', 'l.community_id', '=', 'c.id')
            ->where('l.information_object_id', $objectId)
            ->where('t.is_active', 1)
            ->select([
                'l.*',
                't.code as label_code',
                't.category',
                't.name as label_name',
                't.description',
                't.icon_path',
                't.local_contexts_url',
                'c.name as community_name',
            ])
            ->orderBy('t.display_order')
            ->get();
    }

    /**
     * Get access restrictions for an object (active only, date-filtered)
     */
    protected function getObjectRestrictions(int $objectId)
    {
        return DB::table('icip_access_restriction as r')
            ->leftJoin('icip_community as c', 'r.community_id', '=', 'c.id')
            ->where('r.information_object_id', $objectId)
            ->where(function ($query) {
                $query->whereNull('r.start_date')
                    ->orWhere('r.start_date', '<=', now()->toDateString());
            })
            ->where(function ($query) {
                $query->whereNull('r.end_date')
                    ->orWhere('r.end_date', '>=', now()->toDateString());
            })
            ->select([
                'r.*',
                'c.name as community_name',
            ])
            ->orderBy('r.created_at', 'desc')
            ->get();
    }

    /**
     * Get consultations for an object (non-confidential only)
     */
    protected function getObjectConsultations(int $objectId)
    {
        return DB::table('icip_consultation as con')
            ->join('icip_community as c', 'con.community_id', '=', 'c.id')
            ->where('con.information_object_id', $objectId)
            ->where('con.is_confidential', 0)
            ->select([
                'con.*',
                'c.name as community_name',
            ])
            ->orderBy('con.consultation_date', 'desc')
            ->get();
    }

    /**
     * Get dashboard statistics
     */
    protected function getDashboardStats(): array
    {
        $stats = [];

        $stats['total_icip_objects'] = Schema::hasTable('icip_object_summary')
            ? DB::table('icip_object_summary')->where('has_icip_content', 1)->count()
            : 0;

        $stats['consent_by_status'] = Schema::hasTable('icip_consent')
            ? DB::table('icip_consent')
                ->select('consent_status', DB::raw('COUNT(*) as count'))
                ->groupBy('consent_status')
                ->pluck('count', 'consent_status')
                ->toArray()
            : [];

        $stats['pending_consultations'] = Schema::hasTable('icip_consent')
            ? DB::table('icip_consent')
                ->whereIn('consent_status', ['pending_consultation', 'consultation_in_progress'])
                ->count()
            : 0;

        $expiryDays = $this->getIcipConfig('consent_expiry_warning_days', 90);
        $expiryDate = now()->addDays((int) $expiryDays)->toDateString();

        $stats['expiring_consents'] = Schema::hasTable('icip_consent')
            ? DB::table('icip_consent')
                ->whereNotNull('consent_expiry_date')
                ->where('consent_expiry_date', '<=', $expiryDate)
                ->where('consent_expiry_date', '>=', now()->toDateString())
                ->count()
            : 0;

        $stats['total_communities'] = Schema::hasTable('icip_community')
            ? DB::table('icip_community')->where('is_active', 1)->count()
            : 0;

        $stats['follow_ups_due'] = Schema::hasTable('icip_consultation')
            ? DB::table('icip_consultation')
                ->where('status', 'follow_up_required')
                ->whereNotNull('follow_up_date')
                ->where('follow_up_date', '<=', now()->toDateString())
                ->count()
            : 0;

        $stats['tk_labels_applied'] = Schema::hasTable('icip_tk_label')
            ? DB::table('icip_tk_label')->count()
            : 0;

        $stats['active_restrictions'] = Schema::hasTable('icip_access_restriction')
            ? DB::table('icip_access_restriction')
                ->where(function ($query) {
                    $query->whereNull('start_date')
                        ->orWhere('start_date', '<=', now()->toDateString());
                })
                ->where(function ($query) {
                    $query->whereNull('end_date')
                        ->orWhere('end_date', '>=', now()->toDateString());
                })
                ->count()
            : 0;

        return $stats;
    }

    /**
     * Get records pending consultation
     */
    protected function getPendingConsultation(int $limit = 50)
    {
        if (!Schema::hasTable('icip_consent')) {
            return collect();
        }

        return DB::table('icip_consent as c')
            ->join('information_object as io', 'c.information_object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
            ->leftJoin('icip_community as com', 'c.community_id', '=', 'com.id')
            ->whereIn('c.consent_status', [
                'pending_consultation',
                'consultation_in_progress',
                'unknown',
            ])
            ->select([
                'c.*',
                'ioi.title as object_title',
                'io.identifier',
                's.slug',
                'com.name as community_name',
            ])
            ->orderBy('c.created_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get consents expiring soon
     */
    protected function getExpiringConsents(int $days = 90)
    {
        if (!Schema::hasTable('icip_consent')) {
            return collect();
        }

        $expiryDate = now()->addDays($days)->toDateString();

        return DB::table('icip_consent as c')
            ->join('information_object as io', 'c.information_object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
            ->leftJoin('icip_community as com', 'c.community_id', '=', 'com.id')
            ->whereNotNull('c.consent_expiry_date')
            ->where('c.consent_expiry_date', '<=', $expiryDate)
            ->where('c.consent_expiry_date', '>=', now()->toDateString())
            ->select([
                'c.*',
                'ioi.title as object_title',
                'io.identifier',
                's.slug',
                'com.name as community_name',
            ])
            ->orderBy('c.consent_expiry_date', 'asc')
            ->get();
    }

    /**
     * Check if user can access an object based on ICIP restrictions
     */
    protected function checkAccess(int $objectId, ?int $userId = null): array
    {
        $result = [
            'allowed'                   => true,
            'requires_acknowledgement'  => false,
            'unacknowledged_notices'    => [],
            'blocked_reason'            => null,
            'restrictions'              => [],
        ];

        if (!Schema::hasTable('icip_cultural_notice')) {
            return $result;
        }

        // Check notices that block access or require acknowledgement
        $notices = $this->getObjectNotices($objectId);
        foreach ($notices as $notice) {
            if ($notice->blocks_access) {
                $acknowledged = $userId
                    ? DB::table('icip_notice_acknowledgement')
                        ->where('notice_id', $notice->id)
                        ->where('user_id', $userId)
                        ->exists()
                    : false;

                if (!$acknowledged) {
                    $result['allowed'] = false;
                    $result['blocked_reason'] = 'Cultural notice requires acknowledgement before access';
                    $result['unacknowledged_notices'][] = $notice;
                }
            } elseif ($notice->requires_acknowledgement) {
                $acknowledged = $userId
                    ? DB::table('icip_notice_acknowledgement')
                        ->where('notice_id', $notice->id)
                        ->where('user_id', $userId)
                        ->exists()
                    : false;

                if (!$acknowledged) {
                    $result['requires_acknowledgement'] = true;
                    $result['unacknowledged_notices'][] = $notice;
                }
            }
        }

        // Check restrictions
        $restrictions = $this->getObjectRestrictions($objectId);
        foreach ($restrictions as $restriction) {
            $result['restrictions'][] = $restriction;
            if ($restriction->override_security_clearance) {
                if (in_array($restriction->restriction_type, [
                    'community_permission_required',
                    'initiated_only',
                    'repatriation_pending',
                ])) {
                    $result['allowed'] = false;
                    $result['blocked_reason'] = 'ICIP restriction: ' . (self::RESTRICTION_TYPES[$restriction->restriction_type] ?? ucwords(str_replace('_', ' ', $restriction->restriction_type)));
                }
            }
        }

        return $result;
    }

    /**
     * Update ICIP summary for an object
     */
    protected function updateObjectSummary(int $objectId): void
    {
        if (!Schema::hasTable('icip_object_summary')) {
            return;
        }

        $consent = DB::table('icip_consent')
            ->where('information_object_id', $objectId)
            ->orderBy('created_at', 'desc')
            ->first();

        $noticeCount = DB::table('icip_cultural_notice')
            ->where('information_object_id', $objectId)
            ->count();

        $labelCount = DB::table('icip_tk_label')
            ->where('information_object_id', $objectId)
            ->count();

        $restrictionCount = DB::table('icip_access_restriction')
            ->where('information_object_id', $objectId)
            ->count();

        $blockingNotice = DB::table('icip_cultural_notice as n')
            ->join('icip_cultural_notice_type as t', 'n.notice_type_id', '=', 't.id')
            ->where('n.information_object_id', $objectId)
            ->where(function ($query) {
                $query->where('t.requires_acknowledgement', 1)
                    ->orWhere('t.blocks_access', 1);
            })
            ->first();

        // Collect community IDs from consent and notice records
        $communityIds = [];
        $consentCommunities = DB::table('icip_consent')
            ->where('information_object_id', $objectId)
            ->whereNotNull('community_id')
            ->pluck('community_id')
            ->toArray();
        $communityIds = array_merge($communityIds, $consentCommunities);

        $noticeCommunities = DB::table('icip_cultural_notice')
            ->where('information_object_id', $objectId)
            ->whereNotNull('community_id')
            ->pluck('community_id')
            ->toArray();
        $communityIds = array_unique(array_merge($communityIds, $noticeCommunities));

        $lastConsultation = DB::table('icip_consultation')
            ->where('information_object_id', $objectId)
            ->orderBy('consultation_date', 'desc')
            ->value('consultation_date');

        $hasContent = $consent || $noticeCount > 0 || $labelCount > 0 || $restrictionCount > 0;

        DB::table('icip_object_summary')->updateOrInsert(
            ['information_object_id' => $objectId],
            [
                'has_icip_content'          => $hasContent ? 1 : 0,
                'consent_status'            => $consent ? $consent->consent_status : null,
                'has_cultural_notices'      => $noticeCount > 0 ? 1 : 0,
                'cultural_notice_count'     => $noticeCount,
                'has_tk_labels'             => $labelCount > 0 ? 1 : 0,
                'tk_label_count'            => $labelCount,
                'has_restrictions'          => $restrictionCount > 0 ? 1 : 0,
                'restriction_count'         => $restrictionCount,
                'requires_acknowledgement'  => $blockingNotice && $blockingNotice->requires_acknowledgement ? 1 : 0,
                'blocks_access'             => $blockingNotice && $blockingNotice->blocks_access ? 1 : 0,
                'community_ids'             => !empty($communityIds) ? json_encode(array_values($communityIds)) : null,
                'last_consultation_date'    => $lastConsultation,
                'consent_expiry_date'       => $consent ? $consent->consent_expiry_date : null,
                'updated_at'               => now(),
            ]
        );
    }

    /**
     * Get ICIP config value
     */
    protected function getIcipConfig(string $key, $default = null)
    {
        if (!Schema::hasTable('icip_config')) {
            return $default;
        }

        $value = DB::table('icip_config')
            ->where('config_key', $key)
            ->value('config_value');

        return $value !== null ? $value : $default;
    }

    // ========================================
    // OCAP OVERLAY — Ownership, Control, Access, Possession
    // Pluggable per-market (Canada, Australia, Aotearoa, etc.). No defaults are applied
    // to the platform unless icip_config.ocap_enabled = '1'.
    // ========================================

    public function ocapDashboard()
    {
        $svc = new \AhgIcip\Services\OcapService();
        if (!$svc->isEnabled()) {
            return view('icip::ocap-disabled', [
                'enableUrl' => route('ahgicip.ocap-settings'),
            ]);
        }

        return view('icip::ocap-dashboard', [
            'agg'    => $svc->aggregate(),
            'rollup' => $svc->rollup(),
        ]);
    }

    public function ocapSettings(Request $request)
    {
        if (!Schema::hasTable('icip_config')) {
            abort(404);
        }

        if ($request->isMethod('post')) {
            $val = $request->input('ocap_enabled') === '1' ? '1' : '0';
            DB::table('icip_config')->updateOrInsert(
                ['config_key' => 'ocap_enabled'],
                ['config_value' => $val, 'updated_at' => now()]
            );
            return redirect()->route('ahgicip.ocap-settings')->with('success', 'OCAP overlay ' . ($val === '1' ? 'enabled' : 'disabled') . '.');
        }

        return view('icip::ocap-settings', [
            'enabled' => (string) $this->getIcipConfig('ocap_enabled', '0') === '1',
        ]);
    }

    public function ocapSetPossession(Request $request)
    {
        $request->validate([
            'information_object_id' => 'required|integer',
            'possession'            => 'nullable|in:community,repository,shared',
        ]);

        $ioId = (int) $request->input('information_object_id');
        $possession = $request->input('possession') ?: null;

        if (!Schema::hasTable('icip_object_summary') || !Schema::hasColumn('icip_object_summary', 'possession_assertion')) {
            abort(409, 'OCAP columns missing.');
        }

        $exists = DB::table('icip_object_summary')->where('information_object_id', $ioId)->exists();
        if ($exists) {
            DB::table('icip_object_summary')
                ->where('information_object_id', $ioId)
                ->update(['possession_assertion' => $possession, 'updated_at' => now()]);
        } else {
            DB::table('icip_object_summary')->insert([
                'information_object_id' => $ioId,
                'possession_assertion'  => $possession,
                'updated_at'            => now(),
            ]);
        }

        return back()->with('success', 'Possession assertion updated.');
    }
}
