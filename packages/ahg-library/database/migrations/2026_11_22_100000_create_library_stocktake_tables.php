<?php

/**
 * Physical stocktake (#1474 item 5): a count, its scans and its variance lines.
 * Mirrored in database/core/04_library_stocktake.sql for fresh installs.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

use AhgLibrary\Services\LibraryStocktakeService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('library_stocktake')) {
            Schema::create('library_stocktake', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 255);
                $table->integer('branch_id')->nullable()->index('idx_stocktake_branch');
                $table->string('shelf_from', 100)->nullable();
                $table->string('shelf_to', 100)->nullable();
                $table->string('status', 30)->default('open')->index('idx_stocktake_status');
                $table->unsignedInteger('created_by')->nullable();
                $table->dateTime('started_at');
                $table->dateTime('reconciled_at')->nullable();
                $table->unsignedInteger('signed_off_by')->nullable();
                $table->dateTime('signed_off_at')->nullable();
                $table->text('sign_off_note')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('library_stocktake_scan')) {
            Schema::create('library_stocktake_scan', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('stocktake_id');
                $table->string('barcode', 50);
                $table->string('scanned_shelf', 100)->nullable();
                $table->unsignedInteger('scan_count')->default(1);
                $table->unsignedInteger('scanned_by')->nullable();
                $table->dateTime('scanned_at');
                $table->unique(['stocktake_id', 'barcode'], 'uq_stocktake_scan');
            });
        }

        if (! Schema::hasTable('library_stocktake_line')) {
            Schema::create('library_stocktake_line', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('stocktake_id');
                $table->unsignedBigInteger('copy_id')->nullable();
                $table->string('barcode', 50)->nullable();
                $table->unsignedBigInteger('library_item_id')->nullable();
                $table->unsignedInteger('information_object_id')->nullable();
                $table->string('copy_status', 30)->nullable();
                $table->string('home_shelf', 100)->nullable();
                $table->string('scanned_shelf', 100)->nullable();
                $table->string('classification', 30);
                $table->string('reason_code', 40)->nullable();
                $table->unsignedInteger('heritage_asset_id')->nullable();
                $table->decimal('carrying_snapshot', 18, 2)->nullable();
                $table->decimal('proposed_amount', 18, 2)->nullable();
                $table->decimal('impairment_amount', 18, 2)->nullable();
                $table->string('oci_movement_ids', 255)->nullable();
                $table->unsignedInteger('posted_by')->nullable();
                $table->dateTime('posted_at')->nullable();
                $table->index(['stocktake_id', 'classification'], 'idx_stocktake_line_class');
                $table->index('heritage_asset_id', 'idx_stocktake_line_asset');
            });
        }

        // One row per copy ever posted as a loss, across all stocktakes.
        if (! Schema::hasTable('library_stocktake_posting')) {
            Schema::create('library_stocktake_posting', function (Blueprint $table) {
                $table->unsignedBigInteger('copy_id')->primary();
                $table->unsignedBigInteger('line_id');
                $table->unsignedBigInteger('stocktake_id');
                $table->unsignedInteger('posted_by')->nullable();
                $table->dateTime('posted_at');
            });
        }

        if (Schema::hasTable('ahg_dropdown')) {
            foreach (LibraryStocktakeService::dropdownSeed() as $taxonomy => $def) {
                $sort = 10;
                foreach ($def['options'] as $code => $label) {
                    DB::table('ahg_dropdown')->insertOrIgnore([
                        'taxonomy' => $taxonomy,
                        'taxonomy_label' => $def['label'],
                        'taxonomy_section' => 'library',
                        'code' => $code,
                        'label' => $label,
                        'sort_order' => $sort,
                        'is_default' => 0,
                        'is_active' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $sort += 10;
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_stocktake_posting');
        Schema::dropIfExists('library_stocktake_line');
        Schema::dropIfExists('library_stocktake_scan');
        Schema::dropIfExists('library_stocktake');
    }
};
