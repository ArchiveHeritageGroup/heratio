<?php

/**
 * LostPlaceDemoCommand - #1323 "Lost Places" POC: build a real pilot dataset
 * from public-domain Wikimedia Commons evidence for a vanished place.
 *
 * `php artisan ahg:lost-place-demo` downloads a curated set of PUBLIC-DOMAIN
 * photographs/plans of the Crystal Palace (Sydenham, destroyed by fire 1936)
 * from Wikimedia Commons, ingests them as digital surrogates on a single
 * "lost place" archival record linked to a place access point, and flags that
 * record `owl:deprecated` (destroyed - deprecate-not-delete, #1321). It then
 * prints the gather/coverage summary so the whole #1323 pipeline is runnable
 * end-to-end on real evidence.
 *
 * Idempotent (skips already-downloaded files / existing rows) and reversible
 * (`--remove`). All image fetches carry a descriptive User-Agent per Wikimedia
 * policy. No AI calls here, so no gateway involvement.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
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

namespace AhgExhibition\Console\Commands;

use AhgExhibition\Services\LostPlaceGatherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LostPlaceDemoCommand extends Command
{
    protected $signature = 'ahg:lost-place-demo
                            {--place=Crystal Palace : place name (access point + record subject)}
                            {--category=Crystal Palace (building) : Wikimedia Commons root category}
                            {--count=12 : max public-domain images to ingest}
                            {--remove : remove the demo dataset (records, relations, files)}';

    protected $description = 'Lost Places POC (#1323): build a real PD evidence pack (Crystal Palace) from Wikimedia Commons.';

    private const PLACE_TAXONOMY_ID = 42;
    private const UA = 'HeratioLostPlacesPOC/1.0 (https://theahg.co.za; johan@theahg.co.za)';
    private const COMMONS = 'https://commons.wikimedia.org/w/api.php';

    public function handle(LostPlaceGatherService $gather): int
    {
        $place = trim((string) $this->option('place'));
        $slug = Str::slug($place);
        $relDir = 'uploads/lost-place-demo/'.$slug.'/';
        $absDir = rtrim((string) config('heratio.storage_path'), '/').'/'.$relDir;

        if ($this->option('remove')) {
            return $this->remove($place, $absDir);
        }

        // 1. Resolve a candidate file list from the category + its subcategories.
        $titles = $this->commonsFiles((string) $this->option('category'));
        if (! $titles) {
            $this->error('No files found in the Commons category (check the name / network).');

            return self::FAILURE;
        }
        $this->line('Commons candidates: '.count($titles));

        // 2. Keep public-domain images, resolve download URLs, download.
        @mkdir($absDir, 0775, true);
        $want = max(1, (int) $this->option('count'));
        $files = [];
        foreach ($titles as $title) {
            if (count($files) >= $want) {
                break;
            }
            $info = $this->imageInfo($title);
            if (! $info || ! str_starts_with((string) $info['mime'], 'image/')) {
                continue;
            }
            if (! $this->isPublicDomain($info['license'])) {
                continue;
            }
            $name = $this->safeName($title, $info['url']);
            $abs = $absDir.$name;
            if (! is_file($abs)) {
                $bytes = $this->download($info['url']);
                if ($bytes === null || strlen($bytes) < 1024) {
                    continue;
                }
                file_put_contents($abs, $bytes);
            }
            $files[] = ['name' => $name, 'mime' => $info['mime'], 'title' => $title, 'license' => $info['license']];
            $this->line('  + '.$name.'  ('.$info['license'].')');
        }
        if (! $files) {
            $this->error('No public-domain images could be downloaded.');

            return self::FAILURE;
        }

        // 3. Catalogue: place term + lost-place record + digital surrogates + deprecation.
        $termId = 0;
        $ioId = 0;
        DB::transaction(function () use ($place, $relDir, $files, &$termId, &$ioId) {
            $termId = $this->ensurePlaceTerm($place);
            $ioId = $this->ensureRecord($place);
            $this->ensureRelation($ioId, $termId);
            foreach ($files as $f) {
                $this->ensureDigitalObject($ioId, $relDir, $f['name'], $f['mime']);
            }
            $this->markDeprecated($ioId);
        });

        $this->info("\nIngested ".count($files)." public-domain image(s) -> record IO {$ioId}, place term {$termId}.");

        // 4. Show the gather/coverage summary.
        $result = $gather->gather($place);
        $cov = $result['coverage'];
        $this->newLine();
        $this->info('Gather summary');
        $this->line("  Records linked .......... {$cov['records_total']}");
        $this->line("  Images / Documents ...... {$cov['image_total']} / {$cov['document_total']}");
        $this->line("  In RiC graph ............ ".($result['in_ric_graph'] ? 'yes' : 'no'));
        $this->line('  Reconstruction level .... '.strtoupper($cov['reconstruction_level']));
        $this->line('  '.$cov['reconstruction_note']);
        $this->newLine();
        $this->line("Next: php artisan ahg:lost-place-gather \"{$place}\" --discover");
        $this->line("      php artisan ahg:lost-place-reconstruct \"{$place}\"   (needs the TripoSR gateway endpoint live)");

        return self::SUCCESS;
    }

    /** Files in a category plus its immediate subcategories (deduped). */
    private function commonsFiles(string $category): array
    {
        $cats = array_merge([$category], $this->subcats($category));
        $titles = [];
        foreach ($cats as $cat) {
            foreach ($this->categoryMembers($cat, 'file') as $t) {
                $titles[$t] = true;
            }
        }

        return array_keys($titles);
    }

    private function subcats(string $category): array
    {
        return array_map(
            static fn ($t) => preg_replace('/^Category:/', '', $t),
            $this->categoryMembers($category, 'subcat')
        );
    }

    private function categoryMembers(string $category, string $type): array
    {
        $resp = $this->commons([
            'action' => 'query', 'format' => 'json', 'list' => 'categorymembers',
            'cmtitle' => 'Category:'.$category, 'cmtype' => $type, 'cmlimit' => '200',
        ]);

        return array_map(
            static fn ($m) => $m['title'],
            $resp['query']['categorymembers'] ?? []
        );
    }

    /** @return array{url:string,mime:string,license:string}|null */
    private function imageInfo(string $fileTitle): ?array
    {
        $resp = $this->commons([
            'action' => 'query', 'format' => 'json', 'titles' => $fileTitle,
            'prop' => 'imageinfo', 'iiprop' => 'url|mime|extmetadata',
        ]);
        foreach (($resp['query']['pages'] ?? []) as $p) {
            $ii = $p['imageinfo'][0] ?? null;
            if (! $ii) {
                continue;
            }

            return [
                'url' => (string) ($ii['url'] ?? ''),
                'mime' => (string) ($ii['mime'] ?? ''),
                'license' => (string) ($ii['extmetadata']['LicenseShortName']['value'] ?? 'unknown'),
            ];
        }

        return null;
    }

    private function isPublicDomain(string $license): bool
    {
        $l = strtolower($license);

        return str_contains($l, 'public domain') || str_contains($l, 'cc0') || $l === 'pd';
    }

    private function commons(array $params): array
    {
        try {
            $r = Http::withHeaders(['User-Agent' => self::UA])->timeout(30)->get(self::COMMONS, $params);

            return $r->successful() ? (array) $r->json() : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function download(string $url): ?string
    {
        try {
            $r = Http::withHeaders(['User-Agent' => self::UA])->timeout(90)->get($url);

            return $r->successful() ? $r->body() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function safeName(string $fileTitle, string $url): string
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION)) ?: 'jpg';
        $base = Str::slug(Str::limit(preg_replace('/^File:/', '', $fileTitle), 70, ''));

        return ($base !== '' ? $base : 'image-'.substr(md5($fileTitle), 0, 8)).'.'.$ext;
    }

    // ---- catalogue writers (idempotent) -----------------------------------

    private function ensurePlaceTerm(string $place): int
    {
        $existing = DB::table('term')
            ->join('term_i18n', function ($j) {
                $j->on('term.id', '=', 'term_i18n.id')->on('term_i18n.culture', '=', 'term.source_culture');
            })
            ->where('term.taxonomy_id', self::PLACE_TAXONOMY_ID)
            ->where('term_i18n.name', $place)
            ->value('term.id');
        if ($existing) {
            return (int) $existing;
        }

        $id = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitTerm', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('term')->insert(['id' => $id, 'taxonomy_id' => self::PLACE_TAXONOMY_ID, 'source_culture' => 'en']);
        DB::table('term_i18n')->insert(['id' => $id, 'culture' => 'en', 'name' => $place]);

        return $id;
    }

    private function ensureRecord(string $place): int
    {
        $title = "The {$place} (demo - Lost Places #1323)";
        $existing = DB::table('information_object as io')
            ->join('information_object_i18n as i', function ($j) {
                $j->on('io.id', '=', 'i.id')->on('i.culture', '=', 'io.source_culture');
            })
            ->where('i.title', $title)->value('io.id');
        if ($existing) {
            return (int) $existing;
        }

        $id = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('information_object')->insert([
            'id' => $id, 'identifier' => 'LOSTPLACE-'.Str::upper(Str::slug($place)), 'source_culture' => 'en',
        ]);
        DB::table('information_object_i18n')->insert([
            'id' => $id, 'culture' => 'en', 'title' => $title,
            'scope_and_content' => "Public-domain photographic and graphic evidence of {$place}, assembled from Wikimedia Commons for the Lost Places reconstruction POC (#1323).",
        ]);

        return $id;
    }

    private function ensureRelation(int $ioId, int $termId): void
    {
        $exists = DB::table('object_term_relation')->where('object_id', $ioId)->where('term_id', $termId)->exists();
        if ($exists) {
            return;
        }
        $rid = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitObjectTermRelation', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('object_term_relation')->insert(['id' => $rid, 'object_id' => $ioId, 'term_id' => $termId]);
    }

    private function ensureDigitalObject(int $ioId, string $relDir, string $name, string $mime): void
    {
        $exists = DB::table('digital_object')->where('object_id', $ioId)->where('name', $name)->exists();
        if ($exists) {
            return;
        }
        $id = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitDigitalObject', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('digital_object')->insert([
            'id' => $id, 'object_id' => $ioId, 'name' => $name, 'path' => $relDir,
            'mime_type' => $mime, 'media_type_id' => null, 'parent_id' => null,
        ]);
    }

    private function markDeprecated(int $ioId): void
    {
        if (! class_exists('AhgRic\\Services\\RicDeprecationService')) {
            return;
        }
        try {
            app('AhgRic\\Services\\RicDeprecationService')->markDeprecated(
                'information_object', $ioId,
                'Subject destroyed (e.g. the Crystal Palace burned down 30 November 1936); record retained - deprecate-not-delete (#1321).'
            );
        } catch (\Throwable $e) {
            // register absent; non-fatal.
        }
    }

    private function remove(string $place, string $absDir): int
    {
        $title = "The {$place} (demo - Lost Places #1323)";
        $ioId = (int) (DB::table('information_object as io')
            ->join('information_object_i18n as i', function ($j) {
                $j->on('io.id', '=', 'i.id')->on('i.culture', '=', 'io.source_culture');
            })->where('i.title', $title)->value('io.id') ?? 0);

        if ($ioId) {
            $doIds = DB::table('digital_object')->where('object_id', $ioId)->pluck('id')->all();
            DB::table('digital_object')->where('object_id', $ioId)->delete();
            DB::table('object')->whereIn('id', $doIds)->delete();
            DB::table('object_term_relation')->where('object_id', $ioId)->delete();
            DB::table('information_object_i18n')->where('id', $ioId)->delete();
            DB::table('information_object')->where('id', $ioId)->delete();
            DB::table('object')->where('id', $ioId)->delete();
            if (class_exists('AhgRic\\Services\\RicDeprecationService')) {
                try { app('AhgRic\\Services\\RicDeprecationService')->reinstate('information_object', $ioId); } catch (\Throwable $e) {}
            }
        }
        if (is_dir($absDir)) {
            array_map('unlink', glob($absDir.'*') ?: []);
            @rmdir($absDir);
        }
        $this->info("Removed demo dataset for \"{$place}\" (record {$ioId}, files in {$absDir}).");

        return self::SUCCESS;
    }
}
