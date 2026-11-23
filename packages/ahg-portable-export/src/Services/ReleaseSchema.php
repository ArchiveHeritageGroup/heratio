<?php

namespace AhgPortableExport\Services;

use Illuminate\Support\Facades\DB;

/** Applies the #1586 section of install.sql and its dropdown seed. Idempotent. */
class ReleaseSchema
{
    public static function install(): void
    {
        $dir = __DIR__.'/../../database/';
        $sql = (string) file_get_contents($dir.'install.sql');
        $start = strpos($sql, '-- portable-release-begin');
        $end = strpos($sql, '-- portable-release-end');
        if ($start === false || $end === false) {
            throw new \RuntimeException('install.sql has no portable-release section');
        }
        $section = preg_replace('/^--.*$/m', '', substr($sql, $start, $end - $start));
        foreach (array_filter(array_map('trim', explode(";\n", $section))) as $statement) {
            DB::statement(rtrim($statement, ';'));
        }
        DB::unprepared((string) file_get_contents($dir.'seed_dropdowns.sql'));
    }
}
