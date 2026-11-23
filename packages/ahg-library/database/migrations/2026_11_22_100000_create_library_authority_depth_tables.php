<?php

use AhgLibrary\Services\AuthorityRecordService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Authority control depth (heratio#1475 item 3): record typing, external
 * identity with local overrides, and a relational broader/narrower hierarchy.
 * Side tables only, so library_subject_authority keeps its existing shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('library_authority_record')) {
            Schema::create('library_authority_record', function (Blueprint $table) {
                $table->unsignedBigInteger('authority_id')->primary();
                $table->string('record_type', 30)->default('subject');
                $table->string('scheme', 30)->nullable();
                $table->string('external_id', 191)->nullable();
                $table->json('overridden_fields')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->string('last_sync_error', 500)->nullable();
                $table->timestamps();

                $table->unique(['scheme', 'external_id'], 'uk_authority_external');
                $table->index('record_type', 'idx_authority_record_type');
                $table->foreign('authority_id', 'fk_authority_record')
                    ->references('id')->on('library_subject_authority')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('library_authority_relation')) {
            Schema::create('library_authority_relation', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('broader_id');
                $table->unsignedBigInteger('narrower_id');
                $table->string('origin', 20)->default('local');
                $table->boolean('suppressed')->default(false);
                $table->timestamps();

                $table->unique(['broader_id', 'narrower_id'], 'uk_authority_relation');
                $table->index(['narrower_id', 'suppressed'], 'idx_authority_relation_narrower');
                $table->foreign('broader_id', 'fk_authority_relation_broader')
                    ->references('id')->on('library_subject_authority')->onDelete('cascade');
                $table->foreign('narrower_id', 'fk_authority_relation_narrower')
                    ->references('id')->on('library_subject_authority')->onDelete('cascade');
            });
        }

        $this->seedDropdowns();
        $this->backfillRecordTypes();
        $this->backfillLcshLinks();
    }

    public function down(): void
    {
        Schema::dropIfExists('library_authority_relation');
        Schema::dropIfExists('library_authority_record');
    }

    private function seedDropdowns(): void
    {
        if (! Schema::hasTable('ahg_dropdown')) {
            return;
        }

        $sets = [
            'library_authority_record_type' => ['Authority Record Type', [
                'subject' => 'Subject', 'name' => 'Name', 'title' => 'Title', 'genre' => 'Genre / Form',
            ]],
            'library_authority_subject_type' => ['Authority Heading Type', [
                'topic' => 'Topic', 'topical' => 'Topical', 'geographic' => 'Geographic', 'temporal' => 'Temporal',
                'genre' => 'Genre', 'form' => 'Form', 'uniform' => 'Uniform title', 'names' => 'Names',
                'personal' => 'Personal name', 'corporate' => 'Corporate name', 'meeting' => 'Meeting name',
            ]],
            'library_authority_source' => ['Authority Source Vocabulary', [
                'local' => 'Local', 'lcsh' => 'LCSH', 'lcgft' => 'LCGFT', 'lcnaf' => 'LCNAF',
                'mesh' => 'MeSH', 'gsafd' => 'GSAFD', 'rvm' => 'RVM',
            ]],
            'library_authority_relation_origin' => ['Authority Relation Origin', [
                'local' => 'Local', 'sync' => 'External vocabulary',
            ]],
        ];

        foreach ($sets as $taxonomy => [$taxonomyLabel, $options]) {
            $order = 10;
            foreach ($options as $code => $label) {
                DB::table('ahg_dropdown')->insertOrIgnore([
                    'taxonomy' => $taxonomy, 'taxonomy_label' => $taxonomyLabel, 'taxonomy_section' => 'library',
                    'code' => $code, 'label' => $label, 'sort_order' => $order,
                    'is_default' => $order === 10 ? 1 : 0, 'is_active' => 1,
                ]);
                $order += 10;
            }
        }
    }

    /** Types existing rows from the heading type they already carry. */
    private function backfillRecordTypes(): void
    {
        $column = Schema::hasColumn('library_subject_authority', 'subject_type') ? 'subject_type' : 'heading_type';

        DB::table('library_subject_authority')->select('id', $column)->orderBy('id')
            ->chunkById(500, function ($rows) use ($column) {
                DB::table('library_authority_record')->insertOrIgnore($rows->map(fn ($r) => [
                    'authority_id' => $r->id,
                    'record_type' => AuthorityRecordService::typeForHeading($r->{$column}),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all());
            });
    }

    /** Existing LCSH ids become links. Where rows share an id, the oldest row keeps it. */
    private function backfillLcshLinks(): void
    {
        if (! Schema::hasColumn('library_subject_authority', 'lcsh_id')) {
            return;
        }

        DB::table('library_subject_authority')
            ->where(fn ($q) => $q->whereNotNull('lcsh_id')->orWhereNotNull('lcsh_uri'))
            ->select('id', 'lcsh_id', 'lcsh_uri')->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $externalId = AuthorityRecordService::lcshIdFrom($row);
                    if (! $externalId || DB::table('library_authority_record')
                        ->where('scheme', 'lcsh')->where('external_id', $externalId)->exists()) {
                        continue;
                    }
                    DB::table('library_authority_record')->where('authority_id', $row->id)
                        ->whereNull('external_id')
                        ->update(['scheme' => 'lcsh', 'external_id' => $externalId, 'updated_at' => now()]);
                }
            });
    }
};
