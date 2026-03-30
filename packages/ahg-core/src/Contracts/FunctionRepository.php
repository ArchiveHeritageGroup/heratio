<?php

namespace AhgCore\Contracts;

interface FunctionRepository
{
    /**
     * Find a function by ID with i18n data.
     */
    public function findById(int $id, string $culture = 'en'): ?object;

    /**
     * Find a function by slug.
     */
    public function findBySlug(string $slug, string $culture = 'en'): ?object;

    /**
     * Get related descriptions linked to this function.
     */
    public function getRelatedDescriptions(int $id): array;

    /**
     * Get sub-functions.
     */
    public function getChildren(int $id, string $culture = 'en'): array;
}
