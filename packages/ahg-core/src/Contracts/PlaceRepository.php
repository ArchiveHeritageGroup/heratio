<?php

namespace AhgCore\Contracts;

interface PlaceRepository
{
    /**
     * Find a place by term ID with i18n data.
     */
    public function findById(int $id, string $culture = 'en'): ?object;

    /**
     * Get descriptions associated with this place.
     */
    public function getRelatedDescriptions(int $id): array;

    /**
     * Get agents associated with this place.
     */
    public function getRelatedAgents(int $id): array;

    /**
     * Get child places in the hierarchy.
     */
    public function getChildren(int $id, string $culture = 'en'): array;
}
