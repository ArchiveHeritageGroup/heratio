<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Completes the library_subject_authority and creates library_item_authority_link.
     * Idempotent — handles the table created by the partial batch-22 run.
     *
     * Batch-22 state: id, heading, lc_label, rda_label, authorized_form,
     * heading_normalized, heading_type, source, lcsh_id, lcsh_uri,
     * suggested_dewey, suggested_lcc, broader_terms, narrower_terms,
     * related_terms, usage_count, first_used_at, last_used_at, created_at.
     * Missing: updated_at, linked_count, notes, vocab_uri, vocab_code, uri.
     */
    public function up(): void
    {
        if (! Schema::hasTable('library_subject_authority')) {
            // Fresh install: create the full schema
            Schema::create('library_subject_authority', function (Blueprint $table) {
                $table->id();
                $table->string('heading', 500)->nullable();
                $table->string('lc_label', 500)->nullable();
                $table->string('rda_label', 500)->nullable();
                $table->string('authorized_form', 500)->nullable();
                $table->string('subject_type', 50)->default('topic');
                $table->string('vocab_uri', 500)->nullable();
                $table->string('vocab_code', 50)->nullable();
                $table->string('source', 100)->nullable();
                $table->string('uri', 500)->nullable();
                $table->unsignedInteger('linked_count')->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['lc_label', 'subject_type'], 'heading_type');
                $table->index('lc_label', 'idx_auth_lc_label');
                $table->index('subject_type', 'idx_auth_subject_type');
            });
        } else {
            // Existing batch-22 table: add only what is missing, column-by-column
            Schema::table('library_subject_authority', function (Blueprint $table) {
                if (! Schema::hasColumn('library_subject_authority', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
                if (! Schema::hasColumn('library_subject_authority', 'linked_count')) {
                    $table->unsignedInteger('linked_count')->default(0);
                }
                if (! Schema::hasColumn('library_subject_authority', 'notes')) {
                    $table->text('notes')->nullable();
                }
                if (! Schema::hasColumn('library_subject_authority', 'vocab_uri')) {
                    $table->string('vocab_uri', 500)->nullable();
                }
                if (! Schema::hasColumn('library_subject_authority', 'vocab_code')) {
                    $table->string('vocab_code', 50)->nullable();
                }
                if (! Schema::hasColumn('library_subject_authority', 'uri')) {
                    $table->string('uri', 500)->nullable();
                }
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