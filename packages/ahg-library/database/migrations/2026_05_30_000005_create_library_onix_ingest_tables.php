<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 *
 * ONIX ingestion (heratio#1094): batch log + per-record review queue.
 *
 * `library_onix_ingest`      - one row per uploaded ONIX message / API call.
 * `library_onix_ingest_line` - one row per <Product>, the review queue that a
 *                              cataloguer approves/skips before commit. On
 *                              commit each valid line creates a bibliographic
 *                              record (via LibraryService::create) and an
 *                              acquisitions order line.
 *
 * Statuses are plain VARCHAR (no ENUM, per project rules); the workflow values
 * are internal state, not operator-edited dropdowns.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('library_onix_ingest')) {
            Schema::create('library_onix_ingest', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('filename', 255)->nullable();
                $table->string('source', 20)->default('file');   // file | api | paste
                $table->string('onix_version', 8)->nullable();    // 3.0 | 2.1
                $table->string('status', 20)->default('parsed');  // parsed | committed | failed
                $table->unsignedInteger('record_count')->default(0);
                $table->unsignedInteger('valid_count')->default(0);
                $table->unsignedInteger('error_count')->default(0);
                $table->unsignedInteger('imported_count')->default(0);
                $table->unsignedBigInteger('order_id')->nullable();   // acquisitions order created/linked on commit
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index('created_at');
            });
        }

        if (!Schema::hasTable('library_onix_ingest_line')) {
            Schema::create('library_onix_ingest_line', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('ingest_id');
                $table->string('product_ref', 255)->nullable();   // ONIX RecordReference
                $table->string('isbn', 20)->nullable();
                $table->string('issn', 20)->nullable();
                $table->string('title', 500)->nullable();
                $table->string('subtitle', 500)->nullable();
                $table->string('author', 500)->nullable();
                $table->string('publisher', 255)->nullable();
                $table->string('pub_year', 8)->nullable();
                $table->string('edition', 100)->nullable();
                $table->string('material_type', 50)->nullable();
                $table->decimal('price', 12, 2)->nullable();
                $table->string('currency', 8)->nullable();
                $table->string('supplier', 255)->nullable();
                // parsed | valid | invalid | duplicate | imported | skipped
                $table->string('status', 20)->default('parsed');
                $table->string('error', 1000)->nullable();
                $table->unsignedBigInteger('library_item_id')->nullable();  // set on commit
                $table->unsignedBigInteger('order_line_id')->nullable();    // set on commit
                $table->longText('raw')->nullable();   // the <Product> fragment, for audit / re-parse
                $table->timestamps();

                $table->index('ingest_id');
                $table->index('status');
                $table->index('isbn');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_onix_ingest_line');
        Schema::dropIfExists('library_onix_ingest');
    }
};
