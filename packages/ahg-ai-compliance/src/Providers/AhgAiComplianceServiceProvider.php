<?php
/**
 * Heratio - EU AI Act Article 12 compliance package wiring.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAiCompliance\Providers;

use AhgAiCompliance\Console\Commands\AnnexIvCommand;
use AhgAiCompliance\Console\Commands\HaltCommand;
use AhgAiCompliance\Console\Commands\InstallKeyCommand;
use AhgAiCompliance\Console\Commands\PruneCommand;
use AhgAiCompliance\Console\Commands\RiskMonitorCommand;
use AhgAiCompliance\Console\Commands\VerifyInferenceLogCommand;
use AhgAiCompliance\Services\AiRiskService;
use AhgAiCompliance\Services\InferenceLogger;
use AhgAiCompliance\Services\KeyResolver;
use AhgAiCompliance\Services\OversightService;
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

        $this->app->singleton(AiRiskService::class, function ($app) {
            return new AiRiskService($app->make(InferenceLogger::class));
        });

        $this->app->singleton(OversightService::class, function ($app) {
            return new OversightService($app->make(InferenceLogger::class));
        });
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-ai-compliance');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallKeyCommand::class,
                VerifyInferenceLogCommand::class,
                PruneCommand::class,
                RiskMonitorCommand::class,
                AnnexIvCommand::class,
                HaltCommand::class,
            ]);

            try {
                if (!Schema::hasTable('ai_inference_log') || !Schema::hasTable('ai_inference_key')) {
                    $this->runInstallSqlFile(__DIR__ . '/../../database/install.sql');
                }
                if (!Schema::hasTable('ai_risk_register') || !Schema::hasTable('ai_risk_incident')) {
                    $this->runInstallSqlFile(__DIR__ . '/../../database/install-risk-register.sql');
                    $this->app->make(AiRiskService::class)->seedIfEmpty();
                }
                if (!Schema::hasTable('ai_model_registry')) {
                    // Issue #725 - EU AI Act Article 11 / Annex IV model registry.
                    // Seed rows are baked into install-model-registry.sql via INSERT IGNORE.
                    $this->runInstallSqlFile(__DIR__ . '/../../database/install-model-registry.sql');
                }
                if (!Schema::hasTable('ai_oversight_policy')
                    || !Schema::hasTable('ai_operator_attestation')
                    || !Schema::hasTable('ai_review_decision')) {
                    // Issue #726 - EU AI Act Article 14 human oversight tables.
                    $this->runInstallSqlFile(__DIR__ . '/../../database/install-oversight.sql');
                    $this->app->make(OversightService::class)->seedIfEmpty();
                }
            } catch (Throwable $e) {
                // Boot must never abort. The CI path covers this via
                // `php artisan migrate`-style smoke after fresh install.
                // See reference_ci_schema_hastable.md for the single-try
                // pattern.
            }
        }
    }

    private function runInstallSqlFile(string $path): void
    {
        $sql = (string) file_get_contents($path);

        // Strip line comments before splitting so stray semicolons inside
        // them do not fracture statements. SQL string literals can still
        // legitimately contain `;` and `--`, but our install files only
        // emit CREATE TABLE / INSERT IGNORE structures that never put
        // either inside a quoted string.
        $lines = preg_split('/\r?\n/', $sql) ?: [];
        $stripped = '';
        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }
            $stripped .= $line . "\n";
        }

        foreach (array_filter(array_map('trim', explode(';', $stripped))) as $stmt) {
            if ($stmt !== '') {
                \DB::statement($stmt);
            }
        }
    }
}
