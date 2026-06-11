<?php

/**
 * ResearchLeadService - the public "Research Leads" feed (north-star
 * heratio#1210: generative scholarship - AI finds connections no human spotted).
 *
 * Research Leads PROMOTE the most compelling AI-found cross-collection
 * connections - the ones already surfaced and persisted by the Discoveries
 * feature - into browsable scholarly leads. Each lead pairs the connection with
 * a plain-language "why this might matter" prompt that turns a graph link into a
 * question a researcher could actually pursue.
 *
 * It builds STRICTLY ON the existing Discoveries layer, read-only:
 *   - ahg_scholarship_discovery - the persisted, evidence-grounded discovery set
 *     written by `php artisan ahg:generate-discoveries` (this service NEVER
 *     writes that table). A lead is seeded from a discovery's snapshotted
 *     evidence; the verified links, connection count and confidence come
 *     straight from the discovery, so a lead can never claim more than the
 *     discovery it rests on.
 *
 * The ONLY table this service writes to is research_lead. It curates leads into
 * three states (pending / published / dismissed - VARCHAR, never an ENUM) and
 * exposes:
 *   - generate()       - promote top discoveries into pending leads (idempotent
 *                        per record); optionally enrich the "why it matters"
 *                        prompt via the AHG gateway (LlmService), labelled.
 *   - publicFeed()     - PUBLISHED leads whose record is published, newest first
 *   - publicLead()     - one PUBLISHED lead (published record only) or null
 *   - adminList()      - every lead, for the curation screen
 *   - publish()/dismiss()/repend() - curation transitions
 *
 * All AI use routes through the AHG gateway via AhgAiServices\Services\LlmService
 * - never a direct inference node. AI is OPTIONAL and NEVER runs on the public
 * page load: generation is an explicit admin/CLI action. If the gateway is down
 * the lead still stands on a factual, graph-grounded "why it matters" prompt.
 *
 * The published-records gate mirrors the rest of Heratio: an item is "published"
 * when its row in the status table (type_id = 158) carries status_id = 160; the
 * catalogue root (id = 1) is never surfaced. Every read/write path is
 * Schema::hasTable-guarded so a missing table degrades to an empty result rather
 * than a 500. International, jurisdiction-neutral.
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
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

namespace AhgSemanticSearch\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ResearchLeadService
{
    /** The single table this service writes to. */
    public const TABLE = 'research_lead';

    /** The Discoveries persistence table - READ-ONLY source for leads. */
    public const DISCOVERY_TABLE = 'ahg_scholarship_discovery';

    /** Catalogue root id, never surfaced as a real record. */
    protected const ROOT_ID = 1;

    /** Publication-status type_id and the "published" status_id in the status table. */
    protected const PUBLICATION_TYPE_ID = 158;

    protected const PUBLISHED_STATUS_ID = 160;

    /**
     * Canonical curation states, in workflow order. VARCHAR-backed (Dropdown
     * Manager idiom), never a MySQL ENUM. Only "published" is shown publicly.
     *
     * @var array<string,array{label:string,level:string,help:string}>
     */
    public const STATUSES = [
        'pending' => [
            'label' => 'Pending review',
            'level' => 'secondary',
            'help' => 'Promoted from a discovery and awaiting a curator decision. Not shown publicly.',
        ],
        'published' => [
            'label' => 'Published',
            'level' => 'success',
            'help' => 'Approved by a curator and visible on the public Research Leads feed.',
        ],
        'dismissed' => [
            'label' => 'Dismissed',
            'level' => 'light',
            'help' => 'Set aside by a curator. Kept for the record but not shown publicly.',
        ],
    ];

    /**
     * Standing framing surfaced wherever a lead is shown. A research lead is a
     * starting point for enquiry, grounded in real catalogue links, not a finding
     * or a claim of fact.
     */
    public const DISCLAIMER = 'A research lead is a starting point for enquiry, not a finding. Each lead is '
        .'drawn by an AI from the catalogue own verified links and is offered as a hypothesis to test against the '
        .'underlying records. The AI is instructed to use only the supplied catalogue connections and never to '
        .'invent people, places, dates or records, but it can still misread or overstate a link. Verify every lead '
        .'against the records it cites before relying on it.';

    /**
     * Is the leads table present? Every read/write path gates on this so a fresh
     * (un-booted) install never fatals.
     */
    public function available(): bool
    {
        try {
            return Schema::hasTable(self::TABLE);
        } catch (\Throwable $e) {
            Log::info('[research-leads] table probe failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Whether the read-only Discoveries source table exists and has rows. Without
     * it there is nothing to promote into leads.
     */
    public function discoveriesAvailable(): bool
    {
        try {
            return Schema::hasTable(self::DISCOVERY_TABLE)
                && DB::table(self::DISCOVERY_TABLE)->exists();
        } catch (\Throwable $e) {
            Log::info('[research-leads] discovery probe failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Normalise an incoming status to a known canonical value, defaulting to
     * "pending" when blank/unknown.
     */
    public function normaliseStatus(?string $status): string
    {
        $status = strtolower(trim((string) $status));

        return array_key_exists($status, self::STATUSES) ? $status : 'pending';
    }

    /**
     * Human metadata for a status value, with a safe fallback.
     *
     * @return array{key:string,label:string,level:string,help:string}
     */
    public function statusMeta(?string $status): array
    {
        $key = strtolower(trim((string) $status));
        if (isset(self::STATUSES[$key])) {
            return ['key' => $key] + self::STATUSES[$key];
        }

        $label = $key === '' ? 'Pending review' : ucwords(str_replace('_', ' ', $key));

        return ['key' => $key === '' ? 'pending' : $key, 'label' => $label, 'level' => 'secondary', 'help' => ''];
    }

    /**
     * Map a stored 0-100 confidence score to the label/level/score band the views
     * render (same thresholds as the Discoveries surface).
     *
     * @return array{label:string,level:string,score:int}
     */
    public function confidenceBand(int $score): array
    {
        $score = (int) max(0, min(100, $score));
        if ($score >= 70) {
            return ['label' => 'High confidence', 'level' => 'success', 'score' => $score];
        }
        if ($score >= 40) {
            return ['label' => 'Moderate confidence', 'level' => 'warning', 'score' => $score];
        }

        return ['label' => 'Tentative', 'level' => 'secondary', 'score' => $score];
    }

    /**
     * Promote the most compelling persisted discoveries into pending research
     * leads. Idempotent per record (unique key on information_object_id): a
     * re-run refreshes the lead's evidence/headline in place rather than
     * duplicating, and PRESERVES the curation status of any lead a curator has
     * already acted on (published / dismissed are left untouched; only the
     * grounding content is refreshed).
     *
     * Read-only over ahg_scholarship_discovery; the ONLY table written is
     * research_lead. AI enrichment of the "why it matters" prompt is OPTIONAL and
     * routed through the AHG gateway (LlmService) - never on a public page load,
     * never a direct node. With $enrich = false (or the gateway down) the lead
     * still carries a factual, graph-grounded prompt.
     *
     * @param  int   $limit   max discoveries to promote (highest confidence first)
     * @param  bool  $enrich  attempt AI enrichment of the "why it matters" prompt
     * @param  bool  $dryRun  compute without writing
     * @param  int|null  $userId  the curator triggering generation, when known
     * @return array{promoted:int,refreshed:int,skipped:int,enriched:int,ai_reached:bool}
     */
    public function generate(int $limit = 25, bool $enrich = false, bool $dryRun = false, ?int $userId = null): array
    {
        $result = ['promoted' => 0, 'refreshed' => 0, 'skipped' => 0, 'enriched' => 0, 'ai_reached' => false];

        if (! $this->available() || ! $this->discoveriesAvailable()) {
            return $result;
        }

        $limit = max(1, min($limit, 200));

        try {
            $discoveries = DB::table(self::DISCOVERY_TABLE)
                ->orderByDesc('confidence')
                ->orderByDesc('generated_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            Log::info('[research-leads] discovery read failed: '.$e->getMessage());

            return $result;
        }

        $now = now();

        foreach ($discoveries as $disc) {
            $objectId = (int) ($disc->information_object_id ?? 0);
            if ($objectId <= 0) {
                $result['skipped']++;

                continue;
            }

            $evidence = $this->decodeEvidence($disc->evidence ?? null);
            $confidence = (int) ($disc->confidence ?? 0);
            $total = (int) ($disc->connection_count ?? 0);
            $leadText = trim((string) ($disc->summary ?? ''));
            $headline = $this->buildHeadline($disc->title ?? null, $evidence, $total);

            // A factual, graph-grounded "why it matters" prompt is always
            // available, with no AI involvement.
            $why = $this->factualWhyItMatters($total, $evidence);

            // Optional AI enrichment, strictly via the gateway, NEVER on a page
            // load. The factual prompt is the floor; AI only ever replaces it
            // with a richer, still-grounded version.
            if ($enrich) {
                $aiWhy = $this->aiWhyItMatters($disc->title ?? null, $leadText, $evidence);
                if ($aiWhy !== null) {
                    $result['ai_reached'] = true;
                    if (trim($aiWhy) !== '') {
                        $why = $aiWhy;
                        $result['enriched']++;
                    }
                }
            }

            $existing = $this->findByObject($objectId);

            if ($dryRun) {
                $existing ? $result['refreshed']++ : $result['promoted']++;

                continue;
            }

            $payload = [
                'information_object_id' => $objectId,
                'source_discovery_id' => (int) ($disc->id ?? 0) ?: null,
                'headline' => $headline,
                'lead_text' => $leadText !== '' ? $leadText : null,
                'why_it_matters' => $why,
                'connection_count' => $total,
                'confidence' => $confidence,
                'evidence' => json_encode($evidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ai_labelled' => 1,
                'generated_at' => $now,
                'updated_at' => $now,
            ];

            try {
                if ($existing !== null) {
                    // Refresh grounding only - DO NOT clobber a curator's decision.
                    DB::table(self::TABLE)->where('id', (int) $existing['id'])->update($payload);
                    $result['refreshed']++;
                } else {
                    $payload['status'] = 'pending';
                    $payload['curated_by'] = $userId;
                    $payload['created_at'] = $now;
                    DB::table(self::TABLE)->insert($payload);
                    $result['promoted']++;
                }
            } catch (\Throwable $e) {
                Log::warning('[research-leads] generate write failed for io '.$objectId.': '.$e->getMessage());
                $result['skipped']++;
            }
        }

        return $result;
    }

    /**
     * The PUBLIC Research Leads feed: PUBLISHED leads whose underlying record is
     * itself published, newest-published first. Read-only; never throws -
     * degrades to an empty list.
     *
     * @return array<int,array<string,mixed>>
     */
    public function publicFeed(int $limit = 60): array
    {
        if (! $this->available()) {
            return [];
        }

        try {
            $rows = DB::table(self::TABLE)
                ->where('status', 'published')
                ->orderByDesc('published_at')
                ->orderByDesc('confidence')
                ->orderByDesc('id')
                ->limit(max(1, min($limit, 200)))
                ->get();
        } catch (\Throwable $e) {
            Log::info('[research-leads] public feed failed: '.$e->getMessage());

            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $objectId = (int) ($row->information_object_id ?? 0);
            // Publication gate: the record itself must be published, too.
            if (! $this->isPublished($objectId)) {
                continue;
            }
            $out[] = $this->decorate($row);
        }

        return $out;
    }

    /**
     * One PUBLISHED lead for the public detail page, only when its underlying
     * record is also published. Returns null otherwise (the controller then 404s
     * / redirects). Never throws.
     *
     * @return array<string,mixed>|null
     */
    public function publicLead(int $id): ?array
    {
        if (! $this->available() || $id <= 0) {
            return null;
        }

        try {
            $row = DB::table(self::TABLE)
                ->where('id', $id)
                ->where('status', 'published')
                ->first();
        } catch (\Throwable $e) {
            Log::info('[research-leads] public lead failed for '.$id.': '.$e->getMessage());

            return null;
        }

        if ($row === null) {
            return null;
        }
        if (! $this->isPublished((int) ($row->information_object_id ?? 0))) {
            return null;
        }

        return $this->decorate($row);
    }

    /**
     * Every lead, for the admin curation screen, optionally filtered to one
     * status, newest first. Never throws.
     *
     * @return array<int,array<string,mixed>>
     */
    public function adminList(?string $statusFilter = null, int $limit = 500): array
    {
        if (! $this->available()) {
            return [];
        }

        try {
            $q = DB::table(self::TABLE)->orderByDesc('updated_at')->orderByDesc('id');

            $statusFilter = $statusFilter !== null ? strtolower(trim($statusFilter)) : null;
            if ($statusFilter !== null && $statusFilter !== '') {
                $q->where('status', $statusFilter);
            }
            if ($limit > 0) {
                $q->limit($limit);
            }

            $rows = $q->get();
        } catch (\Throwable $e) {
            Log::info('[research-leads] admin list failed: '.$e->getMessage());

            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->decorate($row);
        }

        return $out;
    }

    /**
     * Counts of leads per status, for the admin filter chips. Never throws.
     *
     * @return array<string,int>
     */
    public function statusCounts(): array
    {
        if (! $this->available()) {
            return [];
        }

        try {
            $rows = DB::table(self::TABLE)
                ->select('status', DB::raw('COUNT(*) as c'))
                ->groupBy('status')
                ->get();
        } catch (\Throwable $e) {
            Log::info('[research-leads] status counts failed: '.$e->getMessage());

            return [];
        }

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row->status] = (int) $row->c;
        }

        return $counts;
    }

    /**
     * One lead by id, decorated, or null. Never throws.
     *
     * @return array<string,mixed>|null
     */
    public function find(int $id): ?array
    {
        if (! $this->available() || $id <= 0) {
            return null;
        }

        try {
            $row = DB::table(self::TABLE)->where('id', $id)->first();
        } catch (\Throwable $e) {
            Log::info('[research-leads] find failed for '.$id.': '.$e->getMessage());

            return null;
        }

        return $row === null ? null : $this->decorate($row);
    }

    /**
     * The existing lead for one record (so generation refreshes rather than
     * duplicates). Decorated, or null. Never throws.
     *
     * @return array<string,mixed>|null
     */
    public function findByObject(int $objectId): ?array
    {
        if (! $this->available() || $objectId <= 0) {
            return null;
        }

        try {
            $row = DB::table(self::TABLE)->where('information_object_id', $objectId)->first();
        } catch (\Throwable $e) {
            Log::info('[research-leads] findByObject failed for '.$objectId.': '.$e->getMessage());

            return null;
        }

        return $row === null ? null : $this->decorate($row);
    }

    /**
     * Curate a lead to "published": it becomes visible on the public feed. Stamps
     * published_at + the curator. Returns true on success. Never throws.
     */
    public function publish(int $id, ?int $userId = null): bool
    {
        return $this->transition($id, 'published', $userId, true);
    }

    /**
     * Curate a lead to "dismissed": kept for the record but never shown publicly.
     */
    public function dismiss(int $id, ?int $userId = null): bool
    {
        return $this->transition($id, 'dismissed', $userId, false);
    }

    /**
     * Return a lead to "pending" (undo a publish/dismiss). Clears published_at.
     */
    public function repend(int $id, ?int $userId = null): bool
    {
        return $this->transition($id, 'pending', $userId, false);
    }

    /**
     * Shared status transition. Writes ONLY the leads table. Never throws.
     */
    protected function transition(int $id, string $status, ?int $userId, bool $stampPublished): bool
    {
        if (! $this->available() || $id <= 0) {
            return false;
        }

        $status = $this->normaliseStatus($status);
        $now = now();

        $update = [
            'status' => $status,
            'curated_by' => $userId,
            'updated_at' => $now,
        ];
        // published_at is set when publishing, cleared otherwise so the public
        // ordering (newest-published first) stays meaningful.
        $update['published_at'] = $stampPublished ? $now : null;

        try {
            return DB::table(self::TABLE)->where('id', $id)->update($update) >= 0;
        } catch (\Throwable $e) {
            Log::warning('[research-leads] transition to '.$status.' failed for '.$id.': '.$e->getMessage());

            return false;
        }
    }

    /**
     * Build a short, factual headline for a lead from the discovery's record title
     * and connection count. No AI - purely descriptive.
     *
     * @param  array<string,mixed>  $evidence
     */
    protected function buildHeadline(?string $title, array $evidence, int $total): string
    {
        $title = trim((string) ($title ?? ($evidence['record']['title'] ?? '')));
        if ($title === '') {
            $title = __('an unnamed record');
        }

        if ($total <= 0) {
            return (string) $title;
        }

        return trim((string) $title);
    }

    /**
     * A factual, graph-grounded "why this might matter" prompt, built with NO AI.
     * Turns the verified connection metrics into a researcher-facing question.
     * This is the floor every lead stands on, gateway up or down.
     *
     * @param  array<string,mixed>  $evidence
     */
    protected function factualWhyItMatters(int $total, array $evidence): string
    {
        $domains = $this->evidenceDomains($evidence);
        $secondHop = (int) ($evidence['second_hop_count'] ?? 0);

        $parts = [];
        if ($total === 1) {
            $parts[] = 'This record sits at the centre of a verified catalogue connection.';
        } elseif ($total > 1) {
            $parts[] = 'This record sits at the centre of '.$total.' verified catalogue connections.';
        } else {
            $parts[] = 'This record carries a connection worth following up.';
        }

        if ($domains) {
            $parts[] = 'The links cross '.$this->joinHuman($domains).'.';
        }
        if ($secondHop > 0) {
            $parts[] = $secondHop === 1
                ? 'One further entity is reachable indirectly through a shared link, which may point to a connection no one has written down.'
                : $secondHop.' further entities are reachable indirectly through shared links, which may point to connections no one has written down.';
        }

        $parts[] = 'Following these links could reveal shared people, places or activities worth comparing across the collection.';

        return implode(' ', $parts);
    }

    /**
     * OPTIONAL AI enrichment of the "why it matters" prompt, routed STRICTLY
     * through the AHG gateway via LlmService. Given only the lead text and the
     * verified evidence, the model writes a short, plain-language prompt framing
     * why the connection might be worth a researcher's time - grounded in the
     * supplied links, never inventing facts.
     *
     * Returns null when the gateway is unreachable / LlmService is unavailable
     * (so the caller keeps the factual prompt). NEVER throws. NEVER called on a
     * public page-load - only from generate() during an explicit admin/CLI run.
     *
     * @param  array<string,mixed>  $evidence
     */
    protected function aiWhyItMatters(?string $title, string $leadText, array $evidence): ?string
    {
        try {
            $llm = app(\AhgAiServices\Services\LlmService::class);
        } catch (\Throwable $e) {
            Log::info('[research-leads] LlmService unavailable: '.$e->getMessage());

            return null;
        }

        $label = trim((string) ($title ?? '')) !== '' ? '"'.trim((string) $title).'"' : 'this record';
        $domains = $this->evidenceDomains($evidence);
        $linkNames = $this->evidenceLinkNames($evidence, 20);

        $lines = [];
        $lines[] = 'You are a research analyst inside an archival catalogue. Write a SHORT, plain-language'
            .' prompt (2-3 sentences) explaining why the connection below might matter to a researcher,'
            .' phrased as an invitation to investigate.';
        $lines[] = '';
        $lines[] = 'HARD CONSTRAINTS:';
        $lines[] = '1. Use ONLY the catalogue links and notes supplied below. Do NOT introduce any person,'
            .' place, organisation, date, event or record not listed.';
        $lines[] = '2. NEVER invent facts, names or dates.';
        $lines[] = '3. Be concrete and inviting, e.g. "X and Y share Z - worth comparing their...".';
        $lines[] = '4. Plain language for a general scholarly audience. No preamble, no headings, no bullet points.';
        $lines[] = '';
        $lines[] = 'THE CONNECTION centres on '.$label.'.';
        if ($leadText !== '') {
            $lines[] = 'AI lead already drawn from the links: '.$leadText;
        }
        if ($domains) {
            $lines[] = 'The links cross these domains: '.implode(', ', $domains).'.';
        }
        if ($linkNames) {
            $lines[] = 'Verified linked entities: '.implode('; ', $linkNames).'.';
        }
        $lines[] = '';
        $lines[] = 'Return ONLY the 2-3 sentence prompt.';

        try {
            $raw = $llm->complete(implode("\n", $lines), [
                'system_prompt' => 'You are a careful archival research analyst. You ground every statement in'
                    .' the supplied catalogue links and never invent entities or facts. You write short, inviting,'
                    .' plain-language research prompts.',
                'temperature' => 0.3,
                'max_tokens' => 220,
                'purpose' => 'research-lead-why-it-matters',
                'data_scope' => 'catalogue-graph',
            ]);
        } catch (\Throwable $e) {
            Log::info('[research-leads] AI gateway call failed: '.$e->getMessage());

            return null;
        }

        if (! is_string($raw)) {
            return null;
        }

        $clean = trim($raw);
        // The gateway reached us (return ''-as-empty is still a "reached" signal),
        // but only use a non-trivial answer.
        if ($clean === '') {
            return '';
        }

        // Keep it tidy and bounded for the card.
        $clean = preg_replace('/\s+/', ' ', $clean);

        return mb_substr($clean, 0, 1200);
    }

    /**
     * Decode the discovery / lead evidence JSON snapshot into an array. Never
     * throws.
     *
     * @return array<string,mixed>
     */
    protected function decodeEvidence($evidence): array
    {
        if (empty($evidence)) {
            return [];
        }
        if (is_array($evidence)) {
            return $evidence;
        }
        $decoded = json_decode((string) $evidence, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * The distinct domains present in an evidence snapshot's grouped connections.
     *
     * @param  array<string,mixed>  $evidence
     * @return array<int,string>
     */
    protected function evidenceDomains(array $evidence): array
    {
        $domains = [];
        foreach (($evidence['connections'] ?? []) as $group) {
            $domain = trim((string) ($group['domain'] ?? ''));
            if ($domain !== '' && ! in_array($domain, $domains, true)) {
                $domains[] = $domain;
            }
        }

        return $domains;
    }

    /**
     * Up to $cap verified linked-entity names from an evidence snapshot, used to
     * ground the AI prompt.
     *
     * @param  array<string,mixed>  $evidence
     * @return array<int,string>
     */
    protected function evidenceLinkNames(array $evidence, int $cap = 20): array
    {
        $names = [];
        foreach (($evidence['connections'] ?? []) as $group) {
            foreach (($group['items'] ?? []) as $item) {
                $name = trim((string) ($item['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $names[] = $name;
                if (count($names) >= $cap) {
                    return $names;
                }
            }
        }

        return $names;
    }

    /**
     * Flatten an evidence snapshot's grouped connections into the card's link
     * list (name + slug + domain).
     *
     * @param  array<string,mixed>  $evidence
     * @return array<int,array{name:string,slug:?string,domain:string}>
     */
    protected function evidenceLinks(array $evidence): array
    {
        $links = [];
        foreach (($evidence['connections'] ?? []) as $group) {
            $domain = (string) ($group['domain'] ?? 'Other');
            foreach (($group['items'] ?? []) as $item) {
                $name = trim((string) ($item['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $links[] = [
                    'name' => $name,
                    'slug' => $item['slug'] ?? null,
                    'domain' => $domain,
                ];
            }
        }

        return $links;
    }

    /**
     * Join a list of strings into a human phrase ("a, b and c").
     *
     * @param  array<int,string>  $items
     */
    protected function joinHuman(array $items): string
    {
        $items = array_values(array_filter($items, fn ($v) => trim((string) $v) !== ''));
        $n = count($items);
        if ($n === 0) {
            return '';
        }
        if ($n === 1) {
            return $items[0];
        }
        $last = array_pop($items);

        return implode(', ', $items).' and '.$last;
    }

    /**
     * Is an information object PUBLISHED (and not the catalogue root)? Publication
     * status lives in the status table (type_id = 158); status_id = 160 means
     * published. Fully existence-guarded; on any uncertainty returns false so an
     * unpublished or absent record is never surfaced publicly.
     */
    public function isPublished(int $objectId): bool
    {
        if ($objectId <= 0 || $objectId === self::ROOT_ID) {
            return false;
        }

        try {
            if (! Schema::hasTable('information_object')) {
                return false;
            }
            if (! DB::table('information_object')->where('id', $objectId)->exists()) {
                return false;
            }
            if (! Schema::hasTable('status')) {
                return false;
            }

            $statusId = DB::table('status')
                ->where('object_id', $objectId)
                ->where('type_id', self::PUBLICATION_TYPE_ID)
                ->orderByDesc('id')
                ->value('status_id');

            return (int) $statusId === self::PUBLISHED_STATUS_ID;
        } catch (\Throwable $e) {
            Log::info('[research-leads] publish probe failed for '.$objectId.': '.$e->getMessage());

            return false;
        }
    }

    /**
     * Decorate a raw lead row into the array shape the views consume: the centre
     * record (title + slug, read-only from the catalogue), the verified links
     * rehydrated from the evidence snapshot, status + confidence metadata, and
     * the plain-language prompt. Never throws.
     *
     * @return array<string,mixed>
     */
    protected function decorate(object $row): array
    {
        $objectId = (int) ($row->information_object_id ?? 0);
        $evidence = $this->decodeEvidence($row->evidence ?? null);

        // Prefer a live catalogue lookup; fall back to the snapshot when the
        // catalogue tables are unavailable.
        [$title, $slug] = $this->recordTitleSlug($objectId, $evidence);

        $links = $this->evidenceLinks($evidence);
        $confidence = (int) ($row->confidence ?? 0);

        $lead = [
            'id' => (int) ($row->id ?? 0),
            'information_object_id' => $objectId,
            'source_discovery_id' => $row->source_discovery_id !== null ? (int) $row->source_discovery_id : null,
            'record' => [
                'id' => $objectId,
                'title' => $title,
                'slug' => $slug,
            ],
            'headline' => trim((string) ($row->headline ?? '')) !== '' ? (string) $row->headline : $title,
            'lead_text' => $row->lead_text !== null ? (string) $row->lead_text : null,
            'why_it_matters' => $row->why_it_matters !== null ? (string) $row->why_it_matters : null,
            'links' => $links,
            'domains' => $this->evidenceDomains($evidence),
            'second_hop' => (int) ($evidence['second_hop_count'] ?? 0),
            'connection_count' => (int) ($row->connection_count ?? 0),
            'confidence' => $this->confidenceBand($confidence),
            'ai_labelled' => (bool) ($row->ai_labelled ?? 1),
            'status' => (string) ($row->status ?? 'pending'),
            'generated_at' => $row->generated_at !== null ? (string) $row->generated_at : null,
            'published_at' => $row->published_at !== null ? (string) $row->published_at : null,
            'updated_at' => $row->updated_at !== null ? (string) $row->updated_at : null,
        ];

        $lead['status_meta'] = $this->statusMeta($lead['status']);

        return $lead;
    }

    /**
     * Read-only catalogue title + slug for one record, falling back to the
     * evidence snapshot. Never throws.
     *
     * @param  array<string,mixed>  $evidence
     * @return array{0:?string,1:?string}
     */
    protected function recordTitleSlug(int $objectId, array $evidence): array
    {
        $title = $evidence['record']['title'] ?? null;
        $slug = $evidence['record']['slug'] ?? null;

        if ($objectId <= 0) {
            return [$title !== null ? (string) $title : null, $slug !== null ? (string) $slug : null];
        }

        try {
            if (Schema::hasTable('information_object_i18n')) {
                $live = DB::table('information_object_i18n')->where('id', $objectId)->value('title');
                if ($live !== null && trim((string) $live) !== '') {
                    $title = (string) $live;
                }
            }
            if (Schema::hasTable('slug')) {
                $liveSlug = DB::table('slug')->where('object_id', $objectId)->value('slug');
                if ($liveSlug !== null && trim((string) $liveSlug) !== '') {
                    $slug = (string) $liveSlug;
                }
            }
        } catch (\Throwable $e) {
            Log::info('[research-leads] title/slug lookup failed for '.$objectId.': '.$e->getMessage());
        }

        return [$title !== null ? (string) $title : null, $slug !== null ? (string) $slug : null];
    }
}
