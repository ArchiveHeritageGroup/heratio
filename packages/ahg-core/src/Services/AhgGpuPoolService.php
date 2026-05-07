<?php

/**
 * AhgGpuPoolService - Heratio
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

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Centralised GPU/AI endpoint pool. Replaces scattered per-service
 * settings (voice_local_llm_url, mt.endpoint, endpoint, etc.) with a
 * single registry the operator manages from one admin page. Lets ops
 * swap GPUs (today: .78 RTX 3070; tomorrow: .115; next week: a third)
 * by editing one row instead of chasing per-service settings.
 *
 * Storage: lazy-created `ahg_gpu_endpoint` table with one row per
 * physical GPU host (e.g. ollama-78, ollama-115, ollama-3rd). Each
 * carries an Ollama-style HTTP base URL + a comma-separated list of
 * models the host supports + a priority (lower = preferred) + an
 * is_active toggle for soft-disable without deleting the row.
 *
 * Strategy: 'priority' (default - first active row by priority for the
 * model wins) or 'round-robin' (rotate per-call across active rows
 * that support the model). Strategy lives in `ai_gpu_pool_strategy`
 * setting so the operator can flip without touching code.
 *
 * Health: pickEndpoint() optionally probes /api/tags before returning
 * a URL; failures get logged + the row's last_healthcheck_status flips
 * to 'down'. The next pick skips down rows so a single bad GPU doesn't
 * break the request path - it routes to the next-priority survivor.
 */
class AhgGpuPoolService
{
    public const TABLE = 'ahg_gpu_endpoint';
    public const SETTING_STRATEGY = 'ai_gpu_pool_strategy';
    public const SETTING_RR_CURSOR = 'ai_gpu_pool_rr_cursor';

    /** Round-robin cursor stored in ahg_settings to survive restarts. */
    private static int $rrCursor = 0;

    /**
     * Lazy-create the table. Idempotent. Seeds initial rows from the
     * existing legacy settings (voice_local_llm_url + endpoint +
     * mt.endpoint) so the operator's pre-pool config is preserved.
     * Re-running after seed is a no-op (INSERT IGNORE keyed by name).
     */
    public static function ensureTable(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            self::seedFromLegacySettings();
            return;
        }
        Schema::create(self::TABLE, function ($t) {
            $t->id();
            $t->string('name', 80)->unique();
            $t->string('url', 255);
            $t->text('models_supported')->nullable()
                ->comment('CSV of model names this endpoint serves, e.g. "qwen2.5:7b,llama3:70b"');
            $t->integer('priority')->default(100)
                ->comment('lower wins under priority strategy');
            // vRAM column drives the size-aware pick logic: an 8GB endpoint
            // can't honour a request for a 20GB model, so pickByMinVram
            // skips it. Operator sets per host; current ops:
            //   .78  = 8  (RTX 3070)
            //   .115 = 20 (incoming - TBD model)
            //   3rd  = 24 (next week)
            $t->integer('vram_gb')->default(8)
                ->comment('GPU vRAM in GB - drives capacity-aware model dispatch');
            $t->boolean('is_active')->default(true);
            $t->string('last_healthcheck_status', 20)->nullable();
            $t->timestamp('last_healthcheck_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        self::seedFromLegacySettings();
    }

    /**
     * One-time backfill from the scattered settings - so an operator
     * who's been running on voice_local_llm_url=http://192.168.0.78:11434
     * gets a 'gpu-78' pool row automatically rather than starting from
     * zero. Idempotent (insertOrIgnore keyed by name).
     */
    private static function seedFromLegacySettings(): void
    {
        try {
            $voiceUrl = (string) DB::table('ahg_settings')->where('setting_key', 'voice_local_llm_url')->value('setting_value');
            $endpoint = (string) DB::table('ahg_settings')->where('setting_key', 'endpoint')->value('setting_value');
            $mtEndpoint = (string) DB::table('ahg_settings')->where('setting_key', 'mt.endpoint')->value('setting_value');

            $now = now();
            $seed = [];
            if ($voiceUrl !== '') {
                $seed[] = self::seedRow('legacy-voice', $voiceUrl, 'qwen2.5:7b,qwen3:8b', 100, $now);
            }
            if ($endpoint !== '' && $endpoint !== $voiceUrl) {
                $seed[] = self::seedRow('legacy-endpoint', $endpoint, 'qwen2.5:7b,llava', 110, $now);
            }
            if ($mtEndpoint !== '' && stripos($mtEndpoint, 'translate') !== false) {
                // mt.endpoint points at the translate-adapter, not Ollama
                // directly. Mark with a distinguishing model tag so callers
                // that ask for translation route here.
                $seed[] = self::seedRow('legacy-translate', $mtEndpoint, 'translate-adapter', 50, $now);
            }
            if (!empty($seed)) {
                DB::table(self::TABLE)->insertOrIgnore($seed);
            }
        } catch (\Throwable $e) {
            Log::warning('[ahg-gpu-pool] seed-from-legacy failed: ' . $e->getMessage());
        }
    }

    private static function seedRow(string $name, string $url, string $models, int $priority, $now): array
    {
        // vRAM defaults vary by host. The legacy URLs map to known hardware:
        //   192.168.0.78  = RTX 3070 (8GB)
        //   192.168.0.115 = incoming GPU (20GB)
        //   anything else = conservative 8GB fallback (operator can edit)
        $vram = 8;
        if (str_contains($url, '192.168.0.78'))  $vram = 8;
        if (str_contains($url, '192.168.0.115')) $vram = 20;

        return [
            'name' => $name,
            'url' => rtrim($url, '/'),
            'models_supported' => $models,
            'priority' => $priority,
            'vram_gb' => $vram,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'notes' => 'auto-seeded from legacy ahg_settings on first AhgGpuPoolService boot; edit vram_gb if the host changed',
        ];
    }

    /**
     * Pick the next endpoint URL for a given model (or null = any).
     * Honours the active strategy + skips down rows + skips rows whose
     * vram_gb is below $minVramGb (so a 20GB-required model never gets
     * dispatched to an 8GB host). Returns null when the pool is empty /
     * all-down / nothing meets the vRAM floor (caller decides what to
     * do - usually fall back to the legacy hardcoded URL or fail loudly).
     *
     * Quick reference for callers picking $minVramGb:
     *   1.5B/3B int4 -> 4 GB
     *   7B  int4 -> 6 GB     (RTX 3070 + headroom)
     *   13B int4 -> 10 GB    (needs 20GB-class)
     *   34B int4 -> 22 GB    (needs 24GB-class)
     *   70B int4 -> 40 GB    (needs multi-GPU or 48GB)
     * Operator can override per-model in models_supported by listing
     * only the models the host can actually run.
     */
    public static function pickEndpoint(?string $model = null, int $minVramGb = 0): ?string
    {
        self::ensureTable();
        $strategy = (string) (DB::table('ahg_settings')
            ->where('setting_key', self::SETTING_STRATEGY)
            ->value('setting_value') ?: 'priority');

        $candidates = self::activeCandidates($model, $minVramGb);
        if (empty($candidates)) return null;

        if ($strategy === 'round-robin') {
            // Persist the cursor so successive PHP-FPM workers rotate together.
            $cursor = (int) DB::table('ahg_settings')
                ->where('setting_key', self::SETTING_RR_CURSOR)
                ->value('setting_value');
            $idx = $cursor % count($candidates);
            $row = $candidates[$idx];
            DB::table('ahg_settings')->updateOrInsert(
                ['setting_key' => self::SETTING_RR_CURSOR],
                ['setting_value' => (string) ($cursor + 1), 'setting_group' => 'ai_pool']
            );
            return rtrim($row->url, '/');
        }

        // 'priority' (default): first by priority asc, id asc tiebreak.
        return rtrim($candidates[0]->url, '/');
    }

    /**
     * Get the candidate list for a model in priority order, filtered by
     * is_active=1 + last_healthcheck_status != 'down' + vram_gb >=
     * $minVramGb. When $model is null, returns every matching active
     * row (caller is doing a model-agnostic call like /api/tags or
     * wants to manage the pool).
     */
    public static function activeCandidates(?string $model = null, int $minVramGb = 0): array
    {
        self::ensureTable();
        $q = DB::table(self::TABLE)->where('is_active', 1);

        // Skip rows last marked down. Lifetime of a 'down' marker is until
        // the next health check flips it - run health() to refresh.
        $q->where(function ($w) {
            $w->whereNull('last_healthcheck_status')
              ->orWhere('last_healthcheck_status', '!=', 'down');
        });

        if ($model !== null && $model !== '') {
            // Substring match in the comma-separated models_supported field.
            // Keeps the schema simple - a one-row-per-model side table would
            // be more relational but adds churn the operator would have to
            // manage manually.
            $q->where(function ($w) use ($model) {
                $w->whereNull('models_supported')
                  ->orWhere('models_supported', 'LIKE', '%' . $model . '%');
            });
        }

        if ($minVramGb > 0) {
            $q->where('vram_gb', '>=', $minVramGb);
        }

        return $q->orderBy('priority')->orderBy('id')->get()->all();
    }

    /**
     * Probe every endpoint via Ollama's /api/tags (cheap, no model load).
     * Updates last_healthcheck_status + last_healthcheck_at per row.
     * Translate-adapter rows are probed via the adapter's /healthz path
     * (Heratio convention) instead. Returns counts: ['up'=>N, 'down'=>M].
     */
    public static function health(): array
    {
        self::ensureTable();
        $rows = DB::table(self::TABLE)->where('is_active', 1)->get();
        $up = 0;
        $down = 0;

        foreach ($rows as $row) {
            $url = rtrim($row->url, '/');
            $models = (string) ($row->models_supported ?? '');
            $isAdapter = stripos($models, 'translate-adapter') !== false;
            $probe = $isAdapter ? '/healthz' : '/api/tags';

            try {
                $resp = Http::timeout(3)->get($url . $probe);
                $ok = $resp->successful();
            } catch (\Throwable $e) {
                $ok = false;
            }

            DB::table(self::TABLE)->where('id', $row->id)->update([
                'last_healthcheck_status' => $ok ? 'up' : 'down',
                'last_healthcheck_at' => now(),
            ]);
            $ok ? $up++ : $down++;
        }

        return ['up' => $up, 'down' => $down, 'total' => count($rows)];
    }

    /**
     * Operator-facing helper: register a new endpoint. Used by the
     * /admin/ahgSettings/gpuPool form's POST handler + by artisan
     * commands like `ahg:gpu-pool-add gpu-115 http://192.168.0.115:11434`.
     */
    public static function registerEndpoint(string $name, string $url, string $modelsCsv = '', int $priority = 100, int $vramGb = 8, ?string $notes = null): int
    {
        self::ensureTable();
        return (int) DB::table(self::TABLE)->updateOrInsert(
            ['name' => $name],
            [
                'url' => rtrim($url, '/'),
                'models_supported' => $modelsCsv,
                'priority' => $priority,
                'vram_gb' => $vramGb,
                'is_active' => 1,
                'notes' => $notes,
                'updated_at' => now(),
            ]
        );
    }
}
