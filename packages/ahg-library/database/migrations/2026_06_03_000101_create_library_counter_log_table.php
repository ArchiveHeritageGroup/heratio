<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 *
 * COUNTER R5 per-event log (heratio#1096). The aggregate counters live in
 * library_usage_stats; this table records each individual access event so the
 * NISO COUNTER metrics that require session-level de-duplication can be
 * computed correctly:
 *   - Unique_Item_Requests / Unique_Item_Investigations are "distinct
 *     item per session per day", which is impossible to derive from a
 *     pre-aggregated counter alone.
 *   - PR1 "successful searches" needs a per-session search count.
 *
 * No ENUM columns (per project rules) - resource_type / access_type / status
 * are VARCHAR backed by ahg_dropdown taxonomies where a managed list is wanted.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('library_counter_log')) {
            Schema::create('library_counter_log', function (Blueprint $table) {
                $table->bigIncrements('id');
                // Anonymised per-browser session token (sha256 hex of a cookie
                // value injected by InjectUsageTracker). Never the raw user id.
                $table->string('session_id', 64)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('resource_id')->nullable(); // library_item.id (null for searches)
                $table->string('resource_type', 30)->default('item');  // item | title | database | platform | search
                $table->string('access_type', 30)->default('Controlled'); // Controlled | OA_Gold (COUNTER access type)
                $table->string('event', 30)->default('investigation'); // investigation | request | search | denied | open_access
                $table->date('event_date');
                $table->string('status', 20)->default('success'); // success | denied
                $table->timestamp('created_at')->useCurrent();

                $table->index('session_id', 'idx_counter_session');
                $table->index('event_date', 'idx_counter_date');
                $table->index('resource_id', 'idx_counter_resource');
                $table->index(['event_date', 'event'], 'idx_counter_date_event');
                $table->index(['session_id', 'resource_id', 'event_date'], 'idx_counter_unique_probe');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_counter_log');
    }
};
