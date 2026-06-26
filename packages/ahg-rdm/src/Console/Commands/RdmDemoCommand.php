<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgRdm\Console\Commands;

use AhgRdm\Services\DatasetService;
use AhgRdm\Services\PopiaGateService;
use AhgRdm\Services\PopiaScanService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * One-command demo of the full ahg-rdm Feature-2 pipeline (#1343):
 * deposit -> POPIA scan -> human gate -> restrict -> DOI -> landing -> scoreboard,
 * on the 100%-SYNTHETIC demo dataset (never real PII).
 *
 *   php artisan ahg:rdm-demo [--fresh]
 */
class RdmDemoCommand extends Command
{
    protected $signature = 'ahg:rdm-demo {--fresh : delete a prior demo dataset and rebuild}';

    protected $description = 'Run the full POPIA RDM demo on the synthetic dataset (deposit->scan->gate->DOI->landing).';

    private const TITLE = 'POPIA RDM Demo (synthetic)';

    public function handle(): int
    {
        $demoDir = __DIR__.'/../../../resources/demo';
        if (! is_dir($demoDir)) {
            $this->error("Demo assets not found at {$demoDir}");

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->purge();
        }

        $userId = (int) (DB::table('users')->min('id') ?: 1);
        $projectId = $this->ensureProject();

        $svc = app(DatasetService::class);
        $datasetId = $svc->create(self::TITLE, 'Synthetic social-science/health study for the POPIA scan demo. No real personal data.', $projectId, $userId);
        $this->info("Created dataset #{$datasetId}".($projectId ? " (project #{$projectId})" : ' (no project)'));

        // Feature 1 (#1337): attach a Data Management Plan. Pure orchestration -
        // the maDMP is authored by the ahg-research DMP builder; the dataset just
        // references it. Skips cleanly when the DMP slice isn't installed.
        if ($projectId) {
            $dmpId = app(\AhgRdm\Services\DmpLinkService::class)->createAndLink($datasetId, [
                'title'         => 'DMP — POPIA RDM Demo Study',
                'funder'        => 'NRF',
                'contact_name'  => 'Demo PI',
                'contact_email' => 'demo.pi@up.ac.za',
            ], $userId);
            if ($dmpId) {
                $this->line("Linked Data Management Plan #{$dmpId} (maDMP, status draft).");
            } else {
                $this->line('DMP builder not installed - dataset left without a DMP.');
            }
        }

        // 1. Deposit the synthetic files (copies, since deposit moves the staged file).
        $files = [];
        foreach ([
            'survey_responses.csv'                 => 'text/csv',
            'interview_transcripts/interview_01.txt' => 'text/plain',
            'consent_forms.pdf'                    => 'application/pdf',
            'climate_measurements.csv'             => 'text/csv',
            'readme.txt'                           => 'text/plain',
        ] as $rel => $mime) {
            $src = $demoDir.'/'.$rel;
            if (! is_file($src)) {
                continue;
            }
            $tmp = sys_get_temp_dir().'/'.basename($rel);
            copy($src, $tmp);
            $files[] = new UploadedFile($tmp, basename($rel), $mime, null, true);
        }
        $dep = $svc->deposit($datasetId, $files, $userId);
        $this->line("Deposited {$dep['stored']} file(s).");

        // 2. POPIA scan
        $this->line('Running POPIA scan (deterministic -> lexicon -> NER)...');
        $scan = app(PopiaScanService::class)->scanDataset($datasetId);
        $this->info("Scan verdict: {$scan['verdict']} ({$scan['findings']} findings across {$scan['scanned']}/{$scan['files']} files)");
        $this->table(['File', 'Type', 'Category', 'Method', 'Sample'],
            DB::table('rdm_scan_finding')->where('dataset_id', $datasetId)
                ->orderBy('file_name')->get(['file_name', 'type', 'category', 'method', 'sample'])
                ->map(fn ($f) => [$f->file_name, $f->type, $f->category, $f->method, $f->sample])->all());

        // 3. Human gate (simulated): confirm every finding, then restrict.
        foreach (DB::table('rdm_scan_finding')->where('dataset_id', $datasetId)->pluck('id') as $fid) {
            app(PopiaGateService::class)->resolveFinding($fid, 'confirm', 'demo reviewer confirms', $userId);
        }
        $gate = app(PopiaGateService::class);
        try {
            $gate->setDisposition($datasetId, 'release', $userId);
            $this->warn('Unexpected: open release allowed on a flagged dataset.');
        } catch (\Throwable $e) {
            $this->line('Open release correctly BLOCKED (confirmed PII). Applying restrict...');
        }
        $r = $gate->setDisposition($datasetId, 'restrict', $userId);

        // 4. Report
        $ds = DB::table('rdm_dataset')->where('id', $datasetId)->first();
        $base = rtrim((string) config('app.url'), '/');
        $this->newLine();
        $this->info('=== DEMO COMPLETE ===');
        $this->line("Verdict      : {$ds->verdict}");
        $this->line("Disposition  : {$ds->disposition}  (status {$ds->status})");
        $this->line("DMP linked   : ".(! empty($ds->dmp_id) ? "#{$ds->dmp_id} (maDMP)" : '(none)'));
        $this->line("DOI          : ".($ds->doi ?: '(none)'));
        $this->line("Landing page : {$base}/research/datasets/{$datasetId}/landing");
        $this->line("Dataset (admin): {$base}/research/datasets/{$datasetId}");
        $this->line("Compliance   : {$base}/research/datasets/compliance");
        $this->newLine();
        $this->line("Punchline: every one of those synthetic SA ID numbers would, on Figshare, be on a foreign");
        $this->line("cloud and openly downloadable. Here the deterministic scan caught them, a human confirmed,");
        $this->line("and the dataset is restricted + POPIA-resident - with a citable DOI for the metadata.");

        return self::SUCCESS;
    }

    private function ensureProject(): ?int
    {
        $researcherId = DB::table('research_researcher')->min('id');
        if (! $researcherId) {
            return null; // no researcher to own a project; dataset stays unlinked (faculty blank)
        }
        $existing = DB::table('research_project')->where('title', 'POPIA RDM Demo Study')->value('id');
        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('research_project')->insertGetId([
            'owner_id'    => $researcherId,
            'title'       => 'POPIA RDM Demo Study',
            'institution' => 'University of Pretoria - Faculty of Humanities',
            'status'      => 'active',
            'visibility'  => 'private',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    private function purge(): void
    {
        $rows = DB::table('rdm_dataset')->where('title', self::TITLE)->get(['id', 'dmp_id']);
        foreach ($rows as $row) {
            DB::table('rdm_scan_finding')->where('dataset_id', $row->id)->delete();
            DB::table('rdm_dataset_file')->where('dataset_id', $row->id)->delete();
            DB::table('rdm_dataset')->where('id', $row->id)->delete();
            // Remove the demo's own DMP (created by --fresh) so re-runs don't pile up.
            if (! empty($row->dmp_id) && \Illuminate\Support\Facades\Schema::hasTable('research_dmp')) {
                DB::table('research_dmp_section')->where('dmp_id', $row->dmp_id)->delete();
                DB::table('research_dmp')->where('id', $row->dmp_id)->delete();
            }
        }
        $ids = $rows->pluck('id');
        if ($ids->count()) {
            $this->line("Purged {$ids->count()} prior demo dataset(s).");
        }
    }
}
