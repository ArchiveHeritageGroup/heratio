<?php

namespace AhgIcip\Services;

use AhgCore\Services\SecretCrypto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * LocalContextsHubService
 *
 * Integration with the Local Contexts Hub (localcontextshub.org): sync a
 * registered Hub PROJECT's applied TK/BC Labels + Notices into Heratio and
 * pull authoritative label metadata/translations.
 *
 * Design (issue #1448):
 *  - Config seam in icip_config: local_contexts_hub_enabled (0/1),
 *    local_contexts_api_key (SecretCrypto-wrapped), local_contexts_hub_url
 *    (default https://localcontextshub.org), local_contexts_project_ids
 *    (comma/newline-separated Hub project unique_ids).
 *  - All Hub calls go through Laravel's Http client with a short timeout and
 *    are fully guarded: any failure logs a warning and degrades gracefully to
 *    the local icip_tk_label_type catalog. The Hub is NEVER on a page's
 *    critical path.
 *  - Synced projects are persisted in icip_hub_project so display works
 *    offline and a scheduled `ahg:icip-hub-sync` keeps them current.
 *  - Additive + guarded: with the module disabled or no credentials/table,
 *    every method behaves exactly as the previous stub (empty / local-only).
 *
 * NOTE: the live Hub API contract (endpoints/auth/payloads) can only be
 * verified against a real registered project + credentials, which the dev
 * host does not have. This implements the documented v1 contract
 * (GET /api/v1/projects/{unique_id}/ with an X-Api-Key header) behind the
 * enable flag, so it is inert until an operator supplies credentials.
 */
class LocalContextsHubService
{
    /** Cache TTL for a fetched Hub project (seconds). */
    private const CACHE_TTL = 21600; // 6h

    /** HTTP timeout for Hub calls (seconds). */
    private const HTTP_TIMEOUT = 10;

    private const DEFAULT_HUB_URL = 'https://localcontextshub.org';

    // ── Config ──────────────────────────────────────────────────────────

    public function isEnabled(): bool
    {
        try {
            if (! Schema::hasTable('icip_config')) {
                return false;
            }
            $val = DB::table('icip_config')->where('config_key', 'local_contexts_hub_enabled')->value('config_value');

            return (int) $val === 1;
        } catch (\Throwable $e) {
            Log::warning('LocalContextsHubService::isEnabled error: '.$e->getMessage());

            return false;
        }
    }

    public function getApiKey(): ?string
    {
        try {
            if (! Schema::hasTable('icip_config')) {
                return null;
            }

            // #1395(D) decrypt-at-rest - value may be Crypt ciphertext or legacy plaintext.
            return SecretCrypto::reveal((string) DB::table('icip_config')->where('config_key', 'local_contexts_api_key')->value('config_value')) ?: null;
        } catch (\Throwable $e) {
            Log::warning('LocalContextsHubService::getApiKey error: '.$e->getMessage());

            return null;
        }
    }

    /** The Hub base URL (no trailing slash), configurable per instance. */
    public function hubBaseUrl(): string
    {
        try {
            if (Schema::hasTable('icip_config')) {
                $url = trim((string) DB::table('icip_config')->where('config_key', 'local_contexts_hub_url')->value('config_value'));
                if ($url !== '') {
                    return rtrim($url, '/');
                }
            }
        } catch (\Throwable $e) {
            // fall through to default
        }

        return self::DEFAULT_HUB_URL;
    }

    /**
     * Hub project unique_ids configured for sync.
     *
     * @return array<int,string>
     */
    public function configuredProjectIds(): array
    {
        try {
            if (! Schema::hasTable('icip_config')) {
                return [];
            }
            $raw = (string) DB::table('icip_config')->where('config_key', 'local_contexts_project_ids')->value('config_value');

            return array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $raw) ?: [])));
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ── Hub fetch ───────────────────────────────────────────────────────

    /**
     * Fetch one Hub project (its applied TK/BC Labels + Notices) from the
     * live Hub API. Cached; returns null on any failure (unreachable, auth,
     * bad payload) so callers can fall back to the local catalog.
     *
     * @return array<string,mixed>|null
     */
    public function fetchProject(string $projectId, bool $fresh = false): ?array
    {
        $projectId = trim($projectId);
        if ($projectId === '' || ! $this->isEnabled()) {
            return null;
        }

        $cacheKey = 'icip_hub_project:'.md5($this->hubBaseUrl().'|'.$projectId);
        if ($fresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($projectId) {
            try {
                $req = Http::timeout(self::HTTP_TIMEOUT)
                    ->retry(1, 200)
                    ->acceptJson();

                $apiKey = $this->getApiKey();
                if ($apiKey) {
                    // The Hub uses an X-Api-Key header for account-scoped access;
                    // public projects are readable without one.
                    $req = $req->withHeaders(['X-Api-Key' => $apiKey]);
                }

                $url = $this->hubBaseUrl().'/api/v1/projects/'.rawurlencode($projectId).'/';
                $resp = $req->get($url);

                if (! $resp->successful()) {
                    Log::warning('LocalContextsHubService: Hub returned HTTP '.$resp->status().' for project '.$projectId);

                    return null;
                }

                $data = $resp->json();

                return is_array($data) ? $data : null;
            } catch (\Throwable $e) {
                Log::warning('LocalContextsHubService::fetchProject error: '.$e->getMessage());

                return null;
            }
        });
    }

    // ── Sync + persistence ──────────────────────────────────────────────

    /**
     * Fetch a Hub project and persist its Labels + Notices into
     * icip_hub_project. Returns a summary. Never throws.
     *
     * @return array{ok:bool,project_id:string,labels:int,notices:int,error?:string}
     */
    public function syncProject(string $projectId): array
    {
        $projectId = trim($projectId);
        $summary = ['ok' => false, 'project_id' => $projectId, 'labels' => 0, 'notices' => 0];

        if ($projectId === '') {
            $summary['error'] = 'empty project id';

            return $summary;
        }
        if (! $this->isEnabled()) {
            $summary['error'] = 'hub integration disabled';

            return $summary;
        }
        if (! $this->ensureTable()) {
            $summary['error'] = 'icip_hub_project table unavailable';

            return $summary;
        }

        $data = $this->fetchProject($projectId, true);
        if ($data === null) {
            $summary['error'] = 'fetch failed (see log) - local catalog remains the fallback';

            return $summary;
        }

        $labels = $this->extractLabels($data);
        $notices = is_array($data['notice'] ?? null) ? $data['notice'] : (is_array($data['notices'] ?? null) ? $data['notices'] : []);

        try {
            $now = Carbon::now();
            DB::table('icip_hub_project')->updateOrInsert(
                ['project_id' => $projectId],
                [
                    'title'        => mb_substr((string) ($data['title'] ?? ($data['project_page'] ?? '')), 0, 500),
                    'labels_json'  => json_encode(array_values($labels), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'notices_json' => json_encode(array_values($notices), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'http_status'  => 200,
                    'synced_at'    => $now,
                    'updated_at'   => $now,
                    'created_at'   => $now,
                ]
            );
            $summary['ok'] = true;
            $summary['labels'] = count($labels);
            $summary['notices'] = count($notices);
        } catch (\Throwable $e) {
            Log::warning('LocalContextsHubService::syncProject persist error: '.$e->getMessage());
            $summary['error'] = 'persist failed: '.$e->getMessage();
        }

        return $summary;
    }

    /**
     * Sync every configured Hub project. Returns per-project summaries.
     *
     * @return array<int,array<string,mixed>>
     */
    public function syncAll(): array
    {
        $out = [];
        foreach ($this->configuredProjectIds() as $pid) {
            $out[] = $this->syncProject($pid);
        }

        return $out;
    }

    /**
     * Read a previously-synced project from local storage.
     *
     * @return array{project_id:string,title:?string,labels:array,notices:array,synced_at:?string}|null
     */
    public function getSyncedProject(string $projectId): ?array
    {
        try {
            if (! Schema::hasTable('icip_hub_project')) {
                return null;
            }
            $row = DB::table('icip_hub_project')->where('project_id', trim($projectId))->first();
            if (! $row) {
                return null;
            }

            return [
                'project_id' => $row->project_id,
                'title'      => $row->title,
                'labels'     => json_decode((string) $row->labels_json, true) ?: [],
                'notices'    => json_decode((string) $row->notices_json, true) ?: [],
                'synced_at'  => $row->synced_at,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ── Label metadata (Hub-first, local-catalog fallback) ──────────────

    /**
     * Authoritative metadata for a TK/BC label code. Prefers a synced Hub
     * label (name, text/description, image, community, translations); falls
     * back to the local icip_tk_label_type catalog so display/gating always
     * work offline. Returns [] only when neither source has the code.
     *
     * @return array<string,mixed>
     */
    public function labelMetadata(string $labelCode): array
    {
        $labelCode = trim($labelCode);
        if ($labelCode === '') {
            return [];
        }

        // 1. Hub-synced labels (if any project is synced).
        try {
            if ($this->isEnabled() && Schema::hasTable('icip_hub_project')) {
                foreach (DB::table('icip_hub_project')->pluck('labels_json') as $json) {
                    foreach ((json_decode((string) $json, true) ?: []) as $lbl) {
                        if (! is_array($lbl)) {
                            continue;
                        }
                        $type = (string) ($lbl['label_type'] ?? ($lbl['type'] ?? ''));
                        if ($type !== '' && strcasecmp($type, $labelCode) === 0) {
                            return [
                                'source'       => 'hub',
                                'code'         => $type,
                                'name'         => (string) ($lbl['name'] ?? ''),
                                'description'  => (string) ($lbl['label_text'] ?? ($lbl['text'] ?? '')),
                                'image'        => (string) ($lbl['img_url'] ?? ($lbl['image_url'] ?? '')),
                                'community'    => (string) ($lbl['community'] ?? ''),
                                'translations' => is_array($lbl['translations'] ?? null) ? $lbl['translations'] : [],
                            ];
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // fall through to local catalog
        }

        // 2. Local icip_tk_label_type catalog (current behaviour).
        try {
            if (Schema::hasTable('icip_tk_label_type')) {
                $row = DB::table('icip_tk_label_type')->where('code', $labelCode)->first();
                if ($row) {
                    return [
                        'source'      => 'local',
                        'code'        => $row->code,
                        'name'        => $row->name,
                        'description' => $row->description,
                        'image'       => $row->icon_path,
                        'url'         => $row->local_contexts_url,
                    ];
                }
            }
        } catch (\Throwable $e) {
            // fall through
        }

        return [];
    }

    /**
     * Search synced Hub labels (and the local catalog) by free text. Replaces
     * the old stub. Returns [] when the module is off / nothing matches.
     *
     * @return array<int,array<string,mixed>>
     */
    public function query(string $q, array $opts = []): array
    {
        $q = trim($q);
        if ($q === '' || ! $this->isEnabled()) {
            return [];
        }

        $needle = mb_strtolower($q);
        $hits = [];
        try {
            if (Schema::hasTable('icip_hub_project')) {
                foreach (DB::table('icip_hub_project')->pluck('labels_json') as $json) {
                    foreach ((json_decode((string) $json, true) ?: []) as $lbl) {
                        if (! is_array($lbl)) {
                            continue;
                        }
                        $hay = mb_strtolower(($lbl['name'] ?? '').' '.($lbl['label_text'] ?? '').' '.($lbl['label_type'] ?? ''));
                        if (str_contains($hay, $needle)) {
                            $hits[] = $lbl;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('LocalContextsHubService::query error: '.$e->getMessage());
        }

        return $hits;
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /**
     * Flatten the Hub project's tk_labels + bc_labels into one list, each
     * tagged with its family. Tolerant of the exact payload key names.
     *
     * @param  array<string,mixed>  $data
     * @return array<int,array<string,mixed>>
     */
    private function extractLabels(array $data): array
    {
        $out = [];
        foreach (['tk_labels' => 'tk', 'bc_labels' => 'bc'] as $key => $family) {
            foreach ((is_array($data[$key] ?? null) ? $data[$key] : []) as $lbl) {
                if (is_array($lbl)) {
                    $lbl['family'] = $family;
                    $out[] = $lbl;
                }
            }
        }

        return $out;
    }

    /**
     * Ensure the icip_hub_project cache table exists (self-heal). Mirrored in
     * database/core so fresh installs carry it; created here for existing
     * databases that predate it.
     */
    public function ensureTable(): bool
    {
        try {
            if (Schema::hasTable('icip_hub_project')) {
                return true;
            }
            DB::statement(
                'CREATE TABLE IF NOT EXISTS `icip_hub_project` ('
                .'`project_id` varchar(191) NOT NULL,'
                .'`title` varchar(500) DEFAULT NULL,'
                .'`labels_json` longtext,'
                .'`notices_json` longtext,'
                .'`http_status` int DEFAULT NULL,'
                .'`synced_at` datetime DEFAULT NULL,'
                .'`created_at` datetime DEFAULT NULL,'
                .'`updated_at` datetime DEFAULT NULL,'
                .'PRIMARY KEY (`project_id`)'
                .') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            return Schema::hasTable('icip_hub_project');
        } catch (\Throwable $e) {
            Log::warning('LocalContextsHubService::ensureTable error: '.$e->getMessage());

            return false;
        }
    }
}
