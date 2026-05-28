<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Article grouping for the demo blog. `article_group` is a free-text label
 * (e.g. "Compliance", "Product", "News"); the admin form offers existing
 * groups as a datalist and the public index renders one section per group.
 * VARCHAR, not ENUM, and not the reserved word `group`.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('blog_post') || Schema::hasColumn('blog_post', 'article_group')) {
            return;
        }
        Schema::table('blog_post', function (Blueprint $t) {
            $t->string('article_group', 100)->nullable()->after('author');
            $t->index('article_group', 'idx_blog_post_group');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('blog_post') && Schema::hasColumn('blog_post', 'article_group')) {
            Schema::table('blog_post', function (Blueprint $t) {
                $t->dropIndex('idx_blog_post_group');
                $t->dropColumn('article_group');
            });
        }
    }
};
