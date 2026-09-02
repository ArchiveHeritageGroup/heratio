<?php

/**
 * #1480 - retire the fabricated NER confidence of exactly 1.0.
 *
 * `ahg_ner_entity.confidence` carried 1.0000 on 2,003 of 2,124 rows on dev. That
 * number was never measured. Both detectors that write here are known:
 *
 *   - spaCy (`backend_used = 'local'`) emits NO per-entity score at all. Every
 *     one of its 1.0000 rows was written by a code path that filled the column
 *     to avoid leaving it empty. That path is gone as of v1.154.647, which
 *     introduced a single writer keeping confidence nullable and never
 *     defaulting it; entities written since correctly carry NULL.
 *   - the PII scanner (`backend_used = 'pii_detector'`) DOES emit a real spread,
 *     and its ceiling is 0.95 - a Luhn-validated card. It has no 1.0 anywhere in
 *     PiiScanService. Its rows at 1.0000 carry NER entity types (ISAD_SUBJECT,
 *     ISAD_PLACE, PERSON) rather than PII types, so they came from the same
 *     fabricating path under a combined run.
 *
 * So no detector in this system produces 1.0, and every row holding it is
 * asserting a certainty nothing took. That is worse than an empty column,
 * because a confidence gate reads it and passes everything: the threshold
 * setting defaults to 0.85, which a constant 1.0 clears every time. The entities
 * this most affects are exactly the ones needing review - a person misfiled as
 * an organisation, a surname dropped - all recorded as certain.
 *
 * Only EXACTLY 1.0000 is touched. The genuine scores (0.85 x77, 0.80, 0.70,
 * matching PiiScanService's own constants) are left alone, as is NULL.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ahg_ner_entity') || ! Schema::hasColumn('ahg_ner_entity', 'confidence')) {
            return;
        }

        $n = DB::table('ahg_ner_entity')->where('confidence', 1.0)->update(['confidence' => null]);

        // The value propagates: AiNerService selects ahg_ner_entity.confidence and
        // it reaches IIIF annotations as ner_confidence (#697). Clear it there too,
        // or a consumer reading the annotation still sees 100% on an unscored
        // entity. Guarded - the annotations package need not be installed.
        if (Schema::hasTable('ahg_iiif_annotation') && Schema::hasColumn('ahg_iiif_annotation', 'ner_confidence')) {
            DB::table('ahg_iiif_annotation')->where('ner_confidence', 1.0)->update(['ner_confidence' => null]);
        }

        if ($n > 0) {
            \Illuminate\Support\Facades\Log::info("[#1480] cleared fabricated NER confidence of 1.0 on {$n} entities");
        }
    }

    /**
     * Deliberately empty rather than restoring 1.0.
     *
     * There is nothing to put back: the value did not come from a measurement,
     * so re-writing it would not restore information, it would re-introduce the
     * false assertion this migration exists to remove. Rolling back leaves the
     * column NULL, which is the honest state either way.
     */
    public function down(): void
    {
        // no-op, see docblock
    }
};
