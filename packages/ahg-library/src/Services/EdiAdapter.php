<?php

namespace AhgLibrary\Services;

use AhgLibrary\Models\IllRequest;
use AhgLibrary\Models\TradingPartner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * EdiAdapter - EDI/EANCOM message dispatcher for library trading partners.
 *
 * Supports endpoint types: SFTP, AS2, HTTP/HTTPS, EMAIL, MANUAL.
 * Builds message payloads for: EANCOM S93/S94, UN/EDIFACT ILL, X12 850.
 */
class EdiAdapter
{
    protected ?TradingPartner $partner = null;

    public function __construct(?TradingPartner $partner = null)
    {
        $this->partner = $partner;
    }

    // ── Connection testing ──────────────────────────────────────────────

    /**
     * @return array{ok: bool, message: string, details: array}
     */
    public function testConnection(?TradingPartner $partner = null): array
    {
        $tp = $partner ?? $this->partner;
        if (!$tp) {
            return ['ok' => false, 'message' => 'No trading partner configured.', 'details' => []];
        }

        return match ($tp->endpoint_type) {
            'SFTP'       => $this->testSftp($tp),
            'AS2'        => $this->testAs2($tp),
            'HTTP_HTTPS' => $this->testHttp($tp),
            'EMAIL'      => $this->testEmail($tp),
            'MANUAL'     => ['ok' => true, 'message' => 'Manual mode: no connection test needed.', 'details' => []],
            default      => ['ok' => false, 'message' => "Unknown endpoint type: {$tp->endpoint_type}", 'details' => []],
        };
    }

    protected function testSftp(TradingPartner $tp): array
    {
        $cfg = $tp->endpoint_config ?? [];
        $host = $cfg['host'] ?? '';
        $port = (int) ($cfg['port'] ?? 22);

        if (empty($host)) {
            return ['ok' => false, 'message' => 'SFTP host not configured.', 'details' => []];
        }

        try {
            // Quick TCP socket probe
            $fp = @fsockopen($host, $port, $errno, $errstr, 10);
            if ($fp) {
                fclose($fp);
                return ['ok' => true, 'message' => "TCP open {$host}:{$port}", 'details' => ['host' => $host, 'port' => $port]];
            }
            return ['ok' => false, 'message' => "{$errstr} ({$errno})", 'details' => ['host' => $host]];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'SFTP test failed: ' . $e->getMessage(), 'details' => []];
        }
    }

    protected function testAs2(TradingPartner $tp): array
    {
        $cfg = $tp->endpoint_config ?? [];
        $url = $cfg['as2_url'] ?? '';

        if (empty($url)) {
            return ['ok' => false, 'message' => 'AS2 URL not configured.', 'details' => []];
        }

        try {
            $client = new \GuzzleHttp\Client(['timeout' => 15, 'verify' => true, 'allow_redirects' => false]);
            $resp = $client->request('HEAD', $url);
            return ['ok' => $resp->getStatusCode() < 500, 'message' => "AS2 endpoint HTTP {$resp->getStatusCode()}", 'details' => ['url' => $url]];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'AS2 test failed: ' . $e->getMessage(), 'details' => ['url' => $url]];
        }
    }

    protected function testHttp(TradingPartner $tp): array
    {
        $cfg = $tp->endpoint_config ?? [];
        $url = $cfg['url'] ?? '';

        if (empty($url)) {
            return ['ok' => false, 'message' => 'HTTP/HTTPS URL not configured.', 'details' => []];
        }

        try {
            $client = new \GuzzleHttp\Client(['timeout' => 15, 'verify' => true]);
            $resp = $client->request('GET', $url);
            return ['ok' => $resp->getStatusCode() < 500, 'message' => "HTTP {$resp->getStatusCode()}", 'details' => ['url' => $url]];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'HTTP test failed: ' . $e->getMessage(), 'details' => ['url' => $url]];
        }
    }

    protected function testEmail(TradingPartner $tp): array
    {
        $cfg = $tp->endpoint_config ?? [];
        $host = $cfg['smtp_host'] ?? 'localhost';
        $port = (int) ($cfg['smtp_port'] ?? 587);
        return ['ok' => true, 'message' => "SMTP configured: {$host}:{$port}", 'details' => ['host' => $host, 'port' => $port]];
    }

    // ── Message building ────────────────────────────────────────────────

    /**
     * Build an EDI message payload for an ILL request.
     *
     * @return array{raw: string, envelope: string, type: string, msg_ref: string}
     */
    public function buildIllRequestMessage(IllRequest $request): array
    {
        // The encoder carries the request number, title and identifiers that a
        // partner must echo back for its reply to be matched (#1475).
        return (new EdiEncoderService($this->partner))->encode($request);
    }

    // ── Transmission ───────────────────────────────────────────────────

    /**
     * Send an ILL request via the configured endpoint.
     *
     * @return array{ok: bool, message: string, edi_message_id: string|null}
     */
    public function sendIllRequest(IllRequest $request): array
    {
        $tp = $this->partner;
        if (!$tp) {
            return ['ok' => false, 'message' => 'No trading partner set.', 'edi_message_id' => null];
        }

        if (!$tp->is_active) {
            return ['ok' => false, 'message' => 'Trading partner is inactive.', 'edi_message_id' => null];
        }

        if ($tp->test_mode) {
            Log::info('[EdiAdapter] TEST mode - message not sent', [
                'partner' => $tp->edi_partner_code,
                'ill'     => $request->ill_number,
            ]);
            return [
                'ok'            => true,
                'message'       => 'TEST mode: message not transmitted.',
                'test_mode'     => true,
                'edi_message_id' => 'TEST-' . strtoupper(Str::random(8)),
            ];
        }

        $msg = $this->buildIllRequestMessage($request);

        return match ($tp->endpoint_type) {
            'MANUAL'     => $this->prepareManual($msg, $tp),
            'SFTP'       => $this->sendViaSftp($msg, $tp),
            'AS2'        => $this->sendViaAs2($msg, $tp),
            'HTTP_HTTPS' => $this->sendViaHttp($msg, $tp),
            'EMAIL'      => $this->sendViaEmail($msg, $tp),
            default      => ['ok' => false, 'message' => "Unsupported endpoint: {$tp->endpoint_type}", 'edi_message_id' => null],
        };
    }

    /** Whether this install has what the transport needs to send anything. */
    public static function canTransmit(?string $endpointType): bool
    {
        return match ($endpointType) {
            'MANUAL', 'AS2', 'HTTP_HTTPS', 'EMAIL' => true,
            'SFTP'  => class_exists(\phpseclib3\Net\SFTP::class),
            default => false,
        };
    }

    protected function prepareManual(array $msg, TradingPartner $tp): array
    {
        $sub = trim((string) $tp->outbound_directory, '/');
        if (preg_match('#(^|/)\.\.(/|$)#', $sub)) {
            return $this->manualFailure($tp, 'Outbound directory is not valid.');
        }

        $root = rtrim((string) config('heratio.ill_edi_outbound_path'), '/');
        $dir = $sub === '' ? $root : $root . '/' . $sub;
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            return $this->manualFailure($tp, 'Outbound directory could not be created.');
        }

        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) ($msg['msg_ref'] ?? 'msg')) . '.edi';
        if (@file_put_contents($dir . '/' . $filename, $msg['raw'], LOCK_EX) === false) {
            return $this->manualFailure($tp, 'Outbound file could not be written.');
        }

        $tp->last_outbound_at = Carbon::now();
        $tp->save();

        return [
            'ok'            => true,
            'message'       => 'Message queued to ' . ($sub === '' ? '' : $sub . '/') . $filename,
            'edi_message_id' => $msg['msg_ref'],
        ];
    }

    private function manualFailure(TradingPartner $tp, string $message): array
    {
        $tp->last_error_at = Carbon::now();
        $tp->last_error_message = $message;
        $tp->save();

        return ['ok' => false, 'message' => $message, 'edi_message_id' => null];
    }

    protected function sendViaSftp(array $msg, TradingPartner $tp): array
    {
        $cfg = $tp->endpoint_config ?? [];
        $host = $cfg['host'] ?? '';
        $port = (int) ($cfg['port'] ?? 22);
        $user = $cfg['username'] ?? '';
        $pass = $cfg['password'] ?? '';
        $dir  = $cfg['path'] ?? $tp->outbound_directory;

        try {
            if (class_exists(\phpseclib3\Net\SFTP::class)) {
                $sftp = new \phpseclib3\Net\SFTP($host, $port, 30);
                $key = null;
                if (!empty($cfg['private_key'])) {
                    $keyClass = '\\phpseclib3\\Net\\SFTP\\PrivateKeyAlgorithm\\RSA\\PrivateKey';
                    if (class_exists($keyClass)) {
                        $key = $keyClass::load($cfg['private_key']);
                    }
                }
                $login = $key ? $sftp->login($user, $key) : $sftp->login($user, $pass);
                if (!$login) {
                    throw new \RuntimeException('SFTP authentication failed.');
                }
                $remotePath = rtrim($dir, '/') . '/' . ($msg['msg_ref'] ?? 'msg') . '.edi';
                if (!$sftp->put($remotePath, $msg['raw'])) {
                    throw new \RuntimeException('SFTP upload failed.');
                }

                $tp->last_outbound_at = Carbon::now();
                $tp->last_error_at = null;
                $tp->last_error_message = null;
                $tp->save();

                return [
                    'ok'            => true,
                    'message'       => "SFTP upload ok: {$remotePath}",
                    'edi_message_id' => $msg['msg_ref'],
                ];
            }
            return ['ok' => false, 'message' => 'phpseclib3 not installed.', 'edi_message_id' => null];
        } catch (\Throwable $e) {
            $tp->last_error_at = Carbon::now();
            $tp->last_error_message = $e->getMessage();
            $tp->save();
            return ['ok' => false, 'message' => 'SFTP error: ' . $e->getMessage(), 'edi_message_id' => null];
        }
    }

    protected function sendViaAs2(array $msg, TradingPartner $tp): array
    {
        $cfg = $tp->endpoint_config ?? [];
        $url = $cfg['as2_url'] ?? '';
        if (empty($url)) {
            return ['ok' => false, 'message' => 'AS2 URL not configured.', 'edi_message_id' => null];
        }
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 60, 'verify' => true]);
            $client->request('POST', $url, [
                'headers' => [
                    'Content-Type'   => 'application/edifact',
                    'AS2-Version'   => '1.2',
                    'AS2-From'      => parse_url(config('app.url'), PHP_URL_HOST) ?: 'HERARIO',
                    'AS2-To'        => $tp->edi_partner_code,
                    'Message-ID'    => '<' . ($msg['msg_ref'] ?? Str::random(16)) . '@' . (parse_url(config('app.url'), PHP_URL_HOST) ?: 'heratio') . '>',
                ],
                'body' => $msg['raw'],
            ]);
            $tp->last_outbound_at = Carbon::now();
            $tp->save();
            return ['ok' => true, 'message' => 'AS2 message sent.', 'edi_message_id' => $msg['msg_ref']];
        } catch (\Throwable $e) {
            $tp->last_error_at = Carbon::now();
            $tp->last_error_message = $e->getMessage();
            $tp->save();
            return ['ok' => false, 'message' => 'AS2 error: ' . $e->getMessage(), 'edi_message_id' => null];
        }
    }

    protected function sendViaHttp(array $msg, TradingPartner $tp): array
    {
        $cfg = $tp->endpoint_config ?? [];
        $url = $cfg['url'] ?? '';
        if (empty($url)) {
            return ['ok' => false, 'message' => 'HTTP/HTTPS URL not configured.', 'edi_message_id' => null];
        }
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 60, 'verify' => true]);
            $resp = $client->request('POST', $url, [
                'headers' => ['Content-Type' => 'application/edifact'],
                'body'    => $msg['raw'],
            ]);
            $tp->last_outbound_at = Carbon::now();
            $tp->save();
            return ['ok' => true, 'message' => 'HTTP POST ok: ' . $resp->getStatusCode(), 'edi_message_id' => $msg['msg_ref']];
        } catch (\Throwable $e) {
            $tp->last_error_at = Carbon::now();
            $tp->last_error_message = $e->getMessage();
            $tp->save();
            return ['ok' => false, 'message' => 'HTTP error: ' . $e->getMessage(), 'edi_message_id' => null];
        }
    }

    protected function sendViaEmail(array $msg, TradingPartner $tp): array
    {
        $cfg = $tp->endpoint_config ?? [];
        $to = $cfg['smtp_to'] ?? ($cfg['contact_email'] ?? '');
        if (empty($to)) {
            return ['ok' => false, 'message' => 'No recipient email configured.', 'edi_message_id' => null];
        }
        try {
            \Illuminate\Support\Facades\Mail::raw($msg['raw'], function ($m) use ($to, $msg) {
                $m->to($to)
                  ->subject('EDI ILL Message ' . ($msg['msg_ref'] ?? ''))
                  ->from(config('mail.from.address', 'noreply@heratio'));
            });
            $tp->last_outbound_at = Carbon::now();
            $tp->save();
            return ['ok' => true, 'message' => "EDI message emailed to {$to}", 'edi_message_id' => $msg['msg_ref']];
        } catch (\Throwable $e) {
            $tp->last_error_at = Carbon::now();
            $tp->last_error_message = $e->getMessage();
            $tp->save();
            return ['ok' => false, 'message' => 'Email error: ' . $e->getMessage(), 'edi_message_id' => null];
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    protected function truncate(?string $text, int $max): string
    {
        if (!$text) return '';
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max) : $text;
    }
}
