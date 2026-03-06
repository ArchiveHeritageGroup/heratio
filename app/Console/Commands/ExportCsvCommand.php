<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportCsvCommand extends Command
{
    protected $signature = 'heratio:export:csv
                            {type : Entity type to export (informationobject, actor, repository, accession)}
                            {--output= : Output file path (default: stdout)}
                            {--culture=en : Culture/language for i18n fields}';

    protected $description = 'Export data to CSV format';

    public function handle(): int
    {
        $type = $this->argument('type');
        $culture = $this->option('culture') ?? 'en';
        $outputPath = $this->option('output');

        $exportMethod = match ($type) {
            'informationobject' => 'exportInformationObjects',
            'actor' => 'exportActors',
            'repository' => 'exportRepositories',
            'accession' => 'exportAccessions',
            default => null,
        };

        if (! $exportMethod) {
            $this->error("Invalid type: {$type}. Valid types: informationobject, actor, repository, accession");

            return self::FAILURE;
        }

        $this->info("Exporting {$type} records...");

        $output = $outputPath ? fopen($outputPath, 'w') : STDOUT;

        if ($output === false) {
            $this->error("Cannot open output file: {$outputPath}");

            return self::FAILURE;
        }

        $count = $this->{$exportMethod}($output, $culture);

        if ($outputPath) {
            fclose($output);
            $this->info("Exported {$count} records to {$outputPath}");
        } else {
            $this->info("Exported {$count} records.", verbosity: \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_VERBOSE);
        }

        return self::SUCCESS;
    }

    private function exportInformationObjects($output, string $culture): int
    {
        $headers = [
            'id', 'identifier', 'title', 'level_of_description', 'extent_and_medium',
            'scope_and_content', 'arrangement', 'repository', 'parent_id',
            'publication_status', 'culture',
        ];
        fputcsv($output, $headers);

        $query = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($join) use ($culture) {
                $join->on('io.id', '=', 'i18n.id')
                    ->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as lod', function ($join) use ($culture) {
                $join->on('io.level_of_description_id', '=', 'lod.id')
                    ->where('lod.culture', '=', $culture);
            })
            ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
            ->leftJoin('status as st', function ($join) {
                $join->on('io.id', '=', 'st.object_id')
                    ->where('st.type_id', '=', 158); // publication status type
            })
            ->leftJoin('term_i18n as ps', function ($join) use ($culture) {
                $join->on('st.status_id', '=', 'ps.id')
                    ->where('ps.culture', '=', $culture);
            })
            ->where('io.id', '!=', 1) // Exclude root node
            ->select([
                'io.id',
                'io.identifier',
                'i18n.title',
                'lod.name as level_of_description',
                'i18n.extent_and_medium',
                'i18n.scope_and_content',
                'i18n.arrangement',
                'io.repository_id',
                'io.parent_id',
                DB::raw("COALESCE(ps.name, 'Draft') as publication_status"),
                DB::raw("'{$culture}' as culture"),
            ])
            ->orderBy('io.lft');

        $count = 0;
        $query->chunk(500, function ($rows) use ($output, &$count) {
            foreach ($rows as $row) {
                fputcsv($output, (array) $row);
                $count++;
            }
        });

        return $count;
    }

    private function exportActors($output, string $culture): int
    {
        $headers = [
            'id', 'authorized_form_of_name', 'entity_type', 'dates_of_existence',
            'history', 'places', 'culture',
        ];
        fputcsv($output, $headers);

        $query = DB::table('actor as a')
            ->leftJoin('actor_i18n as i18n', function ($join) use ($culture) {
                $join->on('a.id', '=', 'i18n.id')
                    ->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as et', function ($join) use ($culture) {
                $join->on('a.entity_type_id', '=', 'et.id')
                    ->where('et.culture', '=', $culture);
            })
            ->whereNotIn('a.id', [3, 4]) // Exclude system actors
            ->select([
                'a.id',
                'i18n.authorized_form_of_name',
                'et.name as entity_type',
                'i18n.dates_of_existence',
                'i18n.history',
                'i18n.places',
                DB::raw("'{$culture}' as culture"),
            ])
            ->orderBy('a.id');

        $count = 0;
        $query->chunk(500, function ($rows) use ($output, &$count) {
            foreach ($rows as $row) {
                fputcsv($output, (array) $row);
                $count++;
            }
        });

        return $count;
    }

    private function exportRepositories($output, string $culture): int
    {
        $headers = [
            'id', 'authorized_form_of_name', 'identifier', 'history',
            'collecting_policies', 'buildings', 'culture',
        ];
        fputcsv($output, $headers);

        $query = DB::table('repository as r')
            ->leftJoin('actor_i18n as i18n', function ($join) use ($culture) {
                $join->on('r.id', '=', 'i18n.id')
                    ->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('repository_i18n as ri', function ($join) use ($culture) {
                $join->on('r.id', '=', 'ri.id')
                    ->where('ri.culture', '=', $culture);
            })
            ->select([
                'r.id',
                'i18n.authorized_form_of_name',
                'r.identifier',
                'i18n.history',
                'ri.collecting_policies',
                'ri.buildings',
                DB::raw("'{$culture}' as culture"),
            ])
            ->orderBy('r.id');

        $count = 0;
        $query->chunk(500, function ($rows) use ($output, &$count) {
            foreach ($rows as $row) {
                fputcsv($output, (array) $row);
                $count++;
            }
        });

        return $count;
    }

    private function exportAccessions($output, string $culture): int
    {
        $headers = [
            'id', 'identifier', 'date', 'source_of_acquisition',
            'location_information', 'acquisition_type', 'processing_status',
            'processing_priority', 'culture',
        ];
        fputcsv($output, $headers);

        $query = DB::table('accession as a')
            ->leftJoin('accession_i18n as i18n', function ($join) use ($culture) {
                $join->on('a.id', '=', 'i18n.id')
                    ->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as at', function ($join) use ($culture) {
                $join->on('a.acquisition_type_id', '=', 'at.id')
                    ->where('at.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as ps', function ($join) use ($culture) {
                $join->on('a.processing_status_id', '=', 'ps.id')
                    ->where('ps.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as pp', function ($join) use ($culture) {
                $join->on('a.processing_priority_id', '=', 'pp.id')
                    ->where('pp.culture', '=', $culture);
            })
            ->select([
                'a.id',
                'a.identifier',
                'a.date',
                'i18n.source_of_acquisition',
                'i18n.location_information',
                'at.name as acquisition_type',
                'ps.name as processing_status',
                'pp.name as processing_priority',
                DB::raw("'{$culture}' as culture"),
            ])
            ->orderBy('a.id');

        $count = 0;
        $query->chunk(500, function ($rows) use ($output, &$count) {
            foreach ($rows as $row) {
                fputcsv($output, (array) $row);
                $count++;
            }
        });

        return $count;
    }
}
