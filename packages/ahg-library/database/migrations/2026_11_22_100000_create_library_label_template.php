<?php

/**
 * Label stationery templates and the label format dropdown - #1476 item 2.
 *
 * Built-in rows describe common sheet layouts by geometry; operators add their
 * own for any other stock. Additive and safe to run repeatedly.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('library_label_template')) {
            Schema::create('library_label_template', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('code', 50)->unique();
                $table->string('name', 150);
                $table->decimal('page_width_mm', 6, 2);
                $table->decimal('page_height_mm', 6, 2);
                $table->decimal('margin_top_mm', 6, 2);
                $table->decimal('margin_left_mm', 6, 2);
                $table->decimal('label_width_mm', 6, 2);
                $table->decimal('label_height_mm', 6, 2);
                $table->decimal('pitch_x_mm', 6, 2);
                $table->decimal('pitch_y_mm', 6, 2);
                $table->unsignedSmallInteger('columns_across');
                $table->unsignedSmallInteger('rows_down');
                $table->boolean('is_builtin')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        $now = now();
        // code, name, page w/h, top, left, label w/h, pitch x/y, across, down
        $builtin = [
            ['a4_21_63x38', 'A4 - 21 labels, 63.5 x 38.1 mm', 210, 297, 15.15, 7.25, 63.5, 38.1, 66, 38.1, 3, 7],
            ['a4_24_63x33', 'A4 - 24 labels, 63.5 x 33.9 mm', 210, 297, 12.9, 7.25, 63.5, 33.9, 66, 33.9, 3, 8],
            ['a4_14_99x38', 'A4 - 14 labels, 99.1 x 38.1 mm', 210, 297, 15.15, 4.65, 99.1, 38.1, 101.6, 38.1, 2, 7],
            ['a4_65_38x21', 'A4 - 65 labels, 38.1 x 21.2 mm', 210, 297, 10.7, 4.75, 38.1, 21.2, 40.6, 21.2, 5, 13],
            ['letter_30_67x25', 'US Letter - 30 labels, 66.7 x 25.4 mm', 215.9, 279.4, 12.7, 4.76, 66.68, 25.4, 69.85, 25.4, 3, 10],
        ];
        foreach ($builtin as $r) {
            DB::table('library_label_template')->insertOrIgnore([
                'code' => $r[0], 'name' => $r[1],
                'page_width_mm' => $r[2], 'page_height_mm' => $r[3],
                'margin_top_mm' => $r[4], 'margin_left_mm' => $r[5],
                'label_width_mm' => $r[6], 'label_height_mm' => $r[7],
                'pitch_x_mm' => $r[8], 'pitch_y_mm' => $r[9],
                'columns_across' => $r[10], 'rows_down' => $r[11],
                'is_builtin' => 1, 'is_active' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        if (Schema::hasTable('ahg_dropdown')) {
            foreach ([['spine', 'Spine label', 10, 1], ['barcode', 'Barcode label', 20, 0], ['combined', 'Spine and barcode', 30, 0]] as [$code, $label, $sort, $default]) {
                DB::table('ahg_dropdown')->insertOrIgnore([
                    'taxonomy' => 'library_label_format',
                    'taxonomy_label' => 'Library Label Format',
                    'taxonomy_section' => 'library',
                    'code' => $code, 'label' => $label, 'sort_order' => $sort,
                    'is_default' => $default, 'is_active' => 1,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Additive: templates may hold an operator's custom stock definitions.
    }
};
