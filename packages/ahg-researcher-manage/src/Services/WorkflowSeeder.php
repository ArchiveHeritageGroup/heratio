<?php

/**
 * WorkflowSeeder - Idempotent seed for AHG Researcher Manage workflow
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

namespace AhgResearcherManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ports the install.sql DELIMITER //CREATE PROCEDURE
 * ahg_researcher_seed_workflow block to PHP. PDO can't parse DELIMITER
 * (it's a mysql-CLI directive, not server SQL), so the procedure was never
 * created via PackageInstaller and the seed never ran. See issue #105.
 */
class WorkflowSeeder
{
    public static function seed(): void
    {
        if (!Schema::hasTable('ahg_workflow') || !Schema::hasTable('ahg_workflow_step')) {
            return;
        }

        DB::table('ahg_workflow')->insertOrIgnore([[
            'id'                   => 100,
            'name'                 => 'Researcher Submission Review',
            'description'          => 'Two-step review for researcher-submitted collections',
            'scope_type'           => 'global',
            'trigger_event'        => 'submit',
            'applies_to'           => 'information_object',
            'is_active'            => 1,
            'is_default'           => 0,
            'require_all_steps'    => 1,
            'notification_enabled' => 1,
        ]]);

        DB::table('ahg_workflow_step')->insertOrIgnore([
            [
                'id'              => 100,
                'workflow_id'     => 100,
                'name'            => 'Content Review',
                'step_order'      => 1,
                'step_type'       => 'review',
                'action_required' => 'approve_reject',
                'pool_enabled'    => 1,
                'instructions'    => 'Review the researcher submission for completeness, metadata quality, and adherence to descriptive standards.',
                'is_active'       => 1,
            ],
            [
                'id'              => 101,
                'workflow_id'     => 100,
                'name'            => 'Publication Approval',
                'step_order'      => 2,
                'step_type'       => 'approve',
                'action_required' => 'approve_reject',
                'pool_enabled'    => 1,
                'instructions'    => 'Final approval before publishing records. Verify repository placement and access conditions.',
                'is_active'       => 1,
            ],
        ]);
    }
}
