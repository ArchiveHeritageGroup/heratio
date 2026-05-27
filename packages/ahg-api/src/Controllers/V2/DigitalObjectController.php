<?php

/**
 * DigitalObjectController - V2 REST endpoints scoped to a single digital
 * object. Today this controller's only responsibility is exposing the
 * `embedded_metadata` block as a standalone payload (issue #747) so that
 * clients which already have a description payload can fetch the embedded
 * EXIF/IPTC/XMP without re-fetching the whole description.
 *
 * Copyright (C) 2026 Plain Sailing Information Systems
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

declare(strict_types=1);

namespace AhgApi\Controllers\V2;

use AhgApi\Services\EmbeddedMetadataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DigitalObjectController extends BaseApiController
{
    protected EmbeddedMetadataService $embedded;

    public function __construct(?EmbeddedMetadataService $embedded = null)
    {
        parent::__construct();
        $this->embedded = $embedded ?? new EmbeddedMetadataService();
    }

    /**
     * GET /api/v2/digital-object/{id}/embedded-metadata
     *
     * Returns ONLY the embedded EXIF/IPTC/XMP block for a single digital
     * object. Useful for clients that already have a description payload
     * and just want to lazy-load the heavier embedded block.
     *
     * ODRL: when the parent IO has an active odrl:use prohibition for the
     * caller, returns 403. (Differs from the inline include on /descriptions
     * which silently drops the key - here the block IS the response, so we
     * must signal denial explicitly.)
     */
    public function embeddedMetadata(int $id, Request $request): JsonResponse
    {
        $do = DB::table('digital_object')->where('id', $id)->first(['id', 'object_id']);
        if (! $do) {
            return $this->error('Not Found', "Digital object #{$id} not found.", 404);
        }

        $block = $this->embedded->forDigitalObject((int) $do->id, $this->apiUserId($request));

        if ($block === null) {
            return $this->error('Forbidden', 'Embedded metadata access denied by ODRL policy.', 403);
        }

        return $this->success([
            'digital_object_id' => (int) $do->id,
            'information_object_id' => (int) $do->object_id,
            'embedded_metadata' => $block,
        ]);
    }
}
