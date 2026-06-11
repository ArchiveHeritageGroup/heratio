<?php
/**
 * Heratio - download a digital object WITH C2PA content credentials attached
 * (issue #1201: "content credentials travel with the object on export").
 *
 * A NEW, additive, parallel download path - it does NOT touch the locked IO /
 * media download route. It streams a digital object's master file so the
 * downloaded copy carries verifiable provenance:
 *
 *   - When the native c2patool is present AND the master is an embeddable
 *     container (JPEG/PNG/TIFF/MP4), it builds + signs a C2PA manifest, embeds
 *     it (JUMBF) into a temp copy, streams THAT as an attachment, then deletes
 *     the temp copy after the stream completes.
 *   - Otherwise it streams the ORIGINAL master and advertises a sidecar manifest
 *     via a Link header (+ a custom X-C2PA-* header), so the credentials still
 *     travel out-of-band. A companion route serves that sidecar as
 *     `credentials.c2pa` (canonical signed-manifest JSON).
 *
 * Every path degrades: unknown object or missing file -> 404; an embed failure
 * falls back to the original + sidecar and is logged; it never 500s. It reuses
 * DigitalObjectProvenanceService (master resolution) and C2paService
 * (build/sign/embed) and never shells out to c2patool directly.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Controllers;

use AhgC2pa\Manifest\ManifestBuilder;
use AhgC2pa\Services\C2paService;
use AhgC2pa\Services\DigitalObjectProvenanceService;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class VerifyObjectDownloadController extends Controller
{
    public function __construct(
        private DigitalObjectProvenanceService $resolver,
        private C2paService $c2pa,
    ) {
    }

    /**
     * GET /verify/{digitalObjectId}/download
     *
     * Stream the digital object's master file with content credentials. When
     * embedding is possible the streamed bytes are C2PA-signed; otherwise the
     * original is streamed and a sidecar manifest is advertised in the headers
     * (served by credentials() below). Unknown object / unreadable master -> 404.
     */
    public function download(int $digitalObjectId): StreamedResponse
    {
        $master = $this->resolver->resolveMasterForDownload($digitalObjectId);
        if ($master === null) {
            abort(404);
        }

        $srcPath = $master['path'];
        $mime    = $master['mime'] ?? $this->guessMime($srcPath);

        // Try a native embed into a temp copy so the streamed bytes themselves
        // carry the manifest. Any failure here is non-fatal - we fall through to
        // streaming the original plus a sidecar.
        $embeddedTemp = $this->tryEmbed($digitalObjectId, $srcPath);
        if ($embeddedTemp !== null) {
            return $this->streamFile(
                $embeddedTemp,
                $this->downloadName($master['name'], $srcPath),
                $mime,
                ['X-C2PA-Credentials' => 'embedded'],
                deleteAfter: true,
            );
        }

        // Fallback: stream the original, advertise the sidecar so the
        // credentials still travel out-of-band.
        $sidecarUrl = $this->safeUrl('/verify/' . $digitalObjectId . '/credentials.c2pa');
        $headers = [
            'X-C2PA-Credentials' => 'sidecar',
            'X-C2PA-Manifest'    => $sidecarUrl,
            // RFC 8288 Link header so generic clients can discover the manifest.
            'Link'               => '<' . $sidecarUrl . '>; rel="c2pa-manifest"; type="application/c2pa+json"',
        ];

        return $this->streamFile(
            $srcPath,
            $master['name'],
            $mime,
            $headers,
            deleteAfter: false,
        );
    }

    /**
     * GET /verify/{digitalObjectId}/credentials.c2pa
     *
     * The companion sidecar: a freshly built + signed C2PA manifest for the
     * object's master, served as JSON (application/c2pa+json) so it can travel
     * alongside a downloaded original. Unknown object / unreadable master -> 404.
     * A signing fault -> 404 rather than 500 (no credentials to hand out).
     */
    public function credentials(int $digitalObjectId): Response
    {
        $master = $this->resolver->resolveMasterForDownload($digitalObjectId);
        if ($master === null) {
            abort(404);
        }

        $signed = $this->buildSignedManifest($digitalObjectId, $master['path']);
        if ($signed === null) {
            abort(404);
        }

        // Serve the canonical signed-manifest bytes verbatim (do not re-encode -
        // that would reorder keys and break byte-stable verification).
        $json = ManifestBuilder::toCanonicalJson($signed);

        return response($json, 200, [
            'Content-Type'           => 'application/c2pa+json',
            'Content-Disposition'    => 'attachment; filename="' . $this->sidecarName($master['name']) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'no-store, private',
        ]);
    }

    /* ----------------------------------------------------------------- *
     * Manifest build + embed helpers.
     * ----------------------------------------------------------------- */

    /**
     * Build + sign a manifest for the master, then ask C2paService to embed it
     * into a TEMP copy (never the original). Returns the temp path on success,
     * or null on any degrade (no c2patool, non-embeddable format, build/sign
     * fault, embed failure). Never throws.
     */
    private function tryEmbed(int $digitalObjectId, string $srcPath): ?string
    {
        try {
            if (!$this->c2pa->canEmbed() || !C2paService::isEmbeddableFormat($srcPath)) {
                return null;
            }

            $signed = $this->buildSignedManifest($digitalObjectId, $srcPath);
            if ($signed === null) {
                return null;
            }

            $ext  = strtolower(pathinfo($srcPath, PATHINFO_EXTENSION)) ?: 'bin';
            $dest = $this->tempPath($ext);

            $out = $this->c2pa->embed($srcPath, $signed, $dest);
            if ($out === null || !is_readable($out)) {
                // embed() logs its own reason; clean up a stale temp if any.
                if (is_file($dest)) {
                    @unlink($dest);
                }
                return null;
            }

            return $out;
        } catch (Throwable $e) {
            Log::warning('c2pa: download embed fell back to original', [
                'digital_object_id' => $digitalObjectId,
                'err'               => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Build + Ed25519-sign a C2PA manifest for this digital object's master.
     * Reuses C2paService::manifestForDigitalObject() (which loads any standard
     * EXIF/IPTC/XMP metadata) + signManifest(). Returns null on any fault.
     *
     * @return array<string,mixed>|null
     */
    private function buildSignedManifest(int $digitalObjectId, string $srcPath): ?array
    {
        try {
            $ioId = $this->resolveIoId($digitalObjectId);

            $manifest = $this->c2pa->manifestForDigitalObject(
                $ioId,
                $digitalObjectId,
                $srcPath,
                $this->heratioVersion(),
            );

            return $this->c2pa->signManifest($manifest);
        } catch (Throwable $e) {
            Log::warning('c2pa: download manifest build/sign failed', [
                'digital_object_id' => $digitalObjectId,
                'err'               => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Best-effort owning-IO id for the manifest title. Falls back to the
     * digital object id when the join is unavailable (the manifest only uses it
     * for labelling, never for security).
     */
    private function resolveIoId(int $digitalObjectId): int
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('digital_object')) {
                $row = \Illuminate\Support\Facades\DB::table('digital_object')
                    ->where('id', $digitalObjectId)
                    ->first(['object_id']);
                if ($row !== null && !empty($row->object_id)) {
                    return (int) $row->object_id;
                }
            }
        } catch (Throwable) {
            // fall through
        }
        return $digitalObjectId;
    }

    /* ----------------------------------------------------------------- *
     * Streaming + filename helpers.
     * ----------------------------------------------------------------- */

    /**
     * Stream a file as a download attachment without loading it into memory.
     * When $deleteAfter is true the file is unlinked once the stream completes
     * (used for the embedded temp copy).
     *
     * @param array<string,string> $extraHeaders
     */
    private function streamFile(
        string $path,
        string $downloadName,
        string $mime,
        array $extraHeaders,
        bool $deleteAfter,
    ): StreamedResponse {
        $size = @filesize($path);

        $response = new StreamedResponse(function () use ($path, $deleteAfter): void {
            $handle = @fopen($path, 'rb');
            if ($handle === false) {
                if ($deleteAfter && is_file($path)) {
                    @unlink($path);
                }
                return;
            }
            try {
                while (!feof($handle)) {
                    $chunk = fread($handle, 1024 * 256);
                    if ($chunk === false) {
                        break;
                    }
                    echo $chunk;
                    flush();
                }
            } finally {
                fclose($handle);
                if ($deleteAfter && is_file($path)) {
                    @unlink($path);
                }
            }
        });

        $response->headers->set('Content-Type', $mime);
        $response->headers->set(
            'Content-Disposition',
            'attachment; filename="' . $this->asciiFilename($downloadName) . '"'
                . "; filename*=UTF-8''" . rawurlencode($downloadName),
        );
        if (is_int($size) && $size > 0) {
            $response->headers->set('Content-Length', (string) $size);
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'no-store, private');
        foreach ($extraHeaders as $k => $v) {
            $response->headers->set($k, $v);
        }

        return $response;
    }

    /**
     * Filename for an embedded download: keep the original stem but reflect that
     * the bytes now carry credentials (e.g. "scan.jpg" -> "scan.c2pa.jpg").
     */
    private function downloadName(string $originalName, string $srcPath): string
    {
        $ext = strtolower(pathinfo($srcPath, PATHINFO_EXTENSION));
        $stem = pathinfo($originalName !== '' ? $originalName : basename($srcPath), PATHINFO_FILENAME);
        if ($stem === '') {
            $stem = 'download';
        }
        return $ext !== '' ? "{$stem}.c2pa.{$ext}" : "{$stem}.c2pa";
    }

    private function sidecarName(string $originalName): string
    {
        $stem = pathinfo($originalName !== '' ? $originalName : 'download', PATHINFO_FILENAME);
        if ($stem === '') {
            $stem = 'download';
        }
        return $stem . '.c2pa';
    }

    /**
     * Strip a filename to a safe ASCII fallback for the legacy filename= param
     * (the UTF-8 filename* param carries the real name for modern clients).
     */
    private function asciiFilename(string $name): string
    {
        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?? 'download';
        $ascii = trim($ascii, '_');
        return $ascii === '' ? 'download' : $ascii;
    }

    private function tempPath(string $ext): string
    {
        $base = function_exists('sys_get_temp_dir') ? sys_get_temp_dir() : '/tmp';
        return rtrim($base, '/') . '/c2pa-dl-' . bin2hex(random_bytes(8)) . '.' . $ext;
    }

    private function guessMime(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'tif', 'tiff' => 'image/tiff',
            'jp2'         => 'image/jp2',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            'pdf'         => 'application/pdf',
            'mp4'         => 'video/mp4',
            'mp3'         => 'audio/mpeg',
            default       => 'application/octet-stream',
        };
    }

    private function heratioVersion(): string
    {
        try {
            if (function_exists('base_path')) {
                $path = base_path('version.json');
                if (is_readable($path)) {
                    $data = json_decode((string) file_get_contents($path), true);
                    if (is_array($data) && isset($data['version'])) {
                        return (string) $data['version'];
                    }
                }
            }
        } catch (Throwable) {
            // fall through
        }
        return 'unknown';
    }

    private function safeUrl(string $path): string
    {
        if (function_exists('url')) {
            try {
                return (string) url($path);
            } catch (Throwable) {
                // fall through
            }
        }
        return $path;
    }
}
