<?php

/**
 * #1351 — seed the controlled vocabularies behind the ahg-acl security views
 * (audit action-type filter, access-request type/priority, watermark position)
 * so those selects source ahg_dropdown instead of hardcoded <option> lists.
 *
 * Enumerated values live in ahg_dropdown (never ENUM columns) per the Dropdown
 * Manager convention. Codes match what the consuming code already stores:
 * security_audit_log.action values and WatermarkService position codes.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded: a minimal install may not have ahg_dropdown yet.
        if (!Schema::hasTable('ahg_dropdown')) {
            return;
        }

        $taxonomies = [
            'security_audit_action' => [
                'label'   => 'Security Audit Action',
                'section' => 'security',
                'options' => [
                    ['code' => 'view',          'label' => 'View',     'default' => false],
                    ['code' => 'download',      'label' => 'Download', 'default' => false],
                    ['code' => 'print',         'label' => 'Print',    'default' => false],
                    ['code' => 'classify',      'label' => 'Classify', 'default' => false],
                    ['code' => 'access_denied', 'label' => 'Denied',   'default' => false],
                ],
            ],
            'security_access_request_type' => [
                'label'   => 'Security Access Request Type',
                'section' => 'security',
                'options' => [
                    ['code' => 'view',     'label' => 'View Only', 'default' => true],
                    ['code' => 'download', 'label' => 'Download',  'default' => false],
                    ['code' => 'print',    'label' => 'Print',     'default' => false],
                ],
            ],
            'security_access_request_priority' => [
                'label'   => 'Security Access Request Priority',
                'section' => 'security',
                'options' => [
                    ['code' => 'normal',    'label' => 'Normal',    'default' => true],
                    ['code' => 'urgent',    'label' => 'Urgent',    'default' => false],
                    ['code' => 'immediate', 'label' => 'Immediate', 'default' => false],
                ],
            ],
            'watermark_position' => [
                'label'   => 'Watermark Position',
                'section' => 'digital_media',
                'options' => [
                    ['code' => 'center',       'label' => 'Center',       'default' => false],
                    ['code' => 'top_left',     'label' => 'Top Left',     'default' => false],
                    ['code' => 'top_right',    'label' => 'Top Right',    'default' => false],
                    ['code' => 'bottom_left',  'label' => 'Bottom Left',  'default' => false],
                    ['code' => 'bottom_right', 'label' => 'Bottom Right', 'default' => true],
                    ['code' => 'tile',         'label' => 'Tile',         'default' => false],
                ],
            ],
        ];

        $now = now();
        foreach ($taxonomies as $taxonomy => $def) {
            foreach ($def['options'] as $idx => $opt) {
                // INSERT IGNORE semantics: skip if (taxonomy, code) already present.
                $exists = DB::table('ahg_dropdown')
                    ->where('taxonomy', $taxonomy)
                    ->where('code', $opt['code'])
                    ->exists();
                if ($exists) {
                    continue;
                }
                DB::table('ahg_dropdown')->insert([
                    'taxonomy'         => $taxonomy,
                    'taxonomy_label'   => $def['label'],
                    'taxonomy_section' => $def['section'],
                    'code'             => $opt['code'],
                    'label'            => $opt['label'],
                    'sort_order'       => ($idx + 1) * 10,
                    'is_default'       => $opt['default'] ? 1 : 0,
                    'is_active'        => 1,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);
            }
        }
    }
};
