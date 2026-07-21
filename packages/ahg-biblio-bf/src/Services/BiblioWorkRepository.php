<?php

/**
 * BiblioWorkRepository - reads the live catalogue for BIBFRAME serialisation.
 *
 * BIBFRAME needs a Work / Instance / Item / Agent view of the catalogue. Heratio
 * stores bibliographic records in `library_item` (title on information_object_i18n,
 * contributors on library_item_creator, physical copies on library_copy), so this
 * class projects those tables onto the BIBFRAME shape:
 *
 *   Work     <- the FRBR work_key cluster (library_item.work_key), or the single
 *               item when it has no key yet
 *   Instance <- each library_item in that cluster (edition / format / publication)
 *   Item     <- each library_copy of an instance (a physical copy)
 *   Agent    <- library_item_creator rows across the cluster, de-duplicated
 *
 * A "work id" in the BIBFRAME routes is therefore a library_item.id - the
 * representative item of its cluster.
 *
 * This replaces the library_biblio_work / _instance / _item / _agent / _work_agent
 * scaffold, which was referenced throughout this package but which no migration
 * ever created, so every BIBFRAME export silently produced nothing (#1414). The
 * catalogue standardises on library_item (#1412).
 *
 * Copyright (C) 2026 Johan Pieterse
 * The Archive Heritage Group (Pty) Ltd
 * Email: johan@theahg.co.za
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

namespace AhgBiblioBf\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BiblioWorkRepository
{
    /**
     * MARC relator codes for the roles stored on library_item_creator.
     * bfAgentBlock()/bfWorkBlock() emit these into LoC relator URIs.
     */
    protected const ROLE_RELATORS = [
        'author'       => 'aut',
        'creator'      => 'aut',
        'editor'       => 'edt',
        'illustrator'  => 'ill',
        'translator'   => 'trl',
        'contributor'  => 'ctb',
        'compiler'     => 'com',
        'photographer' => 'pht',
    ];

    /**
     * Fetch a work with its instances and agents.
     *
     * @param  int $workId  library_item.id of the representative item
     * @return array{work:object|null, instances:Collection, agents:Collection}
     */
    public function find(int $workId): array
    {
        $empty = ['work' => null, 'instances' => collect(), 'agents' => collect()];

        if (! $this->catalogueAvailable()) {
            return $empty;
        }

        $representative = $this->itemRow($workId);
        if (! $representative) {
            return $empty;
        }

        $clusterIds = $this->clusterIds($representative);
        $instances  = $this->instancesFor($clusterIds);
        $agents     = $this->agentsFor($clusterIds);

        $work = (object) [
            'id'         => (int) $representative->id,
            'title'      => $representative->title ?: 'Untitled',
            'author'     => $agents->first()->name ?? '',
            'language'   => $representative->language ?: 'en',
            'work_key'   => $representative->work_key,
            // xsd:date in the RDF, so date only.
            'created_at' => $this->toDate($representative->created_at),
        ];

        return ['work' => $work, 'instances' => $instances, 'agents' => $agents];
    }

    /**
     * Physical copies of an instance, as BIBFRAME Items.
     *
     * @param  int $instanceId  library_item.id
     */
    public function itemsForInstance(int $instanceId): Collection
    {
        if (! Schema::hasTable('library_copy')) {
            return collect();
        }

        return DB::table('library_copy')
            ->where('library_item_id', $instanceId)
            ->orderBy('copy_number')
            ->get(['id', 'barcode', 'copy_number', 'shelf_location', 'status'])
            ->map(fn ($copy) => (object) [
                'id'       => (int) $copy->id,
                'barcode'  => $copy->barcode,
                'copy'     => $copy->copy_number,
                'location' => $copy->shelf_location,
                'status'   => $copy->status,
            ]);
    }

    /**
     * Representative item of each work cluster, newest first.
     *
     * One row per work, so the export list offers Works rather than every
     * edition of the same work.
     */
    public function listWorks(int $limit = 200): Collection
    {
        if (! $this->catalogueAvailable()) {
            return collect();
        }

        // Grouping on the work_key (falling back to the row's own id for
        // unclustered items) keeps this ONLY_FULL_GROUP_BY-safe: the select
        // list is aggregates alone. Titles are hydrated in a second pass.
        $representatives = DB::table('library_item')
            ->selectRaw('MIN(id) AS id, MAX(created_at) AS created_at')
            ->groupByRaw("COALESCE(work_key, CONCAT('id:', id))")
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        if ($representatives->isEmpty()) {
            return collect();
        }

        $ids   = $representatives->pluck('id')->all();
        $rows  = $this->itemRows($ids)->keyBy('id');
        $names = $this->primaryCreatorNames($ids);

        return $representatives
            ->map(function ($rep) use ($rows, $names) {
                $row = $rows->get($rep->id);
                if (! $row) {
                    return null;
                }

                return (object) [
                    'id'         => (int) $row->id,
                    'title'      => $row->title ?: 'Untitled',
                    'author'     => $names[$row->id] ?? null,
                    'created_at' => $row->created_at,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Number of distinct works (work_key clusters) in the catalogue.
     */
    public function countWorks(): int
    {
        if (! $this->catalogueAvailable()) {
            return 0;
        }

        return (int) DB::table('library_item')
            ->selectRaw("COUNT(DISTINCT COALESCE(work_key, CONCAT('id:', id))) AS aggregate")
            ->value('aggregate');
    }

    /**
     * Number of instances (catalogue records) - every library_item is one.
     */
    public function countInstances(): int
    {
        return $this->catalogueAvailable() ? (int) DB::table('library_item')->count() : 0;
    }

    /**
     * Number of items (physical copies).
     */
    public function countItems(): int
    {
        return Schema::hasTable('library_copy') ? (int) DB::table('library_copy')->count() : 0;
    }

    /**
     * All contributors in the catalogue, as BIBFRAME Agents.
     *
     * De-duplicated by name so the agent index lists each person or body once
     * rather than once per catalogue record they contributed to.
     */
    public function listAgents(): Collection
    {
        if (! Schema::hasTable('library_item_creator')) {
            return collect();
        }

        return DB::table('library_item_creator')
            ->selectRaw('MIN(id) AS id, name, MIN(role) AS role, MIN(created_at) AS created_at')
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->groupBy('name')
            ->orderBy('name')
            ->get()
            ->map(fn ($creator) => $this->toAgent($creator));
    }

    // ─── Writes (BIBFRAME import) ──────────────────────────────────────────────

    /**
     * Whether records can be created - ahg-library owns catalogue writes.
     */
    public function canWrite(): bool
    {
        return class_exists(\AhgLibrary\Services\LibraryService::class) && $this->catalogueAvailable();
    }

    /**
     * Find a catalogue record by exact title (case-insensitive).
     *
     * @return int|null library_item.id
     */
    public function findIdByTitle(string $title): ?int
    {
        $title = trim($title);
        if ($title === '' || ! $this->catalogueAvailable()) {
            return null;
        }

        $id = DB::table('library_item')
            ->join('information_object_i18n', 'information_object_i18n.id', '=', 'library_item.information_object_id')
            ->whereRaw('LOWER(information_object_i18n.title) = ?', [mb_strtolower($title)])
            ->orderBy('library_item.id')
            ->value('library_item.id');

        return $id ? (int) $id : null;
    }

    /**
     * Create a catalogue record from BIBFRAME-derived fields.
     *
     * Goes through LibraryService so the record gets its object /
     * information_object / i18n / slug rows and sector identifier like any
     * other catalogue entry.
     *
     * @param  array $data title, creator, language, publisher, pub_place, pub_date, isbn
     * @return int|null    library_item.id
     */
    public function createFromBibframe(array $data): ?int
    {
        return $this->createFromImport($data);
    }

    /**
     * Create a catalogue record from serialisation-derived fields.
     *
     * Shared by the BIBFRAME and FRBR importers (#1414, #1417), which both
     * reduce to the same handful of bibliographic fields.
     *
     * @param  array $data title, creator, language, publisher, pub_place, pub_date, isbn
     * @return int|null    library_item.id
     */
    public function createFromImport(array $data): ?int
    {
        if (! $this->canWrite()) {
            return null;
        }

        $creators = [];
        if (! empty($data['creator'])) {
            $creators[] = ['name' => $data['creator'], 'role' => 'author'];
        }

        $libraryService = new \AhgLibrary\Services\LibraryService(app()->getLocale());

        $objectId = $libraryService->create([
            'title'             => $data['title'] ?? 'Untitled',
            'creators'          => $creators,
            'language'          => $data['language'] ?? null,
            'publisher'         => $data['publisher'] ?? null,
            'publication_place' => $data['pub_place'] ?? null,
            'publication_date'  => $data['pub_date'] ?? null,
            'isbn'              => $data['isbn'] ?? null,
        ]);

        $id = DB::table('library_item')->where('information_object_id', $objectId)->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * Apply BIBFRAME Instance fields to an existing catalogue record.
     * Only fills columns that are currently empty, so an import never
     * overwrites what a cataloguer already recorded.
     */
    public function applyInstanceFields(int $libraryItemId, array $data): void
    {
        $row = DB::table('library_item')->where('id', $libraryItemId)->first();
        if (! $row) {
            return;
        }

        $map = [
            'publisher'         => $data['publisher'] ?? null,
            'publication_place' => $data['pub_place'] ?? null,
            'publication_date'  => $data['pub_date'] ?? null,
            'isbn'              => $data['isbn'] ?? null,
        ];

        $update = [];
        foreach ($map as $column => $value) {
            if (! empty($value) && empty($row->{$column})) {
                $update[$column] = $value;
            }
        }

        if (! empty($update)) {
            $update['updated_at'] = now();
            DB::table('library_item')->where('id', $libraryItemId)->update($update);
        }
    }

    /**
     * Attach a physical copy to a catalogue record.
     *
     * @return int|null library_copy.id, or null when skipped
     */
    public function addCopy(int $libraryItemId, ?string $barcode): ?int
    {
        if (! Schema::hasTable('library_copy')) {
            return null;
        }

        // library_copy.barcode is UNIQUE - a repeated import must not collide.
        if ($barcode !== null && $barcode !== '') {
            $existing = DB::table('library_copy')->where('barcode', $barcode)->value('id');
            if ($existing) {
                return (int) $existing;
            }
        }

        $nextCopy = (int) DB::table('library_copy')
            ->where('library_item_id', $libraryItemId)
            ->max('copy_number');

        return (int) DB::table('library_copy')->insertGetId([
            'library_item_id' => $libraryItemId,
            'copy_number'     => $nextCopy + 1,
            'barcode'         => $barcode ?: null,
            'status'          => 'available',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    // ─── Internals ─────────────────────────────────────────────────────────────

    protected function catalogueAvailable(): bool
    {
        return Schema::hasTable('library_item') && Schema::hasTable('information_object_i18n');
    }

    /**
     * library_item ids sharing the representative's work_key.
     *
     * @return int[]
     */
    protected function clusterIds(object $representative): array
    {
        if (empty($representative->work_key)) {
            return [(int) $representative->id];
        }

        return DB::table('library_item')
            ->where('work_key', $representative->work_key)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function itemRow(int $id): ?object
    {
        return $this->itemRows([$id])->first();
    }

    /**
     * @param int[] $ids
     */
    protected function itemRows(array $ids): Collection
    {
        if (empty($ids)) {
            return collect();
        }

        $culture = app()->getLocale();

        return DB::table('library_item')
            ->leftJoin('information_object_i18n', function ($join) use ($culture) {
                $join->on('information_object_i18n.id', '=', 'library_item.information_object_id')
                     ->where('information_object_i18n.culture', '=', $culture);
            })
            ->whereIn('library_item.id', $ids)
            ->orderBy('library_item.id')
            ->select([
                'library_item.id',
                'library_item.information_object_id',
                'library_item.work_key',
                'library_item.subtitle',
                'library_item.isbn',
                'library_item.issn',
                'library_item.publisher',
                'library_item.publication_place',
                'library_item.publication_date',
                'library_item.language',
                'library_item.edition',
                'library_item.material_type',
                'library_item.carrier_type',
                'library_item.created_at',
            ])
            // A record catalogued only in another language would otherwise
            // export as "Untitled", so fall back to any title it does have.
            ->selectRaw(
                "COALESCE(NULLIF(information_object_i18n.title, ''), ("
                . "SELECT NULLIF(fallback.title, '') FROM information_object_i18n AS fallback"
                . " WHERE fallback.id = library_item.information_object_id"
                . " AND fallback.title IS NOT NULL AND fallback.title <> ''"
                . " ORDER BY fallback.culture LIMIT 1"
                . ")) AS title"
            )
            ->get();
    }

    /**
     * @param int[] $clusterIds
     */
    protected function instancesFor(array $clusterIds): Collection
    {
        return $this->itemRows($clusterIds)->map(fn ($row) => (object) [
            'id'        => (int) $row->id,
            'title'     => $row->title ?: 'Untitled',
            'publisher' => $row->publisher ?? '',
            'pub_place' => $row->publication_place ?? '',
            'pub_date'  => $row->publication_date ?? '',
            'isbn'      => $row->isbn ?? '',
            'issn'      => $row->issn ?? '',
            'edition'   => $row->edition ?? '',
            'carrier'   => $this->carrierCode($row),
        ]);
    }

    /**
     * @param int[] $clusterIds
     */
    protected function agentsFor(array $clusterIds): Collection
    {
        if (empty($clusterIds) || ! Schema::hasTable('library_item_creator')) {
            return collect();
        }

        return DB::table('library_item_creator')
            ->whereIn('library_item_id', $clusterIds)
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'role', 'actor_id', 'authority_uri', 'created_at'])
            ->unique(fn ($creator) => mb_strtolower(trim($creator->name)))
            ->values()
            ->map(fn ($creator) => $this->toAgent($creator));
    }

    /**
     * First-listed contributor name per library_item id.
     *
     * @param  int[] $ids
     * @return array<int, string>
     */
    protected function primaryCreatorNames(array $ids): array
    {
        if (empty($ids) || ! Schema::hasTable('library_item_creator')) {
            return [];
        }

        $names = [];

        foreach (
            DB::table('library_item_creator')
                ->whereIn('library_item_id', $ids)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['library_item_id', 'name']) as $creator
        ) {
            $itemId = (int) $creator->library_item_id;
            if (! isset($names[$itemId]) && trim((string) $creator->name) !== '') {
                $names[$itemId] = $creator->name;
            }
        }

        return $names;
    }

    protected function toAgent(object $creator): object
    {
        return (object) [
            'id'            => (int) $creator->id,
            'name'          => $creator->name,
            'type'          => self::ROLE_RELATORS[strtolower((string) ($creator->role ?? ''))] ?? 'ctb',
            'role'          => $creator->role ?? null,
            'actor_id'      => $creator->actor_id ?? null,
            'authority_uri' => $creator->authority_uri ?? null,
            'created_at'    => $this->toCarbon($creator->created_at ?? null),
        ];
    }

    /**
     * RDA carrier code for a catalogue row, from the explicit carrier_type when
     * the cataloguer set one, else inferred from the material type.
     */
    protected function carrierCode(object $row): string
    {
        if (! empty($row->carrier_type)) {
            return (string) $row->carrier_type;
        }

        return match ((string) ($row->material_type ?? '')) {
            'electronic'  => 'cr',   // online resource
            'audiovisual' => 'vd',   // videodisc
            'map'         => 'nb',   // sheet
            'manuscript'  => 'nc',   // volume
            default       => 'nc',   // volume
        };
    }

    protected function toDate(mixed $value): string
    {
        $carbon = $this->toCarbon($value);

        return $carbon ? $carbon->toDateString() : '';
    }

    protected function toCarbon(mixed $value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
