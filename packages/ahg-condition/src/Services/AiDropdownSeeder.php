<?php

/**
 * AiDropdownSeeder - Idempotent seed for AHG Condition AI dropdowns
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

namespace AhgCondition\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ports the install.sql DELIMITER //CREATE PROCEDURE
 * ahg_condition_seed_ai_dropdowns block to PHP. PDO can't parse DELIMITER
 * (it's a mysql-CLI directive, not server SQL), so the procedure was never
 * created via PackageInstaller and the seed never ran. See issue #105.
 */
class AiDropdownSeeder
{
    public static function seed(): void
    {
        if (!Schema::hasTable('ahg_dropdown')) {
            return;
        }

        DB::table('ahg_dropdown')->insertOrIgnore([
            ['taxonomy' => 'ai_assessment_source', 'taxonomy_label' => 'AI Assessment Source', 'taxonomy_section' => 'ai', 'code' => 'manual',       'label' => 'Manual Upload',     'color' => '#6c757d', 'sort_order' => 10, 'is_default' => 1],
            ['taxonomy' => 'ai_assessment_source', 'taxonomy_label' => 'AI Assessment Source', 'taxonomy_section' => 'ai', 'code' => 'bulk',         'label' => 'Bulk Scan',         'color' => '#0d6efd', 'sort_order' => 20, 'is_default' => 0],
            ['taxonomy' => 'ai_assessment_source', 'taxonomy_label' => 'AI Assessment Source', 'taxonomy_section' => 'ai', 'code' => 'auto',         'label' => 'Auto (On Upload)',  'color' => '#198754', 'sort_order' => 30, 'is_default' => 0],
            ['taxonomy' => 'ai_assessment_source', 'taxonomy_label' => 'AI Assessment Source', 'taxonomy_section' => 'ai', 'code' => 'api',          'label' => 'External API',      'color' => '#6f42c1', 'sort_order' => 40, 'is_default' => 0],
            ['taxonomy' => 'ai_assessment_source', 'taxonomy_label' => 'AI Assessment Source', 'taxonomy_section' => 'ai', 'code' => 'manual_entry', 'label' => 'Manual Entry',      'color' => '#495057', 'sort_order' => 50, 'is_default' => 0],

            ['taxonomy' => 'ai_service_tier', 'taxonomy_label' => 'AI Service Tier', 'taxonomy_section' => 'ai', 'code' => 'free',       'label' => 'Free (50/month)',           'color' => '#6c757d', 'sort_order' => 10, 'is_default' => 1],
            ['taxonomy' => 'ai_service_tier', 'taxonomy_label' => 'AI Service Tier', 'taxonomy_section' => 'ai', 'code' => 'standard',   'label' => 'Standard (500/month)',      'color' => '#0d6efd', 'sort_order' => 20, 'is_default' => 0],
            ['taxonomy' => 'ai_service_tier', 'taxonomy_label' => 'AI Service Tier', 'taxonomy_section' => 'ai', 'code' => 'pro',        'label' => 'Professional (5000/month)', 'color' => '#198754', 'sort_order' => 30, 'is_default' => 0],
            ['taxonomy' => 'ai_service_tier', 'taxonomy_label' => 'AI Service Tier', 'taxonomy_section' => 'ai', 'code' => 'enterprise', 'label' => 'Enterprise (Unlimited)',    'color' => '#dc3545', 'sort_order' => 40, 'is_default' => 0],

            ['taxonomy' => 'ai_confidence_level', 'taxonomy_label' => 'AI Confidence Level', 'taxonomy_section' => 'ai', 'code' => 'low',       'label' => 'Low (<50%)',       'color' => '#dc3545', 'sort_order' => 10, 'is_default' => 0],
            ['taxonomy' => 'ai_confidence_level', 'taxonomy_label' => 'AI Confidence Level', 'taxonomy_section' => 'ai', 'code' => 'medium',    'label' => 'Medium (50-75%)',  'color' => '#ffc107', 'sort_order' => 20, 'is_default' => 0],
            ['taxonomy' => 'ai_confidence_level', 'taxonomy_label' => 'AI Confidence Level', 'taxonomy_section' => 'ai', 'code' => 'high',      'label' => 'High (75-90%)',    'color' => '#198754', 'sort_order' => 30, 'is_default' => 1],
            ['taxonomy' => 'ai_confidence_level', 'taxonomy_label' => 'AI Confidence Level', 'taxonomy_section' => 'ai', 'code' => 'very_high', 'label' => 'Very High (>90%)', 'color' => '#0d6efd', 'sort_order' => 40, 'is_default' => 0],
        ]);
    }
}
