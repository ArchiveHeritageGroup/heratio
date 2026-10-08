<?php

/**
 * StorageFlatMigrationService - carry the flat location fields into the storage
 * location tree (heratio#1528, parity with atom-ahg-plugins#193).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3.0 or later. This
 * file is part of Heratio. See <https://www.gnu.org/licenses/> for details.
 */

namespace AhgStorageManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Port of the AtoM ahgStorageManagePlugin StorageFlatMigrationService, on the
 * same tables.
 *
 * Before the tree existed, where a box was kept was written down in three
 * places, none of which knew about the others:
 *
 *  - physical_object_extended: building, floor, room, aisle, bay, rack, shelf;
 *  - ahg_strongroom, through ahg_physical_object_storage: one named room;
 *  - physical_object_i18n.location: a line of free text.
 *
 * This reads them in that order of trust and, for each physical object, finds or
 * creates the chain of locations it names and places the object in the innermost
 * one. The placement goes through StorageMovementService, so it is a first
 * placement in the movement log like any other, with a note saying where it came
 * from and a named actor.
 *
 * Nothing is deleted and nothing is overwritten. The flat fields stay exactly as
 * they were, an object that already has a place in the tree is left alone, and a
 * location that already exists is reused (same parent, type and name, without
 * regard to case), so the whole thing can be run again after more boxes are
 * catalogued and only the new ones move.
 *
 * Free text is not guessed at. "Delmas" might be a town, a building or a shelf
 * mark, and a tree built from guesses has to be taken apart by hand. An object
 * with nothing but free text is reported and left, unless the operator says what
 * its free text means with the free_text_type option.
 *
 * run(false) changes nothing and returns what run(true) would do.
 */
class StorageFlatMigrationService
{
    /** physical_object_extended columns, outermost first, with the type each becomes. */
    public const LEVELS = StoragePlacementService::LEVELS;

    public const NOTE = 'Migrated from the flat location fields';

    public const ACTOR = 'ahg:storage-migrate-flat-locations';

    private StorageLocationService $locations;

    private StorageMovementService $movements;

    /** @var array<string, int> "parent|type|name" => location id; negative while only planned */
    private array $known = [];

    private int $planned = 0;

    private bool $apply = false;

    private array $report = [];

    public function __construct(private string $culture = 'en')
    {
        $this->movements = new StorageMovementService($culture);
        $this->locations = new StorageLocationService(null, $this->movements);
    }

    /**
     * @param  bool  $apply  false: report only, write nothing
     * @param  array  $options  free_text_type: a location type to file free-text-only
     *                          objects under, as a root location named by the text
     * @return array locations_created, locations_reused, placed, skipped, each a
     *               list of rows a person can read
     */
    public function run(bool $apply = false, array $options = []): array
    {
        $freeTextType = $options['free_text_type'] ?? null;

        if ($freeTextType !== null && ! array_key_exists($freeTextType, $this->locations->types())) {
            throw new RuntimeException('Unknown location type for free text: '.$freeTextType);
        }

        $this->apply = $apply;
        $this->known = [];
        $this->planned = 0;
        $this->report = ['locations_created' => [], 'locations_reused' => [], 'placed' => [], 'skipped' => []];

        $work = function () use ($freeTextType) {
            $strongrooms = $this->strongrooms();

            // Every strongroom becomes a room, whether or not anything is in it:
            // an empty room is still a room the archive has.
            foreach ($strongrooms['rooms'] as $room) {
                $this->resolve([['room', $room['name']]], $room);
            }

            foreach ($this->objects() as $object) {
                $id = (int) $object['id'];
                $name = $object['name'] ?: 'Object '.$id;

                if ($this->movements->currentLocationOf($id) !== null) {
                    $this->report['skipped'][] = ['object_id' => $id, 'name' => $name, 'reason' => 'already has a place in the tree'];

                    continue;
                }

                $path = [];
                $source = null;
                $room = $strongrooms['of'][$id] ?? null;

                foreach (self::LEVELS as $level) {
                    $value = trim((string) ($object[$level] ?? ''));
                    if ($value !== '') {
                        $path[] = [$level, $value];
                    }
                }

                $freeText = trim((string) ($object['location'] ?? ''));

                if ($path) {
                    $source = 'structured fields';
                    if ($room !== null) {
                        $source .= ' (also assigned to strongroom "'.$room.'", not used)';
                    }
                } elseif ($room !== null) {
                    $path = [['room', $room]];
                    $source = 'strongroom';
                } elseif ($freeText !== '') {
                    if ($freeTextType === null) {
                        $this->report['skipped'][] = ['object_id' => $id, 'name' => $name, 'reason' => 'free text only: "'.$freeText.'"'];

                        continue;
                    }
                    $path = [[$freeTextType, $freeText]];
                    $source = 'free text';
                } else {
                    $this->report['skipped'][] = ['object_id' => $id, 'name' => $name, 'reason' => 'no location recorded'];

                    continue;
                }

                $locationId = $this->resolve($path);

                if ($this->apply) {
                    $this->movements->moveObject($id, $locationId, [
                        'note' => self::NOTE.' ('.$source.')',
                        'user_id' => null,
                        'username' => self::ACTOR,
                    ]);
                }

                $this->report['placed'][] = [
                    'object_id' => $id,
                    'name' => $name,
                    'path' => implode(' > ', array_map(static fn ($step) => StorageLocationService::placeName($step[0], $step[1]), $path)),
                    'source' => $source,
                ];
            }

            return $this->report;
        };

        // One transaction: a migration that stopped half way would leave some
        // boxes in the tree and some not, with nothing to say which.
        return $apply ? DB::transaction($work) : $work();
    }

    /**
     * The location at the end of a path, creating what is missing on the way.
     *
     * @param  array  $path  [[type, name], ...] outermost first
     * @param  array|null  $extra  description and capacity for the last location, when it is created
     */
    private function resolve(array $path, ?array $extra = null): int
    {
        $parentId = null;
        $trail = [];
        $last = count($path) - 1;

        foreach ($path as $index => [$type, $name]) {
            $name = StorageLocationService::placeName($type, $name);
            $trail[] = $name;
            $key = ($parentId ?? 0).'|'.$type.'|'.mb_strtolower($name);

            if (! isset($this->known[$key])) {
                // A planned parent has no row yet, so nothing can exist under it.
                $existing = ($parentId === null || $parentId > 0)
                    ? $this->locations->findChild($parentId, $type, $name)
                    : null;

                $label = implode(' > ', $trail).' ('.$type.')';

                if ($existing !== null) {
                    $this->known[$key] = $existing;
                    $this->report['locations_reused'][] = $label;
                } elseif ($this->apply) {
                    $data = ['name' => $name, 'location_type' => $type, 'parent_id' => $parentId];
                    if ($index === $last && $extra) {
                        $data += array_intersect_key($extra, array_flip(['description', 'notes', 'capacity_value', 'capacity_unit']));
                    }
                    $this->known[$key] = $this->locations->create($data);
                    $this->report['locations_created'][] = $label;
                } else {
                    $this->known[$key] = --$this->planned;
                    $this->report['locations_created'][] = $label;
                }
            }

            $parentId = $this->known[$key];
        }

        return (int) $parentId;
    }

    /** Every physical object with whatever the flat fields say about it. */
    private function objects(): array
    {
        $query = DB::table('physical_object as p')
            ->leftJoin('physical_object_i18n as i', function ($join) {
                $join->on('i.id', '=', 'p.id')->where('i.culture', '=', $this->culture);
            })
            ->orderBy('p.id')
            ->select('p.id', 'i.name', 'i.location');

        if (Schema::hasTable('physical_object_extended')) {
            $query->leftJoin('physical_object_extended as e', 'e.physical_object_id', '=', 'p.id');
            foreach (self::LEVELS as $level) {
                $query->addSelect('e.'.$level);
            }
        }

        return array_map(static fn ($row) => (array) $row, $query->get()->all());
    }

    /**
     * @return array rooms: each strongroom as a location to be; of: object id => strongroom name
     */
    private function strongrooms(): array
    {
        if (! Schema::hasTable('ahg_strongroom')) {
            return ['rooms' => [], 'of' => []];
        }

        $rooms = [];
        $names = [];

        foreach (DB::table('ahg_strongroom')->orderBy('id')->get() as $room) {
            $name = trim((string) $room->name);
            if ($name === '') {
                continue;
            }

            $names[(int) $room->id] = $name;
            $rooms[] = [
                'name' => $name,
                'description' => $room->location_description,
                'notes' => $room->notes,
                'capacity_value' => $room->capacity_value,
                'capacity_unit' => $room->capacity_unit,
            ];
        }

        $of = [];
        if (Schema::hasTable('ahg_physical_object_storage')) {
            foreach (DB::table('ahg_physical_object_storage')->get() as $row) {
                if (isset($names[(int) $row->strongroom_id])) {
                    $of[(int) $row->physical_object_id] = $names[(int) $row->strongroom_id];
                }
            }
        }

        return ['rooms' => $rooms, 'of' => $of];
    }
}
