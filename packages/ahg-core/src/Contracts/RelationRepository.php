<?php

namespace AhgCore\Contracts;

interface RelationRepository
{
    /**
     * Get all relations where entity is subject or object.
     */
    public function getRelationsForEntity(int $entityId, ?int $typeId = null): array;

    /**
     * Get relation by ID.
     */
    public function findById(int $id): ?object;

    /**
     * Check if a relation exists between two entities.
     */
    public function exists(int $subjectId, int $objectId, ?int $typeId = null): bool;

    /**
     * Get relation types (from term table, taxonomy for relation types).
     */
    public function getRelationTypes(): array;
}
