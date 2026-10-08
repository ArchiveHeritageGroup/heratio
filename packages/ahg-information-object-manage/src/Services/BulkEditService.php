<?php

/**
 * BulkEditService - bulk edits of archival descriptions (heratio#1542).
 *
 * Four operations over a scope (a fonds or series and everything beneath it,
 * or a repository):
 *
 *   find_replace   replace text in one field, in one language
 *   set_field      set the level, repository or publication status
 *   rename         retitle from a pattern ({title}, {identifier}, {n})
 *   sort_children  reorder a record's children by identifier, naturally or
 *                  numerically
 *
 * preview() shows every change before anything is written. run() applies a
 * queued batch (BulkEditJob calls it), recording each change's before and
 * after in ahg_bulk_edit_change and in the audit log. undo() puts the before
 * values back wherever a field still holds what the batch wrote, so a later
 * hand edit is never overwritten.
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

namespace AhgInformationObjectManage\Services;

use AhgCore\Services\EncryptionService;
use AhgCore\Services\HierarchyQueryService;
use Illuminate\Support\Facades\DB;

class BulkEditService
{
    public const KINDS = ['find_replace', 'set_field', 'rename', 'sort_children'];

    /** information_object_i18n columns find and replace may touch. */
    public const TEXT_COLUMNS = [
        'title' => 'Title',
        'alternate_title' => 'Alternate title',
        'edition' => 'Edition',
        'extent_and_medium' => 'Extent and medium',
        'archival_history' => 'Archival history',
        'acquisition' => 'Immediate source of acquisition',
        'scope_and_content' => 'Scope and content',
        'appraisal' => 'Appraisal, destruction and scheduling',
        'accruals' => 'Accruals',
        'arrangement' => 'System of arrangement',
        'access_conditions' => 'Conditions governing access',
        'reproduction_conditions' => 'Conditions governing reproduction',
        'physical_characteristics' => 'Physical characteristics',
        'finding_aids' => 'Finding aids',
        'location_of_originals' => 'Location of originals',
        'location_of_copies' => 'Location of copies',
        'related_units_of_description' => 'Related units of description',
        'rules' => 'Rules or conventions',
        'sources' => 'Sources',
        'revision_history' => 'Dates of creation, revision and deletion',
    ];

    /** Columns stored encrypted when access-restriction encryption is on. */
    private const ENCRYPTED = ['access_conditions', 'reproduction_conditions'];

    /** Fields set_field may set, with where they live. */
    public const SET_FIELDS = [
        'level_of_description_id' => 'Level of description',
        'repository_id' => 'Repository',
        'publication_status_id' => 'Publication status',
    ];

    private const ROOT_ID = 1;

    private const PREVIEW_LIMIT = 200;

    public function __construct(private ?EncryptionService $encryption = null)
    {
        $this->encryption ??= new EncryptionService;
    }

    /**
     * Ids in scope, in tree order: a record and everything beneath it
     * (ancestor_id), or every description of a repository (repository_id).
     *
     * @return list<int>
     */
    public function scopeIds(array $scope): array
    {
        if (! empty($scope['ancestor_id'])) {
            $ids = app(HierarchyQueryService::class)->descendantIds('information_object', (int) $scope['ancestor_id'], true);
        } elseif (! empty($scope['repository_id'])) {
            $ids = DB::table('information_object')->where('repository_id', (int) $scope['repository_id'])->pluck('id')->all();
        } else {
            return [];
        }
        $ids = array_values(array_diff(array_map('intval', $ids), [self::ROOT_ID]));

        return $ids ? DB::table('information_object')->whereIn('id', $ids)->orderBy('lft')->orderBy('id')->pluck('id')->map('intval')->all() : [];
    }

    /** Throw on parameters the operation cannot run with. */
    public function validate(string $kind, array $params): void
    {
        $fail = fn (string $m) => throw new \InvalidArgumentException($m);
        match ($kind) {
            'find_replace' => (isset(self::TEXT_COLUMNS[$params['column'] ?? '']) && ($params['pattern'] ?? '') !== '')
                ?: $fail(__('Choose a field and enter the text to find.')),
            'set_field' => isset(self::SET_FIELDS[$params['field'] ?? '']) ?: $fail(__('Choose a field to set.')),
            'rename' => str_contains((string) ($params['template'] ?? ''), '{') || trim((string) ($params['template'] ?? '')) !== ''
                ?: $fail(__('Enter a title pattern.')),
            'sort_children' => in_array($params['mode'] ?? '', ['natural', 'numeric'], true) ?: $fail(__('Choose how to sort.')),
            default => $fail(__('Unknown bulk edit.')),
        };
    }

    /**
     * Every change the operation would make, without writing anything.
     *
     * @return array{total: int, changes: list<array{object_id: int, slug: ?string, title: ?string, field: string, before: ?string, after: ?string}>}
     */
    public function preview(string $kind, array $params, array $scope): array
    {
        $this->validate($kind, $params);
        $changes = $this->plan($kind, $params, $scope);

        return ['total' => count($changes), 'changes' => array_slice($this->label($changes), 0, self::PREVIEW_LIMIT)];
    }

    /** Queue a batch; returns its id. The caller dispatches BulkEditJob. */
    public function queue(string $kind, array $params, array $scope, ?int $userId): int
    {
        $this->validate($kind, $params);

        return (int) DB::table('ahg_bulk_edit')->insertGetId([
            'kind' => $kind, 'params' => json_encode($params), 'scope' => json_encode($scope),
            'status' => 'queued', 'total' => count($this->scopeIds($scope)), 'created_by' => $userId, 'created_at' => now(),
        ]);
    }

    /** Apply a queued batch. Re-plans at run time so it acts on current values. */
    public function run(int $batchId): void
    {
        $batch = DB::table('ahg_bulk_edit')->where('id', $batchId)->first();
        if (! $batch || ! in_array($batch->status, ['queued', 'failed'], true)) {
            return;
        }
        DB::table('ahg_bulk_edit')->where('id', $batchId)->update(['status' => 'running', 'error' => null]);

        try {
            $params = json_decode($batch->params, true) ?: [];
            $changes = $this->plan($batch->kind, $params, json_decode($batch->scope, true) ?: []);
            DB::table('ahg_bulk_edit')->where('id', $batchId)->update(['total' => count($changes)]);

            if ($batch->kind === 'sort_children') {
                $this->applyOrder($batchId, $changes);
            } else {
                foreach (array_chunk($changes, 100) as $n => $chunk) {
                    DB::transaction(function () use ($batchId, $chunk) {
                        foreach ($chunk as $c) {
                            $this->write($c['object_id'], $c['field'], $c['culture'] ?? null, $c['after']);
                            $this->record($batchId, $c);
                        }
                    });
                    DB::table('ahg_bulk_edit')->where('id', $batchId)->update(['done' => min(count($changes), ($n + 1) * 100), 'changed' => min(count($changes), ($n + 1) * 100)]);
                }
            }

            DB::table('ahg_bulk_edit')->where('id', $batchId)->update([
                'status' => 'done', 'done' => count($changes), 'changed' => count($changes), 'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            DB::table('ahg_bulk_edit')->where('id', $batchId)->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 2000)]);
            throw $e;
        }
    }

    /**
     * Put back what a batch changed, where the field still holds the batch's
     * value. Returns [restored, skipped].
     *
     * @return array{restored: int, skipped: int}
     */
    public function undo(int $batchId, ?int $userId): array
    {
        $batch = DB::table('ahg_bulk_edit')->where('id', $batchId)->first();
        if (! $batch || $batch->status !== 'done') {
            throw new \DomainException(__('Only a finished batch can be undone.'));
        }
        $restored = $skipped = 0;
        $rows = DB::table('ahg_bulk_edit_change')->where('bulk_edit_id', $batchId)->orderByDesc('id')->get();

        DB::transaction(function () use ($rows, &$restored, &$skipped) {
            foreach ($rows as $r) {
                if ($r->field === '_order') {
                    foreach (json_decode((string) $r->before_value, true) ?: [] as $childId) {
                        if (DB::table('information_object')->where('id', $childId)->where('parent_id', $r->object_id)->exists()) {
                            InformationObjectService::moveUnder((int) $childId, (int) $r->object_id);
                        }
                    }
                    $restored++;

                    continue;
                }
                if ((string) $this->read((int) $r->object_id, $r->field, $r->culture) !== (string) $r->after_value) {
                    $skipped++;   // edited since; leave the newer value

                    continue;
                }
                $this->write((int) $r->object_id, $r->field, $r->culture, $r->before_value);
                $restored++;
            }
        });

        DB::table('ahg_bulk_edit')->where('id', $batchId)->update(['status' => 'undone', 'undone_at' => now(), 'undone_by' => $userId]);

        return ['restored' => $restored, 'skipped' => $skipped];
    }

    // ----------------------------------------------------------------

    /** @return list<array{object_id: int, field: string, culture: ?string, before: ?string, after: ?string}> */
    private function plan(string $kind, array $params, array $scope): array
    {
        $culture = (string) ($params['culture'] ?? app()->getLocale());

        if ($kind === 'sort_children') {
            return $this->planOrder($scope, $params['mode']);
        }

        $changes = [];
        $n = 0;   // position in scope, for {n}
        $ids = $this->scopeIds($scope);
        foreach (array_chunk($ids, 500) as $chunk) {
            if ($kind === 'set_field') {
                foreach ($chunk as $id) {
                    $before = $this->read($id, $params['field'], null);
                    $after = $params['value'] === '' || $params['value'] === null ? null : (string) (int) $params['value'];
                    if ((string) $before !== (string) $after) {
                        $changes[] = ['object_id' => $id, 'field' => $params['field'], 'culture' => null, 'before' => $before, 'after' => $after];
                    }
                }

                continue;
            }

            $column = $kind === 'rename' ? 'title' : $params['column'];
            $rows = DB::table('information_object_i18n as i')->join('information_object as io', 'io.id', '=', 'i.id')
                ->whereIn('i.id', $chunk)->where('i.culture', $culture)
                ->get(['i.id', "i.{$column} as value", 'i.title', 'io.identifier'])->keyBy('id');
            foreach ($chunk as $id) {
                $n++;
                $row = $rows[$id] ?? null;
                if (! $row) {
                    continue;
                }
                $before = $this->plain($column, $row->value, $id);
                $after = $kind === 'rename'
                    ? strtr((string) $params['template'], ['{title}' => (string) $row->title, '{identifier}' => (string) $row->identifier, '{n}' => (string) $n])
                    : $this->replace((string) $before, (string) $params['pattern'], (string) ($params['replacement'] ?? ''), ! empty($params['case_sensitive']));
                $after = trim($after) === '' && $column === 'title' ? $before : $after;
                if ((string) $before !== (string) $after) {
                    $changes[] = ['object_id' => $id, 'field' => $column, 'culture' => $culture, 'before' => $before, 'after' => $after];
                }
            }
        }

        return $changes;
    }

    private function replace(string $text, string $find, string $with, bool $caseSensitive): string
    {
        return $caseSensitive ? str_replace($find, $with, $text)
            : (string) preg_replace('/'.preg_quote($find, '/').'/iu', str_replace(['\\', '$'], ['\\\\', '\\$'], $with), $text);
    }

    /** One change row holding the parent's child order before and after. */
    private function planOrder(array $scope, string $mode): array
    {
        $parentId = (int) ($scope['ancestor_id'] ?? 0);
        $children = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i', fn ($j) => $j->on('i.id', '=', 'io.id')->where('i.culture', app()->getLocale()))
            ->where('io.parent_id', $parentId)->orderBy('io.lft')->orderBy('io.id')
            ->get(['io.id', 'io.identifier', 'i.title']);
        $before = $children->pluck('id')->map('intval')->all();
        $key = fn ($c) => (string) ($c->identifier ?? '') !== '' ? (string) $c->identifier : (string) ($c->title ?? '');
        $sorted = $children->all();
        usort($sorted, function ($a, $b) use ($mode, $key) {
            if ($mode === 'numeric') {
                $na = preg_match('/\d+/', $key($a), $m) ? (int) $m[0] : PHP_INT_MAX;
                $nb = preg_match('/\d+/', $key($b), $m) ? (int) $m[0] : PHP_INT_MAX;
                if ($na !== $nb) {
                    return $na <=> $nb;
                }
            }

            return strnatcasecmp($key($a), $key($b)) ?: $a->id <=> $b->id;
        });
        $after = array_map(fn ($c) => (int) $c->id, $sorted);

        return $before === $after ? [] : [[
            'object_id' => $parentId, 'field' => '_order', 'culture' => null,
            'before' => json_encode($before), 'after' => json_encode($after),
        ]];
    }

    private function applyOrder(int $batchId, array $changes): void
    {
        foreach ($changes as $c) {
            foreach (json_decode($c['after'], true) as $i => $childId) {
                InformationObjectService::moveUnder((int) $childId, (int) $c['object_id']);
                DB::table('ahg_bulk_edit')->where('id', $batchId)->update(['done' => $i + 1]);
            }
            $this->record($batchId, $c, false);
        }
    }

    private function read(int $id, string $field, ?string $culture): ?string
    {
        if ($field === 'publication_status_id') {
            $v = DB::table('status')->where('object_id', $id)->where('type_id', 158)->value('status_id');
        } elseif (isset(self::SET_FIELDS[$field])) {
            $v = DB::table('information_object')->where('id', $id)->value($field);
        } else {
            $v = $this->plain($field, DB::table('information_object_i18n')->where('id', $id)->where('culture', $culture)->value($field), $id);
        }

        return $v === null ? null : (string) $v;
    }

    private function write(int $id, string $field, ?string $culture, ?string $value): void
    {
        if ($field === 'publication_status_id') {
            \AhgCore\Support\StatusRow::put($id, 158, (int) $value);
        } elseif (isset(self::SET_FIELDS[$field])) {
            DB::table('information_object')->where('id', $id)->update([$field => $value === null ? null : (int) $value]);
        } else {
            $stored = in_array($field, self::ENCRYPTED, true)
                ? $this->encryption->encrypt(EncryptionService::CATEGORY_ACCESS_RESTRICTIONS, $value, 'information_object_i18n', $field, $id)
                : $value;
            DB::table('information_object_i18n')->where('id', $id)->where('culture', $culture)->update([$field => $stored]);
        }
        DB::table('object')->where('id', $id)->update(['updated_at' => now()]);   // a real change, by construction
    }

    private function plain(string $field, ?string $stored, int $id): ?string
    {
        return in_array($field, self::ENCRYPTED, true)
            ? $this->encryption->decrypt(EncryptionService::CATEGORY_ACCESS_RESTRICTIONS, $stored, 'information_object_i18n', $field, $id)
            : $stored;
    }

    private function record(int $batchId, array $c, bool $audit = true): void
    {
        DB::table('ahg_bulk_edit_change')->insert([
            'bulk_edit_id' => $batchId, 'object_id' => $c['object_id'], 'field' => $c['field'],
            'culture' => $c['culture'] ?? null, 'before_value' => $c['before'], 'after_value' => $c['after'],
        ]);
        if ($audit) {
            \AhgCore\Support\AuditLog::captureEdit($c['object_id'], 'information_object',
                [$c['field'] => $c['before'], 'bulk_edit_id' => $batchId], [$c['field'] => $c['after'], 'bulk_edit_id' => $batchId]);
        }
    }

    /** Add slug and title to change rows for display. */
    private function label(array $changes): array
    {
        $ids = array_column($changes, 'object_id');
        if (isset($changes[0]) && $changes[0]['field'] === '_order') {
            $ids = array_merge($ids, json_decode($changes[0]['after'], true));
        }
        $slugs = DB::table('slug')->whereIn('object_id', $ids)->pluck('slug', 'object_id');
        $titles = DB::table('information_object_i18n')->whereIn('id', $ids)->where('culture', app()->getLocale())->pluck('title', 'id');

        return array_map(fn ($c) => $c + ['slug' => $slugs[$c['object_id']] ?? null, 'title' => $titles[$c['object_id']] ?? null,
            'order_titles' => $c['field'] === '_order' ? array_map(fn ($i) => $titles[$i] ?? ('#'.$i), json_decode($c['after'], true)) : null], $changes);
    }
}
