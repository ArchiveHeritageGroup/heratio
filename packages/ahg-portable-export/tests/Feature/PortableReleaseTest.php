<?php

/**
 * PortableReleaseTest - #1586. Numbered frozen releases, version and concept
 * DOIs, approval before any deposit, corrections log, and the rule that a
 * package which failed the disclosure checks cannot be released. Every
 * DataCite and Zenodo call is faked; stray HTTP fails the test.
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Services\AclService;
use AhgCore\Support\StatusRow;
use AhgPortableExport\Services\PortableReleaseService;
use AhgPortableExport\Services\ReleaseException;
use AhgPortableExport\Services\ReleaseSchema;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PortableReleaseTest extends TestCase
{
    use DatabaseTransactions;

    private string $dir;

    private PortableReleaseService $svc;

    private int $admin;

    /** @var array<string,array> DataCite's state, by DOI */
    private array $datacite = [];

    /** @var string[] order of DataCite writes as "doi:event" */
    private array $doiWrites = [];

    private bool $dataciteDown = false;

    /** DDL commits, so it has to run before the test transaction opens. */
    protected function setUpTraits()
    {
        if (! Schema::hasTable('portable_release_deposit')
            || ! Schema::hasTable('portable_release_abandonment')
            || ! Schema::hasTable('portable_release_freeze_request')
            || ! DB::table('ahg_dropdown')->where('taxonomy', 'portable_release_status')->where('code', 'abandoned')->exists()
            || ! DB::table('ahg_dropdown')->where('taxonomy', 'portable_release_freeze_status')->exists()) {
            ReleaseSchema::install();
        }

        return parent::setUpTraits();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/release-test-'.Str::random(10);
        mkdir($this->dir, 0775, true);
        config(['heratio.storage_path' => $this->dir, 'app.url' => 'https://archive.example.org']);

        DB::table('ahg_doi_config')->update(['is_active' => 0]);
        DB::table('ahg_doi_config')->insert([
            'repository_id' => null, 'datacite_repo_id' => 'TEST.REPO', 'datacite_prefix' => '10.99999',
            'datacite_password' => 'not-a-real-secret', 'datacite_url' => 'https://api.test.datacite.org',
            'environment' => 'test', 'is_active' => 1,
        ]);
        DB::table('ahg_settings')->where('setting_key', 'portable_export_enabled')->update(['setting_value' => 'true']);

        $this->fakeHttp();
        $this->svc = app(PortableReleaseService::class);
        $this->admin = $this->user(100);
    }

    protected function tearDown(): void
    {
        exec('chmod -R u+rwx '.escapeshellarg($this->dir).' && rm -rf '.escapeshellarg($this->dir));
        parent::tearDown();
    }

    private function fakeHttp(array $zenodo = []): void
    {
        Http::preventStrayRequests();
        Http::fake(array_merge($zenodo, ['https://api.test.datacite.org/*' => function (Request $r) {
            if ($this->dataciteDown) {
                return Http::response(['errors' => [['title' => 'down']]], 500);
            }
            if ($r->method() === 'GET') {
                $doi = substr(parse_url($r->url(), PHP_URL_PATH), strlen('/dois/'));

                return isset($this->datacite[$doi])
                    ? Http::response(['data' => ['id' => $doi, 'attributes' => ['state' => ($this->datacite[$doi]['event'] ?? '') === 'publish' ? 'findable' : 'draft']]])
                    : Http::response(['errors' => []], 404);
            }
            $attrs = json_decode($r->body(), true)['data']['attributes'];
            $this->datacite[$attrs['doi']] = $attrs;
            $this->doiWrites[] = $attrs['doi'].':'.($attrs['event'] ?? 'draft');

            return Http::response(['data' => ['id' => $attrs['doi']]], $r->method() === 'POST' ? 201 : 200);
        }]));
    }

    private function user(int $group): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        $name = 'rel'.Str::random(10);
        DB::table('user')->insert(['id' => $id, 'username' => $name, 'email' => $name.'@example.org', 'password_hash' => Hash::make('x'), 'salt' => 'x', 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => $group]);
        AclService::forgetUser();

        return $id;
    }

    private function io(bool $published = true): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert(['id' => $id, 'source_culture' => 'en', 'lft' => 8000000 + $id * 2, 'rgt' => 8000001 + $id * 2]);
        StatusRow::set($id, 158, $published ? 160 : 159);

        return $id;
    }

    private function rec(int $id, string $title, string $identifier = ''): array
    {
        return ['id' => $id, 'identifier' => $identifier ?: 'REF-'.$id, 'title' => $title, 'lft' => random_int(1, 9999), 'rgt' => random_int(10000, 20000)];
    }

    /** A package in the shape the bundle worker writes. */
    private function export(array $records, array $opts = []): int
    {
        $path = $this->dir.'/export-'.Str::random(8).'.zip';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('data/ios.json', json_encode($records));
        $summary = ['records_in_scope' => count($records), 'records_included' => count($records), 'included_unpublished' => $opts['unpublished'] ?? false, 'withheld' => ['unpublished' => 0], 'exported_by' => 1];
        if (! ($opts['no_summary'] ?? false)) {
            $zip->addFromString('data/disclosure-summary.json', json_encode($summary));
        }
        $asset = 'file-bytes-'.Str::random(4);
        $assetId = $opts['asset_for'] ?? ($records[0]['id'] ?? 0);
        $assetPath = "assets/ref/$assetId/scan.jpg";
        $zip->addFromString($assetPath, $asset);
        if ($opts['master'] ?? false) {
            $zip->addFromString("assets/master/$assetId/original.tif", 'master-bytes');
        }
        $zip->addFromString('data/digital_objects.json', json_encode($opts['objects'] ?? []));
        $sums = ($opts['bad_sum'] ?? false ? str_repeat('0', 64) : hash('sha256', $asset))."  $assetPath\n";
        if ($opts['master'] ?? false) {
            $sums .= hash('sha256', 'master-bytes')."  assets/master/$assetId/original.tif\n";
        }
        if ($opts['unlisted_asset'] ?? false) {
            $sums = '';
        }
        if (! ($opts['no_sums'] ?? false)) {
            $zip->addFromString('SHA256SUMS', $sums);
        }
        $zip->addFromString('index.html', '<html></html>');
        $zip->close();

        return (int) DB::table('portable_export')->insertGetId(array_merge([
            'user_id' => $this->admin ?? 1, 'title' => 'Demo', 'scope_type' => 'repository', 'scope_repository_id' => null,
            'status' => $opts['status'] ?? 'complete', 'output_path' => $path, 'destination' => 'zip',
            'disclosure_summary' => ($opts['no_summary'] ?? false) ? null : json_encode($summary),
            'created_at' => now(),
        ], $opts['row'] ?? []));
    }

    private function series(int $export): int
    {
        return $this->svc->createSeries($export, ['title' => 'Demonstration Collection', 'creator_name' => 'Test Archive', 'publisher' => 'Test Archive', 'license' => 'cc-by-4.0', 'description' => 'A demonstration.'], $this->admin);
    }

    private function target(string $type = 'institution'): int
    {
        return (int) DB::table('portable_release_target')->insertGetId([
            'name' => 'Target '.$type, 'target_type' => $type,
            'base_url' => $type === 'zenodo' ? 'https://sandbox.zenodo.org' : null,
            'api_token' => $type === 'zenodo' ? Crypt::encryptString('zenodo-test-token') : null,
            'is_active' => 1, 'created_by' => $this->admin, 'created_at' => now(),
        ]);
    }

    private function publishV(int $series, int $export, ?int $target = null): int
    {
        $id = $this->svc->freeze($series, $export, $this->admin);
        $this->svc->approve($id, $target ?? $this->target(), $this->admin);
        $result = $this->svc->publish($id);
        $this->assertTrue($result['ok'], (string) $result['error']);

        return $id;
    }

    private function assertRefused(callable $fn, string $needle): void
    {
        try {
            $fn();
            $this->fail('Expected the release step to be refused: '.$needle);
        } catch (ReleaseException $e) {
            $this->assertStringContainsString($needle, implode(' | ', $e->failures));
        }
    }

    private function relations(string $doi): array
    {
        return collect($this->datacite[$doi]['relatedIdentifiers'] ?? [])->map(fn ($r) => $r['relationType'].':'.$r['relatedIdentifier'])->all();
    }

    public function test_v1_and_v2_carry_dois_relations_manifest_and_a_changelog(): void
    {
        [$a, $b, $c, $d] = [$this->io(), $this->io(), $this->io(), $this->io()];
        $series = $this->series($e1 = $this->export([$this->rec($a, 'Alpha'), $this->rec($b, 'Beta'), $this->rec($c, 'Gamma')]));
        $v1 = $this->publishV($series, $e1);

        $s = DB::table('portable_release_series')->find($series);
        $r1 = DB::table('portable_release')->find($v1);
        $this->assertSame(1, (int) $r1->version_number);
        $this->assertSame('10.99999/portable.'.$s->series_key, $s->concept_doi);
        $this->assertSame($s->concept_doi.'.v1', $r1->version_doi);
        $this->assertSame('publish', $this->datacite[$r1->version_doi]['event']);
        $this->assertContains('IsVersionOf:'.$s->concept_doi, $this->relations($r1->version_doi));
        $this->assertContains('HasVersion:'.$r1->version_doi, $this->relations($s->concept_doi));

        // Manifest: a SHA-256 per file, matching what is really in the frozen zip.
        $manifest = json_decode(file_get_contents($r1->frozen_path.'/release-manifest.json'), true);
        $this->assertSame(hash_file('sha256', $r1->frozen_path.'/package.zip'), $manifest['package']['sha256']);
        $zip = new \ZipArchive;
        $zip->open($r1->frozen_path.'/package.zip');
        foreach ($manifest['files'] as $f) {
            $this->assertSame(hash('sha256', $zip->getFromName($f['path'])), $f['sha256'], $f['path']);
        }
        $this->assertSame($zip->numFiles, count($manifest['files']));
        $zip->close();
        $this->assertSame('0444', substr(sprintf('%o', fileperms($r1->frozen_path.'/package.zip')), -4));

        $this->svc->addCorrection($series, ['correction_type' => 'corrected_figure', 'record_ref' => 'REF-'.$b, 'field_name' => 'extent', 'old_value' => '12 boxes', 'new_value' => '21 boxes', 'reason' => 'Transposed digits'], $this->admin);

        // v2: Beta edited, Gamma withdrawn, Delta added; Alpha only moved in the tree.
        $e2 = $this->export([$this->rec($a, 'Alpha'), $this->rec($b, 'Beta corrected'), $this->rec($d, 'Delta')]);
        $v2 = $this->publishV($series, $e2);
        $r2 = DB::table('portable_release')->find($v2);
        $changes = json_decode($r2->changelog_json, true);

        $this->assertSame([$d], array_column($changes['added'], 'id'));
        $this->assertSame([$c], array_column($changes['removed'], 'id'));
        $this->assertSame([$b], array_column($changes['changed'], 'id'));
        $this->assertSame(['title'], $changes['changed'][0]['fields']);
        $this->assertSame('21 boxes', $changes['corrections'][0]['new_value']);
        $this->assertSame((int) $v2, (int) DB::table('portable_release_correction')->where('series_id', $series)->value('release_id'));

        $log = file_get_contents($r2->frozen_path.'/CHANGELOG.md');
        $this->assertStringContainsString('Changes from v1', $log);
        $this->assertStringContainsString('was "12 boxes", now "21 boxes"', $log);
        $this->assertStringNotContainsString('Gamma', $log, 'The public changelog must not repeat a withdrawn record\'s content.');
        $this->assertStringNotContainsString('Beta corrected', $log);

        $this->assertContains('IsNewVersionOf:'.$r1->version_doi, $this->relations($r2->version_doi));
        $this->assertContains('IsPreviousVersionOf:'.$r2->version_doi, $this->relations($r1->version_doi));
        $this->assertContains('HasVersion:'.$r2->version_doi, $this->relations($s->concept_doi));

        // The concept address always leads to the newest version; v1 stays citable.
        $this->get('/portable-release/'.$s->series_key)->assertRedirect('/portable-release/'.$s->series_key.'/v2');
        $this->get('/portable-release/'.$s->series_key.'/v1')->assertOk()->assertSee('A newer version exists');
        $this->get('/portable-release/'.$s->series_key.'/v2')->assertOk()->assertSee($r2->version_doi)->assertSee('Transposed digits');
        $this->assertSame($manifest['package']['sha256'], hash_file('sha256', $r1->frozen_path.'/package.zip'), 'v1 is unchanged by v2');
    }

    public function test_a_package_that_failed_the_disclosure_checks_cannot_be_released(): void
    {
        $ok = $this->io();
        $series = $this->series($this->export([$this->rec($ok, 'Fine')]));

        $draft = $this->io(false);
        $embargoed = $this->io();
        DB::table('embargo')->insert(['object_id' => $embargoed, 'embargo_type' => 'full', 'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addYear()->toDateString(), 'is_perpetual' => 0, 'status' => 'active', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $redacted = $this->io();
        DB::table('privacy_visual_redaction')->insert($this->redactionRow($redacted));

        $cases = [
            'unpublished record' => [$this->export([$this->rec($ok, 'Fine'), $this->rec($draft, 'Draft')]), 'not cleared'],
            'embargoed record' => [$this->export([$this->rec($embargoed, 'Embargoed')]), 'not cleared'],
            'include-unpublished override' => [$this->export([$this->rec($ok, 'Fine')], ['unpublished' => true]), 'include-unpublished'],
            'no disclosure summary' => [$this->export([$this->rec($ok, 'Fine')], ['no_summary' => true]), 'no disclosure summary'],
            'original of a redacted record' => [$this->export([$this->rec($redacted, 'Has PII')]), 'PII redactions'],
            'file for an unlisted record' => [$this->export([$this->rec($ok, 'Fine')], ['asset_for' => $draft]), 'does not list'],
            'broken fixity' => [$this->export([$this->rec($ok, 'Fine')], ['bad_sum' => true]), 'Fixity check failed'],
            'unfinished export' => [$this->export([$this->rec($ok, 'Fine')], ['status' => 'failed']), 'has not completed'],
            'no SHA256SUMS' => [$this->export([$this->rec($ok, 'Fine')], ['no_sums' => true]), 'no SHA256SUMS'],
            'asset missing from SHA256SUMS' => [$this->export([$this->rec($ok, 'Fine')], ['unlisted_asset' => true]), 'not covered by SHA256SUMS'],
            'master file in the package' => [$this->export([$this->rec($ok, 'Fine')], ['master' => true]), 'master files'],
            'built with include_masters' => [$this->export([$this->rec($ok, 'Fine')], ['row' => ['include_masters' => 1]]), 'master files'],
            'researcher offline copy' => [$this->export([$this->rec($ok, 'Fine')], ['row' => ['researcher_user_id' => $this->admin, 'group_source' => 'project', 'sync_token' => str_repeat('a', 32), 'mode' => 'editable']]), 'private or editable'],
            'editable mode alone' => [$this->export([$this->rec($ok, 'Fine')], ['row' => ['mode' => 'editable']]), 'private or editable'],
            'file name of a redacted record' => [$this->export([$this->rec($ok, 'Fine'), $this->rec($redacted, 'Has PII')], ['asset_for' => $ok, 'objects' => [['id' => 1, 'object_id' => $redacted, 'usage_id' => 140, 'name' => 'jane-doe-id.jpg', 'path' => '/r/x/']]]), 'file names'],
        ];
        foreach ($cases as $name => [$export, $needle]) {
            $this->assertRefused(fn () => $this->svc->freeze($series, $export, $this->admin), $needle);
            $this->assertSame(0, DB::table('portable_release')->where('series_id', $series)->count(), $name);
        }
        Http::assertNothingSent();
        $key = DB::table('portable_release_series')->where('id', $series)->value('series_key');
        $this->assertSame([], glob($this->dir.'/portable-releases/'.$key.'/*') ?: [], 'A refused package leaves nothing behind');
    }

    private function redactionRow(int $objectId): array
    {
        $row = ['object_id' => $objectId];
        foreach (DB::select('DESCRIBE privacy_visual_redaction') as $col) {
            if ($col->Null === 'NO' && $col->Default === null && ! str_contains($col->Extra, 'auto_increment') && ! isset($row[$col->Field])) {
                $row[$col->Field] = match (true) {
                    str_contains($col->Type, 'int'), str_contains($col->Type, 'decimal'), str_contains($col->Type, 'float') => 1,
                    str_contains($col->Type, 'json') => '{}',
                    str_contains($col->Type, 'date'), str_contains($col->Type, 'time') => now(),
                    default => 'x',
                };
            }
        }

        return $row;
    }

    /** The real bundle worker's output, both as a zip and as a folder, must be releasable. */
    public function test_a_package_built_by_the_real_worker_can_be_released(): void
    {
        DB::table('ahg_settings')->where('setting_key', 'portable_export_include_unpublished')->update(['setting_value' => '0']);
        $good = $this->io();
        $draft = $this->io(false);
        foreach ([$good, $draft] as $io) {
            DB::table('information_object_i18n')->insert(['id' => $io, 'culture' => 'en', 'title' => 'Worker record '.$io]);
            DB::table('slug')->insert(['object_id' => $io, 'slug' => 'worker-record-'.$io]);
        }

        foreach (['zip', 'folder'] as $destination) {
            $export = (int) DB::table('portable_export')->insertGetId([
                'user_id' => $this->admin, 'title' => 'Worker '.$destination, 'scope_type' => 'clipboard',
                'scope_items' => json_encode(['items' => ['worker-record-'.$good, 'worker-record-'.$draft]]),
                'mode' => 'read_only', 'destination' => $destination,
                'destination_path' => $destination === 'folder' ? $this->dir.'/built' : null,
                'output_path' => $destination === 'zip' ? $this->dir.'/built-'.Str::random(6).'.zip' : null,
                'culture' => 'en', 'status' => 'pending', 'created_at' => now(),
            ]);
            $this->artisan('ahg:portable-export-worker', ['--id' => $export])->assertExitCode(0);
            $this->assertSame('complete', DB::table('portable_export')->where('id', $export)->value('status'));

            $series = $this->series($export);
            $id = $this->svc->freeze($series, $export, $this->admin);
            $row = DB::table('portable_release')->find($id);

            $this->assertSame(1, (int) $row->record_count, 'The worker withheld the draft, so the release holds one record');
            $paths = array_column(json_decode(file_get_contents($row->frozen_path.'/release-manifest.json'), true)['files'], 'path');
            $this->assertContains('data/ios.json', $paths);
            $this->assertContains('verify.sh', $paths);
        }
    }

    public function test_the_worker_does_not_list_a_redacted_records_file_names(): void
    {
        DB::table('ahg_settings')->where('setting_key', 'portable_export_include_unpublished')->update(['setting_value' => '0']);
        $redacted = $this->io();
        DB::table('information_object_i18n')->insert(['id' => $redacted, 'culture' => 'en', 'title' => 'Redacted '.$redacted]);
        DB::table('slug')->insert(['object_id' => $redacted, 'slug' => 'redacted-record-'.$redacted]);
        DB::table('privacy_visual_redaction')->insert($this->redactionRow($redacted));
        $objectId = (int) DB::table('object')->insertGetId(['class_name' => 'QubitDigitalObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('digital_object')->insert(['id' => $objectId, 'object_id' => $redacted, 'usage_id' => 140, 'name' => 'jane-doe-id.jpg', 'path' => '/secret/path/', 'mime_type' => 'image/jpeg', 'byte_size' => 1]);

        $export = (int) DB::table('portable_export')->insertGetId([
            'user_id' => $this->admin, 'title' => 'Redaction', 'scope_type' => 'clipboard',
            'scope_items' => json_encode(['items' => ['redacted-record-'.$redacted]]), 'mode' => 'read_only',
            'destination' => 'zip', 'output_path' => $this->dir.'/redaction.zip', 'culture' => 'en', 'status' => 'pending', 'created_at' => now(),
        ]);
        $this->artisan('ahg:portable-export-worker', ['--id' => $export])->assertExitCode(0);

        $zip = new \ZipArchive;
        $zip->open($this->dir.'/redaction.zip');
        $listed = (string) $zip->getFromName('data/digital_objects.json');
        $zip->close();
        $this->assertSame([], json_decode($listed, true));
        $this->assertStringNotContainsString('jane-doe', $listed);

        $series = $this->series($export);
        $id = $this->svc->freeze($series, $export, $this->admin);
        $this->assertSame('frozen', DB::table('portable_release')->where('id', $id)->value('status'));
    }

    public function test_a_researcher_export_is_not_offered_for_release(): void
    {
        $io = $this->io();
        $private = $this->export([$this->rec($io, 'One')], ['row' => ['researcher_user_id' => $this->admin, 'group_source' => 'project', 'sync_token' => str_repeat('b', 32), 'mode' => 'editable']]);
        $master = $this->export([$this->rec($io, 'One')], ['row' => ['include_masters' => 1]]);
        $public = $this->export([$this->rec($io, 'One')]);

        $ids = \AhgPortableExport\Services\ReleaseGate::whereReleasable(DB::table('portable_export')->whereIn('id', [$private, $master, $public]))->pluck('id')->map('intval')->all();
        $this->assertSame([$public], $ids);

        $this->actingAs(User::find($this->admin))->post('/portable-export/releases/series', ['export_id' => $private, 'title' => 'No', 'creator_name' => 'A', 'publisher' => 'A'])
            ->assertSessionHasErrors('export_id');
    }

    public function test_a_frozen_version_with_a_restricted_record_can_be_abandoned_and_replaced(): void
    {
        [$a, $b] = [$this->io(), $this->io()];
        $series = $this->series($e1 = $this->export([$this->rec($a, 'Alpha')]));
        $v1 = $this->publishV($series, $e1);

        $this->svc->addCorrection($series, ['correction_type' => 'other', 'reason' => 'Carried forward'], $this->admin);
        $e2 = $this->export([$this->rec($a, 'Alpha'), $this->rec($b, 'Beta')]);
        $v2 = $this->svc->freeze($series, $e2, $this->admin);
        StatusRow::set($b, 158, 159);

        $this->assertRefused(fn () => $this->svc->approve($v2, $this->target(), $this->admin), 'not cleared');
        $this->assertRefused(fn () => $this->svc->freeze($series, $this->export([$this->rec($a, 'Alpha')]), $this->admin), 'released or abandoned');

        $editor = User::find($this->user(101));
        $this->actingAs($editor)->post("/portable-export/releases/$v2/abandon", ['reason' => 'x', 'confirm_abandon' => '1'])->assertForbidden();
        $admin = User::find($this->admin);
        $this->actingAs($admin)->post("/portable-export/releases/$v2/abandon", ['reason' => 'x'])->assertSessionHasErrors('confirm_abandon');
        $this->assertSame('frozen', DB::table('portable_release')->where('id', $v2)->value('status'));
        $this->actingAs($admin)->post("/portable-export/releases/$v2/abandon", ['reason' => 'Beta was restricted', 'confirm_abandon' => '1'])->assertSessionHas('success');
        $this->actingAs($admin)->get("/portable-export/releases/series/$series")->assertOk()->assertSee('Beta was restricted');

        $row = DB::table('portable_release')->find($v2);
        $this->assertSame('abandoned', $row->status);
        $this->assertSame('Beta was restricted', DB::table('portable_release_abandonment')->where('release_id', $v2)->value('reason'));
        $this->assertNull(DB::table('portable_release_correction')->where('series_id', $series)->value('release_id'));
        $this->assertTrue(DB::table('ahg_audit_log')->where('entity_type', 'portable_release')->where('entity_id', $v2)->exists());
        $this->assertFalse($this->svc->publish($v2)['ok']);
        $this->assertNull(DB::table('portable_release')->where('id', $v2)->value('last_error'));

        $v3 = $this->publishV($series, $this->export([$this->rec($a, 'Alpha changed')]));
        $r3 = DB::table('portable_release')->find($v3);
        $r1 = DB::table('portable_release')->find($v1);
        $this->assertSame(3, (int) $r3->version_number, 'An abandoned number is never reused');
        $changes = json_decode($r3->changelog_json, true);
        $this->assertSame([], $changes['removed'], 'The diff base is v1, so Beta was never public and is not reported');
        $this->assertSame('Carried forward', $changes['corrections'][0]['reason']);
        $this->assertContains('IsNewVersionOf:'.$r1->version_doi, $this->relations($r3->version_doi));
        $this->assertNotContains('HasVersion:'.$row->version_doi, $this->relations(DB::table('portable_release_series')->where('id', $series)->value('concept_doi')));
        $key = DB::table('portable_release_series')->where('id', $series)->value('series_key');
        $this->get("/portable-release/$key/v2")->assertNotFound();
    }

    public function test_a_version_with_anything_public_cannot_be_abandoned(): void
    {
        $series = $this->series($e = $this->export([$this->rec($this->io(), 'One')]));
        $released = $this->publishV($series, $e);
        $this->assertRefused(fn () => $this->svc->abandon($released, 'x', $this->admin), 'Only a frozen or approved');

        // The local row says draft, but a crash left the DOI findable at DataCite.
        $id = $this->svc->freeze($series, $this->export([$this->rec($this->io(), 'Two')]), $this->admin);
        $this->svc->approve($id, $this->target(), $this->admin);
        $doi = DB::table('portable_release')->where('id', $id)->value('version_doi');
        DB::table('portable_release')->where('id', $id)->update(['doi_state' => 'draft']);
        $this->datacite[$doi] = ['doi' => $doi, 'event' => 'publish'];
        $this->assertRefused(fn () => $this->svc->abandon($id, 'x', $this->admin), 'already public');
        $this->assertSame('approved', DB::table('portable_release')->where('id', $id)->value('status'));
    }

    public function test_abandoning_after_a_failed_zenodo_publish_deletes_the_draft_and_refuses_a_published_one(): void
    {
        $submitted = false;
        $deleted = 0;
        Http::swap(new \Illuminate\Http\Client\Factory);
        $this->fakeHttp(['https://sandbox.zenodo.org/*' => function (Request $r) use (&$submitted, &$deleted) {
            $rec = ['id' => 777, 'submitted' => $submitted, 'links' => ['bucket' => 'https://sandbox.zenodo.org/api/files/b', 'record_html' => 'https://sandbox.zenodo.org/records/777']];
            if ($r->method() === 'DELETE') {
                $deleted++;

                return Http::response(null, 204);
            }
            if (str_ends_with($r->url(), '/actions/publish')) {
                return Http::response(['message' => 'later'], 500);
            }

            return Http::response($rec, $r->method() === 'POST' ? 201 : 200);
        }]);

        $series = $this->series($e = $this->export([$this->rec($this->io(), 'One')]));
        $id = $this->svc->freeze($series, $e, $this->admin);
        $this->svc->approve($id, $this->target('zenodo'), $this->admin);
        $this->assertFalse($this->svc->publish($id)['ok']);

        $submitted = true;
        $this->assertRefused(fn () => $this->svc->abandon($id, 'x', $this->admin), 'already published at Zenodo');
        $this->assertSame(0, $deleted);

        $submitted = false;
        $this->svc->abandon($id, 'Draft only', $this->admin);
        $this->assertSame(1, $deleted);
        $this->assertSame('abandoned', DB::table('portable_release')->where('id', $id)->value('status'));
    }

    public function test_freeze_runs_as_a_recorded_job_and_clears_a_dead_workers_leftovers(): void
    {
        $series = $this->series($e = $this->export([$this->rec($this->io(), 'One')]));
        $key = DB::table('portable_release_series')->where('id', $series)->value('series_key');
        $leftover = $this->dir.'/portable-releases/'.$key.'/.tmp-deadworker1';
        mkdir($leftover, 0775, true);
        file_put_contents($leftover.'/package.zip', 'partial');

        \Illuminate\Support\Facades\Queue::fake();
        $this->actingAs(User::find($this->admin))->post("/portable-export/releases/series/$series/freeze", ['export_id' => $e])->assertSessionHas('success');
        \Illuminate\Support\Facades\Queue::assertPushed(\AhgPortableExport\Jobs\FreezeReleaseJob::class);
        $this->assertSame(0, DB::table('portable_release')->where('series_id', $series)->count(), 'Nothing is frozen inside the web request');
        $request = DB::table('portable_release_freeze_request')->where('series_id', $series)->first();
        $this->assertSame('queued', $request->status);
        $this->assertRefused(fn () => $this->svc->requestFreeze($series, $e, $this->admin), 'being frozen');

        (new \AhgPortableExport\Jobs\FreezeReleaseJob((int) $request->id))->handle($this->svc);
        $done = DB::table('portable_release_freeze_request')->find($request->id);
        $this->assertSame('done', $done->status);
        $this->assertNotNull($done->release_id);
        $this->assertDirectoryDoesNotExist($leftover);
        $this->actingAs(User::find($this->admin))->get("/portable-export/releases/series/$series")->assertOk()->assertSee('Recent freezes');

        // A refused package is recorded where the archivist will see it.
        $this->svc->abandon((int) $done->release_id, 'Next', $this->admin);
        $bad = $this->export([$this->rec($this->io(false), 'Draft')]);
        $failed = $this->svc->requestFreeze($series, $bad, $this->admin);
        $this->svc->runFreezeRequest($failed);
        $row = DB::table('portable_release_freeze_request')->find($failed);
        $this->assertSame('failed', $row->status);
        $this->assertStringContainsString('not cleared', $row->failures);

        // A worker that died without reporting does not block the series for good.
        $dead = $this->svc->requestFreeze($series, $this->export([$this->rec($this->io(), 'Three')]), $this->admin);
        DB::table('portable_release_freeze_request')->where('id', $dead)->update(['status' => 'running', 'started_at' => now()->subSeconds(PortableReleaseService::FREEZE_STALE_SECONDS + 10)]);
        $next = $this->svc->requestFreeze($series, $this->export([$this->rec($this->io(), 'Four')]), $this->admin);
        $this->assertSame('failed', DB::table('portable_release_freeze_request')->where('id', $dead)->value('status'));
        $this->assertSame('queued', DB::table('portable_release_freeze_request')->where('id', $next)->value('status'));
    }

    public function test_the_public_recheck_does_not_need_the_private_fingerprints(): void
    {
        $io = $this->io();
        $series = $this->series($e = $this->export([$this->rec($io, 'One')]));
        $id = $this->svc->freeze($series, $e, $this->admin);
        $row = DB::table('portable_release')->find($id);
        chmod($row->frozen_path, 0775);
        chmod($row->frozen_path.'/record-index.json', 0664);
        unlink($row->frozen_path.'/record-index.json');

        $this->assertSame([], $this->svc->recheck($row));
        StatusRow::set($io, 158, 159);
        $this->assertNotSame([], $this->svc->recheck($row));
    }

    public function test_nothing_is_sent_before_approval(): void
    {
        $series = $this->series($e = $this->export([$this->rec($this->io(), 'One')]));
        $id = $this->svc->freeze($series, $e, $this->admin);

        $result = $this->svc->publish($id);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('not been approved', $result['error']);
        Http::assertNothingSent();
        $this->assertSame('frozen', DB::table('portable_release')->where('id', $id)->value('status'));
    }

    public function test_a_record_restricted_after_the_freeze_blocks_approval(): void
    {
        $io = $this->io();
        $series = $this->series($e = $this->export([$this->rec($io, 'One')]));
        $id = $this->svc->freeze($series, $e, $this->admin);

        StatusRow::set($io, 158, 159);

        $this->assertRefused(fn () => $this->svc->approve($id, $this->target(), $this->admin), 'not cleared');
        Http::assertNothingSent();
    }

    public function test_a_frozen_file_that_changed_cannot_be_published(): void
    {
        $series = $this->series($e = $this->export([$this->rec($this->io(), 'One')]));
        $id = $this->svc->freeze($series, $e, $this->admin);
        $path = DB::table('portable_release')->where('id', $id)->value('frozen_path');
        chmod($path, 0775);
        chmod($path.'/CHANGELOG.md', 0664);
        file_put_contents($path.'/CHANGELOG.md', 'edited');

        $this->assertRefused(fn () => $this->svc->approve($id, $this->target(), $this->admin), 'has changed');
    }

    public function test_zenodo_deposit_uses_the_release_doi_and_a_failure_resumes_without_a_second_deposit(): void
    {
        $creates = 0;
        $publishFails = true;
        $auth = [];
        Http::swap(new \Illuminate\Http\Client\Factory);
        $this->fakeHttp(['https://sandbox.zenodo.org/*' => function (Request $r) use (&$creates, &$publishFails, &$auth) {
            $auth[] = $r->header('Authorization')[0] ?? '';
            $path = parse_url($r->url(), PHP_URL_PATH);
            $rec = ['id' => 555, 'submitted' => false, 'links' => ['bucket' => 'https://sandbox.zenodo.org/api/files/bucket-1', 'record_html' => 'https://sandbox.zenodo.org/records/555']];
            if ($r->method() === 'POST' && $path === '/api/deposit/depositions') {
                $creates++;

                return Http::response($rec, 201);
            }
            if (str_ends_with($path, '/actions/publish')) {
                if ($publishFails) {
                    return Http::response(['message' => 'try later'], 500);
                }

                return Http::response(['submitted' => true] + $rec, 202);
            }

            return Http::response($rec, 200);
        }]);

        $series = $this->series($e = $this->export([$this->rec($this->io(), 'One')]));
        $id = $this->svc->freeze($series, $e, $this->admin);
        $r = DB::table('portable_release')->find($id);
        $this->svc->approve($id, $this->target('zenodo'), $this->admin);

        $first = $this->svc->publish($id);
        $this->assertFalse($first['ok']);
        $this->assertSame('approved', DB::table('portable_release')->where('id', $id)->value('status'));
        $this->assertSame('draft', $this->datacite[$r->version_doi]['event'] ?? 'draft', 'No DOI is findable while the deposit has failed');
        $this->assertNotContains($r->version_doi.':publish', $this->doiWrites);
        $this->assertSame('555', DB::table('portable_release_deposit')->where('release_id', $id)->value('external_id'));

        $publishFails = false;
        $second = $this->svc->publish($id);
        $this->assertTrue($second['ok'], (string) $second['error']);
        $this->assertSame(1, $creates, 'The retry must reuse the deposit');
        $this->assertSame('released', DB::table('portable_release')->where('id', $id)->value('status'));
        $this->assertSame(['Bearer zenodo-test-token'], array_values(array_unique($auth)));

        $meta = collect(Http::recorded())->map(fn ($p) => $p[0])
            ->first(fn ($q) => $q->method() === 'PUT' && str_ends_with(parse_url($q->url(), PHP_URL_PATH), '/depositions/555'));
        $this->assertSame($r->version_doi, json_decode($meta->body(), true)['metadata']['doi']);

        // The token never goes to DataCite.
        Http::assertNotSent(fn ($q) => str_contains($q->url(), 'datacite') && $q->hasHeader('Authorization', 'Bearer zenodo-test-token'));
    }

    public function test_a_datacite_failure_stays_retryable(): void
    {
        $series = $this->series($e = $this->export([$this->rec($this->io(), 'One')]));
        $id = $this->svc->freeze($series, $e, $this->admin);
        $this->svc->approve($id, $this->target(), $this->admin);

        $this->dataciteDown = true;
        $this->assertFalse($this->svc->publish($id)['ok']);
        $row = DB::table('portable_release')->find($id);
        $this->assertSame('approved', $row->status);
        $this->assertNotEmpty($row->last_error);
        $key = DB::table('portable_release_series')->where('id', $series)->value('series_key');
        $this->get('/portable-release/'.$key.'/v1')->assertNotFound();

        $this->dataciteDown = false;
        $this->assertTrue($this->svc->publish($id)['ok']);
        $this->get('/portable-release/'.$key.'/v1')->assertOk();
    }

    public function test_a_private_app_url_is_refused_before_a_doi_is_registered(): void
    {
        $series = $this->series($e = $this->export([$this->rec($this->io(), 'One')]));
        $id = $this->svc->freeze($series, $e, $this->admin);
        config(['app.url' => 'http://192.168.0.112:8090']);

        $this->assertRefused(fn () => $this->svc->approve($id, $this->target(), $this->admin), 'not a public address');
        Http::assertNothingSent();
    }

    public function test_a_released_version_is_withheld_when_a_record_later_becomes_restricted(): void
    {
        $io = $this->io();
        $series = $this->series($e = $this->export([$this->rec($io, 'One')]));
        $this->publishV($series, $e);
        $key = DB::table('portable_release_series')->where('id', $series)->value('series_key');
        $this->get("/portable-release/$key/v1/package")->assertOk();

        StatusRow::set($io, 158, 159);

        $this->get("/portable-release/$key/v1/package")->assertForbidden();
        $this->get("/portable-release/$key/v1/manifest")->assertForbidden();
        $this->get("/portable-release/$key/v1")->assertOk()->assertSee('withheld pending review')->assertDontSee('Download package');
    }

    public function test_an_unreleased_version_is_not_public(): void
    {
        $series = $this->series($e = $this->export([$this->rec($this->io(), 'One')]));
        $this->svc->freeze($series, $e, $this->admin);
        $key = DB::table('portable_release_series')->where('id', $series)->value('series_key');

        $this->get("/portable-release/$key")->assertNotFound();
        $this->get("/portable-release/$key/v1")->assertNotFound();
        $this->get("/portable-release/$key/v1/package")->assertNotFound();
    }

    public function test_staff_pages_are_administrator_only(): void
    {
        $this->assertAdminGated($this->get('/portable-export/releases'));

        $editor = User::find($this->user(101));
        $this->actingAs($editor)->get('/portable-export/releases')->assertForbidden();
        $this->actingAs($editor)->post('/portable-export/releases/targets', ['name' => 'x', 'target_type' => 'institution'])->assertForbidden();
    }

    public function test_the_workflow_runs_through_the_pages_and_never_shows_the_token(): void
    {
        $admin = User::find($this->admin);
        $io = $this->io();
        $export = $this->export([$this->rec($io, 'One')]);

        $this->actingAs($admin)->post('/portable-export/releases/targets', ['name' => 'Bad', 'target_type' => 'zenodo', 'base_url' => 'https://evil.example.org', 'api_token' => 'abc'])
            ->assertSessionHasErrors('base_url');
        $this->actingAs($admin)->post('/portable-export/releases/targets', ['name' => 'Sandbox', 'target_type' => 'zenodo', 'base_url' => 'https://sandbox.zenodo.org', 'api_token' => 'visible-if-buggy'])->assertSessionHasNoErrors();
        $stored = DB::table('portable_release_target')->where('name', 'Sandbox')->value('api_token');
        $this->assertNotSame('visible-if-buggy', $stored);
        $this->assertSame('visible-if-buggy', Crypt::decryptString($stored));
        $this->actingAs($admin)->post('/portable-export/releases/targets', ['name' => 'Our repository', 'target_type' => 'institution'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->get('/portable-export/releases')->assertOk()->assertSee('Sandbox')->assertDontSee('visible-if-buggy')->assertDontSee($stored);

        $this->actingAs($admin)->post('/portable-export/releases/series', ['export_id' => $export, 'title' => 'Paged', 'creator_name' => 'Archive', 'publisher' => 'Archive'])->assertRedirect();
        $series = (int) DB::table('portable_release_series')->where('title', 'Paged')->value('id');
        $this->actingAs($admin)->post("/portable-export/releases/series/$series/corrections", ['correction_type' => 'not-a-code', 'reason' => 'x'])->assertSessionHasErrors('correction_type');
        $this->actingAs($admin)->post("/portable-export/releases/series/$series/freeze", ['export_id' => $export])->assertSessionHas('success');
        $this->actingAs($admin)->get("/portable-export/releases/series/$series")->assertOk()->assertSee('Approve and publish');

        $id = (int) DB::table('portable_release')->where('series_id', $series)->value('id');
        $target = (int) DB::table('portable_release_target')->where('name', 'Our repository')->value('id');
        $this->actingAs($admin)->post("/portable-export/releases/$id/approve", ['target_id' => $target])->assertSessionHasErrors('confirm');
        Http::assertNothingSent();
        $this->actingAs($admin)->post("/portable-export/releases/$id/approve", ['target_id' => $target, 'confirm' => '1'])->assertSessionHas('success');
        $this->assertSame('released', DB::table('portable_release')->where('id', $id)->value('status'));
    }
}
