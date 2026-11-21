<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * heratio#1764: face recognition search.
 *
 * digital_object_faces gets what matching needs: the embedding (unit-length
 * float32 vector, packed little-endian, so cosine similarity is a dot
 * product), the backend that produced it, the frame time for faces found in
 * video, and the person the face has been assigned to.
 *
 * face_person holds named persons (optionally the authority record they
 * are); face_match_rejection remembers "this face is not that person" so a
 * rejected suggestion is not offered again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('face_person')) {
            Schema::create('face_person', function (Blueprint $t) {
                $t->increments('id');
                $t->string('name', 255);
                $t->integer('actor_id')->nullable()->index();
                $t->text('notes')->nullable();
                $t->integer('created_by')->nullable();
                $t->dateTime('created_at')->nullable();
                $t->dateTime('updated_at')->nullable();
                $t->foreign('actor_id')->references('id')->on('actor')->nullOnDelete();
            });
        }

        if (Schema::hasTable('digital_object_faces')) {
            $add = [
                'person_id' => fn (Blueprint $t) => $t->unsignedInteger('person_id')->nullable()->after('digital_object_id')->index(),
                'frame_time_seconds' => fn (Blueprint $t) => $t->decimal('frame_time_seconds', 10, 3)->nullable()->after('face_index'),
                'embedding' => fn (Blueprint $t) => $t->binary('embedding')->nullable()->after('bounding_box'),
                'embedding_dim' => fn (Blueprint $t) => $t->unsignedSmallInteger('embedding_dim')->nullable()->after('embedding'),
                'backend' => fn (Blueprint $t) => $t->string('backend', 50)->nullable()->after('confidence'),
            ];
            foreach ($add as $col => $def) {
                if (! Schema::hasColumn('digital_object_faces', $col)) {
                    Schema::table('digital_object_faces', $def);
                }
            }
            $hasFk = DB::selectOne("SELECT COUNT(*) AS n FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'digital_object_faces' AND CONSTRAINT_NAME = 'digital_object_faces_person_id_foreign'");
            if ((int) ($hasFk->n ?? 0) === 0) {
                Schema::table('digital_object_faces', function (Blueprint $t) {
                    $t->foreign('person_id')->references('id')->on('face_person')->nullOnDelete();
                });
            }
        }

        if (! Schema::hasTable('face_match_rejection')) {
            Schema::create('face_match_rejection', function (Blueprint $t) {
                $t->increments('id');
                $t->integer('face_id');
                $t->unsignedInteger('person_id');
                $t->integer('rejected_by')->nullable();
                $t->dateTime('created_at')->nullable();
                $t->unique(['face_id', 'person_id']);
                $t->foreign('face_id')->references('id')->on('digital_object_faces')->cascadeOnDelete();
                $t->foreign('person_id')->references('id')->on('face_person')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('face_match_rejection');
        if (Schema::hasTable('digital_object_faces') && Schema::hasColumn('digital_object_faces', 'person_id')) {
            Schema::table('digital_object_faces', function (Blueprint $t) {
                $t->dropForeign(['person_id']);
                $t->dropColumn(['person_id', 'frame_time_seconds', 'embedding', 'embedding_dim', 'backend']);
            });
        }
        Schema::dropIfExists('face_person');
    }
};
