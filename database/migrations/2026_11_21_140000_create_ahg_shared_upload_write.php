<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * heratio#1627: every file Heratio writes into a folder it shares with another
 * system's catalogue (PSIS on heratio.org) is recorded here, so it can be
 * traced, and later cleaned up, without touching the other system's files.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ahg_shared_upload_write')) {
            return;
        }
        Schema::create('ahg_shared_upload_write', function (Blueprint $t) {
            $t->id();
            $t->string('path', 1024);
            $t->string('name', 255);
            $t->unsignedInteger('digital_object_id')->nullable()->index();
            $t->string('purpose', 40)->nullable();
            $t->timestamp('written_at')->useCurrent();
            $t->index(['name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ahg_shared_upload_write');
    }
};
