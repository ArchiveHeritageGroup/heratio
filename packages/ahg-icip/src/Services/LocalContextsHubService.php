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
 * Hub API contract (confirmed 2026-09-30 against the Hub's own OpenAPI schema
 * at {hub}/api/v2/schema/, "Local Contexts Hub API 2.3.0 (v2)"):
 *  - v2 is the default API since 10 Feb 2025. Every v2 call needs the
 *    account's API key in an `X-Api-Key` header; without it the Hub answers
 *    403 {"detail":"Authentication not provided."}. Project detail is
 *    GET {hub}/api/v2/projects/{unique_id}/. Private projects are never
 *    returned.
 *  - v1 (GET {hub}/api/v1/projects/{unique_id}/) is the legacy, keyless API
 *    and still serves Public projects. It is used only when no key is set.
 *  - Payload: tk_labels[] / bc_labels[] (unique_id, name, label_type,
 *    language_tag, language, label_text, img_url, svg_url, audiofile,
 *    community, translations[], created, updated) and notice[]
 *    (notice_type, name, default_text, img_url, svg_url, translations[]).
 *    Translations are {translated_name, language_tag, language,
 *    translated_text}. `community` is a plain name in v1 and an object
 *    {id, name, profile_url} in v2; both are normalised on sync.
 *  - Production https://localcontextshub.org, sandbox
 *    https://sandbox.localcontextshub.org (separate accounts and keys).
 */
class LocalContextsHubService
{
    /** Cache TTL for a fetched Hub project (seconds). */
    private const CACHE_TTL = 21600; // 6h

    /** HTTP timeout for Hub calls (seconds). */
    private const HTTP_TIMEOUT = 10;

    private const DEFAULT_HUB_URL = 'https://localcontextshub.org';

    /**
     * Local catalog code (icip_tk_label_type.code) => Hub family + label_type.
     * TK and BC share some label_type values (clan, outreach, non_commercial),
     * so the family is part of the match.
     */
    private const LOCAL_CODE_MAP = [
        'tk_a'   => ['tk', 'attribution'],
        'tk_cl'  => ['tk', 'clan'],
        'tk_f'   => ['tk', 'family'],
        'tk_mc'  => ['tk', 'tk_multiple_community'],
        'tk_nc'  => ['tk', 'non_commercial'],
        'tk_o'   => ['tk', 'outreach'],
        'tk_s'   => ['tk', 'secret_sacred'],
        'tk_v'   => ['tk', 'verified'],
        'tk_cs'  => ['tk', 'culturally_sensitive'],
        'tk_cv'  => ['tk', 'community_voice'],
        'tk_co'  => ['tk', 'community_use_only'],
        'tk_wr'  => ['tk', 'women_restricted'],
        'tk_wg'  => ['tk', 'women_general'],
        'tk_mr'  => ['tk', 'men_restricted'],
        'tk_mg'  => ['tk', 'men_general'],
        'tk_ss'  => ['tk', 'seasonal'],
        'bc_p'   => ['bc', 'provenance'],
        'bc_mc'  => ['bc', 'multiple_community'],
        'bc_cl'  => ['bc', 'clan'],
        'bc_cnc' => ['bc', 'non_commercial'],
        'bc_o'   => ['bc', 'outreach'],
        'bc_r'   => ['bc', 'research'],
    ];

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
                    ->retry(1, 200, null, false)
                    ->acceptJson();

                // v2 needs the account key in X-Api-Key; without a key only
                // the legacy keyless v1 API can read Public projects.
                $apiKey = $this->getApiKey();
                if ($apiKey) {
                    $req = $req->withHeaders(['X-Api-Key' => $apiKey]);
                }
                $version = $apiKey ? 'v2' : 'v1';

                $url = $this->hubBaseUrl().'/api/'.$version.'/projects/'.rawurlencode($projectId).'/';
                $resp = $req->get($url);

                if (! $resp->successful()) {
                    $why = match ($resp->status()) {
                        401, 403 => 'API key missing, invalid or not allowed to read this project',
                        404      => 'project not found, or it is Private',
                        default  => 'unexpected response',
                    };
                    Log::warning('LocalContextsHubService: Hub '.$version.' returned HTTP '.$resp->status().' for project '.$projectId.' ('.$why.')');

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
        $notices = [];
        foreach ((is_array($data['notice'] ?? null) ? $data['notice'] : []) as $n) {
            if (is_array($n)) {
                $notices[] = $this->normaliseCommunity($n);
            }
        }

        try {
            $now = Carbon::now();
            DB::table('icip_hub_project')->updateOrInsert(
                ['project_id' => $projectId],
                [
                    'title'        => mb_substr((string) ($data['title'] ?? ''), 0, 500),
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
     * Authoritative metadata for a TK/BC label code. Accepts a local catalog
     * code (tk_a, bc_p) or a Hub label_type, optionally family-prefixed
     * (attribution, tk:attribution). Prefers a synced Hub label (customised
     * name and text, icons, community, translations); falls back to the local
     * icip_tk_label_type catalog so display/gating always work offline.
     * Returns [] only when neither source has the code.
     *
     * @return array<string,mixed>
     */
    public function labelMetadata(string $labelCode): array
    {
        $labelCode = trim($labelCode);
        if ($labelCode === '') {
            return [];
        }

        [$family, $hubType] = self::LOCAL_CODE_MAP[strtolower($labelCode)]
            ?? (str_contains($labelCode, ':') ? explode(':', strtolower($labelCode), 2) : [null, strtolower($labelCode)]);

        // 1. Hub-synced labels (if any project is synced).
        try {
            if ($this->isEnabled() && Schema::hasTable('icip_hub_project')) {
                foreach (DB::table('icip_hub_project')->orderByDesc('synced_at')->get(['project_id', 'labels_json']) as $row) {
                    foreach ((json_decode((string) $row->labels_json, true) ?: []) as $lbl) {
                        if (! is_array($lbl)) {
                            continue;
                        }
                        $type = strtolower((string) ($lbl['label_type'] ?? ''));
                        if ($type === '' || $type !== $hubType) {
                            continue;
                        }
                        if ($family !== null && ($lbl['family'] ?? null) !== $family) {
                            continue;
                        }

                        return [
                            'source'        => 'hub',
                            'code'          => $labelCode,
                            'label_type'    => $type,
                            'family'        => $lbl['family'] ?? null,
                            'name'          => (string) ($lbl['name'] ?? ''),
                            'description'   => (string) ($lbl['label_text'] ?? ''),
                            'language'      => (string) ($lbl['language'] ?? ''),
                            'language_tag'  => (string) ($lbl['language_tag'] ?? ''),
                            'image'         => (string) ($lbl['img_url'] ?? ''),
                            'svg'           => (string) ($lbl['svg_url'] ?? ''),
                            'audio'         => (string) ($lbl['audiofile'] ?? ''),
                            'url'           => (string) ($lbl['label_page'] ?? ''),
                            'community'     => (string) ($lbl['community'] ?? ''),
                            'community_url' => (string) ($lbl['community_profile_url'] ?? ''),
                            'translations'  => is_array($lbl['translations'] ?? null) ? $lbl['translations'] : [],
                            'project_id'    => $row->project_id,
                        ];
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
                        $names = array_column(is_array($lbl['translations'] ?? null) ? $lbl['translations'] : [], 'translated_name');
                        $hay = mb_strtolower(implode(' ', array_merge([
                            $lbl['name'] ?? '', $lbl['label_text'] ?? '', $lbl['label_type'] ?? '', $lbl['community'] ?? '',
                        ], $names)));
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
     * tagged with its family and with `community` normalised to a name.
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
                    $out[] = $this->normaliseCommunity($lbl);
                }
            }
        }

        return $out;
    }

    /**
     * v2 sends `community` as {id, name, profile_url}; v1 sends the name.
     * Store the name in `community` either way, keeping the id + profile URL.
     *
     * @param  array<string,mixed>  $item
     * @return array<string,mixed>
     */
    private function normaliseCommunity(array $item): array
    {
        if (is_array($item['community'] ?? null)) {
            $c = $item['community'];
            $item['community'] = (string) ($c['name'] ?? '');
            $item['community_id'] = $c['id'] ?? null;
            $item['community_profile_url'] = (string) ($c['profile_url'] ?? '');
        }

        return $item;
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
