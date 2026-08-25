<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgResearch\Services;

use AhgCore\Services\SsrfGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fetch a document from an external URL into a research workspace. #1492.
 *
 * Before this, a researcher could RECORD where a source lived - InboxService
 * stores source_url as a string - but nothing ever retrieved it. This brings the
 * document into the pack with provenance attached.
 *
 * This is server-side fetching of a user-supplied URL, i.e. the textbook SSRF
 * shape, so every request goes through AhgCore\Services\SsrfGuard (#1395(C)):
 * every resolved A/AAAA address is checked against private, reserved, loopback
 * and link-local ranges, cloud-metadata endpoints are blocked by name, and
 * integer-host bypasses are normalised. It fails closed.
 *
 * Redirects are followed HERE, one hop at a time, re-asserting the guard on each
 * new location - SsrfGuard::safeHttpOptions() disables curl's own redirect
 * following precisely because a 30x to 127.0.0.1 would otherwise walk straight
 * past a check that already passed.
 */
class ResearchSourceFetchService
{
    /** A redirect chain longer than this is treated as hostile or broken. */
    private const MAX_REDIRECTS = 4;

    private const TIMEOUT_SECONDS = 30;

    /**
     * Content types a research pack should accept. Deliberately an allow-list:
     * a deny-list of "dangerous" types is a losing game, and a research document
     * that is genuinely none of these can still be uploaded by hand.
     */
    private const ALLOWED_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/rtf',
        'application/xml',
        'application/json',
        'text/plain',
        'text/csv',
        'text/html',
        'text/xml',
        'image/jpeg',
        'image/png',
        'image/tiff',
        'image/gif',
        'image/webp',
    ];

    public function __construct(
        private SsrfGuard $ssrf,
        private ResearchQuotaService $quota,
    ) {
    }

    /**
     * Retrieve $url into $workspaceId, returning a result array rather than
     * throwing - the caller is a form post and wants a message, not a stack
     * trace.
     *
     * @return array{ok:bool,error:?string,file_id:?int,file_name:?string,bytes:?int}
     */
    public function fetchToWorkspace(string $url, int $workspaceId, int $researcherId): array
    {
        $url = trim($url);

        try {
            $this->ssrf->assertSafeUrl($url);
        } catch (\Throwable $e) {
            return $this->fail('That URL cannot be fetched: ' . $e->getMessage());
        }

        $maxBytes = $this->quota->maxFetchBytes();

        $tmp = @tempnam(sys_get_temp_dir(), 'ahgfetch_');
        if ($tmp === false) {
            return $this->fail('Could not create a temporary file for the download.');
        }

        try {
            $meta = $this->download($url, $tmp, $maxBytes);
        } catch (\Throwable $e) {
            @unlink($tmp);

            return $this->fail($e->getMessage());
        }

        $bytes = (int) @filesize($tmp);
        if ($bytes <= 0) {
            @unlink($tmp);

            return $this->fail('The source returned an empty document.');
        }

        // Sniff the actual bytes rather than trusting the declared header. A
        // server can claim anything; finfo reads what arrived.
        $detected = $this->detectMime($tmp) ?: $meta['content_type'];
        if (! $this->typeAllowed($detected)) {
            @unlink($tmp);

            return $this->fail('That document type is not accepted (' . $detected . ').');
        }

        // Same storage quota as a hand upload - a fetched file is not a loophole.
        $check = $this->quota->checkStorage($researcherId, $bytes);
        if (! ($check['allowed'] ?? true)) {
            @unlink($tmp);

            return $this->fail($check['message'] ?? 'Storage quota exceeded.');
        }

        $dir = rtrim((string) config('heratio.storage_path'), '/')
            . '/research/workspace/' . $workspaceId . '/';
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            @unlink($tmp);

            return $this->fail('Could not create the workspace storage directory.');
        }

        $fileName = $this->uniqueName($dir, $this->nameFromUrl($meta['final_url'], $detected));
        $dest = $dir . $fileName;

        if (! @rename($tmp, $dest)) {
            // rename fails across filesystems; copy is the fallback.
            if (! @copy($tmp, $dest)) {
                @unlink($tmp);

                return $this->fail('Could not save the downloaded document.');
            }
            @unlink($tmp);
        }
        @chmod($dest, 0664);

        $row = [
            'workspace_id'  => $workspaceId,
            'researcher_id' => $researcherId,
            'file_name'     => $fileName,
            'file_path'     => $dest,
            'file_size'     => $bytes,
            'mime_type'     => $detected,
            'checksum'      => hash_file('sha256', $dest),
            'checksum_type' => 'sha256',
            'created_at'    => now(),
        ];

        // Guarded so an instance that has not run the #1492 migration still
        // fetches - it simply records no provenance rather than throwing on an
        // unknown column. See #1471: CI builds its schema from database/core.
        if (\Illuminate\Support\Facades\Schema::hasColumn('research_workspace_file', 'source_url')) {
            $row['source_url'] = mb_substr($meta['final_url'], 0, 1024);
            $row['fetched_at'] = now();
        }

        $id = DB::table('research_workspace_file')->insertGetId($row);

        Log::info('[ahg-research] fetched external source into workspace', [
            'workspace_id' => $workspaceId,
            'url'          => $meta['final_url'],
            'bytes'        => $bytes,
            'mime'         => $detected,
        ]);

        return ['ok' => true, 'error' => null, 'file_id' => (int) $id, 'file_name' => $fileName, 'bytes' => $bytes];
    }

    /**
     * Stream $url to $sink, following redirects manually and re-asserting the
     * SSRF guard on every hop.
     *
     * @return array{final_url:string,content_type:string}
     */
    private function download(string $url, string $sink, int $maxBytes): array
    {
        $current = $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $this->ssrf->assertSafeUrl($current);

            $fh = @fopen($sink, 'wb');
            if ($fh === false) {
                throw new \RuntimeException('Could not open the temporary file for writing.');
            }

            $ch = curl_init($current);
            $contentType = '';
            $overSize = false;

            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => false,   // handled here, with re-validation
                CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HEADER         => false,
                CURLOPT_USERAGENT      => 'Heratio-Research/1.0 (+document fetch)',
                CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$contentType) {
                    if (stripos($line, 'content-type:') === 0) {
                        $contentType = trim(explode(';', substr($line, 13))[0]);
                    }

                    return strlen($line);
                },
                // Enforce the cap DURING the transfer. Content-Length is a claim
                // the server makes; this counts what actually arrives, so a
                // lying or chunked response cannot exceed the limit.
                CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use ($fh, $maxBytes, &$overSize) {
                    $written = (int) @ftell($fh);
                    if ($written + strlen($chunk) > $maxBytes) {
                        $overSize = true;

                        return 0; // aborts the transfer
                    }

                    return (int) fwrite($fh, $chunk);
                },
            ]);

            $ok     = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $errNo  = curl_errno($ch);
            $errMsg = curl_error($ch);
            $location = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            curl_close($ch);
            fclose($fh);

            if ($overSize) {
                throw new \RuntimeException(
                    'That document is larger than the ' . (int) floor($maxBytes / 1024 / 1024) . ' MB limit.'
                );
            }

            if ($status >= 300 && $status < 400) {
                if ($location === '') {
                    throw new \RuntimeException('The source redirected without a destination.');
                }
                $current = $this->absolutise($location, $current);
                continue;
            }

            if ($ok === false || $errNo !== 0) {
                throw new \RuntimeException('Could not retrieve that URL: ' . ($errMsg ?: 'transfer failed'));
            }

            if ($status < 200 || $status >= 300) {
                throw new \RuntimeException('The source returned HTTP ' . $status . '.');
            }

            return ['final_url' => $current, 'content_type' => $contentType];
        }

        throw new \RuntimeException('That URL redirected too many times.');
    }

    private function absolutise(string $location, string $base): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $p = parse_url($base);
        if (empty($p['scheme']) || empty($p['host'])) {
            throw new \RuntimeException('Could not resolve a relative redirect.');
        }

        $root = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');

        return $root . '/' . ltrim($location, '/');
    }

    private function detectMime(string $path): ?string
    {
        if (! function_exists('finfo_open')) {
            return null;
        }
        $fi = @finfo_open(FILEINFO_MIME_TYPE);
        if ($fi === false) {
            return null;
        }
        $type = @finfo_file($fi, $path);
        finfo_close($fi);

        return $type ?: null;
    }

    private function typeAllowed(?string $type): bool
    {
        return $type !== null && in_array(strtolower(trim($type)), self::ALLOWED_TYPES, true);
    }

    private function nameFromUrl(string $url, ?string $mime): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $base = basename($path);
        $base = preg_replace('/[^A-Za-z0-9_.-]/', '_', (string) $base) ?: '';

        if ($base === '' || $base === '_' || ! str_contains($base, '.')) {
            $ext = match ($mime) {
                'application/pdf' => 'pdf',
                'text/html'       => 'html',
                'text/plain'      => 'txt',
                'text/csv'        => 'csv',
                'image/jpeg'      => 'jpg',
                'image/png'       => 'png',
                default           => 'bin',
            };
            $base = 'source_' . date('YmdHis') . '.' . $ext;
        }

        return mb_substr($base, 0, 200);
    }

    private function uniqueName(string $dir, string $name): string
    {
        if (! file_exists($dir . $name)) {
            return $name;
        }

        $ext  = pathinfo($name, PATHINFO_EXTENSION);
        $stem = $ext !== '' ? substr($name, 0, -(strlen($ext) + 1)) : $name;

        $i = 1;
        do {
            $candidate = $stem . '_' . $i . ($ext !== '' ? '.' . $ext : '');
            $i++;
        } while (file_exists($dir . $candidate));

        return $candidate;
    }

    /** @return array{ok:bool,error:string,file_id:null,file_name:null,bytes:null} */
    private function fail(string $message): array
    {
        return ['ok' => false, 'error' => $message, 'file_id' => null, 'file_name' => null, 'bytes' => null];
    }
}
