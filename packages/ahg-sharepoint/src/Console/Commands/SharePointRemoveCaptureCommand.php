<?php

/**
 * sharepoint:remove-capture - remove a document a visitor dropped, everywhere:
 * the SharePoint file and its redacted copy, the record on Heratio with its
 * digital object files, search entry and AI read results, and the downloaded
 * copy in the SharePoint inbox. For real (client) documents dropped during a
 * live demonstration, which must not stay on a server the public demo login
 * reaches.
 *
 * Shows everything it matched and asks before deleting. --dry-run only lists.
 * A digital object file is deleted only if the PSIS database ("archive") does
 * not point at the same file (the uploads folder is shared with PSIS).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgSharePoint\Console\Commands;

use AhgSharePoint\Services\GraphClientService;
use AhgSharePoint\Services\SharePointWriteBackService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SharePointRemoveCaptureCommand extends Command
{
    protected $signature = 'sharepoint:remove-capture
        {name : Part of the SharePoint file name, e.g. "Scan_0001" or "INV-12345"}
        {--drive=1 : sharepoint_drive id}
        {--dry-run : List what would be removed, delete nothing}
        {--force : Do not ask for confirmation}';

    protected $description = 'Remove a dropped document from SharePoint and Heratio (file, redacted copy, record, files, search entry)';

    public function handle(GraphClientService $graph): int
    {
        $needle = trim((string) $this->argument('name'));
        if (mb_strlen($needle) < 4) {
            $this->error('Give at least 4 characters of the file name.');

            return self::FAILURE;
        }
        $drive = DB::table('sharepoint_drive')->where('id', (int) $this->option('drive'))->first();
        if (! $drive) {
            $this->error('No sharepoint_drive with id '.$this->option('drive'));

            return self::FAILURE;
        }
        $t = (int) $drive->tenant_id;
        $base = "/sites/{$drive->site_id}/drives/{$drive->drive_id}";

        // SharePoint: the file(s) and redacted copies whose name contains the needle.
        $spItems = [];
        foreach ([$base.'/root/children', $base.'/root:/'.rawurlencode(SharePointWriteBackService::REDACTED_FOLDER).':/children'] as $path) {
            try {
                foreach ($graph->get($t, $path.'?$select=id,name,file&$top=999')['value'] ?? [] as $it) {
                    if (isset($it['file']) && mb_stripos($it['name'], $needle) !== false) {
                        $spItems[$it['id']] = $it['name'];
                    }
                }
            } catch (\Throwable) {
                // No Redacted copies folder yet.
            }
        }

        // Heratio: the records those files were captured as.
        $events = DB::table('sharepoint_event')->where('drive_id', $drive->id)
            ->where(fn ($q) => $q->whereIn('sp_item_id', array_keys($spItems) ?: ['-'])
                ->orWhere('raw_payload', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $needle).'%'))
            ->get(['id', 'sp_item_id', 'information_object_id']);
        $ioIds = $events->pluck('information_object_id')->filter()->unique()->values()->all();
        $titles = DB::table('information_object_i18n')->whereIn('id', $ioIds ?: [0])->where('culture', 'en')->pluck('title', 'id');

        if (! $spItems && ! $ioIds) {
            $this->warn("Nothing found matching \"{$needle}\".");

            return self::SUCCESS;
        }
        $this->line('SharePoint files:');
        foreach ($spItems as $name) {
            $this->line("  - {$name}");
        }
        $this->line('Heratio records:');
        foreach ($ioIds as $id) {
            $this->line("  - {$id} ".($titles[$id] ?? ''));
        }
        if ($this->option('dry-run')) {
            $this->info('Dry run - nothing deleted.');

            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm('Delete all of the above?', false)) {
            $this->line('Nothing deleted.');

            return self::SUCCESS;
        }

        foreach ($spItems as $id => $name) {
            $graph->delete($t, "{$base}/items/{$id}");
            $this->line("  deleted in SharePoint: {$name}");
        }
        foreach ($ioIds as $ioId) {
            $this->removeRecord((int) $ioId);
        }
        foreach ($events as $ev) {
            $inbox = rtrim((string) config('heratio.uploads_path'), '/')."/sharepoint-inbox/{$ev->id}";
            if (is_dir($inbox)) {
                array_map('unlink', glob($inbox.'/*') ?: []);
                @rmdir($inbox);
            }
            DB::table('sharepoint_event')->where('id', $ev->id)->update(['information_object_id' => null, 'status' => 'removed']);
        }
        $this->info('Removed.');

        return self::SUCCESS;
    }

    private function removeRecord(int $ioId): void
    {
        $io = DB::table('information_object')->where('id', $ioId)->first(['id', 'lft', 'rgt']);
        if (! $io) {
            return;
        }
        // Digital object files (master and derivatives), unless PSIS points at the same file.
        $dos = DB::table('digital_object')->where('object_id', $ioId)->get();
        $dos = $dos->merge(DB::table('digital_object')->whereIn('parent_id', $dos->pluck('id')->all() ?: [0])->get());
        foreach ($dos as $do) {
            $file = \AhgCore\Services\DigitalObjectService::resolveDiskPath($do);
            $sharedWithPsis = false;
            try {
                $sharedWithPsis = DB::table('archive.digital_object')->where('path', $do->path)->where('name', $do->name)->exists();
            } catch (\Throwable) {
                $sharedWithPsis = true;   // cannot check PSIS: keep the file
            }
            if ($file && is_file($file) && ! $sharedWithPsis) {
                @unlink($file);
            }
        }
        DB::table('digital_object')->whereIn('id', $dos->pluck('id')->all() ?: [0])->delete();
        foreach (['ahg_ai_suggestion' => 'object_id', 'status' => 'object_id'] as $table => $col) {
            try {
                DB::table($table)->where($col, $ioId)->delete();
            } catch (\Throwable) {
            }
        }
        // Search entry.
        try {
            Http::timeout(5)->delete(rtrim((string) config('services.elasticsearch.host'), '/').'/'.config('services.elasticsearch.prefix', 'heratio_').'qubitinformationobject/_doc/'.$ioId);
        } catch (\Throwable) {
        }
        // The record itself, as the record page's delete does.
        $width = $io->rgt - $io->lft + 1;
        DB::table('information_object_i18n')->where('id', $ioId)->delete();
        DB::table('information_object')->where('id', $ioId)->delete();
        DB::table('slug')->where('object_id', $ioId)->delete();
        DB::table('object')->where('id', $ioId)->delete();
        if ($io->rgt) {
            DB::table('information_object')->where('lft', '>', $io->rgt)->decrement('lft', $width);
            DB::table('information_object')->where('rgt', '>', $io->rgt)->decrement('rgt', $width);
        }
        $this->line("  deleted on Heratio: record {$ioId}");
    }
}
