<?php

namespace AhgCore\Services;

use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class CronSchedulerService
{
    protected string $table = 'cron_schedule';

    /**
     * Get all schedules that are due to run now.
     */
    public function getDueSchedules(): Collection
    {
        return DB::table($this->table)
            ->where('is_enabled', 1)
            ->where(function ($q) {
                $q->whereNull('next_run_at')
                  ->orWhere('next_run_at', '<=', Carbon::now());
            })
            ->where(function ($q) {
                $q->whereNull('last_run_status')
                  ->orWhere('last_run_status', '!=', 'running');
            })
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Run all due schedules and return results.
     */
    public function runDueSchedules(): array
    {
        $results = [];
        $due = $this->getDueSchedules();

        foreach ($due as $schedule) {
            $results[] = $this->runSingle($schedule);
        }

        return $results;
    }

    /**
     * Run a single schedule entry.
     */
    public function runSingle(object $schedule): array
    {
        // Mark as running
        DB::table($this->table)->where('id', $schedule->id)->update([
            'last_run_status' => 'running',
            'last_run_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $start = microtime(true);
        $status = 'success';
        $output = '';

        try {
            $exitCode = Artisan::call($schedule->artisan_command);
            $output = Artisan::output();

            if ($exitCode !== 0) {
                $status = 'failed';
            }
        } catch (\Throwable $e) {
            $status = 'failed';
            $output = $e->getMessage();
        }

        $durationMs = (int) ((microtime(true) - $start) * 1000);
        $nextRun = $this->computeNextRun($schedule->cron_expression);

        // Truncate output to last 5000 chars for storage
        if (strlen($output) > 5000) {
            $output = '... (truncated) ...' . substr($output, -5000);
        }

        $updateData = [
            'last_run_status' => $status,
            'last_run_duration_ms' => $durationMs,
            'last_run_output' => $output ?: null,
            'next_run_at' => $nextRun,
            'total_runs' => DB::raw('total_runs + 1'),
            'updated_at' => Carbon::now(),
        ];

        if ($status === 'failed') {
            $updateData['total_failures'] = DB::raw('total_failures + 1');
        }

        DB::table($this->table)->where('id', $schedule->id)->update($updateData);

        return [
            'id' => $schedule->id,
            'slug' => $schedule->slug,
            'status' => $status,
            'duration_ms' => $durationMs,
            'next_run' => $nextRun->toDateTimeString(),
        ];
    }

    /**
     * Compute next run time from a cron expression.
     */
    public function computeNextRun(string $cronExpression): Carbon
    {
        $cron = new CronExpression($cronExpression);
        return Carbon::instance($cron->getNextRunDate());
    }

    /**
     * Toggle a schedule's enabled state.
     */
    public function toggleEnabled(int $id, bool $enabled): void
    {
        $data = ['is_enabled' => $enabled, 'updated_at' => Carbon::now()];

        if ($enabled) {
            $schedule = DB::table($this->table)->where('id', $id)->first();
            if ($schedule) {
                $data['next_run_at'] = $this->computeNextRun($schedule->cron_expression);
            }
        }

        DB::table($this->table)->where('id', $id)->update($data);
    }

    /**
     * Update a schedule's settings.
     */
    public function updateSchedule(int $id, array $data): void
    {
        $allowed = ['cron_expression', 'timeout_minutes', 'notify_on_failure', 'notify_email'];
        $update = array_intersect_key($data, array_flip($allowed));
        $update['updated_at'] = Carbon::now();

        if (isset($update['cron_expression'])) {
            $update['next_run_at'] = $this->computeNextRun($update['cron_expression']);
        }

        DB::table($this->table)->where('id', $id)->update($update);
    }

    /**
     * Get a single schedule by ID.
     */
    public function find(int $id): ?object
    {
        return DB::table($this->table)->where('id', $id)->first();
    }

    /**
     * Get all schedules grouped by category.
     */
    public function getAllGrouped(): Collection
    {
        return DB::table($this->table)
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->groupBy('category');
    }

    /**
     * Get summary stats.
     */
    public function getStats(): object
    {
        $now = Carbon::now();
        $dayAgo = $now->copy()->subDay();

        return (object) [
            'total' => DB::table($this->table)->count(),
            'enabled' => DB::table($this->table)->where('is_enabled', 1)->count(),
            'disabled' => DB::table($this->table)->where('is_enabled', 0)->count(),
            'failed_24h' => DB::table($this->table)
                ->where('last_run_status', 'failed')
                ->where('last_run_at', '>=', $dayAgo)
                ->count(),
            'running' => DB::table($this->table)
                ->where('last_run_status', 'running')
                ->count(),
        ];
    }

    /**
     * Seed or update all default schedules.
     */
    public function seedDefaults(bool $reset = false): int
    {
        $defaults = $this->getDefaultSchedules();
        $count = 0;

        foreach ($defaults as $entry) {
            if ($reset) {
                DB::table($this->table)->updateOrInsert(
                    ['slug' => $entry['slug']],
                    array_merge($entry, [
                        'next_run_at' => $this->computeNextRun($entry['cron_expression']),
                        'updated_at' => Carbon::now(),
                        'created_at' => Carbon::now(),
                    ])
                );
            } else {
                $exists = DB::table($this->table)->where('slug', $entry['slug'])->exists();
                if (!$exists) {
                    DB::table($this->table)->insert(array_merge($entry, [
                        'next_run_at' => $this->computeNextRun($entry['cron_expression']),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]));
                }
            }
            $count++;
        }

        return $count;
    }

    /**
     * All default schedule entries — the single source of truth for seeding.
     */
    public function getDefaultSchedules(): array
    {
        $sort = 0;
        $schedules = [];

        $categories = [
            'Search & Indexing' => [
                ['slug' => 'search-update', 'name' => 'Incremental Search Update', 'description' => 'Push recently modified records to the search index.', 'artisan_command' => 'ahg:search-update', 'cron_expression' => '*/5 * * * *', 'duration_hint' => 'short', 'log_file' => 'logs/search-update.log'],
                ['slug' => 'search-populate', 'name' => 'Full Search Populate', 'description' => 'Rebuild the entire search index from scratch.', 'artisan_command' => 'ahg:search-populate', 'cron_expression' => '0 2 * * 0', 'duration_hint' => 'long', 'log_file' => 'logs/search-populate.log'],
                ['slug' => 'search-cleanup', 'name' => 'Search Cleanup', 'description' => 'Remove stale search index entries and orphaned records.', 'artisan_command' => 'ahg:search-cleanup', 'cron_expression' => '0 3 * * *', 'duration_hint' => 'medium', 'log_file' => 'logs/search-cleanup.log'],
            ],
            'Cache Management' => [
                ['slug' => 'refresh-facet-cache', 'name' => 'Refresh Facet Cache', 'description' => 'Rebuild facet counts for browse sidebars (entity type, repository, place, subject).', 'artisan_command' => 'ahg:refresh-facet-cache', 'cron_expression' => '0 * * * *', 'duration_hint' => 'medium', 'log_file' => 'logs/facet-cache.log'],
                ['slug' => 'cache-xml-purge', 'name' => 'XML Cache Purge', 'description' => 'Remove stale cached XML exports (EAD, EAC-CPF, DC) older than the configured threshold.', 'artisan_command' => 'ahg:cache-xml-purge', 'cron_expression' => '0 4 * * 0', 'duration_hint' => 'short', 'log_file' => 'logs/xml-purge.log'],
            ],
            'Saved Search Notifications' => [
                ['slug' => 'notify-saved-searches-daily', 'name' => 'Daily Notifications', 'description' => 'Email users who have daily saved search notifications enabled.', 'artisan_command' => 'ahg:notify-saved-searches --frequency=daily', 'cron_expression' => '0 6 * * *', 'duration_hint' => 'medium', 'log_file' => 'logs/saved-search-notify.log'],
                ['slug' => 'notify-saved-searches-weekly', 'name' => 'Weekly Notifications', 'description' => 'Email users who have weekly saved search notifications.', 'artisan_command' => 'ahg:notify-saved-searches --frequency=weekly', 'cron_expression' => '0 7 * * 1', 'duration_hint' => 'medium', 'log_file' => 'logs/saved-search-notify.log'],
                ['slug' => 'notify-saved-searches-monthly', 'name' => 'Monthly Notifications', 'description' => 'Email users who have monthly saved search notifications.', 'artisan_command' => 'ahg:notify-saved-searches --frequency=monthly', 'cron_expression' => '0 8 1 * *', 'duration_hint' => 'medium', 'log_file' => 'logs/saved-search-notify.log'],
            ],
            'Digital Objects' => [
                ['slug' => 'regen-derivatives', 'name' => 'Regenerate Derivatives', 'description' => 'Regenerate thumbnail and reference image derivatives from master files.', 'artisan_command' => 'ahg:regen-derivatives --type=all', 'cron_expression' => '0 2 * * 0', 'duration_hint' => 'long', 'log_file' => 'logs/regen-derivatives.log'],
                ['slug' => '3d-derivatives', 'name' => '3D Derivatives', 'description' => 'Generate thumbnails for 3D model files (GLB, GLTF, OBJ, STL) using Blender.', 'artisan_command' => 'ahg:3d-derivatives', 'cron_expression' => '0 * * * *', 'duration_hint' => 'long', 'log_file' => 'logs/3d-derivatives.log'],
                ['slug' => '3d-multiangle', 'name' => '3D Multi-Angle Renders', 'description' => 'Generate multi-angle renders of 3D models for preview galleries.', 'artisan_command' => 'ahg:3d-multiangle', 'cron_expression' => '0 3 * * *', 'duration_hint' => 'long', 'log_file' => 'logs/3d-multiangle.log'],
            ],
            'Finding Aids' => [
                ['slug' => 'finding-aid-generate', 'name' => 'Generate Finding Aids', 'description' => 'Generate PDF/HTML finding aids for all fonds-level descriptions.', 'artisan_command' => 'ahg:finding-aid-generate --all', 'cron_expression' => '0 1 * * *', 'duration_hint' => 'long', 'log_file' => 'logs/finding-aids.log'],
                ['slug' => 'finding-aid-delete', 'name' => 'Delete Old Finding Aids', 'description' => 'Remove finding aid files older than the specified number of days.', 'artisan_command' => 'ahg:finding-aid-delete --older-than=90', 'cron_expression' => '0 3 1 * *', 'duration_hint' => 'short', 'log_file' => 'logs/finding-aids.log'],
            ],
            'Import & Export' => [
                ['slug' => 'export-bulk-ead', 'name' => 'Bulk EAD Export', 'description' => 'Export all descriptions in EAD format for backup and interchange.', 'artisan_command' => 'ahg:export-bulk --format=ead --path=/backups/ead', 'cron_expression' => '0 2 * * 0', 'duration_hint' => 'long', 'log_file' => 'logs/export-bulk.log'],
            ],
            'OAI-PMH' => [
                ['slug' => 'oai-harvest', 'name' => 'OAI Harvest', 'description' => 'Harvest records from configured OAI-PMH sources.', 'artisan_command' => 'ahg:oai-harvest', 'cron_expression' => '0 5 * * *', 'duration_hint' => 'medium', 'log_file' => 'logs/oai-harvest.log'],
            ],
            'Backup & Maintenance' => [
                ['slug' => 'backup-daily', 'name' => 'Daily Backup', 'description' => 'Run database backup with 30-day retention.', 'artisan_command' => 'ahg:backup --components=database --retention=30', 'cron_expression' => '0 2 * * *', 'duration_hint' => 'medium', 'log_file' => 'logs/backup.log'],
                ['slug' => 'backup-weekly-full', 'name' => 'Weekly Full Backup', 'description' => 'Full backup including uploads with 90-day retention.', 'artisan_command' => 'ahg:backup --components=database,uploads --retention=90', 'cron_expression' => '0 3 * * 0', 'duration_hint' => 'long', 'log_file' => 'logs/backup.log'],
                ['slug' => 'backup-cleanup', 'name' => 'Cleanup Old Backups', 'description' => 'Remove backups that exceed retention policy.', 'artisan_command' => 'ahg:backup-cleanup', 'cron_expression' => '0 4 * * *', 'duration_hint' => 'short', 'log_file' => 'logs/backup.log'],
                ['slug' => 'cleanup-uploads', 'name' => 'Cleanup Uploads', 'description' => 'Remove orphaned and temporary upload files older than the specified days.', 'artisan_command' => 'ahg:cleanup-uploads --days=7', 'cron_expression' => '30 4 * * *', 'duration_hint' => 'short', 'log_file' => 'logs/cleanup.log'],
            ],
            'Audit & Logging' => [
                ['slug' => 'audit-purge', 'name' => 'Audit Log Purge', 'description' => 'Remove audit log entries older than the specified number of days.', 'artisan_command' => 'ahg:audit-purge --older-than=365', 'cron_expression' => '0 3 1 * *', 'duration_hint' => 'medium', 'log_file' => 'logs/audit.log'],
                ['slug' => 'audit-retention', 'name' => 'Audit Retention', 'description' => 'Apply retention policies to audit records and archive as needed.', 'artisan_command' => 'ahg:audit-retention', 'cron_expression' => '0 4 * * 0', 'duration_hint' => 'medium', 'log_file' => 'logs/audit.log'],
                ['slug' => 'cleanup-login-attempts', 'name' => 'Cleanup Login Attempts', 'description' => 'Remove expired login attempt records to free database space.', 'artisan_command' => 'ahg:cleanup-login-attempts', 'cron_expression' => '0 3 * * *', 'duration_hint' => 'short', 'log_file' => 'logs/security.log'],
            ],
            'Digital Preservation' => [
                ['slug' => 'preservation-scheduler', 'name' => 'Preservation Scheduler', 'description' => 'Execute due preservation actions from the scheduler queue.', 'artisan_command' => 'ahg:preservation-scheduler', 'cron_expression' => '*/5 * * * *', 'duration_hint' => 'medium', 'log_file' => 'logs/preservation-scheduler.log'],
                ['slug' => 'preservation-fixity', 'name' => 'Fixity Check', 'description' => 'Verify file checksums for digital objects not checked within the specified age in days.', 'artisan_command' => 'ahg:preservation-fixity --age=30 --report', 'cron_expression' => '0 2 * * *', 'duration_hint' => 'long', 'log_file' => 'logs/fixity.log'],
                ['slug' => 'preservation-identify', 'name' => 'Format Identification', 'description' => 'Identify file formats for unidentified digital objects using DROID/Siegfried.', 'artisan_command' => 'ahg:preservation-identify --unidentified', 'cron_expression' => '0 3 * * *', 'duration_hint' => 'medium', 'log_file' => 'logs/format-id.log'],
                ['slug' => 'preservation-virus-scan', 'name' => 'Virus Scan', 'description' => 'Scan unscanned digital objects for viruses using ClamAV and quarantine threats.', 'artisan_command' => 'ahg:preservation-virus-scan --unscanned --quarantine', 'cron_expression' => '0 4 * * *', 'duration_hint' => 'medium', 'log_file' => 'logs/virus-scan.log'],
                ['slug' => 'preservation-replicate', 'name' => 'Replication', 'description' => 'Replicate digital objects to secondary storage with verification.', 'artisan_command' => 'ahg:preservation-replicate --verify', 'cron_expression' => '0 1 * * *', 'duration_hint' => 'long', 'log_file' => 'logs/replication.log'],
                ['slug' => 'preservation-package', 'name' => 'AIP Packaging', 'description' => 'Generate Archival Information Packages (AIPs) in BagIt format.', 'artisan_command' => 'ahg:preservation-package --type=aip --output=/preservation/aips', 'cron_expression' => '0 0 * * 0', 'duration_hint' => 'long', 'log_file' => 'logs/packages.log'],
                ['slug' => 'preservation-obsolescence', 'name' => 'Obsolescence Check', 'description' => 'Check file formats against obsolescence risk registries.', 'artisan_command' => 'ahg:preservation-obsolescence --risk-level=medium', 'cron_expression' => '0 6 1 * *', 'duration_hint' => 'medium', 'log_file' => 'logs/obsolescence.log'],
                ['slug' => 'preservation-stats', 'name' => 'Preservation Stats', 'description' => 'Generate daily preservation statistics in JSON format.', 'artisan_command' => 'ahg:preservation-stats --format=json', 'cron_expression' => '0 7 * * *', 'duration_hint' => 'short', 'log_file' => 'logs/preservation-stats.log'],
            ],
            'Integrity Assurance' => [
                ['slug' => 'integrity-schedule', 'name' => 'Integrity Scheduler', 'description' => 'Execute due integrity verification schedules (fixity checks).', 'artisan_command' => 'ahg:integrity-schedule --run-due', 'cron_expression' => '*/15 * * * *', 'duration_hint' => 'medium', 'log_file' => 'logs/integrity-scheduler.log'],
                ['slug' => 'integrity-verify', 'name' => 'Integrity Verify', 'description' => 'Verify integrity of up to 500 records that have not been checked in 14 days.', 'artisan_command' => 'ahg:integrity-verify --limit=500 --stale-days=14', 'cron_expression' => '0 3 * * *', 'duration_hint' => 'long', 'log_file' => 'logs/integrity-verify.log'],
                ['slug' => 'integrity-report', 'name' => 'Integrity Report', 'description' => 'Generate weekly integrity verification summary report.', 'artisan_command' => 'ahg:integrity-report --summary', 'cron_expression' => '0 8 * * 1', 'duration_hint' => 'short', 'log_file' => 'logs/integrity-report.log'],
                ['slug' => 'integrity-retention-scan', 'name' => 'Retention Scan', 'description' => 'Identify records eligible for disposal under retention policies.', 'artisan_command' => 'ahg:integrity-retention --scan-eligible', 'cron_expression' => '0 1 * * *', 'duration_hint' => 'medium', 'log_file' => 'logs/integrity-retention.log'],
                ['slug' => 'integrity-retention-process', 'name' => 'Retention Process Queue', 'description' => 'Process the retention disposal queue for approved disposals.', 'artisan_command' => 'ahg:integrity-retention --process-queue', 'cron_expression' => '0 2 * * *', 'duration_hint' => 'medium', 'log_file' => 'logs/integrity-retention.log'],
            ],
            'Metadata Export (GLAM Standards)' => [
                ['slug' => 'metadata-export', 'name' => 'Metadata Export', 'description' => 'Export metadata in all supported GLAM standards (DC, EAD, EAC-CPF, MODS, METS).', 'artisan_command' => 'ahg:metadata-export --format=all --output=/exports/weekly', 'cron_expression' => '0 3 * * 0', 'duration_hint' => 'long', 'log_file' => 'logs/metadata-export.log'],
            ],
            'DOI Management' => [
                ['slug' => 'doi-process-queue', 'name' => 'DOI Process Queue', 'description' => 'Process pending DOI minting requests via DataCite API.', 'artisan_command' => 'ahg:doi-process-queue --limit=50', 'cron_expression' => '*/15 * * * *', 'duration_hint' => 'short', 'log_file' => 'logs/doi-queue.log'],
                ['slug' => 'doi-mint', 'name' => 'DOI Mint', 'description' => 'Mint new DOIs for records flagged for persistent identification.', 'artisan_command' => 'ahg:doi-mint --limit=50', 'cron_expression' => '0 1 * * *', 'duration_hint' => 'medium', 'log_file' => 'logs/doi-mint.log'],
                ['slug' => 'doi-update', 'name' => 'DOI Update', 'description' => 'Update DataCite metadata for DOIs whose records were modified.', 'artisan_command' => 'ahg:doi-update --modified-since=yesterday', 'cron_expression' => '0 3 * * *', 'duration_hint' => 'medium', 'log_file' => 'logs/doi-update.log'],
                ['slug' => 'doi-verify', 'name' => 'DOI Verify', 'description' => 'Verify all registered DOIs resolve correctly.', 'artisan_command' => 'ahg:doi-verify --all', 'cron_expression' => '0 6 * * 0', 'duration_hint' => 'medium', 'log_file' => 'logs/doi-verify.log'],
                ['slug' => 'doi-sync', 'name' => 'DOI Sync', 'description' => 'Sync DOI metadata with DataCite for all registered DOIs.', 'artisan_command' => 'ahg:doi-sync --all --limit=500', 'cron_expression' => '0 4 * * 0', 'duration_hint' => 'medium', 'log_file' => 'logs/doi-sync.log'],
                ['slug' => 'doi-report', 'name' => 'DOI Report', 'description' => 'Generate monthly DOI registration and resolution summary report.', 'artisan_command' => 'ahg:doi-report --type=summary', 'cron_expression' => '0 7 1 * *', 'duration_hint' => 'short', 'log_file' => 'logs/doi-report.log'],
            ],
            'AI & NLP Services' => [
                ['slug' => 'ai-ner', 'name' => 'AI NER Extraction', 'description' => 'Run Named Entity Recognition on unprocessed descriptions to extract persons, organizations, places, dates.', 'artisan_command' => 'ahg:ai-ner --limit=100 --unprocessed', 'cron_expression' => '0 2 * * *', 'duration_hint' => 'long', 'log_file' => 'logs/ai-ner.log'],
                ['slug' => 'ai-translate', 'name' => 'AI Translation', 'description' => 'Translate descriptions into configured secondary languages using LLM.', 'artisan_command' => 'ahg:ai-translate --limit=50', 'cron_expression' => '0 3 * * *', 'duration_hint' => 'long', 'log_file' => 'logs/ai-translate.log'],
                ['slug' => 'ai-process-pending', 'name' => 'AI Process Pending', 'description' => 'Process pending AI tasks (summarization, translation, description suggestions).', 'artisan_command' => 'ahg:ai-process-pending --limit=20', 'cron_expression' => '*/5 * * * *', 'duration_hint' => 'medium', 'log_file' => 'logs/ai-pending.log'],
                ['slug' => 'ai-suggest-description', 'name' => 'AI Suggest Description', 'description' => 'Generate description suggestions for records with empty scope/content using OCR text.', 'artisan_command' => 'ahg:ai-suggest-description --empty-only --with-ocr --limit=100', 'cron_expression' => '0 2 * * *', 'duration_hint' => 'long', 'log_file' => 'logs/ai-suggest.log'],
                ['slug' => 'ai-htr', 'name' => 'AI Handwritten Text Recognition', 'description' => 'Run HTR on handwritten document images to extract text.', 'artisan_command' => 'ahg:ai-htr --all --limit=100', 'cron_expression' => '0 3 * * *', 'duration_hint' => 'long', 'log_file' => 'logs/ai-htr.log'],
                ['slug' => 'ai-spellcheck', 'name' => 'AI Spellcheck', 'description' => 'Run AI-powered spellcheck across descriptions and flag corrections.', 'artisan_command' => 'ahg:ai-spellcheck --all --limit=200', 'cron_expression' => '0 4 * * 0', 'duration_hint' => 'medium', 'log_file' => 'logs/ai-spellcheck.log'],
                ['slug' => 'ai-ner-sync', 'name' => 'AI NER Sync / Retrain', 'description' => 'Sync NER training data and retrain the model with accepted corrections.', 'artisan_command' => 'ahg:ai-ner-sync --retrain', 'cron_expression' => '0 1 1 * *', 'duration_hint' => 'long', 'log_file' => 'logs/ai-ner-sync.log'],
                ['slug' => 'ai-sync-entity-cache', 'name' => 'AI Entity Cache Sync', 'description' => 'Refresh the AI entity resolution cache from authority records.', 'artisan_command' => 'ahg:ai-sync-entity-cache', 'cron_expression' => '0 5 * * *', 'duration_hint' => 'medium', 'log_file' => 'logs/ai-entity-cache.log'],
                ['slug' => 'ai-condition-scan', 'name' => 'AI Condition Scan', 'description' => 'Run AI-powered condition assessments on queued digital objects.', 'artisan_command' => 'ahg:ai-condition-scan --limit=100', 'cron_expression' => '0 3 * * *', 'duration_hint' => 'long', 'log_file' => 'logs/ai-condition.log'],
                ['slug' => 'ai-condition-status', 'name' => 'AI Condition Status', 'description' => 'Check status of pending AI condition assessment jobs.', 'artisan_command' => 'ahg:ai-condition-status', 'cron_expression' => '*/5 * * * *', 'duration_hint' => 'short', 'log_file' => 'logs/ai-condition-status.log'],
                ['slug' => 'ai-summarize', 'name' => 'AI Summarize', 'description' => 'Generate AI summaries for records with empty scope and content fields.', 'artisan_command' => 'ahg:ai-summarize --all-empty --field=scope_and_content', 'cron_expression' => '0 2 * * 0', 'duration_hint' => 'long', 'log_file' => 'logs/ai-summarize.log'],
                ['slug' => 'llm-health', 'name' => 'LLM Health Check', 'description' => 'Verify LLM provider connectivity and model availability.', 'artisan_command' => 'ahg:llm-health', 'cron_expression' => '*/5 * * * *', 'duration_hint' => 'short', 'log_file' => 'logs/llm-health.log'],
            ],
            'Qdrant Vector Search' => [
                ['slug' => 'qdrant-text-index', 'name' => 'Qdrant Text Index', 'description' => 'Index text embeddings into Qdrant for semantic search across archive records.', 'artisan_command' => 'ahg:qdrant-index --db-name=archive --db-user=root --collection=archive_records', 'cron_expression' => '0 1 * * 0', 'duration_hint' => 'long', 'log_file' => 'logs/qdrant-index.log'],
                ['slug' => 'qdrant-image-index', 'name' => 'Qdrant Image Index', 'description' => 'Index image embeddings into Qdrant for visual similarity search.', 'artisan_command' => 'ahg:qdrant-image-index --db-name=archive --db-user=root', 'cron_expression' => '0 2 * * 0', 'duration_hint' => 'long', 'log_file' => 'logs/qdrant-image-index.log'],
            ],
            '3D Model / TripoSR' => [
                ['slug' => 'triposr-health', 'name' => 'TripoSR Health Check', 'description' => 'Verify TripoSR service connectivity for 3D model generation.', 'artisan_command' => 'ahg:triposr-health', 'cron_expression' => '*/5 * * * *', 'duration_hint' => 'short', 'log_file' => 'logs/triposr-health.log'],
            ],
            'Workflow & Notifications' => [
                ['slug' => 'workflow-process', 'name' => 'Process Workflows', 'description' => 'Process pending workflow tasks, escalate overdue items, and send email notifications.', 'artisan_command' => 'ahg:workflow-process --escalate --notifications', 'cron_expression' => '*/15 * * * *', 'duration_hint' => 'short', 'log_file' => 'logs/workflow-process.log'],
                ['slug' => 'workflow-sla-check', 'name' => 'Workflow SLA Check', 'description' => 'Check workflow tasks against SLA targets and flag breaches.', 'artisan_command' => 'ahg:workflow-sla-check', 'cron_expression' => '*/15 * * * *', 'duration_hint' => 'short', 'log_file' => 'logs/workflow-sla.log'],
                ['slug' => 'donor-reminders', 'name' => 'Donor Agreement Reminders', 'description' => 'Process and send donor agreement renewal reminders.', 'artisan_command' => 'ahg:donor-reminders', 'cron_expression' => '0 8 * * *', 'duration_hint' => 'short', 'log_file' => 'logs/donor-reminders.log'],
                ['slug' => 'embargo-process', 'name' => 'Embargo Processing', 'description' => 'Automatically lift expired embargoes and update publication status.', 'artisan_command' => 'ahg:embargo-process', 'cron_expression' => '0 6 * * *', 'duration_hint' => 'short', 'log_file' => 'logs/embargo-process.log'],
                ['slug' => 'icip-check-expiry', 'name' => 'ICIP Expiry Check', 'description' => 'Check for intellectual property rights expiring within the specified days.', 'artisan_command' => 'ahg:icip-check-expiry --days=90', 'cron_expression' => '0 8 * * *', 'duration_hint' => 'short', 'log_file' => 'logs/icip-expiry.log'],
            ],
            'Statistics & Reporting' => [
                ['slug' => 'statistics-aggregate', 'name' => 'Aggregate Statistics', 'description' => 'Aggregate daily statistics for reports dashboard (record counts, user activity, storage usage).', 'artisan_command' => 'ahg:statistics-aggregate --all', 'cron_expression' => '0 2 * * *', 'duration_hint' => 'short', 'log_file' => 'logs/stats-aggregate.log'],
            ],
            'Accession Management' => [
                ['slug' => 'accession-intake', 'name' => 'Accession Intake Stats', 'description' => 'Generate daily accession intake statistics and processing summary.', 'artisan_command' => 'ahg:accession-intake --stats', 'cron_expression' => '0 8 * * *', 'duration_hint' => 'short', 'log_file' => 'logs/accession-intake.log'],
            ],
            'Authority Records' => [
                ['slug' => 'authority-completeness-scan', 'name' => 'Authority Completeness Scan', 'description' => 'Scan authority records for completeness against ISAAR(CPF) required fields.', 'artisan_command' => 'ahg:authority-completeness-scan', 'cron_expression' => '0 3 * * *', 'duration_hint' => 'medium', 'log_file' => 'logs/authority-completeness.log'],
                ['slug' => 'authority-ner-pipeline', 'name' => 'Authority NER Pipeline', 'description' => 'Run NER pipeline to suggest new authority records from description text.', 'artisan_command' => 'ahg:authority-ner-pipeline', 'cron_expression' => '0 4 * * *', 'duration_hint' => 'long', 'log_file' => 'logs/authority-ner.log'],
                ['slug' => 'authority-dedup-scan', 'name' => 'Authority Dedup Scan', 'description' => 'Scan authority records for potential duplicates using fuzzy matching.', 'artisan_command' => 'ahg:authority-dedup-scan', 'cron_expression' => '0 2 * * 0', 'duration_hint' => 'long', 'log_file' => 'logs/authority-dedup.log'],
                ['slug' => 'authority-function-sync', 'name' => 'Authority-Function Sync', 'description' => 'Synchronise authority-function relationships from ISDF records.', 'artisan_command' => 'ahg:authority-function-sync', 'cron_expression' => '0 5 * * *', 'duration_hint' => 'medium', 'log_file' => 'logs/authority-function-sync.log'],
            ],
            'Display / GLAM' => [
                ['slug' => 'display-reindex', 'name' => 'Display Reindex', 'description' => 'Rebuild the display/exhibition index for public-facing GLAM pages.', 'artisan_command' => 'ahg:display-reindex', 'cron_expression' => '0 4 * * 0', 'duration_hint' => 'medium', 'log_file' => 'logs/display-reindex.log'],
            ],
            'Duplicate Detection' => [
                ['slug' => 'dedupe-scan', 'name' => 'Dedupe Scan', 'description' => 'Run duplicate detection scan across information objects using configured rules and similarity threshold.', 'artisan_command' => 'ahg:dedupe-scan --limit=500 --threshold=80', 'cron_expression' => '0 3 * * 0', 'duration_hint' => 'long', 'log_file' => 'logs/dedupe-scan.log'],
            ],
            'Heritage & Linked Data' => [
                ['slug' => 'heritage-build-graph', 'name' => 'Heritage Build Graph', 'description' => 'Build the heritage knowledge graph and link entities to Getty vocabularies (AAT, TGN, ULAN).', 'artisan_command' => 'ahg:heritage-build-graph --link-getty', 'cron_expression' => '0 3 * * 0', 'duration_hint' => 'long', 'log_file' => 'logs/heritage-graph.log'],
                ['slug' => 'linked-data-sync', 'name' => 'Linked Data Sync', 'description' => 'Synchronise linked data from external authority sources (VIAF, Wikidata, LCNAF).', 'artisan_command' => 'ahg:linked-data-sync --source=all --limit=500', 'cron_expression' => '0 4 * * 0', 'duration_hint' => 'long', 'log_file' => 'logs/linked-data-sync.log'],
            ],
            'Library Management' => [
                ['slug' => 'library-overdue-check', 'name' => 'Overdue Check', 'description' => 'Check for overdue library items and send notification emails.', 'artisan_command' => 'ahg:library-overdue-check --notify', 'cron_expression' => '0 6 * * *', 'duration_hint' => 'short', 'log_file' => 'logs/library-overdue.log'],
                ['slug' => 'library-process-fines', 'name' => 'Process Fines', 'description' => 'Calculate and apply overdue fines to patron accounts.', 'artisan_command' => 'ahg:library-process-fines', 'cron_expression' => '0 1 * * *', 'duration_hint' => 'short', 'log_file' => 'logs/library-fines.log'],
                ['slug' => 'library-hold-expiry', 'name' => 'Hold Expiry', 'description' => 'Expire unfulfilled hold requests past their pickup deadline.', 'artisan_command' => 'ahg:library-hold-expiry', 'cron_expression' => '0 2 * * *', 'duration_hint' => 'short', 'log_file' => 'logs/library-holds.log'],
                ['slug' => 'library-patron-expiry', 'name' => 'Patron Expiry', 'description' => 'Flag patron accounts approaching expiry with a grace period notification.', 'artisan_command' => 'ahg:library-patron-expiry --grace-days=7', 'cron_expression' => '0 3 * * *', 'duration_hint' => 'short', 'log_file' => 'logs/library-patron.log'],
                ['slug' => 'library-serial-expected', 'name' => 'Serial Expected Issues', 'description' => 'Generate expected serial issue predictions for the next 3 months.', 'artisan_command' => 'ahg:library-serial-expected --months=3', 'cron_expression' => '0 5 * * 1', 'duration_hint' => 'medium', 'log_file' => 'logs/library-serials.log'],
                ['slug' => 'library-ill-overdue', 'name' => 'ILL Overdue', 'description' => 'Check for overdue inter-library loan items and send reminders.', 'artisan_command' => 'ahg:library-ill-overdue', 'cron_expression' => '0 6 * * *', 'duration_hint' => 'short', 'log_file' => 'logs/library-ill.log'],
                ['slug' => 'library-process-covers', 'name' => 'Process Covers', 'description' => 'Fetch and generate cover images for library items missing thumbnails.', 'artisan_command' => 'ahg:library-process-covers --missing-only --limit=100', 'cron_expression' => '0 4 * * 0', 'duration_hint' => 'medium', 'log_file' => 'logs/library-covers.log'],
            ],
            'Museum Management' => [
                ['slug' => 'museum-aat-sync', 'name' => 'AAT Sync', 'description' => 'Synchronise museum object classifications with the Getty Art & Architecture Thesaurus.', 'artisan_command' => 'ahg:museum-aat-sync --depth=3', 'cron_expression' => '0 2 1 * *', 'duration_hint' => 'long', 'log_file' => 'logs/museum-aat-sync.log'],
                ['slug' => 'museum-exhibition', 'name' => 'Exhibition Processing', 'description' => 'Process exhibition scheduling, loan status updates, and availability changes.', 'artisan_command' => 'ahg:museum-exhibition --process', 'cron_expression' => '0 7 * * *', 'duration_hint' => 'medium', 'log_file' => 'logs/museum-exhibition.log'],
            ],
            'Portable Packages' => [
                ['slug' => 'portable-cleanup', 'name' => 'Portable Cleanup', 'description' => 'Remove expired portable export packages from the staging area.', 'artisan_command' => 'ahg:portable-cleanup', 'cron_expression' => '0 3 * * *', 'duration_hint' => 'short', 'log_file' => 'logs/portable-cleanup.log'],
            ],
            'Compliance' => [
                ['slug' => 'privacy-scan-pii', 'name' => 'Privacy PII Scan', 'description' => 'Scan records for personally identifiable information under POPIA jurisdiction rules.', 'artisan_command' => 'ahg:privacy-scan-pii --jurisdiction=popia --limit=500', 'cron_expression' => '0 2 * * 1', 'duration_hint' => 'long', 'log_file' => 'logs/privacy-scan.log'],
                ['slug' => 'popia-breach-check', 'name' => 'POPIA Breach Check', 'description' => 'Check for potential data breaches requiring POPIA notification.', 'artisan_command' => 'ahg:popia-breach-check', 'cron_expression' => '0 * * * *', 'duration_hint' => 'short', 'log_file' => 'logs/breach-check.log'],
                ['slug' => 'cdpa-license-check', 'name' => 'CDPA License Check', 'description' => 'Verify copyright and licensing compliance under the CDPA framework.', 'artisan_command' => 'ahg:cdpa-license-check', 'cron_expression' => '0 6 1 * *', 'duration_hint' => 'medium', 'log_file' => 'logs/cdpa-license.log'],
                ['slug' => 'naz-closure-check', 'name' => 'NAZ Closure Check', 'description' => 'Check records against National Archives of Zimbabwe closure periods.', 'artisan_command' => 'ahg:naz-closure-check', 'cron_expression' => '0 6 1 * *', 'duration_hint' => 'medium', 'log_file' => 'logs/naz-closure.log'],
                ['slug' => 'naz-transfer-due', 'name' => 'NAZ Transfer Due', 'description' => 'Identify records due for transfer to the National Archives within the specified days.', 'artisan_command' => 'ahg:naz-transfer-due --days=90', 'cron_expression' => '0 6 1 * *', 'duration_hint' => 'medium', 'log_file' => 'logs/naz-transfer.log'],
            ],
            'Security & Compliance' => [
                ['slug' => 'services-check', 'name' => 'Services Health Check', 'description' => 'Verify all dependent services (MySQL, Redis, Elasticsearch, Qdrant, TripoSR) are reachable.', 'artisan_command' => 'ahg:services-check', 'cron_expression' => '*/5 * * * *', 'duration_hint' => 'short', 'log_file' => 'logs/services-check.log'],
            ],
            'API & Webhooks' => [
                ['slug' => 'webhook-retry', 'name' => 'Webhook Retry', 'description' => 'Retry failed webhook deliveries up to the configured maximum attempts.', 'artisan_command' => 'ahg:webhook-retry --limit=50', 'cron_expression' => '*/15 * * * *', 'duration_hint' => 'short', 'log_file' => 'logs/webhook-retries.log'],
            ],
            'Queue Engine' => [
                ['slug' => 'queue-flush', 'name' => 'Flush Failed Jobs', 'description' => 'Remove all failed jobs from the failed_jobs table.', 'artisan_command' => 'queue:flush', 'cron_expression' => '0 4 * * 0', 'duration_hint' => 'short', 'log_file' => 'logs/queue-cleanup.log'],
                ['slug' => 'queue-retry', 'name' => 'Retry All Failed Jobs', 'description' => 'Push all failed jobs back onto the queue for re-processing.', 'artisan_command' => 'queue:retry --all', 'cron_expression' => '0 6 * * 1', 'duration_hint' => 'short', 'log_file' => 'logs/queue-retry.log'],
            ],
            'RiC Triplestore' => [
                ['slug' => 'ric-queue', 'name' => 'RiC Queue Process', 'description' => 'Process RiC sync queue — push records to Fuseki triplestore. Enable when Fuseki is configured.', 'artisan_command' => 'ahg:ric-queue --limit=500', 'cron_expression' => '0 2 * * *', 'duration_hint' => 'medium', 'log_file' => 'logs/ric-queue.log', 'is_enabled' => false],
                ['slug' => 'ric-integrity', 'name' => 'RiC Integrity Check', 'description' => 'Verify RiC triples integrity and auto-fix orphans. Enable when Fuseki is configured.', 'artisan_command' => 'ahg:ric-integrity --fix', 'cron_expression' => '0 5 * * 0', 'duration_hint' => 'long', 'log_file' => 'logs/ric-integrity.log', 'is_enabled' => false],
            ],
        ];

        foreach ($categories as $category => $jobs) {
            foreach ($jobs as $job) {
                $sort++;
                $schedules[] = array_merge([
                    'category' => $category,
                    'is_enabled' => true,
                    'timeout_minutes' => 60,
                    'sort_order' => $sort,
                    'notify_on_failure' => false,
                ], $job);
            }
        }

        return $schedules;
    }
}
