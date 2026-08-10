<?php

/**
 * IcipAccessService - Controller for Heratio
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

namespace AhgIcip\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use AhgIcip\Concerns\LogsIcipAccess;

/**
 * #1427 - the single, shared source of the graded ICIP cultural-access decision.
 *
 * Previously the decision lived only inside IcipController::checkAccess(), so the
 * primary staff show page had no way to consult it without duplicating the logic.
 * This service centralises it: IcipController delegates here, and the archival
 * show page (InformationObjectController::show) now gates on it too.
 *
 * Product decisions (Johan, 2026-08-04): FAIL CLOSED on an unknown user
 * attribute, and reuse the existing icip_community_steward link as the
 * community-affiliation attribute. All ten restriction types are evaluated;
 * workflow states (elder approval / under consultation) surface a consultation
 * requirement rather than a hard block.
 */
class IcipAccessService
{
    use LogsIcipAccess;

    /** Human labels for the ten restriction types (mirrors IcipController::RESTRICTION_TYPES). */
    public const RESTRICTION_TYPES = [
        'community_permission_required' => 'Community Permission Required',
        'gender_restricted_male' => 'Men Only (Gender Restricted)',
        'gender_restricted_female' => 'Women Only (Gender Restricted)',
        'initiated_only' => 'Initiated Persons Only',
        'seasonal' => 'Seasonal Restriction',
        'mourning_period' => 'Mourning Period',
        'repatriation_pending' => 'Repatriation Pending',
        'under_consultation' => 'Under Consultation',
        'elder_approval_required' => 'Elder Approval Required',
        'custom' => 'Custom Restriction',
    ];

    /**
     * Evaluate ICIP cultural access for one object and (optionally) one user.
     * Returns the graded decision array. Safe to call when the ICIP tables are
     * absent (returns an allow with no restrictions).
     */
    public function check(int $objectId, ?int $userId = null): array
    {
        $result = [
            'allowed' => true,
            'requires_acknowledgement' => false,
            'unacknowledged_notices' => [],
            'blocked_reason' => null,
            'restrictions' => [],
            'requires_consultation' => false,
            'consultation_restrictions' => [],
            'access_reason' => null,
        ];

        if (! Schema::hasTable('icip_cultural_notice')) {
            return $result;
        }

        // Notices that block access or require acknowledgement.
        foreach ($this->notices($objectId) as $notice) {
            if ($notice->blocks_access) {
                $acknowledged = $userId
                    ? DB::table('icip_notice_acknowledgement')
                        ->where('notice_id', $notice->id)
                        ->where('user_id', $userId)
                        ->exists()
                    : false;
                if (! $acknowledged) {
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
                if (! $acknowledged) {
                    $result['requires_acknowledgement'] = true;
                    $result['unacknowledged_notices'][] = $notice;
                }
            }
        }

        // Access restrictions - all ten types evaluated (fail closed).
        foreach ($this->restrictions($objectId) as $restriction) {
            $result['restrictions'][] = $restriction;
            if (! $restriction->override_security_clearance) {
                continue;
            }
            $type = $restriction->restriction_type;
            $label = 'ICIP restriction: '.(self::RESTRICTION_TYPES[$type] ?? ucwords(str_replace('_', ' ', $type)));

            if ($type === 'community_permission_required') {
                // The one type an existing user attribute can satisfy: a steward
                // of the restricting community IS the community granting
                // permission. Everyone else is denied (fail closed).
                $isSteward = $userId && $restriction->community_id
                    && Schema::hasTable('icip_community_steward')
                    && DB::table('icip_community_steward')
                        ->where('community_id', $restriction->community_id)
                        ->where('user_id', $userId)
                        ->exists();
                if ($isSteward) {
                    $result['access_reason'] = 'Granted: steward of the restricting community';
                } else {
                    $result['allowed'] = false;
                    $result['blocked_reason'] = $label;
                }
            } elseif (in_array($type, ['elder_approval_required', 'under_consultation'], true)) {
                // Workflow states: not a binary block - surface a consultation
                // requirement that routes to icip_consultation.
                $result['requires_consultation'] = true;
                $result['consultation_restrictions'][] = $type;
            } else {
                // initiated_only, repatriation_pending, seasonal, mourning_period,
                // gender_restricted_male/female, custom - no verified user
                // attribute to grant these, so FAIL CLOSED. The date-windowed
                // types (seasonal / mourning_period) already lift automatically
                // once their window passes (see restrictions()).
                $result['allowed'] = false;
                $result['blocked_reason'] = $label;
            }
        }

        // Audit every decision on an ICIP-restricted object so a source community
        // can be given an account of who opened what.
        if (! empty($result['restrictions'])) {
            $this->logAccess($objectId, $userId, $result);
        }

        return $result;
    }

    /** Active, date-in-window cultural notices for an object. */
    private function notices(int $objectId)
    {
        return DB::table('icip_cultural_notice as n')
            ->join('icip_cultural_notice_type as t', 'n.notice_type_id', '=', 't.id')
            ->leftJoin('icip_community as c', 'n.community_id', '=', 'c.id')
            ->where('n.information_object_id', $objectId)
            ->where('t.is_active', 1)
            ->where(function ($query) {
                $query->whereNull('n.start_date')->orWhere('n.start_date', '<=', now()->toDateString());
            })
            ->where(function ($query) {
                $query->whereNull('n.end_date')->orWhere('n.end_date', '>=', now()->toDateString());
            })
            ->select(['n.*', 't.requires_acknowledgement', 't.blocks_access', 'c.name as community_name'])
            ->orderBy('t.display_order')
            ->get();
    }

    /** Active, date-in-window access restrictions for an object (#1426 window filter). */
    private function restrictions(int $objectId)
    {
        return DB::table('icip_access_restriction as r')
            ->leftJoin('icip_community as c', 'r.community_id', '=', 'c.id')
            ->where('r.information_object_id', $objectId)
            ->where(function ($query) {
                $query->whereNull('r.start_date')->orWhere('r.start_date', '<=', now()->toDateString());
            })
            ->where(function ($query) {
                $query->whereNull('r.end_date')->orWhere('r.end_date', '>=', now()->toDateString());
            })
            ->select(['r.*', 'c.name as community_name'])
            ->orderBy('r.created_at', 'desc')
            ->get();
    }

}
