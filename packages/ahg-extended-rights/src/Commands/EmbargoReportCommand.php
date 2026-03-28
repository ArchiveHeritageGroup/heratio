<?php

namespace AhgExtendedRights\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmbargoReportCommand extends Command
{
    protected $signature = 'embargo:report
        {--active : Show active embargoes}
        {--expiring=0 : Show embargoes expiring within N days}
        {--lifted : Show recently lifted embargoes}
        {--expired : Show expired but not lifted}
        {--format=table : Output format (table or csv)}
        {--days=30 : Time range for lifted/expired reports}
        {--output= : Write CSV to file path}';

    protected $description = 'Report on embargo status across the system';

    public function handle(): int
    {
        if (!Schema::hasTable('rights_embargo')) {
            $this->error('Table rights_embargo does not exist.');
            return 1;
        }

        $format = $this->option('format');

        if ($this->option('active')) {
            return $this->reportActive($format);
        }
        if ((int) $this->option('expiring') > 0) {
            return $this->reportExpiring((int) $this->option('expiring'), $format);
        }
        if ($this->option('lifted')) {
            return $this->reportLifted((int) $this->option('days'), $format);
        }
        if ($this->option('expired')) {
            return $this->reportExpiredNotLifted($format);
        }

        // Default: summary
        return $this->reportSummary();
    }

    private function reportSummary(): int
    {
        $active = DB::table('rights_embargo')->where('status', 'active')->count();
        $lifted = DB::table('rights_embargo')->where('status', 'lifted')->count();
        $expired = DB::table('rights_embargo')
            ->where('status', 'active')
            ->where('end_date', '<', now()->toDateString())
            ->count();
        $total = DB::table('rights_embargo')->count();

        $this->info('Embargo Summary');
        $this->table(
            ['Status', 'Count'],
            [
                ['Active', $active],
                ['Lifted', $lifted],
                ['Expired (not lifted)', $expired],
                ['Total', $total],
            ]
        );

        return 0;
    }

    private function reportActive(string $format): int
    {
        $rows = $this->getEmbargoRows(
            DB::table('rights_embargo as re')
                ->leftJoin('information_object_i18n as ioi', function ($j) {
                    $j->on('re.object_id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
                })
                ->where('re.status', 'active')
                ->select('re.id', 're.object_id', 'ioi.title', 're.embargo_type', 're.start_date', 're.end_date', 're.status')
                ->orderBy('re.end_date')
                ->get()
        );

        return $this->outputRows($rows, $format, 'Active Embargoes');
    }

    private function reportExpiring(int $days, string $format): int
    {
        $rows = $this->getEmbargoRows(
            DB::table('rights_embargo as re')
                ->leftJoin('information_object_i18n as ioi', function ($j) {
                    $j->on('re.object_id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
                })
                ->where('re.status', 'active')
                ->where('re.end_date', '<=', now()->addDays($days)->toDateString())
                ->where('re.end_date', '>=', now()->toDateString())
                ->select('re.id', 're.object_id', 'ioi.title', 're.embargo_type', 're.start_date', 're.end_date', 're.status')
                ->orderBy('re.end_date')
                ->get()
        );

        return $this->outputRows($rows, $format, "Embargoes expiring within {$days} days");
    }

    private function reportLifted(int $days, string $format): int
    {
        $rows = $this->getEmbargoRows(
            DB::table('rights_embargo as re')
                ->leftJoin('information_object_i18n as ioi', function ($j) {
                    $j->on('re.object_id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
                })
                ->where('re.status', 'lifted')
                ->where('re.lifted_at', '>=', now()->subDays($days))
                ->select('re.id', 're.object_id', 'ioi.title', 're.embargo_type', 're.start_date', 're.end_date', 're.status', 're.lifted_at', 're.lift_reason')
                ->orderByDesc('re.lifted_at')
                ->get()
        );

        return $this->outputRows($rows, $format, "Embargoes lifted in last {$days} days");
    }

    private function reportExpiredNotLifted(string $format): int
    {
        $rows = $this->getEmbargoRows(
            DB::table('rights_embargo as re')
                ->leftJoin('information_object_i18n as ioi', function ($j) {
                    $j->on('re.object_id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
                })
                ->where('re.status', 'active')
                ->where('re.end_date', '<', now()->toDateString())
                ->select('re.id', 're.object_id', 'ioi.title', 're.embargo_type', 're.start_date', 're.end_date', 're.status')
                ->orderBy('re.end_date')
                ->get()
        );

        return $this->outputRows($rows, $format, 'Expired but not lifted');
    }

    private function getEmbargoRows($collection): array
    {
        return $collection->map(fn ($r) => [
            'ID'       => $r->id,
            'Object'   => $r->object_id,
            'Title'    => mb_substr($r->title ?? '', 0, 50),
            'Type'     => $r->embargo_type ?? '',
            'Start'    => $r->start_date ?? '',
            'End'      => $r->end_date ?? '',
            'Status'   => $r->status ?? '',
        ])->toArray();
    }

    private function outputRows(array $rows, string $format, string $title): int
    {
        $this->info("{$title}: " . count($rows) . " records");

        if (empty($rows)) {
            return 0;
        }

        if ($format === 'csv') {
            $output = $this->option('output');
            $headers = array_keys($rows[0]);
            $csv = implode(',', $headers) . "\n";
            foreach ($rows as $row) {
                $csv .= implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', $v) . '"', $row)) . "\n";
            }

            if ($output) {
                file_put_contents($output, $csv);
                $this->info("Written to {$output}");
            } else {
                $this->line($csv);
            }
        } else {
            $this->table(array_keys($rows[0]), $rows);
        }

        return 0;
    }
}
