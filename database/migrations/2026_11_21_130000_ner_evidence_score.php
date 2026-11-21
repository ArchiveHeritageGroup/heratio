<?php

/**
 * heratio#1501 - a review signal for NER entities that the extractor did not score.
 *
 * `ahg_ner_entity.confidence` keeps meaning "the extractor said this" and is
 * never filled in by anything else. spaCy emits no per-entity score, so most
 * rows have none. The evidence columns hold a separate, labelled signal:
 *
 *   - evidence_method 'rules': measured from what the catalogue already holds
 *     (a reviewer's earlier decision on the same name, a gazetteer entry, an
 *     authority record or place term, the shape of the value). CPU only.
 *   - evidence_method 'llm_verify': the probability a small model, asked through
 *     the AI gateway, gives that the value is of its type in the record's text.
 *
 * Also drops the column default of 1.0000 on `confidence`: any writer that left
 * the column out was recording certainty nothing measured (#1480).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ahg_ner_entity')) {
            if (! Schema::hasColumn('ahg_ner_entity', 'evidence_score')) {
                DB::statement('ALTER TABLE ahg_ner_entity
                    ADD COLUMN evidence_score DECIMAL(5,4) NULL AFTER confidence,
                    ADD COLUMN evidence_method VARCHAR(40) NULL AFTER evidence_score,
                    ADD COLUMN evidence_reasons VARCHAR(255) NULL AFTER evidence_method,
                    ADD COLUMN evidence_scored_at TIMESTAMP NULL AFTER evidence_reasons');
            }
            if (Schema::hasColumn('ahg_ner_entity', 'confidence')) {
                DB::statement('ALTER TABLE ahg_ner_entity MODIFY confidence DECIMAL(5,4) NULL DEFAULT NULL');
            }
        }

        if (Schema::hasTable('ahg_dropdown')) {
            foreach ([
                ['rules', 'Catalogue evidence', 'Reviewer decisions, gazetteer, authority records and the shape of the value. No AI call.', 10],
                ['llm_verify', 'Model check', 'Probability from a small model asked through the AI gateway whether the value is of its type in the text.', 20],
            ] as [$code, $label, $description, $order]) {
                if (DB::table('ahg_dropdown')->where('taxonomy', 'ner_evidence_method')->where('code', $code)->exists()) {
                    continue;
                }
                DB::table('ahg_dropdown')->insert([
                    'taxonomy' => 'ner_evidence_method', 'taxonomy_label' => 'NER evidence method', 'taxonomy_section' => 'ai',
                    'code' => $code, 'label' => $label, 'sort_order' => $order, 'is_default' => 0, 'is_active' => 1,
                    'metadata' => json_encode(['description' => $description]),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // The explorer setting now reads the evidence score as well; say so, but
        // only where the description is still the shipped text.
        if (Schema::hasTable('ahg_settings') && Schema::hasColumn('ahg_settings', 'description')) {
            DB::table('ahg_settings')->where('setting_key', 'explorer_ner_min_confidence')
                ->where('description', 'Also use names read from the text that are not yet reviewed, at or above this confidence (0 to 1). 0 uses reviewed names only.')
                ->update(['description' => 'Also use names read from the text that are not yet reviewed, at or above this score (0 to 1): the extractor\'s own confidence, or the evidence score where it gave none. 0.8 admits names a reviewer, gazetteer or authority record backs; 0.5 also admits name-shaped values. 0 uses reviewed names only.']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ahg_ner_entity') && Schema::hasColumn('ahg_ner_entity', 'evidence_score')) {
            DB::statement('ALTER TABLE ahg_ner_entity DROP COLUMN evidence_score, DROP COLUMN evidence_method, DROP COLUMN evidence_reasons, DROP COLUMN evidence_scored_at');
        }
        // The 1.0000 default is not restored: it asserted a certainty nothing measured.
    }
};
