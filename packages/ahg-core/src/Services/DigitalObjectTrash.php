<?php

/**
 * DigitalObjectTrash - Heratio ahg-core
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1734: a deleted digital object goes to a trash area first.
 *
 * Its files (master and derivatives) are moved - renamed on the same volume,
 * never copied and unlinked - to uploads_path/.trash/<date>/<digital object id>/,
 * and its rows (object, digital_object, any extra rows the caller names) are
 * kept as JSON in ahg_trash. Restore puts both back; purge removes the files
 * once the retention has passed (ahg:trash-purge).
 *
 * Retention is the setting trash_retention_days (default 30); 0 turns the
 * trash off and deletes at once, as before.
 *
 * ponytail: the uploads folder can be shared with another system (on
 * heratio.org, PSIS). Moving a file is no worse than the old immediate
 * delete, but the purge schedule ships switched off until both systems agree
 * on the trash location and retention.
 */
class DigitalObjectTrash
{
    public const TABLE = 'ahg_trash';

    public static function enabled(): bool
    {
        return Schema::hasTable(self::TABLE) && self::retentionDays() > 0;
    }

    public static function retentionDays(): int
    {
        return max(0, AhgSettingsService::getInt('trash_retention_days', 30));
    }

    public static function root(): string
    {
        return rtrim((string) config('heratio.uploads_path', DigitalObjectService::UPLOAD_DIR), '/').'/.trash';
    }

    /**
     * Move the objects' files to the trash and record their rows. Returns the
     * trash id, or null when a file could not be moved (nothing is deleted then).
     *
     * @param  \Illuminate\Support\Collection<int, object>  $objects  master + derivatives
     * @param  array<string, list<array>>  $extraRows  table => rows to restore with it
     */
    public static function put(int $digitalObjectId, $objects, array $extraRows = []): ?int
    {
        $dir = self::root().'/'.now()->format('Y-m-d').'/'.$digitalObjectId;
        $files = [];
        foreach ($objects as $obj) {
            $from = DigitalObjectService::resolveDiskPath($obj);
            if (! $from || ! is_file($from)) {
                continue;
            }
            if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                Log::error('[trash] cannot create '.$dir);

                return null;
            }
            $to = $dir.'/'.$obj->id.'-'.basename($from);
            $sha = @hash_file('sha256', $from) ?: null;
            if (! @rename($from, $to)) {
                // Put back what was already moved; refuse the delete.
                foreach ($files as $f) {
                    @rename($f['to'], $f['from']);
                }
                Log::error('[trash] cannot move '.$from);

                return null;
            }
            $files[] = ['digital_object_id' => (int) $obj->id, 'from' => $from, 'to' => $to, 'sha256' => $sha];
        }

        $ids = collect($objects)->pluck('id')->all();
        $rows = [
            'object' => DB::table('object')->whereIn('id', $ids)->get()->map(fn ($r) => (array) $r)->all(),
            'digital_object' => collect($objects)->map(fn ($r) => (array) $r)->all(),
        ];
        // Rows that hang off the objects and would go with them (alt text,
        // display flags) - restored after the objects themselves.
        if (Schema::hasTable('property')) {
            $props = DB::table('property')->whereIn('object_id', $ids)->get();
            $rows['property'] = $props->map(fn ($r) => (array) $r)->all();
            if ($props->isNotEmpty() && Schema::hasTable('property_i18n')) {
                $rows['property_i18n'] = DB::table('property_i18n')->whereIn('id', $props->pluck('id'))->get()->map(fn ($r) => (array) $r)->all();
            }
        }
        $rows += $extraRows;
        $master = collect($objects)->firstWhere('id', $digitalObjectId) ?? collect($objects)->first();

        $trashId = (int) DB::table(self::TABLE)->insertGetId([
            'digital_object_id' => $digitalObjectId,
            'information_object_id' => $master->object_id ?? null,
            'name' => mb_substr((string) ($master->name ?? ''), 0, 500),
            'rows_json' => json_encode($rows),
            'files_json' => json_encode($files),
            'status' => 'trashed',
            'deleted_by' => auth()->id(),
            'deleted_at' => now(),
            'purge_after' => now()->addDays(self::retentionDays()),
        ]);
        self::premis($master, 'deletion', 'Moved to the trash for '.self::retentionDays().' days');

        return $trashId;
    }

    /** Put the files and rows back. False when a file is no longer in the trash. */
    public static function restore(int $trashId): bool
    {
        $t = DB::table(self::TABLE)->where('id', $trashId)->where('status', 'trashed')->first();
        if (! $t) {
            return false;
        }
        $files = json_decode((string) $t->files_json, true) ?: [];
        foreach ($files as $f) {
            if (! is_file($f['to']) || is_file($f['from'])) {
                return false;
            }
        }
        DB::transaction(function () use ($t, $files) {
            foreach (json_decode((string) $t->rows_json, true) ?: [] as $table => $rows) {
                foreach ($rows as $row) {
                    $key = $table === 'property_i18n' ? ['id' => $row['id'], 'culture' => $row['culture']] : ['id' => $row['id']];
                    if (! DB::table($table)->where($key)->exists()) {
                        DB::table($table)->insert(array_diff_key($row, array_flip(self::generatedColumns($table))));
                    }
                }
            }
            foreach ($files as $f) {
                $dir = dirname($f['from']);
                is_dir($dir) || @mkdir($dir, 0775, true);
                if (! @rename($f['to'], $f['from'])) {
                    throw new \RuntimeException('cannot restore '.$f['from']);
                }
            }
            DB::table(self::TABLE)->where('id', $t->id)->update(['status' => 'restored', 'restored_at' => now(), 'restored_by' => auth()->id()]);
        });
        $master = DB::table('digital_object')->where('id', $t->digital_object_id)->first();
        self::premis($master, 'restoration', 'Restored from the trash');

        return true;
    }

    /** Remove a trashed object's files for good. */
    public static function purge(int $trashId): bool
    {
        $t = DB::table(self::TABLE)->where('id', $trashId)->where('status', 'trashed')->first();
        if (! $t) {
            return false;
        }
        foreach (json_decode((string) $t->files_json, true) ?: [] as $f) {
            if (is_file($f['to'])) {
                @unlink($f['to']);
            }
            @rmdir(dirname($f['to']));
            @rmdir(dirname($f['to'], 2));
        }
        DB::table(self::TABLE)->where('id', $t->id)->update(['status' => 'purged', 'purged_at' => now()]);
        $rows = json_decode((string) $t->rows_json, true) ?: [];
        self::premis((object) ($rows['digital_object'][0] ?? ['id' => $t->digital_object_id, 'object_id' => $t->information_object_id]), 'deletion', 'Purged from the trash');

        return true;
    }

    /** Purge everything past its retention. Returns how many were purged. */
    public static function purgeExpired(): int
    {
        if (! Schema::hasTable(self::TABLE)) {
            return 0;
        }
        $n = 0;
        foreach (DB::table(self::TABLE)->where('status', 'trashed')->where('purge_after', '<=', now())->pluck('id') as $id) {
            $n += self::purge((int) $id) ? 1 : 0;
        }

        return $n;
    }

    private static function generatedColumns(string $table): array
    {
        static $cache = [];

        return $cache[$table] ??= collect(Schema::getColumns($table))
            ->filter(fn ($c) => ! empty($c['generation']))->pluck('name')->all();
    }

    private static function premis(?object $obj, string $type, string $detail): void
    {
        if (! $obj || ! class_exists(\AhgPreservation\Services\PreservationService::class)) {
            return;
        }
        try {
            app(\AhgPreservation\Services\PreservationService::class)->logEvent((int) $obj->id, ($obj->object_id ?? null) ? (int) $obj->object_id : null, $type, $detail, 'success');
        } catch (\Throwable $e) {
            Log::info('[trash] PREMIS event skipped: '.$e->getMessage());
        }
    }
}
