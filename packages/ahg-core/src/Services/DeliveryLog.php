<?php

/**
 * DeliveryLog - Heratio ahg-core
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1739 / #1772: record a view or a download where it is delivered -
 * the record page that rendered, the original the file gate let through, the
 * release link that served a file - for every visitor, signed in or not.
 *
 * Each delivery writes
 *  - a usage event (ahg_usage_event) for the statistics dashboards, bots
 *    flagged rather than dropped; and
 *  - an audit row (security_audit_log, category access or download) when the
 *    audit settings ask for views or downloads, bots excepted.
 * A visitor's repeat of the same delivery within the statistics session
 * window (default 30 minutes) is not counted again, so a PDF viewer's range
 * requests or a reload are one delivery.
 */
class DeliveryLog
{
    public const VIEW = 'view';

    public const DOWNLOAD = 'download';

    public static function view(int $ioId, array $details = []): void
    {
        self::record(self::VIEW, $ioId, null, $details);
    }

    public static function download(int $ioId, ?int $digitalObjectId, array $details = []): void
    {
        self::record(self::DOWNLOAD, $ioId, $digitalObjectId, $details);
    }

    public static function record(string $type, int $ioId, ?int $digitalObjectId = null, array $details = []): void
    {
        if ($ioId <= 1) {
            return;
        }
        try {
            $req = request();
            $userId = auth()->id();
            $ip = (string) $req->ip();
            $ipHash = hash('sha256', $ip.'|'.config('app.key'));
            $window = max(1, (int) self::stat('session_timeout_minutes', 30));
            $who = $userId ? 'u'.$userId : 'a'.$ipHash;
            if (! Cache::add('delivery:'.$type.':'.$ioId.':'.(int) $digitalObjectId.':'.$who, 1, $window * 60)) {
                return;
            }
            $ua = substr((string) $req->userAgent(), 0, 500);
            $bot = self::botName($ua);

            if (Schema::hasTable('ahg_usage_event') && ! ($userId === null && self::stat('track_authenticated_only', '0') === '1')) {
                $anon = self::stat('anonymize_ip', '1') === '1';
                DB::table('ahg_usage_event')->insert([
                    'event_type' => $type,
                    'object_type' => 'information_object',
                    'object_id' => $ioId,
                    'digital_object_id' => $digitalObjectId,
                    'repository_id' => DB::table('information_object')->where('id', $ioId)->value('repository_id'),
                    'user_id' => $userId,
                    'session_id' => substr((string) ($req->hasSession() ? $req->session()->getId() : ''), 0, 64) ?: null,
                    'ip_address' => $anon ? (string) preg_replace('/\.\d+$/', '.0', $ip) : $ip,
                    'ip_hash' => $ipHash,
                    'user_agent' => $ua,
                    'referer' => substr((string) $req->headers->get('referer'), 0, 1000) ?: null,
                    'is_bot' => $bot !== null ? 1 : 0,
                    'bot_name' => $bot,
                    'created_at' => now(),
                ]);
            }

            if ($bot === null) {
                self::audit($type, $ioId, $digitalObjectId, $userId, $ip, $ua, $details);
            }
        } catch (\Throwable $e) {
            // Counting must never stop a page or a file from being delivered.
            Log::warning('[delivery-log] not recorded', ['type' => $type, 'io' => $ioId, 'error' => $e->getMessage()]);
        }
    }

    private static function audit(string $type, int $ioId, ?int $doId, ?int $userId, string $ip, string $ua, array $details): void
    {
        if (! Schema::hasTable('security_audit_log')) {
            return;
        }
        $audit = Cache::remember('delivery:audit-settings', 60, fn () => DB::table('ahg_settings')
            ->where('setting_group', 'audit')->pluck('setting_value', 'setting_key')->all());
        if (($audit['audit_enabled'] ?? '1') !== '1'
            || ($audit[$type === self::VIEW ? 'audit_views' : 'audit_downloads'] ?? '0') !== '1') {
            return;
        }
        if (($audit['audit_ip_anonymize'] ?? '1') === '1') {
            $ip = (string) preg_replace('/\.\d+$/', '.0', $ip);
        }
        DB::table('security_audit_log')->insert([
            'action' => $type,
            'action_category' => $type === self::VIEW ? 'access' : 'download',
            'object_id' => $ioId,
            'object_type' => 'information_object',
            'user_id' => $userId,
            'user_name' => $userId ? DB::table('user')->where('id', $userId)->value('username') : null,
            'details' => json_encode(array_filter($details + ['digital_object_id' => $doId, 'path' => '/'.request()->path()], fn ($v) => $v !== null)),
            'ip_address' => $ip,
            'user_agent' => $ua,
            'created_at' => now(),
        ]);
    }

    /** The bot list name matching this user agent, or null for a person. */
    private static function botName(string $ua): ?string
    {
        if (self::stat('bot_filtering_enabled', '1') !== '1') {
            return null;
        }
        if ($ua === '') {
            return 'empty user agent';
        }
        $bots = Cache::remember('delivery:bots', 300, fn () => Schema::hasTable('ahg_bot_list')
            ? DB::table('ahg_bot_list')->where('is_active', 1)->pluck('pattern', 'name')->all() : []);
        foreach ($bots as $name => $pattern) {
            if ($pattern !== '' && @preg_match('~'.str_replace('~', '\~', (string) $pattern).'~i', $ua)) {
                return (string) $name;
            }
        }

        return preg_match('~bot|crawl|spider|slurp|curl/|wget/|python-requests~i', $ua) ? 'generic crawler' : null;
    }

    private static function stat(string $key, $default)
    {
        $all = Cache::remember('delivery:stat-settings', 60, fn () => DB::table('ahg_settings')
            ->where('setting_group', 'statistics')->pluck('setting_value', 'setting_key')->all());

        return $all[$key] ?? $default;
    }
}
