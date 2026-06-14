<?php

/**
 * RiCBridgeService - publishes research-portal domain objects into the RiC graph.
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

namespace AhgResearch\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Bridges the research lifecycle (#1254) onto the Records in Contexts graph
 * maintained by ahg-ric. Triggered by ResearchRiCSubscriber off the four
 * AhgResearch\Events lifecycle events.
 *
 * RiC mapping (FIRST CUT - see notes below, flagged for refinement):
 *   - A research project  -> rico:Activity (a research undertaking is an
 *     activity/process, not a static record). Title = project.title,
 *     start/end dates from the project row, status echoed into the description.
 *   - The project owner    -> rico:Agent, linked Activity --performed_by--> Agent
 *     (rico:isOrWasPerformedBy).
 *   - A research output    -> rico:Activity (a publication/dissemination act).
 *     Linked Output --is_associated_with--> Project (rico:isAssociatedWith,
 *     symmetric) so the graph can traverse both directions.
 *
 * REFINE LATER: outputs are modelled as Activities here to keep the first cut
 * lightweight and avoid polluting the information_object nested-set tree that
 * RicEntityService::createRecord() writes into. If outputs should become true
 * rico:Record / rico:Instantiation entities (with DOIs as instantiations),
 * revisit createOutputRicEntity(). Likewise the project->output predicate
 * 'includes' (rico:includes, RecordSet->Record) was considered but its declared
 * domain/range is hierarchical record containment; 'is_associated_with' is the
 * safer associative link for Activity<->Activity until a project-scoped
 * relation type is seeded.
 *
 * IDEMPOTENCY: a sidecar map table `research_ric_map` records the RiC id minted
 * for each (entity_type, entity_id) pair. Re-publishing reuses the existing RiC
 * id (updateActivity instead of createActivity) and relies on the natural-key
 * dedupe in research_ric_map plus RicEntityService::createRelation reuse. The
 * map table is created on demand (CREATE TABLE IF NOT EXISTS) the same way the
 * copilot-answer table is, so no migration / schema ALTER of locked tables is
 * needed.
 */
class RiCBridgeService
{
    private const MAP_TABLE = 'research_ric_map';

    /**
     * Publish a newly-created project to the RiC graph.
     */
    public function publishProjectCreated(int $projectId): void
    {
        $this->publishProject($projectId);
    }

    /**
     * Re-publish an updated project (idempotent - updates the existing Activity).
     */
    public function publishProjectUpdated(int $projectId): void
    {
        $this->publishProject($projectId);
    }

    /**
     * Publish a closed/completed project. Same Activity, refreshed end-date +
     * status so the graph reflects the closure.
     */
    public function publishProjectClosed(int $projectId): void
    {
        $this->publishProject($projectId);
    }

    /**
     * Publish a published research output and associate it with its project.
     */
    public function publishOutputPublished(int $outputId): void
    {
        if (! $this->ricAvailable()) {
            return;
        }

        try {
            $output = $this->loadOutput($outputId);
            if ($output === null) {
                Log::warning("[ahg-research] RiC bridge: output {$outputId} missing, skipped.");

                return;
            }

            $this->ensureMapTable();

            $ric = app(\AhgRic\Services\RicEntityService::class);

            $title = trim((string) ($output->title ?? '')) ?: ('Research output #' . $outputId);
            $payload = [
                'name'         => $title,
                'start_date'   => $this->normaliseDate($output->output_date ?? null),
                'description'  => $this->outputDescription($output),
            ];

            $activityId = $this->reuseOrCreateActivity($ric, 'output', $outputId, $payload);
            if ($activityId <= 0) {
                return;
            }

            // Associate the output with its parent project (if the project has
            // itself been published to RiC). is_associated_with is symmetric, so
            // the inverse edge is created by RicEntityService automatically.
            $projectId = (int) ($output->project_id ?? 0);
            if ($projectId > 0) {
                $projectRicId = $this->publishProject($projectId);
                if ($projectRicId > 0) {
                    $ric->createRelation($projectRicId, $activityId, 'is_associated_with');
                }
            }
        } catch (\Throwable $e) {
            // Never let a graph-publish failure break the triggering request.
            Log::warning('[ahg-research] RiC bridge publishOutputPublished failed: ' . $e->getMessage());
        }
    }

    // ================================================================
    // Internals
    // ================================================================

    /**
     * Ensure a rico:Activity exists for the project, refresh its fields, and
     * link it to its owner Agent. Returns the RiC activity id (0 on skip/fail).
     */
    private function publishProject(int $projectId): int
    {
        if (! $this->ricAvailable()) {
            return 0;
        }

        try {
            $project = $this->loadProject($projectId);
            if ($project === null) {
                Log::warning("[ahg-research] RiC bridge: project {$projectId} missing, skipped.");

                return 0;
            }

            $this->ensureMapTable();

            $ric = app(\AhgRic\Services\RicEntityService::class);

            $title = trim((string) ($project->title ?? '')) ?: ('Research project #' . $projectId);
            $payload = [
                'name'        => $title,
                'start_date'  => $this->normaliseDate($project->start_date ?? null),
                'end_date'    => $this->normaliseDate($project->actual_end_date ?? $project->expected_end_date ?? null),
                'description' => $this->projectDescription($project),
            ];

            $activityId = $this->reuseOrCreateActivity($ric, 'project', $projectId, $payload);
            if ($activityId <= 0) {
                return 0;
            }

            // Link the owner researcher as the performing Agent.
            $ownerName = $this->ownerDisplayName($project);
            if ($ownerName !== null) {
                $agentId = $this->reuseOrCreateAgent($ric, $project, $ownerName);
                if ($agentId > 0) {
                    // performed_by => rico:isOrWasPerformedBy (Activity -> Agent)
                    $ric->createRelation($activityId, $agentId, 'performed_by');
                }
            }

            return $activityId;
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] RiC bridge publishProject failed: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Reuse the mapped RiC Activity (update) or mint a new one (create), then
     * persist the mapping. Idempotent on (entity_type, entity_id).
     */
    private function reuseOrCreateActivity(object $ric, string $entityType, int $entityId, array $payload): int
    {
        $existing = $this->mappedRicId($entityType, $entityId);

        if ($existing > 0 && $ric->getActivityById($existing) !== null) {
            $ric->updateActivity($existing, $payload);

            return $existing;
        }

        $ricId = (int) $ric->createActivity($payload);
        if ($ricId > 0) {
            $this->storeMapping($entityType, $entityId, $ricId);
        }

        return $ricId;
    }

    /**
     * Reuse the mapped RiC Agent for the owner, or mint one. Keyed on the
     * researcher id when available, else falls back to a per-project agent.
     */
    private function reuseOrCreateAgent(object $ric, object $project, string $name): int
    {
        $ownerId = (int) ($project->owner_id ?? 0);
        $mapKey  = $ownerId > 0 ? $ownerId : (int) $project->id;
        $mapType = $ownerId > 0 ? 'researcher' : 'project_owner';

        $existing = $this->mappedRicId($mapType, $mapKey);
        if ($existing > 0) {
            return $existing;
        }

        // createAgent requires a non-empty name (throws otherwise).
        $ricId = (int) $ric->createAgent(['name' => $name]);
        if ($ricId > 0) {
            $this->storeMapping($mapType, $mapKey, $ricId);
        }

        return $ricId;
    }

    private function loadProject(int $projectId): ?object
    {
        try {
            if (! Schema::hasTable('research_project')) {
                return null;
            }

            return DB::table('research_project as p')
                ->leftJoin('research_researcher as r', 'p.owner_id', '=', 'r.id')
                ->where('p.id', $projectId)
                ->select(
                    'p.*',
                    'r.first_name as owner_first_name',
                    'r.last_name as owner_last_name',
                    'r.email as owner_email'
                )
                ->first();
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] RiC bridge loadProject failed: ' . $e->getMessage());

            return null;
        }
    }

    private function loadOutput(int $outputId): ?object
    {
        try {
            if (! Schema::hasTable('research_output')) {
                return null;
            }

            return DB::table('research_output')->where('id', $outputId)->first();
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] RiC bridge loadOutput failed: ' . $e->getMessage());

            return null;
        }
    }

    private function ownerDisplayName(object $project): ?string
    {
        $name = trim(
            (string) ($project->owner_first_name ?? '') . ' ' . (string) ($project->owner_last_name ?? '')
        );
        if ($name !== '') {
            return $name;
        }

        $email = trim((string) ($project->owner_email ?? ''));

        return $email !== '' ? $email : null;
    }

    private function projectDescription(object $project): string
    {
        $parts = ['Research project published from Heratio.'];
        $status = trim((string) ($project->status ?? ''));
        if ($status !== '') {
            $parts[] = 'Status: ' . $status . '.';
        }
        $type = trim((string) ($project->project_type ?? ''));
        if ($type !== '') {
            $parts[] = 'Type: ' . $type . '.';
        }

        return implode(' ', $parts);
    }

    private function outputDescription(object $output): string
    {
        $parts = ['Research output published from Heratio.'];
        $type = trim((string) ($output->output_type ?? ''));
        if ($type !== '') {
            $parts[] = 'Type: ' . $type . '.';
        }
        $venue = trim((string) ($output->venue ?? ''));
        if ($venue !== '') {
            $parts[] = 'Venue: ' . $venue . '.';
        }
        $idType = trim((string) ($output->identifier_type ?? ''));
        $id     = trim((string) ($output->identifier ?? ''));
        if ($idType !== '' && $id !== '') {
            $parts[] = strtoupper($idType) . ': ' . $id . '.';
        }

        return implode(' ', $parts);
    }

    /**
     * Coerce a date-ish value to a Y-m-d string or null. Tolerates empty
     * strings, full datetimes, and already-formatted dates.
     */
    private function normaliseDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);
        if ($s === '' || str_starts_with($s, '0000')) {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse($s)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function ricAvailable(): bool
    {
        return class_exists(\AhgRic\Services\RicEntityService::class);
    }

    // ---- sidecar map table ---------------------------------------------

    private function mappedRicId(string $entityType, int $entityId): int
    {
        try {
            $row = DB::table(self::MAP_TABLE)
                ->where('entity_type', $entityType)
                ->where('entity_id', $entityId)
                ->value('ric_id');

            return $row ? (int) $row : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function storeMapping(string $entityType, int $entityId, int $ricId): void
    {
        try {
            DB::table(self::MAP_TABLE)->updateOrInsert(
                ['entity_type' => $entityType, 'entity_id' => $entityId],
                ['ric_id' => $ricId, 'updated_at' => now(), 'created_at' => now()]
            );
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] RiC bridge storeMapping failed: ' . $e->getMessage());
        }
    }

    /**
     * Create the sidecar map table on demand. Idempotent; mirrors the
     * copilot-answer runtime-install pattern (reference_ci_schema_hastable):
     * one outer try around hasTable + the DDL.
     */
    private function ensureMapTable(): void
    {
        try {
            if (Schema::hasTable(self::MAP_TABLE)) {
                return;
            }

            DB::unprepared(
                'CREATE TABLE IF NOT EXISTS `' . self::MAP_TABLE . '` (
                    `id` int NOT NULL AUTO_INCREMENT,
                    `entity_type` varchar(32) NOT NULL,
                    `entity_id` int NOT NULL,
                    `ric_id` int NOT NULL,
                    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uniq_entity` (`entity_type`, `entity_id`),
                    KEY `idx_ric` (`ric_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (\Throwable $e) {
            // DB not ready / race - retries on next publish.
            Log::warning('[ahg-research] RiC bridge ensureMapTable failed: ' . $e->getMessage());
        }
    }
}
