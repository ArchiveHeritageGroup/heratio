<?php

/**
 * heratio#1571 - opt-in install count. One row per install that said yes in
 * docker/install-heratio.sh: a random install id, the version and the CPU
 * architecture. Nothing that identifies an organisation, and no IP address.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('heratio_install_ping')) {
            return;
        }

        Schema::create('heratio_install_ping', function (Blueprint $table) {
            $table->char('install_id', 36)->primary()->comment('Random UUID made by the installer');
            $table->string('version', 64);
            $table->string('arch', 32);
            $table->string('source', 20)->default('installer');
            $table->unsignedInteger('pings')->default(1)->comment('Reinstalls reusing the id');
            $table->timestamp('first_seen')->useCurrent();
            $table->timestamp('last_seen')->useCurrent();
            $table->index('first_seen', 'idx_install_ping_first');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heratio_install_ping');
    }
};
