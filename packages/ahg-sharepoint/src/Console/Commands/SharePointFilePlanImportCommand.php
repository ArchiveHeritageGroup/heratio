<?php

/**
 * sharepoint:fileplan-import - load a file plan (CSV: code,title,
 * retention_period,disposal_action,document_types) into a SharePoint site's
 * Managed Metadata term store as a nested term set, one term per file plan
 * entry labelled "5/2/2 Purchase orders", nested by code.
 *
 *   --check    read only: show the term store, its groups and the set, write nothing
 *   --dry-run  show what would be created, write nothing
 *
 * Idempotent: an existing group, set or term (same label under the same
 * parent) is reused, so re-running adds only what is missing. Each term keeps
 * its code, retention and disposal as term properties.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgSharePoint\Console\Commands;

use AhgSharePoint\Services\GraphClientService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SharePointFilePlanImportCommand extends Command
{
    protected $signature = 'sharepoint:fileplan-import
        {csv? : File plan CSV (default: the Kopano sample in ahg-sharepoint/resources/fileplans)}
        {--drive=1 : sharepoint_drive id whose site gets the term set}
        {--group=Heratio : Term group name (created in the site collection if missing)}
        {--set=Kopano file plan : Term set name}
        {--check : Read only - show the term store and stop}
        {--dry-run : Show what would be created, write nothing}';

    protected $description = 'Import a file plan into a SharePoint site\'s Managed Metadata as a nested term set';

    public function handle(GraphClientService $graph): int
    {
        $drive = DB::table('sharepoint_drive')->where('id', (int) $this->option('drive'))->first();
        if (! $drive) {
            $this->error('No sharepoint_drive with id '.$this->option('drive'));

            return self::FAILURE;
        }
        $t = (int) $drive->tenant_id;
        $base = "/sites/{$drive->site_id}/termStore";

        $store = $graph->get($t, $base);
        $lang = (string) ($store['defaultLanguageTag'] ?? 'en-US');
        $this->info("Term store {$store['id']} (default language {$lang}) for site {$drive->site_id}");
        $groups = $graph->get($t, "{$base}/groups")['value'] ?? [];
        foreach ($groups as $g) {
            $this->line("  group: {$g['displayName']} [{$g['scope']}]");
        }
        $group = collect($groups)->firstWhere('displayName', $this->option('group'));

        if ($this->option('check')) {
            if ($group) {
                foreach ($graph->get($t, "{$base}/groups/{$group['id']}/sets")['value'] ?? [] as $s) {
                    $this->line("  set in {$group['displayName']}: ".($s['localizedNames'][0]['name'] ?? $s['id']));
                }
            }
            $this->info('Check only - nothing written.');

            return self::SUCCESS;
        }

        $rows = $this->readCsv((string) ($this->argument('csv') ?: base_path('packages/ahg-sharepoint/resources/fileplans/kopano-sample-file-plan.csv')));
        if (! $rows) {
            $this->error('The file plan CSV has no rows.');

            return self::FAILURE;
        }
        $dry = (bool) $this->option('dry-run');

        if (! $group) {
            $this->line("  + group {$this->option('group')} (site collection)");
            $group = $dry ? ['id' => 'new-group'] : $graph->post($t, "{$base}/groups", ['displayName' => $this->option('group'), 'scope' => 'siteCollection']);
        }
        $set = null;
        if ($group['id'] !== 'new-group') {
            $set = collect($graph->get($t, "{$base}/groups/{$group['id']}/sets")['value'] ?? [])
                ->first(fn ($s) => collect($s['localizedNames'] ?? [])->contains('name', $this->option('set')));
        }
        if (! $set) {
            $this->line("  + term set {$this->option('set')}");
            $set = $dry ? ['id' => 'new-set'] : $graph->post($t, "{$base}/sets", [
                'parentGroup' => ['id' => $group['id']],
                'localizedNames' => [['languageTag' => $lang, 'name' => $this->option('set')]],
                'description' => 'File plan imported by Heratio (sharepoint:fileplan-import).',
            ]);
        }

        // Parents before children: sort by depth, then code.
        usort($rows, fn ($a, $b) => [substr_count($a['code'], '/'), $a['code']] <=> [substr_count($b['code'], '/'), $b['code']]);
        $termIds = [];
        $created = $reused = 0;
        foreach ($rows as $r) {
            $label = $r['code'].' '.$r['title'];
            $parentCode = str_contains($r['code'], '/') ? substr($r['code'], 0, strrpos($r['code'], '/')) : null;
            $parentPath = $parentCode === null
                ? "{$base}/sets/{$set['id']}/children"
                : (isset($termIds[$parentCode]) ? "{$base}/sets/{$set['id']}/terms/{$termIds[$parentCode]}/children" : null);
            if ($parentPath === null) {
                $this->warn("  ! {$label}: parent {$parentCode} is not in the CSV, skipped");

                continue;
            }
            // A parent that does not exist yet (dry run) has no children to look up.
            $parentIsNew = str_contains($parentPath, '/sets/new-') || str_contains($parentPath, '/terms/new-');
            $existing = $parentIsNew ? null : collect($graph->get($t, $parentPath)['value'] ?? [])
                ->first(fn ($x) => collect($x['labels'] ?? [])->contains('name', $label));
            if ($existing) {
                $termIds[$r['code']] = $existing['id'];
                $reused++;

                continue;
            }
            $this->line("  + {$label}".($r['retention_period'] !== '' ? "  ({$r['retention_period']}, {$r['disposal_action']})" : ''));
            if ($dry) {
                $termIds[$r['code']] = 'new-'.$r['code'];
                $created++;

                continue;
            }
            $props = array_values(array_filter([
                ['key' => 'code', 'value' => $r['code']],
                $r['retention_period'] !== '' ? ['key' => 'retention', 'value' => $r['retention_period']] : null,
                $r['disposal_action'] !== '' ? ['key' => 'disposal', 'value' => $r['disposal_action']] : null,
                $r['document_types'] !== '' ? ['key' => 'document_types', 'value' => $r['document_types']] : null,
            ]));
            $term = $graph->post($t, $parentPath, [
                'labels' => [['languageTag' => $lang, 'name' => $label, 'isDefault' => true]],
                'properties' => $props,
            ]);
            $termIds[$r['code']] = $term['id'];
            $created++;
        }

        $this->info(($dry ? 'Dry run: would create ' : 'Created ').$created.' term(s); '.$reused.' already there.');
        if (! $dry) {
            $this->line("Term set id: {$set['id']} (group {$group['id']})");
        }

        return self::SUCCESS;
    }

    /** @return array<int, array{code:string,title:string,retention_period:string,disposal_action:string,document_types:string}> */
    private function readCsv(string $path): array
    {
        if (! is_file($path) || ! ($fh = fopen($path, 'r'))) {
            return [];
        }
        $head = array_map(fn ($h) => strtolower(trim((string) $h)), fgetcsv($fh) ?: []);
        $rows = [];
        while (($line = fgetcsv($fh)) !== false) {
            $row = array_combine($head, array_pad(array_map('trim', $line), count($head), ''));
            if (($row['code'] ?? '') === '' || ($row['title'] ?? '') === '') {
                continue;
            }
            $rows[] = ['code' => $row['code'], 'title' => $row['title'], 'retention_period' => $row['retention_period'] ?? '',
                'disposal_action' => $row['disposal_action'] ?? '', 'document_types' => $row['document_types'] ?? ''];
        }
        fclose($fh);

        return $rows;
    }
}
