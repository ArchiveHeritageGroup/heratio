<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgMetadataExport\Services\Exporters\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Whether an information object is published (status table). Byte-identical
 * in CidocCrmSerializer and PremisSerializer. Uses the host's
 * STATUS_TYPE_PUBLICATION + PUBLICATION_STATUS_PUBLISHED constants.
 */
trait ChecksPublicationStatus
{
    private function isPublic(int $objectId): bool
    {
        if ($objectId <= 1) {
            return false;
        }
        if (! Schema::hasTable('status')) {
            return false;
        }

        return DB::table('status')
            ->where('object_id', $objectId)
            ->where('type_id', self::STATUS_TYPE_PUBLICATION)
            ->where('status_id', self::PUBLICATION_STATUS_PUBLISHED)
            ->exists();
    }
}
