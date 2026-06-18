<?php

/**
 * RepatriationClaimService - structured repatriation-claim / virtual-return
 * workflow on top of the displaced-heritage register (north-star heratio#1207).
 *
 * Where DisplacedHeritageService DETECTS (read-only) which catalogue items have a
 * recorded origin that differs from where they are held, this service layers a
 * human-curated CLAIM on top of any such item and drives the lightweight
 * workflow around it:
 *
 *   - register()  - record a claim against a displaced item
 *   - list()      - list claims, optionally filtered by status
 *   - find()      - one claim by id
 *   - updateStatus() / update() - advance / amend a claim (admin)
 *   - virtualReturn() - assemble the read-only "virtual return" context for one
 *                       claim: the item shown in its ORIGIN context (origin place
 *                       + claimant community + the item's record link), so the
 *                       object can be re-encountered in its own context even when
 *                       physical return has not happened.
 *
 * Writes ONLY to the new displaced_heritage_claim table. Everything it knows
 * about the underlying item it reads, read-only, from the existing register
 * (DisplacedHeritageService::scan()) plus existence-guarded look-ups of the
 * catalogue title / slug. It never ALTERs or writes any existing table.
 *
 * Sensitive subject matter. A claim is a DOCUMENTED REQUEST AND ITS STATUS,
 * recorded for transparency and dialogue. The status values describe where a
 * conversation stands; none asserts a legal outcome. Every read path is
 * Schema::hasTable-guarded and wrapped so a missing table degrades to an empty
 * result rather than a 500.
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

class RepatriationClaimService
{
    /** The single table this service writes to. */
    public const TABLE = 'displaced_heritage_claim';

    /**
     * Canonical claim-status workflow values. VARCHAR in the table, NOT an ENUM:
     * this list seeds the form dropdown and validation, while the column itself
     * accepts any value supplied by the Dropdown Manager. Each value is a
     * neutral, factual stage in a documented dialogue - never a legal verdict.
     *
     * @var array<string,array{label:string,level:string,help:string}>
     */
    public const STATUSES = [
        'registered' => [
            'label' => 'Registered',
            'level' => 'secondary',
            'help' => 'A claim has been recorded and is awaiting review.',
        ],
        'under_review' => [
            'label' => 'Under review',
            'level' => 'info',
            'help' => 'The claim and its documented evidence are being examined.',
        ],
        'acknowledged' => [
            'label' => 'Acknowledged',
            'level' => 'primary',
            'help' => 'The holding institution has acknowledged the claim and the dialogue is open.',
        ],
        'virtual_return' => [
            'label' => 'Virtual return',
            'level' => 'dark',
            'help' => 'The object is made accessible in its origin context digitally, independent of any physical transfer.',
        ],
        'returned' => [
            'label' => 'Returned',
            'level' => 'success',
            'help' => 'A physical return has been recorded.',
        ],
        'disputed' => [
            'label' => 'Disputed',
            'level' => 'warning',
            'help' => 'The facts or the claim are contested and remain under discussion.',
        ],
    ];

    /**
     * Standing framing surfaced wherever claims are shown. A claim record is a
     * documented request and its status - never a legal determination.
     */
    public const DISCLAIMER = 'A repatriation claim record documents a request and where the dialogue around it '
        .'currently stands, recorded in a spirit of transparency and care. A claim, and any status it carries, is '
        .'NOT a legal determination, NOT a finding of wrongful removal, and NOT advice. Origin, ownership and '
        .'lawful-transfer history are matters for the relevant communities, holding institutions and qualified '
        .'staff to assess together, case by case, under the applicable law.';

    protected DisplacedHeritageService $register;

    public function __construct(?DisplacedHeritageService $register = null)
    {
        $this->register = $register ?? new DisplacedHeritageService;
    }

    /**
     * Is the claim table present? All read/write paths gate on this so a fresh
     * (un-booted) install never fatals.
     */
    public function available(): bool
    {
        try {
            return Schema::hasTable(self::TABLE);
        } catch (\Throwable $e) {
            Log::info('[repatriation] table probe failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Normalise an incoming status to a known canonical value, defaulting to
     * "registered" when blank/unknown. Keeps the workflow values clean while the
     * VARCHAR column itself stays open to Dropdown-Manager additions.
     */
    public function normaliseStatus(?string $status): string
    {
        $status = strtolower(trim((string) $status));

        return array_key_exists($status, self::STATUSES) ? $status : 'registered';
    }

    /**
     * Human metadata for a status value (label / bootstrap level / help), with a
     * safe fallback for any value not in the canonical map.
     *
     * @return array{key:string,label:string,level:string,help:string}
     */
    public function statusMeta(?string $status): array
    {
        $key = strtolower(trim((string) $status));
        if (isset(self::STATUSES[$key])) {
            return ['key' => $key] + self::STATUSES[$key];
        }

        // Unknown (dropdown-added) value: present it as a neutral label.
        $label = $key === '' ? 'Registered' : ucwords(str_replace('_', ' ', $key));

        return ['key' => $key === '' ? 'registered' : $key, 'label' => $label, 'level' => 'secondary', 'help' => ''];
    }

    /**
     * Register a new claim against a displaced item. Writes one row to the new
     * table only. Returns the new id, or null on failure (never throws).
     *
     * @param  array<string,mixed>  $data
     */
    public function register(array $data, ?int $userId = null): ?int
    {
        if (! $this->available()) {
            return null;
        }

        $itemRef = (int) ($data['item_ref'] ?? 0);
        if ($itemRef <= 0) {
            return null;
        }

        $now = now();

        try {
            $id = (int) DB::table(self::TABLE)->insertGetId([
                'item_ref' => $itemRef,
                'claimant_community' => $this->clip($data['claimant_community'] ?? null, 512),
                'origin_place' => $this->clip($data['origin_place'] ?? null, 512),
                'current_holder' => $this->clip($data['current_holder'] ?? null, 512),
                'claim_status' => $this->normaliseStatus($data['claim_status'] ?? 'registered'),
                'evidence_summary' => $this->clipText($data['evidence_summary'] ?? null),
                'contact' => $this->clip($data['contact'] ?? null, 512),
                'notes' => $this->clipText($data['notes'] ?? null),
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[repatriation] register failed: '.$e->getMessage());

            return null;
        }

        // #1207 notifications: staff in-app + claimant email receipt. Fail-soft.
        if ($id > 0) {
            $this->notifyClaimRegistered($id, $userId);
        }

        return $id;
    }

    /**
     * Update an existing claim's editable fields. Returns true on success. When the
     * status changes as part of the edit, an audit-trail row is appended (who/when/
     * from->to) via the dialogue service - best effort, never blocks the update.
     *
     * @param  array<string,mixed>  $data
     */
    public function update(int $id, array $data, ?int $userId = null, ?string $userName = null): bool
    {
        if (! $this->available() || $id <= 0) {
            return false;
        }

        // Read the current status BEFORE the write so the audit trail can record
        // the transition. Read-only; degrades to null on any failure.
        $before = null;
        try {
            $before = DB::table(self::TABLE)->where('id', $id)->value('claim_status');
            $before = $before !== null ? (string) $before : null;
        } catch (\Throwable $e) {
            Log::info('[repatriation] pre-update status read failed for '.$id.': '.$e->getMessage());
        }

        $newStatus = $this->normaliseStatus($data['claim_status'] ?? 'registered');

        $update = [
            'claimant_community' => $this->clip($data['claimant_community'] ?? null, 512),
            'origin_place' => $this->clip($data['origin_place'] ?? null, 512),
            'current_holder' => $this->clip($data['current_holder'] ?? null, 512),
            'claim_status' => $newStatus,
            'evidence_summary' => $this->clipText($data['evidence_summary'] ?? null),
            'contact' => $this->clip($data['contact'] ?? null, 512),
            'notes' => $this->clipText($data['notes'] ?? null),
            'updated_at' => now(),
        ];

        try {
            $ok = DB::table(self::TABLE)->where('id', $id)->update($update) >= 0;
        } catch (\Throwable $e) {
            Log::warning('[repatriation] update failed for '.$id.': '.$e->getMessage());

            return false;
        }

        if ($ok && $before !== $newStatus) {
            $this->logStatusTransition($id, $before, $newStatus, null, $userId, $userName);
            $this->notifyStatusChanged($id, $before, $newStatus, $userId, $userName);
        }

        return $ok;
    }

    /**
     * Advance just the status of a claim. Returns true on success. Records the
     * transition (who/when/from->to + optional note) in the audit trail - best
     * effort, never blocks the status change.
     */
    public function updateStatus(int $id, ?string $status, ?string $note = null, ?int $userId = null, ?string $userName = null): bool
    {
        if (! $this->available() || $id <= 0) {
            return false;
        }

        $before = null;
        try {
            $before = DB::table(self::TABLE)->where('id', $id)->value('claim_status');
            $before = $before !== null ? (string) $before : null;
        } catch (\Throwable $e) {
            Log::info('[repatriation] pre-status read failed for '.$id.': '.$e->getMessage());
        }

        $to = $this->normaliseStatus($status);

        try {
            $ok = DB::table(self::TABLE)->where('id', $id)->update([
                'claim_status' => $to,
                'updated_at' => now(),
            ]) >= 0;
        } catch (\Throwable $e) {
            Log::warning('[repatriation] status update failed for '.$id.': '.$e->getMessage());

            return false;
        }

        if ($ok) {
            $this->logStatusTransition($id, $before, $to, $note, $userId, $userName);
            if ($before !== $to) {
                $this->notifyStatusChanged($id, $before, $to, $userId, $userName);
            }
        }

        return $ok;
    }

    /**
     * Append one status-transition row to the audit trail via the dialogue
     * service. Best effort: a missing audit table or any failure is swallowed so
     * the claim write is never rolled back by an audit hiccup.
     */
    protected function logStatusTransition(int $id, ?string $from, string $to, ?string $note, ?int $userId, ?string $userName): void
    {
        try {
            (new RepatriationDialogueService)->logStatusChange($id, $from, $to, $note, $userId, $userName);
        } catch (\Throwable $e) {
            Log::info('[repatriation] status audit log skipped for '.$id.': '.$e->getMessage());
        }
    }

    /**
     * #1207: fire the "new claim" notifications (staff in-app + claimant email).
     * Best effort: a notification failure never blocks the claim write.
     */
    protected function notifyClaimRegistered(int $id, ?int $userId, ?string $userName = null): void
    {
        try {
            (new RepatriationNotifier)->claimRegistered($id, $userId, $userName);
        } catch (\Throwable $e) {
            Log::info('[repatriation] register notification skipped for '.$id.': '.$e->getMessage());
        }
    }

    /**
     * #1207: fire the "status changed" notifications (staff/logger in-app +
     * claimant email). Best effort: a notification failure never blocks the write.
     */
    protected function notifyStatusChanged(int $id, ?string $from, string $to, ?int $userId, ?string $userName): void
    {
        try {
            (new RepatriationNotifier)->claimStatusChanged($id, $from, $to, $userId, $userName);
        } catch (\Throwable $e) {
            Log::info('[repatriation] status notification skipped for '.$id.': '.$e->getMessage());
        }
    }

    /**
     * List claims, optionally filtered to one status. Each row is enriched,
     * read-only, with the underlying item's catalogue title and slug (best
     * effort). Never throws - degrades to an empty list.
     *
     * @return array<int,array<string,mixed>>
     */
    public function list(?string $statusFilter = null, int $limit = 500): array
    {
        if (! $this->available()) {
            return [];
        }

        try {
            $q = DB::table(self::TABLE)->orderByDesc('updated_at')->orderByDesc('id');

            $statusFilter = $statusFilter !== null ? strtolower(trim($statusFilter)) : null;
            if ($statusFilter !== null && $statusFilter !== '') {
                $q->where('claim_status', $statusFilter);
            }
            if ($limit > 0) {
                $q->limit($limit);
            }

            $rows = $q->get();
        } catch (\Throwable $e) {
            Log::info('[repatriation] list failed: '.$e->getMessage());

            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->decorate($row);
        }

        return $out;
    }

    /**
     * Counts of claims per status (for the register filter chips). Includes any
     * dropdown-added value actually present in the data, not only the canonical
     * set. Never throws.
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
                ->select('claim_status', DB::raw('COUNT(*) as c'))
                ->groupBy('claim_status')
                ->get();
        } catch (\Throwable $e) {
            Log::info('[repatriation] status counts failed: '.$e->getMessage());

            return [];
        }

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row->claim_status] = (int) $row->c;
        }

        return $counts;
    }

    /**
     * Read-only aggregate of the whole claims register for the public dashboard
     * (north-star heratio#1207). Cheap aggregate COUNTs only - no per-record
     * loops, no heavy joins - so the page stays fast on a large register. Every
     * query is Schema::hasTable-guarded (via available()) and wrapped so a missing
     * table or any failure degrades to a fully-zeroed structure rather than a 500.
     *
     * The shape is deliberately presentation-agnostic: counts keyed by status, a
     * normalised top-status breakdown carrying the same neutral label/level/help
     * metadata the rest of the feature uses, the top origin places / communities,
     * the virtual-return vs physically-returned split, the grand total, and a
     * small "recent activity" tail (the only place that reads individual rows, and
     * only a handful, decorated for linking to each /virtual-return/{id} page).
     *
     * @param  int  $topOrigins   how many origin places / communities to surface
     * @param  int  $recentLimit  how many recent claims to surface
     * @return array{
     *   available:bool,
     *   total:int,
     *   by_status:array<int,array{key:string,label:string,level:string,help:string,count:int}>,
     *   raw_status_counts:array<string,int>,
     *   by_origin:array<int,array{place:string,count:int}>,
     *   by_community:array<int,array{community:string,count:int}>,
     *   virtual_return:int,
     *   returned:int,
     *   in_dialogue:int,
     *   recent:array<int,array<string,mixed>>
     * }
     */
    public function dashboard(int $topOrigins = 12, int $recentLimit = 8): array
    {
        $empty = [
            'available' => false,
            'total' => 0,
            'by_status' => [],
            'raw_status_counts' => [],
            'by_origin' => [],
            'by_community' => [],
            'virtual_return' => 0,
            'returned' => 0,
            'in_dialogue' => 0,
            'recent' => [],
        ];

        if (! $this->available()) {
            return $empty;
        }

        // Counts per status (single GROUP BY). Reuses statusCounts() so there is
        // one source of truth for the status tally.
        $rawCounts = $this->statusCounts();
        $total = array_sum($rawCounts);

        // Normalise into an ordered, labelled breakdown. We lead with the
        // canonical workflow order, then append any dropdown-added status actually
        // present in the data so nothing is silently dropped.
        $byStatus = [];
        foreach (self::STATUSES as $key => $meta) {
            $c = (int) ($rawCounts[$key] ?? 0);
            $byStatus[] = ['key' => $key] + $meta + ['count' => $c];
        }
        foreach ($rawCounts as $key => $c) {
            if (! array_key_exists($key, self::STATUSES)) {
                $meta = $this->statusMeta($key);
                $byStatus[] = ['key' => $meta['key'], 'label' => $meta['label'], 'level' => $meta['level'], 'help' => $meta['help'], 'count' => (int) $c];
            }
        }

        // Virtual-return vs physically-returned split, plus an "in dialogue" tally
        // (everything that is neither a virtual nor a physical return).
        $virtualReturn = (int) ($rawCounts['virtual_return'] ?? 0);
        $returned = (int) ($rawCounts['returned'] ?? 0);
        $inDialogue = max(0, $total - $virtualReturn - $returned);

        // Top origin places and claimant communities (cheap GROUP BY COUNT, top N).
        $byOrigin = $this->topGroup('origin_place', $topOrigins, 'place');
        $byCommunity = $this->topGroup('claimant_community', $topOrigins, 'community');

        // Recent activity tail - the only individual-row read, bounded to a handful
        // and decorated so each links to its /virtual-return/{id} page.
        $recent = [];
        if ($recentLimit > 0) {
            try {
                $rows = DB::table(self::TABLE)
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->limit($recentLimit)
                    ->get();
                foreach ($rows as $row) {
                    $recent[] = $this->decorate($row);
                }
            } catch (\Throwable $e) {
                Log::info('[repatriation] dashboard recent failed: '.$e->getMessage());
            }
        }

        return [
            'available' => true,
            'total' => (int) $total,
            'by_status' => $byStatus,
            'raw_status_counts' => $rawCounts,
            'by_origin' => $byOrigin,
            'by_community' => $byCommunity,
            'virtual_return' => $virtualReturn,
            'returned' => $returned,
            'in_dialogue' => $inDialogue,
            'recent' => $recent,
        ];
    }

    /**
     * Cheap "top N by COUNT" over one non-empty VARCHAR column. Read-only,
     * Schema-guarded by the caller (available()), and wrapped so any failure
     * degrades to an empty list. The returned rows carry the value under $valueKey
     * plus its count.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function topGroup(string $column, int $limit, string $valueKey): array
    {
        if ($limit <= 0) {
            return [];
        }

        try {
            $rows = DB::table(self::TABLE)
                ->select($column, DB::raw('COUNT(*) as c'))
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->groupBy($column)
                ->orderByDesc('c')
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            Log::info('[repatriation] topGroup('.$column.') failed: '.$e->getMessage());

            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $value = (string) ($row->{$column} ?? '');
            if ($value === '') {
                continue;
            }
            $out[] = [$valueKey => $value, 'count' => (int) $row->c];
        }

        return $out;
    }

    /**
     * One claim by id, decorated, or null if not found. Never throws.
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
            Log::info('[repatriation] find failed for '.$id.': '.$e->getMessage());

            return null;
        }

        if ($row === null) {
            return null;
        }

        return $this->decorate($row);
    }

    /**
     * Assemble the read-only "virtual return" context for one claim: the item
     * shown in its ORIGIN context. This is the heart of the slice - it lets the
     * public re-encounter the object in its own place and community even when
     * physical return has not happened.
     *
     * Builds from:
     *   - the claim row (origin place, claimant community, current holder,
     *     evidence summary, status),
     *   - the underlying item's catalogue title + a public record link when the
     *     item is PUBLISHED (existence-guarded; nothing for unpublished items),
     *   - the existing register's own trace of the item, when present, so the
     *     origin-vs-holding context shown matches the detection slice exactly.
     *
     * Returns null when the claim id is unknown. Never throws.
     *
     * @return array<string,mixed>|null
     */
    public function virtualReturn(int $id): ?array
    {
        $claim = $this->find($id);
        if ($claim === null) {
            return null;
        }

        $itemRef = (int) ($claim['item_ref'] ?? 0);

        // Read-only catalogue context for a PUBLISHED item only.
        $itemContext = $this->publishedItemContext($itemRef);

        // Read-only register trace for the same item, if the detection slice
        // currently traces it. Keeps the origin/holding framing consistent.
        $registerTrace = $this->registerTraceFor($itemRef);

        return [
            'claim' => $claim,
            'item' => $itemContext,        // null when unpublished / absent
            'register' => $registerTrace,  // null when not traced by the register
            'disclaimer' => self::DISCLAIMER,
        ];
    }

    /**
     * Read-only catalogue context for a PUBLISHED information object: its title,
     * slug, and a public record URL. Returns null for unpublished or absent
     * items (the virtual-return page is then origin-context-only, no record
     * link). Publication status lives in the status table (type_id=158,
     * status_id=160 == published) per the Heratio data model; the probe is fully
     * existence-guarded and degrades to null on any uncertainty.
     *
     * @return array{title:?string,slug:?string,url:?string}|null
     */
    protected function publishedItemContext(int $itemRef): ?array
    {
        if ($itemRef <= 0) {
            return null;
        }

        try {
            if (! Schema::hasTable('information_object')) {
                return null;
            }

            $exists = DB::table('information_object')->where('id', $itemRef)->exists();
            if (! $exists) {
                return null;
            }

            // Publication gate: only surface a record link for published items.
            // status.type_id = 158 is publication status; status_id 160 = published.
            $published = true;
            if (Schema::hasTable('status')) {
                $statusId = DB::table('status')
                    ->where('object_id', $itemRef)
                    ->where('type_id', 158)
                    ->orderByDesc('id')
                    ->value('status_id');
                // When a publication status exists, require it to be "published".
                if ($statusId !== null) {
                    $published = ((int) $statusId === 160);
                }
            }

            if (! $published) {
                return ['title' => null, 'slug' => null, 'url' => null, 'published' => false];
            }

            $title = null;
            if (Schema::hasTable('information_object_i18n')) {
                $title = DB::table('information_object_i18n')->where('id', $itemRef)->value('title');
                $title = $title !== null ? (string) $title : null;
            }

            $slug = null;
            if (Schema::hasTable('slug')) {
                $slug = DB::table('slug')->where('object_id', $itemRef)->value('slug');
                $slug = $slug !== null ? (string) $slug : null;
            }

            return [
                'title' => $title,
                'slug' => $slug,
                'url' => ($slug !== null && $slug !== '') ? url('/'.$slug) : null,
                'published' => true,
            ];
        } catch (\Throwable $e) {
            Log::info('[repatriation] item context failed for '.$itemRef.': '.$e->getMessage());

            return null;
        }
    }

    /**
     * If the detection register currently traces this item, return its single
     * trace record so the virtual-return page can show the same origin/holding
     * framing. Read-only; reuses DisplacedHeritageService::scan() and selects the
     * one record in memory. Null when not traced. Never throws.
     *
     * @return array<string,mixed>|null
     */
    protected function registerTraceFor(int $itemRef): ?array
    {
        if ($itemRef <= 0) {
            return null;
        }

        try {
            $report = $this->register->scan(['limit' => 0]);
            $records = is_array($report['records'] ?? null) ? $report['records'] : [];
            foreach ($records as $r) {
                if ((int) ($r['id'] ?? 0) === $itemRef) {
                    return $r;
                }
            }
        } catch (\Throwable $e) {
            Log::info('[repatriation] register trace failed for '.$itemRef.': '.$e->getMessage());
        }

        return null;
    }

    /**
     * Decorate a raw claim row: array shape + status metadata + best-effort
     * catalogue title/slug for the underlying item.
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
            Log::info('[repatriation] decorate lookup failed for '.$itemRef.': '.$e->getMessage());
        }

        return [
            'id' => (int) $row->id,
            'item_ref' => $itemRef,
            'item_title' => $title,
            'item_slug' => $slug,
            'claimant_community' => $row->claimant_community !== null ? (string) $row->claimant_community : null,
            'origin_place' => $row->origin_place !== null ? (string) $row->origin_place : null,
            'current_holder' => $row->current_holder !== null ? (string) $row->current_holder : null,
            'claim_status' => (string) ($row->claim_status ?? 'registered'),
            'status_meta' => $this->statusMeta($row->claim_status ?? 'registered'),
            'evidence_summary' => $row->evidence_summary !== null ? (string) $row->evidence_summary : null,
            'contact' => $row->contact !== null ? (string) $row->contact : null,
            'notes' => $row->notes !== null ? (string) $row->notes : null,
            'created_by' => $row->created_by !== null ? (int) $row->created_by : null,
            'created_at' => $row->created_at !== null ? (string) $row->created_at : null,
            'updated_at' => $row->updated_at !== null ? (string) $row->updated_at : null,
        ];
    }

    /**
     * Trim + length-clip a short VARCHAR value, returning null for blanks.
     */
    protected function clip($value, int $max): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
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
