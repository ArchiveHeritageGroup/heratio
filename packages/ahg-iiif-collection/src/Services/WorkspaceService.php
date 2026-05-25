<?php
/**
 * Heratio - per-user Mirador workspace persistence service (issue #699).
 *
 * @copyright Copyright (c) 2026, The Archive and Heritage Group (Pty) Ltd
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

namespace AhgIiifCollection\Services;

use Illuminate\Support\Facades\DB;

/**
 * WorkspaceService - reads / writes rows in ahg_iiif_workspace.
 *
 * The schema is intentionally simple: one row per saved workspace per user.
 * config_json holds whatever Mirador.exportConfig() emits on the client; we
 * do not attempt to inspect or rewrite it server-side. is_default is mutually
 * exclusive per user (setDefault() clears the rest in one transaction).
 */
class WorkspaceService
{
    public function listForUser(int $userId): array
    {
        $rows = DB::table('ahg_iiif_workspace')
            ->where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'is_default', 'created_at', 'updated_at'])
            ->all();

        return array_map(fn ($r) => (array) $r, $rows);
    }

    public function find(int $userId, int $id): ?array
    {
        $row = DB::table('ahg_iiif_workspace')
            ->where('user_id', $userId)
            ->where('id', $id)
            ->first();

        return $row ? (array) $row : null;
    }

    public function findDefault(int $userId): ?array
    {
        $row = DB::table('ahg_iiif_workspace')
            ->where('user_id', $userId)
            ->where('is_default', 1)
            ->first();

        return $row ? (array) $row : null;
    }

    public function create(int $userId, string $name, $configJson, bool $isDefault = false): int
    {
        $payload = $this->normaliseConfigPayload($configJson);

        return DB::transaction(function () use ($userId, $name, $payload, $isDefault) {
            if ($isDefault) {
                DB::table('ahg_iiif_workspace')
                    ->where('user_id', $userId)
                    ->update(['is_default' => 0]);
            }

            return (int) DB::table('ahg_iiif_workspace')->insertGetId([
                'user_id'     => $userId,
                'name'        => mb_substr($name, 0, 255),
                'config_json' => $payload,
                'is_default'  => $isDefault ? 1 : 0,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        });
    }

    public function update(int $userId, int $id, array $fields): bool
    {
        $row = $this->find($userId, $id);
        if (!$row) {
            return false;
        }

        $patch = [];
        if (array_key_exists('name', $fields) && $fields['name'] !== null) {
            $patch['name'] = mb_substr((string) $fields['name'], 0, 255);
        }
        if (array_key_exists('config_json', $fields) && $fields['config_json'] !== null) {
            $patch['config_json'] = $this->normaliseConfigPayload($fields['config_json']);
        }
        if (!$patch) {
            return true;
        }

        $patch['updated_at'] = now();

        DB::table('ahg_iiif_workspace')
            ->where('user_id', $userId)
            ->where('id', $id)
            ->update($patch);

        return true;
    }

    public function delete(int $userId, int $id): bool
    {
        $deleted = DB::table('ahg_iiif_workspace')
            ->where('user_id', $userId)
            ->where('id', $id)
            ->delete();

        return $deleted > 0;
    }

    /**
     * Flag a single workspace as the user's default-on-load. Clears the
     * default flag on every other row owned by the same user in one txn so
     * the (user_id, is_default=1) invariant stays at most-one-row.
     */
    public function setDefault(int $userId, int $id): bool
    {
        $row = $this->find($userId, $id);
        if (!$row) {
            return false;
        }

        DB::transaction(function () use ($userId, $id) {
            DB::table('ahg_iiif_workspace')
                ->where('user_id', $userId)
                ->update(['is_default' => 0]);
            DB::table('ahg_iiif_workspace')
                ->where('user_id', $userId)
                ->where('id', $id)
                ->update(['is_default' => 1, 'updated_at' => now()]);
        });

        return true;
    }

    /**
     * Accept either a JSON string or an already-decoded array/object and
     * normalise it to the canonical JSON string we store. We re-encode to
     * defend against caller-supplied whitespace and to validate well-formedness
     * in one step (json_encode never emits invalid JSON).
     */
    private function normaliseConfigPayload($configJson): string
    {
        if (is_string($configJson)) {
            $decoded = json_decode($configJson, true);
            if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
                return json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            // Last-ditch: pass through as a JSON string literal so the column
            // stays valid JSON even on garbage input.
            return json_encode($configJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return json_encode($configJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
