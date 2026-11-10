<?php

/**
 * sharepoint:drive-add - register a SharePoint document library for capture,
 * found from the site URL and the library's name through Microsoft Graph.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgSharePoint\Console\Commands;

use AhgSharePoint\Repositories\SharePointDriveRepository;
use AhgSharePoint\Services\GraphClientService;
use Illuminate\Console\Command;

class SharePointDriveAddCommand extends Command
{
    protected $signature = 'sharepoint:drive-add {tenant : sharepoint_tenant.id} {site_url : e.g. https://contoso.sharepoint.com/sites/demo} {library=Documents : The document library name} {--parent= : Heratio record id the captured files go under}';

    protected $description = 'Register a SharePoint document library for capture (looked up through Microsoft Graph)';

    public function handle(GraphClientService $graph, SharePointDriveRepository $drives): int
    {
        $tenant = (int) $this->argument('tenant');
        $url = parse_url((string) $this->argument('site_url'));
        if (empty($url['host']) || empty($url['path'])) {
            $this->error('Give the full site URL, e.g. https://contoso.sharepoint.com/sites/demo');

            return self::FAILURE;
        }
        $site = $graph->get($tenant, '/sites/'.$url['host'].':'.rtrim($url['path'], '/'));
        $list = $graph->get($tenant, '/sites/'.$site['id'].'/drives')['value'] ?? [];
        $want = mb_strtolower((string) $this->argument('library'));
        $drive = collect($list)->first(fn ($d) => mb_strtolower((string) ($d['name'] ?? '')) === $want);
        if (! $drive) {
            $this->error('No library "'.$this->argument('library').'" on that site. Libraries: '.implode(', ', array_column($list, 'name')));

            return self::FAILURE;
        }
        $parent = $this->option('parent') ? (int) $this->option('parent') : null;
        $id = $drives->create([
            'tenant_id' => $tenant, 'site_id' => $site['id'], 'site_url' => (string) $this->argument('site_url'),
            'site_title' => $site['displayName'] ?? null, 'drive_id' => $drive['id'], 'drive_name' => $drive['name'],
            'ingest_enabled' => 1, 'sector' => 'archive', 'default_parent_id' => $parent,
            'default_parent_placement' => $parent ? 'existing' : 'top_level',
        ]);
        $this->info("Library \"{$drive['name']}\" on \"".($site['displayName'] ?? '')."\" registered as drive {$id}.");

        return self::SUCCESS;
    }
}
