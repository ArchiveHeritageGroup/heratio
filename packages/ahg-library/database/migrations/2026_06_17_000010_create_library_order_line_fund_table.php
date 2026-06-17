<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Multi-fund acquisitions splitting (#1311). A single order line can be
     * split across several budget funds (e.g. 60% Fund A / 40% Fund B). The
     * junction holds one row per {order_line_id, fund_code} portion. When no
     * rows exist for a line the legacy single-fund_code path on
     * library_order_line is used unchanged. Mirrors PSIS ahgLibraryPlugin.
     */
    public function up(): void
    {
        if (Schema::hasTable('library_order_line_fund')) {
            return;
        }

        Schema::create('library_order_line_fund', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_line_id');
            $table->string('fund_code', 50);
            $table->decimal('amount', 12, 2)->default(0.00);
            $table->timestamp('created_at')->useCurrent();

            $table->index('order_line_id', 'idx_olf_line');
            $table->index('fund_code', 'idx_olf_fund');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_order_line_fund');
    }
};
