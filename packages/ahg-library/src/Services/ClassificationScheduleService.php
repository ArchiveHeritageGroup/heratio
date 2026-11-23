<?php

namespace AhgLibrary\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Classification schedules (#1475 item 6): schedules and their hierarchy, CSV
 * loading, validation of a keyed number and the derived shelf-order key.
 */
class ClassificationScheduleService
{
    private const SCHEDULE = 'library_classification_schedule';
    private const ENTRY = 'library_classification_entry';
    private const SHELF = 'library_item_shelf_key';
    private const MAX_IMPORT_ERRORS = 50;

    public function schedules(): Collection
    {
        return DB::table(self::SCHEDULE.' as s')
            ->leftJoin(self::ENTRY.' as e', 'e.schedule_id', '=', 's.id')
            ->groupBy('s.id', 's.scheme', 's.name', 's.edition', 's.description', 's.validation_mode', 's.is_active', 's.created_at', 's.updated_at')
            ->orderBy('s.scheme')->orderBy('s.name')
            ->select('s.*', DB::raw('COUNT(e.id) as entry_count'))
            ->get();
    }

    public function schedule(int $id): ?object
    {
        return DB::table(self::SCHEDULE)->where('id', $id)->first();
    }

    public function activeSchedule(?string $scheme): ?object
    {
        $scheme = ClassificationNotation::canonicalScheme($scheme);

        return $scheme === null ? null
            : DB::table(self::SCHEDULE)->where('scheme', $scheme)->where('is_active', 1)->first();
    }

    public function saveSchedule(array $data, ?int $id = null): int
    {
        $row = [
            'scheme' => ClassificationNotation::canonicalScheme($data['scheme']),
            'name' => $data['name'],
            'edition' => $data['edition'] ?? null,
            'description' => $data['description'] ?? null,
            'validation_mode' => $data['validation_mode'],
            'is_active' => ! empty($data['is_active']) ? 1 : 0,
            'updated_at' => now(),
        ];

        return DB::transaction(function () use ($row, $id) {
            if ($id) {
                DB::table(self::SCHEDULE)->where('id', $id)->update($row);
            } else {
                $id = DB::table(self::SCHEDULE)->insertGetId($row + ['created_at' => now()]);
            }
            if ($row['is_active']) { // One schedule answers for a scheme at a time.
                DB::table(self::SCHEDULE)->where('scheme', $row['scheme'])->where('id', '!=', $id)->update(['is_active' => 0]);
            }

            return (int) $id;
        });
    }

    public function deleteSchedule(int $id): void
    {
        DB::transaction(function () use ($id) {
            $entries = DB::table(self::ENTRY)->where('schedule_id', $id)->select('id');
            DB::table(self::SHELF)->whereIn('schedule_entry_id', $entries)->update(['schedule_entry_id' => null]);
            DB::table(self::ENTRY)->where('schedule_id', $id)->delete();
            DB::table(self::SCHEDULE)->where('id', $id)->delete();
        });
    }

    public function children(int $scheduleId, ?int $parentId, int $limit = 500): Collection
    {
        return DB::table(self::ENTRY.' as e')
            ->where('e.schedule_id', $scheduleId)
            ->when($parentId, fn ($q) => $q->where('e.parent_id', $parentId), fn ($q) => $q->whereNull('e.parent_id'))
            ->orderBy('e.sort_key')->limit($limit)
            ->select('e.*', DB::raw('(SELECT COUNT(*) FROM '.self::ENTRY.' c WHERE c.parent_id = e.id) as child_count'))
            ->get();
    }

    public function search(int $scheduleId, string $q, int $limit = 100): Collection
    {
        $like = '%'.addcslashes($q, '%_\\').'%';

        return DB::table(self::ENTRY.' as e')
            ->where('e.schedule_id', $scheduleId)
            // notation is case-sensitive (utf8mb4_bin); a search should not be.
            ->where(fn ($w) => $w->whereRaw('LOWER(e.notation) LIKE LOWER(?)', [$like])->orWhere('e.caption', 'like', $like))
            ->orderBy('e.sort_key')->limit($limit)
            ->select('e.*', DB::raw('(SELECT COUNT(*) FROM '.self::ENTRY.' c WHERE c.parent_id = e.id) as child_count'))
            ->get();
    }

    public function entry(int $id): ?object
    {
        return DB::table(self::ENTRY)->where('id', $id)->first();
    }

    /** Root-first chain of entries ending at the given one. */
    public function path(object $entry): array
    {
        $path = [$entry];
        $seen = [$entry->id => true];
        while ($path[0]->parent_id && ! isset($seen[$path[0]->parent_id])) {
            $parent = $this->entry((int) $path[0]->parent_id);
            if (! $parent) {
                break;
            }
            $seen[$parent->id] = true;
            array_unshift($path, $parent);
        }

        return $path;
    }

    /** @throws RuntimeException when the parent is invalid or would form a loop */
    public function saveEntry(int $scheduleId, array $data, ?int $id = null): int
    {
        $schedule = $this->schedule($scheduleId) ?? throw new RuntimeException(__('Schedule not found.'));
        $notation = ClassificationNotation::normalise($schedule->scheme, $data['notation']);
        $end = trim((string) ($data['notation_end'] ?? ''));
        $end = $end === '' ? null : ClassificationNotation::normalise($schedule->scheme, $end);

        $duplicate = DB::table(self::ENTRY)->where('schedule_id', $scheduleId)->where('notation', $notation)
            ->when($id, fn ($q) => $q->where('id', '!=', $id))->exists();
        if ($duplicate) {
            throw new RuntimeException(__('That notation is already in this schedule.'));
        }

        $parentId = ! empty($data['parent_id']) ? (int) $data['parent_id'] : null;
        if ($parentId) {
            $parent = $this->entry($parentId);
            if (! $parent || (int) $parent->schedule_id !== $scheduleId) {
                throw new RuntimeException(__('The broader entry must be in the same schedule.'));
            }
        } else {
            $parentId = $this->nearestBroaderId($schedule->scheme, $scheduleId, $notation, $id);
        }

        $row = [
            'schedule_id' => $scheduleId,
            'parent_id' => $parentId,
            'notation' => $notation,
            'notation_end' => $end,
            'caption' => $data['caption'],
            'note' => $data['note'] ?? null,
            'sort_key' => ClassificationNotation::sortKey($schedule->scheme, $notation),
            'sort_key_end' => $end === null ? null : ClassificationNotation::sortKey($schedule->scheme, $end),
            'updated_at' => now(),
        ];

        $notationChanged = ! $id || $this->entry($id)?->notation !== $notation;

        return DB::transaction(function () use ($row, $id, $scheduleId, $schedule, $notation, $notationChanged) {
            if ($id) {
                DB::table(self::ENTRY)->where('id', $id)->where('schedule_id', $scheduleId)->update($row);
            } else {
                $id = DB::table(self::ENTRY)->insertGetId($row + ['created_at' => now()]);
            }
            if ($notationChanged) {
                $this->adoptNarrower($schedule->scheme, $scheduleId, [$notation]);
            }
            $this->rebuildDepths($scheduleId);

            return (int) $id;
        });
    }

    /** @throws RuntimeException while the entry still has narrower entries */
    public function deleteEntry(int $id): void
    {
        if (DB::table(self::ENTRY)->where('parent_id', $id)->exists()) {
            throw new RuntimeException(__('Remove or move the narrower entries first.'));
        }
        DB::transaction(function () use ($id) {
            DB::table(self::SHELF)->where('schedule_entry_id', $id)->update(['schedule_entry_id' => null]);
            DB::table(self::ENTRY)->where('id', $id)->delete();
        });
    }

    /**
     * Load a CSV (notation, caption, optional notation_end, parent_notation, note).
     * All or nothing: any bad row means nothing is written. Only columns in the
     * header are updated on existing entries, and an existing entry keeps its
     * broader entry unless its row names a parent_notation.
     *
     * @return array{created:int, updated:int, errors:string[]}
     */
    public function importCsv(int $scheduleId, string $path): array
    {
        $schedule = $this->schedule($scheduleId) ?? throw new RuntimeException(__('Schedule not found.'));
        $scheme = $schedule->scheme;
        $fh = fopen($path, 'r');
        if (! $fh) {
            throw new RuntimeException(__('The file could not be read.'));
        }

        try {
            $header = fgetcsv($fh, 0, ',', '"', '');
            $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header ?: []);
            if (! in_array('notation', $header, true) || ! in_array('caption', $header, true)) {
                return ['created' => 0, 'updated' => 0, 'errors' => [__('The first row must name the columns, including notation and caption.')]];
            }

            $rows = [];
            $errors = [];
            $line = 1;
            while (($cells = fgetcsv($fh, 0, ',', '"', '')) !== false) {
                $line++;
                if ($cells === [null]) {
                    continue;
                }
                if (count($cells) > count($header)) {
                    if (count($errors) < self::MAX_IMPORT_ERRORS) {
                        $errors[] = __('Line :line: :problem', ['line' => $line, 'problem' => __('more cells than columns (quote any value containing a comma)')]);
                    }
                    continue;
                }
                $r = array_combine($header, array_pad($cells, count($header), ''));
                $notation = ClassificationNotation::normalise($scheme, (string) $r['notation']);
                $caption = trim((string) $r['caption']);
                $end = trim((string) ($r['notation_end'] ?? ''));
                $problem = match (true) {
                    $notation === '' => __('notation is empty'),
                    mb_strlen($notation) > 100 => __('notation is longer than 100 characters'),
                    $caption === '' => __('caption is empty'),
                    mb_strlen($caption) > 500 => __('caption is longer than 500 characters'),
                    mb_strlen($end) > 100 => __('notation_end is longer than 100 characters'),
                    isset($rows[$notation]) => __('notation repeats an earlier row'),
                    default => null,
                };
                if ($problem) {
                    if (count($errors) < self::MAX_IMPORT_ERRORS) {
                        $errors[] = __('Line :line: :problem', ['line' => $line, 'problem' => $problem]);
                    }
                    continue;
                }
                $end = $end === '' ? null : ClassificationNotation::normalise($scheme, $end);
                $parent = trim((string) ($r['parent_notation'] ?? ''));
                $rows[$notation] = [
                    'line' => $line,
                    'parent' => $parent === '' ? null : ClassificationNotation::normalise($scheme, $parent),
                    'row' => [
                        'schedule_id' => $scheduleId,
                        'notation' => $notation,
                        'notation_end' => $end,
                        'caption' => $caption,
                        'note' => ($note = trim((string) ($r['note'] ?? ''))) === '' ? null : $note,
                        'sort_key' => ClassificationNotation::sortKey($scheme, $notation),
                        'sort_key_end' => $end === null ? null : ClassificationNotation::sortKey($scheme, $end),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                ];
            }
        } finally {
            fclose($fh);
        }

        $existing = DB::table(self::ENTRY)->where('schedule_id', $scheduleId)->pluck('id', 'notation')->all();
        foreach ($rows as $notation => $r) {
            if ($r['parent'] !== null && ! isset($rows[$r['parent']]) && ! isset($existing[$r['parent']])) {
                if (count($errors) < self::MAX_IMPORT_ERRORS) {
                    $errors[] = __('Line :line: parent_notation :parent is not in the file or the schedule', ['line' => $r['line'], 'parent' => $r['parent']]);
                }
            }
        }
        if ($errors) {
            return ['created' => 0, 'updated' => 0, 'errors' => $errors];
        }

        try {
            DB::transaction(function () use ($rows, $scheduleId, $scheme, $existing, $header) {
                $columns = ['caption', 'sort_key', 'updated_at'];
                if (in_array('notation_end', $header, true)) {
                    array_push($columns, 'notation_end', 'sort_key_end');
                }
                if (in_array('note', $header, true)) {
                    $columns[] = 'note';
                }
                foreach (array_chunk(array_column($rows, 'row'), 500) as $chunk) {
                    DB::table(self::ENTRY)->upsert($chunk, ['schedule_id', 'notation'], $columns);
                }
                $ids = DB::table(self::ENTRY)->where('schedule_id', $scheduleId)->pluck('id', 'notation')->all();
                foreach ($rows as $notation => $r) {
                    // A database that folds case or accents can merge two spellings of a row.
                    $id = $ids[$notation] ?? throw new RuntimeException(__('Line :line: :problem', [
                        'line' => $r['line'], 'problem' => __('notation repeats an earlier row'),
                    ]));
                    if ($r['parent'] === null && isset($existing[$notation])) {
                        continue;
                    }
                    $parentId = null;
                    if ($r['parent'] !== null) {
                        $parentId = $ids[$r['parent']] ?? throw new RuntimeException(__('Line :line: parent_notation :parent is not in the file or the schedule', [
                            'line' => $r['line'], 'parent' => $r['parent'],
                        ]));
                    } else {
                        foreach (ClassificationNotation::broaderCandidates($scheme, (string) $notation) as $candidate) {
                            if (isset($ids[$candidate])) {
                                $parentId = $ids[$candidate];
                                break;
                            }
                        }
                    }
                    DB::table(self::ENTRY)->where('id', $id)->update(['parent_id' => $parentId]);
                }
                $this->adoptNarrower($scheme, $scheduleId, array_map('strval', array_keys(array_diff_key($rows, $existing))));
                $this->rebuildDepths($scheduleId);
            });
        } catch (RuntimeException $e) {
            return ['created' => 0, 'updated' => 0, 'errors' => [$e->getMessage()]];
        }

        $updated = count(array_intersect_key($rows, $existing));

        return ['created' => count($rows) - $updated, 'updated' => $updated, 'errors' => []];
    }

    /**
     * Judge a keyed number against the scheme's active schedule.
     * status: empty | no_schedule | invalid | unknown | broader | within | exact
     * exact and within (a true range hit) are validated; broader only names the
     * nearest class above a number the schedule does not contain.
     */
    public function check(?string $scheme, ?string $number): array
    {
        $number = trim((string) $number);
        if ($number === '') {
            return ['status' => 'empty', 'schedule' => null, 'entry' => null, 'path' => []];
        }
        $schedule = $this->activeSchedule($scheme);
        if (! $schedule) {
            return ['status' => 'no_schedule', 'schedule' => null, 'entry' => null, 'path' => []];
        }
        $result = fn ($status, $entry = null) => [
            'status' => $status,
            'schedule' => $schedule,
            'entry' => $entry,
            'path' => $entry ? array_map(fn ($e) => ['notation' => $e->notation, 'caption' => $e->caption], $this->path($entry)) : [],
        ];

        $normal = ClassificationNotation::normalise($schedule->scheme, $number);
        $exact = DB::table(self::ENTRY)->where('schedule_id', $schedule->id)->where('notation', $normal)->first();
        if ($exact) {
            return $result('exact', $exact);
        }
        $class = ClassificationNotation::classPortion($schedule->scheme, $number);
        if ($class === null) {
            return $result('invalid');
        }
        // LC letters followed by anything but a number are not a class number.
        if ($schedule->scheme === ClassificationNotation::LC && $class !== $normal && ! preg_match('/\d/', $class)) {
            return $result('invalid');
        }
        if ($class !== $normal && ($hit = DB::table(self::ENTRY)->where('schedule_id', $schedule->id)->where('notation', $class)->first())) {
            return $result('exact', $hit); // Class number is scheduled; the rest is cutter, date or auxiliary.
        }

        $broader = null;
        foreach (ClassificationNotation::broaderCandidates($schedule->scheme, $number) as $candidate) {
            if ($broader = DB::table(self::ENTRY)->where('schedule_id', $schedule->id)->where('notation', $candidate)->first()) {
                break;
            }
        }
        $key = ClassificationNotation::sortKey($schedule->scheme, $class);
        $range = DB::table(self::ENTRY)->where('schedule_id', $schedule->id)
            ->whereNotNull('sort_key_end')->where('sort_key', '<=', $key)->where('sort_key_end', '>=', $key)
            ->orderByDesc('depth')->orderByDesc('sort_key')->first();
        if ($range) {
            return $result('within', ! $broader || $range->depth >= $broader->depth ? $range : $broader);
        }

        return $broader ? $result('broader', $broader) : $result('unknown');
    }

    /**
     * Apply the active schedule's validation mode to a cataloguing form.
     *
     * @return string|null warning to show after saving
     * @throws ValidationException in strict mode
     */
    public function guardKeyedNumber(array $input, ?array $existing = null): ?string
    {
        if (! Schema::hasTable(self::SCHEDULE)) {
            return null;
        }
        // The form omits fields it does not edit; those keep their stored values.
        $keyed = $this->keyedNumber($existing ? $input + $existing : $input);
        if (! $keyed) {
            return null;
        }
        if ($existing && ($was = $this->keyedNumber($existing)) && $was['scheme'] === $keyed['scheme']
            && ClassificationNotation::normalise($was['scheme'], $was['number']) === ClassificationNotation::normalise($keyed['scheme'], $keyed['number'])) {
            return null;
        }
        $check = $this->check($keyed['scheme'], $keyed['number']);
        if (! in_array($check['status'], ['invalid', 'unknown', 'broader'], true) || $check['schedule']->validation_mode === 'off') {
            return null;
        }
        $message = $check['status'] === 'broader'
            ? __(':number is not in the schedule :schedule. Nearest broader class: :notation.', ['number' => $keyed['number'], 'schedule' => $check['schedule']->name, 'notation' => $check['entry']->notation])
            : __(':number is not in the schedule :schedule.', ['number' => $keyed['number'], 'schedule' => $check['schedule']->name]);
        if ($check['schedule']->validation_mode === 'strict') {
            throw ValidationException::withMessages([$keyed['field'] => $message]);
        }

        return $message;
    }

    /** Which field the cataloguer edits for this record's class number; the one validated. */
    public function keyedNumber(array $d): ?array
    {
        $scheme = ClassificationNotation::canonicalScheme($d['classification_scheme'] ?? null);
        $dewey = trim((string) ($d['dewey_decimal'] ?? ''));
        $classNo = trim((string) ($d['classification_number'] ?? ''));
        $call = trim((string) ($d['call_number'] ?? ''));

        if ($scheme === null) {
            return $dewey !== '' ? ['scheme' => ClassificationNotation::DEWEY, 'number' => $dewey, 'field' => 'dewey_decimal'] : null;
        }
        if ($scheme === ClassificationNotation::DEWEY && $dewey !== '') {
            return ['scheme' => $scheme, 'number' => $dewey, 'field' => 'dewey_decimal'];
        }
        if ($call !== '') {
            return ['scheme' => $scheme, 'number' => $call, 'field' => 'call_number'];
        }

        // Stored by imports only: the form has no input for it.
        return $classNo !== '' ? ['scheme' => $scheme, 'number' => $classNo, 'field' => 'classification_number'] : null;
    }

    /**
     * The number an item files under: its call number, which is what the shelf
     * list shows. A call number that does not fit its scheme gets no key and is
     * listed as not in shelf order rather than filed somewhere misleading.
     */
    private function shelfNumber(array $d): ?array
    {
        $call = trim((string) ($d['call_number'] ?? ''));
        if ($call === '') {
            return $this->keyedNumber($d);
        }
        $scheme = ClassificationNotation::canonicalScheme($d['classification_scheme'] ?? null)
            ?? ClassificationNotation::detectScheme($call);
        if ($scheme === null || ClassificationNotation::classPortion($scheme, $call) === null) {
            return null;
        }

        return ['scheme' => $scheme, 'number' => $call, 'field' => 'call_number'];
    }

    /** Never lets a shelf-key problem fail the catalogue save that triggered it. */
    public function refreshShelfKeyForObject(int $informationObjectId): void
    {
        try {
            if (! Schema::hasTable(self::SHELF)) {
                return;
            }
            $item = DB::table('library_item')->where('information_object_id', $informationObjectId)
                ->select('id', 'classification_scheme', 'classification_number', 'dewey_decimal', 'call_number')->first();
            if ($item) {
                $this->writeShelfKey($item);
            }
        } catch (\Throwable $e) {
            Log::warning('library shelf key refresh failed: '.$e->getMessage());
        }
    }

    public function rebuildShelfKeys(): int
    {
        $written = 0;
        DB::table('library_item')->select('id', 'classification_scheme', 'classification_number', 'dewey_decimal', 'call_number')
            ->orderBy('id')->chunkById(500, function ($items) use (&$written) {
                foreach ($items as $item) {
                    $written += $this->writeShelfKey($item) ? 1 : 0;
                }
            });

        return $written;
    }

    /** Items with a call number, in shelf order; records with no key file last. */
    public function shelfList(int $limit): Collection
    {
        $q = DB::table('library_item as li')
            ->whereNotNull('li.call_number')->where('li.call_number', '!=', '');
        if (! Schema::hasTable(self::SHELF)) {
            return $q->orderBy('li.call_number')->limit($limit)->select('li.*')->get();
        }

        return $q->leftJoin(self::SHELF.' as sk', 'sk.library_item_id', '=', 'li.id')
            ->orderByRaw('CASE WHEN sk.sort_key IS NULL THEN 1 ELSE 0 END')
            ->orderBy('sk.scheme')->orderBy('sk.sort_key')->orderBy('li.call_number')
            ->limit($limit)->select('li.*', 'sk.sort_key as shelf_sort_key')->get();
    }

    private function writeShelfKey(object $item): bool
    {
        $keyed = $this->shelfNumber((array) $item);
        if (! $keyed) {
            DB::table(self::SHELF)->where('library_item_id', $item->id)->delete();

            return false;
        }
        $check = $this->check($keyed['scheme'], $keyed['number']);
        DB::table(self::SHELF)->updateOrInsert(['library_item_id' => $item->id], [
            'scheme' => $keyed['scheme'],
            'class_number' => mb_substr($keyed['number'], 0, 255),
            'schedule_entry_id' => $check['entry']->id ?? null,
            'sort_key' => ClassificationNotation::sortKey($keyed['scheme'], $keyed['number']),
            'updated_at' => now(),
        ]);

        return true;
    }

    private function nearestBroaderId(string $scheme, int $scheduleId, string $notation, ?int $exceptId): ?int
    {
        foreach (ClassificationNotation::broaderCandidates($scheme, $notation) as $candidate) {
            $id = DB::table(self::ENTRY)->where('schedule_id', $scheduleId)->where('notation', $candidate)
                ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        return null;
    }

    /**
     * Move existing entries under newly added broader notations. An entry moves
     * only if it has no parent or its parent is further up its own notation
     * chain than the new entry.
     * ponytail: no stored "manual" flag, so a parent set outside the chain is
     * kept but a deliberate skip-level parent inside it moves when an entry is
     * added between them. Add a parent_is_manual column if that ever matters.
     */
    private function adoptNarrower(string $scheme, int $scheduleId, array $added): void
    {
        if (! $added) {
            return;
        }
        $added = array_flip($added);
        $rows = DB::table(self::ENTRY)->where('schedule_id', $scheduleId)->get(['id', 'parent_id', 'notation']);
        $ids = $rows->pluck('id', 'notation')->all();
        $notations = $rows->pluck('notation', 'id')->all();
        $parents = $rows->pluck('parent_id', 'id')->all();

        foreach ($rows as $row) {
            $candidates = ClassificationNotation::broaderCandidates($scheme, $row->notation);
            $limit = count($candidates);
            if ($row->parent_id) {
                $limit = array_search($notations[$row->parent_id] ?? null, $candidates, true);
                if ($limit === false) {
                    continue; // Parent set by hand outside the notation chain.
                }
            }
            foreach (array_slice($candidates, 0, $limit) as $candidate) {
                if (! isset($added[$candidate], $ids[$candidate])) {
                    continue;
                }
                // Never adopt an entry that is itself above the new parent.
                $seen = [];
                for ($up = (int) $ids[$candidate]; $up && ! isset($seen[$up]); $up = (int) ($parents[$up] ?? 0)) {
                    if ($up === (int) $row->id) {
                        continue 2;
                    }
                    $seen[$up] = true;
                }
                DB::table(self::ENTRY)->where('id', $row->id)->update(['parent_id' => $ids[$candidate]]);
                $parents[$row->id] = $ids[$candidate];
                break;
            }
        }
    }

    /** @throws RuntimeException when the parent links contain a loop */
    private function rebuildDepths(int $scheduleId): void
    {
        $rows = DB::table(self::ENTRY)->where('schedule_id', $scheduleId)->get(['id', 'parent_id', 'depth']);
        $children = [];
        foreach ($rows as $r) {
            $children[(int) $r->parent_id][] = (int) $r->id;
        }
        $depth = [];
        $level = $children[0] ?? [];
        for ($d = 0; $level; $d++) {
            $next = [];
            foreach ($level as $id) {
                $depth[$id] = $d;
                array_push($next, ...($children[$id] ?? []));
            }
            $level = $next;
        }
        if (count($depth) !== $rows->count()) {
            throw new RuntimeException(__('The broader-entry links form a loop.'));
        }
        foreach ($rows as $r) {
            if ((int) $r->depth !== $depth[(int) $r->id]) {
                DB::table(self::ENTRY)->where('id', $r->id)->update(['depth' => $depth[(int) $r->id]]);
            }
        }
    }
}
