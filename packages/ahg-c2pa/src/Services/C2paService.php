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
     * c2patool can write a C2PA manifest into these container formats. Other
     * extensions (PDF, raw text, glTF, ...) are sidecar-only.
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
