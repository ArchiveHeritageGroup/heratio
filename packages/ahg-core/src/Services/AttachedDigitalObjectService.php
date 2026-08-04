<?php

/**
 * AttachedDigitalObjectService - Heratio
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

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #1447 - attach more than one digital object directly to one archival
 * description, without spawning a child description per image.
 *
 * The existing single primary digital_object (object_id = the IO) is left
 * untouched. Additional objects are ordinary digital_object rows (with their own
 * thumbnail/reference derivatives via parent_id) whose object_id is NULL, linked
 * to the description only through information_object_digital_object. Legacy reads
 * that filter on object_id therefore never see the extras; the show-page gallery
 * and (later phases) exports read them through this service.
 */
class AttachedDigitalObjectService
{
    public const TABLE = 'information_object_digital_object';

    /** Whether the feature's link table is installed. */
    public static function available(): bool
    {
        return Schema::hasTable(self::TABLE);
    }

    /**
     * Upload a file and attach it to the description as an ADDITIONAL object.
     * Reuses DigitalObjectService::upload() (storage + derivative generation),
     * then detaches the new master from the primary slot (object_id = NULL) so it
     * is only reachable via the link table. Returns the new link-row id.
     */
    public function attach(int $ioId, UploadedFile $file, ?string $caption = null, ?string $role = null): int
    {
        // Create the master + derivatives via the shared uploader, then move the
        // whole set out of the primary slot. upload() stamps object_id = $ioId on
        // the master AND its thumbnail/reference derivatives, so both must be
        // nulled - otherwise the derivatives leak into WHERE object_id = IO reads
        // (list thumbnail, EAD/IIIF/RiC exports) and the extra is no longer
        // invisible to the legacy primary path.
        $masterId = DigitalObjectService::upload($ioId, $file);
        DB::table('digital_object')
            ->where(function ($q) use ($masterId) {
                $q->where('id', $masterId)->orWhere('parent_id', $masterId);
            })
            ->update(['object_id' => null]);

        $sort = (int) DB::table(self::TABLE)->where('information_object_id', $ioId)->max('sort_order');

        return (int) DB::table(self::TABLE)->insertGetId([
            'information_object_id' => $ioId,
            'digital_object_id'     => $masterId,
            'sort_order'            => $sort + 1,
            'is_primary'            => 0,
            'caption'               => $caption !== null && $caption !== '' ? mb_substr($caption, 0, 255) : null,
            'role'                  => $role !== null && $role !== '' ? mb_substr($role, 0, 64) : null,
            'created_by'            => auth()->id(),
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
    }

    /**
     * Attached objects for a description, in display order. Each entry carries
     * the link metadata plus the master digital_object and its thumbnail /
     * reference derivatives (mirrors DigitalObjectService::getForObject shape).
     *
     * @return Collection<int,object>
     */
    public function listFor(int $ioId): Collection
    {
        if (! self::available()) {
            return collect();
        }

        $links = DB::table(self::TABLE)
            ->where('information_object_id', $ioId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        if ($links->isEmpty()) {
            return collect();
        }

        $masters = DB::table('digital_object')
            ->whereIn('id', $links->pluck('digital_object_id')->all())
            ->get()
            ->keyBy('id');
        $derivatives = DB::table('digital_object')
            ->whereIn('parent_id', $links->pluck('digital_object_id')->all())
            ->get()
            ->groupBy('parent_id');

        return $links->map(function ($link) use ($masters, $derivatives) {
            $master = $masters->get($link->digital_object_id);
            if (! $master) {
                return null; // link points at a deleted object - skip
            }
            $derivs = $derivatives->get($link->digital_object_id, collect());

            return (object) [
                'link_id'    => $link->id,
                'sort_order' => $link->sort_order,
                'is_primary' => (int) $link->is_primary,
                'caption'    => $link->caption,
                'role'       => $link->role,
                'master'     => $master,
                'reference'  => $derivs->firstWhere('usage_id', DigitalObjectService::USAGE_REFERENCE),
                'thumbnail'  => $derivs->firstWhere('usage_id', DigitalObjectService::USAGE_THUMBNAIL),
            ];
        })->filter()->values();
    }

    /**
     * The attached MASTER digital_object rows for a description, in display
     * order - for exporters (EAD <dao> set, IIIF canvases, RiC instantiations)
     * that already fetch the primary via object_id and want to append the
     * extras. Returns full rows; callers read the columns they need. Empty when
     * the feature is not installed or nothing is attached.
     *
     * @return Collection<int,object>
     */
    public function attachedMasters(int $ioId): Collection
    {
        if (! self::available()) {
            return collect();
        }
        $ids = DB::table(self::TABLE)
            ->where('information_object_id', $ioId)
            ->orderBy('sort_order')->orderBy('id')
            ->pluck('digital_object_id');
        if ($ids->isEmpty()) {
            return collect();
        }
        $order = $ids->values()->flip();
        $rows = DB::table('digital_object')->whereIn('id', $ids->all())->get();

        return $rows->sortBy(fn ($r) => $order[$r->id] ?? PHP_INT_MAX)->values();
    }

    /** Number of objects attached to a description (cheap existence/count check). */
    public function countFor(int $ioId): int
    {
        return self::available()
            ? (int) DB::table(self::TABLE)->where('information_object_id', $ioId)->count()
            : 0;
    }

    /**
     * Detach one attached object: remove the link row and delete the underlying
     * digital object (master + derivatives + files) via DigitalObjectService.
     * No-op-safe if the link is already gone. Returns true when a link was removed.
     */
    public function detach(int $linkId): bool
    {
        if (! self::available()) {
            return false;
        }
        $link = DB::table(self::TABLE)->where('id', $linkId)->first();
        if (! $link) {
            return false;
        }
        DB::table(self::TABLE)->where('id', $linkId)->delete();
        try {
            DigitalObjectService::delete((int) $link->digital_object_id);
        } catch (\Throwable $e) {
            // The link is gone regardless; a failed file cleanup must not throw
            // into the request. Orphaned rows are harmless (object_id is NULL).
        }

        return true;
    }

    /** The description id a link belongs to (for redirect/permission checks). */
    public function ownerIoId(int $linkId): ?int
    {
        if (! self::available()) {
            return null;
        }

        return DB::table(self::TABLE)->where('id', $linkId)->value('information_object_id');
    }
}
