<?php

/**
 * Live events (heratio#1743, GCIS 5.1.1): a live stream scheduled on a record,
 * ingested over RTMP by ahg:live-ingest, played live as HLS and archived as
 * the record's digital object when it ends.
 *
 * live_event: one row per event. status is VARCHAR (codes in
 * LiveStreamService, labels in ahg_dropdown 'live_event_status'). The worker
 * claims a row with worker_started_at + heartbeat_at; a heartbeat older than
 * LiveStreamService::HEARTBEAT_STALE seconds means no worker holds it.
 *
 * Settings (group 'media', edited under Settings > Media Player): the ingest
 * host and port shown to the encoder, how long the worker waits for the
 * encoder, the longest event, and the queue the worker job goes to. Values are
 * the code defaults; existing rows are left alone.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

use AhgMediaStreaming\Services\LiveStreamService as LSS;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('live_event')) {
            Schema::create('live_event', function (Blueprint $t) {
                $t->id();
                $t->integer('information_object_id')->index();
                $t->string('title', 255);
                $t->text('description')->nullable();
                $t->dateTime('scheduled_at')->index();
                $t->string('status', 20)->default('scheduled')->index();
                $t->string('stream_key', 64)->unique();
                $t->integer('ingest_port')->nullable()->comment('Port of the last ingest run');
                $t->dateTime('start_requested_at')->nullable();
                $t->dateTime('worker_started_at')->nullable();
                $t->dateTime('heartbeat_at')->nullable();
                $t->string('worker_host', 255)->nullable();
                $t->integer('worker_pid')->nullable();
                $t->dateTime('stop_requested_at')->nullable();
                $t->dateTime('started_at')->nullable()->comment('When the first frames arrived');
                $t->dateTime('ended_at')->nullable();
                $t->integer('recording_digital_object_id')->nullable();
                $t->text('error')->nullable();
                $t->integer('created_by')->nullable();
                $t->timestamps();
            });
        }

        if (Schema::hasTable('ahg_settings')) {
            foreach ([
                'live_ingest_host' => ['', 'Host name or address encoders send live streams to. Empty = the host in the site address.'],
                'live_ingest_port' => ['1935', 'RTMP port the live ingest worker listens on. The firewall must allow it from the encoder\'s network.'],
                'live_wait_minutes' => ['60', 'How long a started live event waits for the encoder before it is marked failed.'],
                'live_max_hours' => ['8', 'Longest live event, in hours (1 to 12). The worker stops the stream after this.'],
                'live_queue' => ['live', 'Queue the live ingest worker job goes to. Needs its own long-running queue worker.'],
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

        if (Schema::hasTable('ahg_dropdown')) {
            $sort = 0;
            foreach (LSS::STATUS_LABELS as $code => $label) {
                $sort += 10;
                if (! DB::table('ahg_dropdown')->where('taxonomy', 'live_event_status')->where('code', $code)->exists()) {
                    DB::table('ahg_dropdown')->insert([
                        'taxonomy' => 'live_event_status', 'taxonomy_label' => 'Live event status', 'taxonomy_section' => 'digital_media',
                        'code' => $code, 'label' => $label, 'sort_order' => $sort, 'is_active' => 1, 'created_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('live_event');
    }
};
