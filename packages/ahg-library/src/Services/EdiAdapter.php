<?php

namespace AhgLibrary\Services;

use AhgLibrary\Models\IllRequest;
use AhgLibrary\Models\TradingPartner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * EdiAdapter — EDI/EANCOM message dispatcher for library trading partners.
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
        $tp = $this->partner;
        $type = $tp?->edi_type ?? 'EANCOM';

        return match ($type) {
            'EANCOM'     => $this->buildEancom($request),
            'UN/EDIFACT' => $this->buildEdifact($request),
            'X12'        => $this->buildX12($request),
            'CUSTOM'    => $this->buildCustom($request),
            default      => $this->buildEancom($request),
        };
    }

    protected function buildEancom(IllRequest $request): array
    {
        $profile = $this->partner?->message_profile ?? 'EANCOM_S93';
        $now = Carbon::now();
        $sender = parse_url(config('app.url'), PHP_URL_HOST) ?: 'HERARIO';
        $receiver = $this->partner?->edi_partner_code ?? 'PARTNER';
        $msgRef = 'MR' . strtoupper(Str::random(12));
        $grpRef = 'GR' . strtoupper(Str::random(8));
        $nowFmt = $now->format('ymdHis');

        // UNB envelope
        $unb = "UNB+UNOC:3+{$sender}:ZZZ+{$receiver}:ZZZ+{$nowFmt}+{$msgRef}'";

        // UNG group
        $ung = "UNG+ILLIC+:ZZ+{$sender}:ZZZ+{$receiver}:ZZZ+{$nowFmt}+{$grpRef}'";

        // UNH message header
        $msgId = $profile === 'EANCOM_S94' ? '24' : '23';
        $unh = "UNH+{$msgRef}+ILLIC:{$msgId}:3:UN'";

        // BGM begin message
        $bgmRef = 'BGM' . strtoupper(Str::random(8));
        $title = $this->truncate($request->title ?? '', 300);
        $bgm = "BGM+24+{$bgmRef}+AC'";

        // NAD segments — requester / responder
        $requester = $request->requesterLibrary;
        $responder = $request->responderLibrary;

        $nadReq = $requester
            ? "NAD+LR+{$requester->code}+::{::}+" . $this->truncate($requester->name, 35) . "+{$requester->address}++{$requester->contact_email}'"
            : "NAD+LR+::SYSTEM+HERARIO'";

        $nadResp = $responder
            ? "NAD+LE+{$responder->code}+::{::}+" . $this->truncate($responder->name, 35) . "+{$responder->address}++{$responder->contact_email}'"
            : '';

        // LIN item line
        $isbnSeg = $request->isbn ? "LIN+1+++ISBN:IB' " : '';
        $issnSeg = $request->issn ? "LIN+2+++ISSN:IB' " : '';
        $lin = rtrim("LIN+1+{$isbnSeg}{$issnSeg}", ' ');

        // IMD description
        $author = $this->truncate($request->author ?? '', 200);
        $imd = "IMD+L+{:}+{:}:EN+{$author}:AU'";

        // DTM dates
        $reqDate = $request->request_date
            ? Carbon::parse($request->request_date)->format('ymd')
            : $now->format('ymd');
        $neededDate = $request->needed_by_date
            ? 'DTM+369:' . Carbon::parse($request->needed_by_date)->format('ymd') . ':102\' '
            : '';
        $dtm = "DTM+137:{$reqDate}:102' {$neededDate}";
        $dtm = trim(str_replace("  ", " ", $dtm));

        // FTX notes
        $ftx = $request->requester_note
            ? "FTX+GEN+++{$this->truncate($request->requester_note, 300)}'"
            : '';

        // UNT trailer
        $segmentCount = substr_count($unb . $ung . $unh . $bgm . $nadReq . $nadResp . $lin . $imd . $dtm . $ftx, "'") + 1;
        $unt = "UNT+{$segmentCount}+{$msgRef}'";

        // UNE + UNZ envelope close
        $une = "UNE+1+{$grpRef}'";
        $unz = "UNZ+1+{$msgRef}'";

        $raw = implode("\n", array_filter([$unb, $ung, $unh, $bgm, $nadReq, $nadResp, $lin, $imd, $dtm, $ftx, $unt, $une, $unz]));

        return [
            'raw'      => $raw,
            'envelope' => $unb,
            'type'     => 'EANCOM',
            'msg_ref'  => $msgRef,
        ];
    }

    protected function buildEdifact(IllRequest $request): array
    {
        // UN/EDIFACT — same structure as EANCOM but without EANCOM qualifier in UNB
        $result = $this->buildEancom($request);
        $result['type'] = 'UN/EDIFACT';
        // Strip the UNOC:3 qualifier for pure EDIFACT
        $result['envelope'] = preg_replace('/\+UNOC:3\+/', '+UNB+', $result['envelope']) ?: $result['envelope'];
        $result['raw'] = preg_replace('/\+UNOC:3\+/', '+UNB+', $result['raw']) ?: $result['raw'];
        return $result;
    }

    protected function buildX12(IllRequest $request): array
    {
        $now = Carbon::now();
        $isaCtrl = str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT);
        $gsCtrl  = str_pad((string) random_int(1, 999999), 4, '0', STR_PAD_LEFT);
        $stCtrl  = str_pad((string) random_int(1, 99999), 4, '0', STR_PAD_LEFT);
        $sender  = parse_url(config('app.url'), PHP_URL_HOST) ?: 'HERARIO';
        $receiver = $this->partner?->edi_partner_code ?? 'PARTNER';

        $isa = "ISA*00*          *00*          *ZZ*${sender}       *ZZ*${receiver}     *{$now->format('ymd')}*{$now->format('Hi')}*^*00501*{$isaCtrl}*0*P*:~";
        $gs  = "GS*IL*${sender}*${receiver}*{$now->format('ymd')}*{$now->format('Hi')}*{$gsCtrl}*X*005010X22A~";
        $st  = "ST*850*{$stCtrl}~";
        $ref = "REF*IV*" . ($request->ill_number ?? 'ILL-' . $request->id) . "~";
        $n1  = "N1*LB*{$sender}*92*" . ($this->partner?->vendor?->code ?? '') . "~";
        $n2  = $request->requester_note ? "N2*" . $this->truncate($request->requester_note, 60) . "~" : '';
        $po1 = "PO1*1*1*EA*" . number_format((float) ($request->cost_amount ?? 0), 2, '.', '') . "*UK*" . ($request->isbn ?: ($request->issn ?: '')) . "**PF*ILL~
               PID*F****" . $this->truncate($request->title ?? '', 80) . "~
               PAT*06***" . ($request->needed_by_date ? Carbon::parse($request->needed_by_date)->format('ymd') : $now->format('ymd')) . "~";
        $ctt = "CTT*1~";
        $se  = "SE*" . (substr_count($st . $ref . $n1 . $n2 . $po1 . $ctt, '~') + 1) . "*{$stCtrl}~";
        $ge  = "GE*1*{$gsCtrl}~";
        $iea = "IEA*1*{$isaCtrl}~";

        $raw = implode("\n", array_filter([$isa, $gs, $st, $ref, $n1, $n2, $po1, $ctt, $se, $ge, $iea]));

        return [
            'raw'     => $raw,
            'envelope' => $isa,
            'type'    => 'X12 850',
            'msg_ref' => $stCtrl,
        ];
    }

    protected function buildCustom(IllRequest $request): array
    {
        $raw = json_encode([
            'ill_number'       => $request->ill_number,
            'title'            => $request->title,
            'author'           => $request->author,
            'isbn'             => $request->isbn,
            'issn'             => $request->issn,
            'volume'           => $request->volume,
            'issue'            => $request->issue,
            'pages'            => $request->pages,
            'requester_note'   => $request->requester_note,
            'needed_by_date'   => $request->needed_by_date?->toDateString(),
            'request_date'     => $request->request_date?->toDateString(),
            'request_type'     => $request->request_type ?? 'BORROW',
            'borrowing_protocol' => $request->borrowing_protocol ?? 'AARC',
            'material_type'    => $request->material_type ?? 'BOOK',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return [
            'raw'      => $raw,
            'envelope' => 'CUSTOM/JSON',
            'type'     => 'CUSTOM',
            'msg_ref'  => 'CUST' . strtoupper(Str::random(10)),
        ];
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
            Log::info('[EdiAdapter] TEST mode — message not sent', [
                'partner' => $tp->edi_partner_code,
                'ill'     => $request->ill_number,
            ]);
            return [
                'ok'            => true,
                'message'       => 'TEST mode: message not transmitted.',
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

    protected function prepareManual(array $msg, TradingPartner $tp): array
    {
        $dir = base_path($tp->outbound_directory);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $filename = ($msg['msg_ref'] ?? 'msg') . '.edi';
        file_put_contents(rtrim($dir, '/') . '/' . $filename, $msg['raw']);

        $tp->last_outbound_at = Carbon::now();
        $tp->save();

        return [
            'ok'            => true,
            'message'       => "Message queued to {$tp->outbound_directory}/{$filename}",
            'edi_message_id' => $msg['msg_ref'],
        ];
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
                $sftp->put($remotePath, $msg['raw']);

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
