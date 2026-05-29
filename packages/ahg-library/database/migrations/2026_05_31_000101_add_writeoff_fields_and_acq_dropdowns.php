<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 *
 * GRAP 103 / IPSAS 17 disposal support for acquisitions (heratio#1091):
 *   - Adds written_off_reason / written_off_by / written_off_date to library_order.
 *   - Seeds the acq_disposal_reason and acq_reason dropdown taxonomies
 *     (INSERT IGNORE, so re-running is harmless and existing rows win).
 *
 * Enumerated values live in ahg_dropdown (never ENUM columns) per the Dropdown
 * Manager convention.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('library_order')) {
            Schema::table('library_order', function (Blueprint $table) {
                if (!Schema::hasColumn('library_order', 'written_off_reason')) {
                    $table->string('written_off_reason', 50)->nullable()->after('approved_date');
                }
                if (!Schema::hasColumn('library_order', 'written_off_by')) {
                    $table->string('written_off_by', 100)->nullable()->after('written_off_reason');
                }
                if (!Schema::hasColumn('library_order', 'written_off_date')) {
                    $table->date('written_off_date')->nullable()->after('written_off_by');
                }
            });
        }

        $this->seedDropdowns();
    }

    public function down(): void
    {
        if (Schema::hasTable('library_order')) {
            Schema::table('library_order', function (Blueprint $table) {
                foreach (['written_off_reason', 'written_off_by', 'written_off_date'] as $col) {
                    if (Schema::hasColumn('library_order', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        // Dropdown rows are left in place on rollback (shared reference data).
    }

    /**
     * INSERT IGNORE the disposal-reason and acquisition-reason taxonomies.
     * Guarded: a minimal install may not have ahg_dropdown yet.
     */
    private function seedDropdowns(): void
    {
        if (!Schema::hasTable('ahg_dropdown')) {
            return;
        }

        $taxonomies = [
            'acq_disposal_reason' => [
                'label'   => 'Acquisition Disposal Reason',
                'options' => [
                    ['code' => 'damaged',   'label' => 'Damaged',           'default' => true],
                    ['code' => 'lost',      'label' => 'Lost',              'default' => false],
                    ['code' => 'obsolete',  'label' => 'Obsolete',          'default' => false],
                    ['code' => 'duplicate', 'label' => 'Duplicate',         'default' => false],
                    ['code' => 'withdrawn', 'label' => 'Withdrawn',         'default' => false],
                ],
            ],
            'acq_reason' => [
                'label'   => 'Acquisition Reason Code',
                'options' => [
                    ['code' => 'purchase', 'label' => 'Purchase', 'default' => true],
                    ['code' => 'gift',     'label' => 'Gift',     'default' => false],
                    ['code' => 'exchange', 'label' => 'Exchange', 'default' => false],
                    ['code' => 'deposit',  'label' => 'Deposit',  'default' => false],
                    ['code' => 'approval', 'label' => 'Approval', 'default' => false],
                ],
            ],
            'library_vendor_type' => [
                'label'   => 'Library Vendor Type',
                'options' => [
                    ['code' => 'local',         'label' => 'Local',         'default' => true],
                    ['code' => 'international', 'label' => 'International',   'default' => false],
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
                    'taxonomy_section' => 'library',
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
