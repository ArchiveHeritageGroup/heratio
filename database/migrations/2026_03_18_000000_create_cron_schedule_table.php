<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_schedule', function (Blueprint $table) {
            $table->increments('id');
            $table->string('slug', 120)->unique();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('category', 80);
            $table->string('artisan_command', 500);
            $table->boolean('is_enabled')->default(true);
            $table->string('cron_expression', 60)->default('0 * * * *');
            $table->unsignedInteger('timeout_minutes')->default(60);
            $table->string('duration_hint', 10)->default('medium');
            $table->string('log_file', 200)->nullable();
            $table->dateTime('last_run_at')->nullable();
            $table->string('last_run_status', 20)->nullable();
            $table->unsignedInteger('last_run_duration_ms')->nullable();
            $table->text('last_run_output')->nullable();
            $table->dateTime('next_run_at')->nullable();
            $table->unsignedInteger('total_runs')->default(0);
            $table->unsignedInteger('total_failures')->default(0);
            $table->boolean('notify_on_failure')->default(false);
            $table->string('notify_email', 200)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_enabled', 'next_run_at'], 'idx_enabled_next');
            $table->index('category', 'idx_category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_schedule');
    }
};
