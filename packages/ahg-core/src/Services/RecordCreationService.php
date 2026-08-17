<?php

/**
 * RecordCreationService - the writes every record-creation path owes.
 *
 * Creating an information object is not just the `information_object` row. It
 * also needs a publication status and a node in the hierarchy closure tree.
 * Because ~17 paths across the codebase hand-rolled their own creation, most
 * omitted one or both:
 *
 *  - No `status` row (#1461): the record has neither Draft nor Published, just
 *    nothing. It reads as unpublished to guests (the show page 404s) AND is
 *    matched by no publication-status filter, so it is invisible to the public
 *    and missing from the admin's Draft list. A state no operator chose.
 *  - No closure node (#1462): the record is absent from every closure-backed
 *    descendant query - including from its own ancestors - so a collection
 *    walk silently skips it. The lft/rgt nested set is chronically stale, which
 *    is exactly why closure is authoritative for descendants.
 *
 * Both halves are idempotent, so this is safe to call from a path that already
 * does part of the work, and safe to call twice.
 *
 * Lives in ahg-core rather than ahg-information-object-manage so importers and
 * sector packages can call it without depending on a sibling feature package -
 * the same direction ClosureMaintenanceService is already used in.
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

namespace AhgCore\Services;

use AhgCore\Constants\TermId;
use AhgCore\Support\GlobalSettings;
use Illuminate\Support\Facades\DB;

class RecordCreationService
{
    /**
     * Finish creating an information object: publication status + closure node.
     *
     * Call AFTER the information_object row exists, inside the caller's
     * transaction where it has one.
     *
     * @param int|null $publicationStatusId explicit status; null defers to the
     *                                      defaultPubStatus global, then Draft
     */
    public static function finalizeInformationObject(int $objectId, ?int $parentId = null, ?int $publicationStatusId = null): void
    {
        self::ensurePublicationStatus($objectId, $publicationStatusId);
        self::ensureClosureNode($objectId, $parentId);
    }

    /**
     * Give the object a publication status if it has none.
     *
     * Resolution: explicit argument > the operator's `defaultPubStatus` global
     * > Draft. An unrecognised value falls back to Draft rather than writing a
     * status nothing matches.
     *
     * Never overwrites an existing status - a caller that already set one, or
     * an operator who has since published the record, wins.
     *
     * @return int the status_id now in force
     */
    public static function ensurePublicationStatus(int $objectId, ?int $publicationStatusId = null): int
    {
        $existing = DB::table('status')
            ->where('object_id', $objectId)
            ->where('type_id', TermId::STATUS_TYPE_PUBLICATION)
            ->value('status_id');
        if ($existing !== null) {
            return (int) $existing;
        }

        $statusId = ! empty($publicationStatusId)
            ? (int) $publicationStatusId
            : GlobalSettings::defaultPublicationStatusId(TermId::PUBLICATION_STATUS_DRAFT);

        if (! in_array($statusId, [TermId::PUBLICATION_STATUS_DRAFT, TermId::PUBLICATION_STATUS_PUBLISHED], true)) {
            $statusId = TermId::PUBLICATION_STATUS_DRAFT;
        }

        DB::table('status')->insert([
            // #1470: status.id must be an object id, not one from status's
            // own AUTO_INCREMENT counter.
            'id' => \AhgCore\Support\StatusRow::allocateId(),
            'object_id' => $objectId,
            'type_id' => TermId::STATUS_TYPE_PUBLICATION,
            'status_id' => $statusId,
            'serial_number' => 0,
        ]);

        return $statusId;
    }

    /**
     * Add the object to the hierarchy closure tree (#1333).
     *
     * Best-effort: ClosureMaintenanceService::addNode already no-ops when the
     * closure tables are absent and is itself idempotent, and a closure failure
     * must never lose a record that is otherwise created.
     */
    public static function ensureClosureNode(int $objectId, ?int $parentId): void
    {
        try {
            app(ClosureMaintenanceService::class)
                ->addNode('information_object', $objectId, $parentId !== null ? (int) $parentId : null);
        } catch (\Throwable $e) {
            // Closure infrastructure unavailable - not fatal to the creation.
        }
    }
}
