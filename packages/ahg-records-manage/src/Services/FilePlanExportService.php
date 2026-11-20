<?php

/**
 * FilePlanExportService - heratio#1740 follow-up.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgRecordsManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Export the file plan and the retention schedules as CSV and JSON.
 *
 * The file plan CSV uses exactly the columns the spreadsheet importer
 * (FilePlanImportService::importFromSpreadsheet) reads, with header names its
 * detectColumnMapping() maps on its own, so an exported CSV imports back
 * unchanged. The JSON carries every node field. Retention schedules have no
 * importer; their export is for interchange and evidence.
 */
class FilePlanExportService
{
    /** The importer's columns, in order. */
    public const FILEPLAN_COLUMNS = ['code', 'title', 'description', 'retention_period', 'disposal_action'];

    public const SCHEDULE_COLUMNS = ['schedule_ref', 'title', 'schedule_type', 'description', 'authority', 'disposal_authority_number', 'jurisdiction', 'effective_date', 'review_date', 'expiry_date', 'status', 'version', 'approved_by', 'approved_at'];

    public const CLASS_COLUMNS = ['class_ref', 'title', 'description', 'retention_period_years', 'retention_period_months', 'retention_trigger', 'disposal_action', 'disposal_confirmation_required', 'review_required', 'citation', 'is_active', 'sort_order'];

    /** File plan nodes, parents before children. */
    public function fileplanNodes(): array
    {
        if (! Schema::hasTable('rm_fileplan_node')) {
            return [];
        }

        return DB::table('rm_fileplan_node as n')
            ->leftJoin('rm_fileplan_node as p', 'p.id', '=', 'n.parent_id')
            ->orderBy('n.depth')->orderBy('n.lft')->orderBy('n.code')
            ->select('n.*', 'p.code as parent_code')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function fileplanCsv(): string
    {
        $rows = array_map(fn ($n) => array_map(fn ($c) => $n[$c] ?? null, self::FILEPLAN_COLUMNS), $this->fileplanNodes());

        return $this->csv(self::FILEPLAN_COLUMNS, $rows);
    }

    public function fileplanJson(): array
    {
        return [
            'format' => 'heratio-fileplan',
            'version' => 1,
            'exported_at' => now()->toIso8601String(),
            'import_columns' => self::FILEPLAN_COLUMNS,
            'nodes' => $this->fileplanNodes(),
        ];
    }

    /** Schedules, each with its disposal classes. */
    public function schedules(): array
    {
        if (! Schema::hasTable('rm_retention_schedule')) {
            return [];
        }
        $classes = Schema::hasTable('rm_disposal_class')
            ? DB::table('rm_disposal_class')->orderBy('sort_order')->orderBy('class_ref')->get()->groupBy('retention_schedule_id')
            : collect();

        return DB::table('rm_retention_schedule')->orderBy('schedule_ref')->orderBy('version')->get()
            ->map(function ($s) use ($classes) {
                $row = (array) $s;
                $row['disposal_classes'] = ($classes[$s->id] ?? collect())->map(fn ($c) => (array) $c)->values()->all();

                return $row;
            })->all();
    }

    /** One row per disposal class; a schedule with no classes gets one row with empty class columns. */
    public function schedulesCsv(): string
    {
        $header = array_merge(
            array_map(fn ($c) => 'schedule_'.preg_replace('/^schedule_/', '', $c), self::SCHEDULE_COLUMNS),
            array_map(fn ($c) => 'class_'.preg_replace('/^class_/', '', $c), self::CLASS_COLUMNS)
        );
        $rows = [];
        foreach ($this->schedules() as $s) {
            $sched = array_map(fn ($c) => $s[$c] ?? null, self::SCHEDULE_COLUMNS);
            foreach ($s['disposal_classes'] ?: [[]] as $c) {
                $rows[] = array_merge($sched, array_map(fn ($col) => $c[$col] ?? null, self::CLASS_COLUMNS));
            }
        }

        return $this->csv($header, $rows);
    }

    public function schedulesJson(): array
    {
        return [
            'format' => 'heratio-retention-schedules',
            'version' => 1,
            'exported_at' => now()->toIso8601String(),
            'schedules' => $this->schedules(),
        ];
    }

    private function csv(array $header, array $rows): string
    {
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $header, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($fh, array_map([self::class, 'cell'], $row), ',', '"', '');
        }
        rewind($fh);
        $out = stream_get_contents($fh);
        fclose($fh);

        return $out;
    }

    /**
     * A value that a spreadsheet would run as a formula (=, +, @) gets a
     * leading apostrophe; the importer strips it again (uncell()).
     */
    public static function cell($v): string
    {
        $v = (string) ($v ?? '');

        return $v !== '' && in_array($v[0], ['=', '+', '@'], true) ? "'".$v : $v;
    }

    public static function uncell(string $v): string
    {
        return strlen($v) > 1 && $v[0] === "'" && in_array($v[1], ['=', '+', '@'], true) ? substr($v, 1) : $v;
    }
}
