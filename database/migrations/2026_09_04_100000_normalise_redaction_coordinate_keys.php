<?php

/**
 * Normalise privacy_visual_redaction.coordinates onto one key shape.
 *
 * The column holds two shapes for the same rectangle: the editor writes
 * left/top/width/height, but rows exist as x/y/width/height - five of each on
 * heratio-dev. Only the editor's own loader ever read both, via
 * `$coords['left'] ?? $coords['x']`. Three consumers read left/top alone and
 * each failed differently on an x/y row: the public overlay computed NaN and
 * drew the mask at the right size in the wrong place, the burn-in renderer
 * defaulted both to zero and baked it into the corner of a derivative file,
 * and the audit snapshot diffed two identical rectangles as different.
 *
 * v1.154.714 routed all four through PrivacyService::redactionRect(), so
 * nothing is broken today. This removes the trap rather than the symptom: a
 * fifth consumer written next year will reach for 'left' and be right.
 *
 * Deliberately NOT calling redactionRect(): a migration is a historical record
 * and must not depend on code that can move or change meaning after it has run.
 * The four lines below are the same decision, frozen.
 *
 * Only rows that actually need it are rewritten, so a re-run is a no-op, and a
 * row whose JSON does not parse is left exactly as it is rather than replaced
 * with zeros - a zero-size rectangle is skipped by both renderers, so
 * "repairing" an unreadable row would silently drop a redaction.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('privacy_visual_redaction')) {
            return;
        }

        $rewritten = 0;
        $skipped = 0;

        foreach (DB::table('privacy_visual_redaction')->select('id', 'coordinates')->cursor() as $row) {
            $c = json_decode((string) ($row->coordinates ?? ''), true);

            // Unreadable or not an object: leave it alone and say so.
            if (! is_array($c) || $c === []) {
                $skipped++;

                continue;
            }

            // Already the canonical shape.
            if (array_key_exists('left', $c) && array_key_exists('top', $c)
                && array_key_exists('width', $c) && array_key_exists('height', $c)) {
                continue;
            }

            $rect = [
                'left' => (float) ($c['left'] ?? $c['x'] ?? 0),
                'top' => (float) ($c['top'] ?? $c['y'] ?? 0),
                'width' => (float) ($c['width'] ?? $c['w'] ?? 0),
                'height' => (float) ($c['height'] ?? $c['h'] ?? 0),
            ];

            // A rectangle with no extent would be dropped by both renderers, so
            // rewriting it would turn "unreadable" into "silently absent".
            if ($rect['width'] <= 0 || $rect['height'] <= 0) {
                $skipped++;

                continue;
            }

            DB::table('privacy_visual_redaction')
                ->where('id', $row->id)
                ->update(['coordinates' => json_encode($rect)]);
            $rewritten++;
        }

        if ($rewritten || $skipped) {
            \Log::info('[redaction] coordinate keys normalised', [
                'rewritten' => $rewritten,
                'left_alone' => $skipped,
            ]);
        }
    }

    public function down(): void
    {
        // Nothing to reverse. Both shapes mean the same rectangle and every
        // reader accepts either, so restoring x/y would only put the trap back.
    }
};
