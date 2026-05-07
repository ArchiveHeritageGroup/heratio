<?php

namespace AhgIcip\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * LocalContextsHubService
 *
 * Minimal stub integration for Local Contexts Hub. Returns empty results
 * unless enabled via icip_config.local_contexts_hub_enabled and an API key
 * is present in icip_config.local_contexts_api_key.
 */
class LocalContextsHubService
{
    public function isEnabled(): bool
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('icip_config')) {
                return false;
            }
            $val = DB::table('icip_config')->where('config_key', 'local_contexts_hub_enabled')->value('config_value');
            return (int) $val === 1;
        } catch (\Throwable $e) {
            Log::warning('LocalContextsHubService::isEnabled error: ' . $e->getMessage());
            return false;
        }
    }

    public function getApiKey(): ?string
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('icip_config')) {
                return null;
            }
            return DB::table('icip_config')->where('config_key', 'local_contexts_api_key')->value('config_value') ?: null;
        } catch (\Throwable $e) {
            Log::warning('LocalContextsHubService::getApiKey error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Query the Local Contexts Hub — stub implementation.
     * Returns an empty array unless isEnabled() and api key present.
     */
    public function query(string $q, array $opts = []): array
    {
        if (!$this->isEnabled()) {
            return [];
        }
        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            return [];
        }
        // Real integration would call external API here. For now return empty.
        return [];
    }
}
