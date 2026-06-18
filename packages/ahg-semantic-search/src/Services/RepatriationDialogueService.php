<?php

/**
 * RepatriationDialogueService - the two-way dialogue, status audit trail, and
 * token-permissioned shared-record access for the repatriation engine
 * (north-star heratio#1207).
 *
 * Where DisplacedHeritageService DETECTS displaced items and RepatriationClaimService
 * records a CLAIM and its virtual-return view, this service drives the conversation
 * AROUND a claim:
 *
 *   - postMessage()      - append one threaded dialogue message to a claim
 *   - messages()         - the dialogue thread for a claim (optionally only the
 *                          'shared' messages visible on the joint record)
 *   - logStatusChange()  - append one row to the append-only status audit trail
 *   - statusHistory()    - the full status history of a claim, newest first
 *   - grantAccess()      - mint a shared-record capability token for a claimant /
 *                          origin-community representative
 *   - grantsForClaim()   - the access grants on a claim (staff view)
 *   - resolveToken()     - resolve a shared-record token to its active grant + claim
 *   - revokeGrant()      - revoke a grant
 *
 * Writes ONLY to the three new tables (repatriation_claim_message,
 * repatriation_claim_status_log, repatriation_claim_access). Every reference into
 * the claim / catalogue tables is a SOFT reference (no foreign key). Every read /
 * write path is Schema::hasTable-guarded and wrapped so a missing table degrades
 * to an empty result rather than a 500.
 *
 * Sensitive subject matter. A dialogue message and a claim's status describe where
 * a conversation stands; neither asserts a legal outcome.
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
use Illuminate\Support\Str;

class RepatriationDialogueService
{
    public const MESSAGE_TABLE = 'repatriation_claim_message';

    public const STATUS_LOG_TABLE = 'repatriation_claim_status_log';

    public const ACCESS_TABLE = 'repatriation_claim_access';

    /**
     * Who can speak in a dialogue. VARCHAR in the table, never an ENUM. Each value
     * is a neutral party label - never a verdict on the claim.
     *
     * @var array<string,array{label:string,level:string}>
     */
    public const AUTHOR_ROLES = [
        'institution' => ['label' => 'Holding institution', 'level' => 'primary'],
        'claimant' => ['label' => 'Claimant / origin community', 'level' => 'success'],
        'mediator' => ['label' => 'Mediator', 'level' => 'info'],
    ];

    /**
     * Message visibility. A 'shared' message appears on the joint shared record;
     * an 'internal' message is staff-only.
     *
     * @var array<string,array{label:string,level:string}>
     */
    public const VISIBILITIES = [
        'shared' => ['label' => 'Shared on the joint record', 'level' => 'success'],
        'internal' => ['label' => 'Internal (staff only)', 'level' => 'secondary'],
    ];

    /**
     * Roles a shared-record access grant can carry. VARCHAR, never an ENUM.
     *
     * @var array<string,string>
     */
    public const GRANTEE_ROLES = [
        'claimant' => 'Claimant / origin community',
        'mediator' => 'Mediator',
        'observer' => 'Observer (read-only)',
    ];

    // ----------------------------------------------------------------- helpers

    /**
     * Is a given table present? All read/write paths gate on this so a fresh
     * (un-booted) install never fatals.
     */
    public function tableAvailable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            Log::info('[repatriation-dialogue] table probe failed for '.$table.': '.$e->getMessage());

            return false;
        }
    }

    /**
     * Normalise an author role to a known value, defaulting to 'institution'.
     */
    public function normaliseRole(?string $role): string
    {
        $role = strtolower(trim((string) $role));

        return array_key_exists($role, self::AUTHOR_ROLES) ? $role : 'institution';
    }

    /**
     * Normalise a message visibility, defaulting to 'shared'.
     */
    public function normaliseVisibility(?string $visibility): string
    {
        $visibility = strtolower(trim((string) $visibility));

        return array_key_exists($visibility, self::VISIBILITIES) ? $visibility : 'shared';
    }

    // ---------------------------------------------------------------- dialogue

    /**
     * Append one dialogue message to a claim. Returns the new id, or null on
     * failure (never throws). A claimant message posted through a shared grant
     * carries its access_id so the thread shows who spoke.
     *
     * @param  array<string,mixed>  $data
     */
    public function postMessage(int $claimId, array $data): ?int
    {
        if ($claimId <= 0 || ! $this->tableAvailable(self::MESSAGE_TABLE)) {
            return null;
        }

        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '') {
            return null;
        }

        $now = now();

        try {
            return (int) DB::table(self::MESSAGE_TABLE)->insertGetId([
                'claim_id' => $claimId,
                'author_role' => $this->normaliseRole($data['author_role'] ?? 'institution'),
                'author_name' => $this->clip($data['author_name'] ?? null, 255),
                'author_user' => isset($data['author_user']) && $data['author_user'] !== null ? (int) $data['author_user'] : null,
                'access_id' => isset($data['access_id']) && $data['access_id'] !== null ? (int) $data['access_id'] : null,
                'body' => $body,
                'visibility' => $this->normaliseVisibility($data['visibility'] ?? 'shared'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[repatriation-dialogue] postMessage failed for claim '.$claimId.': '.$e->getMessage());

            return null;
        }
    }

    /**
     * The dialogue thread for a claim, oldest-first (reading order). When
     * $sharedOnly is true, only 'shared' messages are returned (the joint record
     * surface); staff see everything. Never throws - degrades to an empty list.
     *
     * @return array<int,array<string,mixed>>
     */
    public function messages(int $claimId, bool $sharedOnly = false): array
    {
        if ($claimId <= 0 || ! $this->tableAvailable(self::MESSAGE_TABLE)) {
            return [];
        }

        try {
            $q = DB::table(self::MESSAGE_TABLE)
                ->where('claim_id', $claimId)
                ->orderBy('id');
            if ($sharedOnly) {
                $q->where('visibility', 'shared');
            }
            $rows = $q->get();
        } catch (\Throwable $e) {
            Log::info('[repatriation-dialogue] messages failed for claim '.$claimId.': '.$e->getMessage());

            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $role = $this->normaliseRole($row->author_role ?? 'institution');
            $vis = $this->normaliseVisibility($row->visibility ?? 'shared');
            $out[] = [
                'id' => (int) $row->id,
                'claim_id' => (int) $row->claim_id,
                'author_role' => $role,
                'author_role_meta' => self::AUTHOR_ROLES[$role],
                'author_name' => $row->author_name !== null ? (string) $row->author_name : null,
                'author_user' => $row->author_user !== null ? (int) $row->author_user : null,
                'access_id' => $row->access_id !== null ? (int) $row->access_id : null,
                'body' => (string) $row->body,
                'visibility' => $vis,
                'visibility_meta' => self::VISIBILITIES[$vis],
                'created_at' => $row->created_at !== null ? (string) $row->created_at : null,
            ];
        }

        return $out;
    }

    // -------------------------------------------------------------- status log

    /**
     * Append one row to the status audit trail. Best effort: returns true on
     * success, false otherwise; never throws. A no-op change (from == to) is still
     * recorded when a note is supplied (it is a meaningful "re-affirmed" event),
     * but a bare from==to with no note is skipped.
     */
    public function logStatusChange(int $claimId, ?string $from, string $to, ?string $note = null, ?int $userId = null, ?string $userName = null): bool
    {
        if ($claimId <= 0 || ! $this->tableAvailable(self::STATUS_LOG_TABLE)) {
            return false;
        }

        $from = $from !== null ? strtolower(trim($from)) : null;
        $to = strtolower(trim($to));
        $note = $this->clip($note, 1024);

        if ($from === $to && ($note === null || $note === '')) {
            return true; // nothing meaningful to record
        }

        $now = now();

        try {
            DB::table(self::STATUS_LOG_TABLE)->insert([
                'claim_id' => $claimId,
                'from_status' => $from,
                'to_status' => $to !== '' ? $to : 'registered',
                'note' => $note,
                'changed_by' => $userId,
                'changed_by_name' => $this->clip($userName, 255),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('[repatriation-dialogue] logStatusChange failed for claim '.$claimId.': '.$e->getMessage());

            return false;
        }
    }

    /**
     * The status history of a claim, newest-first. Never throws - degrades to an
     * empty list.
     *
     * @return array<int,array<string,mixed>>
     */
    public function statusHistory(int $claimId): array
    {
        if ($claimId <= 0 || ! $this->tableAvailable(self::STATUS_LOG_TABLE)) {
            return [];
        }

        try {
            $rows = DB::table(self::STATUS_LOG_TABLE)
                ->where('claim_id', $claimId)
                ->orderByDesc('id')
                ->get();
        } catch (\Throwable $e) {
            Log::info('[repatriation-dialogue] statusHistory failed for claim '.$claimId.': '.$e->getMessage());

            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row->id,
                'from_status' => $row->from_status !== null ? (string) $row->from_status : null,
                'to_status' => (string) $row->to_status,
                'note' => $row->note !== null ? (string) $row->note : null,
                'changed_by' => $row->changed_by !== null ? (int) $row->changed_by : null,
                'changed_by_name' => $row->changed_by_name !== null ? (string) $row->changed_by_name : null,
                'created_at' => $row->created_at !== null ? (string) $row->created_at : null,
            ];
        }

        return $out;
    }

    // --------------------------------------------------------- shared access

    /**
     * Mint a shared-record access grant for a claim. Returns the new grant
     * (including the freshly-generated opaque token) or null on failure. The token
     * is the capability the origin-community representative uses to open the shared
     * record; treat it as secret.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>|null
     */
    public function grantAccess(int $claimId, array $data, ?int $userId = null): ?array
    {
        if ($claimId <= 0 || ! $this->tableAvailable(self::ACCESS_TABLE)) {
            return null;
        }

        $role = strtolower(trim((string) ($data['grantee_role'] ?? 'claimant')));
        if (! array_key_exists($role, self::GRANTEE_ROLES)) {
            $role = 'claimant';
        }

        $token = $this->newToken();
        $now = now();

        try {
            $id = (int) DB::table(self::ACCESS_TABLE)->insertGetId([
                'claim_id' => $claimId,
                'token' => $token,
                'grantee_name' => $this->clip($data['grantee_name'] ?? null, 255),
                'grantee_role' => $role,
                'can_message' => $role === 'observer' ? 0 : (int) (bool) ($data['can_message'] ?? true),
                'status' => 'active',
                'expires_at' => ! empty($data['expires_at']) ? $data['expires_at'] : null,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $this->grantById($id);
        } catch (\Throwable $e) {
            Log::warning('[repatriation-dialogue] grantAccess failed for claim '.$claimId.': '.$e->getMessage());

            return null;
        }
    }

    /**
     * All access grants on a claim (staff view), newest-first. Never throws.
     *
     * @return array<int,array<string,mixed>>
     */
    public function grantsForClaim(int $claimId): array
    {
        if ($claimId <= 0 || ! $this->tableAvailable(self::ACCESS_TABLE)) {
            return [];
        }

        try {
            $rows = DB::table(self::ACCESS_TABLE)
                ->where('claim_id', $claimId)
                ->orderByDesc('id')
                ->get();
        } catch (\Throwable $e) {
            Log::info('[repatriation-dialogue] grantsForClaim failed for claim '.$claimId.': '.$e->getMessage());

            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->decorateGrant($row);
        }

        return $out;
    }

    /**
     * Resolve a shared-record token to its grant, but ONLY when the grant is
     * active and not expired. Returns null otherwise (unknown / revoked / expired).
     * Never throws.
     *
     * @return array<string,mixed>|null
     */
    public function resolveToken(?string $token): ?array
    {
        $token = trim((string) $token);
        if ($token === '' || ! $this->tableAvailable(self::ACCESS_TABLE)) {
            return null;
        }

        try {
            $row = DB::table(self::ACCESS_TABLE)->where('token', $token)->first();
        } catch (\Throwable $e) {
            Log::info('[repatriation-dialogue] resolveToken failed: '.$e->getMessage());

            return null;
        }

        if ($row === null || (string) $row->status !== 'active') {
            return null;
        }

        if ($row->expires_at !== null) {
            try {
                if (now()->greaterThan(\Illuminate\Support\Carbon::parse($row->expires_at))) {
                    return null;
                }
            } catch (\Throwable $e) {
                // unparseable expiry - treat as no expiry rather than locking out
            }
        }

        return $this->decorateGrant($row);
    }

    /**
     * Record that a grant opened the shared record (best effort, never throws).
     */
    public function touchGrant(int $grantId): void
    {
        if ($grantId <= 0 || ! $this->tableAvailable(self::ACCESS_TABLE)) {
            return;
        }
        try {
            DB::table(self::ACCESS_TABLE)->where('id', $grantId)->update(['last_seen_at' => now()]);
        } catch (\Throwable $e) {
            Log::info('[repatriation-dialogue] touchGrant failed for '.$grantId.': '.$e->getMessage());
        }
    }

    /**
     * Revoke an access grant. Returns true on success. Never throws.
     */
    public function revokeGrant(int $grantId): bool
    {
        if ($grantId <= 0 || ! $this->tableAvailable(self::ACCESS_TABLE)) {
            return false;
        }
        try {
            return DB::table(self::ACCESS_TABLE)->where('id', $grantId)->update([
                'status' => 'revoked',
                'updated_at' => now(),
            ]) >= 0;
        } catch (\Throwable $e) {
            Log::warning('[repatriation-dialogue] revokeGrant failed for '.$grantId.': '.$e->getMessage());

            return false;
        }
    }

    /**
     * One grant by id, decorated, or null.
     *
     * @return array<string,mixed>|null
     */
    public function grantById(int $grantId): ?array
    {
        if ($grantId <= 0 || ! $this->tableAvailable(self::ACCESS_TABLE)) {
            return null;
        }
        try {
            $row = DB::table(self::ACCESS_TABLE)->where('id', $grantId)->first();
        } catch (\Throwable $e) {
            Log::info('[repatriation-dialogue] grantById failed for '.$grantId.': '.$e->getMessage());

            return null;
        }

        return $row !== null ? $this->decorateGrant($row) : null;
    }

    // --------------------------------------------------------------- internals

    /**
     * @return array<string,mixed>
     */
    protected function decorateGrant(object $row): array
    {
        $role = (string) $row->grantee_role;
        $expired = false;
        if ($row->expires_at !== null) {
            try {
                $expired = now()->greaterThan(\Illuminate\Support\Carbon::parse($row->expires_at));
            } catch (\Throwable $e) {
                $expired = false;
            }
        }

        return [
            'id' => (int) $row->id,
            'claim_id' => (int) $row->claim_id,
            'token' => (string) $row->token,
            'grantee_name' => $row->grantee_name !== null ? (string) $row->grantee_name : null,
            'grantee_role' => $role,
            'grantee_role_label' => self::GRANTEE_ROLES[$role] ?? ucfirst($role),
            'can_message' => (bool) $row->can_message,
            'status' => (string) $row->status,
            'is_active' => ((string) $row->status === 'active') && ! $expired,
            'expired' => $expired,
            'expires_at' => $row->expires_at !== null ? (string) $row->expires_at : null,
            'last_seen_at' => $row->last_seen_at !== null ? (string) $row->last_seen_at : null,
            'created_at' => $row->created_at !== null ? (string) $row->created_at : null,
        ];
    }

    /**
     * Generate a fresh opaque, URL-safe capability token.
     */
    protected function newToken(): string
    {
        return Str::lower(Str::random(48));
    }

    /**
     * Trim + length-clip a short value, returning null for blanks.
     */
    protected function clip($value, int $max): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }
}
