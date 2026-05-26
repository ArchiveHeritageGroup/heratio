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
 *   embedInJpeg()              - shell out to c2pa-tools to embed JUMBF;
 *                                falls back to sidecar() if CLI absent
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

    private static function autodetectBinary(): ?string
    {
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

        if ($assetPath !== null) {
            $builder->withAssetFile($assetPath);
        } else {
            $builder->withAssetString($output);
        }

        return $builder->build();
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
     * Embed a manifest in a JPEG via the c2pa-tools CLI. If the CLI is not
     * installed, falls back to writing a sidecar (and returns the sidecar
     * path; the caller can detect this by extension).
     *
     * @param array<string,mixed> $signedManifest
     * @return string absolute path of the produced artefact (new .jpg or sidecar .c2pa.json)
     */
    public function embedInJpeg(string $imagePath, array $signedManifest): string
    {
        if (!is_readable($imagePath)) {
            throw new RuntimeException("C2paService: input image not readable: {$imagePath}");
        }

        if ($this->c2paToolBinary === null) {
            Log::info('c2pa: c2patool not installed, falling back to sidecar', ['image' => $imagePath]);
            return $this->sidecar($signedManifest, $imagePath);
        }

        $outputPath = preg_replace('/\.jpe?g$/i', '.c2pa.jpg', $imagePath) ?: ($imagePath . '.c2pa.jpg');
        if ($outputPath === $imagePath) {
            $outputPath = $imagePath . '.c2pa.jpg';
        }

        $manifestPath = tempnam(sys_get_temp_dir(), 'c2pa-manifest-') ?: '/tmp/c2pa-manifest-' . bin2hex(random_bytes(4));
        file_put_contents($manifestPath, ManifestBuilder::toCanonicalJson($signedManifest));

        $cmd = sprintf(
            '%s %s --manifest %s --output %s 2>&1',
            escapeshellcmd($this->c2paToolBinary),
            escapeshellarg($imagePath),
            escapeshellarg($manifestPath),
            escapeshellarg($outputPath),
        );

        $exit = 0;
        $output = [];
        exec($cmd, $output, $exit);
        @unlink($manifestPath);

        if ($exit !== 0 || !is_readable($outputPath)) {
            Log::warning('c2pa: c2patool embed failed; falling back to sidecar', [
                'image' => $imagePath,
                'exit'  => $exit,
                'out'   => implode("\n", $output),
            ]);
            return $this->sidecar($signedManifest, $imagePath);
        }

        return $outputPath;
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
