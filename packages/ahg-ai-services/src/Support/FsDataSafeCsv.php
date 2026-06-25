<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgAiServices\Support;

/**
 * Writes FS-Scotland records to a FamilySearch Data Safe CSV: header row of
 * Index System Names in the canonical column order, one row per record, missing
 * fields emitted as empty. Deterministic / no I/O dependency (pure string).
 */
final class FsDataSafeCsv
{
    /**
     * @param array<int,array<string,string>> $records each keyed by system name
     */
    public static function toString(array $records): string
    {
        $cols = array_keys(FsScotlandProfile::COLUMNS);
        $out = fopen('php://temp', 'r+');
        fputcsv($out, $cols);
        foreach ($records as $record) {
            $row = [];
            foreach ($cols as $sys) {
                $row[] = (string) ($record[$sys] ?? '');
            }
            fputcsv($out, $row);
        }
        rewind($out);
        $csv = stream_get_contents($out) ?: '';
        fclose($out);

        return $csv;
    }

    /** Write the CSV to $path; returns bytes written or false. */
    public static function toFile(array $records, string $path): int|false
    {
        return file_put_contents($path, self::toString($records));
    }
}
