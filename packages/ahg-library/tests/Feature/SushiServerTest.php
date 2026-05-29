<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 *
 * SushiServerTest - the SUSHI 5.x server endpoint (heratio#766, heratio#1096).
 *
 * Boots a minimal Laravel container (Capsule + config + response factory) over
 * in-memory SQLite and drives the real SushiServerController. Coverage:
 *   - status is public + well-formed
 *   - reports list contains the master report ids
 *   - report() returns a COUNTER R5 envelope (Report_Header + Report_Items)
 *   - report() rejects an unsupported report id with SUSHI exception 3000
 *   - BR1/PR1/DR1 R4-style aliases map to the R5 master reports
 *   - optional-auth default lets anonymous through; require_auth enforces the
 *     consumer registry
 *   - every report/members/reports call writes a row to library_sushi_audit_log
 */

namespace AhgLibrary\Tests\Feature;

use AhgLibrary\Controllers\SushiServerController;
use AhgLibrary\Services\LibraryUsageService;
use AhgLibrary\Tests\AhgLibraryTestCase;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Facade;

class SushiServerTest extends AhgLibraryTestCase
{
    private Capsule $capsule;
    private Container $container;
    private SushiServerController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootContainer();
        $this->controller = new SushiServerController(new LibraryUsageService());
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

        $config = new ConfigRepository([
            'app' => ['name' => 'Heratio Test', 'url' => 'http://localhost'],
            'library' => [
                'sushi' => ['require_auth' => false],
                'counter' => ['customer_id' => 'heratio-self', 'institution_name' => 'Heratio Test Library'],
            ],
        ]);
        $container->instance('config', $config);

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

        $container->bind('log', fn () => new class {
            public function warning($m = null) {}
            public function error($m = null) {}
            public function info($m = null) {}
        });

        // url() helper.
        $url = new UrlGenerator(new RouteCollection(), Request::create('http://localhost', 'GET'));
        $container->instance('url', $url);
        $container->instance(\Illuminate\Contracts\Routing\UrlGenerator::class, $url);

        // response()->json().
        $responseFactory = new ResponseFactory(
            new \Illuminate\View\Factory(
                new \Illuminate\View\Engines\EngineResolver(),
                new \Illuminate\View\FileViewFinder(new \Illuminate\Filesystem\Filesystem(), []),
                new Dispatcher($container)
            ),
            new \Illuminate\Routing\Redirector($url)
        );
        $container->instance(\Illuminate\Contracts\Routing\ResponseFactory::class, $responseFactory);

        Facade::setFacadeApplication($container);
    }

    private function req(array $query = []): Request
    {
        $r = Request::create('/api/sushi/r5/reports', 'GET', $query);
        $r->server->set('REMOTE_ADDR', '203.0.113.9');
        $r->headers->set('User-Agent', 'CounterHarvester/1.0');
        return $r;
    }

    // ─── status ─────────────────────────────────────────────────────────────

    public function test_status_is_public_and_well_formed(): void
    {
        $resp = $this->controller->status();
        $this->assertSame(200, $resp->getStatusCode());
        $body = json_decode($resp->getContent(), true);
        $this->assertTrue($body[0]['Service_Active']);
    }

    // ─── reports list ───────────────────────────────────────────────────────

    public function test_reports_list_contains_master_reports(): void
    {
        $resp = $this->controller->reports($this->req());
        $ids = array_column(json_decode($resp->getContent(), true), 'Report_ID');
        foreach (['PR', 'TR', 'DR', 'IR'] as $id) {
            $this->assertContains($id, $ids);
        }
    }

    // ─── report envelope ─────────────────────────────────────────────────────

    public function test_report_returns_counter_r5_envelope(): void
    {
        $resp = $this->controller->report($this->req(['begin_date' => '2026-01-01', 'end_date' => '2026-01-31']), 'PR');
        $this->assertSame(200, $resp->getStatusCode());
        $body = json_decode($resp->getContent(), true);
        $this->assertArrayHasKey('Report_Header', $body);
        $this->assertArrayHasKey('Report_Items', $body);
        $this->assertSame('PR', $body['Report_Header']['Report_ID']);
    }

    public function test_report_rejects_unsupported_report_id(): void
    {
        $resp = $this->controller->report($this->req(), 'ZZ');
        $this->assertSame(401, $resp->getStatusCode());
        $body = json_decode($resp->getContent(), true);
        $this->assertSame(3000, $body['Code']);
    }

    public function test_br1_alias_maps_to_tr(): void
    {
        $resp = $this->controller->report($this->req(), 'BR1');
        $this->assertSame(200, $resp->getStatusCode());
        $this->assertSame('TR', json_decode($resp->getContent(), true)['Report_Header']['Report_ID']);
    }

    public function test_pr1_alias_maps_to_pr(): void
    {
        $resp = $this->controller->report($this->req(), 'PR1');
        $this->assertSame('PR', json_decode($resp->getContent(), true)['Report_Header']['Report_ID']);
    }

    public function test_dr1_alias_maps_to_dr(): void
    {
        $resp = $this->controller->report($this->req(), 'DR1');
        $this->assertSame('DR', json_decode($resp->getContent(), true)['Report_Header']['Report_ID']);
    }

    // ─── auth ─────────────────────────────────────────────────────────────

    public function test_anonymous_allowed_when_require_auth_false(): void
    {
        $this->container->make('config')->set('library.sushi.require_auth', false);
        $resp = $this->controller->members($this->req());
        $this->assertSame(200, $resp->getStatusCode());
    }

    public function test_require_auth_blocks_missing_credentials(): void
    {
        $this->container->make('config')->set('library.sushi.require_auth', true);
        $resp = $this->controller->members($this->req()); // no api_key/customer/requestor
        $this->assertSame(401, $resp->getStatusCode());
        $this->assertSame(2010, json_decode($resp->getContent(), true)['Code']);
    }

    public function test_require_auth_accepts_registered_consumer(): void
    {
        $this->container->make('config')->set('library.sushi.require_auth', true);
        $key = 'secret-key-123';
        $this->capsule->table('library_sushi_consumer')->insert([
            'customer_id'  => 'cust-1',
            'requestor_id' => 'req-1',
            'api_key_hash' => hash('sha256', $key),
            'name'         => 'Consortium A',
            'active'       => 1,
        ]);
        $resp = $this->controller->members($this->req([
            'api_key' => $key, 'customer_id' => 'cust-1', 'requestor_id' => 'req-1',
        ]));
        $this->assertSame(200, $resp->getStatusCode());
    }

    // ─── audit log ─────────────────────────────────────────────────────────

    public function test_report_request_is_audited(): void
    {
        $before = $this->capsule->table('library_sushi_audit_log')->count();
        $this->controller->report($this->req(['customer_id' => 'cust-9', 'begin_date' => '2026-02-01', 'end_date' => '2026-02-28']), 'TR');
        $rows = $this->capsule->table('library_sushi_audit_log')->get();
        $this->assertSame($before + 1, $rows->count());
        $row = $rows->last();
        $this->assertSame('TR', $row->report_id);
        $this->assertSame('cust-9', $row->customer_id);
        $this->assertSame('203.0.113.9', $row->ip);
    }

    public function test_members_and_reports_are_audited(): void
    {
        $this->controller->members($this->req());
        $this->controller->reports($this->req());
        $reportIds = $this->capsule->table('library_sushi_audit_log')->pluck('report_id')->all();
        $this->assertContains('_members', $reportIds);
        $this->assertContains('_reports', $reportIds);
    }
}
