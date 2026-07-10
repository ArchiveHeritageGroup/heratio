<?php

/**
 * #1357 — token table backing the anonymous, published-only share links produced
 * by PortableExportController::apiToken() and served by ::share(). Previously
 * apiToken() returned a /portable-export/share/{token} URL but neither the table
 * nor the route existed, so every generated link 404'd.
 *
 * A 128-bit token + expiry + optional download cap gate the public download; the
 * published-only safety check lives in the controller. This is a new plugin table
 * — it does not touch any base-AtoM table.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('portable_export_share_token')) {
            Schema::create('portable_export_share_token', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('export_id')->index();
                $table->string('token', 64)->unique();
                $table->timestamp('expires_at')->nullable();
                $table->unsignedInteger('max_downloads')->nullable();
                $table->unsignedInteger('download_count')->default(0);
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('portable_export_share_token');
    }
};
