<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Article attachments - parent/child file uploads for the demo-site blog.
 * Each blog_post (parent) can carry multiple downloadable files (children):
 * guides and templates, each with its own short description. Published
 * attachments are listed for download on the public /articles/{slug} page.
 * Heratio-only (demo site); not part of the client product.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('blog_attachment')) {
            return;
        }
        Schema::create('blog_attachment', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('blog_post_id');
            // guide | template (VARCHAR, no ENUM - per project dropdown rule)
            $t->string('kind', 20)->default('guide');
            $t->string('title', 255);
            $t->string('description', 500)->nullable();
            $t->string('file_path', 500);          // storage path under the public disk
            $t->string('file_name', 255);          // original filename for download
            $t->string('mime', 150)->nullable();
            $t->unsignedBigInteger('file_size')->default(0); // bytes
            $t->integer('sort_order')->default(0);
            $t->integer('created_by')->nullable();
            $t->timestamps();
            $t->foreign('blog_post_id', 'fk_blog_attachment_post')
              ->references('id')->on('blog_post')->onDelete('cascade');
            $t->index(['blog_post_id', 'kind', 'sort_order'], 'idx_blog_attachment_post');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_attachment');
    }
};
