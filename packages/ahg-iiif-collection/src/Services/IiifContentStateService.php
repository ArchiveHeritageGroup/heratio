<?php

/**
 * IiifContentStateService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgIiifCollection\Services;

/**
 * IIIF Content State API 1.0 encoder / decoder (issue #696).
 *
 * Spec: https://iiif.io/api/content-state/1.0/
 *
 * A Content State is a URL-safe base64-encoded JSON-LD Annotation that
 * captures a viewer's pose - which manifest is open, which canvas, which
 * region of the canvas, what zoom / rotation. It's the "deep link" that
 * gets shared in chats and emails when a researcher wants to point a
 * colleague at the same spot in a long-scroll manifest.
 *
 * Encoding (per spec):
 *   1. Serialise the Annotation to JSON.
 *   2. Base64-encode using the URL-safe alphabet (+/= -> -_).
 *   3. Trim trailing padding.
 *
 * Decoding reverses that exactly. The annotation body we round-trip is
 * a W3C Annotation with `motivation = contentState` and a `target` that
 * points at the manifest IRI, the canvas IRI, and (optionally) a
 * FragmentSelector with `xywh=` and rotation.
 */
class IiifContentStateService
{
    /**
     * Encode an Annotation-shaped state structure to an opaque token.
     * The caller hands us the components; we build the spec-conformant
     * Annotation envelope and return the URL-safe base64 string.
     *
     * @param string $manifestUri full IIIF manifest IRI
     * @param string|null $canvasIri canvas IRI (manifest's items[N].id)
     * @param array<string,mixed>|null $selector optional FragmentSelector
     *                                  shape, e.g. ['xywh'=>'100,200,400,300']
     */
    public function encode(string $manifestUri, ?string $canvasIri = null, ?array $selector = null): string
    {
        $annotation = $this->buildAnnotation($manifestUri, $canvasIri, $selector);
        $json = json_encode($annotation, JSON_UNESCAPED_SLASHES);
        return $this->base64UrlEncode($json);
    }

    /**
     * Decode an opaque token back into the underlying Annotation. Returns
     * null when the token isn't valid base64 / valid JSON, so the
     * controller can return 400 instead of 500.
     *
     * @return array<string,mixed>|null
     */
    public function decode(string $token): ?array
    {
        $json = $this->base64UrlDecode($token);
        if ($json === null) {
            return null;
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }
        return $decoded;
    }

    /**
     * Construct the spec-conformant Annotation envelope. Public so test
     * code can build the envelope without exercising the base64 layer.
     *
     * @param array<string,mixed>|null $selector
     * @return array<string,mixed>
     */
    public function buildAnnotation(string $manifestUri, ?string $canvasIri, ?array $selector): array
    {
        $target = [
            'type' => 'SpecificResource',
            'source' => [
                'id' => $manifestUri,
                'type' => 'Manifest',
                'partOf' => [[
                    'id' => $manifestUri,
                    'type' => 'Manifest',
                ]],
            ],
        ];

        if ($canvasIri !== null && $canvasIri !== '') {
            $target['source'] = [
                'id' => $canvasIri,
                'type' => 'Canvas',
                'partOf' => [[
                    'id' => $manifestUri,
                    'type' => 'Manifest',
                ]],
            ];
        }

        if (is_array($selector) && !empty($selector)) {
            // Default conformsTo / type to FragmentSelector when the
            // caller only supplied an `xywh` field.
            $sel = $selector;
            if (!isset($sel['type'])) {
                $sel['type'] = 'FragmentSelector';
            }
            if (!isset($sel['conformsTo']) && $sel['type'] === 'FragmentSelector') {
                $sel['conformsTo'] = 'http://www.w3.org/TR/media-frags/';
            }
            $target['selector'] = $sel;
        }

        return [
            '@context' => 'http://iiif.io/api/presentation/3/context.json',
            'id' => 'https://example.com/content-state/' . substr(sha1(json_encode($target).':'.time()), 0, 16),
            'type' => 'Annotation',
            'motivation' => 'contentState',
            'target' => $target,
        ];
    }

    /**
     * URL-safe base64 (RFC 4648 §5). The IIIF Content State spec mandates
     * + -> -, / -> _, and trailing padding stripped. Decoders must
     * reverse all three before calling base64_decode().
     */
    private function base64UrlEncode(string $raw): string
    {
        $b64 = base64_encode($raw);
        $b64 = strtr($b64, '+/', '-_');
        return rtrim($b64, '=');
    }

    private function base64UrlDecode(string $token): ?string
    {
        if ($token === '') {
            return null;
        }
        $token = strtr($token, '-_', '+/');
        $pad = strlen($token) % 4;
        if ($pad > 0) {
            $token .= str_repeat('=', 4 - $pad);
        }
        $raw = base64_decode($token, true);
        if ($raw === false) {
            return null;
        }
        return $raw;
    }
}
