<?php

/**
 * AhgAuthorityResolutionServiceProvider - Service for Heratio
 *
 * Registers the authority-resolution engine's services in the Laravel
 * container and the artisan command. Auto-seeds the role-language token
 * config on first boot if the ahg_settings row is missing.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgAuthorityResolution\Providers;

use AhgAuthorityResolution\Console\Commands\CacheClearCommand;
use AhgAuthorityResolution\Console\Commands\CacheStatsCommand;
use AhgAuthorityResolution\Console\Commands\ExportNerFeedbackCommand;
use AhgAuthorityResolution\Console\Commands\GenerateCandidatesCommand;
use AhgAuthorityResolution\Console\Commands\PromoteSampleCommand;
use AhgAuthorityResolution\Console\Commands\ReprocessCommand;
use AhgAuthorityResolution\Console\Commands\ReprocessParkedCommand;
use AhgAuthorityResolution\Console\Commands\ScanParkedCommand;
use AhgAuthorityResolution\Console\Commands\ScoreEvidenceCommand;
use AhgAuthorityResolution\Console\Commands\StatusCommand;
use AhgAuthorityResolution\Console\Commands\WriteProvenanceCommand;
use AhgAuthorityResolution\Services\Adapters\FusekiAgentAdapter;
use AhgAuthorityResolution\Services\Adapters\FusekiPlaceAdapter;
use AhgAuthorityResolution\Services\Adapters\MysqlActorAdapter;
use AhgAuthorityResolution\Services\Adapters\MysqlTermAdapter;
use AhgAuthorityResolution\Services\AssignmentService;
use AhgAuthorityResolution\Services\AuthorityCreator;
use AhgAuthorityResolution\Services\CandidateGeneratorService;
use AhgAuthorityResolution\Services\ContextDerivationService;
use AhgAuthorityResolution\Services\DecisionProvenanceWriter;
use AhgAuthorityResolution\Services\DecisionRecorder;
use AhgAuthorityResolution\Services\Evidence\ConflictEvaluator;
use AhgAuthorityResolution\Services\Evidence\CoOccurringPersonEvaluator;
use AhgAuthorityResolution\Services\Evidence\DocumentPriorService;
use AhgAuthorityResolution\Services\Evidence\GeographicEvaluator;
use AhgAuthorityResolution\Services\Evidence\HierarchicalEvaluator;
use AhgAuthorityResolution\Services\Evidence\PlaceConflictEvaluator;
use AhgAuthorityResolution\Services\Evidence\PriorEvaluator;
use AhgAuthorityResolution\Services\Evidence\RelationalEvaluator;
use AhgAuthorityResolution\Services\Evidence\RoleEvaluator;
use AhgAuthorityResolution\Services\Evidence\ScaleEvaluator;
use AhgAuthorityResolution\Services\Evidence\TemporalEvaluator;
use AhgAuthorityResolution\Services\EvidenceScorer;
use AhgAuthorityResolution\Services\FieldProvenanceWriter;
use AhgAuthorityResolution\Services\Lookup\Adapters\GeoNamesAdapter;
use AhgAuthorityResolution\Services\Lookup\Adapters\GndAdapter;
use AhgAuthorityResolution\Services\Lookup\Adapters\IsniAdapter;
use AhgAuthorityResolution\Services\Lookup\Adapters\SagncAdapter;
use AhgAuthorityResolution\Services\Lookup\Adapters\TgnAdapter;
use AhgAuthorityResolution\Services\Lookup\Adapters\ViafAdapter;
use AhgAuthorityResolution\Services\Lookup\Adapters\WikidataAdapter;
use AhgAuthorityResolution\Services\Lookup\PrefillEngine;
use AhgAuthorityResolution\Services\NerFeedbackService;
use AhgAuthorityResolution\Services\ParkQueueService;
use AhgAuthorityResolution\Services\PromoteToMentionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgAuthorityResolutionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ContextDerivationService::class);
        $this->app->singleton(PromoteToMentionService::class);
        $this->app->singleton(DecisionProvenanceWriter::class);

        // Task 9: NER feedback capture. NerFeedbackService depends on
        // PromoteToMentionService::fetchSourceText() (made public for this).
        $this->app->singleton(NerFeedbackService::class, function ($app) {
            return new NerFeedbackService($app->make(PromoteToMentionService::class));
        });

        // DecisionRecorder now optionally accepts NerFeedbackService - on
        // recordReject() it captures a ahg_ner_feedback row in a
        // try/catch so the reject decision is never blocked.
        $this->app->singleton(DecisionRecorder::class, function ($app) {
            return new DecisionRecorder(
                $app->make(DecisionProvenanceWriter::class),
                $app->make(NerFeedbackService::class)
            );
        });

        $this->app->singleton(MysqlActorAdapter::class);
        $this->app->singleton(MysqlTermAdapter::class);
        $this->app->singleton(FusekiAgentAdapter::class);
        $this->app->singleton(FusekiPlaceAdapter::class);

        $this->app->singleton(CandidateGeneratorService::class, function ($app) {
            return new CandidateGeneratorService([
                $app->make(MysqlActorAdapter::class),
                $app->make(MysqlTermAdapter::class),
                $app->make(FusekiAgentAdapter::class),
                $app->make(FusekiPlaceAdapter::class),
            ]);
        });

        // ----- Task 4: evidence assembly + scoring -----
        $this->app->singleton(DocumentPriorService::class);

        $this->app->singleton(TemporalEvaluator::class);
        $this->app->singleton(GeographicEvaluator::class);
        $this->app->singleton(RelationalEvaluator::class);
        $this->app->singleton(RoleEvaluator::class);
        $this->app->singleton(ConflictEvaluator::class);

        $this->app->singleton(HierarchicalEvaluator::class);
        $this->app->singleton(PriorEvaluator::class, function ($app) {
            return new PriorEvaluator($app->make(DocumentPriorService::class));
        });
        $this->app->singleton(CoOccurringPersonEvaluator::class);
        $this->app->singleton(PlaceConflictEvaluator::class);
        $this->app->singleton(ScaleEvaluator::class);

        $this->app->singleton(EvidenceScorer::class, function ($app) {
            return new EvidenceScorer([
                // person/org evaluators
                $app->make(TemporalEvaluator::class),
                $app->make(GeographicEvaluator::class),
                $app->make(RelationalEvaluator::class),
                $app->make(RoleEvaluator::class),
                $app->make(ConflictEvaluator::class),
                // place evaluators
                $app->make(HierarchicalEvaluator::class),
                $app->make(PriorEvaluator::class),
                $app->make(CoOccurringPersonEvaluator::class),
                $app->make(PlaceConflictEvaluator::class),
                $app->make(ScaleEvaluator::class),
            ], $app->make(DocumentPriorService::class));
        });

        // ----- Task 7: parked-mention queue + background scan -----
        $this->app->singleton(ParkQueueService::class, function ($app) {
            return new ParkQueueService(
                $app->make(CandidateGeneratorService::class),
                $app->make(EvidenceScorer::class)
            );
        });

        // ----- Task 6: pre-fill + authority creation + field provenance -----
        $this->app->singleton(ViafAdapter::class);
        $this->app->singleton(WikidataAdapter::class);
        $this->app->singleton(GeoNamesAdapter::class);
        $this->app->singleton(TgnAdapter::class);
        $this->app->singleton(GndAdapter::class);
        $this->app->singleton(IsniAdapter::class);
        $this->app->singleton(SagncAdapter::class);

        $this->app->singleton(PrefillEngine::class, function ($app) {
            return new PrefillEngine([
                $app->make(ViafAdapter::class),
                $app->make(WikidataAdapter::class),
                $app->make(GeoNamesAdapter::class),
                $app->make(TgnAdapter::class),
                $app->make(GndAdapter::class),
                $app->make(IsniAdapter::class),
                $app->make(SagncAdapter::class),
            ]);
        });

        $this->app->singleton(AuthorityCreator::class);
        $this->app->singleton(FieldProvenanceWriter::class);

        // Assign / Workflow feature: routes mentions through ahg-workflow.
        $this->app->singleton(AssignmentService::class);
    }

    public function boot(): void
    {
        try {
            if (Schema::hasTable('ahg_settings')) {
                $this->autoSeedRoleLanguageTokens();
                $this->autoSeedDecisionsGraphUri();
                $this->autoSeedCandidateTopN();
                $this->autoSeedLookupSettings();
            }
            // Assign / Workflow feature: seed the "Authority Resolution
            // Review" workflow definition into the ahg-workflow tables if
            // they exist and the workflow is missing.
            if (Schema::hasTable('ahg_workflow') && Schema::hasTable('ahg_workflow_step')) {
                $this->autoSeedAuthResWorkflow();
            }
        } catch (\Throwable $e) {
            // Boot must never break the request. Settings auto-seed is
            // a nice-to-have; failure is silent.
        }

        // Task 5: Review UI - admin routes + Blade views.
        $this->loadRoutesFrom(__DIR__.'/../../routes/admin.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'auth-res');

        if ($this->app->runningInConsole()) {
            $this->commands([
                PromoteSampleCommand::class,
                WriteProvenanceCommand::class,
                GenerateCandidatesCommand::class,
                ScoreEvidenceCommand::class,
                ScanParkedCommand::class,
                ExportNerFeedbackCommand::class,
                // Task 10: CLI consolidation
                StatusCommand::class,
                ReprocessCommand::class,
                ReprocessParkedCommand::class,
                CacheStatsCommand::class,
                CacheClearCommand::class,
            ]);
        }
    }

    /**
     * Task 6: external authority lookup settings (seven sources, plus
     * precedence + http timeout + field-provenance graph URI). Reads the
     * shipped install file `database/seed_lookup_settings.sql` if it
     * exists; otherwise no-op. Idempotent via INSERT IGNORE in the SQL.
     */
    private function autoSeedLookupSettings(): void
    {
        // Cheap probe: skip if at least one lookup.* key already present.
        $exists = DB::table('ahg_settings')
            ->where('setting_key', 'like', 'lookup.%')
            ->exists();
        if ($exists) {
            return;
        }

        $sqlPath = __DIR__.'/../../database/seed_lookup_settings.sql';
        if (! is_file($sqlPath)) {
            return;
        }
        $sql = file_get_contents($sqlPath);
        if ($sql === false || trim($sql) === '') {
            return;
        }

        // Split on `;` at end-of-line (matches the seed file structure).
        // Each block typically starts with `-- ----- <source> -----` comments
        // followed by the actual INSERT IGNORE statement. We strip leading
        // comment lines before evaluating + executing.
        $rawStatements = preg_split('/;\s*\n/', $sql) ?: [];
        foreach ($rawStatements as $raw) {
            $stmt = $this->stripLeadingCommentLines((string) $raw);
            $stmt = rtrim($stmt, ";\n\t ");
            if ($stmt === '') {
                continue;
            }
            try {
                DB::unprepared($stmt.';');
            } catch (\Throwable $e) {
                // One bad row should not abort the rest.
            }
        }
    }

    private function stripLeadingCommentLines(string $sql): string
    {
        $lines = preg_split('/\r?\n/', $sql) ?: [];
        $out = [];
        $inBody = false;
        foreach ($lines as $line) {
            $trim = ltrim($line);
            if (! $inBody && ($trim === '' || str_starts_with($trim, '--'))) {
                continue;
            }
            $inBody = true;
            $out[] = $line;
        }

        return trim(implode("\n", $out));
    }

    /**
     * Assign / Workflow feature: insert the "Authority Resolution Review"
     * workflow + its single "Review" step if they are not already present.
     * Keyed on the workflow name so it is safe to re-run. Executes the
     * shipped database/seed_workflow.sql when available; falls back to an
     * inline insert if the file is missing.
     */
    private function autoSeedAuthResWorkflow(): void
    {
        $workflowName = 'Authority Resolution Review';

        $exists = DB::table('ahg_workflow')->where('name', $workflowName)->exists();
        if ($exists) {
            return;
        }

        $sqlPath = __DIR__.'/../../database/seed_workflow.sql';
        if (is_file($sqlPath)) {
            $sql = file_get_contents($sqlPath);
            if ($sql !== false && trim($sql) !== '') {
                $rawStatements = preg_split('/;\s*\n/', $sql) ?: [];
                foreach ($rawStatements as $raw) {
                    $stmt = $this->stripLeadingCommentLines((string) $raw);
                    $stmt = rtrim($stmt, ";\n\t ");
                    if ($stmt === '') {
                        continue;
                    }
                    try {
                        DB::unprepared($stmt.';');
                    } catch (\Throwable $e) {
                        // One bad statement should not abort the rest.
                    }
                }
                // If the SQL seed produced the workflow, we are done.
                if (DB::table('ahg_workflow')->where('name', $workflowName)->exists()) {
                    return;
                }
            }
        }

        // Inline fallback - seed file missing or did not apply.
        $workflowId = DB::table('ahg_workflow')->insertGetId([
            'name' => $workflowName,
            'description' => 'Routes promoted NER mentions assigned by an archivist through a single review step. Created by the AHG Authority Resolution Engine (Assign / Workflow feature).',
            'scope_type' => 'global',
            'scope_id' => null,
            'trigger_event' => 'submit',
            'applies_to' => 'ahg_mention',
            'is_active' => 1,
            'is_default' => 0,
            'require_all_steps' => 1,
            'allow_parallel' => 0,
            'notification_enabled' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ahg_workflow_step')->insert([
            'workflow_id' => $workflowId,
            'name' => 'Review',
            'description' => 'Review the assigned mention and resolve it to an authority record (link / create / reject).',
            'step_order' => 1,
            'step_type' => 'review',
            'action_required' => 'approve_reject',
            'pool_enabled' => 1,
            'notification_template' => 'default',
            'instructions' => 'Open the mention in the Authority Resolution review screen, weigh the ranked candidates against the evidence packet, and record a link / create-new / reject decision.',
            'is_optional' => 0,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function autoSeedCandidateTopN(): void
    {
        $exists = DB::table('ahg_settings')
            ->where('setting_key', 'authority_resolution.candidate_top_n')
            ->exists();
        if ($exists) {
            return;
        }
        DB::table('ahg_settings')->insert([
            'setting_key' => 'authority_resolution.candidate_top_n',
            'setting_group' => 'authority_resolution',
            'setting_type' => 'int',
            'setting_value' => '5',
            'description' => 'Top-N candidates to persist per mention in ahg_mention_candidate (CandidateGeneratorService default).',
            'is_sensitive' => 0,
            'is_locked' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function autoSeedDecisionsGraphUri(): void
    {
        $exists = DB::table('ahg_settings')
            ->where('setting_key', 'authority_resolution.decisions_graph_uri')
            ->exists();
        if ($exists) {
            return;
        }
        DB::table('ahg_settings')->insert([
            'setting_key' => 'authority_resolution.decisions_graph_uri',
            'setting_group' => 'authority_resolution',
            'setting_type' => 'string',
            'setting_value' => DecisionProvenanceWriter::DEFAULT_GRAPH_URI,
            'description' => 'Fuseki named-graph URI for authority-resolution decision provenance (RDF-Star).',
            'is_sensitive' => 0,
            'is_locked' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function autoSeedRoleLanguageTokens(): void
    {
        $exists = DB::table('ahg_settings')
            ->where('setting_key', 'authority_resolution.role_language_tokens')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('ahg_settings')->insert([
            'setting_key' => 'authority_resolution.role_language_tokens',
            'setting_group' => 'authority_resolution',
            'setting_type' => 'json',
            'setting_value' => json_encode($this->defaultRoleLanguageTokens(), JSON_UNESCAPED_UNICODE),
            'description' => 'Role-language tokens for authority-resolution context derivation. Keys are kinds (kinship/witness/location/movement/other); values are lowercased token lists.',
            'is_sensitive' => 0,
            'is_locked' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string,list<string>>
     */
    private function defaultRoleLanguageTokens(): array
    {
        return [
            'kinship' => [
                'son of', 'daughter of', 'child of', 'children of',
                'father of', 'mother of', 'parent of', 'parents of',
                'brother of', 'sister of', 'sibling of',
                'wife of', 'husband of', 'spouse of',
                'descendant of', 'ancestor of',
                'uncle of', 'aunt of', 'cousin of', 'nephew of', 'niece of',
                'grandson of', 'granddaughter of', 'grandfather of', 'grandmother of',
            ],
            'witness' => [
                'witnessed by', 'witness was', 'witnesses were',
                'signed by', 'attested by', 'testified by',
                'present at', 'in the presence of', 'in attendance',
                'co-signed by', 'countersigned by',
            ],
            'location' => [
                'located in', 'located at', 'situated in', 'situated at',
                'found at', 'found in', 'based in', 'based at',
                'residing at', 'residing in', 'resident of', 'resident at',
                'dwelling at', 'dwelling in', 'living at', 'living in',
                'born at', 'born in', 'born on',
                'died at', 'died in', 'died on',
                'buried at', 'buried in',
            ],
            'movement' => [
                'travelled to', 'traveled to', 'travelled from', 'traveled from',
                'moved to', 'moved from', 'relocated to', 'relocated from',
                'returned from', 'returned to', 'departed for', 'departed from',
                'journeyed to', 'journeyed from',
                'sailed from', 'sailed to', 'sailed for',
                'arrived at', 'arrived in', 'arrived from',
                'fled to', 'fled from', 'escaped to', 'escaped from',
                'emigrated to', 'immigrated to', 'migrated to',
            ],
            'other' => [
                'officiated by', 'officiated at',
                'ruled by', 'ruled over', 'governed by', 'governed',
                'owned by', 'owned', 'possessed by',
                'served by', 'served as', 'served at', 'served in',
                'appointed by', 'appointed as', 'appointed to',
                'succeeded by', 'succeeded',
                'preceded by', 'preceded',
                'employed by', 'employed at', 'worked for', 'worked at',
                'educated at', 'studied at', 'graduated from',
                'founded', 'founded by', 'co-founded',
                'commanded by', 'commanded', 'led by', 'led',
            ],
        ];
    }
}
