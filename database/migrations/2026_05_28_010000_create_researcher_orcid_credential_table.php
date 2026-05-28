<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Per-researcher ORCID OAuth client credentials. Each researcher registers
     * their own free client at orcid.org/developer-tools and stores their
     * Client ID + Secret here, so ORCID Connect & Sync is self-service and does
     * not depend on a single global .env client an admin would have to manage
     * for every researcher. Secret is encrypted at rest.
     */
    public function up(): void
    {
        if (Schema::hasTable('researcher_orcid_credential')) {
            return;
        }
        Schema::create('researcher_orcid_credential', function (Blueprint $t) {
            $t->id();
            $t->integer('researcher_id');               // signed int to match research_researcher.id
            $t->string('client_id', 100);
            $t->text('client_secret_encrypted')->nullable();
            $t->string('redirect_uri', 500)->nullable();
            $t->string('api_base', 100)->nullable();     // pub.orcid.org (default) or api.orcid.org
            $t->timestamps();
            $t->unique('researcher_id', 'uq_orcid_cred_researcher');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('researcher_orcid_credential');
    }
};
