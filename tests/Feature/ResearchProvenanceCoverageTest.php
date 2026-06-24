<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Provenance-coverage guard for the research module (#1326).
 *
 * The AI/provenance infrastructure is complete (ahg-provenance-ai /
 * ahg_ai_inference + InferenceService; research_activity_log for human action).
 * The gap is COVERAGE: some research controllers mutate resources without
 * writing research_activity_log, and the Crossref/OpenAlex enrichment services
 * do not route through the provenance logger.
 *
 * These tests are ratchets, not one-off audits:
 *  - they fail if a NEW research controller mutates state without logging, and
 *  - they fail if a baseline entry is fixed but left in the allowlist,
 * so the backlog can only shrink. They are static-source checks (no DB).
 */
class ResearchProvenanceCoverageTest extends TestCase
{
    /** Methods that mutate state and therefore must leave a human-action trail. */
    private const MUTATION_RE = '/function\s+(store|update|destroy|delete|accept|reject|approve|create|save|merge)\w*\s*\(/i';

    /**
     * Research controllers that mutate state but do NOT yet leave a human-action
     * trail. Backlog for #1326 - now DRAINED (all 28 wired via the
     * LogsResearchActivity trait, 2026-06-24). Keep empty; NEVER add to it -
     * a new mutating controller must log from day one.
     */
    private array $activityLogBacklog = [];

    /**
     * Services that call external bibliographic enrichment (Crossref / OpenAlex)
     * but do NOT yet log to ahg_ai_inference. Backlog for #1326 bullet 2 -
     * now DRAINED (both route through InferenceService, 2026-06-24).
     */
    private array $enrichmentProvenanceBacklog = [];

    public function test_no_new_research_controller_mutates_without_activity_log(): void
    {
        $dir = base_path('packages/ahg-research/src/Controllers');
        $offenders = [];
        foreach (glob($dir.'/*.php') as $file) {
            $src = file_get_contents($file);
            // A controller leaves a human-action trail if it writes the table
            // directly OR uses the LogsResearchActivity trait (the canonical,
            // DRY mechanism added for #1326).
            $logs = str_contains($src, 'research_activity_log')
                || str_contains($src, 'logResearchActivity')
                || str_contains($src, 'LogsResearchActivity');
            if (preg_match(self::MUTATION_RE, $src) && ! $logs) {
                $offenders[] = basename($file, '.php');
            }
        }
        sort($offenders);

        $new = array_values(array_diff($offenders, $this->activityLogBacklog));
        $this->assertSame([], $new,
            'New research controller(s) mutate state without writing research_activity_log '.
            '(human-action provenance, #1326): '.implode(', ', $new));

        $resolved = array_values(array_diff($this->activityLogBacklog, $offenders));
        $this->assertSame([], $resolved,
            'These controllers now log research_activity_log - remove them from '.
            '$activityLogBacklog so the backlog shrinks: '.implode(', ', $resolved));
    }

    public function test_no_new_enrichment_service_skips_inference_provenance(): void
    {
        $dir = base_path('packages/ahg-research/src/Services');
        $offenders = [];
        foreach (glob($dir.'/*.php') as $file) {
            $src = file_get_contents($file);
            $callsEnrichment = (bool) preg_match('/crossref|openalex/i', $src);
            $logsInference = str_contains($src, 'ahg_ai_inference') || str_contains($src, 'InferenceService');
            if ($callsEnrichment && ! $logsInference) {
                $offenders[] = basename($file, '.php');
            }
        }
        sort($offenders);

        $new = array_values(array_diff($offenders, $this->enrichmentProvenanceBacklog));
        $this->assertSame([], $new,
            'New service(s) call Crossref/OpenAlex without routing through the provenance '.
            'logger (ahg_ai_inference / InferenceService, #1326): '.implode(', ', $new));

        $resolved = array_values(array_diff($this->enrichmentProvenanceBacklog, $offenders));
        $this->assertSame([], $resolved,
            'These enrichment services now log inference provenance - remove them from '.
            '$enrichmentProvenanceBacklog: '.implode(', ', $resolved));
    }
}
