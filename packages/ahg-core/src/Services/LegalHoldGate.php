<?php

/**
 * LegalHoldGate - Heratio ahg-core
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1740 follow-up: ordinary deletion honours legal holds.
 *
 * The one place the core delete paths (description delete, digital object
 * delete, detach, trash purge, the API deletes) ask "may this go?". The answer
 * comes from ahg-records-manage's LegalHoldGuard, which knows that a hold on a
 * file or series holds everything inside it. ahg-core does not depend on that
 * package: without it installed there is no records management and no hold
 * to honour, so the gate lets the delete through.
 */
class LegalHoldGate
{
    private const GUARD = 'AhgRecordsManage\\Services\\LegalHoldGuard';

    /**
     * The hold blocking deletion of the description, or null. With
     * $withContents (deleting the description deletes everything under it)
     * a hold on any record inside it counts too.
     */
    public static function holdOn(int $ioId, bool $withContents = false): ?object
    {
        if ($ioId <= 0 || ! class_exists(self::GUARD)) {
            return null;
        }

        return (self::GUARD)::blockingDeletion($ioId, $withContents);
    }

    /** The hold blocking deletion of a digital object (via the description that owns it). */
    public static function holdOnDigitalObject(int $digitalObjectId): ?object
    {
        $ioId = self::ownerOf($digitalObjectId);

        return $ioId ? self::holdOn($ioId) : null;
    }

    /** Throw when the description (and, with $withContents, anything inside it) is held. */
    public static function assertDeletable(int $ioId, bool $withContents = false): void
    {
        if ($hold = self::holdOn($ioId, $withContents)) {
            throw new RecordUnderLegalHoldException($hold, (int) $ioId);
        }
    }

    public static function assertDigitalObjectDeletable(int $digitalObjectId): void
    {
        $ioId = self::ownerOf($digitalObjectId);
        if ($ioId) {
            self::assertDeletable($ioId);
        }
    }

    /**
     * The description a digital object belongs to: its own object_id, its
     * master's (for a derivative), or the attachment link (attached objects
     * carry no object_id).
     */
    public static function ownerOf(int $digitalObjectId): ?int
    {
        $do = DB::table('digital_object')->where('id', $digitalObjectId)->first(['id', 'object_id', 'parent_id']);
        if (! $do) {
            return null;
        }
        if (! $do->object_id && $do->parent_id) {
            $do = DB::table('digital_object')->where('id', $do->parent_id)->first(['id', 'object_id', 'parent_id']) ?? $do;
        }
        if ($do->object_id) {
            return (int) $do->object_id;
        }
        if (Schema::hasTable(AttachedDigitalObjectService::TABLE)) {
            $io = DB::table(AttachedDigitalObjectService::TABLE)->where('digital_object_id', $do->id)->value('information_object_id');

            return $io ? (int) $io : null;
        }

        return null;
    }
}
