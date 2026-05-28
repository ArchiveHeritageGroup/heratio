<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demo-site blog / articles. Only surfaced when the homepage runs in
 * "marketing" mode (heratio.theahg.co.za). Admin-managed via /admin/articles,
 * public at /articles. Body is Markdown, rendered with Str::markdown() on show.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('blog_post')) {
            return;
        }
        Schema::create('blog_post', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('slug', 200);
            $t->string('title', 255);
            $t->string('excerpt', 500)->nullable();
            $t->mediumText('body')->nullable();
            $t->string('cover_image', 500)->nullable();
            $t->string('author', 150)->nullable();
            $t->string('status', 20)->default('draft'); // draft | published (VARCHAR, no ENUM)
            $t->dateTime('published_at')->nullable();
            $t->integer('created_by')->nullable();
            $t->timestamps();
            $t->unique('slug', 'uq_blog_post_slug');
            $t->index(['status', 'published_at'], 'idx_blog_post_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post');
    }
};
