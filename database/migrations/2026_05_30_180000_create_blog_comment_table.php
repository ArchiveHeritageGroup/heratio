<?php

/**
 * blog_comment - anonymous, blog-style comments on published articles (blog_post).
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('blog_comment')) {
            return;
        }

        Schema::create('blog_comment', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('blog_post_id');
            $table->string('author_name', 150)->nullable()->comment('Optional - blank shows as Anonymous');
            $table->text('body');
            // VARCHAR not ENUM (project rule). approved = visible; pending = held; spam = hidden.
            $table->string('status', 20)->default('approved');
            $table->string('ip', 45)->nullable()->comment('Source IP - rate limiting / abuse review');
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['blog_post_id', 'status']);
            $table->index('created_at');
            $table->foreign('blog_post_id')->references('id')->on('blog_post')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_comment');
    }
};
