<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgResearch\Jobs;

use AhgResearch\Services\ResearchSourceFetchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fetch an external document into a research workspace, off the request. #1492.
 *
 * Doing this synchronously tied up a php-fpm worker for the whole download - up
 * to the 30s HTTP timeout, and the size ceiling is 100 MB, so a slow source
 * could hold a request open for the full timeout while the researcher watched a
 * spinner.
 *
 * Only SCALARS are serialised onto the queue. The service, the SSRF guard and
 * the quota engine are resolved inside handle() - a job payload sits in the
 * database until a worker picks it up, and serialising a service into it would
 * both bloat the row and freeze its dependencies at dispatch time.
 *
 * The queued path is the reason ResearchQuotaService::maxFetchBytes() is
 * deliberately NOT clamped to upload_max_filesize: a worker runs under the CLI
 * SAPI, where this host allows 2M, and clamping would silently cut the limit by
 * a factor of fifty exactly when the work moved off the request.
 */
class ResearchSourceFetchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Two attempts. A fetch is not idempotent - a retry re-downloads and, if the
     * first attempt actually succeeded before failing later, would duplicate the
     * file. Two covers a transient network blip without turning a broken source
     * into a pile of copies.
     */
    public int $tries = 2;

    /** Comfortably above the service's own 30s HTTP timeout. */
    public int $timeout = 180;

    public function __construct(
        private string $url,
        private int $workspaceId,
        private int $researcherId,
    ) {
    }

    public function handle(ResearchSourceFetchService $fetcher): void
    {
        $result = $fetcher->fetchToWorkspace($this->url, $this->workspaceId, $this->researcherId);

        if ($result['ok']) {
            $this->notify(
                'source_fetch_complete',
                'Document fetched',
                'Fetched "' . $result['file_name'] . '" into your workspace.',
            );

            return;
        }

        // A refusal is an ANSWER, not a fault: an SSRF rejection, an oversized
        // document or a disallowed type are all correct outcomes and must not be
        // retried. Tell the researcher and stop - throwing here would burn the
        // second attempt re-proving the same refusal.
        Log::info('[ahg-research] external fetch refused', [
            'url'          => $this->url,
            'workspace_id' => $this->workspaceId,
            'reason'       => $result['error'],
        ]);

        $this->notify('source_fetch_failed', 'Could not fetch document', (string) $result['error']);
    }

    /**
     * Only reached on a genuine exception (database down, disk full), because
     * handle() converts refusals into notifications rather than throwing.
     */
    public function failed(\Throwable $e): void
    {
        Log::warning('[ahg-research] external fetch job failed', [
            'url'          => $this->url,
            'workspace_id' => $this->workspaceId,
            'error'        => $e->getMessage(),
        ]);

        $this->notify(
            'source_fetch_failed',
            'Could not fetch document',
            'The fetch could not be completed. Please try again, or upload the file directly.',
        );
    }

    private function notify(string $type, string $title, string $message): void
    {
        try {
            DB::table('research_notification')->insert([
                'researcher_id'       => $this->researcherId,
                'type'                => $type,
                'title'               => $title,
                'message'             => mb_substr($message, 0, 2000),
                'link'                => '/research/workspaces/' . $this->workspaceId . '/files',
                'related_entity_type' => 'workspace',
                'related_entity_id'   => $this->workspaceId,
                'is_read'             => 0,
                'created_at'          => now(),
            ]);
        } catch (\Throwable $e) {
            // Never let the notification take down the job - the fetch itself
            // may well have succeeded.
            Log::warning('[ahg-research] could not write fetch notification: ' . $e->getMessage());
        }
    }
}
