<?php
/**
 * Heratio - digitisation provenance / content-credentials records (issue #1201).
 *
 * Records who digitised a heritage asset, when, on what device and software,
 * plus any AI-inference steps that touched it, and (when possible) binds the
 * record to a signed C2PA manifest. Manifest-level Ed25519 signing works on
 * any install with ext-sodium; embedding the manifest into the media file
 * (JUMBF) additionally needs the native c2patool binary.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Services;

use AhgC2pa\Manifest\Assertion;
use AhgC2pa\Manifest\ManifestBuilder;
use AhgInferenceReceipts\JcsEncoder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Provenance-record side of the C2PA layer. Distinct from C2paService, which
 * is the low-level build/sign/sidecar engine: this service owns the
 * ahg_c2pa_provenance table and the IO-facing "content credentials" view of a
 * digitised asset.
 */
final class ProvenanceRecordService
{
    public function __construct(private C2paService $c2pa)
    {
    }

    /**
     * Honest capability report for the UI. Manifest-level signing is always
     * available (ext-sodium); media embedding needs c2patool.
     *
     * @return array{
     *     sodium: bool,
     *     can_sign_manifest: bool,
     *     c2patool: bool,
     *     c2patool_path: ?string,
     *     can_embed_media: bool,
     *     summary: string
     * }
     */
    public function capability(): array
    {
        $sodium = extension_loaded('sodium') || function_exists('sodium_crypto_sign');
        $toolPath = $this->detectC2paTool();
        $canEmbed = $toolPath !== null;

        if ($sodium && $canEmbed) {
            $summary = 'Full: manifests are Ed25519-signed and can be embedded into media via c2patool.';
        } elseif ($sodium) {
            $summary = 'Signing requires c2patool only for media embedding (not installed). '
                . 'Manifests are still Ed25519-signed and stored as verifiable records + sidecars.';
        } else {
            $summary = 'ext-sodium not available: provenance records are stored but cannot be signed on this host.';
        }

        return [
            'sodium'            => $sodium,
            'can_sign_manifest' => $sodium,
            'c2patool'          => $canEmbed,
            'c2patool_path'     => $toolPath,
            'can_embed_media'   => $canEmbed,
            'summary'           => $summary,
        ];
    }

    /**
     * Resolve the native c2patool binary. Prefers the configured host path
     * (config('heratio.c2patool_bin'), default /usr/local/bin/c2patool), then
     * a small PATH probe. Returns null when no usable binary is found.
     */
    private function detectC2paTool(): ?string
    {
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
     * Record a digitisation-provenance entry and (when the asset file is
     * available and signing is possible) build + sign a C2PA capture manifest
     * bound to it.
     *
     * @param array{
     *     digital_object_id?: int|null,
     *     captured_by?: string|null,
     *     captured_at?: string|null,
     *     capture_device?: string|null,
     *     capture_software?: string|null,
     *     notes?: string|null,
     *     asset_path?: string|null,
     *     inference_steps?: list<array<string,mixed>>,
     *     heratio_version?: string|null
     * } $input
     *
     * @return int the ahg_c2pa_provenance row id
     */
    public function record(int $informationObjectId, array $input): int
    {
        if (!Schema::hasTable('ahg_c2pa_provenance')) {
            throw new RuntimeException('ProvenanceRecordService: ahg_c2pa_provenance table not installed');
        }

        $digitalObjectId = isset($input['digital_object_id']) ? (int) $input['digital_object_id'] : null;
        if ($digitalObjectId !== null && $digitalObjectId <= 0) {
            $digitalObjectId = null;
        }

        $assetPath = $input['asset_path'] ?? null;
        $assetSha = null;
        if (is_string($assetPath) && $assetPath !== '' && is_readable($assetPath)) {
            $h = hash_file('sha256', $assetPath);
            $assetSha = $h === false ? null : $h;
        }

        $inferenceSteps = $input['inference_steps'] ?? [];
        if (!is_array($inferenceSteps)) {
            $inferenceSteps = [];
        }

        $now = date('Y-m-d H:i:s.v');

        $rowId = (int) DB::table('ahg_c2pa_provenance')->insertGetId([
            'information_object_id' => $informationObjectId,
            'digital_object_id'     => $digitalObjectId,
            'captured_by'           => $this->str($input['captured_by'] ?? null, 255),
            'captured_at'           => $this->dateOrNull($input['captured_at'] ?? null),
            'capture_device'        => $this->str($input['capture_device'] ?? null, 255),
            'capture_software'      => $this->str($input['capture_software'] ?? null, 255),
            'notes'                 => $this->str($input['notes'] ?? null, 65535),
            'asset_sha256'          => $assetSha,
            'inference_steps'       => $inferenceSteps === [] ? null : json_encode(array_values($inferenceSteps)),
            'manifest_id'           => null,
            'sign_status'           => 'unsigned',
            'created_at'            => $now,
            'updated_at'            => $now,
        ]);

        // Best-effort: build + sign a C2PA capture manifest bound to this
        // record. Never let a signing failure lose the provenance record.
        if ($this->capability()['can_sign_manifest']) {
            try {
                $manifestId = $this->buildAndSignManifest(
                    $rowId,
                    $informationObjectId,
                    $assetPath,
                    $assetSha,
                    $input,
                    $inferenceSteps,
                );
                if ($manifestId !== null) {
                    DB::table('ahg_c2pa_provenance')->where('id', $rowId)->update([
                        'manifest_id' => $manifestId,
                        'sign_status' => 'signed',
                        'updated_at'  => date('Y-m-d H:i:s.v'),
                    ]);
                }
            } catch (Throwable $e) {
                Log::warning('c2pa: provenance manifest sign failed', [
                    'provenance_id' => $rowId,
                    'err'           => $e->getMessage(),
                ]);
            }
        }

        return $rowId;
    }

    /**
     * Build a c2pa.actions.v2 "created"/"placed" capture manifest for the
     * record, sign it (Ed25519), write a sidecar if we have a file path, and
     * persist to ahg_c2pa_manifest. Returns the manifest row id or null.
     *
     * @param list<array<string,mixed>> $inferenceSteps
     * @param array<string,mixed> $input
     */
    private function buildAndSignManifest(
        int $provenanceId,
        int $informationObjectId,
        ?string $assetPath,
        ?string $assetSha,
        array $input,
        array $inferenceSteps,
    ): ?int {
        $heratioVersion = $this->str($input['heratio_version'] ?? null, 64) ?? 'unknown';

        $builder = (new ManifestBuilder())
            ->withTitle("Heratio digitisation provenance #{$provenanceId} (IO #{$informationObjectId})")
            ->withFormat(is_string($assetPath) ? self::mimeOfFile($assetPath) : 'application/json')
            ->withClaimGenerator('Heratio/' . $heratioVersion . ' c2pa-php/1.0');

        // Per C2PA 2.1 a single c2pa.actions.v2 assertion carries an ordered
        // list of actions. We emit ONE assertion: the digitisation event
        // ('c2pa.created') followed by one action per AI-inference step
        // ('c2pa.edited', chaining to #61). Keeping them in one assertion
        // avoids label/instance collisions and is the spec-canonical shape.
        $softwareAgent = [
            'name'    => $this->str($input['capture_device'] ?? null, 255) ?? 'Heratio',
            'version' => $this->str($input['capture_software'] ?? null, 255) ?? $heratioVersion,
        ];

        $actions = [];
        $actions[] = [
            'action'        => 'c2pa.created',
            'when'          => $this->dateOrNull($input['captured_at'] ?? null) ?? gmdate('Y-m-d\TH:i:s\Z'),
            'softwareAgent' => $softwareAgent,
            'parameters'    => array_filter([
                'capturedBy'     => $this->str($input['captured_by'] ?? null, 255),
                'heratio_io_id'  => $informationObjectId,
                'heratioVersion' => $heratioVersion,
            ], static fn ($v) => $v !== null && $v !== ''),
        ];

        foreach ($inferenceSteps as $step) {
            if (!is_array($step)) {
                continue;
            }
            $actions[] = [
                'action'        => 'c2pa.edited',
                'when'          => gmdate('Y-m-d\TH:i:s\Z'),
                'softwareAgent' => ['name' => 'Heratio', 'version' => $heratioVersion],
                'parameters'    => array_filter([
                    'inferenceStep' => $this->str($step['step'] ?? null, 128),
                    'model_id'      => $this->str($step['model_id'] ?? null, 128),
                    'model_version' => $this->str($step['model_version'] ?? null, 64),
                    'output_sha256' => $this->str($step['output_sha256'] ?? null, 64),
                ], static fn ($v) => $v !== null && $v !== ''),
            ];
        }

        $builder->addAssertion(new Assertion('c2pa.actions.v2', ['actions' => $actions]));

        // Archival custody stance: no downstream AI training without licence.
        $builder->addAssertion(Assertion::trainingMining(
            permitted: false,
            reason: 'Digitised heritage asset in archival custody; downstream AI training requires explicit licence',
        ));

        if (is_string($assetPath) && is_readable($assetPath)) {
            $builder->withAssetFile($assetPath);
        } else {
            // No file on disk: bind the manifest to a synthetic asset hash
            // computed from the canonical record so the claim still has a
            // stable asset binding.
            $synthetic = JcsEncoder::encode([
                'provenance_id' => $provenanceId,
                'io'            => $informationObjectId,
                'asset_sha256'  => $assetSha,
            ]);
            $builder->withAssetString($synthetic);
        }

        $manifest = $builder->build();
        $signed = $this->c2pa->signManifest($manifest);

        // Sidecar next to the asset when we have a real file path.
        $sidecarPath = null;
        if (is_string($assetPath) && is_readable($assetPath)) {
            try {
                $sidecarPath = $this->c2pa->sidecar($signed, $assetPath);
            } catch (Throwable $e) {
                Log::info('c2pa: sidecar write skipped', ['err' => $e->getMessage()]);
            }

            // Best-effort native embed: when the c2patool binary is present and
            // the master is an embeddable container format (JPEG/PNG/TIFF/MP4),
            // also write the manifest into a JUMBF-embedded copy of the master.
            // Degrades silently (embed() returns null) when the tool is absent
            // or the format is sidecar-only - the signed sidecar + DB record are
            // still the authoritative provenance.
            if ($this->c2pa->canEmbed() && C2paService::isEmbeddableFormat($assetPath)) {
                try {
                    $embedded = $this->c2pa->embed($assetPath, $signed);
                    if ($embedded !== null) {
                        Log::info('c2pa: embedded provenance manifest into master', [
                            'provenance_id' => $provenanceId,
                            'src'           => $assetPath,
                            'dest'          => $embedded,
                        ]);
                    }
                } catch (Throwable $e) {
                    Log::info('c2pa: master embed skipped', ['err' => $e->getMessage()]);
                }
            }
        }

        return $this->c2pa->persist(
            $signed,
            $informationObjectId,
            'c2pa.created',
            'digitisation-capture',
            $heratioVersion,
            $sidecarPath,
        );
    }

    /**
     * Verify a stored provenance record end-to-end: load its bound manifest
     * and re-check assertion hashes + the Ed25519 claim signature.
     *
     * @return array{
     *     status: string,
     *     ok: bool,
     *     errors: list<string>,
     *     manifest: array<string,mixed>|null,
     *     kid: ?string
     * }
     */
    public function verifyRecord(int $provenanceId): array
    {
        $row = DB::table('ahg_c2pa_provenance')->where('id', $provenanceId)->first();
        if ($row === null) {
            return ['status' => 'not-found', 'ok' => false, 'errors' => ['provenance record not found'], 'manifest' => null, 'kid' => null];
        }
        if ($row->manifest_id === null) {
            return ['status' => 'unsigned', 'ok' => false, 'errors' => ['no signed manifest bound to this record'], 'manifest' => null, 'kid' => null];
        }

        $manifestRow = DB::table('ahg_c2pa_manifest')->where('id', $row->manifest_id)->first();
        if ($manifestRow === null) {
            return ['status' => 'manifest-missing', 'ok' => false, 'errors' => ['bound manifest row is missing'], 'manifest' => null, 'kid' => null];
        }

        $manifest = json_decode((string) $manifestRow->manifest_json, true);
        if (!is_array($manifest)) {
            return ['status' => 'corrupt', 'ok' => false, 'errors' => ['manifest_json is not valid JSON'], 'manifest' => null, 'kid' => null];
        }

        $result = C2paService::verify($manifest, fn (string $kid) => $this->resolvePublicKey($kid));

        return [
            'status'   => $result['ok'] ? 'verified' : 'failed',
            'ok'       => $result['ok'],
            'errors'   => $result['errors'],
            'manifest' => $manifest,
            'kid'      => $manifest['claim_signature']['kid'] ?? null,
        ];
    }

    /**
     * Resolve a kid to its raw Ed25519 public key. Prefers the ai_inference_key
     * registry (shared with the EU AI Act chain), falls back to the on-disk
     * signing pubkey.
     */
    private function resolvePublicKey(string $kid): ?string
    {
        try {
            if (Schema::hasTable('ai_inference_key')) {
                $row = DB::table('ai_inference_key')->where('kid', $kid)->first(['public_key']);
                if ($row !== null && is_string($row->public_key) && $row->public_key !== '') {
                    return $row->public_key;
                }
            }
        } catch (Throwable) {
            // fall through to filesystem
        }

        if (!function_exists('storage_path')) {
            return null;
        }
        $pkPath = storage_path('keys/inference-signing.pk');
        if (!is_readable($pkPath)) {
            return null;
        }
        $raw = @file_get_contents($pkPath);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $candidateKid = substr(hash('sha256', $raw), 0, 16);
        return $candidateKid === $kid ? $raw : null;
    }

    /**
     * List provenance records for an information object, newest first.
     *
     * @return list<object>
     */
    public function listForObject(int $informationObjectId): array
    {
        if (!Schema::hasTable('ahg_c2pa_provenance')) {
            return [];
        }
        return DB::table('ahg_c2pa_provenance')
            ->where('information_object_id', $informationObjectId)
            ->orderByDesc('created_at')
            ->get()
            ->all();
    }

    public function find(int $provenanceId): ?object
    {
        if (!Schema::hasTable('ahg_c2pa_provenance')) {
            return null;
        }
        return DB::table('ahg_c2pa_provenance')->where('id', $provenanceId)->first();
    }

    private function str(mixed $v, int $max): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }
        return mb_substr($s, 0, $max);
    }

    private function dateOrNull(mixed $v): ?string
    {
        if (!is_string($v) || trim($v) === '') {
            return null;
        }
        $ts = strtotime($v);
        return $ts === false ? null : date('Y-m-d H:i:s', $ts);
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
            'glb', 'gltf' => 'model/gltf-binary',
            'ply'         => 'model/ply',
            'obj'         => 'model/obj',
            'mp4'         => 'video/mp4',
            'mp3'         => 'audio/mpeg',
            default       => 'application/octet-stream',
        };
    }
}
