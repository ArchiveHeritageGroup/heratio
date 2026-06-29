<?php

/**
 * Consolidate the two provenance stacks into one (issue: provenance de-dup).
 *
 * Heratio historically carried two parallel object-provenance implementations:
 *   - Set A  ahg/provenance         -> provenance_record / _event / _agent / _document
 *                                      (rich governance header + from/to-agent event chain)
 *   - Set B  ahg/information-object  -> provenance_entry
 *                                      (flat, sequence-ordered ownership chain; the live,
 *                                       sector-neutral catalogue surface as of v1.154.182)
 *
 * Set B is the survivor (canonical io.provenance route, IO-keyed, audit-logged). This
 * migration keeps the best of both by:
 *   1. Creating provenance_overview — Set A's per-IO governance header reborn (Nazi-era
 *      due-diligence, cultural-property/restitution status, research workflow, narrative
 *      summary, acquisition + publication flags). Set B had no equivalent.
 *   2. Adding evidence_type / evidence_description to provenance_entry so the chain carries
 *      Set A's per-event evidence richness.
 *   3. Migrating Set A data into the survivor (record -> overview always; event -> entry
 *      only for IOs that have no existing Set B chain, so we never clobber Set B data).
 *   4. Soft-retiring the Set A tables (rename to *_retired — data preserved, reversible).
 *
 * Idempotent and install-safe: every step is guarded by hasTable/hasColumn, so installs
 * where Set A never existed simply create the overview table + columns and skip the rest.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Set A tables to soft-retire (rename to *_retired) once data is migrated. */
    private array $retire = [
        'provenance_record', 'provenance_record_i18n',
        'provenance_event', 'provenance_event_i18n',
        'provenance_agent', 'provenance_agent_i18n',
        'provenance_document',
    ];

    public function up(): void
    {
        // 1. Governance header — one row per information object.
        if (!Schema::hasTable('provenance_overview')) {
            Schema::create('provenance_overview', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('information_object_id')->unique();
                $table->string('current_status', 50)->nullable();
                $table->string('custody_type', 50)->nullable();
                $table->string('acquisition_type', 50)->nullable();
                $table->date('acquisition_date')->nullable();
                $table->string('acquisition_date_text', 100)->nullable();
                $table->decimal('acquisition_price', 15, 2)->nullable();
                $table->string('acquisition_currency', 10)->nullable();
                $table->string('certainty_level', 50)->nullable();
                $table->boolean('has_gaps')->default(false);
                $table->text('gap_description')->nullable();
                $table->string('research_status', 50)->nullable();
                $table->text('research_notes')->nullable();
                $table->boolean('nazi_era_provenance_checked')->default(false);
                $table->boolean('nazi_era_provenance_clear')->nullable();
                $table->text('nazi_era_notes')->nullable();
                $table->string('cultural_property_status', 50)->default('none');
                $table->text('cultural_property_notes')->nullable();
                $table->text('provenance_summary')->nullable();
                $table->boolean('is_complete')->default(false);
                $table->boolean('is_public')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // 2. Carry Set A's per-event evidence richness onto the survivor chain.
        if (Schema::hasTable('provenance_entry')) {
            Schema::table('provenance_entry', function (Blueprint $table) {
                if (!Schema::hasColumn('provenance_entry', 'evidence_type')) {
                    $table->string('evidence_type', 50)->nullable()->after('sources');
                }
                if (!Schema::hasColumn('provenance_entry', 'evidence_description')) {
                    $table->text('evidence_description')->nullable()->after('evidence_type');
                }
            });
        }

        // 3a. provenance_record -> provenance_overview (1:1 per IO). Summary may live in i18n.
        if (Schema::hasTable('provenance_record')) {
            $hasI18n = Schema::hasTable('provenance_record_i18n');
            $records = DB::table('provenance_record')->get();
            foreach ($records as $r) {
                $exists = DB::table('provenance_overview')
                    ->where('information_object_id', $r->information_object_id)->exists();
                if ($exists) {
                    continue;
                }
                $summary = $r->provenance_summary ?? null;
                if ($summary === null && $hasI18n) {
                    $summary = DB::table('provenance_record_i18n')
                        ->where('id', $r->id)->value('provenance_summary');
                }
                DB::table('provenance_overview')->insert([
                    'information_object_id'        => $r->information_object_id,
                    'current_status'               => $r->current_status ?? null,
                    'custody_type'                 => $r->custody_type ?? null,
                    'acquisition_type'             => $r->acquisition_type ?? null,
                    'acquisition_date'             => $r->acquisition_date ?? null,
                    'acquisition_date_text'        => $r->acquisition_date_text ?? null,
                    'acquisition_price'            => $r->acquisition_price ?? null,
                    'acquisition_currency'         => $r->acquisition_currency ?? null,
                    'certainty_level'              => $r->certainty_level ?? null,
                    'has_gaps'                     => $r->has_gaps ?? 0,
                    'gap_description'              => $r->gap_description ?? null,
                    'research_status'              => $r->research_status ?? null,
                    'research_notes'               => $r->research_notes ?? null,
                    'nazi_era_provenance_checked'  => $r->nazi_era_provenance_checked ?? 0,
                    'nazi_era_provenance_clear'    => $r->nazi_era_provenance_clear ?? null,
                    'nazi_era_notes'               => $r->nazi_era_notes ?? null,
                    'cultural_property_status'     => $r->cultural_property_status ?? 'none',
                    'cultural_property_notes'      => $r->cultural_property_notes ?? null,
                    'provenance_summary'           => $summary,
                    'is_complete'                  => $r->is_complete ?? 0,
                    'is_public'                    => $r->is_public ?? 1,
                    'created_by'                   => $r->created_by ?? null,
                    'created_at'                   => $r->created_at ?? now(),
                    'updated_at'                   => $r->updated_at ?? now(),
                ]);
            }

            // 3b. provenance_event -> provenance_entry, but ONLY for IOs with no Set B chain
            //     yet (never clobber existing provenance_entry rows).
            if (Schema::hasTable('provenance_event')) {
                $hasAgent = Schema::hasTable('provenance_agent');
                foreach ($records as $r) {
                    $hasChain = DB::table('provenance_entry')
                        ->where('information_object_id', $r->information_object_id)->exists();
                    if ($hasChain) {
                        continue;
                    }
                    $events = DB::table('provenance_event')
                        ->where('provenance_record_id', $r->id)
                        ->orderBy('sequence_number')->orderBy('sort_order')->get();
                    $seq = 0;
                    foreach ($events as $e) {
                        $agent = ($hasAgent && $e->to_agent_id)
                            ? DB::table('provenance_agent')->where('id', $e->to_agent_id)->first()
                            : null;
                        $seq++;
                        DB::table('provenance_entry')->insert([
                            'information_object_id' => $r->information_object_id,
                            'sequence'              => $e->sequence_number ?? $seq,
                            'owner_name'            => $agent->name ?? 'Unknown',
                            'owner_type'            => $agent->agent_type ?? 'unknown',
                            'owner_actor_id'        => $agent->actor_id ?? null,
                            'owner_location'        => $e->event_location ?? null,
                            'start_date'            => $e->event_date ?? $e->event_date_start ?? null,
                            'end_date'              => $e->event_date_end ?? null,
                            'transfer_type'         => $e->event_type ?? 'unknown',
                            'sale_price'            => $e->price ?? null,
                            'sale_currency'         => $e->currency ?? null,
                            'certainty'             => $e->certainty ?? 'unknown',
                            'sources'               => $e->source_reference ?? null,
                            'evidence_type'         => $e->evidence_type ?? null,
                            'evidence_description'  => $e->evidence_description ?? null,
                            'notes'                 => $e->notes ?? null,
                            'is_gap'                => 0,
                            'created_at'            => $e->created_at ?? now(),
                            'updated_at'            => $e->updated_at ?? now(),
                        ]);
                    }
                }
            }
        }

        // 4. Soft-retire Set A tables (rename — data preserved, reversible).
        foreach ($this->retire as $t) {
            if (Schema::hasTable($t) && !Schema::hasTable($t . '_retired')) {
                Schema::rename($t, $t . '_retired');
            }
        }
    }

    public function down(): void
    {
        foreach ($this->retire as $t) {
            if (Schema::hasTable($t . '_retired') && !Schema::hasTable($t)) {
                Schema::rename($t . '_retired', $t);
            }
        }

        if (Schema::hasTable('provenance_entry')) {
            Schema::table('provenance_entry', function (Blueprint $table) {
                foreach (['evidence_type', 'evidence_description'] as $c) {
                    if (Schema::hasColumn('provenance_entry', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }

        Schema::dropIfExists('provenance_overview');
    }
};
