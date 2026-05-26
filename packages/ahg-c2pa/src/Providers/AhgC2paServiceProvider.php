<?php
/**
 * Heratio - C2PA package wiring.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Providers;

use AhgC2pa\Console\Commands\C2paSmokeCommand;
use AhgC2pa\Console\Commands\C2paVerifyCommand;
use AhgC2pa\Events\AiOutputProduced;
use AhgC2pa\Listeners\WriteC2paSidecar;
use AhgC2pa\Services\C2paService;
use AhgInferenceReceipts\KeyPair;
use AhgInferenceReceipts\Signer as ReceiptSigner;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

final class AhgC2paServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Reuse the inference-receipts Ed25519 key (same kid travels with
        // both the Article 12 chain entries and the C2PA manifests, so a
        // verifier can resolve either through ai_inference_key).
        //
        // We register the singleton ONLY IF nothing else has bound one
        // yet. In production ahg-ai-compliance binds ReceiptSigner first
        // and also registers the kid into ai_inference_key via its
        // KeyResolver. We want to share that one binding, not stomp it.
        $this->app->singletonIf(ReceiptSigner::class, function ($app) {
            $secretPath = function_exists('storage_path')
                ? storage_path('keys/inference-signing.sk')
                : sys_get_temp_dir() . '/c2pa-signing.sk';

            if (!is_readable($secretPath)) {
                $kp = KeyPair::generate();
                $publicPath = function_exists('storage_path')
                    ? storage_path('keys/inference-signing.pk')
                    : sys_get_temp_dir() . '/c2pa-signing.pk';
                $kp->saveTo($secretPath, $publicPath);
                return new ReceiptSigner($kp);
            }
            return new ReceiptSigner(KeyPair::loadFrom($secretPath));
        });

        $this->app->singleton(C2paService::class, function ($app) {
            return new C2paService($app->make(ReceiptSigner::class));
        });
    }

    public function boot(): void
    {
        Event::listen(AiOutputProduced::class, [WriteC2paSidecar::class, 'handle']);

        if ($this->app->runningInConsole()) {
            $this->commands([
                C2paSmokeCommand::class,
                C2paVerifyCommand::class,
            ]);

            try {
                if (!Schema::hasTable('ahg_c2pa_manifest')) {
                    $this->runInstallSqlFile(__DIR__ . '/../../database/install.sql');
                }
            } catch (Throwable) {
                // Boot must never abort. CI runs migrations explicitly;
                // production deploys hit this branch only on first install.
                // See reference_ci_schema_hastable.md for the single-try pattern.
            }
        }
    }

    private function runInstallSqlFile(string $path): void
    {
        $sql = (string) file_get_contents($path);

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
