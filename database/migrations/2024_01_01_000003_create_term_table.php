<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('term', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('taxonomy_id')->nullable();
            $table->string('code')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->integer('lft')->nullable();
            $table->integer('rgt')->nullable();
            $table->string('source_culture')->nullable();
            $table->string('name')->nullable();
            $table->string('use_for')->nullable();
            $table->text('scope_note')->nullable();
            $table->boolean('正向優先')->default(true);
            $table->string('其他的文字')->nullable();
            $table->string('分類來源')->default('local');
            $table->string('其他的分類來源')->nullable();
            $table->timestamp('更新觸發')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term');
    }
};
