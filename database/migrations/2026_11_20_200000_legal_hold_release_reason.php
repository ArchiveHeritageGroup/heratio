<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * heratio#1740 follow-up: releasing a legal hold must say why. The release
 * form always asked for a reason, but there was no column for it and the
 * text was dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('integrity_legal_hold') && ! Schema::hasColumn('integrity_legal_hold', 'release_reason')) {
            Schema::table('integrity_legal_hold', function (Blueprint $t) {
                $t->text('release_reason')->nullable()->after('released_at');
            });
        }
    }

    public function down(): void
    {
        // Additive only: a stored release reason is part of the record's history.
    }
};
