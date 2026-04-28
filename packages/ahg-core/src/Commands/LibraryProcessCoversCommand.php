<?php

/**
 * LibraryProcessCoversCommand — fetch book covers from Open Library by ISBN.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class LibraryProcessCoversCommand extends Command
{
    protected $signature = 'ahg:library-process-covers
        {--limit= : Maximum covers to process}
        {--missing-only : Only process items without covers}';

    protected $description = 'Download book cover images via Open Library / ISBN';

    public function handle(): int
    {
        if (! Schema::hasTable('library_item')) { $this->warn('library_item missing'); return self::SUCCESS; }
        $limit = $this->option('limit') ? max(1, (int) $this->option('limit')) : 200;

        $q = DB::table('library_item')->whereNotNull('isbn');
        if ($this->option('missing-only')) $q->whereNull('cover_url');

        $rows = $q->limit($limit)->get(['id', 'isbn']);
        $this->info("processing {$rows->count()} items");

        $found = 0; $missing = 0;
        foreach ($rows as $r) {
            $isbn = preg_replace('/[^0-9Xx]/', '', (string) $r->isbn);
            if (strlen($isbn) < 10) { $missing++; continue; }
            $url = "https://covers.openlibrary.org/b/isbn/{$isbn}-L.jpg?default=false";
            try {
                $resp = Http::timeout(8)->head($url);
                if ($resp->successful()) {
                    DB::table('library_item')->where('id', $r->id)->update([
                        'cover_url' => $url,
                        'cover_url_original' => "https://covers.openlibrary.org/b/isbn/{$isbn}-L.jpg",
                        'updated_at' => now(),
                    ]);
                    $found++;
                } else {
                    $missing++;
                }
            } catch (\Throwable $e) { $missing++; }
        }
        $this->info("found={$found} missing={$missing}");
        return self::SUCCESS;
    }
}
