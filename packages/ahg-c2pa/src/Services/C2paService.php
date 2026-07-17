<?php
/**
 * Heratio - high-level C2PA orchestration: build, sign, embed/sidecar, persist.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Services;

use AhgC2pa\Manifest\Assertion;
use AhgC2pa\Manifest\C2paSigner;
use AhgC2pa\Manifest\ManifestBuilder;
use AhgC2pa\Manifest\StandardMetadataLoader;
use AhgInferenceReceipts\Signer as ReceiptSigner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Service entry point used by Heratio code that wants to attach a C2PA
 * manifest to AI output. All four ways in:
 *
 *   manifestForAiSuggestion()  - assemble + return unsigned manifest dict
 *   signManifest()             - sign it
 *   sidecar()                  - write the .c2pa.json next to an artefact
 *   embed()                    - shell out to the native c2patool to embed
 *                                JUMBF into JPEG/PNG/TIFF/MP4; returns null
 *                                (degrades) when the binary is absent
 *   embedInJpeg()              - legacy wrapper: embed() then sidecar fallback
 *
 * Every emitted manifest is persisted to ahg_c2pa_manifest for audit + reissue.
 */
final class C2paService
{
    /**
     * @param string|null $c2paToolBinary path to the c2pa-tools CLI, or null to auto-detect
     */
    public function __construct(
        private ReceiptSigner $receiptSigner,
        private ?string $c2paToolBinary = null,
    ) {
        if ($this->c2paToolBinary === null) {
            $this->c2paToolBinary = self::autodetectBinary();
        }
    }

    /**
     * Resolve the native c2patool binary. Prefers the configured path
     * (config('heratio.c2patool_bin'), default /usr/local/bin/c2patool) and
     * falls back to a small PATH probe so the package keeps working on hosts
     * that installed the tool somewhere else. Returns null when no usable
     * binary is found - the embed paths degrade to sidecars rather than fail.
     */
    private static function autodetectBinary(): ?string
    {
        // Config-first: an explicit, env-overridable host path.
        if (function_exists('config')) {
            $configured = config('heratio.c2patool_bin');
            if (is_string($configured) && $configured !== '' && is_executable($configured)) {
                return $configured;
            }
        }

        foreach (['/usr/local/bin/c2patool', '/usr/bin/c2patool'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }
        $which = @shell_exec('command -v c2patool 2>/dev/null');
        if (is_string($which) && trim($which) !== '') {
            return trim($which);
        }
        return null;
    }

    /**
     * Public read-only accessor for the resolved c2patool path (or null when
     * the binary is absent). Used by the embed command and capability reports.
     */
    public function toolBinary(): ?string
    {
        return $this->c2paToolBinary;
    }

    /**
     * Whether native media embedding is available on this host.
     */
    public function canEmbed(): bool
    {
        return $this->c2paToolBinary !== null;
    }

    /**
     * Build (unsigned) an AI-suggestion manifest. Returns the dict suitable
     * for signManifest().
     *
     * @return array<string,mixed>
     */
    public function manifestForAiSuggestion(
        int $informationObjectId,
        string $action,
        string $modelId,
        ?string $modelVersion,
        string $output,
        ?string $assetPath = null,
        ?string $heratioVersion = null,
        ?int $digitalObjectId = null,
    ): array {
        if (!in_array($action, ['ai-generated', 'ai-assisted'], true)) {
            throw new RuntimeException("C2paService: action must be ai-generated or ai-assisted, got '{$action}'");
        }

        $builder = (new ManifestBuilder())
            ->withTitle("Heratio AI {$action} for IO #{$informationObjectId}")
            ->withFormat($assetPath !== null ? self::mimeOfFile($assetPath) : 'text/plain')
            ->withClaimGenerator('Heratio/' . ($heratioVersion ?? 'unknown') . ' c2pa-php/1.0')
            ->addAssertion(Assertion::action($action, [
                'model_id'        => $modelId,
                'model_version'   => $modelVersion,
                'output_sha256'   => hash('sha256', $output),
                'heratio_io_id'   => $informationObjectId,
                'heratioVersion'  => $heratioVersion ?? 'unknown',
            ]))
            ->addAssertion(Assertion::trainingMining(
                permitted: false,
                reason: 'AI-derived artefact in archival custody; downstream training requires explicit licence',
            ));

        // Attach stds.exif / stds.iptc / stds.xmp when this AI run is
        // anchored to a digital object whose sidecar metadata is on file.
        // Empty payloads are silently skipped by the loader.
        if ($digitalObjectId !== null) {
            $builder->withStandardMetadata($digitalObjectId, $informationObjectId);
        }

        if ($assetPath !== null) {
            $builder->withAssetFile($assetPath);
        } else {
            $builder->withAssetString($output);
        }

        return $builder->build();
    }

    /**
     * Build an unsigned C2PA manifest that wraps a digital object's host
     * file plus its three Standard Metadata Assertions. Used by the DAM
     * upload path to sign an asset's embedded EXIF/IPTC/XMP into the C2PA
     * chain even when no AI run is involved.
     *
     * @return array<string,mixed>
     */
    public function manifestForDigitalObject(
        int $informationObjectId,
        int $digitalObjectId,
        string $assetPath,
        ?string $heratioVersion = null,
        ?StandardMetadataLoader $loader = null,
    ): array {
        if (!is_readable($assetPath)) {
            throw new RuntimeException("C2paService: asset not readable: {$assetPath}");
        }

        $builder = (new ManifestBuilder())
            ->withTitle("Heratio digital object #{$digitalObjectId} (IO #{$informationObjectId})")
            ->withFormat(self::mimeOfFile($assetPath))
            ->withClaimGenerator('Heratio/' . ($heratioVersion ?? 'unknown') . ' c2pa-php/1.0')
            ->withAssetFile($assetPath)
            ->withStandardMetadata($digitalObjectId, $informationObjectId, $loader);

        // ManifestBuilder requires at least one assertion. If the sidecar
        // tables had nothing for this object we still want to be able to
        // sign the file, so we fall back to a minimal "edited" action.
        $built = $builder->build();
        if ($built['assertions'] === []) {
            $builder->addAssertion(Assertion::action('placed', [
                'softwareAgent' => ['name' => 'Heratio', 'version' => $heratioVersion ?? 'unknown'],
            ]));
            $built = $builder->build();
        }
        return $built;
    }

    /**
     * Verify a signed manifest end-to-end: re-hash every assertion against
     * its claim-pinned hash, then verify the Ed25519 claim signature under
     * the resolver-supplied public key.
     *
     * The verifier intentionally tolerates unknown assertion labels
     * (forward-compat with future C2PA additions). It validates the
     * hash binding and the signature, and the existence of the well-known
     * top-level keys; it does not interpret label-specific semantics.
     *
     * @param array<string,mixed> $signedManifest as loaded from a .c2pa.json sidecar
     * @param callable(string $kid): ?string $publicKeyResolver returns raw 32-byte key
     * @return array{ok:bool, errors:list<string>, assertion_hashes:array<string,string>}
     */
    public static function verify(array $signedManifest, callable $publicKeyResolver): array
    {
        $errors = [];
        $assertionHashes = [];

        $assertions = $signedManifest['assertions'] ?? null;
        $claimRefs  = $signedManifest['claim']['assertions'] ?? null;

        if (!is_array($assertions)) {
            $errors[] = 'missing assertions array';
        }
        if (!is_array($claimRefs)) {
            $errors[] = 'missing claim.assertions array';
        }

        if (is_array($assertions) && is_array($claimRefs)) {
            foreach ($assertions as $i => $a) {
                if (!is_array($a) || !isset($a['label'], $a['data'])) {
                    $errors[] = "assertion #{$i}: missing label or data";
                    continue;
                }
                try {
                    $obj = new Assertion(
                        (string) $a['label'],
                        is_array($a['data']) ? $a['data'] : [],
                        (int) ($a['instance'] ?? 1),
                    );
                } catch (Throwable $e) {
                    $errors[] = "assertion #{$i}: " . $e->getMessage();
                    continue;
                }
                $hash = $obj->hashHex();
                $assertionHashes[$obj->uri()] = $hash;

                $found = false;
                foreach ($claimRefs as $ref) {
                    if (!is_array($ref)) {
                        continue;
                    }
                    if (($ref['url'] ?? null) === $obj->uri()) {
                        $found = true;
                        if (($ref['hash'] ?? null) !== $hash) {
                            $errors[] = "assertion {$obj->uri()}: hash mismatch (label '{$obj->label}' tampered)";
                        }
                        break;
                    }
                }
                if (!$found) {
                    $errors[] = "assertion {$obj->uri()}: not referenced by claim";
                }
            }
        }

        $sigOk = false;
        try {
            $sigOk = C2paSigner::verify($signedManifest, $publicKeyResolver);
        } catch (Throwable $e) {
            $errors[] = 'signature: ' . $e->getMessage();
        }
        if (!$sigOk) {
            $errors[] = 'claim signature did not verify';
        }

        return [
            'ok'                => $errors === [],
            'errors'            => $errors,
            'assertion_hashes'  => $assertionHashes,
        ];
    }

    /**
     * Sign a manifest.
     *
     * @param array<string,mixed> $manifest from manifestForAiSuggestion() or ManifestBuilder::build()
     * @return array<string,mixed> {manifest_label, assertions, claim, claim_signature}
     */
    public function signManifest(array $manifest): array
    {
        $claimObj = $manifest['_claim_object'] ?? null;
        if (!$claimObj instanceof \AhgC2pa\Manifest\Claim) {
            throw new RuntimeException('C2paService: manifest missing _claim_object; was it built by ManifestBuilder?');
        }

        $signer = new C2paSigner($this->receiptSigner);
        $signed = $signer->sign($claimObj);

        return [
            'manifest_label'  => $manifest['manifest_label'],
            'assertions'      => $manifest['assertions'],
            'claim'           => $signed['claim'],
            'claim_signature' => $signed['claim_signature'],
        ];
    }

    /**
     * Write a signed manifest as `<artefactPath>.c2pa.json`. Always works.
     *
     * @param array<string,mixed> $signedManifest
     * @return string absolute path of the sidecar file
     */
    public function sidecar(array $signedManifest, string $artefactPath): string
    {
        $sidecarPath = $artefactPath . '.c2pa.json';
        $dir = dirname($sidecarPath);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("C2paService: cannot create sidecar directory {$dir}");
        }

        $json = ManifestBuilder::toCanonicalJson($signedManifest);
        if (@file_put_contents($sidecarPath, $json, LOCK_EX) === false) {
            throw new RuntimeException("C2paService: cannot write sidecar to {$sidecarPath}");
        }

        return $sidecarPath;
    }

    /**
     * c2patool can write a C2PA manifest into these container formats. PDFs are
     * embedded separately via {@see embedInPdf()} (associated file, not c2patool);
     * other extensions (raw text, glTF, ...) remain sidecar-only.
     */
    private const EMBEDDABLE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'tif', 'tiff', 'mp4'];

    /**
     * Whether the native c2patool can embed a manifest into this file's
     * container format (by extension). JPEG/PNG/TIFF/MP4 are supported.
     */
    public static function isEmbeddableFormat(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, self::EMBEDDABLE_EXTENSIONS, true);
    }

    /**
     * Embed a signed manifest into a media file via the native c2patool CLI.
     * Works for JPEG/PNG/TIFF/MP4 (the formats c2patool can write JUMBF into).
     *
     * Degrades gracefully - returns null (and logs) rather than throwing when:
     *   - the c2patool binary is not installed,
     *   - the source file is unreadable,
     *   - the source is not an embeddable container format, or
     *   - the c2patool invocation fails.
     *
     * The caller decides whether to fall back to sidecar() (see embedInJpeg()
     * for the legacy contract that always returns a path).
     *
     * @param string              $srcPath  absolute path to the media file to sign
     * @param array<string,mixed> $manifest a signed manifest (signManifest() output)
     * @param string|null         $destPath where to write the embedded copy; defaults
     *                                       to <src>.c2pa.<ext> next to the source
     * @return string|null absolute path of the embedded artefact, or null on degrade
     */
    public function embed(string $srcPath, array $manifest, ?string $destPath = null): ?string
    {
        if ($this->c2paToolBinary === null) {
            Log::info('c2pa: c2patool not installed; embed skipped', ['src' => $srcPath]);
            return null;
        }
        if (!is_readable($srcPath)) {
            Log::warning('c2pa: embed source not readable; skipped', ['src' => $srcPath]);
            return null;
        }
        if (!self::isEmbeddableFormat($srcPath)) {
            Log::info('c2pa: format not embeddable by c2patool; sidecar-only', [
                'src' => $srcPath,
                'ext' => strtolower(pathinfo($srcPath, PATHINFO_EXTENSION)),
            ]);
            return null;
        }

        if ($destPath === null) {
            $ext = strtolower(pathinfo($srcPath, PATHINFO_EXTENSION));
            $destPath = preg_replace('/\.[^.]+$/', '.c2pa.' . $ext, $srcPath) ?: ($srcPath . '.c2pa.' . $ext);
            if ($destPath === $srcPath) {
                $destPath = $srcPath . '.c2pa.' . $ext;
            }
        }

        $manifestPath = tempnam(sys_get_temp_dir(), 'c2pa-manifest-') ?: '/tmp/c2pa-manifest-' . bin2hex(random_bytes(4));
        if (@file_put_contents($manifestPath, ManifestBuilder::toCanonicalJson($manifest)) === false) {
            Log::warning('c2pa: could not stage manifest for embed', ['src' => $srcPath]);
            return null;
        }

        // c2patool overwrites $destPath only with --force; remove a stale one first.
        if (is_file($destPath)) {
            @unlink($destPath);
        }

        $cmd = sprintf(
            '%s %s --manifest %s --output %s --force 2>&1',
            escapeshellcmd($this->c2paToolBinary),
            escapeshellarg($srcPath),
            escapeshellarg($manifestPath),
            escapeshellarg($destPath),
        );

        $exit = 0;
        $output = [];
        exec($cmd, $output, $exit);
        @unlink($manifestPath);

        if ($exit !== 0 || !is_readable($destPath)) {
            Log::warning('c2pa: c2patool embed failed', [
                'src'  => $srcPath,
                'exit' => $exit,
                'out'  => implode("\n", $output),
            ]);
            return null;
        }

        return $destPath;
    }

    /**
     * Embed a manifest in a JPEG via c2patool. Legacy convenience wrapper kept
     * for callers that always want a path back: on any degrade (binary absent,
     * non-JPEG, embed failure) it falls back to writing a sidecar and returns
     * the sidecar path (caller can detect this by the .c2pa.json extension).
     *
     * @param array<string,mixed> $signedManifest
     * @return string absolute path of the produced artefact (embedded .jpg or sidecar .c2pa.json)
     */
    public function embedInJpeg(string $imagePath, array $signedManifest): string
    {
        if (!is_readable($imagePath)) {
            throw new RuntimeException("C2paService: input image not readable: {$imagePath}");
        }

        $embedded = $this->embed($imagePath, $signedManifest);
        if ($embedded !== null) {
            return $embedded;
        }

        return $this->sidecar($signedManifest, $imagePath);
    }

    /**
     * #1387 - embed the signed manifest INSIDE a PDF (not just a sidecar), as a
     * C2PA-associated embedded file (/AFRelationship /C2PA_Manifest) via an
     * append-only incremental update. The original bytes are untouched and the
     * manifest is a standard PDF attachment (never a page-content object), so
     * the document renders identically in every viewer; the credential simply
     * rides inside the file and Heratio can read it back with no sidecar.
     *
     * Deliberately conservative - returns null (caller falls back to sidecar)
     * for anything it cannot modify without risk: non-PDF, compressed xref
     * stream (PDF 1.5+), encrypted, already-signed, or a non-trivial catalog.
     * Every output is re-parsed (round-tripped) before it is accepted.
     *
     * @param array<string,mixed> $signedManifest signManifest() output
     * @return string|null absolute path of the embedded PDF, or null to degrade
     */
    public function embedInPdf(string $srcPath, array $signedManifest, ?string $destPath = null): ?string
    {
        if (!is_readable($srcPath)) {
            return null;
        }
        $pdf = @file_get_contents($srcPath);
        if ($pdf === false || strncmp($pdf, '%PDF-', 5) !== 0) {
            return null;
        }

        // --- guards: only classic-xref, unsigned, unencrypted, simple catalog ---
        if (!preg_match_all('/startxref\s+(\d+)\s+%%EOF/s', $pdf, $sx) || empty($sx[1])) {
            return null;
        }
        $prevStart = (int) end($sx[1]);
        if (substr($pdf, $prevStart, 4) !== 'xref') {
            return null; // cross-reference stream -> would need object-stream surgery
        }
        if (!preg_match('/trailer\s*<<(.*?)>>\s*startxref/s', $pdf, $tm)) {
            return null;
        }
        $trailer = $tm[1];
        if (stripos($trailer, '/Encrypt') !== false) {
            return null;
        }
        if (stripos($pdf, '/Type /Sig') !== false || stripos($pdf, '/Type/Sig') !== false
            || stripos($pdf, '/SigFlags') !== false) {
            return null; // already digitally signed - don't disturb the signature
        }
        if (!preg_match('/\/Root\s+(\d+)\s+\d+\s+R/', $trailer, $rm)
            || !preg_match('/\/Size\s+(\d+)/', $trailer, $szm)) {
            return null;
        }
        $rootNum = (int) $rm[1];
        $size = (int) $szm[1];

        // Catalog object dict (must be a simple dict we can safely re-emit).
        if (!preg_match('/(?<!\d)' . $rootNum . '\s+0\s+obj\s*<<(.*?)>>\s*endobj/s', $pdf, $cm)) {
            return null;
        }
        $catInner = trim($cm[1]);
        if ($catInner === '' || preg_match('/\/(Names|AF|Encrypt)\b/', $catInner)
            || stripos($catInner, '/Pages') === false || strpos($catInner, '<<') !== false) {
            return null; // nested dict / already has embedded files / truncated match -> bail
        }

        $bytes = ManifestBuilder::toCanonicalJson($signedManifest);
        $len = strlen($bytes);
        $efNum = $size;      // EmbeddedFile stream
        $fsNum = $size + 1;  // Filespec
        $newSize = $size + 2;

        $dest = $destPath ?? (preg_replace('/\.pdf$/i', '.c2pa.pdf', $srcPath) ?: $srcPath . '.c2pa.pdf');
        if ($dest === $srcPath) {
            $dest = $srcPath . '.c2pa.pdf';
        }

        // --- append-only incremental update ---
        $out = rtrim($pdf, "\r\n") . "\n";
        $off = [];

        $off[$efNum] = strlen($out);
        $out .= "{$efNum} 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Length {$len} >>\nstream\n"
              . $bytes . "\nendstream\nendobj\n";

        $off[$fsNum] = strlen($out);
        $out .= "{$fsNum} 0 obj\n<< /Type /Filespec /F (c2pa.json) /UF (c2pa.json) /AFRelationship /C2PA_Manifest"
              . " /Desc (C2PA Content Credential) /EF << /F {$efNum} 0 R /UF {$efNum} 0 R >> >>\nendobj\n";

        $off[$rootNum] = strlen($out);
        $out .= "{$rootNum} 0 obj\n<< {$catInner} /AF [ {$fsNum} 0 R ]"
              . " /Names << /EmbeddedFiles << /Names [ (c2pa.json) {$fsNum} 0 R ] >> >> >>\nendobj\n";

        // xref for the changed/new objects (grouped into contiguous subsections).
        ksort($off);
        $xrefStart = strlen($out);
        $xref = "xref\n";
        $nums = array_keys($off);
        for ($i = 0; $i < count($nums); ) {
            $start = $nums[$i];
            $group = [$start];
            while ($i + 1 < count($nums) && $nums[$i + 1] === $nums[$i] + 1) {
                $group[] = $nums[++$i];
            }
            $i++;
            $xref .= $start . ' ' . count($group) . "\n";
            foreach ($group as $n) {
                $xref .= sprintf("%010d %05d n \n", $off[$n], 0);
            }
        }
        $out .= $xref
              . "trailer\n<< /Size {$newSize} /Root {$rootNum} 0 R /Prev {$prevStart} >>\nstartxref\n{$xrefStart}\n%%EOF\n";

        if (@file_put_contents($dest, $out) === false) {
            return null;
        }

        // Round-trip: re-extract the manifest and confirm it matches, else discard.
        $check = $this->extractC2paFromPdf($dest);
        if ($check !== $bytes) {
            @unlink($dest);
            Log::warning('c2pa: PDF embed self-check failed, falling back to sidecar', ['src' => $srcPath]);
            return null;
        }

        return $dest;
    }

    /**
     * #1387 - read the C2PA manifest back out of a PDF's associated embedded
     * file (the counterpart to embedInPdf). Returns the raw manifest bytes, or
     * null if the PDF carries no embedded credential.
     */
    public function extractC2paFromPdf(string $pdfPath): ?string
    {
        $pdf = @file_get_contents($pdfPath);
        if ($pdf === false) {
            return null;
        }
        // Find the last EmbeddedFile stream (embedInPdf appends exactly one).
        if (!preg_match_all('/\/Type\s*\/EmbeddedFile.*?stream\r?\n(.*?)\r?\nendstream/s', $pdf, $m) || empty($m[1])) {
            return null;
        }

        return end($m[1]);
    }

    /**
     * Persist a signed manifest to ahg_c2pa_manifest. Best-effort - if the
     * table does not exist yet (fresh install pre-boot) we skip silently.
     *
     * @param array<string,mixed> $signedManifest
     * @return int|null inserted row id, or null if persistence skipped/failed
     */
    public function persist(
        array $signedManifest,
        int $informationObjectId,
        string $action,
        string $modelId,
        ?string $modelVersion,
        ?string $sidecarPath,
    ): ?int {
        try {
            if (!Schema::hasTable('ahg_c2pa_manifest')) {
                return null;
            }

            $canonical = ManifestBuilder::toCanonicalJson($signedManifest);
            $cbor = ManifestBuilder::toCbor($signedManifest);

            $sig = $signedManifest['claim_signature']['sig'] ?? '';
            $kid = $signedManifest['claim_signature']['kid'] ?? '';

            return (int) DB::table('ahg_c2pa_manifest')->insertGetId([
                'information_object_id' => $informationObjectId,
                'action'                => $action,
                'model_id'              => $modelId,
                'model_version'         => $modelVersion,
                'manifest_json'         => $canonical,
                'manifest_cbor'         => $cbor,
                'sidecar_path'          => $sidecarPath,
                'claim_signature'       => $sig,
                'kid'                   => $kid,
                'created_at'            => date('Y-m-d H:i:s.v'),
            ]);
        } catch (Throwable $e) {
            Log::warning('c2pa: persist failed', ['err' => $e->getMessage()]);
            return null;
        }
    }

    private static function mimeOfFile(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'tif', 'tiff' => 'image/tiff',
            'jp2'         => 'image/jp2',
            'pdf'         => 'application/pdf',
            'mp4'         => 'video/mp4',
            'mp3'         => 'audio/mpeg',
            'txt'         => 'text/plain',
            'json'        => 'application/json',
            default       => 'application/octet-stream',
        };
    }
}
