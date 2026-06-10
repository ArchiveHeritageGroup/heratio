<?php

/**
 * ThemeExhibitionService - Heratio ahg-exhibition
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgExhibition\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * heratio#1186 - generative exhibitions (single-shot variant).
 *
 * Where {@see GenerativeExhibitionService} runs a two-step "draft -> review -> build" flow,
 * this service does the whole thing in one call: a curator enters a theme, the catalogue is
 * searched for on-theme objects, the AI gateway picks and orders the best of them into a
 * narrative, and a single real Exhibition Space (a gallery room) is created with each chosen
 * object laid out on the floor - ready for the curator to walk through or fine-tune in the
 * builder.
 *
 * It is built entirely on the existing public API of {@see ExhibitionSpaceService}
 * (create / createPlacementAt / getById) and the AI gateway via the LlmService abstraction -
 * it never touches a GPU node directly and never mutates an existing space.
 */
class ThemeExhibitionService
{
    public function __construct(private ExhibitionSpaceService $spaces) {}

    /**
     * Curate a themed exhibition end-to-end and return the created space.
     *
     * @param  string       $theme       Free-text theme / prompt (e.g. "maps and exploration").
     * @param  int          $maxObjects  Hard cap on objects to place (1-48).
     * @param  string|null  $building    Optional building name for the new gallery space.
     * @return array{space_id:int, slug:string, placed:int}
     *
     * @throws \InvalidArgumentException when the theme is empty.
     * @throws \RuntimeException when no on-theme catalogue objects can be found.
     */
    public function curate(string $theme, int $maxObjects = 12, ?string $building = null): array
    {
        $theme = trim($theme);
        if ($theme === '') {
            throw new \InvalidArgumentException('A theme is required to generate an exhibition.');
        }
        $maxObjects = max(1, min(48, $maxObjects));

        // 1) Candidate pool from the real catalogue (capped ~60).
        $candidates = $this->candidateObjects($theme, 60);
        if (! $candidates) {
            throw new \RuntimeException('No catalogue objects matched that theme. Try broader or different words.');
        }

        // 2) Let the AI pick + order the best fit; fall back to top keyword matches if unavailable.
        $chosenIds = $this->selectWithAi($theme, $candidates, $maxObjects);
        if (! $chosenIds) {
            $chosenIds = array_slice(array_keys($candidates), 0, $maxObjects);
        }
        $chosenIds = array_values(array_slice($chosenIds, 0, $maxObjects));
        if (! $chosenIds) {
            throw new \RuntimeException('Could not assemble an exhibition for that theme.');
        }

        // 3) Build a real Exhibition Space and place every chosen object across the floor.
        return DB::transaction(function () use ($theme, $building, $chosenIds) {
            $name = $this->spaceName($theme);
            $spaceId = $this->spaces->create([
                'name' => $name,
                'space_type' => 'gallery',
                'building' => $building ?: null,
                'capacity_unit' => 'linear_wall_meters',
                'room_w' => 12,
                'room_d' => 10,
                'room_h' => 4,
                'notes' => 'Auto-generated from the theme: '.$theme,
            ]);

            $placed = $this->placeAcrossFloor($spaceId, $chosenIds);

            $space = $this->spaces->getById($spaceId);
            $slug = $space ? (string) $space->slug : '';

            return ['space_id' => $spaceId, 'slug' => $slug, 'placed' => $placed];
        });
    }

    /**
     * Spread the chosen objects evenly across the gallery floor in a grid so the walkthrough
     * has a coherent layout straight away. pos_x / pos_y are normalised 0..1 (the builder's
     * convention). A real placement row is created per object via the existing service method.
     */
    private function placeAcrossFloor(int $spaceId, array $ioIds): int
    {
        $ioIds = array_values(array_unique(array_map('intval', $ioIds)));
        $count = count($ioIds);
        if ($count === 0) {
            return 0;
        }

        $cols = (int) max(1, ceil(sqrt($count)));
        $rows = (int) max(1, ceil($count / $cols));

        $placed = 0;
        foreach ($ioIds as $i => $ioId) {
            if ($ioId <= 0) {
                continue;
            }
            $col = $i % $cols;
            $row = intdiv($i, $cols);
            $posX = ($col + 1) / ($cols + 1);
            $posY = ($row + 1) / ($rows + 1);
            try {
                $this->spaces->createPlacementAt($spaceId, $ioId, $posX, $posY);
                $placed++;
            } catch (\Throwable $e) {
                Log::info('[ahg-exhibition] theme curate: skipped object '.$ioId.' - '.$e->getMessage());
            }
        }

        return $placed;
    }

    /**
     * On-theme candidate pool drawn from the real catalogue. Matches the theme tokens against
     * title + scope_and_content, and prefers objects that actually have something to display
     * (a digital_object or a 3D model) so the generated show is not empty boxes. Ranked by
     * keyword overlap (title weighted, +1 for having media). Returns id => [id,title,scope].
     *
     * @return array<int,array{id:int,title:string,scope:string}>
     */
    private function candidateObjects(string $theme, int $limit): array
    {
        $tokens = array_values(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($theme)) ?: [],
            fn ($t) => mb_strlen($t) >= 3
        ));

        $q = DB::table('information_object_i18n as i')
            ->join('information_object as io', 'io.id', '=', 'i.id')
            ->where('i.culture', 'en')
            ->where('io.parent_id', '!=', 1)
            ->whereNotNull('i.title')
            ->where('i.title', '!=', '');

        if ($tokens) {
            $q->where(function ($w) use ($tokens) {
                foreach ($tokens as $t) {
                    $w->orWhere('i.title', 'like', '%'.$t.'%')
                        ->orWhere('i.scope_and_content', 'like', '%'.$t.'%');
                }
            });
        }

        $rows = $q->select('io.id', 'i.title', 'i.scope_and_content')->limit(120)->get();

        // Flag objects that have something to show (a digital object or a 3D model) via batched
        // whereIn probes, merged in PHP - simpler and more portable than a correlated subquery.
        $ids = $rows->pluck('id')->map(fn ($v) => (int) $v)->all();
        $mediaIds = [];
        if ($ids) {
            $mediaIds = array_flip(DB::table('digital_object')
                ->whereIn('object_id', $ids)->distinct()->pluck('object_id')->all());
            if ($this->tableExists('object_3d_model')) {
                foreach (DB::table('object_3d_model')->whereIn('object_id', $ids)->distinct()->pluck('object_id') as $mid) {
                    $mediaIds[(int) $mid] = true;
                }
            }
        }

        $scored = [];
        foreach ($rows as $r) {
            $title = mb_strtolower((string) $r->title);
            $scope = mb_strtolower(strip_tags((string) ($r->scope_and_content ?? '')));
            $score = 0;
            foreach ($tokens as $t) {
                if (mb_strpos($title, $t) !== false) {
                    $score += 2;
                }
                if (mb_strpos($scope, $t) !== false) {
                    $score += 1;
                }
            }
            if (isset($mediaIds[(int) $r->id])) {
                $score += 1;   // prefer objects that have something to display
            }
            $scored[] = [
                'id' => (int) $r->id,
                'title' => (string) $r->title,
                'scope' => trim(mb_substr(strip_tags((string) ($r->scope_and_content ?? '')), 0, 240)),
                'score' => $score,
            ];
        }
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        $out = [];
        foreach (array_slice($scored, 0, $limit) as $c) {
            $out[$c['id']] = ['id' => $c['id'], 'title' => $c['title'], 'scope' => $c['scope']];
        }

        return $out;
    }

    /**
     * Ask the AI gateway to pick up to $maxObjects candidates that best fit the theme and order
     * them into a narrative. Candidates are shown with short 1-based NUMBERS (the model copies
     * small numbers reliably; it tends to half-invent long ids). The returned numbers are mapped
     * back to information_object ids and validated against the candidate set. Returns [] when the
     * LLM is unavailable or returns nothing usable, so the caller can fall back to keyword order.
     *
     * @param  array<int,array{id:int,title:string,scope:string}>  $candidates
     * @return array<int,int>  ordered information_object ids
     */
    private function selectWithAi(string $theme, array $candidates, int $maxObjects): array
    {
        $ordered = array_values(array_slice($candidates, 0, 50, true));   // 0-based position -> candidate
        $lines = [];
        foreach ($ordered as $i => $c) {
            $scope = $c['scope'] !== '' ? ' - '.mb_substr($c['scope'], 0, 120) : '';
            $lines[] = ($i + 1).'. '.$c['title'].$scope;
        }
        $list = implode("\n", $lines);

        $prompt = "You are a museum curator assembling an exhibition on the theme: \"{$theme}\".\n"
            ."From the numbered candidate objects below, choose up to {$maxObjects} that best fit the "
            ."theme and put them in the order a visitor should encounter them to tell a coherent story.\n"
            ."Refer to each object ONLY by its number from the list - never invent objects.\n"
            ."Return ONLY a JSON array of the chosen numbers, in narrative order, e.g. [3,1,8,5]. "
            ."No prose, no keys, just the array.\n\n"
            ."CANDIDATES:\n".$list;

        try {
            $resp = (string) app(\AhgAiServices\Services\LlmService::class)
                ->complete($prompt, ['max_tokens' => 300, 'temperature' => 0.4]);
        } catch (\Throwable $e) {
            Log::warning('[ahg-exhibition] theme curate LLM unavailable: '.$e->getMessage());

            return [];
        }

        return $this->parseChosen($resp, $ordered, $maxObjects);
    }

    /**
     * Parse the model's chosen-number array back into validated information_object ids.
     * Accepts a bare JSON array of numbers, or any stray integers in the response as a
     * last resort. Only numbers that index a real candidate are kept, de-duplicated, in order.
     *
     * @param  array<int,array{id:int,title:string,scope:string}>  $ordered
     * @return array<int,int>
     */
    private function parseChosen(string $resp, array $ordered, int $maxObjects): array
    {
        $resp = trim($resp);
        $nums = [];

        $start = strpos($resp, '[');
        $end = strrpos($resp, ']');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($resp, $start, $end - $start + 1), true);
            if (is_array($decoded)) {
                foreach ($decoded as $v) {
                    if (is_int($v) || (is_string($v) && ctype_digit(trim($v)))) {
                        $nums[] = (int) $v;
                    } elseif (is_array($v) && isset($v['n'])) {
                        $nums[] = (int) $v['n'];
                    }
                }
            }
        }
        if (! $nums && preg_match_all('/\d+/', $resp, $m)) {
            $nums = array_map('intval', $m[0]);
        }

        $ids = [];
        foreach ($nums as $n) {
            if ($n >= 1 && isset($ordered[$n - 1])) {
                $id = (int) $ordered[$n - 1]['id'];
                if ($id > 0 && ! in_array($id, $ids, true)) {
                    $ids[] = $id;
                    if (count($ids) >= $maxObjects) {
                        break;
                    }
                }
            }
        }

        return $ids;
    }

    /** "Theme - generated" space name, trimmed to the column width. */
    private function spaceName(string $theme): string
    {
        $base = mb_substr($theme, 0, 80);

        return mb_substr($base.' - generated', 0, 120);
    }

    private function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
