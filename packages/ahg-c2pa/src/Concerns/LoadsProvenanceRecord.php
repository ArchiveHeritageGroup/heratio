<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgC2pa\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Load an information object (with title + slug) for the C2PA provenance
 * controllers. Byte-identical in ProvenanceController and VerifyController
 * (the Schema::hasTable-guarded variant; the services use the
 * $this->tableExists variant in ResolvesProvenanceObject).
 */
trait LoadsProvenanceRecord
{
    private function loadObject(int $informationObjectId): ?object
    {
        if (!Schema::hasTable('information_object')) {
            return null;
        }
        $io = DB::table('information_object')->where('id', $informationObjectId)->first(['id', 'identifier']);
        if ($io === null) {
            return null;
        }
        if (Schema::hasTable('information_object_i18n')) {
            $i18n = DB::table('information_object_i18n')
                ->where('id', $informationObjectId)
                ->orderByRaw("culture = 'en' DESC")
                ->first(['title']);
            $io->title = $i18n->title ?? null;
        }
        if (Schema::hasTable('slug')) {
            $slug = DB::table('slug')->where('object_id', $informationObjectId)->first(['slug']);
            $io->slug = $slug->slug ?? null;
        }
        return $io;
    }
}
