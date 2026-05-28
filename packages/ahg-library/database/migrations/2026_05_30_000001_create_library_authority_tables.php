<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates the library_subject_authority table and library_item_authority_link
     * table for authority-controlled subject heading management.
     */
    public function up(): void
    {
        if (! Schema::hasTable('library_subject_authority')) {
            Schema::create('library_subject_authority', function (Blueprint $table) {
                $table->id();
                $table->string('heading', 500);
                $table->string('subject_type', 50)->default('topic');
                $table->string('source', 50)->default('local');
                $table->string('uri', 1000)->nullable();
                $table->unsignedInteger('linked_count')->default(0);
                $table->timestamps();

                $table->unique(['heading', 'subject_type'], 'heading_type');
            });
        }

        if (! Schema::hasTable('library_item_authority_link')) {
            Schema::create('library_item_authority_link', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('library_item_id');
                $table->unsignedBigInteger('authority_id');
                $table->string('source_tag', 10)->default('650');
                $table->timestamps();

                $table->unique(['library_item_id', 'authority_id'], 'item_authority');
                $table->foreign('library_item_id')
                    ->references('id')->on('library_item')->onDelete('cascade');
                $table->foreign('authority_id')
                    ->references('id')->on('library_subject_authority')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_item_authority_link');
        Schema::dropIfExists('library_subject_authority');
    }
};
