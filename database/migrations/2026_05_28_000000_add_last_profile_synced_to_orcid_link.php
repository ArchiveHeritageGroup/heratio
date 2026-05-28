<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * ORCID profile pull (researcher self-service "Pull from ORCID" + register
     * auto-populate). Distinguishes profile-sync time from works-sync time so
     * the in-sync UI can show both independently.
     */
    public function up(): void
    {
        if (Schema::hasTable('researcher_orcid_link')
            && !Schema::hasColumn('researcher_orcid_link', 'last_profile_synced_at')) {
            Schema::table('researcher_orcid_link', function (Blueprint $t) {
                $t->dateTime('last_profile_synced_at')->nullable()->after('last_synced_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('researcher_orcid_link')
            && Schema::hasColumn('researcher_orcid_link', 'last_profile_synced_at')) {
            Schema::table('researcher_orcid_link', function (Blueprint $t) {
                $t->dropColumn('last_profile_synced_at');
            });
        }
    }
};
