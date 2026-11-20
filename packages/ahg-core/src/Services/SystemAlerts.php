<?php

/**
 * SystemAlerts - Heratio ahg-core
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * heratio#1742: tell people when something on the server is wrong - and when
 * it is right again. An alert goes to every administrator in Heratio and to
 * the addresses in monitoring_alert_email. A check that stays failed is
 * repeated once a day, not every run, so a long outage is not a flood.
 */
class SystemAlerts
{
    /** Hours between reminders while a check stays failed. */
    public const REMIND_HOURS = 24;

    /**
     * Report the state of one check. Returns 'raised', 'recovered',
     * 'reminded' or '' (nothing sent).
     */
    public static function report(string $check, bool $ok, string $detail = '', ?string $link = null): string
    {
        $key = 'system-alert:'.$check;
        $state = Cache::get($key);
        if ($ok) {
            if ($state === null) {
                return '';
            }
            Cache::forget($key);
            self::send(__('Recovered: :check', ['check' => self::label($check)]), $detail ?: __('Back to normal.'), $link, 'system_recovered');

            return 'recovered';
        }
        $now = now()->getTimestamp();
        if ($state !== null && $now - (int) ($state['at'] ?? 0) < self::REMIND_HOURS * 3600) {
            return '';
        }
        Cache::forever($key, ['at' => $now, 'since' => $state['since'] ?? $now]);
        $since = $state ? __('(failing since :at)', ['at' => date('Y-m-d H:i', (int) $state['since'])]) : '';
        self::send(trim(__('Problem: :check', ['check' => self::label($check)]).' '.$since), $detail, $link, 'system_alert');

        return $state ? 'reminded' : 'raised';
    }

    /** Send one alert now (no state), for events such as a missed scheduled job. */
    public static function send(string $title, string $body, ?string $link = null, string $type = 'system_alert'): void
    {
        $site = (string) (config('app.name') ?: 'Heratio').' '.parse_url((string) config('app.url'), PHP_URL_HOST);
        try {
            app(NotificationService::class)->notifyAdmins($type, $title, $body, $link);
        } catch (\Throwable $e) {
            Log::warning('[system-alerts] in-app notice failed: '.$e->getMessage());
        }
        foreach (array_filter(array_map('trim', explode(',', (string) AhgSettingsService::get('monitoring_alert_email', '')))) as $to) {
            try {
                Mail::raw($body.($link ? "\n\n".$link : '')."\n\n- ".$site, fn ($m) => $m->to($to)->subject('['.$site.'] '.$title));
            } catch (\Throwable $e) {
                Log::warning('[system-alerts] mail to '.$to.' failed: '.$e->getMessage());
            }
        }
        Log::warning('[system-alerts] '.$title.' - '.$body);
    }

    private static function label(string $check): string
    {
        return match (true) {
            $check === 'web' => __('the website'),
            $check === 'database' => __('the database'),
            $check === 'search' => __('the search engine'),
            $check === 'queue' => __('the background job queue'),
            str_starts_with($check, 'disk:') => __('disk space on :path', ['path' => substr($check, 5)]),
            default => $check,
        };
    }
}
