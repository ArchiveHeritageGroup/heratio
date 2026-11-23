<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Classification schedules (heratio#1475 item 6). Additive only: library_item
 * is untouched, its derived shelf-order key lives in library_item_shelf_key.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Shelf keys compare byte by byte; a case-insensitive collation would misfile them.
        $binary = fn ($column) => DB::getDriverName() === 'mysql' ? $column->collation('utf8mb4_bin') : $column;

        if (! Schema::hasTable('library_classification_schedule')) {
            Schema::create('library_classification_schedule', function (Blueprint $table) {
                $table->id();
                $table->string('scheme', 50);           // ahg_dropdown: library_classification_scheme
                $table->string('name', 255);
                $table->string('edition', 50)->nullable();
                $table->text('description')->nullable();
                $table->string('validation_mode', 20)->default('warn'); // ahg_dropdown: library_classification_validation
                $table->boolean('is_active')->default(false);
                $table->timestamps();
                $table->unique(['scheme', 'name'], 'uq_lib_class_schedule');
                $table->index(['scheme', 'is_active'], 'idx_lib_class_schedule_active');
            });
        }

        if (! Schema::hasTable('library_classification_entry')) {
            Schema::create('library_classification_entry', function (Blueprint $table) use ($binary) {
                $table->id();
                $table->unsignedBigInteger('schedule_id');
                $table->unsignedBigInteger('parent_id')->nullable();
                $binary($table->string('notation', 100)); // Exact: Ab and AB can be different local notations.
                $table->string('notation_end', 100)->nullable();
                $table->string('caption', 500);
                $table->text('note')->nullable();
                $binary($table->string('sort_key', 191));
                $binary($table->string('sort_key_end', 191)->nullable());
                $table->unsignedSmallInteger('depth')->default(0);
                $table->timestamps();
                $table->unique(['schedule_id', 'notation'], 'uq_lib_class_entry');
                $table->index(['schedule_id', 'parent_id', 'sort_key'], 'idx_lib_class_entry_tree');
                $table->index(['schedule_id', 'sort_key'], 'idx_lib_class_entry_sort');
            });
        }

        if (! Schema::hasTable('library_item_shelf_key')) {
            Schema::create('library_item_shelf_key', function (Blueprint $table) use ($binary) {
                $table->unsignedBigInteger('library_item_id')->primary();
                $table->string('scheme', 50);
                $table->string('class_number', 255);
                $table->unsignedBigInteger('schedule_entry_id')->nullable();
                $binary($table->string('sort_key', 191));
                $table->timestamp('updated_at')->nullable();
                $table->index(['scheme', 'sort_key'], 'idx_lib_shelf_key');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_item_shelf_key');
        Schema::dropIfExists('library_classification_entry');
        Schema::dropIfExists('library_classification_schedule');
    }
};
