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

            // heratio#1207 - PUBLIC community KNOWLEDGE contribution on one
            // repatriation claim. A member of a source community, a descendant, a
            // researcher or any knowledgeable person can contribute knowledge
            // (oral history / provenance / correction / source community) about a
            // displaced object, linked from the claim's /virtual-return/{id}
            // page. The contribution lands MODERATED ('pending') and is shown
            // publicly only once an admin approves it. {claim} is numeric-only,
            // so a two-segment path like /repatriation-knowledge/12 can never
            // shadow the single-segment /{slug} archival-record catch-all in
            // ahg-information-object-manage; bound here (register() +
            // callAfterResolving('router')) for the same precedence guarantee as
            // the routes above. See
            // memory/reference_slug_catchall_route_precedence.md.
            $router->middleware('web')
                ->get('/repatriation-knowledge/{claim}', [
                    \AhgSemanticSearch\Controllers\RepatriationKnowledgeController::class, 'form',
                ])
                ->where('claim', '[0-9]+')
                ->name('repatriation-knowledge.form');
            $router->middleware('web')
                ->post('/repatriation-knowledge/{claim}', [
                    \AhgSemanticSearch\Controllers\RepatriationKnowledgeController::class, 'contribute',
                ])
                ->where('claim', '[0-9]+')
                ->name('repatriation-knowledge.contribute');

            // heratio#1207 - PUBLIC self-service claim lodging. An origin community
            // lodges a repatriation claim DIRECTLY about a traced object, with no
            // staff account: the claim lands 'registered' with no staff author and
            // a notes marker, firing the staff notification for review. Spam is held
            // off with a honeypot + minimum-dwell check in the controller and route
            // throttling here. {item} is numeric-only and the path is 3-segment, so
            // it can never shadow the single-segment /{slug} archival-record
            // catch-all; bound here (register() + callAfterResolving('router')) for
            // the same precedence guarantee as the other public repatriation routes.
            // The /thanks confirmation is registered BEFORE the {item} form so the
            // literal segment wins over the numeric matcher. See
            // memory/reference_slug_catchall_route_precedence.md.
            $router->middleware('web')
                ->get('/repatriation/lodge/thanks', [
                    \AhgSemanticSearch\Controllers\PublicClaimLodgeController::class, 'thanks',
                ])
                ->name('repatriation.lodge.thanks');
            $router->middleware('web')
                ->get('/repatriation/lodge/{item}', [
                    \AhgSemanticSearch\Controllers\PublicClaimLodgeController::class, 'form',
                ])
                ->where('item', '[0-9]+')
                ->name('repatriation.lodge.form');
            $router->middleware(['web', 'throttle:8,1'])
                ->post('/repatriation/lodge/{item}', [
                    \AhgSemanticSearch\Controllers\PublicClaimLodgeController::class, 'submit',
                ])
                ->where('item', '[0-9]+')
                ->name('repatriation.lodge.submit');

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

            // heratio#1207 - the SHARED RECORD surface (pillar 3): a token-gated,
            // permissioned view of ONE claim that both the holding institution and
            // the origin community can see. NOT auth-gated - access is a capability
            // token (repatriation_claim_access) minted by staff. Keyed off the
            // opaque {token}, never the claim id, so it cannot be enumerated; an
            // unknown / revoked / expired token resolves to a dignified "link not
            // active" state. The claimant may post SHARED dialogue messages when the
            // grant permits it; internal staff notes are never exposed here. Bound
            // here (register() + callAfterResolving('router')) for the same catch-all
            // precedence guarantee as the other public repatriation routes; the path
            // is 3-segment so it can never shadow the single-segment /{slug}
            // archival-record catch-all anyway. See
            // memory/reference_slug_catchall_route_precedence.md.
            $router->middleware('web')
                ->get('/repatriation/shared/{token}', [
                    \AhgSemanticSearch\Controllers\RepatriationSharedRecordController::class, 'show',
                ])
                ->where('token', '[A-Za-z0-9]{16,64}')
                ->name('repatriation.shared.show');
            $router->middleware('web')
                ->post('/repatriation/shared/{token}/message', [
                    \AhgSemanticSearch\Controllers\RepatriationSharedRecordController::class, 'message',
                ])
                ->where('token', '[A-Za-z0-9]{16,64}')
                ->name('repatriation.shared.message');

            // heratio#1205 - North Star "race against loss": the PUBLIC, read-only
            // "at risk" register of endangered heritage (PUBLISHED items only),
            // most-urgent first, framing why heritage is endangered and the race
            // to capture it. Single-segment public path, registered the same way
            // as /discoveries and /displaced-heritage (register() +
            // callAfterResolving('router')) so it binds BEFORE the single-segment
            // /{slug} archival-record catch-all in ahg-information-object-manage.
            // See memory/reference_slug_catchall_route_precedence.md.
            // heratio#1205 - the cross-institution "at risk" board. TWO-segment
            // path (/at-risk/global) registered BEFORE the single-segment /at-risk
            // so it binds as the federated board, never as a risk filter on the
            // local register. Merges this instance's published register with a LIVE
            // fetch of every active federation peer's /api/v1/endangered (mirrors
            // the #1204/#1210 federation pattern). Additive - /at-risk is unchanged.
            $router->middleware('web')
                ->get('/at-risk/global', [
                    \AhgSemanticSearch\Controllers\EndangeredHeritageController::class, 'globalRegister',
                ])
                ->name('endangered.register.global');

            $router->middleware('web')
                ->get('/at-risk', [
                    \AhgSemanticSearch\Controllers\EndangeredHeritageController::class, 'register',
                ])
                ->name('endangered.register');

            // heratio#1205 - PUBLIC endangered-heritage dashboard, the next slice of
            // the "race against loss". A read-only aggregate VIEW over the at-risk
            // register (counts by risk category, by urgency, the capture-progress
            // split captured/in-progress/flagged, the grand total, and a short tail
            // of the highest-priority outstanding PUBLISHED items that links onward
            // to /at-risk). Single-segment public path, registered the same way as
            // /at-risk and /repatriation (register() + callAfterResolving('router'))
            // so it binds BEFORE the single-segment /{slug} archival-record catch-all
            // in ahg-information-object-manage. See
            // memory/reference_slug_catchall_route_precedence.md.
            $router->middleware('web')
                ->get('/endangered-heritage', [
                    \AhgSemanticSearch\Controllers\EndangeredHeritageDashboardController::class, 'index',
                ])
                ->name('endangered.dashboard');

            // heratio#1205 - machine-readable twin of the dashboard. The .json suffix
            // keeps it a distinct single-segment path that can never shadow a slug;
            // CORS-open public read-only data. Bound here for the same catch-all
            // precedence guarantee as the HTML dashboard above.
            $router->middleware('web')
                ->get('/endangered-heritage.json', [
                    \AhgSemanticSearch\Controllers\EndangeredHeritageDashboardController::class, 'json',
                ])
                ->name('endangered.dashboard.json');

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

            // heratio#1210 - North Star "generative scholarship" + discovery: the
            // PUBLIC "Explore by theme" surface. Themes are the collection's
            // strongest subjects (subject taxonomy 35): the subject terms under
            // which the most PUBLISHED records sit are its de-facto themes, framed
            // as "ways into the collection". Read-only cheap aggregate over
            // object_term_relation -> term + the publication-status gate; no new
            // table. Single-segment public path /themes, registered here
            // (register() + callAfterResolving('router')) so it binds BEFORE the
            // single-segment /{slug} archival-record catch-all in
            // ahg-information-object-manage. See
            // memory/reference_slug_catchall_route_precedence.md.
            $router->middleware('web')
                ->get('/themes', [
                    \AhgSemanticSearch\Controllers\ThemesController::class, 'index',
                ])
                ->name('themes.index');

            // heratio#1210 - machine-readable twin of the theme list. The .json
            // suffix keeps it a distinct single-segment path that can never shadow
            // a slug; CORS-open public read-only data. Bound here for the same
            // catch-all precedence guarantee as the index above.
            $router->middleware('web')
                ->get('/themes.json', [
                    \AhgSemanticSearch\Controllers\ThemesController::class, 'json',
                ])
                ->name('themes.json');

            // heratio#1210 - per-theme detail: one subject term, its label + scope
            // note, and a paginated, bounded list of the published records filed
            // under it (each linking to the record). Numeric {termId} constraint so
            // a two-segment path like /themes/553 can never shadow the
            // single-segment /{slug} archival-record catch-all; bound here for the
            // same precedence guarantee as the index above.
            $router->middleware('web')
                ->get('/themes/{termId}', [
                    \AhgSemanticSearch\Controllers\ThemesController::class, 'show',
                ])
                ->where('termId', '[0-9]+')
                ->name('themes.show');

            // PUBLIC "Browse by place" discovery surface - the published holdings
            // organised by the PLACES they are about (place taxonomy 42), the
            // geography sibling of the "Explore by theme" subject slice above.
            // Read-only cheap aggregate over object_term_relation -> term + the
            // publication-status gate; no new table. The .json twin is declared
            // FIRST (dotted, so a record slug that literally ends in ".json" can
            // never be swallowed by the HTML index route); the single-segment
            // /places is then bound here (register() + callAfterResolving('router'))
            // so it binds BEFORE the single-segment /{slug} archival-record
            // catch-all in ahg-information-object-manage. See
            // memory/reference_slug_catchall_route_precedence.md.
            $router->middleware('web')
                ->get('/places.json', [
                    \AhgSemanticSearch\Controllers\PlacesController::class, 'json',
                ])
                ->name('places.json');

            $router->middleware('web')
                ->get('/places', [
                    \AhgSemanticSearch\Controllers\PlacesController::class, 'index',
                ])
                ->name('places.index');

            // Per-place detail: one place term, its label + scope note, and a
            // paginated, bounded list of the published records about it (each
            // linking to the record). Numeric {termId} constraint so a two-segment
            // path like /places/553 can never shadow the single-segment /{slug}
            // archival-record catch-all; bound here for the same precedence
            // guarantee as the index above.
            $router->middleware('web')
                ->get('/places/{termId}', [
                    \AhgSemanticSearch\Controllers\PlacesController::class, 'show',
                ])
                ->where('termId', '[0-9]+')
                ->name('places.show');

            // PUBLIC "Browse by genre / form" discovery surface - the published
            // holdings organised by the GENRES and FORMS they carry (genre
            // taxonomy 78), the genre/form sibling of the "Explore by theme"
            // subject slice and the "Browse by place" geography slice above.
            // Read-only cheap aggregate over object_term_relation -> term + the
            // publication-status gate; no new table. The .json twin is declared
            // FIRST (dotted, so a record slug that literally ends in ".json" can
            // never be swallowed by the HTML index route); the single-segment
            // /genres is then bound here (register() + callAfterResolving('router'))
            // so it binds BEFORE the single-segment /{slug} archival-record
            // catch-all in ahg-information-object-manage. See
            // memory/reference_slug_catchall_route_precedence.md.
            $router->middleware('web')
                ->get('/genres.json', [
                    \AhgSemanticSearch\Controllers\GenresController::class, 'json',
                ])
                ->name('genres.json');

            $router->middleware('web')
                ->get('/genres', [
                    \AhgSemanticSearch\Controllers\GenresController::class, 'index',
                ])
                ->name('genres.index');

            // Per-genre detail: one genre term, its label + scope note, and a
            // paginated, bounded list of the published records of it (each
            // linking to the record). Numeric {termId} constraint so a two-segment
            // path like /genres/387 can never shadow the single-segment /{slug}
            // archival-record catch-all; bound here for the same precedence
            // guarantee as the index above.
            $router->middleware('web')
                ->get('/genres/{termId}', [
                    \AhgSemanticSearch\Controllers\GenresController::class, 'show',
                ])
                ->where('termId', '[0-9]+')
                ->name('genres.show');

            // PUBLIC "People and organisations" discovery surface - the published
            // holdings organised by the PEOPLE and ORGANISATIONS that created them
            // (the actors the `event` table credits as creators), the creator
            // sibling of the "Explore by theme" subject slice and the "Browse by
            // place" geography slice above. Read-only cheap aggregate over event ->
            // actor + the publication-status gate; no new table. The .json twin is
            // declared FIRST (dotted, so a record slug that literally ends in
            // ".json" can never be swallowed by the HTML index route); the
            // single-segment /people is then bound here (register() +
            // callAfterResolving('router')) so it binds BEFORE the single-segment
            // /{slug} archival-record catch-all in ahg-information-object-manage.
            // See memory/reference_slug_catchall_route_precedence.md.
            $router->middleware('web')
                ->get('/people.json', [
                    \AhgSemanticSearch\Controllers\PeopleController::class, 'json',
                ])
                ->name('people.json');

            $router->middleware('web')
                ->get('/people', [
                    \AhgSemanticSearch\Controllers\PeopleController::class, 'index',
                ])
                ->name('people.index');

            // Per-creator detail: one actor, the authorized form of name + dates /
            // history, and a paginated, bounded list of the published records they
            // created (each linking to the record). Numeric {actorId} constraint so
            // a two-segment path like /people/830 can never shadow the
            // single-segment /{slug} archival-record catch-all; bound here for the
            // same precedence guarantee as the index above.
            $router->middleware('web')
                ->get('/people/{actorId}', [
                    \AhgSemanticSearch\Controllers\PeopleController::class, 'show',
                ])
                ->where('actorId', '[0-9]+')
                ->name('people.show');

            // Public "Related records" discovery surface. Given ONE published
            // archival record (numeric id OR slug), surface the most-similar OTHER
            // published records by REUSING the existing semantic vector index in
            // ahg-search (no new index, no AI call of its own - see
            // RelatedRecordsService). Both paths are TWO-segment (/related/...), so
            // the single-segment /{slug} archival-record catch-all in
            // ahg-information-object-manage (constrained to one path segment, no
            // slash) can never intercept them; they are nonetheless bound here
            // (register() + callAfterResolving('router')) for the same catch-all
            // precedence guarantee as the discovery surfaces above. See
            // memory/reference_slug_catchall_route_precedence.md.
            //
            // The .json twin is declared FIRST so a record slug that literally ends
            // in ".json" can never be swallowed by the HTML route. The {idOrSlug}
            // matcher allows a multi-segment slug ([A-Za-z0-9][A-Za-z0-9/_-]*) and
            // the .json route additionally requires the literal .json suffix.
            $router->middleware('web')
                ->get('/related/{idOrSlug}.json', [
                    \AhgSemanticSearch\Controllers\RelatedRecordsController::class, 'json',
                ])
                ->where('idOrSlug', '[A-Za-z0-9][A-Za-z0-9/_-]*')
                ->name('related.json');

            $router->middleware('web')
                ->get('/related/{idOrSlug}', [
                    \AhgSemanticSearch\Controllers\RelatedRecordsController::class, 'show',
                ])
                ->where('idOrSlug', '[A-Za-z0-9][A-Za-z0-9/_-]*')
                ->name('related.show');

            // Public collection timeline - the distribution of published records
            // across time, bucketed by period. The .json twin is declared FIRST
            // (dotted, so a slug ending in ".json" can never be swallowed); the
            // single-segment /timeline is bound here (register() +
            // callAfterResolving('router')) so it wins ahead of the /{slug}
            // archival-record catch-all, exactly like /themes above. See
            // memory/reference_slug_catchall_route_precedence.md.
            $router->middleware('web')
                ->get('/timeline.json', [
                    \AhgSemanticSearch\Controllers\TimelineController::class, 'json',
                ])
                ->name('timeline.json');

            $router->middleware('web')
                ->get('/timeline', [
                    \AhgSemanticSearch\Controllers\TimelineController::class, 'index',
                ])
                ->name('timeline.index');

            // PUBLIC "Explore the collection" hub - one coherent entry point that
            // gathers the browse-by discovery surfaces above (themes, places,
            // people, timeline) with a small READ-ONLY teaser from each existing
            // service, then links onward to the full slice page. Additive: it
            // REUSES the slice services and edits none of their files. Each section
            // is Route::has-gated in the controller, so a section (and its onward
            // links) only renders when that slice is installed - never a dead link.
            //
            // The .json twin is declared FIRST (dotted, so a record slug that
            // literally ends in ".json" can never be swallowed by the HTML hub
            // route); the single-segment /explore-collection is then bound here
            // (register() + callAfterResolving('router')) so it binds BEFORE the
            // single-segment /{slug} archival-record catch-all in
            // ahg-information-object-manage, exactly like /themes above. See
            // memory/reference_slug_catchall_route_precedence.md.
            $router->middleware('web')
                ->get('/explore-collection.json', [
                    \AhgSemanticSearch\Controllers\ExploreCollectionController::class, 'json',
                ])
                ->name('explore-collection.json');

            $router->middleware('web')
                ->get('/explore-collection', [
                    \AhgSemanticSearch\Controllers\ExploreCollectionController::class, 'index',
                ])
                ->name('explore-collection.index');
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
        $this->bootFederatedDiscoveryCacheTable();
        $this->bootEndangeredInboundTable();

        // heratio#1207 - repatriation-claim / virtual-return workflow. The
        // structured claim records that sit on top of the displaced-heritage
        // register live in displaced_heritage_claim. Auto-created on first boot
        // behind a Schema::hasTable probe in a single try/catch (the canonical
        // package idiom - never fatal a fresh boot; see
        // memory/reference_ci_schema_hastable.md).
        $this->bootRepatriationClaimTable();

        // heratio#1207 - community KNOWLEDGE contributions about displaced items /
        // repatriation claims (oral history, provenance, corrections, source
        // community). Moderated; live in repatriation_knowledge_contribution.
        // Auto-created on first boot behind a Schema::hasTable probe in a single
        // try/catch (the canonical package idiom - never fatal a fresh boot; see
        // memory/reference_ci_schema_hastable.md).
        $this->bootRepatriationKnowledgeTable();

        // heratio#1207 - repatriation DIALOGUE + status AUDIT TRAIL + shared-record
        // ACCESS grants. The two-way threaded conversation on a claim, the
        // append-only status history, and the token-permissioned shared-record
        // grants live in repatriation_claim_message / repatriation_claim_status_log
        // / repatriation_claim_access. Auto-created on first boot behind a
        // Schema::hasTable probe in a single try/catch (the canonical package idiom
        // - never fatal a fresh boot; see memory/reference_ci_schema_hastable.md).
        $this->bootRepatriationDialogueTables();

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
                \AhgSemanticSearch\Console\Commands\RefreshFederatedDiscoveriesCommand::class,
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
     * heratio#1210 - persistence/cache for the CROSS-INSTITUTIONAL (federated)
     * discovery, which was previously live-only (a peer round-trip + per-connection
     * AI rationale on every page view). ScholarshipService now reads through this
     * table: a fresh row is served directly, an expired/missing one triggers one
     * live refresh that is persisted, and a failed live refresh falls back to the
     * stale row (so a peer outage shows last-known results, not a blank section).
     * Auto-created on first boot behind a Schema::hasTable probe in one try/catch
     * (the canonical package idiom; see memory/reference_ci_schema_hastable.md).
     */
    protected function bootFederatedDiscoveryCacheTable(): void
    {
        try {
            if (Schema::hasTable('ahg_scholarship_federated_discovery')) {
                return;
            }

            Schema::create('ahg_scholarship_federated_discovery', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('information_object_id');
                $table->string('title', 1024)->nullable();
                $table->json('terms')->nullable();
                $table->json('connections')->nullable();
                $table->json('peer_stats')->nullable();
                $table->unsignedInteger('connection_count')->default(0);
                $table->boolean('ai_available')->default(false);
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();

                $table->unique('information_object_id', 'uq_fed_discovery_io');
            });

            Log::info('ahg-semantic-search: ahg_scholarship_federated_discovery created (first-boot)');
        } catch (\Throwable $e) {
            // Never block boot on install failure - log and continue. Federated
            // discovery degrades to live-only (no persistence) when absent.
            Log::warning('ahg-semantic-search federated-discovery-cache boot install skipped: '.$e->getMessage());
        }
    }

    /**
     * heratio#1205 - PUSH-MODEL peer inbound for the endangered network. A
     * federation peer can POST an at-risk flag to /api/v1/endangered/inbound; the
     * verified push lands here for staff review (review_status 'pending'), and an
     * accepted push then surfaces on the cross-institution board. Auto-created on
     * first boot behind a Schema::hasTable probe in one try/catch (the canonical
     * package idiom; see memory/reference_ci_schema_hastable.md).
     */
    protected function bootEndangeredInboundTable(): void
    {
        try {
            if (Schema::hasTable('endangered_inbound_flag')) {
                return;
            }

            Schema::create('endangered_inbound_flag', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->char('dedupe_key', 64);             // sha256(base_url|reference)
                $table->string('source_peer_base_url', 1024);
                $table->string('source_peer_name', 512)->nullable();
                $table->string('reference', 512);           // the peer's item ref/slug
                $table->string('title', 1024)->nullable();
                $table->string('risk', 64)->default('other');
                $table->string('urgency', 64)->default('medium');
                $table->string('capture_status', 64)->nullable();
                $table->text('reason')->nullable();
                $table->string('catalogue_url', 1024)->nullable();
                $table->json('payload')->nullable();
                $table->boolean('peer_verified')->default(false);
                $table->string('key_fingerprint', 128)->nullable();
                $table->string('review_status', 32)->default('pending'); // pending|accepted|declined
                $table->timestamp('received_at')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->unique('dedupe_key', 'uq_endangered_inbound_dedupe');
                $table->index('review_status', 'ix_endangered_inbound_status');
            });

            Log::info('ahg-semantic-search: endangered_inbound_flag created (first-boot)');
        } catch (\Throwable $e) {
            // Never block boot on install failure - the inbound endpoint just
            // returns "not available" when the table is absent.
            Log::warning('ahg-semantic-search endangered-inbound boot install skipped: '.$e->getMessage());
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
     * Idempotent first-boot creation of the repatriation-knowledge table.
     *
     * One row per community knowledge contribution about a displaced object: the
     * claim it concerns (claim_id, a SOFT reference - NO foreign key), the
     * underlying catalogue item (item_ref, also soft), the kind of knowledge
     * (contribution_type, a VARCHAR - never an ENUM), the contributed text, an
     * optional source, the contributor's name surfaced ONLY on explicit consent
     * (credit_consent), and a moderation state (moderation_status, also VARCHAR).
     * No foreign keys, so this additive table never constrains or ALTERs the
     * existing claim / catalogue tables.
     *
     * Prefers the shipped install SQL (database/install_repatriation_knowledge.sql)
     * so the table definition has one source of truth; falls back to a Schema
     * builder create if the SQL file is unreadable. The whole thing is wrapped in
     * a single try/catch so a missing / locked DB at boot can never fatal the
     * app - the service and controllers degrade to the empty-state when the table
     * is absent.
     */
    protected function bootRepatriationKnowledgeTable(): void
    {
        try {
            if (Schema::hasTable('repatriation_knowledge_contribution')) {
                return;
            }

            $sqlPath = __DIR__.'/../../database/install_repatriation_knowledge.sql';
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
            if (! $ran && ! Schema::hasTable('repatriation_knowledge_contribution')) {
                Schema::create('repatriation_knowledge_contribution', function (Blueprint $table) {
                    $table->bigIncrements('id');
                    $table->unsignedBigInteger('claim_id')->nullable();
                    $table->unsignedBigInteger('item_ref')->nullable();
                    $table->string('contribution_type', 32)->default('other');
                    $table->mediumText('body');
                    $table->string('source', 512)->nullable();
                    $table->string('contributor_name', 255)->nullable();
                    $table->boolean('credit_consent')->default(false);
                    $table->unsignedBigInteger('contributed_by')->nullable();
                    $table->string('moderation_status', 32)->default('pending');
                    $table->unsignedBigInteger('moderated_by')->nullable();
                    $table->timestamp('moderated_at')->nullable();
                    $table->timestamps();

                    $table->index('claim_id', 'ix_rkc_claim');
                    $table->index('item_ref', 'ix_rkc_item_ref');
                    $table->index('moderation_status', 'ix_rkc_status');
                    $table->index(['claim_id', 'moderation_status'], 'ix_rkc_claim_status');
                    $table->index(['item_ref', 'moderation_status'], 'ix_rkc_item_status');
                });
            }

            Log::info('ahg-semantic-search: repatriation_knowledge_contribution created (first-boot)');
        } catch (\Throwable $e) {
            // Never block boot on install failure - log and continue. The
            // contribution surfaces degrade to the empty-state when absent.
            Log::warning('ahg-semantic-search repatriation-knowledge boot install skipped: '.$e->getMessage());
        }
    }

    /**
     * Idempotent first-boot creation of the repatriation dialogue / status-audit /
     * shared-access tables (heratio#1207).
     *
     * Three additive tables drive the conversation around a claim:
     * repatriation_claim_message (two-way threaded dialogue),
     * repatriation_claim_status_log (append-only status history), and
     * repatriation_claim_access (token-permissioned shared-record grants). Each
     * reference into the claim / catalogue tables is a SOFT reference (NO foreign
     * key). Prefers the shipped install SQL (one source of truth for all three
     * CREATE TABLE statements); falls back to a Schema builder create per table if
     * the SQL file is unreadable. Wrapped in a single try/catch so a missing /
     * locked DB at boot can never fatal the app - every surface degrades to the
     * empty-state when a table is absent.
     */
    protected function bootRepatriationDialogueTables(): void
    {
        try {
            $haveAll = Schema::hasTable('repatriation_claim_message')
                && Schema::hasTable('repatriation_claim_status_log')
                && Schema::hasTable('repatriation_claim_access');
            if ($haveAll) {
                return;
            }

            $sqlPath = __DIR__.'/../../database/install_repatriation_dialogue.sql';
            $ran = false;
            if (is_readable($sqlPath)) {
                $sql = (string) file_get_contents($sqlPath);
                if (trim($sql) !== '') {
                    \Illuminate\Support\Facades\DB::unprepared($sql);
                    $ran = true;
                }
            }

            // Fallback: build any still-missing table via the Schema builder so a
            // fresh install gets all three even if the SQL file is unavailable.
            if (! $ran || ! Schema::hasTable('repatriation_claim_message')) {
                if (! Schema::hasTable('repatriation_claim_message')) {
                    Schema::create('repatriation_claim_message', function (Blueprint $table) {
                        $table->bigIncrements('id');
                        $table->unsignedBigInteger('claim_id');
                        $table->string('author_role', 32)->default('institution');
                        $table->string('author_name', 255)->nullable();
                        $table->unsignedBigInteger('author_user')->nullable();
                        $table->unsignedBigInteger('access_id')->nullable();
                        $table->mediumText('body');
                        $table->string('visibility', 16)->default('shared');
                        $table->timestamps();

                        $table->index('claim_id', 'ix_rcm_claim');
                        $table->index(['claim_id', 'visibility'], 'ix_rcm_claim_vis');
                        $table->index('access_id', 'ix_rcm_access');
                    });
                }
            }
            if (! Schema::hasTable('repatriation_claim_status_log')) {
                Schema::create('repatriation_claim_status_log', function (Blueprint $table) {
                    $table->bigIncrements('id');
                    $table->unsignedBigInteger('claim_id');
                    $table->string('from_status', 64)->nullable();
                    $table->string('to_status', 64);
                    $table->string('note', 1024)->nullable();
                    $table->unsignedBigInteger('changed_by')->nullable();
                    $table->string('changed_by_name', 255)->nullable();
                    $table->timestamps();

                    $table->index('claim_id', 'ix_rcsl_claim');
                    $table->index(['claim_id', 'id'], 'ix_rcsl_claim_created');
                });
            }
            if (! Schema::hasTable('repatriation_claim_access')) {
                Schema::create('repatriation_claim_access', function (Blueprint $table) {
                    $table->bigIncrements('id');
                    $table->unsignedBigInteger('claim_id');
                    $table->string('token', 64);
                    $table->string('grantee_name', 255)->nullable();
                    $table->string('grantee_role', 32)->default('claimant');
                    $table->boolean('can_message')->default(true);
                    $table->string('status', 16)->default('active');
                    $table->timestamp('expires_at')->nullable();
                    $table->unsignedBigInteger('created_by')->nullable();
                    $table->timestamp('last_seen_at')->nullable();
                    $table->timestamps();

                    $table->unique('token', 'uq_rca_token');
                    $table->index('claim_id', 'ix_rca_claim');
                    $table->index(['claim_id', 'status'], 'ix_rca_claim_status');
                });
            }

            Log::info('ahg-semantic-search: repatriation dialogue/audit/access tables created (first-boot)');
        } catch (\Throwable $e) {
            // Never block boot on install failure - log and continue. The dialogue,
            // audit and shared-access surfaces degrade to the empty-state when
            // their tables are absent.
            Log::warning('ahg-semantic-search repatriation-dialogue boot install skipped: '.$e->getMessage());
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
