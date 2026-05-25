<?php
/**
 * Heratio - EU AI Act Article 12 compliance package wiring.
 *
 * @copyright Copyright (c) 2026, The Archive and Heritage Group (Pty) Ltd
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAiCompliance\Providers;

use AhgAiCompliance\Console\Commands\InstallKeyCommand;
use AhgAiCompliance\Console\Commands\PruneCommand;
use AhgAiCompliance\Console\Commands\VerifyInferenceLogCommand;
use AhgAiCompliance\Services\InferenceLogger;
use AhgAiCompliance\Services\KeyResolver;
use AhgAiCompliance\Storage\EloquentChainStore;
use AhgInferenceReceipts\KeyPair;
use AhgInferenceReceipts\ReceiptChain;
use AhgInferenceReceipts\Signer;
use AhgInferenceReceipts\Storage\ChainStore;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

final class AhgAiComplianceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(KeyResolver::class, fn ($app) => new KeyResolver());

        $this->app->singleton(ChainStore::class, function ($app) {
            return new EloquentChainStore();
        });

        $this->app->singleton(Signer::class, function ($app) {
            $secretPath = storage_path('keys/inference-signing.sk');
            if (!is_readable($secretPath)) {
                $kp = KeyPair::generate();
                $kp->saveTo($secretPath, storage_path('keys/inference-signing.pk'));
                $app->make(KeyResolver::class)->register($kp->kid(), $kp->publicKey(), active: true);
                return new Signer($kp);
            }
            return new Signer(KeyPair::loadFrom($secretPath));
        });

        $this->app->singleton(ReceiptChain::class, function ($app) {
            $store = $app->make(ChainStore::class);
            $signer = $app->make(Signer::class);
            $resolver = $app->make(KeyResolver::class);

            return new ReceiptChain(
                $store,
                $signer,
                static fn (string $kid): ?string => $resolver->publicKey($kid),
            );
        });

        $this->app->singleton(InferenceLogger::class, function ($app) {
            return new InferenceLogger($app->make(ReceiptChain::class));
        });
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallKeyCommand::class,
                VerifyInferenceLogCommand::class,
                PruneCommand::class,
            ]);

            try {
                if (!Schema::hasTable('ai_inference_log') || !Schema::hasTable('ai_inference_key')) {
                    $sql = (string) file_get_contents(__DIR__ . '/../../database/install.sql');
                    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                        if ($stmt !== '') {
                            \DB::statement($stmt);
                        }
                    }
                }
            } catch (Throwable $e) {
                // Boot must never abort. The CI path covers this via
                // `php artisan migrate`-style smoke after fresh install.
                // See reference_ci_schema_hastable.md for the single-try
                // pattern.
            }
        }
    }
}
