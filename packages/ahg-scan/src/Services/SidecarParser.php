<?php

/**
 * SidecarParser — Heratio ahg-scan
 *
 * Parses a heratioScan XML sidecar into a normalised metadata array
 * compatible with IngestService::ingestFile().
 *
 * Namespace:  https://heratio.io/scan/v1
 * Root:       <heratioScan>
 *
 * Envelope:
 *   <sector>, <standard>
 *   <parentSlug> | <parentIdentifier> | <parentId>
 *   <repositorySlug>
 *   <identifier>, <title>, <levelOfDescription>
 *   <dates><date type="creation" start="..." end="..."/></dates>
 *   <publicationStatus>, <accessConditions>
 *   <rightsStatement uri="...">, <ccLicense>, <embargoUntil>, <odrlPolicy>, <rightsHolder>
 *   <digitalObject><usage>, <makeDerivatives>, <ocr>, <htr>, <iiif></digitalObject>
 *   <archiveProfile>|<libraryProfile>|<galleryProfile>|<museumProfile>
 *   <damAugmentation>
 *   <customFields><field name="...">value</field></customFields>
 *   <merge>add-sequence|replace|error</merge>
 *
 * Sector-specific profile content is parsed but preserved as raw structure
 * for P3 sector-routing to consume from ingest_file.sidecar_json — the
 * common envelope fields are what IngestService::ingestFile() needs today.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgScan\Services;

use Illuminate\Support\Facades\DB;

class SidecarParser
{
    public const NAMESPACE = 'https://heratio.io/scan/v1';

    /**
     * Parse a sidecar file into a normalised array.
     *
     * Returns an associative array with these top-level keys:
     *   sector:                'archive'|'library'|'gallery'|'museum'|null
     *   standard:              string|null
     *   parent_id:             int|null          resolved from parentSlug/parentId/parentIdentifier
     *   repository_id:         int|null          resolved from repositorySlug
     *   identifier:            string|null
     *   title:                 string|null
     *   level_of_description_id: int|null       (future)
     *   scope_and_content:     string|null
     *   source_standard:       string|null       (ISAD source_standard column)
     *   dates:                 array[]           [['type'=>'creation','start'=>'YYYY-MM-DD','end'=>'...']]
     *   creators:              array[]           [['name'=>'...', 'vocab'=>'ulan', 'uri'=>'...']]
     *   publication_status:    string|null
     *   access_conditions:     string|null
     *   rights:                array             rightsStatement uri, ccLicense, embargoUntil, odrlPolicy, rightsHolder
     *   digital_object:        array             usage, makeDerivatives, ocr, htr, iiif
     *   sector_profile:        array|null        raw archive/library/gallery/museumProfile as key=>value
     *   dam_augmentation:      array|null        raw damAugmentation block
     *   custom_fields:         array             name=>value
     *   merge:                 string            add-sequence (default) | replace | error
     *   _warnings:             string[]          non-fatal parse issues
     *
     * @throws \RuntimeException when the file is unreadable or not well-formed XML.
     */
    public function parse(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException("Sidecar not readable: {$path}");
        }
        $xml = file_get_contents($path);
        if ($xml === '' || $xml === false) {
            throw new \RuntimeException("Sidecar is empty: {$path}");
        }

        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        if (!$doc->documentElement || $doc->documentElement->localName !== 'heratioScan') {
            throw new \RuntimeException("Sidecar root is not <heratioScan>: {$path}");
        }

        $warnings = [];
        foreach ($errors as $err) {
            if ($err->level === LIBXML_ERR_FATAL) {
                throw new \RuntimeException("Sidecar XML fatal: " . trim($err->message));
            }
            $warnings[] = trim($err->message);
        }

        $root = $doc->documentElement;
        $result = [
            'sector' => $this->child($root, 'sector'),
            'standard' => $this->child($root, 'standard'),
            'parent_id' => null,
            'repository_id' => null,
            'identifier' => $this->child($root, 'identifier'),
            'title' => $this->child($root, 'title'),
            'level_of_description' => $this->child($root, 'levelOfDescription'),
            'scope_and_content' => null,
            'source_standard' => $this->child($root, 'standard'),
            'dates' => [],
            'creators' => [],
            'publication_status' => $this->child($root, 'publicationStatus'),
            'access_conditions' => $this->child($root, 'accessConditions'),
            'rights' => [],
            'digital_object' => [],
            'sector_profile' => null,
            'dam_augmentation' => null,
            'custom_fields' => [],
            'merge' => $this->child($root, 'merge') ?: 'add-sequence',
            '_warnings' => $warnings,
        ];

        // Destination resolution
        $parentSlug = $this->child($root, 'parentSlug');
        $parentId = $this->child($root, 'parentId');
        $parentIdentifier = $this->child($root, 'parentIdentifier');
        $repoSlug = $this->child($root, 'repositorySlug');

        if ($parentId && ctype_digit((string) $parentId)) {
            $result['parent_id'] = (int) $parentId;
        } elseif ($parentSlug) {
            $row = DB::table('slug')->where('slug', $parentSlug)->first();
            $result['parent_id'] = $row ? (int) $row->object_id : null;
            if (!$result['parent_id']) {
                $warnings[] = "parentSlug '{$parentSlug}' does not match any existing slug";
            }
        } elseif ($parentIdentifier) {
            $row = DB::table('information_object')->where('identifier', $parentIdentifier)->first();
            $result['parent_id'] = $row ? (int) $row->id : null;
            if (!$result['parent_id']) {
                $warnings[] = "parentIdentifier '{$parentIdentifier}' does not match any IO";
            }
        }

        if ($repoSlug) {
            $row = DB::table('slug')->where('slug', $repoSlug)->first();
            if ($row) {
                $isRepo = DB::table('repository')->where('id', $row->object_id)->exists();
                $result['repository_id'] = $isRepo ? (int) $row->object_id : null;
            }
        }

        // Dates
        $datesNode = $this->firstChildEl($root, 'dates');
        if ($datesNode) {
            foreach ($datesNode->getElementsByTagNameNS(self::NAMESPACE, 'date') as $dn) {
                $result['dates'][] = [
                    'type' => $dn->getAttribute('type') ?: 'creation',
                    'start' => $dn->getAttribute('start') ?: null,
                    'end' => $dn->getAttribute('end') ?: null,
                ];
            }
        }

        // Creators — only in profile or archiveProfile but also at envelope level if archive-style
        // (handled under sector_profile below; here we leave the array empty unless envelope has creators)

        // Rights
        $rightsStmt = $this->firstChildEl($root, 'rightsStatement');
        $result['rights'] = [
            'statement_uri' => $rightsStmt ? $rightsStmt->getAttribute('uri') : null,
            'cc_license' => $this->child($root, 'ccLicense'),
            'embargo_until' => null,
            'embargo_reason' => null,
            'odrl_policy' => $this->child($root, 'odrlPolicy'),
            'rights_holder' => $this->child($root, 'rightsHolder'),
            'tk_labels' => [],
        ];
        $embargoNode = $this->firstChildEl($root, 'embargoUntil');
        if ($embargoNode) {
            $result['rights']['embargo_until'] = trim($embargoNode->textContent);
            $result['rights']['embargo_reason'] = $embargoNode->getAttribute('reason') ?: null;
        }
        if ($tkNode = $this->firstChildEl($root, 'tkLabel')) {
            $result['rights']['tk_labels'][] = trim($tkNode->textContent);
        }

        // Digital-object directives
        $doNode = $this->firstChildEl($root, 'digitalObject');
        if ($doNode) {
            $result['digital_object'] = [
                'usage' => $this->child($doNode, 'usage'),
                'make_derivatives' => $this->childBool($doNode, 'makeDerivatives'),
                'ocr' => $this->child($doNode, 'ocr'),
                'htr' => $this->child($doNode, 'htr'),
                'iiif' => $this->child($doNode, 'iiif'),
            ];
        }

        // Sector profile — keep raw structure for P3 sector routing
        foreach (['archiveProfile', 'libraryProfile', 'galleryProfile', 'museumProfile'] as $profileName) {
            $pn = $this->firstChildEl($root, $profileName);
            if ($pn) {
                $result['sector_profile'] = [
                    'profile' => $profileName,
                    'data' => $this->nodeToArray($pn),
                ];
                // Cherry-pick common ISAD-shaped fields for the envelope mapping
                if ($profileName === 'archiveProfile') {
                    $result['scope_and_content'] = $result['scope_and_content'] ?: $this->child($pn, 'scopeAndContent');
                }
                break; // one profile per envelope (per plan 3.2.1)
            }
        }

        // DAM augmentation
        $damNode = $this->firstChildEl($root, 'damAugmentation');
        if ($damNode) {
            $result['dam_augmentation'] = $this->nodeToArray($damNode);
        }

        // Custom fields
        $cfNode = $this->firstChildEl($root, 'customFields');
        if ($cfNode) {
            foreach ($cfNode->getElementsByTagNameNS(self::NAMESPACE, 'field') as $fn) {
                $name = $fn->getAttribute('name');
                if ($name !== '') {
                    $result['custom_fields'][$name] = trim($fn->textContent);
                }
            }
        }

        $result['_warnings'] = $warnings;
        return $result;
    }

    /**
     * Translate parsed sidecar to the meta-array shape expected by
     * IngestService::ingestFile(). Only the common envelope fields travel;
     * sector-profile data is preserved in the caller's sidecar_json for P3.
     */
    public function toIngestMeta(array $parsed, ?object $sessionFallback = null): array
    {
        return array_filter([
            'parent_id' => $parsed['parent_id'] ?? $sessionFallback?->parent_id ?? null,
            'identifier' => $parsed['identifier'] ?? null,
            'title' => $parsed['title'] ?? $parsed['identifier'] ?? null,
            'level_of_description_id' => null,  // lookup by label future work
            'repository_id' => $parsed['repository_id'] ?? $sessionFallback?->repository_id ?? null,
            'scope_and_content' => $parsed['scope_and_content'] ?? null,
            'source_standard' => $parsed['source_standard'] ?? $sessionFallback?->standard ?? null,
            'merge' => $parsed['merge'] ?? 'add-sequence',
            'culture' => 'en',
        ], fn($v) => $v !== null);
    }

    // --- helpers ---

    protected function child(\DOMElement $parent, string $localName): ?string
    {
        $list = $parent->getElementsByTagNameNS(self::NAMESPACE, $localName);
        if ($list->length === 0) { return null; }
        foreach ($list as $node) {
            if ($node->parentNode === $parent) {
                $v = trim($node->textContent);
                return $v === '' ? null : $v;
            }
        }
        return null;
    }

    protected function firstChildEl(\DOMElement $parent, string $localName): ?\DOMElement
    {
        foreach ($parent->getElementsByTagNameNS(self::NAMESPACE, $localName) as $node) {
            if ($node->parentNode === $parent) {
                return $node;
            }
        }
        return null;
    }

    protected function childBool(\DOMElement $parent, string $localName): ?bool
    {
        $v = $this->child($parent, $localName);
        if ($v === null) { return null; }
        $v = strtolower($v);
        return in_array($v, ['true', '1', 'yes', 'y', 'on'], true);
    }

    /**
     * Recursively convert a DOM element into a nested array:
     *   <foo><bar>1</bar><baz>x</baz></foo>  →  ['bar'=>'1', 'baz'=>'x']
     *   <foo><item>a</item><item>b</item></foo>  →  ['item'=>['a','b']]
     * Element attributes get stored under '@attributes'.
     */
    protected function nodeToArray(\DOMElement $node): array
    {
        $out = [];
        if ($node->hasAttributes()) {
            $attrs = [];
            foreach ($node->attributes as $attr) {
                $attrs[$attr->name] = $attr->value;
            }
            $out['@attributes'] = $attrs;
        }
        foreach ($node->childNodes as $child) {
            if (!$child instanceof \DOMElement) { continue; }
            $key = $child->localName;
            $value = $this->hasElementChildren($child) ? $this->nodeToArray($child) : trim($child->textContent);
            if ($child->hasAttributes() && !$this->hasElementChildren($child)) {
                $attrs = [];
                foreach ($child->attributes as $attr) {
                    $attrs[$attr->name] = $attr->value;
                }
                $value = ['@attributes' => $attrs, 'value' => $value];
            }
            if (isset($out[$key])) {
                if (!is_array($out[$key]) || !array_is_list($out[$key])) {
                    $out[$key] = [$out[$key]];
                }
                $out[$key][] = $value;
            } else {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    protected function hasElementChildren(\DOMElement $node): bool
    {
        foreach ($node->childNodes as $c) {
            if ($c instanceof \DOMElement) { return true; }
        }
        return false;
    }
}
