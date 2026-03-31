<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
        Schema::dropIfExists('setting_i18n');
        Schema::dropIfExists('setting');
    }
};
