<?php

/**
 * StorytellingService - Heratio ahg-core
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * heratio#1202 - storytelling engine. Turns catalogue records into an engaging public
 * narrative: pick objects on a theme and let the AI gateway write a short, warm "story of the
 * collection" that weaves them together, for a general audience. Distinct from the exhibition
 * designer (#1186, which lays objects out in rooms) - this produces publishable prose.
 */
class StorytellingService
{
    /**
     * @param  array{context?:string, recordIds?:array<int>}  $opts  Extra grounding (#1202):
     *           `context` is curator-supplied background (notes / fetched URL / uploaded doc);
     *           `recordIds` are hand-picked catalogue records to weave in for certain.
     * @return array{ok:bool, theme:string, story:string, objects:array}
     */
    public function generate(string $theme, int $maxObjects = 10, array $opts = []): array
    {
        $theme = trim($theme);
        $context = trim((string) ($opts['context'] ?? ''));
        $recordIds = array_values(array_filter(array_map('intval', (array) ($opts['recordIds'] ?? []))));
        $out = ['ok' => false, 'theme' => $theme, 'story' => '', 'objects' => []];
        if ($theme === '' && $context === '' && ! $recordIds) {
            return $out;
        }

        // Hand-picked records lead (guaranteed inclusion), then theme-matched candidates fill up.
        $objects = $this->mergeObjects($this->pickedObjects($recordIds), $theme !== '' ? $this->candidates($theme, $maxObjects) : [], $maxObjects);
        $out['objects'] = $objects;
        // A story needs SOMETHING to weave - objects, or at least background context.
        if (! $objects && $context === '') {
            return $out;
        }

        $out['story'] = $this->writeStory($theme !== '' ? $theme : 'the material below', $objects, $context);
        $out['ok'] = $out['story'] !== '';

        return $out;
    }

    /**
     * heratio#1202 - "On this day" / "This month in the collection". Finds catalogue records
     * dated to today (exact month + day across any year), and if none, falls back to records
     * dated anywhere in the current month. Returns the same shape as generate() plus a `scope`
     * key (`day` | `month` | `none`) so the caller can label it.
     *
     * @return array{ok:bool, theme:string, scope:string, story:string, objects:array}
     */
    public function onThisDay(int $maxObjects = 10): array
    {
        $today = now();
        $monthName = $today->format('F');
        $dayOrdinal = $today->day.$this->ordinalSuffix($today->day);

        $objects = $this->datedObjects((int) $today->month, (int) $today->day, $maxObjects);
        $scope = 'day';
        if (! $objects) {
            $objects = $this->datedObjects((int) $today->month, null, $maxObjects);   // fall back to the whole month
            $scope = 'month';
        }
        if (! $objects) {
            return ['ok' => false, 'theme' => 'On this day', 'scope' => 'none', 'story' => '', 'objects' => []];
        }

        $theme = $scope === 'day'
            ? "On this day - the {$dayOrdinal} of {$monthName} - in our collection"
            : "This month - {$monthName} - in our collection";
        $hint = $scope === 'day'
            ? "These objects are all connected to today's date ({$dayOrdinal} of {$monthName}) in some year."
            : "These objects are all connected to {$monthName} in some year.";

        return [
            'ok' => $this->writeStory($theme, $objects, $hint) !== '' ? true : false,
            'theme' => $theme, 'scope' => $scope,
            'story' => $this->lastStory, 'objects' => $objects,
        ];
    }

    /** Last story produced by writeStory() - lets onThisDay() avoid a double LLM call. */
    private string $lastStory = '';

    /** Build the prompt, call the AI gateway, return the story prose ('' on failure). */
    private function writeStory(string $themeLabel, array $objects, string $context = ''): string
    {
        $list = $objects
            ? implode("\n", array_map(fn ($o) => '- '.$o['title'].(($o['scope'] ?? '') !== '' ? ': '.$o['scope'] : ''), $objects))
            : '(none selected - ground the story in the background material below)';
        $prompt = "Write an engaging, warm public 'story of the collection' for a general audience, about 180 to 220 words, on the theme: \"{$themeLabel}\". "
            ."Weave in these real objects from the collection, referring to them naturally by name. Be vivid but factual - do NOT invent specific dates, people or events that are not implied by the material. "
            ."No headings, no markdown, no preamble - just the story prose.\n\nOBJECTS:\n".$list;
        if ($context !== '') {
            $prompt .= "\n\nADDITIONAL BACKGROUND (use it to inform the story, but do not contradict the objects, and do not copy it verbatim):\n".$context;
        }

        try {
            $story = trim((string) app(\AhgAiServices\Services\LlmService::class)->complete($prompt, ['max_tokens' => 520, 'temperature' => 0.7]));
        } catch (\Throwable $e) {
            Log::warning('[ahg-core] storytelling LLM failed: '.$e->getMessage());
            $story = '';
        }

        return $this->lastStory = $story;
    }

    /**
     * Catalogue records with an event date in a given month (and optionally an exact day),
     * across any year. Year-only AtoM dates store the day/month as 00 and so never match a real
     * 1-12 / 1-31 value - they are naturally excluded.
     */
    private function datedObjects(int $month, ?int $day, int $limit): array
    {
        $q = DB::table('event as e')
            ->join('information_object as io', 'io.id', '=', 'e.object_id')
            ->join('information_object_i18n as i', function ($j) { $j->on('i.id', '=', 'e.object_id')->where('i.culture', '=', 'en'); })
            ->whereNotNull('e.start_date')
            ->where('io.parent_id', '!=', 1)
            ->where('i.title', '!=', '')
            ->whereRaw('MONTH(e.start_date) = ?', [$month]);
        if ($day !== null) {
            $q->whereRaw('DAY(e.start_date) = ?', [$day]);
        } else {
            $q->whereRaw('DAY(e.start_date) BETWEEN 1 AND 31');
        }

        $rows = $q->orderByRaw('YEAR(e.start_date)')
            ->select('e.object_id as id', 'i.title', 'i.scope_and_content', 'e.start_date')
            ->distinct()->limit($limit)->get();

        $out = [];
        $seen = [];
        foreach ($rows as $r) {
            if (isset($seen[$r->id])) {
                continue;
            }
            $seen[$r->id] = true;
            $year = (int) substr((string) $r->start_date, 0, 4);
            $out[] = ['id' => (int) $r->id, 'title' => (string) $r->title,
                'scope' => trim(($year > 0 ? '['.$year.'] ' : '').mb_substr(strip_tags((string) ($r->scope_and_content ?? '')), 0, 150))];
        }

        return $out;
    }

    /** 1 -> st, 2 -> nd, 3 -> rd, else th (handles the 11-13 exception). */
    private function ordinalSuffix(int $n): string
    {
        if ($n % 100 >= 11 && $n % 100 <= 13) {
            return 'th';
        }

        return match ($n % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }

    /**
     * heratio#1202 - persist a (reviewed/edited) story so it has a permanent, shareable page.
     * Creates a new row or updates an existing one by id. A unique slug is derived from the
     * title on first save and never changes afterwards (stable public URL).
     *
     * @param  array{id?:int, title?:string, theme?:string, body?:string, status?:string, objects?:array}  $data
     * @return array{ok:bool, id:int, slug:string, status:string, error?:string}
     */
    public function save(array $data): array
    {
        $title = trim((string) ($data['title'] ?? '')) ?: trim((string) ($data['theme'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));
        if ($title === '' || $body === '') {
            return ['ok' => false, 'id' => 0, 'slug' => '', 'status' => 'draft', 'error' => 'title and body are required'];
        }
        $status = in_array(($data['status'] ?? 'draft'), ['draft', 'published'], true) ? $data['status'] : 'draft';

        // Normalise the featured-object list to a flat array of integer IO ids.
        $ids = [];
        foreach ((array) ($data['objects'] ?? []) as $o) {
            $id = (int) (is_array($o) ? ($o['id'] ?? 0) : $o);
            if ($id > 0 && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        // Normalise the external-source attribution list (#1202): [{type,label,url?}].
        $sources = [];
        foreach ((array) ($data['sources'] ?? []) as $s) {
            if (! is_array($s)) {
                continue;
            }
            $type = in_array(($s['type'] ?? ''), ['note', 'url', 'upload', 'record'], true) ? $s['type'] : 'note';
            $label = trim((string) ($s['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $entry = ['type' => $type, 'label' => mb_substr($label, 0, 200)];
            if ($type === 'url' && ! empty($s['url'])) {
                $entry['url'] = mb_substr((string) $s['url'], 0, 500);
            }
            $sources[] = $entry;
        }
        $sourcesJson = json_encode($sources);

        $now = now();
        $existingId = (int) ($data['id'] ?? 0);
        if ($existingId > 0 && DB::table('ahg_story')->where('id', $existingId)->exists()) {
            DB::table('ahg_story')->where('id', $existingId)->update([
                'title' => $title, 'theme' => trim((string) ($data['theme'] ?? '')),
                'body' => $body, 'object_ids' => json_encode($ids), 'sources_json' => $sourcesJson,
                'status' => $status, 'updated_at' => $now,
            ]);
            $slug = (string) DB::table('ahg_story')->where('id', $existingId)->value('slug');

            return ['ok' => true, 'id' => $existingId, 'slug' => $slug, 'status' => $status];
        }

        $slug = $this->uniqueStorySlug($title);
        $id = (int) DB::table('ahg_story')->insertGetId([
            'slug' => $slug, 'title' => $title, 'theme' => trim((string) ($data['theme'] ?? '')),
            'body' => $body, 'object_ids' => json_encode($ids), 'sources_json' => $sourcesJson, 'status' => $status,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return ['ok' => true, 'id' => $id, 'slug' => $slug, 'status' => $status];
    }

    /** A saved story by its public slug (any status - the caller decides visibility). */
    public function getBySlug(string $slug): ?object
    {
        return DB::table('ahg_story')->where('slug', $slug)->first();
    }

    /** Resolve the featured objects of a saved story to title + slug for linking. */
    public function storyObjects(object $story): array
    {
        $ids = json_decode((string) ($story->object_ids ?? '[]'), true);
        if (! is_array($ids) || ! $ids) {
            return [];
        }
        $rows = DB::table('information_object_i18n as i')
            ->leftJoin('slug as sl', 'sl.object_id', '=', 'i.id')
            ->where('i.culture', 'en')->whereIn('i.id', $ids)
            ->select('i.id', 'i.title', 'sl.slug')->get()->keyBy('id');
        $out = [];
        foreach ($ids as $id) {   // preserve the authored order
            if (isset($rows[$id])) {
                $out[] = ['id' => (int) $id, 'title' => (string) $rows[$id]->title, 'slug' => $rows[$id]->slug];
            }
        }

        return $out;
    }

    /** Saved stories for the admin list (newest first). */
    public function listSaved(int $limit = 50): array
    {
        return DB::table('ahg_story')->orderByDesc('updated_at')->limit($limit)
            ->get(['id', 'slug', 'title', 'theme', 'status', 'updated_at'])->all();
    }

    /** Slug from a title, de-duplicated against existing stories. */
    private function uniqueStorySlug(string $title): string
    {
        $base = trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($title))), '-');
        $base = $base !== '' ? mb_substr($base, 0, 80) : 'story';
        $slug = $base;
        $n = 1;
        while (DB::table('ahg_story')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }

    /** Hand-picked catalogue records (guaranteed inclusion), in the order chosen. */
    private function pickedObjects(array $recordIds): array
    {
        if (! $recordIds) {
            return [];
        }
        $rows = DB::table('information_object_i18n')
            ->where('culture', 'en')->whereIn('id', $recordIds)
            ->get(['id', 'title', 'scope_and_content'])->keyBy('id');
        $out = [];
        foreach ($recordIds as $id) {
            if (isset($rows[$id]) && trim((string) $rows[$id]->title) !== '') {
                $out[] = ['id' => (int) $id, 'title' => (string) $rows[$id]->title, 'score' => 999,
                    'scope' => trim(mb_substr(strip_tags((string) ($rows[$id]->scope_and_content ?? '')), 0, 160))];
            }
        }

        return $out;
    }

    /** Merge picked + matched objects, picked first, de-duplicated by id, capped at $limit. */
    private function mergeObjects(array $picked, array $matched, int $limit): array
    {
        $seen = [];
        $out = [];
        foreach (array_merge($picked, $matched) as $o) {
            if (! isset($seen[$o['id']])) {
                $seen[$o['id']] = true;
                $out[] = $o;
            }
        }

        return array_slice($out, 0, max($limit, count($picked)));
    }

    /** Typeahead for hand-picking records: title LIKE, returns id + title. */
    public function searchRecords(string $q, int $limit = 12): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        return DB::table('information_object_i18n as i')
            ->join('information_object as io', 'io.id', '=', 'i.id')
            ->where('i.culture', 'en')->where('io.parent_id', '!=', 1)
            ->where('i.title', 'like', '%'.$q.'%')->where('i.title', '!=', '')
            ->orderBy('i.title')->limit($limit)
            ->get(['i.id', 'i.title'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'title' => (string) $r->title])->all();
    }

    /** Decode a saved story's external-source attribution list for display. */
    public function storySources(object $story): array
    {
        $s = json_decode((string) ($story->sources_json ?? '[]'), true);

        return is_array($s) ? $s : [];
    }

    /** On-theme objects (prefer exhibition room objects for context; else the catalogue). */
    private function candidates(string $theme, int $limit): array
    {
        $tokens = array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($theme)), fn ($t) => mb_strlen($t) >= 3));
        if (! $tokens) {
            return [];
        }

        $rows = DB::table('ahg_exhibition_placement as ep')
            ->join('information_object_i18n as i', function ($j) { $j->on('i.id', '=', 'ep.information_object_id')->where('i.culture', '=', 'en'); })
            ->whereNotNull('ep.information_object_id')->where('i.title', '!=', '')
            ->select('ep.information_object_id as id', 'i.title', 'i.scope_and_content')->distinct()->get();

        if ($rows->isEmpty()) {
            $rows = DB::table('information_object_i18n as i')
                ->join('information_object as io', 'io.id', '=', 'i.id')
                ->where('i.culture', 'en')->where('io.parent_id', '!=', 1)
                ->where(function ($q) use ($tokens) {
                    foreach ($tokens as $t) { $q->orWhere('i.title', 'like', '%'.$t.'%')->orWhere('i.scope_and_content', 'like', '%'.$t.'%'); }
                })
                ->where('i.title', '!=', '')->select('io.id', 'i.title', 'i.scope_and_content')->limit(80)->get();
        }

        $scored = [];
        foreach ($rows as $r) {
            $title = mb_strtolower((string) $r->title);
            $scope = mb_strtolower(strip_tags((string) ($r->scope_and_content ?? '')));
            $s = 0;
            foreach ($tokens as $t) {
                if (mb_strpos($title, $t) !== false) { $s += 2; }
                if (mb_strpos($scope, $t) !== false) { $s += 1; }
            }
            if ($s > 0) {   // story stays on-theme: only objects that actually match
                $scored[] = ['id' => (int) $r->id, 'title' => (string) $r->title, 'score' => $s,
                    'scope' => trim(mb_substr(strip_tags((string) ($r->scope_and_content ?? '')), 0, 160))];
            }
        }
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }
}
