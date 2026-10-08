<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1525. When an LLM post-corrects OCR, the raw Tesseract text is kept
 * beside the corrected text and the row is flagged as machine-edited, so a
 * fluent rewrite can always be checked against what the engine actually read.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('iiif_ocr_text')) {
            return;
        }
        Schema::table('iiif_ocr_text', function (Blueprint $t) {
            if (! Schema::hasColumn('iiif_ocr_text', 'raw_text')) {
                $t->longText('raw_text')->nullable()->after('full_text');
            }
            if (! Schema::hasColumn('iiif_ocr_text', 'machine_edited')) {
                $t->boolean('machine_edited')->default(false)->after('raw_text');
            }
        });
    }

    public function down(): void
    {
        Schema::table('iiif_ocr_text', function (Blueprint $t) {
            foreach (['machine_edited', 'raw_text'] as $col) {
                if (Schema::hasColumn('iiif_ocr_text', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
