<?php

/**
 * library_patron_category lookup (#1093).
 *
 * A code-keyed catalogue of patron categories (student, academic, public,
 * staff, ...) carrying the default borrowing limits for that category. The
 * `code` column aligns 1:1 with the `patron_type` value stored on
 * library_patron and with the `patron_type` ahg_dropdown taxonomy used by the
 * patron form, so a category can drive both the dropdown label and the default
 * limits applied at patron-create time.
 *
 * Enumerated patron-type *values* still come from ahg_dropdown (taxonomy =
 * patron_type) per the Dropdown Manager rule - this table holds the per-category
 * policy numbers, not a hardcoded option list.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('library_patron_category')) {
            Schema::create('library_patron_category', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('code', 30)->comment('matches library_patron.patron_type / ahg_dropdown patron_type code');
                $table->string('label', 100);
                $table->text('description')->nullable();
                $table->smallInteger('default_max_checkouts')->unsigned()->default(5);
                $table->smallInteger('default_max_renewals')->unsigned()->default(2);
                $table->smallInteger('default_max_holds')->unsigned()->default(3);
                $table->smallInteger('default_membership_months')->unsigned()->default(12);
                $table->decimal('fine_threshold', 10, 2)->nullable()->comment('overrides global fine threshold');
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrent();
                $table->unique('code', 'uk_patron_category_code');
                $table->index('is_active', 'idx_patron_category_active');
            });
        }

        $this->seedCategories();
    }

    /**
     * Seed the standard categories + the matching patron_type dropdown taxonomy.
     * Idempotent via updateOrInsert; guarded so a fresh DB without ahg_dropdown
     * still gets its categories.
     */
    protected function seedCategories(): void
    {
        $categories = [
            ['code' => 'public',   'label' => 'Public / General', 'co' => 3, 'rn' => 1, 'ho' => 2, 'mo' => 12, 'so' => 10],
            ['code' => 'student',  'label' => 'Student',          'co' => 5, 'rn' => 2, 'ho' => 3, 'mo' => 12, 'so' => 20],
            ['code' => 'academic', 'label' => 'Academic / Faculty','co' => 10,'rn' => 3, 'ho' => 5, 'mo' => 24, 'so' => 30],
            ['code' => 'staff',    'label' => 'Library Staff',     'co' => 15,'rn' => 5, 'ho' => 8, 'mo' => 24, 'so' => 40],
            ['code' => 'researcher','label' => 'Visiting Researcher','co' => 8,'rn' => 2,'ho' => 4,'mo' => 6, 'so' => 50],
        ];

        foreach ($categories as $c) {
            try {
                DB::table('library_patron_category')->updateOrInsert(
                    ['code' => $c['code']],
                    [
                        'label'                     => $c['label'],
                        'default_max_checkouts'     => $c['co'],
                        'default_max_renewals'      => $c['rn'],
                        'default_max_holds'         => $c['ho'],
                        'default_membership_months' => $c['mo'],
                        'is_active'                 => 1,
                        'sort_order'                => $c['so'],
                        'updated_at'                => now(),
                    ]
                );
            } catch (\Throwable) {
                // table not ready (shouldn't happen) - skip
            }
        }

        // Mirror the codes into the patron_type dropdown taxonomy so the patron
        // form has options out of the box. Guarded on ahg_dropdown presence.
        try {
            if (Schema::hasTable('ahg_dropdown')) {
                foreach ($categories as $idx => $c) {
                    DB::table('ahg_dropdown')->updateOrInsert(
                        ['taxonomy' => 'patron_type', 'code' => $c['code']],
                        [
                            'taxonomy_label'   => 'Library Patron Type',
                            'taxonomy_section' => 'library',
                            'label'            => $c['label'],
                            'sort_order'       => ($idx + 1) * 10,
                            'is_default'       => $c['code'] === 'public' ? 1 : 0,
                            'is_active'        => 1,
                            'updated_at'       => now(),
                        ]
                    );
                }
            }
        } catch (\Throwable) {
            // dropdown table not migrated yet - skip
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_patron_category');
    }
};
