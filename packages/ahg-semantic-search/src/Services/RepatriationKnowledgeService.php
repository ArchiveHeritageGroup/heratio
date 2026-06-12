<?php

/**
 * RepatriationKnowledgeService - community KNOWLEDGE contributions about a
 * displaced item / repatriation claim (north-star heratio#1207: the repatriation
 * engine).
 *
 * The detection slice (DisplacedHeritageService) traces, read-only, which
 * catalogue items have a recorded origin that differs from where they are held.
 * The claim slice (RepatriationClaimService / displaced_heritage_claim) layers a
 * curated CLAIM and a public virtual-return view on top. This slice adds the
 * COMMUNITY VOICE: a member of a source community, a descendant, a researcher or
 * any knowledgeable person can contribute KNOWLEDGE about a displaced object -
 * oral history, provenance knowledge, a correction to the record, a pointer to
 * the source community, or another note. The contribution is MODERATED and is
 * shown publicly only once an admin approves it, mirroring the language-revival
 * glossary / transcription flow exactly:
 *
 *   - resolveClaim()       - read READ-ONLY context for one repatriation claim
 *                            (origin place, claimant community, the underlying
 *                            item title / slug) so a contributor knows what they
 *                            are speaking about.
 *   - approvedForClaim()   - the APPROVED community contributions on one claim
 *                            (public read, for the virtual-return page).
 *   - contribute()         - lodge a new contribution; it lands 'pending' and is
 *                            NOT shown publicly until an admin approves it.
 *   - moderationQueue()    - admin queue of contributions in one moderation
 *                            state (mirrors the glossary / transcription flow).
 *   - moderationCounts()   - per-state counts for the admin chips.
 *   - moderate()           - approve / reject one contribution.
 *
 * It writes to ONE additive, soft-keyed table only:
 * repatriation_knowledge_contribution. It never ALTERs or writes any existing
 * table, and it reads the claim / catalogue strictly READ-ONLY behind
 * Schema::hasTable probes via RepatriationClaimService.
 *
 * Sensitive subject matter, handled with care and without partisanship.
 * Knowledge about a displaced object belongs to its communities. A contribution
 * is a documented piece of a DIALOGUE recorded in a spirit of transparency and
 * respect - it is NOT a legal determination of origin, ownership or wrongful
 * removal. The contributor is credited ONLY where they explicitly consent to be
 * named; otherwise the contribution is shown anonymously.
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

class RepatriationKnowledgeService
{
    /** The single table this service writes to. */
    public const TABLE = 'repatriation_knowledge_contribution';

    /** Hard caps so the public read-only surface stays cheap + bounded. */
    protected const MAX_PER_CLAIM = 100;

    protected const MAX_QUEUE = 300;

    /** Longest a contribution body we store (defensive cap; column is MEDIUMTEXT). */
    protected const MAX_BODY_CHARS = 60000;

    /**
     * Canonical knowledge kinds. VARCHAR-backed (Dropdown-Manager idiom), never an
     * ENUM, so the set can grow without a schema change. Each blurb frames the
     * kind of community knowledge invited, respectfully and without partisanship.
     *
     * @var array<string,array{label:string,icon:string,blurb:string}>
     */
    public const CONTRIBUTION_TYPES = [
        'provenance' => [
            'label' => 'Provenance knowledge',
            'icon'  => 'fa-route',
            'blurb' => 'Documented history of how, when and by whom this object moved.',
        ],
        'oral_history' => [
            'label' => 'Oral history',
            'icon'  => 'fa-comments',
            'blurb' => 'Testimony, memory or story connected to this object.',
        ],
        'correction' => [
            'label' => 'Correction',
            'icon'  => 'fa-pen-to-square',
            'blurb' => 'Correct something the record currently gets wrong.',
        ],
        'source_community' => [
            'label' => 'Source community',
            'icon'  => 'fa-people-group',
            'blurb' => 'Identify, or point to, the community this object belongs with.',
        ],
        'other' => [
            'label' => 'Other knowledge',
            'icon'  => 'fa-circle-info',
            'blurb' => 'Any other knowledge that helps understand this object.',
        ],
    ];

    /**
     * Canonical moderation states. VARCHAR-backed, never an ENUM. Only 'approved'
     * contributions are shown on the public surface. Mirrors the glossary flow.
     *
     * @var array<string,array{label:string,level:string}>
     */
    public const MODERATION_STATUSES = [
        'pending'  => ['label' => 'Pending review', 'level' => 'secondary'],
        'approved' => ['label' => 'Approved', 'level' => 'success'],
        'rejected' => ['label' => 'Not published', 'level' => 'light'],
    ];

    /**
     * Respectful, non-partisan, jurisdiction-neutral framing surfaced wherever
     * community knowledge is shown or invited. Knowledge belongs to its
     * communities; a contribution records dialogue, not a determination.
     */
    public const DISCLAIMER = 'Knowledge about a displaced object belongs to the communities connected to it. '
        .'What is shared here - oral history, provenance knowledge, corrections and links to source communities - '
        .'is contributed by people, reviewed before it appears, and held as a record of an open dialogue. A '
        .'contribution is NOT a legal determination of origin, ownership or wrongful removal, and is not advice. '
        .'Contributors are credited by name only where they ask to be; otherwise their contribution appears '
        .'anonymously. Origin, ownership and lawful-transfer history are matters for the relevant communities, '
        .'holding institutions and qualified staff to assess together, case by case, under the applicable law.';

    /** Read-only access to the claim / item context. Reuse, do not duplicate. */
    protected RepatriationClaimService $claims;

    public function __construct(?RepatriationClaimService $claims = null)
    {
        $this->claims = $claims ?? new RepatriationClaimService;
    }

    // ---------------------------------------------------------------------
    // Table availability + small helpers
    // ---------------------------------------------------------------------

    /**
     * Is the contributions table present? All read/write paths gate on this so a
     * fresh (un-booted) install never fatals.
     */
    public function available(): bool
    {
        try {
            return Schema::hasTable(self::TABLE);
        } catch (\Throwable $e) {
            Log::info('[repatriation-knowledge] table probe failed: '.$e->getMessage());

            return false;
        }
    }

    /** Normalise a contribution-type code, falling back to 'other'. */
    public function sanitiseType(?string $type): string
    {
        $type = strtolower(trim((string) $type));

        return array_key_exists($type, self::CONTRIBUTION_TYPES) ? $type : 'other';
    }

    /**
     * Human metadata for a contribution-type value, with a safe fallback for any
     * value not in the canonical map (e.g. a Dropdown-Manager addition).
     *
     * @return array{key:string,label:string,icon:string,blurb:string}
     */
    public function typeMeta(?string $type): array
    {
        $key = strtolower(trim((string) $type));
        if (isset(self::CONTRIBUTION_TYPES[$key])) {
            return ['key' => $key] + self::CONTRIBUTION_TYPES[$key];
        }

        $label = $key === '' ? 'Other knowledge' : ucwords(str_replace('_', ' ', $key));

        return ['key' => $key === '' ? 'other' : $key, 'label' => $label, 'icon' => 'fa-circle-info', 'blurb' => ''];
    }

    // ---------------------------------------------------------------------
    // Read-only claim context (reuse the claim service)
    // ---------------------------------------------------------------------

    /**
     * Read READ-ONLY context for one repatriation claim so a contributor knows
     * what they are speaking about: the claim's origin place, claimant community,
     * status and the underlying item's title / slug. Returns null when the claim
     * id is unknown (the controller renders a clear "not available" state).
     * Never throws.
     *
     * @return array<string,mixed>|null
     */
    public function resolveClaim(int $claimId): ?array
    {
        if ($claimId <= 0) {
            return null;
        }

        try {
            return $this->claims->find($claimId);
        } catch (\Throwable $e) {
            Log::info('[repatriation-knowledge] resolveClaim failed for '.$claimId.': '.$e->getMessage());

            return null;
        }
    }

    // ---------------------------------------------------------------------
    // Public reads (APPROVED only)
    // ---------------------------------------------------------------------

    /**
     * APPROVED community knowledge contributions on one claim, newest first.
     * Public read for the virtual-return page. Read-only over the new table;
     * never throws.
     *
     * @return array<int,array<string,mixed>>
     */
    public function approvedForClaim(int $claimId): array
    {
        if ($claimId <= 0 || ! $this->available()) {
            return [];
        }

        try {
            $rows = DB::table(self::TABLE)
                ->where('claim_id', $claimId)
                ->where('moderation_status', 'approved')
                ->orderByDesc('id')
                ->limit(self::MAX_PER_CLAIM)
                ->get();
        } catch (\Throwable $e) {
            Log::info('[repatriation-knowledge] approvedForClaim failed for '.$claimId.': '.$e->getMessage());

            return [];
        }

        return array_map([$this, 'decorate'], $rows->all());
    }

    /**
     * APPROVED community knowledge contributions on one underlying catalogue item,
     * newest first. Public read for any item-centred surface. Read-only; never
     * throws.
     *
     * @return array<int,array<string,mixed>>
     */
    public function approvedForItem(int $itemRef): array
    {
        if ($itemRef <= 0 || ! $this->available()) {
            return [];
        }

        try {
            $rows = DB::table(self::TABLE)
                ->where('item_ref', $itemRef)
                ->where('moderation_status', 'approved')
                ->orderByDesc('id')
                ->limit(self::MAX_PER_CLAIM)
                ->get();
        } catch (\Throwable $e) {
            Log::info('[repatriation-knowledge] approvedForItem failed for '.$itemRef.': '.$e->getMessage());

            return [];
        }

        return array_map([$this, 'decorate'], $rows->all());
    }

    /** Count of APPROVED contributions on one claim (for a light badge). */
    public function approvedCountForClaim(int $claimId): int
    {
        if ($claimId <= 0 || ! $this->available()) {
            return 0;
        }

        try {
            return (int) DB::table(self::TABLE)
                ->where('claim_id', $claimId)
                ->where('moderation_status', 'approved')
                ->count();
        } catch (\Throwable $e) {
            Log::info('[repatriation-knowledge] approvedCountForClaim failed for '.$claimId.': '.$e->getMessage());

            return 0;
        }
    }

    // ---------------------------------------------------------------------
    // Contribution (the one write path)
    // ---------------------------------------------------------------------

    /**
     * Lodge a new community knowledge contribution. It lands as 'pending' and is
     * NOT shown publicly until an admin approves it. Writes one row to the new
     * table only. Returns the new id, or null on failure (never throws).
     *
     * The contribution is only accepted against a real claim: an unresolved
     * claim_id is rejected so the public surface can never be flooded with orphan
     * rows. The underlying item_ref is taken from the claim, read-only, so each
     * contribution is anchored to both the claim and its catalogue item.
     *
     * @param  array<string,mixed>  $data
     */
    public function contribute(array $data, ?int $userId = null): ?int
    {
        if (! $this->available()) {
            return null;
        }

        $claimId = (int) ($data['claim_id'] ?? 0);
        $claim = $claimId > 0 ? $this->resolveClaim($claimId) : null;
        if ($claim === null) {
            // Only accept contributions against a real claim.
            return null;
        }

        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '') {
            return null;
        }

        $name = $this->clipText($data['contributor_name'] ?? null, 255);
        $consent = ! empty($data['credit_consent']) && $name !== null;

        $now = now();
        $payload = [
            'claim_id' => $claimId,
            'item_ref' => (int) ($claim['item_ref'] ?? 0) ?: null,
            'contribution_type' => $this->sanitiseType($data['contribution_type'] ?? null),
            'body' => mb_substr($body, 0, self::MAX_BODY_CHARS),
            'source' => $this->clipText($data['source'] ?? null, 512),
            'contributor_name' => $consent ? $name : null,
            'credit_consent' => $consent ? 1 : 0,
            'contributed_by' => $userId,
            'moderation_status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        try {
            return (int) DB::table(self::TABLE)->insertGetId($payload);
        } catch (\Throwable $e) {
            Log::warning('[repatriation-knowledge] contribute failed for claim '.$claimId.': '.$e->getMessage());

            return null;
        }
    }

    // ---------------------------------------------------------------------
    // Admin moderation (mirrors the glossary / transcription flow)
    // ---------------------------------------------------------------------

    /**
     * Admin moderation queue: contributions in one moderation state (default
     * 'pending'), newest first. Each row carries a light, read-only claim label so
     * the moderator has context. Read-only over the new table; never throws.
     *
     * @return array<int,array<string,mixed>>
     */
    public function moderationQueue(string $status = 'pending'): array
    {
        if (! $this->available()) {
            return [];
        }

        $status = strtolower(trim($status));
        if (! array_key_exists($status, self::MODERATION_STATUSES)) {
            $status = 'pending';
        }

        try {
            $rows = DB::table(self::TABLE)
                ->where('moderation_status', $status)
                ->orderByDesc('id')
                ->limit(self::MAX_QUEUE)
                ->get();
        } catch (\Throwable $e) {
            Log::info('[repatriation-knowledge] moderationQueue read failed: '.$e->getMessage());

            return [];
        }

        $out = array_map([$this, 'decorate'], $rows->all());
        foreach ($out as &$row) {
            $row['claim'] = $this->claimLabel((int) ($row['claim_id'] ?? 0));
        }
        unset($row);

        return $out;
    }

    /**
     * Counts of contributions per moderation state (for the admin chips).
     *
     * @return array<string,int>
     */
    public function moderationCounts(): array
    {
        if (! $this->available()) {
            return [];
        }

        try {
            $rows = DB::table(self::TABLE)
                ->select('moderation_status', DB::raw('COUNT(*) as c'))
                ->groupBy('moderation_status')
                ->get();
        } catch (\Throwable $e) {
            Log::info('[repatriation-knowledge] moderationCounts failed: '.$e->getMessage());

            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r->moderation_status] = (int) $r->c;
        }

        return $out;
    }

    /**
     * Set the moderation state of one contribution. Returns true on success.
     */
    public function moderate(int $id, string $status, ?int $moderatorId = null): bool
    {
        if (! $this->available() || $id <= 0) {
            return false;
        }

        $status = strtolower(trim($status));
        if (! array_key_exists($status, self::MODERATION_STATUSES)) {
            return false;
        }

        try {
            return DB::table(self::TABLE)->where('id', $id)->update([
                'moderation_status' => $status,
                'moderated_by' => $moderatorId,
                'moderated_at' => now(),
                'updated_at' => now(),
            ]) >= 0;
        } catch (\Throwable $e) {
            Log::warning('[repatriation-knowledge] moderate failed for '.$id.': '.$e->getMessage());

            return false;
        }
    }

    // ---------------------------------------------------------------------
    // Internal helpers
    // ---------------------------------------------------------------------

    /**
     * Read-only, cheap label for a claim (its underlying item title + slug + the
     * origin place) for the moderation table. Returns a soft array even when the
     * claim is gone, so the queue never breaks.
     *
     * @return array{id:int,title:string,slug:?string,origin_place:?string}
     */
    protected function claimLabel(int $claimId): array
    {
        $fallback = ['id' => $claimId, 'title' => '#'.$claimId, 'slug' => null, 'origin_place' => null];
        if ($claimId <= 0) {
            return $fallback;
        }

        try {
            $claim = $this->claims->find($claimId);
        } catch (\Throwable $e) {
            return $fallback;
        }

        if ($claim === null) {
            return $fallback;
        }

        $title = $claim['item_title'] ?? null;

        return [
            'id' => $claimId,
            'title' => ($title !== null && $title !== '') ? (string) $title : ('#'.$claimId),
            'slug' => isset($claim['item_slug']) && $claim['item_slug'] !== null ? (string) $claim['item_slug'] : null,
            'origin_place' => isset($claim['origin_place']) && $claim['origin_place'] !== null ? (string) $claim['origin_place'] : null,
        ];
    }

    /**
     * Decorate a raw contribution row into a view-friendly array, with display
     * metadata for its type and moderation state. The contributor name is only
     * surfaced where they consented to be credited.
     *
     * @return array<string,mixed>
     */
    protected function decorate(object $row): array
    {
        $status = (string) ($row->moderation_status ?? 'pending');
        $statusMeta = self::MODERATION_STATUSES[$status] ?? ['label' => ucfirst($status), 'level' => 'secondary'];

        $type = (string) ($row->contribution_type ?? 'other');

        $consent = ! empty($row->credit_consent);
        $name = $row->contributor_name !== null ? (string) $row->contributor_name : null;

        return [
            'id' => (int) $row->id,
            'claim_id' => $row->claim_id !== null ? (int) $row->claim_id : null,
            'item_ref' => $row->item_ref !== null ? (int) $row->item_ref : null,
            'contribution_type' => $type,
            'type_meta' => $this->typeMeta($type),
            'body' => (string) ($row->body ?? ''),
            'source' => $row->source !== null ? (string) $row->source : null,
            'moderation_status' => $status,
            'status_meta' => ['key' => $status] + $statusMeta,
            // Only surface a name where the contributor consented to be credited.
            'credit_consent' => $consent,
            'contributor_name' => ($consent && $name !== null && $name !== '') ? $name : null,
            'created_at' => $row->created_at !== null ? (string) $row->created_at : null,
        ];
    }

    /** Trim a long value, returning null for blanks. Optional max length. */
    protected function clipText($value, int $max = 0): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return ($max > 0 && mb_strlen($value) > $max) ? mb_substr($value, 0, $max) : $value;
    }
}
