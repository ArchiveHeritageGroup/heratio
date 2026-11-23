<?php

/**
 * LibraryNoticeChannelService - patron channel preference and SMS readiness
 * for overdue notices (#1474 item 2).
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Services;

use AhgCore\Services\AhgSettingsService;
use AhgSecurityClearance\Services\HttpSmsGateway;
use AhgSecurityClearance\Services\NullSmsGateway;
use AhgSecurityClearance\Services\OtpService;
use AhgSecurityClearance\Services\SmsGatewayInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LibraryNoticeChannelService
{
    /** Channels this code can deliver. The options offered come from ahg_dropdown. */
    public const SUPPORTED = ['email', 'sms', 'print'];

    /** @return array<string,string> code => label, active dropdown values only */
    public function channelOptions(): array
    {
        return $this->dropdown('library_notice_channel')
            ->only(self::SUPPORTED)
            ->all();
    }

    /** @return array<string,string> code => label */
    public function statusLabels(): array
    {
        return $this->dropdown('library_notice_status')->all();
    }

    public function preferenceFor(int $patronId): ?string
    {
        if (!Schema::hasTable('library_patron_notice_preference')) {
            return null;
        }
        return DB::table('library_patron_notice_preference')->where('patron_id', $patronId)->value('channel');
    }

    /** A null channel clears the preference, returning the patron to the default. */
    public function setPreference(int $patronId, ?string $channel, ?int $userId): void
    {
        if ($channel === null) {
            DB::table('library_patron_notice_preference')->where('patron_id', $patronId)->delete();
            return;
        }
        DB::table('library_patron_notice_preference')->updateOrInsert(
            ['patron_id' => $patronId],
            ['channel' => $channel, 'updated_by' => $userId, 'updated_at' => now()]
        );
    }

    /**
     * The gateway that can really deliver, or null. NullSmsGateway reports
     * success without sending anything, so it must never count as delivery.
     */
    public function deliverableSmsGateway(): ?SmsGatewayInterface
    {
        if (!class_exists(OtpService::class)) {
            return null;
        }
        $gateway = app(OtpService::class)->smsGateway();
        if ($gateway instanceof NullSmsGateway) {
            return null;
        }
        if ($gateway instanceof HttpSmsGateway && (string) (AhgSettingsService::get('sms_http_endpoint') ?? '') === '') {
            return null;
        }
        return $gateway;
    }

    /** Strips display punctuation; returns null if this cannot be a phone number. */
    public function normalisePhone(?string $phone): ?string
    {
        $clean = preg_replace('/[\s\-().]/', '', (string) $phone);
        return preg_match('/^\+?[0-9]{8,15}$/', $clean) ? $clean : null;
    }

    private function dropdown(string $taxonomy): \Illuminate\Support\Collection
    {
        if (!Schema::hasTable('ahg_dropdown')) {
            return collect();
        }
        return DB::table('ahg_dropdown')
            ->where('taxonomy', $taxonomy)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->pluck('label', 'code');
    }
}
