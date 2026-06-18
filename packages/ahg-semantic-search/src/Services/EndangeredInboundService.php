<?php

/**
 * EndangeredInboundService - Heratio
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

namespace AhgSemanticSearch\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * #1205 PUSH-MODEL peer inbound. The endangered network was pull-only - this
 * instance fetched each peer's register on demand. This service is the inbound
 * half: a federation peer can PUSH an at-risk flag to us (POST /api/v1/endangered/inbound),
 * and we store it for staff review rather than acting on it blind.
 *
 * Trust + safety (the inbound endpoint enforces these BEFORE calling ingest):
 *   - the peer must be a KNOWN federation member/peer (by base_url);
 *   - the push is Ed25519-signature-verified via the T1 FederationVerifier
 *     (TOFU-pinned), and the require-verified policy is honoured;
 *   - the surface gate ('endangered') is applied via FederationGovernance.
 *
 * Stored pushes land review_status = 'pending'. They do NOT touch this instance's
 * own at-risk register and are NOT shown publicly until a curator ACCEPTS them;
 * accepted pushes then surface on the cross-institution federated board, tagged
 * as peer-pushed. Everything here is FAIL-SOFT: a missing table or any failure is
 * swallowed (the push is simply not recorded) and never throws / never 500s.
 */
class EndangeredInboundService
{
    public const TABLE = 'endangered_inbound_flag';

    protected EndangeredHeritageService $local;

    public function __construct(?EndangeredHeritageService $local = null)
    {
        $this->local = $local ?? new EndangeredHeritageService;
    }

    public function available(): bool
    {
        try {
            return Schema::hasTable(self::TABLE);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Record one inbound peer push. Upserts on (peer base_url + reference) so a
     * peer re-pushing the same item updates its pending row rather than spamming
     * duplicates; a row already reviewed (accepted/declined) keeps its decision
     * but refreshes the pushed payload. Returns a receipt, or null on failure.
     *
     * @param  array<string,mixed>  $payload      the pushed item (the /api/v1/endangered item shape)
     * @param  array{verified?:bool,key_fingerprint?:?string}  $verification  T1 verdict
     * @return array{id:int,review_status:string,dedupe_key:string}|null
     */
    public function ingest(array $payload, string $peerBaseUrl, string $peerName = '', array $verification = []): ?array
    {
        if (! $this->available()) {
            return null;
        }

        $reference = trim((string) ($payload['item_ref'] ?? $payload['reference'] ?? ''));
        if ($reference === '' || trim($peerBaseUrl) === '') {
            return null; // nothing to attach the flag to
        }

        $dedupe = hash('sha256', strtolower(trim($peerBaseUrl)).'|'.$reference);
        $now = now();

        $values = [
            'source_peer_base_url' => $this->clip($peerBaseUrl, 1024),
            'source_peer_name'     => $this->clip($peerName !== '' ? $peerName : null, 512),
            'reference'            => $this->clip($reference, 512),
            'title'                => $this->clip($payload['title'] ?? null, 1024),
            'risk'                 => $this->local->normaliseRisk($payload['risk_category'] ?? $payload['risk'] ?? null),
            'urgency'              => $this->local->normaliseUrgency($payload['urgency'] ?? null),
            'capture_status'       => $this->local->normaliseCaptureStatus($payload['capture_status'] ?? null),
            'reason'               => $this->clipText($payload['reason'] ?? null),
            'catalogue_url'        => $this->clip($payload['catalogue_url'] ?? null, 1024),
            'payload'              => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'peer_verified'        => (bool) ($verification['verified'] ?? false),
            'key_fingerprint'      => $this->clip($verification['key_fingerprint'] ?? null, 128),
            'received_at'          => $now,
            'updated_at'           => $now,
        ];

        try {
            $existing = DB::table(self::TABLE)->where('dedupe_key', $dedupe)->first();
            if ($existing !== null) {
                // Keep the prior review decision + received_at; refresh the payload.
                unset($values['received_at']);
                DB::table(self::TABLE)->where('dedupe_key', $dedupe)->update($values);
                $id = (int) $existing->id;
                $status = (string) $existing->review_status;
            } else {
                $id = (int) DB::table(self::TABLE)->insertGetId(
                    $values + ['dedupe_key' => $dedupe, 'review_status' => 'pending', 'created_at' => $now]
                );
                $status = 'pending';
                $this->notifyStaff($id, $values['title'] ?? $reference, $peerName ?: $peerBaseUrl);
            }

            return ['id' => $id, 'review_status' => $status, 'dedupe_key' => $dedupe];
        } catch (\Throwable $e) {
            Log::warning('[endangered-inbound] ingest failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Pending inbound pushes for the staff review queue, newest first.
     *
     * @return array<int,object>
     */
    public function pending(int $limit = 200): array
    {
        if (! $this->available()) {
            return [];
        }
        try {
            return DB::table(self::TABLE)
                ->where('review_status', 'pending')
                ->orderByDesc('received_at')
                ->limit(max(1, $limit))
                ->get()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Counts per review_status, for the queue badges. @return array<string,int> */
    public function statusCounts(): array
    {
        if (! $this->available()) {
            return [];
        }
        try {
            $out = [];
            foreach (DB::table(self::TABLE)->select('review_status', DB::raw('COUNT(*) c'))->groupBy('review_status')->get() as $r) {
                $out[(string) $r->review_status] = (int) $r->c;
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Record a curator decision on one pushed flag. $decision is 'accepted' or
     * 'declined'. Returns true on success. Never throws.
     */
    public function review(int $id, string $decision, ?int $userId = null): bool
    {
        if (! $this->available() || $id <= 0) {
            return false;
        }
        $decision = $decision === 'accepted' ? 'accepted' : 'declined';
        try {
            return DB::table(self::TABLE)->where('id', $id)->update([
                'review_status' => $decision,
                'reviewed_by'   => $userId,
                'reviewed_at'   => now(),
                'updated_at'    => now(),
            ]) >= 0;
        } catch (\Throwable $e) {
            Log::warning('[endangered-inbound] review failed for '.$id.': '.$e->getMessage());

            return false;
        }
    }

    /**
     * ACCEPTED inbound pushes, shaped like federated-board rows so the live
     * cross-institution board can blend them in (tagged peer-pushed). Read-only,
     * fail-soft.
     *
     * @return array<int,array<string,mixed>>
     */
    public function acceptedForBoard(int $limit = 200): array
    {
        if (! $this->available()) {
            return [];
        }
        try {
            $rows = DB::table(self::TABLE)
                ->where('review_status', 'accepted')
                ->orderByDesc('reviewed_at')
                ->limit(max(1, $limit))
                ->get();

            $out = [];
            foreach ($rows as $r) {
                $out[] = [
                    'item_ref'      => (string) $r->reference,
                    'title'         => $r->title,
                    'risk_category' => (string) $r->risk,
                    'urgency'       => (string) $r->urgency,
                    'capture_status' => (string) ($r->capture_status ?? 'flagged'),
                    'reason'        => $r->reason,
                    'catalogue_url' => $r->catalogue_url,
                    'source_peer'   => [
                        'name'     => (string) ($r->source_peer_name ?? $r->source_peer_base_url),
                        'base_url' => (string) $r->source_peer_base_url,
                        'verified' => (bool) $r->peer_verified,
                        'key_fingerprint' => $r->key_fingerprint,
                    ],
                    'pushed'        => true,
                    'flagged_at'    => $r->received_at,
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Notify admins that a peer pushed an at-risk flag awaiting review. Fail-soft. */
    protected function notifyStaff(int $id, string $title, string $peer): void
    {
        try {
            $svcClass = '\\AhgCore\\Services\\NotificationService';
            if (! class_exists($svcClass) || ! Schema::hasTable('ahg_notification')) {
                return;
            }
            (new $svcClass)->notifyAdmins(
                'endangered_inbound',
                'Peer pushed an at-risk flag',
                $peer.' flagged "'.$title.'" as endangered. Review it in the inbound queue.',
                url('/endangered/inbound'),
                self::TABLE,
                $id
            );
        } catch (\Throwable $e) {
            Log::info('[endangered-inbound] staff notify skipped: '.$e->getMessage());
        }
    }

    private function clip(?string $v, int $max): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = trim($v);

        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    private function clipText(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = trim($v);

        return $v === '' ? null : mb_substr($v, 0, 20000);
    }
}
