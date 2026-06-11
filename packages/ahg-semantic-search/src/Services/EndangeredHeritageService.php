<?php

/**
 * EndangeredHeritageService - endangered-heritage register + capture-priority
 * list (north-star heratio#1205: the race against loss).
 *
 * Some heritage is at risk of being lost before it is ever captured: conflict,
 * climate, material decay, lost funding, displacement, or a simple digitisation
 * gap. This service lets curators FLAG catalogue items as at-risk and then drives
 * the lightweight workflow that turns that register into action:
 *
 *   - flag()            - record (or update) an at-risk flag against an item
 *   - list()            - list flags, optionally filtered by capture_status
 *   - priorityList()    - the capture WORKLIST, ordered most-urgent first, each
 *                         row carrying a simple priority score
 *   - find()            - one flag by id
 *   - findByItem()      - the existing flag for an item (so re-flagging updates)
 *   - updateCaptureStatus() - advance a flag through the capture workflow
 *   - statusCounts() / urgencyCounts() - filter-chip counts for the views
 *   - publicRegister()  - the read-only "at risk" register: PUBLISHED items only
 *
 * Writes ONLY to the new endangered_heritage_item table. Everything it knows
 * about the underlying item it reads, read-only, from the existing catalogue
 * (information_object / information_object_i18n / slug / status), all behind
 * Schema::hasTable probes. It never ALTERs or writes any existing table.
 *
 * The published-records gate mirrors the rest of Heratio: an item is "published"
 * when its row in the status table (type_id = 158 publication status) carries
 * status_id = 160 (published); the catalogue root (id = 1) is never surfaced.
 *
 * Framing is deliberately factual and non-alarmist. A flag records that an item
 * is judged to need capture sooner rather than later, and why - not a prediction
 * of certain loss, and not a claim about any institution's stewardship.
 *
 * Every read/write path is Schema::hasTable-guarded and wrapped so a missing
 * table degrades to an empty result rather than a 500.
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

class EndangeredHeritageService
{
    /** The single table this service writes to. */
    public const TABLE = 'endangered_heritage_item';

    /** Catalogue root id, never surfaced as a real record. */
    protected const ROOT_ID = 1;

    /** Publication-status type_id and the "published" status_id in the status table. */
    protected const PUBLICATION_TYPE_ID = 158;

    protected const PUBLISHED_STATUS_ID = 160;

    /**
     * Canonical risk categories. VARCHAR in the table, NOT an ENUM: this list
     * seeds the form dropdown and validation while the column itself accepts any
     * value supplied by the Dropdown Manager. Each is a neutral, factual reason
     * heritage can be lost before capture - never an accusation of neglect.
     *
     * @var array<string,array{label:string,help:string,icon:string}>
     */
    public const RISK_CATEGORIES = [
        'conflict' => [
            'label' => 'Conflict or unrest',
            'help' => 'Armed conflict, civil unrest or instability threatens the item or its holding site.',
            'icon' => 'fa-person-rifle',
        ],
        'climate' => [
            'label' => 'Climate or environment',
            'help' => 'Flood, fire, drought, sea-level rise or other environmental pressure threatens the item.',
            'icon' => 'fa-cloud-showers-heavy',
        ],
        'decay' => [
            'label' => 'Material decay',
            'help' => 'The physical carrier is degrading: fragile media, obsolete formats, mould, corrosion or embrittlement.',
            'icon' => 'fa-hourglass-half',
        ],
        'funding' => [
            'label' => 'Funding or stewardship risk',
            'help' => 'Loss of funding or custodial capacity puts ongoing care and access at risk.',
            'icon' => 'fa-hand-holding-dollar',
        ],
        'displacement' => [
            'label' => 'Displacement',
            'help' => 'The item or community of origin is displaced, threatening continuity of context and care.',
            'icon' => 'fa-route',
        ],
        'digitisation_gap' => [
            'label' => 'Digitisation gap',
            'help' => 'No durable digital surrogate exists yet, so the only record is the vulnerable original.',
            'icon' => 'fa-camera-retro',
        ],
        'other' => [
            'label' => 'Other risk',
            'help' => 'Another documented risk to the item that warrants prioritised capture.',
            'icon' => 'fa-triangle-exclamation',
        ],
    ];

    /**
     * Canonical urgency bands, most-urgent first, each with a base weight used by
     * the priority score. VARCHAR-backed dropdown, never an ENUM.
     *
     * @var array<string,array{label:string,level:string,weight:int}>
     */
    public const URGENCIES = [
        'critical' => ['label' => 'Critical', 'level' => 'danger', 'weight' => 1000],
        'high' => ['label' => 'High', 'level' => 'warning', 'weight' => 100],
        'medium' => ['label' => 'Medium', 'level' => 'info', 'weight' => 10],
        'low' => ['label' => 'Low', 'level' => 'secondary', 'weight' => 1],
    ];

    /**
     * Canonical capture-workflow values, in flow order. VARCHAR-backed dropdown,
     * never an ENUM. Each describes where the capture effort stands.
     *
     * @var array<string,array{label:string,level:string,help:string}>
     */
    public const CAPTURE_STATUSES = [
        'unflagged' => [
            'label' => 'Unflagged',
            'level' => 'light',
            'help' => 'No longer treated as at-risk for capture purposes.',
        ],
        'flagged' => [
            'label' => 'Flagged',
            'level' => 'secondary',
            'help' => 'Identified as at-risk and awaiting capture.',
        ],
        'in_progress' => [
            'label' => 'Capture in progress',
            'level' => 'primary',
            'help' => 'Digitisation or capture work is under way.',
        ],
        'captured' => [
            'label' => 'Captured',
            'level' => 'success',
            'help' => 'A durable digital surrogate has been produced.',
        ],
    ];

    /**
     * Standing framing surfaced wherever the register is shown. Factual,
     * non-alarmist: a flag is a prioritisation judgement, not a prediction of
     * certain loss or a claim about any institution's stewardship.
     */
    public const DISCLAIMER = 'This register records items that curators judge should be captured sooner rather '
        .'than later, and the documented reason why. A flag is a prioritisation aid offered in a spirit of care: '
        .'it is NOT a prediction that an item will be lost, NOT a statement about any institution stewardship, and '
        .'NOT advice. Risk to heritage, and the order in which to act, are matters for qualified staff to assess '
        .'against the evidence in every case.';

    /**
     * Is the register table present? All read/write paths gate on this so a
     * fresh (un-booted) install never fatals.
     */
    public function available(): bool
    {
        try {
            return Schema::hasTable(self::TABLE);
        } catch (\Throwable $e) {
            Log::info('[endangered] table probe failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Normalise an incoming risk category to a known canonical value, defaulting
     * to "other" when blank/unknown.
     */
    public function normaliseRisk(?string $risk): string
    {
        $risk = strtolower(trim((string) $risk));

        return array_key_exists($risk, self::RISK_CATEGORIES) ? $risk : 'other';
    }

    /**
     * Normalise an incoming urgency to a known canonical value, defaulting to
     * "medium" when blank/unknown.
     */
    public function normaliseUrgency(?string $urgency): string
    {
        $urgency = strtolower(trim((string) $urgency));

        return array_key_exists($urgency, self::URGENCIES) ? $urgency : 'medium';
    }

    /**
     * Normalise an incoming capture status to a known canonical value, defaulting
     * to "flagged" when blank/unknown.
     */
    public function normaliseCaptureStatus(?string $status): string
    {
        $status = strtolower(trim((string) $status));

        return array_key_exists($status, self::CAPTURE_STATUSES) ? $status : 'flagged';
    }

    /**
     * Human metadata for an urgency value (label / level / weight), with a safe
     * fallback for any value not in the canonical map.
     *
     * @return array{key:string,label:string,level:string,weight:int}
     */
    public function urgencyMeta(?string $urgency): array
    {
        $key = strtolower(trim((string) $urgency));
        if (isset(self::URGENCIES[$key])) {
            return ['key' => $key] + self::URGENCIES[$key];
        }

        return ['key' => $key === '' ? 'medium' : $key, 'label' => ucfirst($key ?: 'medium'), 'level' => 'info', 'weight' => 10];
    }

    /**
     * Human metadata for a capture-status value, with a safe fallback.
     *
     * @return array{key:string,label:string,level:string,help:string}
     */
    public function captureStatusMeta(?string $status): array
    {
        $key = strtolower(trim((string) $status));
        if (isset(self::CAPTURE_STATUSES[$key])) {
            return ['key' => $key] + self::CAPTURE_STATUSES[$key];
        }

        $label = $key === '' ? 'Flagged' : ucwords(str_replace('_', ' ', $key));

        return ['key' => $key === '' ? 'flagged' : $key, 'label' => $label, 'level' => 'secondary', 'help' => ''];
    }

    /**
     * Human metadata for a risk category, with a safe fallback.
     *
     * @return array{key:string,label:string,help:string,icon:string}
     */
    public function riskMeta(?string $risk): array
    {
        $key = strtolower(trim((string) $risk));
        if (isset(self::RISK_CATEGORIES[$key])) {
            return ['key' => $key] + self::RISK_CATEGORIES[$key];
        }

        $label = $key === '' ? 'Other risk' : ucwords(str_replace('_', ' ', $key));

        return ['key' => $key === '' ? 'other' : $key, 'label' => $label, 'help' => '', 'icon' => 'fa-triangle-exclamation'];
    }

    /**
     * A simple, transparent priority score for ordering the capture worklist.
     *
     * Score = urgency base weight + a small bonus when no durable digital
     * surrogate exists yet (the digitisation_gap category, which is the case the
     * "race against loss" most wants surfaced) + a tiny recency-of-flag nudge so
     * that, within an urgency band, the longest-waiting flags sort first. The
     * formula is intentionally legible so a curator can reason about the order.
     *
     * @param  array<string,mixed>  $flag
     */
    public function priorityScore(array $flag): int
    {
        $urgency = $this->normaliseUrgency($flag['urgency'] ?? null);
        $base = (int) (self::URGENCIES[$urgency]['weight'] ?? 10);

        $risk = $this->normaliseRisk($flag['risk_category'] ?? null);
        $gapBonus = $risk === 'digitisation_gap' ? 5 : 0;

        // Captured items drop to the floor: they no longer compete for capture
        // effort, but we keep them visible rather than hiding them.
        $captureStatus = $this->normaliseCaptureStatus($flag['capture_status'] ?? null);
        if ($captureStatus === 'captured' || $captureStatus === 'unflagged') {
            return 0;
        }

        return $base + $gapBonus;
    }

    /**
     * Record (or update) an at-risk flag against an item. If the item already
     * carries a flag, the existing row is updated in place (one flag per item).
     * Writes one row to the new table only. Returns the flag id, or null on
     * failure (never throws).
     *
     * @param  array<string,mixed>  $data
     */
    public function flag(array $data, ?int $userId = null): ?int
    {
        if (! $this->available()) {
            return null;
        }

        $itemRef = (int) ($data['item_ref'] ?? 0);
        if ($itemRef <= 0) {
            return null;
        }

        $now = now();

        $payload = [
            'item_ref' => $itemRef,
            'risk_category' => $this->normaliseRisk($data['risk_category'] ?? 'other'),
            'urgency' => $this->normaliseUrgency($data['urgency'] ?? 'medium'),
            'reason' => $this->clipText($data['reason'] ?? null),
            'capture_status' => $this->normaliseCaptureStatus($data['capture_status'] ?? 'flagged'),
            'updated_at' => $now,
        ];

        try {
            $existing = $this->findByItem($itemRef);
            if ($existing !== null) {
                DB::table(self::TABLE)->where('id', (int) $existing['id'])->update($payload);

                return (int) $existing['id'];
            }

            $payload['flagged_by'] = $userId;
            $payload['created_at'] = $now;

            return (int) DB::table(self::TABLE)->insertGetId($payload);
        } catch (\Throwable $e) {
            Log::warning('[endangered] flag failed for item '.$itemRef.': '.$e->getMessage());

            return null;
        }
    }

    /**
     * Advance just the capture status of a flag. Returns true on success.
     */
    public function updateCaptureStatus(int $id, ?string $status): bool
    {
        if (! $this->available() || $id <= 0) {
            return false;
        }

        try {
            return DB::table(self::TABLE)->where('id', $id)->update([
                'capture_status' => $this->normaliseCaptureStatus($status),
                'updated_at' => now(),
            ]) >= 0;
        } catch (\Throwable $e) {
            Log::warning('[endangered] capture-status update failed for '.$id.': '.$e->getMessage());

            return false;
        }
    }

    /**
     * List flags, optionally filtered to one capture status. Each row is enriched,
     * read-only, with the underlying item's catalogue title and slug, its display
     * metadata, and its priority score. Never throws - degrades to an empty list.
     *
     * @return array<int,array<string,mixed>>
     */
    public function list(?string $statusFilter = null, int $limit = 1000): array
    {
        if (! $this->available()) {
            return [];
        }

        try {
            $q = DB::table(self::TABLE)->orderByDesc('updated_at')->orderByDesc('id');

            $statusFilter = $statusFilter !== null ? strtolower(trim($statusFilter)) : null;
            if ($statusFilter !== null && $statusFilter !== '') {
                $q->where('capture_status', $statusFilter);
            }
            if ($limit > 0) {
                $q->limit($limit);
            }

            $rows = $q->get();
        } catch (\Throwable $e) {
            Log::info('[endangered] list failed: '.$e->getMessage());

            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->decorate($row);
        }

        return $out;
    }

    /**
     * The capture-priority WORKLIST: every flag still awaiting capture (flagged or
     * in_progress), ordered most-urgent first by priority score, then by how long
     * it has waited. Captured / unflagged rows are excluded - this is the list of
     * work still to do. Never throws.
     *
     * @return array<int,array<string,mixed>>
     */
    public function priorityList(int $limit = 1000): array
    {
        $rows = $this->list(null, $limit);

        $worklist = array_values(array_filter($rows, function ($r) {
            $s = (string) ($r['capture_status'] ?? 'flagged');

            return $s === 'flagged' || $s === 'in_progress';
        }));

        usort($worklist, function ($a, $b) {
            $sa = (int) ($a['priority_score'] ?? 0);
            $sb = (int) ($b['priority_score'] ?? 0);
            if ($sa !== $sb) {
                return $sb <=> $sa; // higher score first
            }
            // Within a band, the oldest flag (smallest id) sorts first.
            return (int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0);
        });

        return $worklist;
    }

    /**
     * Counts of flags per capture status (for the worklist filter chips).
     * Includes any dropdown-added value actually present. Never throws.
     *
     * @return array<string,int>
     */
    public function statusCounts(): array
    {
        return $this->countsBy('capture_status');
    }

    /**
     * Counts of flags per urgency band. Never throws.
     *
     * @return array<string,int>
     */
    public function urgencyCounts(): array
    {
        return $this->countsBy('urgency');
    }

    /**
     * Shared GROUP BY count helper.
     *
     * @return array<string,int>
     */
    protected function countsBy(string $column): array
    {
        if (! $this->available()) {
            return [];
        }

        try {
            $rows = DB::table(self::TABLE)
                ->select($column, DB::raw('COUNT(*) as c'))
                ->groupBy($column)
                ->get();
        } catch (\Throwable $e) {
            Log::info('[endangered] counts failed for '.$column.': '.$e->getMessage());

            return [];
        }

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row->{$column}] = (int) $row->c;
        }

        return $counts;
    }

    /**
     * One flag by id, decorated, or null if not found. Never throws.
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
            Log::info('[endangered] find failed for '.$id.': '.$e->getMessage());

            return null;
        }

        return $row === null ? null : $this->decorate($row);
    }

    /**
     * The existing flag for one item (so re-flagging updates rather than
     * duplicates). Decorated, or null. Never throws.
     *
     * @return array<string,mixed>|null
     */
    public function findByItem(int $itemRef): ?array
    {
        if (! $this->available() || $itemRef <= 0) {
            return null;
        }

        try {
            $row = DB::table(self::TABLE)->where('item_ref', $itemRef)->orderByDesc('id')->first();
        } catch (\Throwable $e) {
            Log::info('[endangered] findByItem failed for '.$itemRef.': '.$e->getMessage());

            return null;
        }

        return $row === null ? null : $this->decorate($row);
    }

    /**
     * The public, read-only "at risk" register: PUBLISHED items only, still
     * awaiting capture (flagged or in_progress), ordered most-urgent first. This
     * is the surface that frames why heritage is endangered and the race to
     * capture it. Captured items are omitted (the race is won for them) and so are
     * unpublished items. Never throws.
     *
     * @return array<int,array<string,mixed>>
     */
    public function publicRegister(?string $riskFilter = null, int $limit = 1000): array
    {
        $worklist = $this->priorityList($limit);

        $riskFilter = $riskFilter !== null ? strtolower(trim($riskFilter)) : null;

        $out = [];
        foreach ($worklist as $row) {
            // Publication gate: only published, real records appear publicly.
            if (! $this->isPublished((int) ($row['item_ref'] ?? 0))) {
                continue;
            }
            if ($riskFilter !== null && $riskFilter !== '' && strcasecmp((string) ($row['risk_category'] ?? ''), $riskFilter) !== 0) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Counts of PUBLISHED at-risk items per risk category, for the public-register
     * filter chips. Built from publicRegister() so the gate is applied once and
     * consistently. Never throws.
     *
     * @param  array<int,array<string,mixed>>|null  $register  pre-built register, to avoid a second pass
     * @return array<string,int>
     */
    public function publicRiskCounts(?array $register = null): array
    {
        $register = $register ?? $this->publicRegister(null, 0);

        $counts = [];
        foreach ($register as $row) {
            $key = (string) ($row['risk_category'] ?? 'other');
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Is an information object PUBLISHED (and not the catalogue root)? Publication
     * status lives in the status table (type_id = 158); status_id = 160 means
     * published. Fully existence-guarded; on any uncertainty returns false so an
     * unpublished or absent item is never surfaced publicly.
     */
    public function isPublished(int $itemRef): bool
    {
        if ($itemRef <= 0 || $itemRef === self::ROOT_ID) {
            return false;
        }

        try {
            if (! Schema::hasTable('information_object')) {
                return false;
            }
            if (! DB::table('information_object')->where('id', $itemRef)->exists()) {
                return false;
            }

            if (! Schema::hasTable('status')) {
                // No status table at all: be conservative and treat as unpublished
                // for the PUBLIC surface.
                return false;
            }

            $statusId = DB::table('status')
                ->where('object_id', $itemRef)
                ->where('type_id', self::PUBLICATION_TYPE_ID)
                ->orderByDesc('id')
                ->value('status_id');

            return (int) $statusId === self::PUBLISHED_STATUS_ID;
        } catch (\Throwable $e) {
            Log::info('[endangered] publish probe failed for '.$itemRef.': '.$e->getMessage());

            return false;
        }
    }

    /**
     * Decorate a raw flag row: array shape + display metadata (risk / urgency /
     * capture status) + priority score + best-effort catalogue title/slug for the
     * underlying item.
     *
     * @return array<string,mixed>
     */
    protected function decorate(object $row): array
    {
        $itemRef = (int) ($row->item_ref ?? 0);

        $title = null;
        $slug = null;
        try {
            if (Schema::hasTable('information_object_i18n')) {
                $title = DB::table('information_object_i18n')->where('id', $itemRef)->value('title');
                $title = $title !== null ? (string) $title : null;
            }
            if (Schema::hasTable('slug')) {
                $slug = DB::table('slug')->where('object_id', $itemRef)->value('slug');
                $slug = $slug !== null ? (string) $slug : null;
            }
        } catch (\Throwable $e) {
            Log::info('[endangered] decorate lookup failed for '.$itemRef.': '.$e->getMessage());
        }

        $flag = [
            'id' => (int) $row->id,
            'item_ref' => $itemRef,
            'item_title' => $title,
            'item_slug' => $slug,
            'risk_category' => (string) ($row->risk_category ?? 'other'),
            'urgency' => (string) ($row->urgency ?? 'medium'),
            'reason' => $row->reason !== null ? (string) $row->reason : null,
            'capture_status' => (string) ($row->capture_status ?? 'flagged'),
            'flagged_by' => $row->flagged_by !== null ? (int) $row->flagged_by : null,
            'created_at' => $row->created_at !== null ? (string) $row->created_at : null,
            'updated_at' => $row->updated_at !== null ? (string) $row->updated_at : null,
        ];

        $flag['risk_meta'] = $this->riskMeta($flag['risk_category']);
        $flag['urgency_meta'] = $this->urgencyMeta($flag['urgency']);
        $flag['capture_meta'] = $this->captureStatusMeta($flag['capture_status']);
        $flag['priority_score'] = $this->priorityScore($flag);

        return $flag;
    }

    /**
     * Trim a long TEXT value, returning null for blanks.
     */
    protected function clipText($value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
