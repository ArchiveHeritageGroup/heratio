<?php

/** Rules for numbered releases of a Portable Export. All HTTP is faked. */

namespace Tests\Feature;

use AhgCore\Services\AclService;
use AhgCore\Support\StatusRow;
use AhgPortableExport\Console\Commands\BundleWorkerCommand;
use AhgPortableExport\Services\PortableReleaseService;
use AhgPortableExport\Services\ReleaseException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PortableReleaseVersionRulesTest extends TestCase
{
    use DatabaseTransactions;

    private string $dir;

    private PortableReleaseService $svc;

    private int $admin;

    private array $datacite = [];

    /** Fail DataCite writes matching this "doi-suffix:event" test. */
    private $failWrite = null;

    private int $zenodoCreates = 0;

    private int $zenodoPublishes = 0;

    private bool $zenodoPublished = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/verify-release-'.Str::random(10);
        mkdir($this->dir, 0775, true);
        config(['heratio.storage_path' => $this->dir, 'heratio.uploads_path' => $this->dir, 'app.url' => 'https://archive.example.org']);
        DB::table('ahg_doi_config')->update(['is_active' => 0]);
        DB::table('ahg_doi_config')->insert(['repository_id' => null, 'datacite_repo_id' => 'TEST.REPO', 'datacite_prefix' => '10.99999',
            'datacite_password' => 'not-a-real-secret', 'datacite_url' => 'https://api.test.datacite.org', 'environment' => 'test', 'is_active' => 1]);
        DB::table('ahg_settings')->where('setting_key', 'portable_export_enabled')->update(['setting_value' => 'true']);
        Http::preventStrayRequests();
        Http::fake([
            'https://api.test.datacite.org/*' => function (Request $r) {
                if ($r->method() === 'GET') {
                    $doi = substr(parse_url($r->url(), PHP_URL_PATH), strlen('/dois/'));

                    return isset($this->datacite[$doi])
                        ? Http::response(['data' => ['id' => $doi, 'attributes' => ['state' => ($this->datacite[$doi]['event'] ?? '') === 'publish' ? 'findable' : 'draft']]])
                        : Http::response(['errors' => []], 404);
                }
                $attrs = json_decode($r->body(), true)['data']['attributes'];
                if ($this->failWrite && ($this->failWrite)($attrs['doi'], $attrs['event'] ?? 'draft')) {
                    return Http::response(['errors' => [['title' => 'down']]], 500);
                }
                $this->datacite[$attrs['doi']] = $attrs;

                return Http::response(['data' => ['id' => $attrs['doi']]], $r->method() === 'POST' ? 201 : 200);
            },
            'https://sandbox.zenodo.org/*' => function (Request $r) {
                $path = (string) parse_url($r->url(), PHP_URL_PATH);
                $record = ['id' => 77, 'submitted' => $this->zenodoPublished, 'links' => ['bucket' => 'https://sandbox.zenodo.org/api/files/b1', 'record_html' => 'https://sandbox.zenodo.org/records/77']];
                if ($r->method() === 'POST' && $path === '/api/deposit/depositions') {
                    $this->zenodoCreates++;
                } elseif ($r->method() === 'POST' && str_ends_with($path, '/actions/publish')) {
                    $this->zenodoPublishes++;
                    $this->zenodoPublished = true;
                    $record['submitted'] = true;
                }

                return Http::response($record, 200);
            },
        ]);
        $this->svc = app(PortableReleaseService::class);
        $this->admin = $this->user();
    }

    protected function tearDown(): void
    {
        exec('chmod -R u+rwx '.escapeshellarg($this->dir).' && rm -rf '.escapeshellarg($this->dir));
        parent::tearDown();
    }

    private function user(): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        $name = 'ver'.Str::random(10);
        DB::table('user')->insert(['id' => $id, 'username' => $name, 'email' => $name.'@example.org', 'password_hash' => Hash::make('x'), 'salt' => 'x', 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        AclService::forgetUser();

        return $id;
    }

    private function io(bool $published = true): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert(['id' => $id, 'source_culture' => 'en', 'parent_id' => 1, 'lft' => 8000000 + $id * 2, 'rgt' => 8000001 + $id * 2]);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => 'T'.$id]);
        StatusRow::set($id, 158, $published ? 160 : 159);

        return $id;
    }

    private function digitalObject(int $io, int $usage, string $name): void
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitDigitalObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('digital_object')->insert(['id' => $id, 'object_id' => $io, 'usage_id' => $usage, 'name' => $name, 'path' => '/r/private-path/']);
    }

    private function rec(int $id, string $identifier = ''): array
    {
        return ['id' => $id, 'identifier' => $identifier ?: 'REF-'.$id, 'title' => 'T'.$id];
    }

    private function export(array $records, array $row = []): int
    {
        $path = $this->dir.'/export-'.Str::random(8).'.zip';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('data/ios.json', json_encode($records));
        $summary = ['records_in_scope' => count($records), 'records_included' => count($records), 'included_unpublished' => false, 'withheld' => [], 'exported_by' => 1];
        $zip->addFromString('data/disclosure-summary.json', json_encode($summary));
        $asset = 'bytes-'.Str::random(6);
        $assetPath = 'assets/ref/'.$records[0]['id'].'/scan.jpg';
        $zip->addFromString($assetPath, $asset);
        $zip->addFromString('data/digital_objects.json', '[]');
        $zip->addFromString('SHA256SUMS', hash('sha256', $asset)."  $assetPath\n");
        $zip->close();

        return (int) DB::table('portable_export')->insertGetId(array_merge(['user_id' => $this->admin, 'title' => 'Demo', 'scope_type' => 'repository',
            'scope_repository_id' => null, 'status' => 'complete', 'mode' => 'read_only', 'output_path' => $path, 'destination' => 'zip',
            'disclosure_summary' => json_encode($summary), 'created_at' => now()], $row));
    }

    private function series(int $export): int
    {
        return $this->svc->createSeries($export, ['title' => 'Verify Collection', 'creator_name' => 'Archive', 'publisher' => 'Archive', 'license' => 'cc-by-4.0'], $this->admin);
    }

    private function target(string $type = 'institution'): int
    {
        return (int) DB::table('portable_release_target')->insertGetId(['name' => 'T '.$type, 'target_type' => $type,
            'base_url' => $type === 'zenodo' ? 'https://sandbox.zenodo.org' : null,
            'api_token' => $type === 'zenodo' ? Crypt::encryptString('fake-token') : null,
            'is_active' => 1, 'created_by' => $this->admin, 'created_at' => now()]);
    }

    private function release(int $series, int $export): int
    {
        $id = $this->svc->freeze($series, $export, $this->admin);
        $this->svc->approve($id, $this->target(), $this->admin);
        $this->assertTrue($this->svc->publish($id)['ok']);

        return $id;
    }

    private function key(int $series): string
    {
        return (string) DB::table('portable_release_series')->where('id', $series)->value('series_key');
    }

    private function refused(callable $fn, string $needle): void
    {
        try {
            $fn();
            $this->fail('Expected refusal: '.$needle);
        } catch (ReleaseException $e) {
            $this->assertStringContainsString($needle, implode(' | ', $e->failures));
        }
    }

    /** @return array<string,callable(int):void> */
    private function restrictions(): array
    {
        return [
            'draft' => fn (int $id) => DB::table('status')->where('object_id', $id)->where('type_id', 158)->update(['status_id' => 159]),
            'embargo' => fn (int $id) => DB::table('embargo')->insert(['object_id' => $id, 'embargo_type' => 'full', 'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addYear()->toDateString(), 'status' => 'active', 'is_active' => 1]),
            'classified' => fn (int $id) => DB::table('object_security_classification')->insert(['object_id' => $id, 'classification_id' => 3, 'assigned_at' => now(), 'active' => 1, 'inherit_to_children' => 0]),
            'icip' => fn (int $id) => DB::table('icip_access_restriction')->insert(['information_object_id' => $id, 'restriction_type' => 'community_only', 'applies_to_descendants' => 0]),
            'odrl' => fn (int $id) => DB::table('research_rights_policy')->insert(['target_type' => 'archival_description', 'target_id' => $id, 'policy_type' => 'prohibition', 'action_type' => 'use']),
            'redacted' => fn (int $id) => DB::table('privacy_visual_redaction')->insert(['object_id' => $id, 'coordinates' => '{}']),
        ];
    }

    public function test_change_list_counts_records_that_are_not_disclosable(): void
    {
        $secret = $this->io();
        $open = $this->io();
        $e1 = $this->export([$this->rec($secret, 'SECRET-CASE-FILE'), $this->rec($open)]);
        $series = $this->series($e1);
        $this->release($series, $e1);
        DB::table('embargo')->insert(['object_id' => $secret, 'embargo_type' => 'full', 'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addYear()->toDateString(), 'status' => 'active', 'is_active' => 1]);
        $e2 = $this->export([$this->rec($open)]);
        $this->release($series, $e2);
        $key = $this->key($series);

        $this->get("/portable-release/{$key}/v2")->assertOk()->assertDontSee('SECRET-CASE-FILE');
        $path = $this->get("/portable-release/{$key}/v2/changelog")->baseResponse->getFile()->getPathname();
        $this->assertStringNotContainsString('SECRET-CASE-FILE', file_get_contents($path));
    }

    public function test_package_lists_only_the_file_tiers_it_carries(): void
    {
        $id = $this->io();
        $this->digitalObject($id, 140, 'donor-private-name.tif');
        $work = $this->dir.'/work-'.Str::random(5);
        mkdir($work.'/data', 0775, true);
        $worker = app(BundleWorkerCommand::class);
        $m = new \ReflectionMethod($worker, 'dumpData');
        $m->invoke($worker, $work, (object) ['id' => 1, 'title' => 'x', 'mode' => 'read_only', 'culture' => 'en', 'scope_type' => 'clipboard', 'branding' => null, 'created_at' => now(), 'include_masters' => 0], [$id]);
        $this->assertStringNotContainsString('donor-private-name', file_get_contents($work.'/data/digital_objects.json'));
    }

    public function test_explorer_uses_the_thumbnail_when_a_package_has_no_reference(): void
    {
        $id = $this->io();
        $this->digitalObject($id, 141, 'ref.jpg');
        $this->digitalObject($id, 142, 'thumb.jpg');
        $work = $this->dir.'/work-'.Str::random(5);
        mkdir($work.'/data', 0775, true);
        $worker = app(BundleWorkerCommand::class);
        $row = (object) ['id' => 1, 'title' => 'x', 'mode' => 'read_only', 'culture' => 'en', 'scope_type' => 'clipboard', 'branding' => null, 'created_at' => now(), 'include_references' => 0, 'include_thumbnails' => 1, 'sync_token' => null];
        (new \ReflectionMethod($worker, 'dumpData'))->invoke($worker, $work, $row, [$id]);
        (new \ReflectionMethod($worker, 'emitViewer'))->invoke($worker, $work, $row, ['descriptions' => 1, 'actors' => 0, 'repositories' => 0, 'digital_objects' => 2, 'terms' => 0]);
        $html = (string) file_get_contents($work.'/index.html');
        $this->assertSame(1, preg_match("/var refs = .*?\\.join\\(''\\);/s", $html, $m), 'Viewer image logic not found');
        $script = 'var id='.$id.';var dos='.file_get_contents($work.'/data/digital_objects.json').';'
            .'function esc(s){return s}var ioDos=dos.filter(function(d){return d.object_id===id});'.$m[0].';console.log(imgHtml);';
        file_put_contents($work.'/check.js', $script);
        $out = (string) shell_exec('node '.escapeshellarg($work.'/check.js'));
        $this->assertStringContainsString('assets/thumb/', $out, 'Viewer points at: '.trim($out));
    }

    public function test_a_findable_version_doi_keeps_a_landing_page(): void
    {
        $id = $this->io();
        $export = $this->export([$this->rec($id)]);
        $series = $this->series($export);
        $v = $this->svc->freeze($series, $export, $this->admin);
        $this->svc->approve($v, $this->target(), $this->admin);
        $doi = DB::table('portable_release')->where('id', $v)->value('version_doi');
        // The version DOI goes public, then the concept DOI step fails.
        $this->failWrite = fn ($d, $event) => $event === 'publish' && $d !== $doi;
        $this->assertFalse($this->svc->publish($v)['ok']);
        $this->assertSame('publish', $this->datacite[$doi]['event']);
        $this->failWrite = null;
        StatusRow::set($id, 158, 159);
        $this->assertFalse($this->svc->publish($v)['ok']);
        $this->refused(fn () => $this->svc->abandon($v, 'x', $this->admin), 'findable');
        // A findable DOI is permanent, so its landing address must answer (withheld), not 404.
        $this->get('/portable-release/'.$this->key($series).'/v1')->assertOk();
    }

    public function test_a_clipboard_version_must_continue_the_same_selection(): void
    {
        $a = $this->io();
        $b = $this->io();
        $e1 = $this->export([$this->rec($a)], ['scope_type' => 'clipboard', 'scope_items' => json_encode(['items' => ['first-selection']])]);
        $series = $this->series($e1);
        $this->release($series, $e1);
        $e2 = $this->export([$this->rec($b)], ['scope_type' => 'clipboard', 'scope_items' => json_encode(['items' => ['entirely-unrelated']])]);
        $this->refused(fn () => $this->svc->freeze($series, $e2, $this->admin), 'different scope');
    }

    public function test_only_a_public_address_is_accepted(): void
    {
        $id = $this->io();
        $export = $this->export([$this->rec($id)]);
        $v = $this->svc->freeze($this->series($export), $export, $this->admin);
        $accepted = [];
        foreach (['http://[::1]', 'http://intranet', 'http://100.64.0.1', 'http://100.127.255.254'] as $url) {
            config(['app.url' => $url]);
            try {
                $this->svc->publicUrl(DB::table('portable_release_series')->first());
                $accepted[] = $url;
            } catch (ReleaseException) {
            }
        }
        $this->assertSame([], $accepted, 'Accepted as public');
    }

    public function test_change_list_follows_current_disclosure_after_release(): void
    {
        $gone = $this->io();
        $open = $this->io();
        $e1 = $this->export([$this->rec($gone, 'REF-LATER'), $this->rec($open)]);
        $series = $this->series($e1);
        $this->release($series, $e1);
        $e2 = $this->export([$this->rec($open)]);
        $this->release($series, $e2);
        $key = $this->key($series);
        $this->get("/portable-release/{$key}/v2")->assertOk()->assertSee('REF-LATER');

        $this->restrictions()['embargo']($gone);
        $this->get("/portable-release/{$key}/v2")->assertOk()->assertDontSee('REF-LATER')->assertSee('Withdrawn records not listed: 1.');
        $this->get("/portable-release/{$key}/v2/changelog")->assertStatus(403);
        $this->get("/portable-release/{$key}/v2/package")->assertOk();
    }

    public function test_a_version_stopped_after_its_doi_went_public_serves_no_files_and_the_series_can_continue(): void
    {
        $id = $this->io();
        $open = $this->io();
        $export = $this->export([$this->rec($id), $this->rec($open)]);
        $series = $this->series($export);
        $v1 = $this->svc->freeze($series, $export, $this->admin);
        $this->svc->approve($v1, $this->target(), $this->admin);
        $doi = DB::table('portable_release')->where('id', $v1)->value('version_doi');
        $this->failWrite = fn ($d, $event) => $event === 'publish' && $d !== $doi;
        $this->assertFalse($this->svc->publish($v1)['ok']);
        $this->failWrite = null;
        $key = $this->key($series);

        // Still clear: the version must be finished, not skipped.
        $this->refused(fn () => $this->svc->freeze($series, $this->export([$this->rec($open)]), $this->admin), 'must be released or abandoned');
        $this->get("/portable-release/{$key}/v1/package")->assertStatus(403);

        StatusRow::set($id, 158, 159);
        $page = $this->get("/portable-release/{$key}/v1");
        $page->assertOk()->assertSee('withheld pending review')->assertDontSee('REF-'.$id);
        foreach (['package', 'manifest', 'changelog'] as $file) {
            $this->get("/portable-release/{$key}/v1/{$file}")->assertStatus(403);
        }
        $result = $this->svc->publish($v1);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('freeze a new version', $result['error']);

        $v2 = $this->release($series, $this->export([$this->rec($open)]));
        $this->assertSame(2, (int) DB::table('portable_release')->where('id', $v2)->value('version_number'));
        $concept = DB::table('portable_release_series')->where('id', $series)->value('concept_doi');
        $related = array_column($this->datacite[$concept]['relatedIdentifiers'], 'relatedIdentifier');
        $this->assertContains($doi, $related);

        // The replaced version cannot be finished later, even once its record is clear again.
        StatusRow::set($id, 158, 160);
        $this->assertFalse($this->svc->publish($v1)['ok']);
        $this->assertSame('approved', DB::table('portable_release')->where('id', $v1)->value('status'));
        $this->get("/portable-release/{$key}/v1/package")->assertStatus(403);
    }

    public function test_a_clipboard_version_may_withdraw_and_add_records(): void
    {
        $a = $this->io();
        $b = $this->io();
        $c = $this->io();
        $e1 = $this->export([$this->rec($a), $this->rec($b)], ['scope_type' => 'clipboard', 'scope_items' => json_encode(['items' => ['one', 'two']])]);
        $series = $this->series($e1);
        $this->release($series, $e1);
        $e2 = $this->export([$this->rec($a), $this->rec($c)], ['scope_type' => 'clipboard', 'scope_items' => json_encode(['items' => ['one', 'three']])]);
        $this->assertGreaterThan(0, $this->svc->freeze($series, $e2, $this->admin));
    }

    public function test_public_addresses_are_accepted(): void
    {
        $id = $this->io();
        $export = $this->export([$this->rec($id)]);
        $series = DB::table('portable_release_series')->where('id', $this->series($export))->first();
        foreach (['https://archive.example.org', 'https://8.8.8.8', 'https://[2001:4860:4860::8888]'] as $url) {
            config(['app.url' => $url]);
            $this->assertStringStartsWith($url, $this->svc->publicUrl($series));
        }
        foreach (['http://localhost.', 'http://127.1', 'http://2130706433', 'http://[fe80::1]', 'http://[::ffff:10.0.0.1]', 'http://box.internal'] as $url) {
            config(['app.url' => $url]);
            $this->refused(fn () => $this->svc->publicUrl($series), 'not a public address');
        }
    }
}
