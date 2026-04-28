<?php

/**
 * LoadDigitalObjectsCommand — batch attach digital files under a parent IO.
 *
 * Walks a source directory, creates child information_object records
 * under the supplied parent slug, and uploads each file as the master
 * digital_object via DigitalObjectService.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use AhgCore\Services\DigitalObjectService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoadDigitalObjectsCommand extends Command
{
    protected $signature = 'ahg:load-digital-objects
        {--path= : Source directory path}
        {--attach-to= : Parent information object slug}
        {--limit= : Maximum objects to load}';

    protected $description = 'Batch load digital objects under a parent IO';

    public function handle(): int
    {
        $path = (string) $this->option('path');
        $slug = (string) $this->option('attach-to');
        $limit = $this->option('limit') ? max(1, (int) $this->option('limit')) : PHP_INT_MAX;

        if (! $path || ! is_dir($path)) {
            $this->error("--path must be a readable directory: {$path}");
            return self::FAILURE;
        }
        if (! $slug) {
            $this->error('--attach-to (parent slug) is required');
            return self::FAILURE;
        }

        $parent = DB::table('information_object as i')
            ->join('slug as s', 's.object_id', '=', 'i.id')
            ->where('s.slug', $slug)
            ->select('i.id', 'i.repository_id', 'i.lft', 'i.rgt')
            ->first();
        if (! $parent) {
            $this->error("parent slug not found: {$slug}");
            return self::FAILURE;
        }

        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile()) $files[] = $f->getPathname();
            if (count($files) >= $limit) break;
        }
        sort($files);
        $this->info("loading " . count($files) . " files under #{$parent->id} ({$slug})");

        $loaded = 0; $failed = 0;
        foreach ($files as $file) {
            try {
                $childId = $this->createChildIo($file, $parent);
                $upload = new UploadedFile($file, basename($file), null, null, true);
                DigitalObjectService::upload($childId, $upload);
                $loaded++;
                if ($loaded % 50 === 0) $this->line("  …{$loaded}/" . count($files));
            } catch (\Throwable $e) {
                $failed++;
                $this->warn(sprintf('  FAIL %s — %s', basename($file), $e->getMessage()));
            }
        }
        $this->info("loaded={$loaded} failed={$failed}");
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    protected function createChildIo(string $file, object $parent): int
    {
        $title = pathinfo($file, PATHINFO_FILENAME);
        $now = now()->format('Y-m-d H:i:s');
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitInformationObject',
            'created_at' => $now,
            'updated_at' => $now,
            'serial_number' => 0,
        ]);
        DB::table('information_object')->insert([
            'id' => $objectId,
            'parent_id' => $parent->id,
            'repository_id' => $parent->repository_id,
            'level_of_description_id' => null,
            'lft' => 0,
            'rgt' => 0,
            'source_culture' => 'en',
        ]);
        DB::table('information_object_i18n')->insert([
            'id' => $objectId,
            'culture' => 'en',
            'title' => $title,
        ]);
        $slug = Str::slug($title) ?: ('item-' . $objectId);
        if (DB::table('slug')->where('slug', $slug)->exists()) $slug .= '-' . $objectId;
        DB::table('slug')->insert(['object_id' => $objectId, 'slug' => $slug]);
        return $objectId;
    }
}
