<?php
/**
 * Heratio - assembles a C2PA 2.1 manifest from assertions + a host asset.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Manifest;

use AhgInferenceReceipts\JcsEncoder;
use InvalidArgumentException;

/**
 * Build a C2PA manifest store ("c2pa.manifest") - the unit that ultimately
 * gets embedded in JUMBF or written as a sidecar JSON.
 *
 * Shape we produce (JSON serialisation per spec §11):
 *
 *   {
 *     "manifest_label": "ahg.heratio:<uuid>",
 *     "assertions": [ { label, instance, data }, ... ],
 *     "claim": { ... },             // populated after build()
 *     "claim_signature": { ... }    // populated after C2paSigner::sign()
 *   }
 *
 * The CBOR encoding required for media embedding is produced on demand
 * (toCbor()) and is byte-equivalent to the JSON form modulo CBOR tags.
 * If the local install has the c2pa-tools CLI we delegate JUMBF box
 * construction to it; otherwise the sidecar JSON path is the deliverable.
 */
final class ManifestBuilder
{
    /** @var list<Assertion> */
    private array $assertions = [];

    private string $title = '';
    private string $format = 'application/octet-stream';
    private string $claimGenerator = 'Heratio/1.0';
    private ?string $assetHash = null;
    private ?string $manifestLabel = null;
    /** @var array<string,mixed> */
    private array $claimExtra = [];

    public function withTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function withFormat(string $mimeType): self
    {
        $this->format = $mimeType;
        return $this;
    }

    public function withClaimGenerator(string $generator): self
    {
        $this->claimGenerator = $generator;
        return $this;
    }

    public function withAssetHash(string $hexHash): self
    {
        if (!ctype_xdigit($hexHash)) {
            throw new InvalidArgumentException('ManifestBuilder: asset hash must be hex');
        }
        $this->assetHash = strtolower($hexHash);
        return $this;
    }

    /**
     * Compute the asset hash from a file on disk.
     */
    public function withAssetFile(string $path): self
    {
        if (!is_readable($path)) {
            throw new InvalidArgumentException("ManifestBuilder: asset file not readable: {$path}");
        }
        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            throw new InvalidArgumentException("ManifestBuilder: failed to hash {$path}");
        }
        return $this->withAssetHash($hash);
    }

    /**
     * Provide a synthetic asset hash for text outputs (the "asset" is the
     * AI output string itself; there is no file).
     */
    public function withAssetString(string $body): self
    {
        return $this->withAssetHash(hash('sha256', $body));
    }

    public function withManifestLabel(string $label): self
    {
        $this->manifestLabel = $label;
        return $this;
    }

    public function withClaimExtra(string $key, mixed $value): self
    {
        $this->claimExtra[$key] = $value;
        return $this;
    }

    public function addAssertion(Assertion $a): self
    {
        $this->assertions[] = $a;
        return $this;
    }

    /**
     * Attach the three Standard Metadata Assertions (stds.exif / stds.iptc /
     * stds.xmp) for a given digital object, sourced from Heratio's sidecar
     * tables (digital_object_metadata, dam_iptc_metadata, media_metadata).
     *
     * Any of the three is skipped silently when its sidecar row has no
     * usable content - we don't emit empty assertions. If $loader is null
     * a default StandardMetadataLoader is constructed (uses DB facade).
     *
     * Per C2PA 2.1 § Standard Metadata Assertions, this is the canonical
     * way to surface embedded EXIF/IPTC/XMP through the C2PA hash chain
     * without re-encoding the host asset.
     */
    public function withStandardMetadata(
        int $digitalObjectId,
        ?int $objectId = null,
        ?StandardMetadataLoader $loader = null,
    ): self {
        $loader ??= new StandardMetadataLoader();
        foreach ($loader->loadAssertions($digitalObjectId, $objectId) as $a) {
            $this->assertions[] = $a;
        }
        return $this;
    }

    /**
     * Build the unsigned manifest dict.
     *
     * @return array{
     *     manifest_label: string,
     *     assertions: list<array<string,mixed>>,
     *     claim: array<string,mixed>
     * }
     */
    public function build(): array
    {
        if ($this->title === '') {
            throw new InvalidArgumentException('ManifestBuilder: title is required');
        }
        if ($this->assetHash === null) {
            throw new InvalidArgumentException('ManifestBuilder: asset hash is required (call withAssetFile or withAssetString)');
        }
        if ($this->assertions === []) {
            throw new InvalidArgumentException('ManifestBuilder: at least one assertion is required');
        }

        $ts = gmdate('Y-m-d\TH:i:s\Z');
        $label = $this->manifestLabel ?? ('ahg.heratio:' . self::uuidv4());

        $claim = new Claim(
            title: $this->title,
            format: $this->format,
            claimGenerator: $this->claimGenerator,
            assertions: $this->assertions,
            assetHash: $this->assetHash,
            ts: $ts,
            extra: $this->claimExtra,
        );

        return [
            'manifest_label'  => $label,
            'assertions'      => array_map(
                fn (Assertion $a) => [
                    'label'    => $a->label,
                    'instance' => $a->instance,
                    'data'     => $a->data,
                ],
                $this->assertions,
            ),
            'claim'           => $claim->toArray(),
            // The Claim instance itself is stashed so the caller can pass
            // it to C2paSigner without rebuilding it from the array.
            '_claim_object'   => $claim,
        ];
    }

    /**
     * Canonical (RFC 8785 JCS) JSON of a built manifest (after stripping
     * the internal _claim_object handle). This is the authoritative form
     * we persist to ahg_c2pa_manifest.manifest_json.
     *
     * @param array<string,mixed> $manifest
     */
    public static function toCanonicalJson(array $manifest): string
    {
        unset($manifest['_claim_object']);
        return JcsEncoder::encode($manifest);
    }

    /**
     * CBOR encoding of the same manifest. Uses a deterministic CBOR
     * profile so two implementations agree on bytes (CTAP2 canonical:
     * integer keys preferred where possible, definite lengths, shortest
     * integer encoding).
     *
     * @param array<string,mixed> $manifest
     */
    public static function toCbor(array $manifest): string
    {
        unset($manifest['_claim_object']);
        return CborEncoder::encode($manifest);
    }

    /**
     * RFC 4122 v4 UUID using random_bytes.
     */
    private static function uuidv4(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        $h = bin2hex($b);
        return sprintf('%s-%s-%s-%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 12, 4), substr($h, 16, 4), substr($h, 20, 12));
    }
}
