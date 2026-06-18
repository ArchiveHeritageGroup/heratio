<?php

/**
 * RepatriationNotifier - Heratio
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

use AhgCore\Services\AhgSettingsService;
use AhgCore\Services\NotificationService;
use AhgSemanticSearch\Mail\RepatriationClaimRegisteredMail;
use AhgSemanticSearch\Mail\RepatriationClaimStatusMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * #1207 repatriation claim notifications. Surfaces the two events that matter to
 * BOTH SIDES of a repatriation dialogue:
 *
 *   - a claim is LODGED   -> staff/admins get an in-app notification (the holding
 *                            institution side); the claimant gets an email receipt
 *                            (the community side), when a contact email is given.
 *   - a claim's STATUS    -> staff/admins + the staff member who logged the claim
 *     CHANGES               get an in-app notification; the claimant gets an email
 *                            update naming the from -> to transition.
 *
 * Every path is FAIL-SOFT: a missing notification table, a mail-transport error,
 * or an absent claimant email never blocks the claim write that triggered it, and
 * never throws. The whole feature is gated on the `repatriation_notifications`
 * setting (default on). Tone is deliberately neutral: a claim is a documented
 * request and its status, never a legal determination (see
 * RepatriationClaimService::DISCLAIMER, carried in the emails).
 */
class RepatriationNotifier
{
    /** ahg_notification.type for every claim notification (one filterable bucket). */
    private const NOTIF_TYPE = 'repatriation_claim';

    /**
     * Whether claim notifications are enabled. Default on; degrades to on if the
     * settings store is unavailable (the individual sends are still fail-soft).
     */
    public function enabled(): bool
    {
        try {
            return AhgSettingsService::getBool('repatriation_notifications', true);
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * A new claim has been registered. Notify staff in-app and email the claimant
     * a receipt (when a contact email is present).
     */
    public function claimRegistered(int $claimId, ?int $actorUserId = null, ?string $actorName = null): void
    {
        if (! $this->enabled()) {
            return;
        }
        $claim = $this->claim($claimId);
        if ($claim === null) {
            return;
        }

        $community = $this->community($claim);
        $title = 'New repatriation claim registered';
        $message = "A repatriation claim has been registered by {$community}"
            . (! empty($claim->current_holder) ? " regarding an object held by {$claim->current_holder}" : '')
            . '. Open the claim to review and respond.';
        $link = $this->claimLink($claimId);

        // In-app -> staff / admins (the holding-institution side).
        $this->tryInApp(fn (NotificationService $n) => $n->notifyAdmins(
            self::NOTIF_TYPE, $title, $message, $link, RepatriationClaimService::TABLE, $claimId, $actorUserId, $actorName
        ));

        // Email -> the claimant community (their side), if a contact email is given.
        $email = $this->emailFrom($claim->contact ?? null);
        if ($email !== null) {
            $this->trySend($email, new RepatriationClaimRegisteredMail($claim), 'registered');
        }
    }

    /**
     * A claim's status has actually changed. Notify staff (and the logger) in-app
     * and email the claimant the transition.
     */
    public function claimStatusChanged(int $claimId, ?string $from, string $to, ?int $actorUserId = null, ?string $actorName = null): void
    {
        if (! $this->enabled()) {
            return;
        }
        $claim = $this->claim($claimId);
        if ($claim === null) {
            return;
        }

        $fromLabel = $this->statusLabel($from);
        $toLabel = $this->statusLabel($to);
        $community = $this->community($claim);
        $title = 'Repatriation claim status updated';
        $message = "The claim by {$community} moved from \"{$fromLabel}\" to \"{$toLabel}\".";
        $link = $this->claimLink($claimId);

        // In-app -> staff / admins, plus the staff member who originally logged the
        // claim (so they see movement even if they are not an administrator).
        $this->tryInApp(function (NotificationService $n) use ($title, $message, $link, $claimId, $actorUserId, $actorName, $claim) {
            $n->notifyAdmins(self::NOTIF_TYPE, $title, $message, $link, RepatriationClaimService::TABLE, $claimId, $actorUserId, $actorName);

            $createdBy = (int) ($claim->created_by ?? 0);
            if ($createdBy > 0 && $createdBy !== (int) $actorUserId) {
                $n->notify($createdBy, self::NOTIF_TYPE, $title, $message, $link, RepatriationClaimService::TABLE, $claimId, $actorUserId, $actorName);
            }
        });

        // Email -> the claimant community.
        $email = $this->emailFrom($claim->contact ?? null);
        if ($email !== null) {
            $this->trySend($email, new RepatriationClaimStatusMail($claim, $fromLabel, $toLabel), 'status');
        }
    }

    // ─── helpers ──────────────────────────────────────────────────────────────

    /** Fetch the claim row (read-only, fail-soft) or null. */
    private function claim(int $id): ?object
    {
        if ($id <= 0) {
            return null;
        }
        try {
            if (! Schema::hasTable(RepatriationClaimService::TABLE)) {
                return null;
            }

            return DB::table(RepatriationClaimService::TABLE)->where('id', $id)->first();
        } catch (\Throwable $e) {
            Log::info('[repatriation] notifier claim read failed for '.$id.': '.$e->getMessage());

            return null;
        }
    }

    /** Human label for a claimant community, with a neutral fallback. */
    private function community(object $claim): string
    {
        $c = trim((string) ($claim->claimant_community ?? ''));

        return $c !== '' ? $c : 'an unspecified community';
    }

    /** Human label for a status value, reusing the canonical workflow map. */
    private function statusLabel(?string $status): string
    {
        $key = strtolower(trim((string) $status));
        if (isset(RepatriationClaimService::STATUSES[$key]['label'])) {
            return RepatriationClaimService::STATUSES[$key]['label'];
        }

        return $key === '' ? 'Registered' : ucwords(str_replace('_', ' ', $key));
    }

    /** Deep link to the staff claim workspace; falls back to the register. */
    private function claimLink(int $id): ?string
    {
        try {
            return route('repatriation.claims.edit', ['id' => $id]);
        } catch (\Throwable $e) {
            return '/repatriation/claims';
        }
    }

    /**
     * Extract the first email address from a free-text contact field (it may hold
     * a name, phone, postal address or email). Returns null when none is present.
     */
    private function emailFrom(?string $contact): ?string
    {
        $contact = trim((string) $contact);
        if ($contact === '') {
            return null;
        }
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $contact, $m)) {
            return $m[0];
        }

        return null;
    }

    /** Run an in-app notification closure fail-soft (skips when the table is absent). */
    private function tryInApp(callable $fn): void
    {
        try {
            if (! Schema::hasTable('ahg_notification')) {
                return;
            }
            $fn(new NotificationService);
        } catch (\Throwable $e) {
            Log::info('[repatriation] in-app notify skipped: '.$e->getMessage());
        }
    }

    /** Queue a claimant email fail-soft - delivery failure never blocks the claim. */
    private function trySend(string $email, $mailable, string $context): void
    {
        try {
            Mail::to($email)->queue($mailable);
        } catch (\Throwable $e) {
            Log::warning('[repatriation] claim mail send failed', [
                'context' => $context,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
