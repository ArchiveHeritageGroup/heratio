<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Researcher Offline — sync-back targets for work done in the /research/mobile
 * offline package. Notes and sources reuse research_annotation; collection-item
 * notes update research_collection_item. These two tables cover the remaining
 * kinds: metadata suggestions (curator-reviewed, never a live edit) and files.
 *
 * Additive + idempotent. Mirrors the PSIS ahgResearchPlugin
 * migration_researcher_offline.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('research_metadata_suggestion')) {
            Schema::create('research_metadata_suggestion', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->integer('researcher_id');
                $table->integer('object_id')->comment('information_object.id the suggestion is about');
                $table->string('field', 191)->comment('e.g. Title, Dates, Scope and content');
                $table->text('suggestion');
                $table->string('status', 20)->default('open')->comment('open, accepted, rejected');
                $table->integer('reviewed_by')->nullable()->comment('user_id of the curator who actioned it');
                $table->dateTime('reviewed_at')->nullable();
                $table->dateTime('created_at')->useCurrent();
                $table->index('researcher_id', 'idx_rms_researcher');
                $table->index('object_id', 'idx_rms_object');
                $table->index('status', 'idx_rms_status');
            });
        }

        if (! Schema::hasTable('research_offline_attachment')) {
            Schema::create('research_offline_attachment', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->integer('researcher_id');
                $table->integer('object_id')->nullable()->comment('information_object.id the file relates to');
                $table->string('file_name', 500);
                $table->string('mime_type', 255)->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->string('file_path', 1000)->comment('path relative to the uploads root');
                $table->dateTime('created_at')->useCurrent();
                $table->index('researcher_id', 'idx_roa_researcher');
                $table->index('object_id', 'idx_roa_object');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('research_offline_attachment');
        Schema::dropIfExists('research_metadata_suggestion');
    }
};
