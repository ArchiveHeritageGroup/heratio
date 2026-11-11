<?php

/**
 * sharepoint:records-columns - give a library the records-management columns
 * Heratio fills after reading a document (Record Class, Function/Directorate,
 * Record Type, Record Date, Status), with the choices those values need.
 *
 * Takes the column definitions from a model library (default "Records"),
 * adds the choices Heratio uses that are missing there (renaming the
 * placeholder "Choice 6" to "Health and Safety"), then creates any missing
 * columns on the target library (the capture drive's library) with the same
 * choices, so both libraries look the same.
 *
 *   --check  read only: show what would change
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgSharePoint\Console\Commands;

use AhgSharePoint\Services\GraphClientService;
use AhgSharePoint\Services\SharePointRecordsClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SharePointRecordsColumnsCommand extends Command
{
    protected $signature = 'sharepoint:records-columns
        {--drive=1 : sharepoint_drive id whose library gets the columns}
        {--model=Records : Library whose records columns are the model}
        {--check : Read only - show what would change}';

    protected $description = 'Add the records-management columns (Record Class, Function/Directorate, Record Type, Record Date, Status) to a SharePoint library';

    public function handle(GraphClientService $graph): int
    {
        $drive = DB::table('sharepoint_drive')->where('id', (int) $this->option('drive'))->first();
        if (! $drive) {
            $this->error('No sharepoint_drive with id '.$this->option('drive'));

            return self::FAILURE;
        }
        $t = (int) $drive->tenant_id;
        $site = $drive->site_id;
        $check = (bool) $this->option('check');

        $lists = $graph->get($t, "/sites/{$site}/lists?\$select=id,displayName")['value'] ?? [];
        $model = collect($lists)->firstWhere('displayName', $this->option('model'));
        $targetId = $graph->get($t, "/sites/{$site}/drives/{$drive->drive_id}/list?\$select=id")['id'];
        if (! $model) {
            $this->error('No library named '.$this->option('model').' in the site.');

            return self::FAILURE;
        }

        $wanted = SharePointRecordsClassifier::choicesNeeded();     // display name => [choices]
        $modelCols = $this->columns($graph, $t, $site, $model['id']);

        // 1. The model library: rename the placeholder, add missing choices.
        foreach ($wanted as $display => $choices) {
            $col = $modelCols[$display] ?? null;
            if (! $col || ! isset($col['choice'])) {
                continue;
            }
            $have = array_map(fn ($c) => $c === 'Choice 6' ? 'Health and Safety' : $c, $col['choice']['choices'] ?? []);
            $new = array_values(array_unique(array_merge($have, $choices)));
            if ($new !== ($col['choice']['choices'] ?? [])) {
                $this->line("  {$this->option('model')} / {$display}: ".implode(' | ', $new));
                if (! $check) {
                    $graph->patch($t, "/sites/{$site}/lists/{$model['id']}/columns/{$col['id']}", ['choice' => array_merge($col['choice'], ['choices' => $new])]);
                }
                $modelCols[$display]['choice']['choices'] = $new;
            }
        }

        // 1b. File plan as a Choice column on both libraries (Graph cannot write
        // Managed Metadata). An existing File plan column that is not a Choice
        // (e.g. Managed Metadata) is left alone - delete it first to switch.
        foreach ([[$model['id'], $this->option('model'), false], [$targetId, $drive->drive_name, true]] as [$listId, $listName, $supplierOnly]) {
            $cols = $listId === $model['id'] ? $modelCols : $this->columns($graph, $t, $site, $listId);
            $entries = SharePointRecordsClassifier::fileplanEntries($supplierOnly);
            $fp = $cols['File plan'] ?? null;
            if ($fp && ! isset($fp['choice'])) {
                $this->warn("  {$listName} / File plan is not a Choice column (Managed Metadata?) - delete it to let Heratio create the Choice version");

                continue;
            }
            if (! $fp) {
                $this->line("  + {$listName} / File plan (".count($entries).' entries)');
                if (! $check) {
                    $graph->post($t, "/sites/{$site}/lists/{$listId}/columns", ['name' => 'FilePlan', 'displayName' => 'File plan',
                        'choice' => ['allowTextEntry' => false, 'choices' => $entries, 'displayAs' => 'dropDownMenu']]);
                }
            } elseif (array_diff($entries, $fp['choice']['choices'] ?? [])) {
                $this->line("  {$listName} / File plan: ".count($entries).' entries');
                if (! $check) {
                    $graph->patch($t, "/sites/{$site}/lists/{$listId}/columns/{$fp['id']}", ['choice' => array_merge($fp['choice'], ['choices' => array_values(array_unique(array_merge($fp['choice']['choices'] ?? [], $entries)))])]);
                }
            }
            if ($listId === $targetId && $targetId === $model['id']) {
                break;
            }
        }

        // 2. The target library (Supplier invoices): only the columns relevant to
        // supplier documents - Record Type (supplier types only), Record Date,
        // Status. Record Class and Function/Directorate would always read
        // SCM/Procurement and Finance there, so they are left out.
        if ($targetId !== $model['id']) {
            $targetCols = $this->columns($graph, $t, $site, $targetId);
            $supplier = SharePointRecordsClassifier::choicesNeeded(true);
            foreach (['Record Type', 'Record Date', 'Status'] as $display) {
                if (! isset($modelCols[$display])) {
                    continue;
                }
                $m = $modelCols[$display];
                if (isset($targetCols[$display])) {
                    // Already there: make sure its choices cover the supplier types.
                    $have = $targetCols[$display]['choice']['choices'] ?? null;
                    if ($have !== null && array_diff($supplier[$display] ?? [], $have)) {
                        $new = array_values(array_unique(array_merge($have, $supplier[$display])));
                        $this->line("  {$drive->drive_name} / {$display}: ".implode(' | ', $new));
                        if (! $check) {
                            $graph->patch($t, "/sites/{$site}/lists/{$targetId}/columns/{$targetCols[$display]['id']}", ['choice' => array_merge($targetCols[$display]['choice'], ['choices' => $new])]);
                        }
                    }

                    continue;
                }
                $body = ['name' => $m['name'], 'displayName' => $display];
                if (isset($m['choice'])) {
                    $body['choice'] = ['allowTextEntry' => false, 'choices' => $supplier[$display] ?? $m['choice']['choices'], 'displayAs' => $m['choice']['displayAs'] ?? 'dropDownMenu'];
                } elseif (isset($m['dateTime'])) {
                    $body['dateTime'] = ['format' => $m['dateTime']['format'] ?? 'dateOnly', 'displayAs' => 'default'];
                } else {
                    continue;
                }
                $this->line("  + {$drive->drive_name} / {$display}");
                if (! $check) {
                    $graph->post($t, "/sites/{$site}/lists/{$targetId}/columns", $body);
                }
            }
        }

        $this->info($check ? 'Check only - nothing written.' : 'Done.');

        return self::SUCCESS;
    }

    /** @return array<string, array> display name => column */
    private function columns(GraphClientService $graph, int $t, string $site, string $listId): array
    {
        $out = [];
        foreach ($graph->get($t, "/sites/{$site}/lists/{$listId}/columns")['value'] ?? [] as $c) {
            $out[(string) ($c['displayName'] ?? '')] = $c;
        }

        return $out;
    }
}
