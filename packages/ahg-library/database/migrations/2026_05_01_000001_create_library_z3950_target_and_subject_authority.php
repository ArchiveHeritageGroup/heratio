<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create library_z3950_target — Z39.50 server configuration for copy cataloguing.
     *
     * Targets are stored here so staff can manage multiple servers without
     * touching code. The yaz PECL extension connects to these on search.
     */
    public function up(): void
    {
        if (! Schema::hasTable('library_z3950_target')) {
            Schema::create('library_z3950_target', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255)->comment('Display name for the target');
                $table->string('host', 255)->comment('Hostname or IP address');
                $table->unsignedInteger('port')->default(210)->comment('Z39.50 port, default 210');
                $table->string('database_name', 255)->default('Default')
                    ->comment('Target database / database set name');
                $table->string('syntax', 50)->default('USmarc')
                    ->comment('Record syntax: USmarc | MARC21 | XML | SUTRS');
                $table->string('element_set', 10)->default('F')
                    ->comment('Element set: F (full) | B (brief) | S (suggested)');
                $table->string('username', 255)->nullable()
                    ->comment('Authentication username (if required)');
                $table->string('password', 255)->nullable()
                    ->comment('Authentication password');
                $table->boolean('active')->default(true)
                    ->comment('Include this target in search lists');
                $table->unsignedSmallInteger('sort_order')->default(0)
                    ->comment('Display order in dropdown');
                $table->timestamps();

                $table->index(['active', 'sort_order'], 'idx_z3950_target_active_sort');
            });
        }

        // library_subject_authority — subject authority records (MARC 21 authority)
        if (! Schema::hasTable('library_subject_authority')) {
            Schema::create('library_subject_authority', function (Blueprint $table) {
                $table->id();
                $table->string('lc_label', 500)->nullable()
                    ->comment('Library of Congress authorised heading (RDA: preferred name)');
                $table->string('rda_label', 500)->nullable()
                    ->comment('RDA variant label (e.g. cg, ctg, crt)');
                $table->string('authorized_form', 500)->nullable()
                    ->comment('Cross-vocabulary canonical authorised form');
                $table->string('subject_type', 50)->default('topic')
                    ->comment('topic | person | family | corporate_body | title | geographic | event | uniform_title');
                $table->string('vocab_uri', 500)->nullable()
                    ->comment('Vocabulary URI (e.g. http://id.loc.gov/authorities/subjects/)');
                $table->string('vocab_code', 50)->nullable()
                    ->comment('e.g. lcsh, rvm, mesh, local');
                $table->string('source', 100)->nullable()
                    ->comment('Source system: LCSH, MeSH, FAST, local');
                $table->string('uri', 500)->nullable()
                    ->comment('Individual authority URI (linked via $0)');
                $table->text('notes')->nullable()->comment('Scope/usage notes');
                $table->timestamps();

                $table->index('lc_label', 'idx_auth_lc_label');
                $table->index('subject_type', 'idx_auth_subject_type');
            });
        }

        // Add authority_id FK to library_item_subject if column doesn't exist
        if (Schema::hasTable('library_item_subject') && ! Schema::hasColumn('library_item_subject', 'authority_id')) {
            Schema::table('library_item_subject', function (Blueprint $table) {
                $table->unsignedBigInteger('authority_id')
                    ->nullable()
                    ->after('source')
                    ->comment('FK → library_subject_authority.id');

                $table->foreign('authority_id')
                    ->references('id')
                    ->on('library_subject_authority')
                    ->onDelete('set null');

                $table->index('authority_id', 'idx_lis_authority_id');
            });
        }

        // Add RDA type fields to library_item if they don't exist
        if (Schema::hasTable('library_item') && ! Schema::hasColumn('library_item', 'rda_content_type')) {
            Schema::table('library_item', function (Blueprint $table) {
                $table->string('rda_content_type', 200)->nullable()
                    ->after('source')
                    ->comment('RDA content type term (336$a)');
                $table->string('rda_carrier_type', 200)->nullable()
                    ->after('rda_content_type')
                    ->comment('RDA carrier type term (337$a)');
                $table->string('rda_instance_type', 200)->nullable()
                    ->after('rda_carrier_type')
                    ->comment('RDA instance media type term (338$a)');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_z3950_target');
        // Note: library_subject_authority must be dropped before the FK can be removed
        Schema::dropIfExists('library_subject_authority');

        if (Schema::hasTable('library_item_subject') && Schema::hasColumn('library_item_subject', 'authority_id')) {
            Schema::table('library_item_subject', function (Blueprint $table) {
                $table->dropForeign(['authority_id']);
                $table->dropColumn('authority_id');
            });
        }

        if (Schema::hasTable('library_item') && Schema::hasColumn('library_item', 'rda_content_type')) {
            Schema::table('library_item', function (Blueprint $table) {
                $table->dropColumn(['rda_content_type', 'rda_carrier_type', 'rda_instance_type']);
            });
        }
    }
};
