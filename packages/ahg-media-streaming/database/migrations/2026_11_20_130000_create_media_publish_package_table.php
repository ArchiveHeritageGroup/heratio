<?php

/**
 * Platform publish packages and their distribution log (heratio#1761, GCIS 6.8.2).
 *
 * media_publish_package: one row per platform package made from an approved
 * record - who made it and when, the zip it lives in, and the status it has
 * reached (queued, made, failed, downloaded, posted). Staff post by hand and
 * fill in the posted URL and date. posted_via / external_id are there so a
 * future poster that uses the platform APIs can fill the same rows.
 *
 * Status is VARCHAR (codes in PublishPackageService, labels in ahg_dropdown
 * 'publish_status'); the platform presets are ahg_dropdown 'publish_platform'
 * rows whose metadata holds the limits.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

use AhgMediaStreaming\Services\PublishPackageService as PPS;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('media_publish_package')) {
            Schema::create('media_publish_package', function (Blueprint $t) {
                $t->id();
                $t->integer('information_object_id')->index();
                $t->integer('digital_object_id')->nullable()->comment('The access copy the package was made from');
                $t->string('platform', 50)->index();
                $t->string('status', 20)->default('queued')->index();
                $t->boolean('trimmed')->default(false);
                $t->decimal('source_duration', 10, 2)->nullable();
                $t->decimal('output_duration', 10, 2)->nullable();
                $t->integer('width')->nullable();
                $t->integer('height')->nullable();
                $t->unsignedBigInteger('byte_size')->nullable()->comment('Size of the media file inside the zip');
                $t->string('package_path', 1024)->nullable();
                $t->text('caption')->nullable();
                $t->text('error')->nullable();
                $t->integer('made_by')->nullable();
                $t->dateTime('made_at')->nullable();
                $t->integer('download_count')->default(0);
                $t->integer('downloaded_by')->nullable();
                $t->dateTime('downloaded_at')->nullable();
                $t->string('posted_url', 2048)->nullable();
                $t->date('posted_at')->nullable();
                $t->integer('posted_by')->nullable();
                $t->string('posted_via', 30)->nullable()->comment('manual today; api when a platform poster fills the row');
                $t->string('external_id', 255)->nullable()->comment('The platform post id, for an API poster');
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('ahg_dropdown')) {
            return;
        }
        $now = now();
        $sort = 0;
        foreach (PPS::DEFAULT_PRESETS as $code => $p) {
            $sort += 10;
            $exists = DB::table('ahg_dropdown')->where('taxonomy', 'publish_platform')->where('code', $code)->exists();
            if (! $exists) {
                DB::table('ahg_dropdown')->insert([
                    'taxonomy' => 'publish_platform', 'taxonomy_label' => 'Publish platforms', 'taxonomy_section' => 'digital_media',
                    'code' => $code, 'label' => $p['label'], 'sort_order' => $sort, 'is_active' => 1,
                    'metadata' => json_encode(array_diff_key($p, ['label' => 1])), 'created_at' => $now,
                ]);
            }
        }
        $sort = 0;
        foreach (PPS::STATUS_LABELS as $code => $label) {
            $sort += 10;
            if (! DB::table('ahg_dropdown')->where('taxonomy', 'publish_status')->where('code', $code)->exists()) {
                DB::table('ahg_dropdown')->insert([
                    'taxonomy' => 'publish_status', 'taxonomy_label' => 'Publish package status', 'taxonomy_section' => 'digital_media',
                    'code' => $code, 'label' => $label, 'sort_order' => $sort, 'is_active' => 1, 'created_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('media_publish_package');
        if (Schema::hasTable('ahg_dropdown')) {
            DB::table('ahg_dropdown')->whereIn('taxonomy', ['publish_platform', 'publish_status'])->delete();
        }
    }
};
