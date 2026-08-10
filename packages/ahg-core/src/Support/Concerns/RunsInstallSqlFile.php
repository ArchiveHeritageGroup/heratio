<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgCore\Support\Concerns;

/**
 * Execute a .sql install file statement-by-statement (comment/blank lines
 * stripped). Byte-identical in AhgAiComplianceServiceProvider and
 * AhgC2paServiceProvider.
 */
trait RunsInstallSqlFile
{
    private function runInstallSqlFile(string $path): void
    {
        $sql = (string) file_get_contents($path);

        $lines = preg_split('/\r?\n/', $sql) ?: [];
        $stripped = '';
        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }
            $stripped .= $line . "\n";
        }

        foreach (array_filter(array_map('trim', explode(';', $stripped))) as $stmt) {
            if ($stmt !== '') {
                \DB::statement($stmt);
            }
        }
    }
}
