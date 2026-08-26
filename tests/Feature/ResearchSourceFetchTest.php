<?php

namespace Tests\Feature;

use AhgResearch\Services\ResearchQuotaService;
use AhgResearch\Services\ResearchSourceFetchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * #1492 - fetching a document from an external URL into a research workspace.
 *
 * The point of these tests is the SSRF surface. Fetching a user-supplied URL
 * server-side is the classic way an application is turned into a proxy onto its
 * own private network, and the failure is silent: it looks like a working
 * feature right up until someone asks it for 169.254.169.254.
 *
 * No test here reaches the network. Every case asserts a REJECTION before any
 * request is made, so the suite stays deterministic and runs offline.
 */
class ResearchSourceFetchTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): ResearchSourceFetchService
    {
        return app(ResearchSourceFetchService::class);
    }

    public function test_it_refuses_loopback_and_private_addresses(): void
    {
        foreach ([
            'http://127.0.0.1/secret',
            'http://192.168.0.1/admin',
            'http://10.0.0.5/',
            'http://localhost/',
        ] as $url) {
            $r = $this->svc()->fetchToWorkspace($url, 1, 1);
            $this->assertFalse($r['ok'], "Expected {$url} to be refused");
            $this->assertNull($r['file_id']);
        }
    }

    public function test_it_refuses_cloud_metadata_endpoints(): void
    {
        $r = $this->svc()->fetchToWorkspace('http://169.254.169.254/latest/meta-data/', 1, 1);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsStringIgnoringCase('blocked host', (string) $r['error']);
    }

    /**
     * http://2130706433/ is 127.0.0.1 written as a decimal integer - a documented
     * bypass for guards that only pattern-match dotted quads.
     */
    public function test_it_normalises_integer_encoded_hosts(): void
    {
        $r = $this->svc()->fetchToWorkspace('http://2130706433/', 1, 1);

        $this->assertFalse($r['ok']);
    }

    public function test_it_refuses_non_http_schemes(): void
    {
        foreach (['file:///etc/passwd', 'ftp://example.com/x', 'gopher://example.com/'] as $url) {
            $r = $this->svc()->fetchToWorkspace($url, 1, 1);
            $this->assertFalse($r['ok'], "Expected {$url} to be refused");
        }
    }

    /**
     * The fetch ceiling must NOT be clamped by upload_max_filesize. That ini
     * governs multipart uploads; a queued fetch runs under the CLI SAPI where
     * this host allows only 2M, and clamping there would silently shrink the
     * limit by a factor of fifty.
     */
    public function test_fetch_ceiling_is_independent_of_php_upload_limit(): void
    {
        $quota = app(ResearchQuotaService::class);

        $this->assertSame(100 * 1024 * 1024, $quota->maxFetchBytes());
        $this->assertGreaterThan(0, $quota->maxUploadKb());
    }

    /**
     * A refusal must NOT be a job failure. An SSRF rejection, an oversized
     * document or a disallowed type are correct outcomes: retrying them just
     * re-proves the same refusal and, worse, a job that lands in failed_jobs
     * looks like an outage to whoever reads that table.
     */
    public function test_a_refused_url_notifies_and_does_not_fail_the_job(): void
    {
        if (! Schema::hasTable('research_notification')) {
            $this->markTestSkipped('research_notification not present');
        }

        $before = DB::table('research_notification')->count();

        // Run the job body directly - dispatching would need a worker.
        (new \AhgResearch\Jobs\ResearchSourceFetchJob('http://169.254.169.254/latest/meta-data/', 1, 1))
            ->handle(app(ResearchSourceFetchService::class));

        $note = DB::table('research_notification')->orderByDesc('id')->first();

        $this->assertSame($before + 1, DB::table('research_notification')->count());
        $this->assertSame('source_fetch_failed', $note->type);
        $this->assertStringContainsStringIgnoringCase('blocked host', (string) $note->message);
    }

    public function test_the_configured_limit_is_honoured(): void
    {
        if (! Schema::hasTable('ahg_settings')) {
            $this->markTestSkipped('ahg_settings not present');
        }

        DB::table('ahg_settings')->updateOrInsert(
            ['setting_key' => 'research_max_upload_mb'],
            ['setting_value' => '7']
        );

        $this->assertSame(7 * 1024 * 1024, app(ResearchQuotaService::class)->maxFetchBytes());
    }
}
