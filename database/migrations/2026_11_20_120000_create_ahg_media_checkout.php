<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * heratio#1757 / #1758: check-out and check-in of a record's master file for
 * editing in an outside tool. One row per check-out. status is a VARCHAR
 * (checked_out, checked_in, cancelled). active_digital_object_id holds the
 * digital object id only while the file is checked out and is NULL after, so
 * the unique index allows one open check-out per file and any number of
 * closed ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ahg_media_checkout')) {
            return;
        }
        Schema::create('ahg_media_checkout', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('digital_object_id')->index();
            $t->unsignedInteger('active_digital_object_id')->nullable()->unique();
            $t->unsignedInteger('information_object_id')->nullable()->index();
            $t->unsignedInteger('user_id')->index();
            $t->string('status', 20)->default('checked_out')->index();
            $t->string('note', 1000)->nullable();
            $t->string('version_at_checkout', 20)->nullable();
            $t->string('sha256_at_checkout', 64)->nullable();
            $t->dateTime('checked_out_at');
            $t->dateTime('closed_at')->nullable();
            $t->unsignedInteger('closed_by')->nullable();
            $t->string('close_note', 1000)->nullable();
            $t->unsignedInteger('file_version_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ahg_media_checkout');
    }
};
