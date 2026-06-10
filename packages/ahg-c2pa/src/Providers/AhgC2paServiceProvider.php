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

use AhgC2pa\Console\Commands\C2paEmbedCommand;
use AhgC2pa\Console\Commands\C2paProvenanceBackfillCommand;
use AhgC2pa\Console\Commands\C2paSmokeCommand;
use AhgC2pa\Console\Commands\C2paVerifyCommand;
use AhgC2pa\Events\AiOutputProduced;
use AhgC2pa\Listeners\RecordDigitalObjectProvenance;
use AhgC2pa\Listeners\WriteC2paSidecar;
use AhgC2pa\Middleware\InjectContentCredentialsBadge;
use AhgC2pa\Services\C2paService;
use AhgC2pa\Services\DigitalObjectProvenanceService;
use AhgC2pa\Services\ProvenanceRecordService;
use AhgInferenceReceipts\KeyPair;
use AhgInferenceReceipts\Signer as ReceiptSigner;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
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

        $this->app->singleton(ProvenanceRecordService::class, function ($app) {
            return new ProvenanceRecordService($app->make(C2paService::class));
        });

        // Ingest -> provenance bridge: turns a freshly-created digital object
        // into a signed digitisation-provenance record. Shared by the Eloquent
        // listener and the ahg:c2pa-provenance-backfill command (issue #1201).
        $this->app->singleton(DigitalObjectProvenanceService::class, function ($app) {
            return new DigitalObjectProvenanceService($app->make(ProvenanceRecordService::class));
        });
    }

    public function boot(): void
    {
        Event::listen(AiOutputProduced::class, [WriteC2paSidecar::class, 'handle']);

        // Auto-provenance at ingest (issue #1201): record a signed C2PA
        // digitisation-provenance entry whenever a digital object is created
        // through the Eloquent model. NOTE: every *live* upload path (HTTP
        // upload, the ingest wizard, the scanner) writes the row with a raw
        // DB::table('digital_object')->insert(), which fires no Eloquent event;
        // this listener is therefore a forward-compatible safety net for any
        // model-based create path. Authoritative coverage of the raw-insert
        // paths is the ahg:c2pa-provenance-backfill command below. Both share
        // DigitalObjectProvenanceService and are idempotent, so a digital
        // object can never be double-recorded. The Eloquent "created" event is
        // dispatched under the string key "eloquent.created: <model FQCN>".
        Event::listen(
            'eloquent.created: AhgCore\\Models\\DigitalObject',
            [RecordDigitalObjectProvenance::class, 'handle'],
        );

        // HTTP layer (issue #1201): provenance / content-credentials views.
        // Routes mount under /admin/c2pa, which the IO slug catch-all leaves
        // alone (the catch-all only matches single-segment paths).
        Route::middleware('web')->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-c2pa');

        // Content Credentials "Verify" badge on the GLAM sector show pages
        // (issue #1201). Injected via response middleware so the locked show
        // blades are never edited - same pattern as AhgCore's InjectSplatViewer.
        // Registered in booted() so it runs AFTER the HTTP kernel syncs its
        // middleware groups (a direct push during boot would be overwritten by
        // the kernel's web-group definition).
        $this->app->booted(function () {
            $this->app['router']->pushMiddlewareToGroup('web', InjectContentCredentialsBadge::class);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                C2paSmokeCommand::class,
                C2paVerifyCommand::class,
                C2paEmbedCommand::class,
                C2paProvenanceBackfillCommand::class,
            ]);
        }

        // Schema install. Wrapped in a SINGLE outer try so the hasTable()
        // probe and the install both share one catch (see
        // reference_ci_schema_hastable.md). Boot must never abort.
        try {
            if (!Schema::hasTable('ahg_c2pa_manifest')) {
                $this->runInstallSqlFile(__DIR__ . '/../../database/install.sql');
            }
            if (!Schema::hasTable('ahg_c2pa_provenance')) {
                $this->runInstallSqlFile(__DIR__ . '/../../database/install_provenance.sql');
            }
        } catch (Throwable) {
            // CI runs migrations explicitly; production deploys hit this
            // branch only on first install or when the DB is unreachable.
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
