<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // On Heratio installs the `setting` / `setting_i18n` tables come from
        // the AtoM base schema (database/core/*.sql) with a richer column set.
        // Skip this migration if either table already exists so artisan migrate
        // is idempotent against an installed database.
        if (Schema::hasTable('setting') || Schema::hasTable('setting_i18n')) {
            return;
        }

        Schema::create('setting', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('scope')->nullable();
            $table->timestamps();
        });

        Schema::create('setting_i18n', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('setting_id');
            $table->string('culture', 10);
            $table->longText('value')->nullable();
            $table->foreign('setting_id')->references('id')->on('setting')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        // Never drop these on rollback — they are owned by the AtoM base
        // schema on real installs. Leaving down() empty is intentional.
    }
};
