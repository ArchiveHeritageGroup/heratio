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

        $masterId = (int) $link->digital_object_id;

        // Remove the on-disk files ourselves FIRST. DigitalObjectService::delete()
        // resolves file paths via object_id, which attach() deliberately nulled -
        // so it cannot find an attached object's files and would leave them
        // orphaned under uploads_path/r/<ioId>/. We use the shared resolver
        // (the master/derivative rows still carry the web `path` field, so it
        // resolves correctly even with object_id nulled), then let delete() do
        // the id/parent_id-keyed DB-row cleanup. NB: the /r/<ioId>/ directory is
        // SHARED with this record's primary object - we only unlink the specific
        // attached files, never the directory (#1455).
        $rows = DB::table('digital_object')
            ->where('id', $masterId)->orWhere('parent_id', $masterId)
            ->get(['id', 'name', 'path']);
        foreach ($rows as $r) {
            $onDisk = DigitalObjectService::resolveDiskPath($r);
            if ($onDisk && @is_file($onDisk)) {
                @unlink($onDisk);
            }
        }

        DB::table(self::TABLE)->where('id', $linkId)->delete();
        try {
            DigitalObjectService::delete($masterId);
        } catch (\Throwable $e) {
            // DB-row cleanup is best-effort; the link is gone and the files were
            // removed above, so a failure here must not throw into the request.
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
    /**
     * The description's PRIMARY master digital object, or null.
     *
     * The primary is the row carrying object_id = the description with no
     * parent - the legacy master. Derivatives carry the same object_id but a
     * parent_id, so the parent_id test is what separates them; ordering by
     * usage_id = master first mirrors DigitalObjectService::getForObject(),
     * which tolerates legacy uploads stored with a thumbnail usage id.
     */
    public function primaryMaster(int $ioId): ?object
    {
        return DB::table('digital_object')
            ->where('object_id', $ioId)
            ->whereNull('parent_id')
            ->orderByRaw('usage_id = ? DESC', [DigitalObjectService::USAGE_MASTER])
            ->orderBy('id')
            ->first();
    }

    /**
     * Promote an attached object to be the description's primary.
     *
     * #1489. The primary is decided by digital_object.object_id and a great deal
     * of legacy code reads it that way, so promoting MOVES object_id rather than
     * setting a flag beside it. A second notion of "primary" living in the link
     * table would drift from the first the moment anything wrote only one of
     * them - the same failure shape as #1478. For that reason the link table
     * stays what it has always been: the EXTRAS, never the primary. is_primary
     * is forced to 0 on every row here so a stale flag cannot start disagreeing
     * with object_id.
     *
     * The swap is symmetric: the outgoing primary is demoted into the link table
     * at the slot the incoming one vacated, so the gallery order does not jump.
     * Both master AND derivative rows move together, because a derivative left
     * behind with the old object_id would leak into every `WHERE object_id = IO`
     * read - the list thumbnail and the EAD/IIIF/RiC exports.
     *
     * @return array{ok:bool, error?:string}
     */
    public function setPrimary(int $linkId): array
    {
        if (! self::available()) {
            return ['ok' => false, 'error' => 'Multiple attachments are not available on this installation.'];
        }

        $link = DB::table(self::TABLE)->where('id', $linkId)->first();
        if (! $link) {
            return ['ok' => false, 'error' => 'That attachment no longer exists.'];
        }

        $ioId = (int) $link->information_object_id;
        $newMasterId = (int) $link->digital_object_id;

        if (! DB::table('digital_object')->where('id', $newMasterId)->exists()) {
            return ['ok' => false, 'error' => 'That attachment points at a digital object that has been deleted.'];
        }

        DB::transaction(function () use ($ioId, $newMasterId, $link) {
            $old = $this->primaryMaster($ioId);

            if ($old && (int) $old->id !== $newMasterId) {
                // Demote: clear object_id from the outgoing master and everything
                // hanging off it, then record it as an ordinary attachment in the
                // slot the incoming object is about to leave.
                $this->moveObjectId((int) $old->id, null);
                DB::table(self::TABLE)->insert([
                    'information_object_id' => $ioId,
                    'digital_object_id' => (int) $old->id,
                    'sort_order' => (int) $link->sort_order,
                    'is_primary' => 0,
                    'caption' => null,
                    'role' => null,
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Promote, then drop the link row: the primary is never in this table.
            $this->moveObjectId($newMasterId, $ioId);
            DB::table(self::TABLE)->where('id', $link->id)->delete();

            // Belt and braces - the column is vestigial and must stay that way.
            DB::table(self::TABLE)->where('information_object_id', $ioId)
                ->where('is_primary', '!=', 0)->update(['is_primary' => 0]);
        });

        return ['ok' => true];
    }

    /** Set object_id on a master and every derivative hanging off it. */
    private function moveObjectId(int $masterId, ?int $objectId): void
    {
        DB::table('digital_object')
            ->where(function ($q) use ($masterId) {
                $q->where('id', $masterId)->orWhere('parent_id', $masterId);
            })
            ->update(['object_id' => $objectId]);
    }

    /**
     * Reorder a description's attachments. #1489.
     *
     * Takes the link ids in the order the curator wants them. Ids that do not
     * belong to this description are ignored rather than trusted - the order
     * arrives from the browser - and any attachment the payload omits keeps a
     * stable position AFTER the ones it names, so a partial or stale list can
     * never silently drop a row out of the gallery.
     *
     * @param  array<int,int|string>  $linkIds
     * @return int number of rows repositioned
     */
    public function reorder(int $ioId, array $linkIds): int
    {
        if (! self::available()) {
            return 0;
        }

        $owned = DB::table(self::TABLE)->where('information_object_id', $ioId)
            ->orderBy('sort_order')->orderBy('id')->pluck('id')->all();
        if (! $owned) {
            return 0;
        }

        $wanted = [];
        foreach ($linkIds as $id) {
            $id = (int) $id;
            if (in_array($id, $owned, true) && ! in_array($id, $wanted, true)) {
                $wanted[] = $id;
            }
        }
        foreach ($owned as $id) {          // anything not named keeps its relative place, at the end
            if (! in_array($id, $wanted, true)) {
                $wanted[] = $id;
            }
        }

        $n = 0;
        DB::transaction(function () use ($wanted, $ioId, &$n) {
            foreach ($wanted as $i => $id) {
                $n += DB::table(self::TABLE)->where('id', $id)
                    ->where('information_object_id', $ioId)
                    ->update(['sort_order' => $i + 1, 'updated_at' => now()]);
            }
        });

        return $n;
    }
}
