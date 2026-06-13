<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('research_researcher', 'experience_level')) {
            Schema::table('research_researcher', function (Blueprint $table) {
                $table->string('experience_level', 20)->default('intermediate')->after('status');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('research_researcher', 'experience_level')) {
            Schema::table('research_researcher', function (Blueprint $table) {
                $table->dropColumn('experience_level');
            });
        }
    }
};
