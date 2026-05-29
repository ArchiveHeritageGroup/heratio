<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 *
 * UsageEventControllerTest - the COUNTER R5 beacon endpoint (heratio#1096).
 *
 * Boots a minimal Laravel container (Capsule + validator + cache-backed
 * RateLimiter + response factory) over in-memory SQLite, then drives the real
 * UsageEventController::record() with crafted Request objects. Coverage:
 *   - view event -> investigation, persisted to both surfaces
 *   - request / link_click -> request
 *   - search event accepted without a library_item_id (PR1)
 *   - unknown item silently dropped (no row written)
 *   - validation rejects an unknown event type
 *   - session cookie (lib_usage_sid) is read and hashed (never stored raw)
 */

namespace AhgLibrary\Tests\Feature;

use AhgLibrary\Controllers\UsageEventController;
use AhgLibrary\Services\LibraryUsageService;
use AhgLibrary\Tests\AhgLibraryTestCase;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\RateLimiter;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidationFactory;
use Illuminate\Validation\ValidationException;

class UsageEventControllerTest extends AhgLibraryTestCase
{
    private Capsule $capsule;
    private Container $container;
    private UsageEventController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootContainer();
        $this->controller = new UsageEventController(new LibraryUsageService());
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        parent::tearDown();
    }

    private function bootContainer(): void
    {
        $container = new class extends Container {
            public function getLocale(): string { return 'en'; }
            public function runningInConsole(): bool { return true; }
        };
        Container::setInstance($container);
        $this->container = $container;

        // Config first - both Capsule's DatabaseManager and the cache/rate
        // limiter read from this single repository.
        $config = new ConfigRepository([
            'cache' => [
                'default' => 'array',
                'stores'  => ['array' => ['driver' => 'array']],
            ],
        ]);
        $container->instance('config', $config);

        // Database (Capsule registers its connection config into the container).
        $capsule = new Capsule($container);
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'default');
        $capsule->getDatabaseManager()->setDefaultConnection('default');
        $capsule->setEventDispatcher(new Dispatcher($container));
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $this->capsule = $capsule;
        $container->instance('db', $capsule->getDatabaseManager());
        $container->bind('db.schema', fn () => $capsule->getConnection('default')->getSchemaBuilder());
        $capsule->getConnection('default')->getPdo()->exec($this->loadFixture('schema.sql'));

        // Logging (no-op)
        $container->bind('log', fn () => new class {
            public function warning($m = null) {}
            public function error($m = null) {}
            public function info($m = null) {}
        });

        // Validation
        $translator = new Translator(new ArrayLoader(), 'en');
        $validationFactory = new ValidationFactory($translator, $container);
        $container->instance('validator', $validationFactory);
        $container->instance(\Illuminate\Contracts\Validation\Factory::class, $validationFactory);
        $container->instance('translator', $translator);

        // Register the framework's validate() macro on Request.
        Request::macro('validate', function (array $rules, ...$params) {
            return validator($this->all(), $rules, ...$params)->validate();
        });

        // Cache-backed rate limiter.
        $cacheManager = new CacheManager($container);
        $container->instance('cache', $cacheManager);
        $container->instance(RateLimiter::class, new RateLimiter($cacheManager->store()));

        // Response factory for response()->json().
        $responseFactory = new ResponseFactory(
            new \Illuminate\View\Factory(
                new \Illuminate\View\Engines\EngineResolver(),
                new \Illuminate\View\FileViewFinder(new \Illuminate\Filesystem\Filesystem(), []),
                new Dispatcher($container)
            ),
            new \Illuminate\Routing\Redirector(new \Illuminate\Routing\UrlGenerator(
                new \Illuminate\Routing\RouteCollection(),
                Request::create('http://localhost', 'GET')
            ))
        );
        $container->instance(\Illuminate\Contracts\Routing\ResponseFactory::class, $responseFactory);

        Facade::setFacadeApplication($container);
    }

    private function makeRequest(array $body, array $cookies = []): Request
    {
        $request = Request::create('/api/library/usage-event', 'POST', [], $cookies, [], [], json_encode($body));
        $request->headers->set('Content-Type', 'application/json');
        $request->setJson(new \Symfony\Component\HttpFoundation\InputBag($body));
        // No authenticated user in these tests.
        $request->setUserResolver(fn () => null);
        return $request;
    }

    private function seedItem(int $id): void
    {
        $this->capsule->table('library_item')->insert(['id' => $id, 'information_object_id' => $id, 'material_type' => 'monograph']);
    }

    // ─── view event ─────────────────────────────────────────────────────────

    public function test_view_event_records_investigation(): void
    {
        $this->seedItem(100);
        $resp = $this->controller->record($this->makeRequest(['library_item_id' => 100, 'event' => 'view']));
        $this->assertSame(200, $resp->getStatusCode());
        $this->assertTrue(json_decode($resp->getContent(), true)['ok']);

        $log = $this->capsule->table('library_counter_log')->where('resource_id', 100)->first();
        $this->assertNotNull($log);
        $this->assertSame('investigation', $log->event);
    }

    public function test_request_event_records_request(): void
    {
        $this->seedItem(101);
        $this->controller->record($this->makeRequest(['library_item_id' => 101, 'event' => 'request']));
        $row = $this->capsule->table('library_counter_log')->where('resource_id', 101)->first();
        $this->assertSame('request', $row->event);
    }

    public function test_link_click_maps_to_request(): void
    {
        $this->seedItem(102);
        $this->controller->record($this->makeRequest(['library_item_id' => 102, 'event' => 'link_click']));
        $row = $this->capsule->table('library_counter_log')->where('resource_id', 102)->first();
        $this->assertSame('request', $row->event);
    }

    // ─── search event (PR1) ───────────────────────────────────────────────

    public function test_search_event_accepted_without_item_id(): void
    {
        $resp = $this->controller->record($this->makeRequest(['event' => 'search']));
        $this->assertSame(200, $resp->getStatusCode());
        $this->assertTrue(json_decode($resp->getContent(), true)['ok']);

        $row = $this->capsule->table('library_counter_log')->where('event', 'search')->first();
        $this->assertNotNull($row);
        $this->assertNull($row->resource_id);
    }

    // ─── unknown item dropped ─────────────────────────────────────────────

    public function test_unknown_item_silently_dropped(): void
    {
        $resp = $this->controller->record($this->makeRequest(['library_item_id' => 999999, 'event' => 'view']));
        $this->assertSame(200, $resp->getStatusCode());
        $body = json_decode($resp->getContent(), true);
        $this->assertFalse($body['ok']);
        $this->assertSame('unknown_item', $body['reason']);
        $this->assertSame(0, $this->capsule->table('library_counter_log')->count());
    }

    // ─── validation ───────────────────────────────────────────────────────

    public function test_invalid_event_type_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->controller->record($this->makeRequest(['library_item_id' => 1, 'event' => 'hack']));
    }

    public function test_missing_item_id_rejected_for_non_search(): void
    {
        $this->expectException(ValidationException::class);
        $this->controller->record($this->makeRequest(['event' => 'view']));
    }

    // ─── session cookie hashing ───────────────────────────────────────────

    public function test_session_cookie_is_hashed_not_stored_raw(): void
    {
        $this->seedItem(103);
        $this->controller->record($this->makeRequest(
            ['library_item_id' => 103, 'event' => 'view'],
            ['lib_usage_sid' => 'raw-token-xyz']
        ));
        $row = $this->capsule->table('library_counter_log')->where('resource_id', 103)->first();
        $this->assertNotNull($row->session_id);
        $this->assertNotSame('raw-token-xyz', $row->session_id);
        $this->assertSame(substr(hash('sha256', 'raw-token-xyz'), 0, 64), $row->session_id);
    }
}
