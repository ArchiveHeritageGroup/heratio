<?php

/**
 * EdiEncoderService - encode ILL requests into EDI message profiles.
 *
 * Concrete codec for the EANCOM / UN-EDIFACT / X12 / CUSTOM profiles that the
 * TradingPartner model (library_trading_partner.edi_type + .message_profile)
 * declares. EdiAdapter handles transport (SFTP / AS2 / HTTP / EMAIL / MANUAL);
 * this service is the message-building half it previously inlined.
 *
 * Focused on ILL message types only:
 *   - EANCOM_S93  ILL request           (UNH ... ILLIC:23:3:UN)
 *   - EANCOM_S94  ILL response/status   (UNH ... ILLIC:24:3:UN)
 *   - X12_850     purchase-order style ILL request
 *   - CUSTOM      JSON envelope
 *
 * The output array shape matches EdiAdapter::buildIllRequestMessage():
 *   ['raw' => string, 'envelope' => string, 'type' => string, 'msg_ref' => string]
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Services;

use AhgLibrary\Models\IllRequest;
use AhgLibrary\Models\TradingPartner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class EdiEncoderService
{
    public const SEGMENT_TERMINATOR = "'";

    protected ?TradingPartner $partner;

    public function __construct(?TradingPartner $partner = null)
    {
        $this->partner = $partner;
    }

    public function forPartner(?TradingPartner $partner): self
    {
        $clone = clone $this;
        $clone->partner = $partner;
        return $clone;
    }

    /**
     * Encode an ILL request using the partner's declared edi_type. Falls back
     * to EANCOM when no partner / type is set.
     *
     * @return array{raw:string, envelope:string, type:string, msg_ref:string}
     */
    public function encode(IllRequest $request): array
    {
        $type = $this->partner?->edi_type ?? 'EANCOM';

        return match ($type) {
            'EANCOM'     => $this->encodeEancom($request),
            'UN/EDIFACT' => $this->encodeEdifact($request),
            'X12'        => $this->encodeX12($request),
            'CUSTOM'     => $this->encodeCustom($request),
            default      => $this->encodeEancom($request),
        };
    }

    // ── EANCOM (ISO 9735 EDIFACT subset) ────────────────────────────────────

    /**
     * EANCOM ILL message. message_profile selects S93 (request, message id 23)
     * vs S94 (response/status, message id 24).
     */
    public function encodeEancom(IllRequest $request): array
    {
        $profile  = $this->partner?->message_profile ?? 'EANCOM_S93';
        $now      = Carbon::now();
        $sender   = $this->senderCode();
        $receiver = $this->partner?->edi_partner_code ?? 'PARTNER';
        $msgRef   = 'MR' . strtoupper(Str::random(12));
        $grpRef   = 'GR' . strtoupper(Str::random(8));
        $nowFmt   = $now->format('ymdHis');
        $msgId    = $profile === 'EANCOM_S94' ? '24' : '23';

        $segments = [];
        $segments[] = "UNB+UNOC:3+{$sender}:ZZZ+{$receiver}:ZZZ+{$nowFmt}+{$msgRef}";
        $segments[] = "UNG+ILLIC+{$sender}:ZZ+{$receiver}:ZZZ+{$nowFmt}+{$grpRef}";
        $segments[] = "UNH+{$msgRef}+ILLIC:{$msgId}:3:UN";
        // BGM document type 24 = ILL request; 33 = ILL answer for S94.
        $docType = $profile === 'EANCOM_S94' ? '33' : '24';
        $segments[] = "BGM+{$docType}+" . ($request->ill_number ?? ('ILL-' . $request->id)) . "+AC";

        $segments[] = "DTM+137:{$now->format('ymd')}:102";
        if ($request->needed_by_date) {
            $segments[] = 'DTM+369:' . Carbon::parse($request->needed_by_date)->format('ymd') . ':102';
        }
        if ($request->due_date) {
            $segments[] = 'DTM+261:' . Carbon::parse($request->due_date)->format('ymd') . ':102';
        }

        // NAD requester (LR) / responder (LE)
        $segments[] = 'NAD+LR+' . $this->edifactEscape($this->senderCode()) . '::91';
        $segments[] = 'NAD+LE+' . $this->edifactEscape($receiver) . '::91';

        // LIN + identifiers
        $segments[] = 'LIN+1';
        if ($request->isbn) {
            $segments[] = 'PIA+5+' . $this->edifactEscape($request->isbn) . ':IB';
        }
        if ($request->issn) {
            $segments[] = 'PIA+5+' . $this->edifactEscape($request->issn) . ':IS';
        }

        // IMD title / author free text
        $segments[] = 'IMD+L+050+:::' . $this->edifactEscape($this->truncate($request->title ?? '', 256));
        if (!empty($request->author)) {
            $segments[] = 'IMD+L+009+:::' . $this->edifactEscape($this->truncate($request->author, 256));
        }
        foreach (['volume' => 'VLM', 'issue' => 'ISS', 'pages' => 'PGS'] as $field => $code) {
            if (!empty($request->{$field})) {
                $segments[] = 'IMD+L+' . $code . '+:::' . $this->edifactEscape((string) $request->{$field});
            }
        }

        if (!empty($request->requester_note)) {
            $segments[] = 'FTX+GEN+++' . $this->edifactEscape($this->truncate($request->requester_note, 350));
        }

        // UNT segment count = body segments (UNH..last before UNT) + UNT itself.
        $body = array_slice($segments, 2); // from UNH onward
        $segCount = count($body) + 1;
        $segments[] = "UNT+{$segCount}+{$msgRef}";
        $segments[] = "UNE+1+{$grpRef}";
        $segments[] = "UNZ+1+{$msgRef}";

        $raw = $this->joinSegments($segments);

        return [
            'raw'      => $raw,
            'envelope' => $segments[0] . self::SEGMENT_TERMINATOR,
            'type'     => $profile === 'EANCOM_S94' ? 'EANCOM_S94' : 'EANCOM_S93',
            'msg_ref'  => $msgRef,
        ];
    }

    public function encodeEdifact(IllRequest $request): array
    {
        $result = $this->encodeEancom($request);
        $result['type'] = 'UN/EDIFACT';
        // Pure EDIFACT uses UNOB/UNOA service string; drop EANCOM UNOC marker.
        $result['raw'] = preg_replace('/UNB\+UNOC:3\+/', 'UNB+UNOA:2+', $result['raw']) ?: $result['raw'];
        $result['envelope'] = preg_replace('/UNB\+UNOC:3\+/', 'UNB+UNOA:2+', $result['envelope']) ?: $result['envelope'];
        return $result;
    }

    // ── X12 (ASC X12 850-style ILL request) ─────────────────────────────────

    public function encodeX12(IllRequest $request): array
    {
        $now      = Carbon::now();
        $sender   = str_pad(substr($this->senderCode(), 0, 15), 15);
        $receiver = str_pad(substr($this->partner?->edi_partner_code ?? 'PARTNER', 0, 15), 15);
        $isaCtrl  = str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT);
        $gsCtrl   = str_pad((string) random_int(1, 999999), 4, '0', STR_PAD_LEFT);
        $stCtrl   = str_pad((string) random_int(1, 99999), 4, '0', STR_PAD_LEFT);
        $eb = '~'; // segment terminator

        $st = [];
        $st[] = "ST*850*{$stCtrl}";
        $st[] = 'BEG*00*NE*' . ($request->ill_number ?? ('ILL-' . $request->id)) . '**' . $now->format('Ymd');
        $st[] = 'REF*IL*' . ($request->ill_number ?? ('ILL-' . $request->id));
        if ($request->needed_by_date) {
            $st[] = 'DTM*002*' . Carbon::parse($request->needed_by_date)->format('Ymd');
        }
        $st[] = 'N1*BY*' . $this->x12Escape($this->senderCode());
        $st[] = 'N1*SU*' . $this->x12Escape($request->library_name ?: ($this->partner?->edi_partner_code ?? 'SUPPLIER'));
        $st[] = 'PO1*1*1*EA*' . number_format((float) ($request->cost_amount ?? 0), 2, '.', '')
            . '*PE*IB*' . ($request->isbn ?: ($request->issn ?: ''));
        $st[] = 'PID*F****' . $this->x12Escape($this->truncate($request->title ?? '', 80));
        $st[] = 'CTT*1';
        $st[] = "SE*" . (count($st) + 1) . "*{$stCtrl}";

        $segments = [];
        $segments[] = "ISA*00*          *00*          *ZZ*{$sender}*ZZ*{$receiver}*"
            . $now->format('ymd') . '*' . $now->format('Hi') . "*U*00401*{$isaCtrl}*0*P*>";
        $segments[] = "GS*PO*" . trim($sender) . '*' . trim($receiver) . '*'
            . $now->format('Ymd') . '*' . $now->format('Hi') . "*{$gsCtrl}*X*004010";
        foreach ($st as $s) {
            $segments[] = $s;
        }
        $segments[] = "GE*1*{$gsCtrl}";
        $segments[] = "IEA*1*{$isaCtrl}";

        $raw = implode($eb . "\n", $segments) . $eb;

        return [
            'raw'      => $raw,
            'envelope' => $segments[0] . $eb,
            'type'     => 'X12_850',
            'msg_ref'  => $stCtrl,
        ];
    }

    // ── CUSTOM JSON ──────────────────────────────────────────────────────────

    public function encodeCustom(IllRequest $request): array
    {
        $raw = json_encode([
            'ill_number'        => $request->ill_number,
            'type'              => $request->type ?? 'borrow',
            'request_type'      => $request->request_type ?? 'BORROW',
            'borrowing_protocol' => $request->borrowing_protocol ?? 'AARC',
            'material_type'     => $request->material_type ?? 'BOOK',
            'title'             => $request->title,
            'author'            => $request->author,
            'isbn'              => $request->isbn,
            'issn'              => $request->issn,
            'volume'            => $request->volume,
            'issue'             => $request->issue,
            'pages'             => $request->pages,
            'edition'           => $request->edition,
            'publication_year'  => $request->publication_year,
            'requester_note'    => $request->requester_note,
            'needed_by_date'    => $request->needed_by_date
                ? Carbon::parse($request->needed_by_date)->toDateString() : null,
            'due_date'          => $request->due_date
                ? Carbon::parse($request->due_date)->toDateString() : null,
            'sender'            => $this->senderCode(),
            'receiver'          => $this->partner?->edi_partner_code,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return [
            'raw'      => $raw ?: '{}',
            'envelope' => 'CUSTOM/JSON',
            'type'     => 'CUSTOM',
            'msg_ref'  => 'CUST' . strtoupper(Str::random(10)),
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    protected function senderCode(): string
    {
        return parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'HERATIO';
    }

    protected function joinSegments(array $segments): string
    {
        return implode(self::SEGMENT_TERMINATOR . "\n", $segments) . self::SEGMENT_TERMINATOR;
    }

    /** Escape EDIFACT release character before reserved separators. */
    protected function edifactEscape(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }
        return preg_replace("/([+:'?])/", '?$1', $text) ?? $text;
    }

    /** X12 reserved-char strip (element sep *, segment sep ~, sub-element >). */
    protected function x12Escape(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }
        return str_replace(['*', '~', '>'], [' ', ' ', ' '], $text);
    }

    protected function truncate(?string $text, int $max): string
    {
        if (!$text) {
            return '';
        }
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max) : $text;
    }
}
