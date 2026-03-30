<?php

namespace AhgCore\Contracts;

interface DescriptionRepository
{
    /**
     * Find a description by ID with its i18n data.
     */
    public function findById(int $id, string $culture = 'en'): ?object;

    /**
     * Find a description by slug.
     */
    public function findBySlug(string $slug, string $culture = 'en'): ?object;

    /**
     * Get the parent description.
     */
    public function getParent(int $id): ?object;

    /**
     * Get child descriptions.
     */
    public function getChildren(int $id, string $culture = 'en', int $limit = 50): array;

    /**
     * Get the full hierarchy path (ancestors).
     */
    public function getAncestors(int $id, string $culture = 'en'): array;

    /**
     * Get related descriptions via the relation table.
     */
    public function getRelated(int $id): array;

    /**
     * Get creators (agents linked via event table).
     */
    public function getCreators(int $id, string $culture = 'en'): array;

    /**
     * Get subjects (terms linked via object_term_relation).
     */
    public function getSubjects(int $id): array;
}
