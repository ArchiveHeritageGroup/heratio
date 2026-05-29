<?php

/**
 * EdiDecoderService - parse inbound EDI ILL messages into a normalized array.
 *
 * Concrete decoder counterpart to EdiEncoderService. Handles the inbound side
 * of the ILL EDI loop: lender acknowledgements, status/answer messages and
 * shipment notifications arriving over the partner endpoint. Auto-detects the
 * profile (EANCOM/UN-EDIFACT envelope, X12 ISA envelope, or CUSTOM JSON) and
 * normalizes to:
 *
 *   [
 *     'profile'    => 'EANCOM'|'X12'|'CUSTOM',
 *     'msg_ref'    => string|null,    // interchange / message reference
 *     'ill_number' => string|null,    // BGM / REF / JSON ill_number
 *     'status'     => string|null,    // mapped ISO 10160 status, see LibraryIllService
 *     'raw_status' => string|null,    // partner's native status code/word
 *     'due_date'   => 'Y-m-d'|null,
 *     'note'       => string|null,
 *     'segments'   => array,          // parsed segment tuples for debugging
 *   ]
 *
 * Status mapping targets LibraryIllService::STATUS_* so the result can be fed
 * straight into LibraryIllService::transitionTo().
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Services;

class EdiDecoderService
{
    /**
     * EDIFACT / EANCOM document-type (BGM) code -> ISO 10160 status word.
     * 33 = ILL answer, 34 = shipped, 35 = received, 36 = returned, etc. We map
     * the common ILL answer codes; unknowns fall through to raw_status only.
     */
    protected const EDIFACT_STATUS_MAP = [
        '33' => LibraryIllService::STATUS_REQUESTED,
        '34' => LibraryIllService::STATUS_SHIPPED,
        '35' => LibraryIllService::STATUS_RECEIVED,
        '36' => LibraryIllService::STATUS_RETURNED,
        '37' => LibraryIllService::STATUS_UNFULFILLED,
        '38' => LibraryIllService::STATUS_CANCELLED,
        '39' => LibraryIllService::STATUS_LOST,
    ];

    /** Free-text / native status words -> ISO 10160 status. */
    protected const WORD_STATUS_MAP = [
        'shipped'      => LibraryIllService::STATUS_SHIPPED,
        'in transit'   => LibraryIllService::STATUS_SHIPPED,
        'received'     => LibraryIllService::STATUS_RECEIVED,
        'returned'     => LibraryIllService::STATUS_RETURNED,
        'unfilled'     => LibraryIllService::STATUS_UNFULFILLED,
        'unfulfilled'  => LibraryIllService::STATUS_UNFULFILLED,
        'will supply'  => LibraryIllService::STATUS_REQUESTED,
        'conditional'  => LibraryIllService::STATUS_REQUESTED,
        'cancelled'    => LibraryIllService::STATUS_CANCELLED,
        'canceled'     => LibraryIllService::STATUS_CANCELLED,
        'lost'         => LibraryIllService::STATUS_LOST,
        'overdue'      => LibraryIllService::STATUS_OVERDUE,
    ];

    /**
     * Decode an inbound raw EDI message.
     *
     * @return array{profile:string, msg_ref:?string, ill_number:?string,
     *               status:?string, raw_status:?string, due_date:?string,
     *               note:?string, segments:array}
     */
    public function decode(string $raw): array
    {
        $raw = trim($raw);
        $profile = $this->detectProfile($raw);

        return match ($profile) {
            'X12'    => $this->decodeX12($raw),
            'CUSTOM' => $this->decodeCustom($raw),
            default  => $this->decodeEdifact($raw),
        };
    }

    public function detectProfile(string $raw): string
    {
        $head = ltrim($raw);
        if ($head !== '' && ($head[0] === '{' || $head[0] === '[')) {
            return 'CUSTOM';
        }
        if (str_starts_with($head, 'ISA')) {
            return 'X12';
        }
        return 'EANCOM'; // UNB/UNA envelope (covers UN/EDIFACT too)
    }

    // ── EDIFACT / EANCOM ─────────────────────────────────────────────────────

    protected function decodeEdifact(string $raw): array
    {
        $segments = $this->splitEdifact($raw);

        $result = $this->blank('EANCOM');
        foreach ($segments as $seg) {
            $tag = $seg[0] ?? '';
            switch ($tag) {
                case 'UNB':
                    // UNB+syntax+sender+receiver+datetime+controlref
                    $result['msg_ref'] = $seg[5] ?? ($seg[4] ?? null);
                    break;
                case 'UNH':
                    $result['msg_ref'] = $result['msg_ref'] ?? ($seg[1] ?? null);
                    break;
                case 'BGM':
                    // BGM+docCode+documentNumber+messageFunction
                    $docCode = $this->firstComponent($seg[1] ?? '');
                    $result['ill_number'] = $seg[2] ?? $result['ill_number'];
                    if (isset(self::EDIFACT_STATUS_MAP[$docCode])) {
                        $result['raw_status'] = $docCode;
                        $result['status'] = self::EDIFACT_STATUS_MAP[$docCode];
                    }
                    break;
                case 'STS':
                case 'STA':
                    // status segment - native status word in a component
                    $word = $this->firstComponent($seg[1] ?? '');
                    $mapped = $this->mapWord($word);
                    if ($mapped) {
                        $result['raw_status'] = $word;
                        $result['status'] = $mapped;
                    }
                    break;
                case 'DTM':
                    // DTM+261:yymmdd:102  -> due date qualifier 261
                    $parts = explode(':', $seg[1] ?? '');
                    if (($parts[0] ?? '') === '261' && !empty($parts[1])) {
                        $result['due_date'] = $this->parseEdiDate($parts[1]);
                    }
                    break;
                case 'FTX':
                    $note = $seg[4] ?? ($seg[3] ?? null);
                    if ($note) {
                        $result['note'] = trim((string) $note);
                        $mapped = $this->mapWord($note);
                        if ($mapped && !$result['status']) {
                            $result['raw_status'] = $note;
                            $result['status'] = $mapped;
                        }
                    }
                    break;
            }
        }

        $result['segments'] = $segments;
        return $result;
    }

    protected function splitEdifact(string $raw): array
    {
        // Strip optional UNA service-string advice, then split on the segment
        // terminator (apostrophe), honouring the EDIFACT release char '?'.
        $raw = preg_replace('/^UNA.{6}/', '', trim($raw)) ?? $raw;
        $raw = str_replace(["\r", "\n"], '', $raw);

        $segments = [];
        foreach ($this->splitUnescaped($raw, "'") as $segStr) {
            $segStr = trim($segStr);
            if ($segStr === '') {
                continue;
            }
            $segments[] = $this->splitUnescaped($segStr, '+');
        }
        return $segments;
    }

    /** Split on $sep but skip occurrences preceded by the EDIFACT release '?'. */
    protected function splitUnescaped(string $str, string $sep): array
    {
        $out = [];
        $buf = '';
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $ch = $str[$i];
            if ($ch === '?' && $i + 1 < $len) {
                $buf .= $str[$i + 1];
                $i++;
                continue;
            }
            if ($ch === $sep) {
                $out[] = $buf;
                $buf = '';
                continue;
            }
            $buf .= $ch;
        }
        $out[] = $buf;
        return $out;
    }

    protected function firstComponent(string $element): string
    {
        return explode(':', $element)[0] ?? $element;
    }

    // ── X12 ──────────────────────────────────────────────────────────────────

    protected function decodeX12(string $raw): array
    {
        $result = $this->blank('X12');
        $clean = str_replace(["\r", "\n"], '', trim($raw));
        $segments = array_filter(array_map('trim', explode('~', $clean)), fn($s) => $s !== '');

        $parsed = [];
        foreach ($segments as $segStr) {
            $els = explode('*', $segStr);
            $parsed[] = $els;
            $tag = $els[0] ?? '';
            switch ($tag) {
                case 'ISA':
                    $result['msg_ref'] = isset($els[13]) ? trim($els[13]) : null;
                    break;
                case 'BAK': // PO acknowledgement
                    // BAK*00*AC*ponumber*date ; el[1]=ack type, el[3]=PO number
                    $result['ill_number'] = $els[3] ?? $result['ill_number'];
                    $ackType = strtoupper(trim($els[2] ?? ''));
                    $result['raw_status'] = $ackType;
                    $result['status'] = match ($ackType) {
                        'AC', 'AD' => LibraryIllService::STATUS_REQUESTED, // accepted / accepted with detail
                        'RJ'       => LibraryIllService::STATUS_UNFULFILLED,
                        default    => $result['status'],
                    };
                    break;
                case 'REF':
                    if (($els[1] ?? '') === 'IL' && !empty($els[2])) {
                        $result['ill_number'] = $els[2];
                    }
                    break;
                case 'DTM':
                    if (($els[1] ?? '') === '002' && !empty($els[2])) {
                        $result['due_date'] = $this->parseEdiDate($els[2]);
                    }
                    break;
                case 'ACK': // line item ack status
                    $code = strtoupper(trim($els[1] ?? ''));
                    $result['raw_status'] = $code;
                    $result['status'] = match ($code) {
                        'IA' => LibraryIllService::STATUS_REQUESTED,   // item accepted
                        'IS' => LibraryIllService::STATUS_SHIPPED,     // shipped
                        'IR' => LibraryIllService::STATUS_UNFULFILLED, // item rejected
                        'IB' => LibraryIllService::STATUS_UNFULFILLED, // backordered -> treat as unfulfilled now
                        default => $result['status'],
                    };
                    break;
                case 'NTE':
                case 'MSG':
                    $result['note'] = trim($els[2] ?? ($els[1] ?? ''));
                    break;
            }
        }

        $result['segments'] = $parsed;
        return $result;
    }

    // ── CUSTOM JSON ────────────────────────────────────────────────────────

    protected function decodeCustom(string $raw): array
    {
        $result = $this->blank('CUSTOM');
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return $result;
        }

        $result['msg_ref']    = $data['msg_ref'] ?? ($data['message_ref'] ?? null);
        $result['ill_number'] = $data['ill_number'] ?? null;
        $result['due_date']   = !empty($data['due_date'])
            ? $this->normalizeDate((string) $data['due_date']) : null;
        $result['note']       = $data['note'] ?? ($data['responder_note'] ?? null);

        $native = $data['status'] ?? null;
        if ($native !== null) {
            $result['raw_status'] = (string) $native;
            // Accept either an ISO 10160 word directly or a free-text word.
            $lower = strtolower((string) $native);
            $result['status'] = in_array($lower, LibraryIllService::STATUSES, true)
                ? $lower
                : ($this->mapWord((string) $native) ?? null);
        }

        $result['segments'] = [$data];
        return $result;
    }

    // ── Shared helpers ───────────────────────────────────────────────────────

    protected function mapWord(?string $word): ?string
    {
        if ($word === null) {
            return null;
        }
        $key = strtolower(trim($word));
        if (isset(self::WORD_STATUS_MAP[$key])) {
            return self::WORD_STATUS_MAP[$key];
        }
        foreach (self::WORD_STATUS_MAP as $needle => $status) {
            if (str_contains($key, $needle)) {
                return $status;
            }
        }
        return null;
    }

    /** Parse a yymmdd or yyyymmdd EDI date to Y-m-d. */
    protected function parseEdiDate(string $val): ?string
    {
        $val = preg_replace('/\D/', '', $val) ?? '';
        if (strlen($val) === 6) {
            $y = (int) substr($val, 0, 2);
            $y += $y < 70 ? 2000 : 1900;
            return sprintf('%04d-%s-%s', $y, substr($val, 2, 2), substr($val, 4, 2));
        }
        if (strlen($val) === 8) {
            return substr($val, 0, 4) . '-' . substr($val, 4, 2) . '-' . substr($val, 6, 2);
        }
        return null;
    }

    protected function normalizeDate(string $val): ?string
    {
        try {
            return \Illuminate\Support\Carbon::parse($val)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function blank(string $profile): array
    {
        return [
            'profile'    => $profile,
            'msg_ref'    => null,
            'ill_number' => null,
            'status'     => null,
            'raw_status' => null,
            'due_date'   => null,
            'note'       => null,
            'segments'   => [],
        ];
    }
}
