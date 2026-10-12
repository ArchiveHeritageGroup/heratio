<?php

/**
 * ahg:research-works-seed - put a researcher's own works (papers, talks,
 * workshops, a thesis) into their research workspace from a manifest file.
 *
 * Built so a demo site that is restored from a database snapshot every night
 * can re-apply the same works after the restore. Every write is keyed, so a
 * second run changes nothing:
 *
 *   researcher     research_researcher by user (found by email); approved
 *   bibliography   research_bibliography by (researcher, name)
 *   entries        research_bibliography_entry by (bibliography, title)
 *   talks          research_lecture by (researcher, title), resources replaced
 *   files          copied to {uploads}/research-works/{folder}/ when missing or
 *                  changed; the database restore leaves files alone
 *
 * The manifest and the files stay outside the repository: unpublished work
 * does not belong in a public repo. A work marked "files": false (or with no
 * files) is listed by title only - use this for anything under anonymous
 * review. See docs/research-works-seed.md for the manifest format.
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

namespace AhgResearch\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedResearcherWorksCommand extends Command
{
    protected $signature = 'ahg:research-works-seed
        {manifest : JSON manifest of the researcher and their works}
        {--dry-run : Report what would change; write nothing}';

    protected $description = 'Add a researcher\'s own works (papers, talks, thesis) to their research workspace from a manifest; safe to re-run';

    private const ENTRY_TYPES = ['article', 'book', 'chapter', 'thesis', 'website', 'other'];

    private const LECTURE_TYPES = ['curriculum', 'talk', 'standalone'];

    private const LECTURE_STATUSES = ['draft', 'scheduled', 'delivered', 'published', 'archived'];

    private bool $dry = false;

    private array $counts = ['entries' => 0, 'talks' => 0, 'files' => 0];

    public function handle(): int
    {
        $path = (string) $this->argument('manifest');
        $m = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (! is_array($m) || empty($m['user_email']) || ! is_array($m['works'] ?? null)) {
            $this->error('Manifest missing or invalid: it needs user_email and works.');

            return self::FAILURE;
        }
        $this->dry = (bool) $this->option('dry-run');
        $baseDir = dirname(realpath($path));

        $user = DB::table('user')->where('email', $m['user_email'])->first();
        if (! $user) {
            $this->error("No user with email {$m['user_email']}.");

            return self::FAILURE;
        }

        return $this->dry ? $this->apply($m, $user, $baseDir) : DB::transaction(fn () => $this->apply($m, $user, $baseDir));
    }

    private function apply(array $m, object $user, string $baseDir): int
    {
        $researcherId = $this->researcher($user, (array) ($m['researcher'] ?? []));
        $bib = (array) ($m['bibliography'] ?? []);
        $bibId = $this->bibliography($researcherId, (string) ($bib['name'] ?? 'Publications and presentations'), $bib['description'] ?? null);

        foreach ($m['works'] as $i => $w) {
            if (empty($w['title'])) {
                $this->warn("Work #{$i} has no title; skipped.");

                continue;
            }
            $files = ($w['files'] ?? []) === false ? [] : $this->files((array) ($w['files'] ?? []), $baseDir, (string) ($w['folder'] ?? Str::slug(Str::limit($w['title'], 60, ''))));
            $this->entry($bibId, $w, $files, $i);
            if (! empty($w['talk'])) {
                $this->talk($researcherId, $w, (array) $w['talk'], $files, $i);
            }
        }

        $this->info(sprintf('%s%d bibliography entries, %d talks, %d files copied.', $this->dry ? '[dry run] ' : '', $this->counts['entries'], $this->counts['talks'], $this->counts['files']));

        return self::SUCCESS;
    }

    private function researcher(object $user, array $r): int
    {
        $row = array_filter([
            'title' => $r['title'] ?? null,
            'first_name' => $r['first_name'] ?? null,
            'last_name' => $r['last_name'] ?? null,
            'affiliation_type' => $r['affiliation_type'] ?? null,
            'institution' => $r['institution'] ?? null,
            'department' => $r['department'] ?? null,
            'position' => $r['position'] ?? null,
            'orcid_id' => $r['orcid_id'] ?? null,
            'research_interests' => $r['research_interests'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
        $existing = DB::table('research_researcher')->where('user_id', $user->id)->first();
        if ($this->dry) {
            $this->line(($existing ? 'Update' : 'Create').' researcher for '.$user->email);

            return (int) ($existing->id ?? 0);
        }
        if ($existing) {
            DB::table('research_researcher')->where('id', $existing->id)
                ->update($row + ['status' => 'approved', 'updated_at' => now()]);

            return (int) $existing->id;
        }

        return (int) DB::table('research_researcher')->insertGetId($row + [
            'user_id' => $user->id,
            'first_name' => $row['first_name'] ?? (string) ($user->username ?? 'Researcher'),
            'last_name' => $row['last_name'] ?? '',
            'email' => $user->email,
            'status' => 'approved',
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function bibliography(int $researcherId, string $name, ?string $description): int
    {
        $id = DB::table('research_bibliography')->where('researcher_id', $researcherId)->where('name', $name)->value('id');
        if ($this->dry) {
            return (int) $id;
        }
        if ($id) {
            DB::table('research_bibliography')->where('id', $id)->update(['description' => $description, 'updated_at' => now()]);

            return (int) $id;
        }

        return (int) DB::table('research_bibliography')->insertGetId([
            'researcher_id' => $researcherId, 'name' => $name, 'description' => $description,
            'citation_style' => 'apa', 'is_public' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Copy each file into the public uploads area.
     *
     * @return array<int, array{label: string, kind: string, url: string}>
     */
    private function files(array $files, string $baseDir, string $folder): array
    {
        // nginx serves /uploads/ from the storage path (root, not alias).
        $root = rtrim((string) config('heratio.storage_path'), '/');
        $out = [];
        foreach ($files as $f) {
            $src = (string) ($f['src'] ?? '');
            $src = str_starts_with($src, '/') ? $src : $baseDir.'/'.$src;
            if (! is_file($src)) {
                $this->warn("File not found, skipped: {$src}");

                continue;
            }
            $name = Str::slug(pathinfo($src, PATHINFO_FILENAME)).'.'.strtolower(pathinfo($src, PATHINFO_EXTENSION));
            $rel = 'uploads/research-works/'.Str::slug($folder).'/'.$name;
            $dest = $root.'/'.$rel;
            if (! is_file($dest) || filesize($dest) !== filesize($src) || hash_file('sha256', $dest) !== hash_file('sha256', $src)) {
                $this->counts['files']++;
                if (! $this->dry) {
                    @mkdir(dirname($dest), 0775, true);
                    if (! copy($src, $dest)) {
                        throw new \RuntimeException("Could not copy {$src} to {$dest}");
                    }
                }
            }
            $out[] = ['label' => (string) ($f['label'] ?? $name), 'kind' => (string) ($f['kind'] ?? 'file'), 'url' => '/'.$rel];
        }

        return $out;
    }

    private function entry(int $bibId, array $w, array $files, int $i): void
    {
        $type = in_array($w['entry_type'] ?? '', self::ENTRY_TYPES, true) ? $w['entry_type'] : 'other';
        $notes = trim(implode("\n", array_filter([
            isset($w['status']) ? 'Status: '.$w['status'] : null,
            $w['notes'] ?? null,
            $files ? 'Files: '.implode('; ', array_map(fn ($f) => $f['label'].' '.$f['url'], $files)) : null,
        ])));
        $row = [
            'entry_type' => $type,
            'title' => Str::limit((string) $w['title'], 500, ''),
            'authors' => $w['authors'] ?? null,
            'date' => $w['date'] ?? null,
            'publisher' => $w['publisher'] ?? null,
            'container_title' => $w['container_title'] ?? null,
            'volume' => $w['volume'] ?? null,
            'issue' => $w['issue'] ?? null,
            'pages' => $w['pages'] ?? null,
            'doi' => $w['doi'] ?? null,
            'url' => $w['url'] ?? ($files[0]['url'] ?? null),
            'notes' => $notes !== '' ? $notes : null,
            'sort_order' => $i,
            'updated_at' => now(),
        ];
        $this->counts['entries']++;
        if ($this->dry) {
            $this->line("Entry: {$row['title']}");

            return;
        }
        DB::table('research_bibliography_entry')->updateOrInsert(
            ['bibliography_id' => $bibId, 'title' => $row['title']],
            $row + ['created_at' => now()]
        );
    }

    private function talk(int $researcherId, array $w, array $t, array $files, int $i): void
    {
        $title = Str::limit((string) ($t['title'] ?? $w['title']), 255, '');
        $slides = collect($files)->firstWhere('kind', 'slides');
        $row = [
            'type' => in_array($t['type'] ?? '', self::LECTURE_TYPES, true) ? $t['type'] : 'talk',
            'subtitle' => Str::limit((string) ($t['subtitle'] ?? $t['event'] ?? ''), 255, '') ?: null,
            'summary' => $t['summary'] ?? ($w['abstract'] ?? null),
            'speaker_name' => $t['speaker_name'] ?? ($w['authors'] ?? null),
            'speaker_affiliation' => $t['speaker_affiliation'] ?? null,
            'scheduled_at' => $t['date'] ?? null,
            'location' => $t['location'] ?? null,
            'duration_minutes' => $t['duration_minutes'] ?? null,
            'slides_url' => $slides['url'] ?? null,
            'status' => in_array($t['status'] ?? '', self::LECTURE_STATUSES, true) ? $t['status'] : 'delivered',
            'sort_order' => $i,
            'updated_at' => now(),
        ];
        $this->counts['talks']++;
        if ($this->dry) {
            $this->line("Talk: {$title}");

            return;
        }
        $id = DB::table('research_lecture')->where('researcher_id', $researcherId)->where('title', $title)->value('id');
        if ($id) {
            DB::table('research_lecture')->where('id', $id)->update($row);
        } else {
            $id = DB::table('research_lecture')->insertGetId($row + ['researcher_id' => $researcherId, 'title' => $title, 'created_at' => now()]);
        }
        DB::table('research_lecture_resource')->where('lecture_id', $id)->delete();
        foreach ($files as $n => $f) {
            DB::table('research_lecture_resource')->insert([
                'lecture_id' => $id, 'label' => Str::limit($f['label'], 255, ''), 'url' => $f['url'],
                'resource_type' => $f['kind'] === 'slides' ? 'slides' : ($f['kind'] === 'paper' ? 'reading' : 'file'),
                'sort_order' => $n, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
