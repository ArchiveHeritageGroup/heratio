<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * heratio#1771 media protection: video watermark, encrypted HLS (AES-128)
 * and "cleared for release" on audio/video media.
 *
 * Settings go in the media group (Settings > Media Player). Every switch is
 * off by default, so nothing changes until an administrator turns it on.
 * Existing rows are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('media_release_clearance')) {
            Schema::create('media_release_clearance', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->integer('digital_object_id')->unique();   // the master
                $t->integer('object_id')->nullable()->index(); // its record
                $t->string('status', 20)->default('pending');  // pending | cleared | withheld (MediaProtection::STATUS_*)
                $t->text('note')->nullable();
                $t->integer('cleared_by')->nullable();
                $t->dateTime('cleared_at')->nullable();
                $t->integer('updated_by')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('ahg_settings')) {
            return;
        }
        foreach ([
            'media_video_watermark_enabled' => ['false', 'Burn a watermark into the playback copy and HLS of videos whose record carries a watermark (the same rule as image watermarks). The original is never changed.'],
            'media_video_watermark_type' => ['image', 'image = the record\'s watermark image; text = the text below (or the site name).'],
            'media_video_watermark_text' => ['', 'Text for a text watermark. Empty = the site name.'],
            'media_video_watermark_position' => ['bottom-right', 'top-left, top-center, top-right, center, bottom-left, bottom-center or bottom-right.'],
            'media_video_watermark_opacity' => ['0.4', 'Watermark opacity, 0.05 to 1.'],
            'media_hls_encrypt' => ['false', 'Encrypt HLS segments with AES-128. Each rendition gets its own key, served only to viewers allowed to play the record.'],
            'media_clearance_required' => ['false', 'Audio and video play only for staff until someone clears them for release.'],
        ] as $key => [$value, $desc]) {
            if (! DB::table('ahg_settings')->where('setting_key', $key)->exists()) {
                $row = ['setting_key' => $key, 'setting_value' => $value, 'setting_group' => 'media'];
                if (Schema::hasColumn('ahg_settings', 'description')) {
                    $row['description'] = $desc;
                }
                DB::table('ahg_settings')->insert($row);
            }
        }
    }

    public function down(): void
    {
        // Additive only.
    }
};
