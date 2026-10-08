<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1537. FormService::resolveTemplate() and FormsController read and
 * write ahg_form_template.config, but the package's install.sql never created
 * it, so on any install built from that script template resolution threw and
 * no form template ever applied. Live instances already have the column
 * (nullable TEXT); this adds it where it is missing, and does nothing elsewhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ahg_form_template') && ! Schema::hasColumn('ahg_form_template', 'config')) {
            Schema::table('ahg_form_template', function (Blueprint $t) {
                $t->text('config')->nullable()->after('config_json');
            });
        }
    }

    public function down(): void
    {
        // Left in place: live instances had the column before this migration.
    }
};
