<?php

/**
 * IntegrityNotifier - email + webhook alerts for fixity failures + mismatches
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

namespace AhgIntegrity\Services;

use AhgIntegrity\Support\IntegritySettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class IntegrityNotifier
{
    public const ACTION_FAILURE = 'failure';
    public const ACTION_MISMATCH = 'mismatch';

    /**
     * Dispatch a notification for a fixity event. Honours
     * integrity_notify_on_failure / integrity_notify_on_mismatch (gate)
     * and routes to integrity_alert_email + integrity_webhook_url
     * (destinations). All failures are caught + logged; a notification
     * outage must not stop a fixity scan.
     */
    public function notify(string $action, array $context): void
    {
        if (!$this->shouldNotify($action)) {
            return;
        }

        $email = IntegritySettings::alertEmail();
        $webhook = IntegritySettings::webhookUrl();

        $subject = $this->subjectFor($action, $context);
        $body = $this->bodyFor($action, $context);

        if ($email !== '') {
            $this->sendEmail($email, $subject, $body);
        }
        if ($webhook !== '') {
            $this->sendWebhook($webhook, $action, $context, $subject);
        }
    }

    protected function shouldNotify(string $action): bool
    {
        switch ($action) {
            case self::ACTION_FAILURE:  return IntegritySettings::notifyOnFailure();
            case self::ACTION_MISMATCH: return IntegritySettings::notifyOnMismatch();
            default:                    return false;
        }
    }

    protected function subjectFor(string $action, array $context): string
    {
        $object = $context['digital_object_id'] ?? $context['object_id'] ?? '?';
        return $action === self::ACTION_MISMATCH
            ? "[Heratio integrity] Checksum mismatch on object #$object"
            : "[Heratio integrity] Fixity failure on object #$object";
    }

    protected function bodyFor(string $action, array $context): string
    {
        $lines = ["A fixity event was raised by Heratio."];
        $lines[] = "Action: $action";
        foreach (['digital_object_id', 'object_id', 'algorithm', 'expected', 'actual', 'path', 'error'] as $k) {
            if (!empty($context[$k])) {
                $lines[] = sprintf('%s: %s', ucfirst(str_replace('_', ' ', $k)), $context[$k]);
            }
        }
        $lines[] = '';
        $lines[] = 'Sent by ' . config('app.name', 'Heratio') . ' integrity monitoring.';
        return implode("\n", $lines);
    }

    /**
     * Send via Laravel's Mail facade (sendmail / SMTP per .env). Failure is
     * logged but never thrown so a scan that triggered the notify can
     * complete cleanly even when the mailer is down.
     */
    protected function sendEmail(string $to, string $subject, string $body): void
    {
        try {
            Mail::raw($body, function ($m) use ($to, $subject) {
                $m->to($to)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::warning('[integrity] alert email failed', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * POST a JSON envelope to the configured webhook. Non-2xx responses
     * are logged but never re-tried (the dead-letter retry loop lives in
     * the fixity scan itself, not in this notifier).
     */
    protected function sendWebhook(string $url, string $action, array $context, string $summary): void
    {
        try {
            $payload = json_encode([
                'event' => 'integrity.' . $action,
                'summary' => $summary,
                'context' => $context,
                'timestamp' => date('c'),
                'site' => config('app.url'),
            ]);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'User-Agent: Heratio-Integrity/1.0',
                ],
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($resp === false || $code < 200 || $code >= 300) {
                Log::warning('[integrity] webhook delivery failed', [
                    'url' => $url,
                    'http_code' => $code,
                    'curl_error' => $err,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[integrity] webhook exception', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
