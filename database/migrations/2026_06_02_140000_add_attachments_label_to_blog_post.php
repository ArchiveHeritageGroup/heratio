<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional short message shown above the guides/templates download list on a
 * published article (e.g. "Download the cataloguing templates below"). Heratio
 * demo-site only.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('blog_post') || Schema::hasColumn('blog_post', 'attachments_label')) {
            return;
        }
        Schema::table('blog_post', function (Blueprint $t) {
            $t->string('attachments_label', 255)->nullable()->after('body');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('blog_post', 'attachments_label')) {
            Schema::table('blog_post', function (Blueprint $t) {
                $t->dropColumn('attachments_label');
            });
        }
    }
};
