<?php

namespace AhgSemanticSearch\Providers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgSemanticSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // heratio#1210 - public "Discoveries" surface. Registered here in
        // register() (not boot()) via callAfterResolving('router') so it binds
        // BEFORE the single-segment /{slug} archival-record catch-all in
        // ahg-information-object-manage. That package's composer name sorts
        // before this one, so its boot() (which registers the catch-all) runs
        // ahead of our boot(); but register() runs for ALL providers before ANY
        // boot(), so this route wins the match for the single-segment public
        // path /discoveries. See memory/reference_slug_catchall_route_precedence.md.
        $this->callAfterResolving('router', function ($router) {
            $router->middleware('web')
                ->get('/discoveries', [
                    \AhgSemanticSearch\Controllers\DiscoveriesController::class, 'index',
                ])
                ->name('scholarship.discoveries');

            // heratio#1210 - detail view for a single stored discovery. Numeric
            // constraint so it never shadows the /discoveries index or a slug.
            $router->middleware('web')
                ->get('/discoveries/{id}', [
                    \AhgSemanticSearch\Controllers\DiscoveriesController::class, 'show',
                ])
                ->where('id', '[0-9]+')
                ->name('scholarship.discoveries.show');

            // heratio#1207 - public "Displaced heritage register" surface.
            // Single-segment public path, registered the same way as
            // /discoveries (register() + callAfterResolving('router')) so it
            // binds BEFORE the single-segment /{slug} archival-record catch-all
            // in ahg-information-object-manage. See the note above and
            // memory/reference_slug_catchall_route_precedence.md.
            $router->middleware('web')
                ->get('/displaced-heritage', [
                    \AhgSemanticSearch\Controllers\DisplacedHeritageRegisterController::class, 'index',
                ])
                ->name('displaced-heritage.index');

            // heratio#1207 - per-item detail for one traced object. Numeric
            // constraint so it never shadows the /displaced-heritage index or a
            // single-segment archival-record slug. Bound the same way
            // (register() + callAfterResolving('router')) so it wins the match
            // ahead of the /{slug} catch-all in ahg-information-object-manage.
            $router->middleware('web')
                ->get('/displaced-heritage/{id}', [
                    \AhgSemanticSearch\Controllers\DisplacedHeritageRegisterController::class, 'show',
                ])
                ->where('id', '[0-9]+')
                ->name('displaced-heritage.show');

            // heratio#1207 - public "virtual return" surface. One repatriation
            // claim rendered as a respectful virtual return: the object shown in
            // its ORIGIN context (origin place + claimant community + a record
            // link for PUBLISHED items only). Two-segment path with a numeric
            // {id} so it can never shadow the single-segment /{slug} archival-
            // record catch-all, but bound here (register() +
            // callAfterResolving('router')) for the same precedence guarantee as
            // the routes above. See
            // memory/reference_slug_catchall_route_precedence.md.
            $router->middleware('web')
                ->get('/virtual-return/{id}', [
                    \AhgSemanticSearch\Controllers\VirtualReturnController::class, 'show',
                ])
                ->where('id', '[0-9]+')
                ->name('virtual-return.show');

            // heratio#1207 - PUBLIC repatriation dashboard. A read-only aggregate
            // VIEW over the claims register (counts by status, top origin places /
            // communities, virtual-return vs physically-returned split, recent
            // activity), each row linking onward to a claim's /virtual-return/{id}
            // page. Single-segment public path, registered the same way as
            // /discoveries, /at-risk and /displaced-heritage (register() +
            // callAfterResolving('router')) so it binds BEFORE the single-segment
            // /{slug} archival-record catch-all in ahg-information-object-manage.
            // See memory/reference_slug_catchall_route_precedence.md.
            $router->middleware('web')
                ->get('/repatriation', [
                    \AhgSemanticSearch\Controllers\RepatriationDashboardController::class, 'index',
                ])
                ->name('repatriation.dashboard');

            // heratio#1207 - machine-readable twin of the dashboard. The .json
            // suffix keeps it a distinct single-segment path that can never shadow
            // a slug; CORS-open public read-only data. Bound here for the same
            // catch-all precedence guarantee as the HTML dashboard above.
            $router->middleware('web')
                ->get('/repatriation.json', [
                    \AhgSemanticSearch\Controllers\RepatriationDashboardController::class, 'json',
                ])
                ->name('repatriation.dashboard.json');

            // heratio#1205 - North Star "race against loss": the PUBLIC, read-only
            // "at risk" register of endangered heritage (PUBLISHED items only),
            // most-urgent first, framing why heritage is endangered and the race
            // to capture it. Single-segment public path, registered the same way
            // as /discoveries and /displaced-heritage (register() +
            // callAfterResolving('router')) so it binds BEFORE the single-segment
            // /{slug} archival-record catch-all in ahg-information-object-manage.
            // See memory/reference_slug_catchall_route_precedence.md.
            $router->middleware('web')
                ->get('/at-risk', [
                    \AhgSemanticSearch\Controllers\EndangeredHeritageController::class, 'register',
                ])
                ->name('endangered.register');

            // heratio#1210 - North Star "generative scholarship": the PUBLIC,
            // read-only "Research Leads" feed. The strongest AI-found cross-
            // collection connections (from the Discoveries feature) promoted by
            // curators into browsable scholarly leads, PUBLISHED items only.
            // Single-segment public path, registered the same way as
            // /discoveries and /at-risk (register() + callAfterResolving('router'))
            // so it binds BEFORE the single-segment /{slug} archival-record catch-
            // all in ahg-information-object-manage. See
            // memory/reference_slug_catchall_route_precedence.md.
            $router->middleware('web')
                ->get('/research-leads', [
                    \AhgSemanticSearch\Controllers\ResearchLeadsController::class, 'index',
                ])
                ->name('research-leads.index');

            // heratio#1210 - per-lead public detail. Numeric {id} constraint so it
            // can never shadow the /research-leads index or a single-segment
            // archival-record slug; bound here for the same precedence guarantee
            // as the index above.
            $router->middleware('web')
                ->get('/research-leads/{id}', [
                    \AhgSemanticSearch\Controllers\ResearchLeadsController::class, 'show',
                ])
                ->where('id', '[0-9]+')
                ->name('research-leads.show');

            // heratio#1208 - North Star "a culture you can talk to": public,
            // read-only language-revival corpus. Single-segment /language-corpus
            // bound here (register() + callAfterResolving) to win ahead of the
            // /{slug} catch-all; per-culture views + POSTs follow (culture-code
            // constrained, so they never shadow a slug).
            $router->middleware('web')
                ->get('/language-corpus', [
                    \AhgSemanticSearch\Controllers\LanguageCorpusController::class, 'index',
                ])
                ->name('language-corpus.index');
            $router->middleware('web')
                ->get('/language-corpus/{culture}', [
                    \AhgSemanticSearch\Controllers\LanguageCorpusController::class, 'show',
                ])
                ->where('culture', '[A-Za-z]{2,3}([-_@][A-Za-z0-9]+)*')
                ->name('language-corpus.show');
            $router->middleware('web')
                ->post('/language-corpus/{culture}/contribute', [
                    \AhgSemanticSearch\Controllers\LanguageCorpusController::class, 'contribute',
                ])
                ->where('culture', '[A-Za-z]{2,3}([-_@][A-Za-z0-9]+)*')
                ->name('language-corpus.contribute');
            $router->middleware('web')
                ->post('/language-corpus/{culture}/translate', [
                    \AhgSemanticSearch\Controllers\LanguageCorpusController::class, 'translate',
                ])
                ->where('culture', '[A-Za-z]{2,3}([-_@][A-Za-z0-9]+)*')
                ->name('language-corpus.translate');

            // heratio#1208 - North Star "a culture you can talk to": community
            // TRANSCRIPTION / correction / translation contributions on a
            // published item. The public submit form + POST handler are bound
            // here (register() + callAfterResolving) for the same precedence
            // guarantee as the corpus routes above. {item} is numeric-only, so a
            // two-segment path like /language-transcribe/553 can never shadow the
            // single-segment /{slug} archival-record catch-all in
            // ahg-information-object-manage. See
            // memory/reference_slug_catchall_route_precedence.md.
            $router->middleware('web')
                ->get('/language-transcribe/{item}', [
                    \AhgSemanticSearch\Controllers\LanguageTranscriptionController::class, 'form',
                ])
                ->where('item', '[0-9]+')
                ->name('language-transcribe.form');
            $router->middleware('web')
                ->post('/language-transcribe/{item}', [
                    \AhgSemanticSearch\Controllers\LanguageTranscriptionController::class, 'contribute',
                ])
                ->where('item', '[0-9]+')
                ->name('language-transcribe.contribute');
        });
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-semantic-search');

        // heratio#1210 - persistence layer for generative scholarship. The
        // curated discovery set is stored in ahg_scholarship_discovery so the
        // public page can render stable, browsable, citable discoveries instead
        // of regenerating them on every load. Auto-created on first boot, the
        // canonical Heratio package idiom (Schema::hasTable probe wrapped in a
        // single try/catch so a fresh boot never fatals - see the CI rule in
        // memory/reference_ci_schema_hastable.md).
        $this->bootScholarshipDiscoveryTable();

        // heratio#1207 - repatriation-claim / virtual-return workflow. The
        // structured claim records that sit on top of the displaced-heritage
        // register live in displaced_heritage_claim. Auto-created on first boot
        // behind a Schema::hasTable probe in a single try/catch (the canonical
        // package idiom - never fatal a fresh boot; see
        // memory/reference_ci_schema_hastable.md).
        $this->bootRepatriationClaimTable();

        // heratio#1205 - North Star "race against loss": endangered-heritage
        // register + capture-priority list. The at-risk flags live in
        // endangered_heritage_item. Auto-created on first boot behind a
        // Schema::hasTable probe in a single try/catch (the canonical package
        // idiom - never fatal a fresh boot; see
        // memory/reference_ci_schema_hastable.md).
        $this->bootEndangeredHeritageTable();

        // heratio#1210 - North Star "generative scholarship": the public
        // "Research Leads" feed. Curated leads (promoted from the persisted
        // Discoveries set) live in research_lead. Auto-created on first boot
        // behind a Schema::hasTable probe in a single try/catch (the canonical
        // package idiom - never fatal a fresh boot; see
        // memory/reference_ci_schema_hastable.md).
        $this->bootResearchLeadTable();

        // heratio#1208 - community-contributed glossary behind the public
        // language-revival corpus pages lives in language_revival_glossary.
        // Auto-created on first boot (Schema::hasTable probe in one try/catch).
        $this->bootLanguageGlossaryTable();

        // heratio#1208 - community TRANSCRIPTION / correction / translation
        // contributions on heritage-language items live in
        // language_transcription_contribution. Auto-created on first boot
        // (Schema::hasTable probe in one try/catch).
        $this->bootLanguageTranscriptionTable();

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgSemanticSearch\Console\Commands\KmGraphSyncCommand::class,
                \AhgSemanticSearch\Console\Commands\KmExportGraphCommand::class,
                \AhgSemanticSearch\Console\Commands\ScholarshipDiscoverCommand::class,
                \AhgSemanticSearch\Console\Commands\GenerateDiscoveriesCommand::class,
                \AhgSemanticSearch\Console\Commands\GenerateResearchLeadsCommand::class,
                \AhgSemanticSearch\Console\Commands\DisplacedHeritageScanCommand::class,
            ]);
        }
    }

    /**
     * Idempotent first-boot creation of the research_lead table.
     *
     * One row per promoted research lead: the connection's centre record (a SOFT
     * reference - NO foreign key - so this additive table never constrains or
     * ALTERs the existing catalogue), the plain-language "why it matters" prompt,
     * the AI lead text, a JSON snapshot of the evidence, and a curation status
     * (status is VARCHAR, never an ENUM). Only PUBLISHED leads with published
     * records reach the public feed.
     *
     * Prefers the shipped install SQL (database/install_research_lead.sql) so the
     * table definition has one source of truth; falls back to a Schema builder
     * create if the SQL file is unreadable. The whole thing is wrapped in a single
     * try/catch so a missing / locked DB at boot can never fatal the app - the
     * service and controllers degrade to the empty-state when the table is absent.
     */
    protected function bootResearchLeadTable(): void
    {
        try {
            if (Schema::hasTable('research_lead')) {
                return;
            }

            $sqlPath = __DIR__.'/../../database/install_research_lead.sql';
            $ran = false;
            if (is_readable($sqlPath)) {
                $sql = (string) file_get_contents($sqlPath);
                if (trim($sql) !== '') {
                    \Illuminate\Support\Facades\DB::unprepared($sql);
                    $ran = true;
                }
            }

            // Fallback: build the table via the Schema builder if the SQL file was
            // not available, so a fresh install still gets the table.
            if (! $ran && ! Schema::hasTable('research_lead')) {
                Schema::create('research_lead', function (Blueprint $table) {
                    $table->bigIncrements('id');
                    $table->unsignedBigInteger('information_object_id');
                    $table->unsignedBigInteger('source_discovery_id')->nullable();
                    $table->string('headline', 1024)->nullable();
                    $table->text('lead_text')->nullable();
                    $table->text('why_it_matters')->nullable();
                    $table->unsignedInteger('connection_count')->default(0);
                    $table->unsignedSmallInteger('confidence')->default(0);
                    $table->json('evidence')->nullable();
                    $table->string('status', 32)->default('pending');
                    $table->boolean('ai_labelled')->default(true);
                    $table->unsignedBigInteger('curated_by')->nullable();
                    $table->timestamp('generated_at')->nullable();
                    $table->timestamp('published_at')->nullable();
                    $table->timestamps();

                    $table->unique('information_object_id', 'uq_research_lead_io');
                    $table->index('status', 'ix_research_lead_status');
                    $table->index(['status', 'confidence'], 'ix_research_lead_status_conf');
                    $table->index('source_discovery_id', 'ix_research_lead_source');
                });
            }

            Log::info('ahg-semantic-search: research_lead created (first-boot)');
        } catch (\Throwable $e) {
            // Never block boot on install failure - log and continue. The feed
            // degrades to the empty-state when the table is absent.
            Log::warning('ahg-semantic-search research-lead boot install skipped: '.$e->getMessage());
        }
    }

    /**
     * Idempotent first-boot creation of the persisted-discovery table.
     *
     * One row per curated information object holds the AI-surfaced discovery
     * (title, lead/summary, confidence, and a JSON snapshot of the evidence the
     * lead rests on) so the public Discoveries page can read a stable, fast,
     * citable set rather than recomputing on every request. The whole probe is
     * wrapped in a single try/catch so a missing/locked DB at boot can never
     * fatal the app - the controller falls back to on-demand generation.
     */
    /**
     * heratio#1208 - idempotent first-boot creation of language_revival_glossary
     * (community glossary; VARCHAR moderation_status, soft refs, no FK/ALTER).
     * Never fatal a fresh boot.
     */
    protected function bootLanguageGlossaryTable(): void
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('language_revival_glossary')) {
                return;
            }
            $sqlPath = __DIR__.'/../../database/install_language_glossary.sql';
            if (is_readable($sqlPath)) {
                $sql = (string) file_get_contents($sqlPath);
                if (trim($sql) !== '') {
                    \Illuminate\Support\Facades\DB::unprepared($sql);
                }
            }
        } catch (\Throwable $e) {
            // DB not ready / install hiccup - retries next boot.
        }
    }

    /**
     * heratio#1208 - idempotent first-boot creation of
     * language_transcription_contribution (community transcriptions / corrections
     * / translations; VARCHAR contribution_type + moderation_status, soft refs, no
     * FK/ALTER). Prefers the shipped install SQL so the table definition has one
     * source of truth; falls back to a Schema builder create if the SQL file is
     * unreadable. Wrapped in a single try/catch so a missing / locked DB at boot
     * can never fatal the app - the service and controllers degrade to the
     * empty-state when the table is absent.
     */
    protected function bootLanguageTranscriptionTable(): void
    {
        try {
            if (Schema::hasTable('language_transcription_contribution')) {
                return;
            }

            $sqlPath = __DIR__.'/../../database/install_language_transcription.sql';
            $ran = false;
            if (is_readable($sqlPath)) {
                $sql = (string) file_get_contents($sqlPath);
                if (trim($sql) !== '') {
                    \Illuminate\Support\Facades\DB::unprepared($sql);
                    $ran = true;
                }
            }

            // Fallback: build the table via the Schema builder if the SQL file was
            // not available, so a fresh install still gets the table.
            if (! $ran && ! Schema::hasTable('language_transcription_contribution')) {
                Schema::create('language_transcription_contribution', function (Blueprint $table) {
                    $table->bigIncrements('id');
                    $table->unsignedBigInteger('item_ref')->nullable();
                    $table->string('culture', 16);
                    $table->string('contribution_type', 32)->default('transcription');
                    $table->mediumText('body');
                    $table->string('source', 512)->nullable();
                    $table->unsignedBigInteger('contributed_by')->nullable();
                    $table->string('contributor_name', 255)->nullable();
                    $table->boolean('credit_consent')->default(false);
                    $table->string('moderation_status', 32)->default('pending');
                    $table->unsignedBigInteger('moderated_by')->nullable();
                    $table->timestamp('moderated_at')->nullable();
                    $table->timestamps();

                    $table->index('culture', 'ix_ltc_culture');
                    $table->index('item_ref', 'ix_ltc_item_ref');
                    $table->index('moderation_status', 'ix_ltc_status');
                    $table->index(['item_ref', 'moderation_status'], 'ix_ltc_item_status');
                    $table->index(['culture', 'moderation_status'], 'ix_ltc_culture_status');
                });
            }

            Log::info('ahg-semantic-search: language_transcription_contribution created (first-boot)');
        } catch (\Throwable $e) {
            // Never block boot on install failure - log and continue. The
            // contribution surfaces degrade to the empty-state when absent.
            Log::warning('ahg-semantic-search language-transcription boot install skipped: '.$e->getMessage());
        }
    }

    protected function bootScholarshipDiscoveryTable(): void
    {
        try {
            if (Schema::hasTable('ahg_scholarship_discovery')) {
                return;
            }

            Schema::create('ahg_scholarship_discovery', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('information_object_id');
                $table->string('title', 1024)->nullable();
                $table->text('summary')->nullable();
                $table->unsignedInteger('connection_count')->default(0);
                $table->unsignedSmallInteger('confidence')->default(0);
                $table->json('evidence')->nullable();
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();

                $table->unique('information_object_id', 'uq_scholarship_discovery_io');
                $table->index(['information_object_id', 'confidence'], 'ix_scholarship_discovery_io_conf');
            });

            Log::info('ahg-semantic-search: ahg_scholarship_discovery created (first-boot)');
        } catch (\Throwable $e) {
            // Never block boot on install failure - log and continue. The
            // Discoveries page degrades to on-demand generation when the table
            // is absent.
            Log::warning('ahg-semantic-search scholarship-discovery boot install skipped: '.$e->getMessage());
        }
    }

    /**
     * Idempotent first-boot creation of the repatriation-claim table.
     *
     * One row per claim holds the structured workflow record that sits on top of
     * a displaced-heritage item: who is claiming it, its place of origin, the
     * current holder, where the claim stands (claim_status, a VARCHAR - never an
     * ENUM), the documented evidence summary, and curatorial notes. item_ref is a
     * soft reference to the information_object id (NO foreign key) so this
     * additive table never constrains or ALTERs the existing catalogue.
     *
     * Prefers the shipped install SQL (database/install_repatriation_claim.sql)
     * so the table definition has one source of truth; falls back to a Schema
     * builder create if the SQL file is unreadable. The whole thing is wrapped in
     * a single try/catch so a missing / locked DB at boot can never fatal the
     * app - the service and controllers degrade to the empty-state when the table
     * is absent.
     */
    protected function bootRepatriationClaimTable(): void
    {
        try {
            if (Schema::hasTable('displaced_heritage_claim')) {
                return;
            }

            $sqlPath = __DIR__.'/../../database/install_repatriation_claim.sql';
            $ran = false;
            if (is_readable($sqlPath)) {
                $sql = (string) file_get_contents($sqlPath);
                if (trim($sql) !== '') {
                    \Illuminate\Support\Facades\DB::unprepared($sql);
                    $ran = true;
                }
            }

            // Fallback: build the table via the Schema builder if the SQL file
            // was not available, so a fresh install still gets the table.
            if (! $ran && ! Schema::hasTable('displaced_heritage_claim')) {
                Schema::create('displaced_heritage_claim', function (Blueprint $table) {
                    $table->bigIncrements('id');
                    $table->unsignedBigInteger('item_ref');
                    $table->string('claimant_community', 512)->nullable();
                    $table->string('origin_place', 512)->nullable();
                    $table->string('current_holder', 512)->nullable();
                    $table->string('claim_status', 64)->default('registered');
                    $table->text('evidence_summary')->nullable();
                    $table->string('contact', 512)->nullable();
                    $table->text('notes')->nullable();
                    $table->unsignedBigInteger('created_by')->nullable();
                    $table->timestamps();

                    $table->index('item_ref', 'ix_dhc_item_ref');
                    $table->index('claim_status', 'ix_dhc_status');
                    $table->index(['item_ref', 'claim_status'], 'ix_dhc_item_status');
                });
            }

            Log::info('ahg-semantic-search: displaced_heritage_claim created (first-boot)');
        } catch (\Throwable $e) {
            // Never block boot on install failure - log and continue. The claim
            // workflow degrades to the empty-state when the table is absent.
            Log::warning('ahg-semantic-search repatriation-claim boot install skipped: '.$e->getMessage());
        }
    }

    /**
     * Idempotent first-boot creation of the endangered-heritage register table.
     *
     * One row per at-risk flag holds the risk category, urgency, the documented
     * reason, the capture-workflow status, and who raised the flag. item_ref is a
     * SOFT reference to the information_object id (NO foreign key) so this additive
     * table never constrains or ALTERs the existing catalogue. risk_category,
     * urgency and capture_status are VARCHAR (Dropdown-Manager idiom, never an
     * ENUM).
     *
     * Prefers the shipped install SQL (database/install_endangered_heritage.sql)
     * so the table definition has one source of truth; falls back to a Schema
     * builder create if the SQL file is unreadable. The whole thing is wrapped in
     * a single try/catch so a missing / locked DB at boot can never fatal the app
     * - the service and controllers degrade to the empty-state when the table is
     * absent.
     */
    protected function bootEndangeredHeritageTable(): void
    {
        try {
            if (Schema::hasTable('endangered_heritage_item')) {
                return;
            }

            $sqlPath = __DIR__.'/../../database/install_endangered_heritage.sql';
            $ran = false;
            if (is_readable($sqlPath)) {
                $sql = (string) file_get_contents($sqlPath);
                if (trim($sql) !== '') {
                    \Illuminate\Support\Facades\DB::unprepared($sql);
                    $ran = true;
                }
            }

            // Fallback: build the table via the Schema builder if the SQL file was
            // not available, so a fresh install still gets the table.
            if (! $ran && ! Schema::hasTable('endangered_heritage_item')) {
                Schema::create('endangered_heritage_item', function (Blueprint $table) {
                    $table->bigIncrements('id');
                    $table->unsignedBigInteger('item_ref');
                    $table->string('risk_category', 64)->default('other');
                    $table->string('urgency', 32)->default('medium');
                    $table->text('reason')->nullable();
                    $table->string('capture_status', 32)->default('flagged');
                    $table->unsignedBigInteger('flagged_by')->nullable();
                    $table->timestamps();

                    $table->index('item_ref', 'ix_ehi_item_ref');
                    $table->index('urgency', 'ix_ehi_urgency');
                    $table->index('capture_status', 'ix_ehi_capture_status');
                    $table->index(['item_ref', 'urgency'], 'ix_ehi_item_urgency');
                });
            }

            Log::info('ahg-semantic-search: endangered_heritage_item created (first-boot)');
        } catch (\Throwable $e) {
            // Never block boot on install failure - log and continue. The register
            // degrades to the empty-state when the table is absent.
            Log::warning('ahg-semantic-search endangered-heritage boot install skipped: '.$e->getMessage());
        }
    }
}
