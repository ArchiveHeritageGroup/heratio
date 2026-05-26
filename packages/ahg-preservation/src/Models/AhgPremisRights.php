<?php

/**
 * AhgPremisRights - Eloquent model for the ahg_premis_rights table.
 *
 * Represents a PREMIS 3.0 <rightsStatement> projected from the ODRL policy
 * layer (research_rights_policy). One row per granted-act / basis pairing
 * attached to an information object.
 *
 * Issue #653 Phase 1.
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

namespace AhgPreservation\Models;

use Illuminate\Database\Eloquent\Model;

class AhgPremisRights extends Model
{
    protected $table = 'ahg_premis_rights';

    public $timestamps = false;

    protected $fillable = [
        'information_object_id',
        'rights_basis',
        'rights_granted_act',
        'rights_granted_restriction',
        'applicable_dates_start',
        'applicable_dates_end',
        'source_xml',
        'created_at',
    ];

    /**
     * Allowed PREMIS rightsBasis values (spec §3.5.3.1).
     */
    public const BASES = ['copyright', 'license', 'statute', 'donor', 'policy', 'other'];

    /**
     * Allowed PREMIS rightsGranted/act values reused by Heratio.
     * The PREMIS spec leaves this open; this is the controlled vocabulary
     * we project ODRL action_type values into.
     */
    public const ACTS = ['replicate', 'migrate', 'disseminate', 'delete', 'modify', 'use'];
}
