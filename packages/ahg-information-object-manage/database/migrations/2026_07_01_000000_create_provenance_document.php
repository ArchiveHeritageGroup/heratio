<?php

/**
 * Create provenance_document — supporting-evidence attachments for the
 * per-IO provenance chain (deeds, bills of sale, auction catalogues, customs
 * papers, correspondence, …).
 *
 * Heratio's provenance stack is information-object-keyed (see the
 * consolidate_provenance_stacks migration), so — unlike the AtoM
 * ahgProvenancePlugin original which hung documents off provenance_record /
 * provenance_event — we key each document to information_object_id, with an
 * optional provenance_entry_id when the document evidences one specific
 * custody link in the chain.
 *
 * Files live privately under storage/app/private/provenance-docs/{ioId}/ and
 * are served through a gated download route; external_url carries a reference
 * when the evidence lives in another repository. is_public mirrors the record
 * flag so operators can attach sensitive legal paperwork without exposing it.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('provenance_document')) {
            return;
        }

        Schema::create('provenance_document', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('information_object_id')->index();
            $table->unsignedInteger('provenance_entry_id')->nullable()->index();

            $table->string('document_type', 64)->default('other');
            $table->string('title', 500)->nullable();
            $table->text('description')->nullable();
            $table->date('document_date')->nullable();
            $table->string('document_date_text', 255)->nullable();

            // File storage (private disk)
            $table->string('filename', 500)->nullable();
            $table->string('original_filename', 500)->nullable();
            $table->string('file_path', 1000)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            // External reference (evidence held elsewhere)
            $table->string('external_url', 1000)->nullable();
            $table->string('archive_reference', 500)->nullable();

            $table->boolean('is_public')->default(false);
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provenance_document');
    }
};
