<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configurable physical label / barcode print templates (#1281, PSIS
     * ahgLabelPlugin parity). heratio's label tool had a hardcoded flow layout;
     * this lets operators define reusable Avery-style sheet presets (page size,
     * grid, label dimensions in mm, margins, what to show, barcode/QR source)
     * and pick a default. Seeds one default (Avery L7159 / 3x8 on A4).
     */
    public function up(): void
    {
        if (Schema::hasTable('label_template')) {
            return;
        }

        Schema::create('label_template', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('page_size', 10)->default('A4')->comment('A4, Letter');
            $table->unsignedTinyInteger('columns')->default(3);
            $table->unsignedTinyInteger('rows')->default(8);
            $table->decimal('label_width_mm', 6, 2)->default(63.50);
            $table->decimal('label_height_mm', 6, 2)->default(33.90);
            $table->decimal('margin_mm', 5, 2)->default(10.00);
            $table->decimal('gutter_mm', 5, 2)->default(2.50);
            $table->unsignedTinyInteger('font_size_pt')->default(9);
            $table->boolean('show_title')->default(true);
            $table->boolean('show_identifier')->default(true);
            $table->boolean('show_repository')->default(false);
            $table->boolean('show_barcode')->default(true);
            $table->string('barcode_source', 20)->default('identifier')->comment('identifier, accession, call_number, isbn');
            $table->boolean('show_qr')->default(false);
            $table->string('qr_target', 20)->default('url')->comment('url, identifier');
            $table->boolean('is_default')->default(false);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent();
            $table->index('is_default', 'idx_default');
        });

        // Seed a single working default so the batch sheet has a template to use.
        DB::table('label_template')->insert([
            'name' => 'Avery L7159 (A4, 3 x 8)',
            'page_size' => 'A4',
            'columns' => 3,
            'rows' => 8,
            'label_width_mm' => 63.50,
            'label_height_mm' => 33.90,
            'margin_mm' => 10.00,
            'gutter_mm' => 2.50,
            'font_size_pt' => 9,
            'show_title' => 1,
            'show_identifier' => 1,
            'show_barcode' => 1,
            'barcode_source' => 'identifier',
            'is_default' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('label_template');
    }
};
