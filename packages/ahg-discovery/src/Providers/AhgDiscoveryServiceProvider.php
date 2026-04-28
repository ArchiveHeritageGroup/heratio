<?php

/**
 * AhgDiscoveryServiceProvider
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgDiscovery\Providers;

use AhgDiscovery\Services\DiscoveryQueryLogger;
use AhgDiscovery\Services\Search\ImageSearchStrategy;
use AhgDiscovery\Services\Search\VectorSearchStrategy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AhgDiscoveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DiscoveryQueryLogger::class);
        $this->app->singleton(VectorSearchStrategy::class);
        $this->app->singleton(ImageSearchStrategy::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'discovery');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-discovery');

        $this->seedDefaultSettings();
    }

    /**
     * Idempotent first-boot seed of Discovery default settings into ahg_settings.
     * Uses INSERT IGNORE on (setting_key) so re-running is a no-op.
     */
    protected function seedDefaultSettings(): void
    {
        try {
            if (! Schema::hasTable('ahg_settings')) {
                return;
            }

            $defaults = [
                // Pipeline-wide
                'ahg_discovery_enabled'              => '1',
                'ahg_discovery_cache_ttl'            => '3600',
                'ahg_discovery_expansion_limit'      => '5',
                'ahg_discovery_keyword_pool_size'    => '100',
                'ahg_discovery_entity_pool_size'     => '200',
                'ahg_discovery_hierarchical_top_n'   => '20',
                'ahg_discovery_max_results'          => '100',
                // Vector strategy (Qdrant + Ollama embeddings)
                'ahg_discovery_vector_enabled'       => '1',
                'ahg_discovery_vector_min_score'     => '0.25',
                'ahg_discovery_vector_pool_size'     => '100',
                // Image strategy (Qdrant CLIP)
                'ahg_discovery_image_enabled'        => '1',
                'ahg_discovery_image_min_score'      => '0.30',
                'ahg_discovery_image_pool_size'      => '50',
                'ahg_discovery_image_collection'     => 'archive_images',
                'ahg_discovery_image_embed_url'      => 'http://192.168.0.78:11434',
                'ahg_discovery_image_embed_model'    => 'clip-vit-b-32',
                // Logging — always on; turn off via this flag if too chatty
                'ahg_discovery_log_queries'          => '1',
            ];

            $existingKeys = DB::table('ahg_settings')
                ->whereIn('setting_key', array_keys($defaults))
                ->pluck('setting_key')
                ->all();
            $missing = array_diff(array_keys($defaults), $existingKeys);
            if (empty($missing)) {
                return;
            }

            $rows = [];
            foreach ($missing as $key) {
                $rows[] = [
                    'setting_key'   => $key,
                    'setting_value' => $defaults[$key],
                ];
            }
            DB::table('ahg_settings')->insertOrIgnore($rows);
        } catch (Throwable $e) {
            // Never block boot.
        }
    }
}
