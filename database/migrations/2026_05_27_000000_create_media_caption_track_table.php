<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Stores caption/subtitle/description/chapter tracks for digital objects.
     * Tracks can be stored inline (vtt_content) or hosted remotely (source_url).
     * The VTT format is the canonical storage format; SRT is converted on import.
     *
     * For video accessibility: SDH (subtitle-for-deaf-and-hard-of-hearing) tracks
     * are marked with is_sdh=1 and should display by default.
     */
    public function up(): void
    {
        if (Schema::hasTable('media_caption_track')) {
            return; // table created by a previous boot-time provider hook
        }
        Schema::create('media_caption_track', function (Blueprint $table) {
            $table->id();
            $table->foreignId('digital_object_id')
                ->constrained('digital_object')
                ->cascadeOnDelete();
            $table->enum('track_type', ['caption', 'subtitle', 'description', 'chapters'])
                ->default('subtitle');
            $table->string('label', 120); // display label e.g. "English"
            $table->string('language_code', 10)->default('en'); // ISO 639-1
            $table->boolean('is_sdh')->default(false); // SDH/for deaf-hard-of-hearing
            $table->boolean('is_default')->default(false); // auto-selected on load
            $table->boolean('active')->default(true);
            // Inline VTT content (populated when user pastes/writes directly in UI)
            $table->longText('vtt_content')->nullable();
            // Remote VTT/SRT URL — source takes precedence over vtt_content
            $table->string('source_url', 500)->nullable();
            $table->timestamps();

            $table->index('digital_object_id');
            $table->index(['digital_object_id', 'active']);
            $table->index(['digital_object_id', 'language_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_caption_track');
    }
};
