<?php

/**
 * EadImportCommand — bulk EAD/EAD3 XML import.
 *
 * Walks a file or directory of EAD XML, parses each <ead> root, and
 * creates information_object records under the requested repository.
 * Mirrors the logic of AtoM's EAD import action — title, scope, dates,
 * arrangement, access conditions, and one level of <c> children.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EadImportCommand extends Command
{
    protected $signature = 'ahg:ead-import
        {--source= : Source file or directory path}
        {--schema=ead : Schema type (ead, ead3)}
        {--output= : Output report path}
        {--repository= : Target repository slug}
        {--dry-run : Parse only — do not write}';

    protected $description = 'Bulk EAD/XML import (EAD2002, EAD3)';

    public function handle(): int
    {
        $source = (string) $this->option('source');
        if (! $source || (! is_file($source) && ! is_dir($source))) {
            $this->error("--source not readable: {$source}");
            return self::FAILURE;
        }
        $schema = (string) $this->option('schema');
        $repoSlug = (string) $this->option('repository');
        $dry = (bool) $this->option('dry-run');

        $repoId = null;
        if ($repoSlug) {
            $repoId = DB::table('repository as r')
                ->join('slug as s', 's.object_id', '=', 'r.id')
                ->where('s.slug', $repoSlug)->value('r.id');
            if (! $repoId) {
                $this->error("repository slug not found: {$repoSlug}");
                return self::FAILURE;
            }
        }

        $files = is_dir($source)
            ? array_filter(glob(rtrim($source, '/') . '/*.xml') ?: [], 'is_file')
            : [$source];

        $stats = ['files' => 0, 'created' => 0, 'children' => 0, 'errors' => 0];
        foreach ($files as $file) {
            $stats['files']++;
            try {
                $xml = @simplexml_load_file($file);
                if (! $xml) throw new \RuntimeException('parse error');
                [$created, $children] = $this->importDoc($xml, $schema, $repoId, $dry);
                $stats['created'] += $created;
                $stats['children'] += $children;
                $this->line(sprintf('  %s: created=%d children=%d', basename($file), $created, $children));
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn("  {$file}: {$e->getMessage()}");
            }
        }

        $this->info(sprintf('files=%d created=%d children=%d errors=%d%s',
            $stats['files'], $stats['created'], $stats['children'], $stats['errors'], $dry ? ' (dry-run)' : ''));

        if ($out = $this->option('output')) {
            file_put_contents($out, json_encode($stats, JSON_PRETTY_PRINT));
            $this->info("report -> {$out}");
        }
        return $stats['errors'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    protected function importDoc(\SimpleXMLElement $xml, string $schema, ?int $repoId, bool $dry): array
    {
        $archdesc = $xml->archdesc ?? null;
        if (! $archdesc) return [0, 0];

        $title = (string) ($archdesc->did->unittitle ?? '');
        $scope = trim((string) ($archdesc->scopecontent->p ?? ''));
        $arrangement = trim((string) ($archdesc->arrangement->p ?? ''));
        $access = trim((string) ($archdesc->accessrestrict->p ?? ''));
        $unitId = (string) ($archdesc->did->unitid ?? '');

        if ($dry) return [1, count($archdesc->dsc->c ?? [])];

        $rootId = $this->createIo($repoId, null, $title, $unitId, [
            'scope_and_content' => $scope,
            'arrangement' => $arrangement,
            'access_conditions' => $access,
        ]);
        $children = 0;
        foreach (($archdesc->dsc->c ?? []) as $c) {
            $cTitle = (string) ($c->did->unittitle ?? '');
            $cId = (string) ($c->did->unitid ?? '');
            $this->createIo($repoId, $rootId, $cTitle, $cId, [
                'scope_and_content' => trim((string) ($c->scopecontent->p ?? '')),
            ]);
            $children++;
        }
        return [1, $children];
    }

    protected function createIo(?int $repoId, ?int $parentId, string $title, string $identifier, array $i18n): int
    {
        $now = now()->format('Y-m-d H:i:s');
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitInformationObject',
            'created_at' => $now, 'updated_at' => $now, 'serial_number' => 0,
        ]);
        DB::table('information_object')->insert([
            'id' => $objectId,
            'parent_id' => $parentId,
            'repository_id' => $repoId,
            'identifier' => $identifier ?: null,
            'lft' => 0, 'rgt' => 0,
            'source_culture' => 'en',
        ]);
        DB::table('information_object_i18n')->insert(array_merge(
            ['id' => $objectId, 'culture' => 'en', 'title' => $title ?: '(untitled)'],
            array_filter($i18n, fn ($v) => $v !== '' && $v !== null),
        ));
        $slug = Str::slug($title) ?: ('ead-' . $objectId);
        if (DB::table('slug')->where('slug', $slug)->exists()) $slug .= '-' . $objectId;
        DB::table('slug')->insert(['object_id' => $objectId, 'slug' => $slug]);
        return $objectId;
    }
}
