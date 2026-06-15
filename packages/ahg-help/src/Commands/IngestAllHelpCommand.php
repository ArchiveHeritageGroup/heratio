<?php

/**
 * IngestAllHelpCommand - Command for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgHelp\Commands;

use Illuminate\Console\Command;

/**
 * Bulk-ingest every markdown file under docs/help/ into the help_article table
 * (upsert by slug, via ahg:help-ingest per file). Run by bin/install so a fresh
 * install ships a fully populated Help Center rather than an empty one. Safe to
 * re-run: each article is upserted by slug.
 */
class IngestAllHelpCommand extends Command
{
    protected $signature = 'ahg:help-ingest-all {--dir=docs/help : Directory of markdown help files, relative to base_path()}';

    protected $description = 'Bulk-ingest every markdown file in docs/help/ into help_article (upsert by slug).';

    public function handle(): int
    {
        $dir = base_path((string) $this->option('dir'));
        if (! is_dir($dir)) {
            $this->error("Help directory not found: {$dir}");

            return self::FAILURE;
        }

        $files = glob(rtrim($dir, '/').'/*.md') ?: [];
        if (! $files) {
            $this->warn("No markdown help files in {$dir}");

            return self::SUCCESS;
        }

        $ok = 0;
        $fail = 0;
        foreach ($files as $path) {
            $base = basename($path, '.md');
            // callSilent: suppress per-file output (hundreds of files).
            $code = $this->callSilent('ahg:help-ingest', [
                '--path' => $path,
                '--slug' => $base,
                '--category' => $this->categoryFor($base),
            ]);
            if ($code === self::SUCCESS) {
                $ok++;
            } else {
                $fail++;
                $this->warn("  failed: {$base}");
            }
        }

        $this->info("Help ingest complete: {$ok} ingested, {$fail} failed, ".count($files).' total.');

        // Non-fatal: a few failures should not abort an install.
        return self::SUCCESS;
    }

    /** Group articles into a sensible help category by filename convention. */
    private function categoryFor(string $base): string
    {
        if (str_ends_with($base, '-user-guide')) {
            return 'User Guides';
        }
        if (str_starts_with($base, 'ahg') && str_ends_with($base, 'plugin')) {
            return 'Plugins';
        }

        return 'Reference';
    }
}
