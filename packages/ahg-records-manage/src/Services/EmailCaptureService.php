<?php

/**
 * EmailCaptureService — capture business email as records (Phase 2.6).
 *
 * MVP scope: EML file upload (single message per file). Each captured email lands
 * in rm_email_capture with the original .eml saved to storage for forensic
 * preservation. Subsequent commits add IMAP polling and MSG (Outlook) parsing.
 *
 * From there an officer can:
 *   - classify the email to a file plan node + disposal class
 *   - declare it as an information_object record (full RM lifecycle applies)
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgRecordsManage\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class EmailCaptureService
{
    /**
     * @return array{rows: array, total: int}
     */
    public function listQueue(array $filters = []): array
    {
        $q = DB::table('rm_email_capture as e')
            ->leftJoin('rm_fileplan_node as fp', 'fp.id', '=', 'e.fileplan_node_id')
            ->leftJoin('rm_disposal_class as dc', 'dc.id', '=', 'e.disposal_class_id')
            ->select(
                'e.id', 'e.message_id', 'e.from_address', 'e.subject', 'e.sent_at',
                'e.attachment_count', 'e.information_object_id', 'e.fileplan_node_id',
                'e.disposal_class_id', 'e.capture_source', 'e.status',
                'e.created_at',
                'fp.code as fileplan_code', 'fp.title as fileplan_title',
                'dc.class_ref as disposal_class_ref', 'dc.title as disposal_class_title'
            );

        if (! empty($filters['status'])) {
            $q->where('e.status', $filters['status']);
        }
        if (! empty($filters['source'])) {
            $q->where('e.capture_source', $filters['source']);
        }
        if (! empty($filters['q'])) {
            $term = '%' . $filters['q'] . '%';
            $q->where(function ($w) use ($term) {
                $w->where('e.subject', 'like', $term)
                  ->orWhere('e.from_address', 'like', $term)
                  ->orWhere('e.to_addresses', 'like', $term);
            });
        }
        if (! empty($filters['fileplan_node_id'])) {
            $q->where('e.fileplan_node_id', (int) $filters['fileplan_node_id']);
        }

        $total = (clone $q)->count();
        $rows  = $q->orderByDesc('e.sent_at')
            ->orderByDesc('e.id')
            ->limit($filters['limit'] ?? 100)
            ->offset($filters['offset'] ?? 0)
            ->get()
            ->all();

        return ['rows' => $rows, 'total' => $total];
    }

    public function get(int $id): ?object
    {
        return DB::table('rm_email_capture as e')
            ->leftJoin('rm_fileplan_node as fp', 'fp.id', '=', 'e.fileplan_node_id')
            ->leftJoin('rm_disposal_class as dc', 'dc.id', '=', 'e.disposal_class_id')
            ->select(
                'e.*',
                'fp.code as fileplan_code', 'fp.title as fileplan_title',
                'dc.class_ref as disposal_class_ref', 'dc.title as disposal_class_title'
            )
            ->where('e.id', $id)
            ->first();
    }

    /**
     * Capture an EML file upload. Stores the original file under the configured
     * storage path and inserts a parsed row into rm_email_capture.
     *
     * @return array{id: int, message_id: string, duplicate: bool}
     */
    public function captureFromEml(UploadedFile $file, int $userId): array
    {
        $raw = file_get_contents($file->getRealPath());
        if ($raw === false || trim($raw) === '') {
            throw new \RuntimeException('EML file is empty or unreadable');
        }

        $parsed = $this->parseEml($raw);

        // Idempotency: if this message_id is already captured, return early.
        if ($parsed['message_id'] !== '') {
            $existing = DB::table('rm_email_capture')->where('message_id', $parsed['message_id'])->value('id');
            if ($existing) {
                return ['id' => (int) $existing, 'message_id' => $parsed['message_id'], 'duplicate' => true];
            }
        } else {
            // Synthesise one when the EML has no Message-ID header.
            $parsed['message_id'] = 'heratio-capture-' . Str::uuid()->toString();
        }

        // Persist the original .eml under the configured storage path.
        $storagePath = $this->saveEmlBlob($parsed['message_id'], $raw);

        $id = DB::table('rm_email_capture')->insertGetId([
            'message_id'       => $parsed['message_id'],
            'from_address'     => $parsed['from'],
            'to_addresses'     => $parsed['to'],
            'cc_addresses'     => $parsed['cc'],
            'subject'          => $parsed['subject'],
            'sent_at'          => $parsed['sent_at'],
            'received_at'      => $parsed['received_at'],
            'body_text'        => $parsed['body_text'],
            'body_html'        => $parsed['body_html'],
            'attachment_count' => $parsed['attachment_count'],
            'eml_storage_path' => $storagePath,
            'capture_source'   => 'eml_upload',
            'status'           => 'captured',
            'captured_by'      => $userId,
        ]);

        Log::info('rm: email captured', [
            'id'         => $id,
            'message_id' => $parsed['message_id'],
            'from'       => $parsed['from'],
            'subject'    => $parsed['subject'],
        ]);

        return ['id' => (int) $id, 'message_id' => $parsed['message_id'], 'duplicate' => false];
    }

    /**
     * Classify a captured email to a file plan node and (optional) disposal class.
     */
    public function classify(int $id, int $fileplanNodeId, ?int $disposalClassId, int $userId): bool
    {
        $update = ['fileplan_node_id' => $fileplanNodeId, 'status' => 'classified'];
        if ($disposalClassId !== null) {
            $update['disposal_class_id'] = $disposalClassId;
        }

        $ok = DB::table('rm_email_capture')->where('id', $id)->update($update) > 0;
        if ($ok) {
            Log::info('rm: email classified', ['id' => $id, 'node' => $fileplanNodeId, 'user_id' => $userId]);
        }
        return $ok;
    }

    /**
     * Declare a captured email as a record (information_object).
     *
     * Follows Heratio's canonical Qubit class-table-inheritance pattern:
     *
     *   1. Insert into `object` (parent)        — owns class_name + created_at
     *   2. Insert into `information_object`     — sub-class fields, lft/rgt at end of root
     *   3. Insert into `information_object_i18n`— title + scope_and_content
     *   4. Insert into `slug`                   — public URL component
     *   5. If the email was classified, link disposal class via rm_record_disposal_class
     *   6. Mark rm_email_capture.status=declared and back-link information_object_id
     *
     * Wrapped in a transaction so a partial failure doesn't leave orphan rows.
     *
     * @return int|null new information_object.id, or null on failure
     */
    public function declareAsRecord(int $id, int $userId): ?int
    {
        $email = $this->get($id);
        if (! $email) {
            return null;
        }
        if ($email->information_object_id) {
            return (int) $email->information_object_id;
        }

        try {
            return DB::transaction(function () use ($email, $id, $userId) {
                $title  = $email->subject ?: '[No subject]';
                $now    = now();
                $culture = 'en';

                // 1. object (Qubit class-table inheritance parent)
                $objectId = (int) DB::table('object')->insertGetId([
                    'class_name'    => 'QubitInformationObject',
                    'created_at'    => $now,
                    'updated_at'    => $now,
                    'serial_number' => 0,
                ]);

                // Determine lft/rgt — append as last child of root (id=1).
                $root = DB::table('information_object')->where('id', 1)->select('rgt')->first();
                $newLft = $root ? (int) $root->rgt : 0;
                $newRgt = $newLft + 1;
                if ($root) {
                    // Shift existing nested-set values to make room for the new leaf.
                    DB::table('information_object')->where('rgt', '>=', $root->rgt)->increment('rgt', 2);
                    DB::table('information_object')->where('lft', '>', $root->rgt)->increment('lft', 2);
                }

                // 2. information_object
                DB::table('information_object')->insert([
                    'id'             => $objectId,
                    'identifier'     => 'EMAIL-' . $id,
                    'parent_id'      => 1,
                    'lft'            => $newLft,
                    'rgt'            => $newRgt,
                    'source_culture' => $culture,
                ]);

                // 3. information_object_i18n
                DB::table('information_object_i18n')->insert([
                    'id'                => $objectId,
                    'culture'           => $culture,
                    'title'             => $title,
                    'scope_and_content' => $this->summariseForScope($email),
                ]);

                // 4. slug — uniqueness via incrementing suffix
                $baseSlug = Str::slug($title) ?: ('email-' . $id);
                $slug = $baseSlug;
                $counter = 1;
                while (DB::table('slug')->where('slug', $slug)->exists()) {
                    $slug = $baseSlug . '-' . $counter;
                    $counter++;
                }
                DB::table('slug')->insert([
                    'object_id' => $objectId,
                    'slug'      => $slug,
                ]);

                // 5. If classified, link disposal class to the new IO.
                if ($email->disposal_class_id) {
                    $startDate = substr((string) ($email->sent_at ?? $email->received_at ?? $now->toDateTimeString()), 0, 10) ?: $now->toDateString();
                    DB::table('rm_record_disposal_class')->insert([
                        'information_object_id' => $objectId,
                        'disposal_class_id'     => $email->disposal_class_id,
                        'assigned_by'           => $userId,
                        'retention_start_date'  => $startDate,
                        'created_at'            => $now,
                    ]);
                }

                // 6. Back-link the email row.
                DB::table('rm_email_capture')->where('id', $id)->update([
                    'information_object_id' => $objectId,
                    'status'                => 'declared',
                ]);

                Log::info('rm: email declared as record', [
                    'email_id' => $id,
                    'io_id'    => $objectId,
                    'slug'     => $slug,
                    'user_id'  => $userId,
                ]);

                return $objectId;
            });
        } catch (Throwable $e) {
            Log::error('rm: declareAsRecord failed', ['email_id' => $id, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function counts(): array
    {
        $base = DB::table('rm_email_capture');
        return [
            'total'      => (clone $base)->count(),
            'captured'   => (clone $base)->where('status', 'captured')->count(),
            'classified' => (clone $base)->where('status', 'classified')->count(),
            'declared'   => (clone $base)->where('status', 'declared')->count(),
        ];
    }

    /* -------------------------------------------------------------------- */
    /*  EML parsing                                                          */
    /* -------------------------------------------------------------------- */

    /**
     * Hand-rolled EML parser for the MVP. Handles common cases (headers,
     * single-part body, simple multipart). Not a full MIME implementation.
     *
     * @return array{message_id:string, from:string, to:string, cc:string, subject:string,
     *               sent_at:?string, received_at:?string, body_text:?string,
     *               body_html:?string, attachment_count:int}
     */
    public function parseEml(string $raw): array
    {
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        [$headerBlock, $body] = array_pad(explode("\n\n", $raw, 2), 2, '');

        $headers = $this->parseHeaders($headerBlock);

        $sentAt = $this->parseHeaderDate($headers['date'] ?? null);
        // First Received: header has the most reliable arrival timestamp.
        $receivedAt = null;
        if (! empty($headers['received'])) {
            $first = is_array($headers['received']) ? $headers['received'][0] : $headers['received'];
            if (preg_match('/;\s*(.+)$/', $first, $m)) {
                $receivedAt = $this->parseHeaderDate($m[1]);
            }
        }

        $contentType = strtolower($headers['content-type'] ?? 'text/plain');
        $bodyText = null;
        $bodyHtml = null;
        $attachmentCount = 0;

        if (str_starts_with($contentType, 'multipart/') && preg_match('/boundary="?([^";]+)"?/', $headers['content-type'] ?? '', $m)) {
            $boundary = $m[1];
            $parts = $this->splitMultipart($body, $boundary);
            foreach ($parts as $part) {
                [$partHeaderBlock, $partBody] = array_pad(explode("\n\n", $part, 2), 2, '');
                $partHeaders = $this->parseHeaders($partHeaderBlock);
                $partType = strtolower($partHeaders['content-type'] ?? 'text/plain');
                $disposition = strtolower($partHeaders['content-disposition'] ?? '');

                if (str_contains($disposition, 'attachment')) {
                    $attachmentCount++;
                    continue;
                }
                if (str_starts_with($partType, 'text/plain') && $bodyText === null) {
                    $bodyText = $this->decodePart($partBody, $partHeaders);
                } elseif (str_starts_with($partType, 'text/html') && $bodyHtml === null) {
                    $bodyHtml = $this->decodePart($partBody, $partHeaders);
                }
            }
        } else {
            // Single-part body.
            if (str_starts_with($contentType, 'text/html')) {
                $bodyHtml = $this->decodePart($body, $headers);
            } else {
                $bodyText = $this->decodePart($body, $headers);
            }
        }

        return [
            'message_id'       => trim((string) ($headers['message-id'] ?? ''), " <>\t\r\n"),
            'from'             => $this->cleanAddress($headers['from'] ?? ''),
            'to'               => $this->cleanAddress($headers['to'] ?? ''),
            'cc'               => $this->cleanAddress($headers['cc'] ?? ''),
            'subject'          => $this->decodeHeader($headers['subject'] ?? ''),
            'sent_at'          => $sentAt,
            'received_at'      => $receivedAt,
            'body_text'        => $bodyText,
            'body_html'        => $bodyHtml,
            'attachment_count' => $attachmentCount,
        ];
    }

    /**
     * Returns lowercased header keys; multi-occurrence headers (Received:) become arrays.
     */
    private function parseHeaders(string $block): array
    {
        $headers = [];
        $current = '';
        foreach (explode("\n", $block) as $line) {
            if ($line === '') {
                continue;
            }
            // RFC 2822 continuation: starts with whitespace.
            if (preg_match('/^\s/', $line) && $current !== '') {
                $headers[$current] = (is_array($headers[$current]) ? end($headers[$current]) : $headers[$current]) . ' ' . trim($line);
                continue;
            }
            if (! preg_match('/^([!-~]+):\s*(.*)$/', $line, $m)) {
                continue;
            }
            $key = strtolower($m[1]);
            $value = $m[2];
            if (isset($headers[$key])) {
                $headers[$key] = (array) $headers[$key];
                $headers[$key][] = $value;
            } else {
                $headers[$key] = $value;
            }
            $current = $key;
        }
        return $headers;
    }

    private function splitMultipart(string $body, string $boundary): array
    {
        $delim = '--' . $boundary;
        $segments = explode($delim, $body);
        // First segment is preamble, last segment is "--\n" closing — drop both.
        array_shift($segments);
        if (! empty($segments) && trim((string) end($segments)) === '--') {
            array_pop($segments);
        }
        return array_map(fn($s) => ltrim($s, "\n"), $segments);
    }

    private function decodePart(string $body, array $headers): string
    {
        $encoding = strtolower(trim($headers['content-transfer-encoding'] ?? ''));
        $decoded = match ($encoding) {
            'base64'           => base64_decode(preg_replace('/\s+/', '', $body)) ?: $body,
            'quoted-printable' => quoted_printable_decode($body),
            default            => $body,
        };
        // Best-effort UTF-8 normalisation.
        if (! mb_check_encoding($decoded, 'UTF-8')) {
            $decoded = mb_convert_encoding($decoded, 'UTF-8', mb_detect_encoding($decoded, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true) ?: 'UTF-8');
        }
        return $decoded;
    }

    private function decodeHeader(string $value): string
    {
        // RFC 2047 encoded-word decoding (e.g. =?UTF-8?B?...?=).
        if (function_exists('iconv_mime_decode')) {
            $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            if (is_string($decoded) && $decoded !== '') {
                return trim($decoded);
            }
        }
        return trim($value);
    }

    private function cleanAddress(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $this->decodeHeader($value)));
    }

    private function parseHeaderDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        $ts = strtotime($value);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    private function saveEmlBlob(string $messageId, string $raw): string
    {
        $base = rtrim(config('heratio.storage_path', storage_path('app')), '/');
        $dir  = $base . '/rm/email-capture/' . date('Y/m');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $messageId);
        $path = $dir . '/' . substr($safe, 0, 200) . '.eml';
        @file_put_contents($path, $raw);
        return $path;
    }

    private function summariseForScope(object $email): string
    {
        $parts = [];
        if (! empty($email->from_address)) {
            $parts[] = 'From: ' . $email->from_address;
        }
        if (! empty($email->to_addresses)) {
            $parts[] = 'To: ' . $email->to_addresses;
        }
        if (! empty($email->sent_at)) {
            $parts[] = 'Sent: ' . $email->sent_at;
        }
        $excerpt = trim((string) ($email->body_text ?? strip_tags((string) $email->body_html)));
        if ($excerpt !== '') {
            $parts[] = '--- Body excerpt ---';
            $parts[] = mb_strlen($excerpt) > 1500 ? mb_substr($excerpt, 0, 1500) . "\n…[truncated]" : $excerpt;
        }
        return implode("\n", $parts);
    }
}
