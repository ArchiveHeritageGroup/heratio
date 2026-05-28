<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Read/visit counter for demo-site articles. Incremented once per session per
 * article on the public show page (admin previews are not counted), since the
 * AtoM access_log / security_audit_log do not track blog_post visits.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('blog_post') || Schema::hasColumn('blog_post', 'view_count')) {
            return;
        }
        Schema::table('blog_post', function (Blueprint $t) {
            $t->unsignedInteger('view_count')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('blog_post') && Schema::hasColumn('blog_post', 'view_count')) {
            Schema::table('blog_post', function (Blueprint $t) {
                $t->dropColumn('view_count');
            });
        }
    }
};
