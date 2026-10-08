<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1542. Bulk edits of archival descriptions (find and replace, batch
 * edit, batch rename, sort children) run as background batches. Every value a
 * batch changes is kept with its before and after, so the batch can be undone
 * and each change is auditable on its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ahg_bulk_edit')) {
            Schema::create('ahg_bulk_edit', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('kind', 32);                 // find_replace | set_field | rename | sort_children
                $t->json('params');
                $t->json('scope');
                $t->string('status', 16)->default('queued'); // queued | running | done | failed | undone
                $t->unsignedInteger('total')->default(0);
                $t->unsignedInteger('done')->default(0);
                $t->unsignedInteger('changed')->default(0);
                $t->text('error')->nullable();
                $t->integer('created_by')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->timestamp('finished_at')->nullable();
                $t->timestamp('undone_at')->nullable();
                $t->integer('undone_by')->nullable();
                $t->index('status');
            });
        }
        if (! Schema::hasTable('ahg_bulk_edit_change')) {
            Schema::create('ahg_bulk_edit_change', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('bulk_edit_id');
                $t->integer('object_id');
                $t->string('field', 64);
                $t->string('culture', 16)->nullable();
                $t->longText('before_value')->nullable();
                $t->longText('after_value')->nullable();
                $t->index(['bulk_edit_id', 'id']);
                $t->index('object_id');
                $t->foreign('bulk_edit_id')->references('id')->on('ahg_bulk_edit')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ahg_bulk_edit_change');
        Schema::dropIfExists('ahg_bulk_edit');
    }
};
