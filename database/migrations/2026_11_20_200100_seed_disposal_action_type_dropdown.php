<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * heratio#1740 follow-up: the disposal action types offered when a disposal
 * is initiated come from the Dropdown Manager (taxonomy rm_disposal_action_type)
 * instead of a list in the form. The codes are the ones rm_disposal_action
 * already stores, so existing rows keep their meaning. Labels, order,
 * descriptions and which types are offered can be changed in the Dropdown
 * Manager; a new code also needs code support before it can be executed.
 *
 * Separate from the older rm_disposal_action taxonomy, which describes a
 * retention schedule's end-of-retention action and uses "permanent" where a
 * disposal action stores "retain_permanent".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ahg_dropdown')) {
            return;
        }
        foreach ([
            ['destroy', 'Destroy', 'Permanently delete all digital objects and generate destruction certificate.', 'danger', 10],
            ['transfer_archives', 'Transfer to Archives', 'Transfer records to an archival institution.', 'info', 20],
            ['transfer_external', 'Transfer External', 'Transfer records to an external organization.', 'info', 30],
            ['retain_permanent', 'Retain Permanently', 'Mark records for permanent retention.', 'success', 40],
            ['review', 'Review', 'Flag for review before determining disposal action.', 'warning', 50],
        ] as [$code, $label, $description, $badge, $order]) {
            if (DB::table('ahg_dropdown')->where('taxonomy', 'rm_disposal_action_type')->where('code', $code)->exists()) {
                continue;
            }
            DB::table('ahg_dropdown')->insert([
                'taxonomy' => 'rm_disposal_action_type', 'taxonomy_label' => 'Disposal action type', 'taxonomy_section' => 'records',
                'code' => $code, 'label' => $label, 'sort_order' => $order, 'is_default' => 0, 'is_active' => 1,
                'metadata' => json_encode(['description' => $description, 'badge' => $badge]),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Additive only.
    }
};
