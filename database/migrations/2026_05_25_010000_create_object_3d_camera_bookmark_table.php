<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the object_3d_camera_bookmark table — backs the per-user camera
 * preset feature for the 3D Model Viewer (#666 Phase 2).
 *
 * Previously the object_3d_model row carried a single camera_orbit string,
 * which constrained users to one saved viewpoint per model. This table
 * supports multiple named presets per model, optionally scoped per user
 * (user_id NULL = shared/site-wide bookmark).
 *
 * Type-matching: object_3d_model.id and user.id are both INT signed in the
 * base AtoM schema, so FK columns are declared as integer (not bigInteger)
 * to satisfy the MySQL FK column-type rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('object_3d_camera_bookmark')) {
            return;
        }

        Schema::create('object_3d_camera_bookmark', function (Blueprint $table) {
            $table->bigIncrements('id');
            // object_3d_model.id is INT signed
            $table->integer('object_3d_id');
            // user.id is INT signed; nullable for shared/site-wide bookmarks
            $table->integer('user_id')->nullable();
            $table->string('name', 120);
            // Model Viewer format: "30deg 75deg 5m"
            $table->string('camera_orbit', 120);
            $table->string('camera_target', 120)->nullable();
            $table->string('field_of_view', 40)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('object_3d_id', 'obj3d_cam_bm_obj_idx');
            $table->index(['object_3d_id', 'user_id'], 'obj3d_cam_bm_obj_user_idx');

            $table->foreign('object_3d_id', 'obj3d_cam_bm_obj_fk')
                ->references('id')->on('object_3d_model')
                ->onDelete('cascade');

            $table->foreign('user_id', 'obj3d_cam_bm_user_fk')
                ->references('id')->on('user')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('object_3d_camera_bookmark');
    }
};
