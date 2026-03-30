<?php

namespace AhgCore\Contracts;

interface AgentRepository
{
    /**
     * Find an agent by ID with actor_i18n and entity-specific i18n.
     */
    public function findById(int $id, string $culture = 'en'): ?object;

    /**
     * Find an agent by slug.
     */
    public function findBySlug(string $slug, string $culture = 'en'): ?object;

    /**
     * Get descriptions created by this agent.
     */
    public function getCreatedDescriptions(int $id, string $culture = 'en', int $limit = 50): array;

    /**
     * Get related agents via the relation table.
     */
    public function getRelatedAgents(int $id): array;

    /**
     * Get maintained repositories (for corporate bodies).
     */
    public function getMaintainedRepositories(int $id): array;
}
