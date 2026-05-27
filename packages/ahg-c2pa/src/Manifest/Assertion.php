<?php
/**
 * Heratio - one C2PA assertion (a single self-contained provenance claim).
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
 * A single C2PA assertion - the smallest unit of provenance.
 *
 * Each assertion has:
 *   - a label (e.g. 'c2pa.actions.v2', 'c2pa.training-mining', 'c2pa.ingredients')
 *   - a data payload (associative array, JSON-serialisable)
 *   - an instance suffix (unique per claim, e.g. '__1')
 *
 * The claim references each assertion by URI ('self#jumbf=c2pa.assertions/<label>__<n>')
 * and by SHA-256 hash of its canonical JSON form. Tampering with the
 * assertion bytes after the claim is signed will fail re-hash on verify.
 *
 * Labels we currently emit:
 *
 *   c2pa.actions.v2       - what happened (ai-generated, ai-assisted, edited, etc)
 *   c2pa.training-mining  - claimer's stance on AI training / data mining use
 *   c2pa.ingredients      - prior content this artefact was derived from
 *   stds.exif             - EXIF subset extracted from the host asset
 *                           (per C2PA 2.1 § Standard Metadata Assertions)
 *   stds.iptc             - IPTC IIM/Photo Metadata subset
 *   stds.xmp              - XMP (Dublin Core + xmpRights) subset
 *
 * See https://c2pa.org/specifications/specifications/2.1/specs/C2PA_Specification.html
 * for the full label registry.
 */
final class Assertion
{
    /**
     * @param array<string,mixed> $data
     */
    public function __construct(
        public readonly string $label,
        public readonly array $data,
        public readonly int $instance = 1,
    ) {
        if ($label === '') {
            throw new InvalidArgumentException('Assertion: label must not be empty');
        }
        if ($instance < 1) {
            throw new InvalidArgumentException('Assertion: instance must be >= 1');
        }
    }

    /**
     * "<label>__<instance>" - the suffix used in JUMBF URIs.
     */
    public function uriFragment(): string
    {
        return $this->label . '__' . $this->instance;
    }

    /**
     * URI by which the claim references this assertion.
     */
    public function uri(): string
    {
        return 'self#jumbf=c2pa.assertions/' . $this->uriFragment();
    }

    /**
     * Canonical (RFC 8785 JCS) byte form of the assertion data. Stable
     * across implementations - two callers feeding the same array always
     * get the same bytes.
     */
    public function canonicalBytes(): string
    {
        return JcsEncoder::encode($this->data);
    }

    /**
     * SHA-256 of the canonical bytes, hex-encoded. This is what the claim
     * pins so verifiers can detect tampering.
     */
    public function hashHex(): string
    {
        return hash('sha256', $this->canonicalBytes());
    }

    /**
     * The hashed-uri reference object as it appears inside the claim.
     * Per C2PA 2.1 the algorithm label is "sha256".
     *
     * @return array<string,string>
     */
    public function hashedUri(): array
    {
        return [
            'alg'  => 'sha256',
            'hash' => $this->hashHex(),
            'url'  => $this->uri(),
        ];
    }

    /**
     * Convenience: a fully populated 'c2pa.actions.v2' assertion describing
     * one AI generation event.
     *
     * @param string $action 'ai-generated' or 'ai-assisted' (or any other registered action)
     * @param array<string,mixed> $parameters model_id, model_version, prompt fingerprint, etc.
     */
    public static function action(string $action, array $parameters = []): self
    {
        return new self('c2pa.actions.v2', [
            'actions' => [[
                'action'    => $action,
                'when'      => gmdate('Y-m-d\TH:i:s\Z'),
                'softwareAgent' => $parameters['softwareAgent'] ?? [
                    'name'    => 'Heratio',
                    'version' => $parameters['heratioVersion'] ?? 'unknown',
                ],
                'parameters' => array_diff_key($parameters, array_flip(['softwareAgent', 'heratioVersion'])),
            ]],
        ]);
    }

    /**
     * Convenience: a 'c2pa.training-mining' assertion declaring whether
     * the claimer permits this artefact to be used for AI training.
     */
    public static function trainingMining(bool $permitted, ?string $reason = null): self
    {
        $data = [
            'entries' => [
                'c2pa.ai_generative_training'   => ['use' => $permitted ? 'allowed' : 'notAllowed'],
                'c2pa.ai_inference'             => ['use' => $permitted ? 'allowed' : 'notAllowed'],
                'c2pa.ai_training'              => ['use' => $permitted ? 'allowed' : 'notAllowed'],
                'c2pa.data_mining'              => ['use' => $permitted ? 'allowed' : 'notAllowed'],
            ],
        ];
        if ($reason !== null && $reason !== '') {
            $data['reason'] = $reason;
        }
        return new self('c2pa.training-mining', $data);
    }

    /**
     * Convenience: a 'c2pa.ingredients' assertion declaring source content
     * this artefact was derived from.
     *
     * @param list<array<string,mixed>> $ingredients
     */
    public static function ingredients(array $ingredients): self
    {
        return new self('c2pa.ingredients', ['ingredients' => $ingredients]);
    }

    /**
     * Standard Metadata Assertion - EXIF subset.
     *
     * Per C2PA 2.1 § Standard Metadata Assertions, the assertion data is
     * a single object whose properties use standard Exif tag names with
     * an "Exif/" prefix (e.g. "Exif/DateTimeOriginal", "Exif/Make").
     *
     * Spec: https://c2pa.org/specifications/specifications/2.1/specs/C2PA_Specification.html#_standard_metadata_assertions
     *
     * Throws no exception on empty input; callers that have nothing to
     * declare should simply not build this assertion.
     *
     * @param array<string,mixed> $entries map of Exif/<TagName> => value
     */
    public static function stdsExif(array $entries): self
    {
        return new self('stds.exif', $entries);
    }

    /**
     * Standard Metadata Assertion - IPTC IIM / Photo Metadata subset.
     *
     * Per C2PA 2.1 the property keys are unprefixed IPTC names
     * (By-line, Copyright, Headline, Keywords, Caption-Abstract, ...).
     *
     * @param array<string,mixed> $entries
     */
    public static function stdsIptc(array $entries): self
    {
        return new self('stds.iptc', $entries);
    }

    /**
     * Standard Metadata Assertion - XMP (Dublin Core + xmpRights) subset.
     *
     * Property keys use the standard XMP qualified-name form
     * ("dc:creator", "dc:rights", "dc:title", "dc:subject",
     *  "xmpRights:Marked", "xmpRights:UsageTerms", ...).
     *
     * @param array<string,mixed> $entries
     */
    public static function stdsXmp(array $entries): self
    {
        return new self('stds.xmp', $entries);
    }
}
