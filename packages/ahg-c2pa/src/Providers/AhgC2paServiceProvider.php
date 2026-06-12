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
use AhgC2pa\Console\Commands\C2paReverifyCommand;
use AhgC2pa\Console\Commands\C2paSmokeCommand;
use AhgC2pa\Console\Commands\C2paVerifyCommand;
use AhgC2pa\Controllers\AuthenticityController;
use AhgC2pa\Events\AiOutputProduced;
use AhgC2pa\Listeners\RecordDigitalObjectProvenance;
use AhgC2pa\Listeners\WriteC2paSidecar;
use AhgC2pa\Middleware\InjectContentCredentialsBadge;
use AhgC2pa\Controllers\TransparencyController;
use AhgC2pa\Controllers\TrustController;
use AhgC2pa\Controllers\VerifiedRecordsController;
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
        // Public, single-segment "Content Credentials" explainer / trust page
        // (deepens #1209 / #1201). Unlike the multi-segment /verify/... routes
        // (which the single-segment IO slug catch-all /{slug} can never match),
        // /content-credentials IS a single segment and is NOT in that catch-
        // all's exclusion list, so a route declared in this package's boot()
        // web.php would be SHADOWED by the catch-all depending on provider boot
        // order. We therefore register it here, in register(), via
        // callAfterResolving('router'): this defines the route during the
        // register phase - before the IO package loads its catch-all in boot()
        // - so Laravel's first-match-wins resolution always picks this route.
        // (Same pattern as the z3950 / sru top-level routes in
        // AppServiceProvider; see reference_slug_catchall_route_precedence.md.)
        // Read-only, no auth: this is public trust-anchor copy.
        $this->callAfterResolving('router', function ($router) {
            $router->middleware('web')
                ->get('/content-credentials', [AuthenticityController::class, 'explainer'])
                ->name('c2pa.explainer');
        });

        // Public, single-segment "Trust at a glance" collection-wide TRUST
        // DASHBOARD (issue #1209, north star). The same single-segment problem
        // as /content-credentials applies: /trust and /trust.json are NOT in the
        // IO slug catch-all's (/{slug}) exclusion list, so declaring them in
        // boot() web.php would risk the catch-all shadowing them depending on
        // provider boot order. Registering here, in register(), via
        // callAfterResolving('router') defines them BEFORE the IO package loads
        // its catch-all in boot(), so first-match-wins resolution always picks
        // these. (.json keeps its real extension - nginx passes *.json through
        // to Laravel - and is declared first so it can never be captured as a
        // slug.) Read-only, public, no auth: this is the public trust summary.
        $this->callAfterResolving('router', function ($router) {
            $router->middleware('web')
                ->get('/trust.json', [TrustController::class, 'json'])
                ->name('c2pa.trust.json');
            $router->middleware('web')
                ->get('/trust', [TrustController::class, 'index'])
                ->name('c2pa.trust');
        });

        // Public, single-segment BROWSE of provenance-verified records (issue
        // #1209, north star): the walkable verifiable corpus - the PUBLISHED
        // records that actually carry content credentials, paginated, each
        // linking to its /authenticity/{id} report. Same single-segment problem
        // as /trust and /content-credentials: /verified-records and
        // /verified-records.json are NOT in the IO slug catch-all's (/{slug})
        // exclusion list, so declaring them in boot() web.php would risk the
        // catch-all shadowing them depending on provider boot order. Registering
        // here, in register(), via callAfterResolving('router') defines them
        // BEFORE the IO package loads its catch-all in boot(), so first-match-
        // wins resolution always picks these. The .json companion keeps its real
        // extension (nginx passes *.json through to Laravel), is CORS-open, and
        // is declared FIRST so the literal '.json' can never be captured as a
        // slug. Read-only, public, no auth: this is the public verifiable-corpus
        // index.
        $this->callAfterResolving('router', function ($router) {
            $router->middleware('web')
                ->get('/verified-records.json', [VerifiedRecordsController::class, 'json'])
                ->name('c2pa.verified.records.json');
            $router->middleware('web')
                ->get('/verified-records', [VerifiedRecordsController::class, 'index'])
                ->name('c2pa.verified.records');
        });

        // Public, single-segment catalogue-wide TRANSPARENCY REPORT (issue
        // #1209): the PUBLIC counterpart to the operator-only admin trust console
        // (/admin/trust-console) and to the per-record trust dossier
        // (/trust-dossier). It rolls the whole PUBLISHED catalogue up into five
        // honest dimensions (content credentials, AI provenance, integrity,
        // preservation, accessibility), each a headline number + share, with an
        // honest framing that never overclaims and shows gaps as gaps. Same
        // single-segment problem as /trust, /verified-records and
        // /content-credentials: /transparency and /transparency.json are NOT in
        // the IO slug catch-all's (/{slug}) exclusion list, so declaring them in
        // boot() web.php would risk the catch-all shadowing them depending on
        // provider boot order. Registering here, in register(), via
        // callAfterResolving('router') defines them BEFORE the IO package loads
        // its catch-all in boot(), so first-match-wins resolution always picks
        // these. The .json companion keeps its real extension (nginx passes
        // *.json through to Laravel), is CORS-open, and is declared FIRST so the
        // literal '.json' can never be captured as a slug. Read-only, public, no
        // auth: this is the public, catalogue-wide transparency scorecard.
        $this->callAfterResolving('router', function ($router) {
            $router->middleware('web')
                ->get('/transparency.json', [TransparencyController::class, 'json'])
                ->name('c2pa.transparency.json');
            $router->middleware('web')
                ->get('/transparency', [TransparencyController::class, 'index'])
                ->name('c2pa.transparency');
        });

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
                C2paReverifyCommand::class,
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
