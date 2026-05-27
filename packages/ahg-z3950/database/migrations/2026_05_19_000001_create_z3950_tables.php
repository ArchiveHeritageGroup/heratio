<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('heratio')->hasTable('z3950_targets')) {
            Schema::connection('heratio')->create('z3950_targets', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255);
                $table->string('host', 255);
                $table->unsignedSmallInteger('port')->default(210);
                $table->string('database', 255);
                $table->string('syntax', 50)->default('USmarc');
                $table->string('element_set', 5)->default('F');
                $table->string('charset', 50)->default('UTF-8');
                $table->string('authentication', 255)->nullable();
                $table->boolean('active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::connection('heratio')->hasTable('z3950_query_log')) {
            Schema::connection('heratio')->create('z3950_query_log', function (Blueprint $table) {
                $table->id();
                $table->foreignId('target_id')
                    ->nullable()
                    ->constrained('z3950_targets')
                    ->nullOnDelete();
                $table->string('query', 1000);
                $table->string('syntax', 50);
                $table->unsignedInteger('result_count')->default(0);
                $table->unsignedInteger('elapsed_ms')->default(0);
                $table->text('error')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::connection('heratio')->hasTable('z3950_import_log')) {
            Schema::connection('heratio')->create('z3950_import_log', function (Blueprint $table) {
                $table->id();
                $table->foreignId('target_id')
                    ->nullable()
                    ->constrained('z3950_targets')
                    ->nullOnDelete();
                $table->string('result_set', 64);
                $table->unsignedInteger('record_number')->default(0);
                $table->longText('marc_content');
                $table->unsignedInteger('works_created')->default(0);
                $table->unsignedInteger('instances_created')->default(0);
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('heratio')->dropIfExists('z3950_import_log');
        Schema::connection('heratio')->dropIfExists('z3950_query_log');
        Schema::connection('heratio')->dropIfExists('z3950_targets');
    }
};