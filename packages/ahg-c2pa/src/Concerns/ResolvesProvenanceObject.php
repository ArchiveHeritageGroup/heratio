<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgC2pa\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resolve an information object (by id or slug) for the C2PA provenance
 * services. tableExists(), loadObject() and resolveId() were byte-identical
 * in InferenceProvenanceService and PreservationTimelineService.
 */
trait ResolvesProvenanceObject
{
    private function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function loadObject(int $informationObjectId): ?object
    {
        $io = DB::table('information_object')
            ->where('id', $informationObjectId)
            ->first(['id', 'identifier']);
        if ($io === null) {
            return null;
        }

        $io->title = null;
        $io->slug  = null;

        if ($this->tableExists('information_object_i18n')) {
            $i18n = DB::table('information_object_i18n')
                ->where('id', $informationObjectId)
                ->orderByRaw("culture = 'en' DESC")
                ->first(['title']);
            $io->title = $i18n->title ?? null;
        }
        if ($this->tableExists('slug')) {
            $slug = DB::table('slug')->where('object_id', $informationObjectId)->first(['slug']);
            $io->slug = $slug->slug ?? null;
        }

        return $io;
    }

    private function resolveId(string $idOrSlug): ?int
    {
        $ref = trim($idOrSlug, '/');
        if ($ref === '') {
            return null;
        }

        if (ctype_digit($ref)) {
            $id = (int) $ref;

            return $id > 0 ? $id : null;
        }

        if (! $this->tableExists('slug')) {
            return null;
        }

        $row = DB::table('slug')->where('slug', $ref)->first(['object_id']);
        if ($row === null || ! isset($row->object_id)) {
            return null;
        }

        $id = (int) $row->object_id;

        return $id > 0 ? $id : null;
    }
}
