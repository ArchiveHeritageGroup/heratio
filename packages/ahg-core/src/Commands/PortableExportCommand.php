<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PortableExportCommand extends Command
{
    protected $signature = 'ahg:portable-export
        {--title= : Title for the export package}
        {--scope-type=all : Export scope (all, repository, collection)}
        {--scope-slug= : IO/repository slug}
        {--scope-repository-id= : Repository ID}
        {--mode=read_only : Export mode (read_only, transfer, backup)}
        {--output= : Output directory (default: heratio.backups_path/portable)}
        {--include-masters : Include master files (default: derivatives only)}
        {--no-thumbnails : Exclude thumbnails}
        {--user-id=1 : User id to credit the export to}';

    protected $description = 'Generate portable catalogue (creates portable_export row + queues a worker job)';

    public function handle(): int
    {
        $title = $this->option('title') ?: ('Portable export ' . now()->toDateTimeString());
        $scopeType = (string) $this->option('scope-type');
        $output = $this->option('output') ?: rtrim((string) config('heratio.backups_path', '/tmp'), '/') . '/portable';
        @mkdir($output, 0775, true);

        $rowId = DB::table('portable_export')->insertGetId([
            'user_id'              => (int) $this->option('user-id'),
            'title'                => $title,
            'scope_type'           => $scopeType,
            'scope_slug'           => $this->option('scope-slug'),
            'scope_repository_id'  => $this->option('scope-repository-id') ? (int) $this->option('scope-repository-id') : null,
            'mode'                 => (string) $this->option('mode'),
            'include_masters'      => $this->option('include-masters') ? 1 : 0,
            'include_thumbnails'   => $this->option('no-thumbnails') ? 0 : 1,
            'status'               => 'pending',
            'output_path'          => $output . '/' . preg_replace('/[^A-Za-z0-9_-]/','-',$title) . '.zip',
            'created_at'           => now(),
        ]);

        $this->info("created portable_export id={$rowId} status=pending output={$output}");
        $this->info("worker should pick this up via ahg:portable-export-worker (or you can run that command directly to process the row)");
        return self::SUCCESS;
    }
}
