<?php

/**
 * EadXmlImporter - parse a native EAD2002 or EAD3 finding aid and create /
 * update information_object rows (Heratio archival descriptions).
 *
 * Two-phase API mirrors MarcXmlImporter (#663 Phase 2):
 *
 *   $importer = new EadXmlImporter();
 *   $preview  = $importer->preview($xmlString, $culture);  // dry-run, no writes
 *   $result   = $importer->commit($xmlString, $culture);   // writes + audit
 *
 * Preview returns an array of parsed records, each shaped as:
 *   ['eadid' => '...', 'unitid' => '...', 'title' => '...',
 *    'will_create' => bool, 'matched_io_id' => ?int,
 *    'children' => [...recursive...], 'warnings' => [...]]
 *
 * Commit returns the same shape augmented with 'io_id' => int and 'action'
 * => 'create'|'update' per node, plus a hash-chained audit row id when
 * AhgAuditTrail\Services\AuditLogger is present.
 *
 * Handles BOTH EAD 2002 (root <ead> in namespace urn:isbn:1-931666-22-9)
 * and EAD 3 (root <ead> in namespace http://ead3.archivists.org/schema/).
 * Recursion is uniform - <archdesc> at the top, then <c01>...<c12> +
 * generic <c> nested arbitrarily deep.
 *
 * Round-trip safe for: title, identifier, scope_and_content,
 * extent_and_medium, archival_history, acquisition,
 * reproduction_conditions, access_conditions, arrangement, appraisal,
 * accruals, physical_characteristics, finding_aids,
 * location_of_originals, location_of_copies,
 * related_units_of_description.
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
 */

namespace AhgMetadataExport\Services\Importers;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class EadXmlImporter
{
    public const NS_EAD2002 = 'urn:isbn:1-931666-22-9';

    public const NS_EAD3 = 'http://ead3.archivists.org/schema/';

    private string $culture = 'en';

    /** @var 'ead2002'|'ead3'|null */
    private ?string $detectedVariant = null;

    /** @var class-string|null */
    private ?string $auditLoggerClass = null;

    public function __construct()
    {
        if (class_exists('\\AhgAuditTrail\\Services\\AuditLogger')) {
            $this->auditLoggerClass = '\\AhgAuditTrail\\Services\\AuditLogger';
        }
    }

    /**
     * Detect EAD variant from the root element namespace + tag layout.
     * Returns 'ead2002', 'ead3', or null when no <ead> root is present.
     */
    public function detectVariant(string $xml): ?string
    {
        $prev = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $ok = @$dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (! $ok || ! $dom->documentElement) {
            return null;
        }
        $root = $dom->documentElement;
        if ($root->localName !== 'ead') {
            return null;
        }
        $ns = $root->namespaceURI ?: '';
        if ($ns === self::NS_EAD3) {
            return 'ead3';
        }
        if ($ns === self::NS_EAD2002) {
            return 'ead2002';
        }
        // Some publishers drop the namespace entirely. Fall back to a
        // structural sniff: EAD3 has <control>, EAD2002 has <eadheader>.
        $xp = new DOMXPath($dom);
        if ($xp->query('//control')->length > 0) {
            return 'ead3';
        }
        if ($xp->query('//eadheader')->length > 0) {
            return 'ead2002';
        }
        return null;
    }

    /**
     * Validate an EAD payload against the vendored XSD for the detected
     * variant. Returns [valid, errors]. Errors are libxml-formatted strings.
     *
     * @return array{0: bool, 1: array<int,string>}
     */
    public function validate(string $xml): array
    {
        $variant = $this->detectVariant($xml);
        if ($variant === null) {
            return [false, ['No <ead> root element detected (expected EAD2002 or EAD3)']];
        }
        $schemaFile = $variant === 'ead3' ? 'ead3.xsd' : 'ead2002.xsd';
        $schemaPath = __DIR__.'/../../../resources/schemas/'.$schemaFile;
        if (! is_readable($schemaPath)) {
            // Schema is optional - report unverified rather than fail when
            // the vendored copy isn't present (some installs ship without
            // the XSDs to keep the package small).
            return [true, ['Vendored '.$schemaFile.' not found - skipped XSD validation']];
        }
        $prev = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $dom = new DOMDocument();
        if (! @$dom->loadXML($xml)) {
            $errs = $this->collectLibxmlErrors();
            libxml_use_internal_errors($prev);
            return [false, $errs ?: ['Not well-formed XML']];
        }
        $ok = @$dom->schemaValidate($schemaPath);
        $errors = $ok ? [] : $this->collectLibxmlErrors();
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return [(bool) $ok, $errors];
    }

    /**
     * Parse the EAD payload and return a hierarchical preview of every
     * archival description it contains. No DB writes.
     *
     * @return array<int, array<string,mixed>>
     */
    public function preview(string $xml, string $culture = 'en'): array
    {
        $this->culture = $culture;
        $tree = $this->parseTree($xml);
        if ($tree === null) {
            return [];
        }
        return [$this->describeNode($tree, withMatch: true)];
    }

    /**
     * Parse + write every archival description into information_object.
     * Each successful write emits a chained audit row. Returns the same
     * tree shape as preview() with 'io_id' + 'action' + 'audit_id' on
     * every node.
     *
     * @return array<int, array<string,mixed>>
     */
    public function commit(string $xml, string $culture = 'en'): array
    {
        $this->culture = $culture;
        $tree = $this->parseTree($xml);
        if ($tree === null) {
            return [];
        }
        $desc = $this->describeNode($tree, withMatch: true);
        $this->persistRecursive($desc, parentIoId: null);
        return [$desc];
    }

    /**
     * Public hook into the parse pipeline. Returns the root archdesc node
     * as an associative tree (no I/O), or null when no <archdesc> is
     * present.
     *
     * @return array<string,mixed>|null
     */
    public function parseTree(string $xml): ?array
    {
        $variant = $this->detectVariant($xml);
        if ($variant === null) {
            return null;
        }
        $this->detectedVariant = $variant;

        $prev = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        if (! @$dom->loadXML($xml)) {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            return null;
        }
        libxml_use_internal_errors($prev);

        // Strip the default namespace by routing through localName lookups
        // (DOMXPath needs a registered prefix even for the default xmlns,
        // and we don't want every caller juggling prefixes).
        $archdesc = $this->firstLocal($dom->documentElement, 'archdesc');
        if (! $archdesc) {
            return null;
        }
        $eadid = $this->extractEadid($dom->documentElement);
        $tree = $this->buildSubtree($archdesc, isArchdesc: true);
        $tree['eadid'] = $eadid;
        return $tree;
    }

    /**
     * Recursively build the import tree from a <archdesc> or <c>/<cNN> element.
     *
     * @return array<string,mixed>
     */
    private function buildSubtree(DOMElement $node, bool $isArchdesc = false): array
    {
        $level = $node->getAttribute('level') ?: 'otherlevel';
        $did = $this->firstLocal($node, 'did');
        $unitid = $did ? $this->textOfLocal($did, 'unitid') : null;
        $unittitle = $did ? $this->textOfLocal($did, 'unittitle') : null;
        $extent = $did ? $this->textOfPhysdesc($did) : null;

        $scope = $this->paraText($node, 'scopecontent');
        $arrangement = $this->paraText($node, 'arrangement');
        $access = $this->paraText($node, 'accessrestrict');
        $reproduction = $this->paraText($node, 'userestrict');
        $custodial = $this->paraText($node, 'custodhist');
        $acq = $this->paraText($node, 'acqinfo');
        $appraisal = $this->paraText($node, 'appraisal');
        $accruals = $this->paraText($node, 'accruals');
        $phystech = $this->paraText($node, 'phystech');
        $findaid = $this->paraText($node, 'otherfindaid');
        $origloc = $this->paraText($node, 'originalsloc');
        $altform = $this->paraText($node, 'altformavail');
        $related = $this->paraText($node, 'relatedmaterial');
        $legalstatus = $this->paraText($node, 'legalstatus');
        $separated = $this->paraText($node, 'separatedmaterial');

        $relations = $this->extractRelations($node);

        $children = [];
        // Direct children: any <cN>, <c>, or inside <dsc> for archdesc.
        $containers = $isArchdesc ? $this->localChildren($node, 'dsc') : [$node];
        foreach ($containers as $container) {
            foreach ($this->localCComponentChildren($container) as $c) {
                $children[] = $this->buildSubtree($c);
            }
        }

        $warnings = [];
        if (empty($unittitle)) {
            $warnings[] = 'Skipped: no <unittitle>';
        }

        return [
            'is_archdesc' => $isArchdesc,
            'level' => $level,
            'unitid' => $unitid,
            'identifier' => $unitid,
            'title' => $unittitle,
            'extent_and_medium' => $extent,
            'scope_and_content' => $scope,
            'arrangement' => $arrangement,
            'access_conditions' => $access,
            'reproduction_conditions' => $reproduction,
            'archival_history' => $custodial,
            'acquisition' => $acq,
            'appraisal' => $appraisal,
            'accruals' => $accruals,
            'physical_characteristics' => $phystech,
            'finding_aids' => $findaid,
            'location_of_originals' => $origloc,
            'location_of_copies' => $altform,
            'related_units_of_description' => $related,
            'legalstatus' => $legalstatus,
            'separatedmaterial' => $separated,
            'relations' => $relations,
            'children' => $children,
            'warnings' => $warnings,
        ];
    }

    /**
     * Decorate a parse tree with match-info (matched_io_id, will_create).
     *
     * @param  array<string,mixed>  $node
     * @return array<string,mixed>
     */
    private function describeNode(array $node, bool $withMatch): array
    {
        if ($withMatch) {
            $matchedIoId = $node['identifier'] ? $this->matchExisting((string) $node['identifier']) : null;
            $node['matched_io_id'] = $matchedIoId;
            $node['will_create'] = $matchedIoId === null;
        }
        $node['eadid'] = $node['eadid'] ?? null;
        $kids = [];
        foreach ($node['children'] as $c) {
            $kids[] = $this->describeNode($c, $withMatch);
        }
        $node['children'] = $kids;
        return $node;
    }

    /**
     * Persist a tree of parsed nodes into information_object rows.
     * Walks pre-order so each child gets its parent's freshly-assigned id.
     *
     * @param  array<string,mixed>  $node
     */
    private function persistRecursive(array &$node, ?int $parentIoId): void
    {
        if (empty($node['title'])) {
            $node['io_id'] = null;
            $node['audit_id'] = null;
            $node['action'] = 'skipped';
        } else {
            try {
                [$ioId, $action] = $this->persistOne($node, $parentIoId);
                $node['io_id'] = $ioId;
                $node['action'] = $action;
                $node['audit_id'] = $this->logAudit($ioId, $action, $node);
            } catch (Throwable $e) {
                $node['error'] = $e->getMessage();
                $node['io_id'] = null;
                $node['audit_id'] = null;
                $node['action'] = 'error';
            }
        }
        foreach ($node['children'] as $idx => &$child) {
            $this->persistRecursive($child, $node['io_id'] ?? $parentIoId);
        }
    }

    /**
     * Write one node. Returns [io_id, 'create'|'update'].
     *
     * @param  array<string,mixed>  $node
     * @return array{0:int,1:string}
     */
    private function persistOne(array $node, ?int $parentIoId): array
    {
        $now = date('Y-m-d H:i:s');
        $existingId = $node['matched_io_id'] ?? null;

        if ($existingId) {
            DB::table('information_object')
                ->where('id', $existingId)
                ->update([
                    'identifier' => $node['identifier'] ?: null,
                ]);
            DB::table('information_object_i18n')
                ->updateOrInsert(
                    ['id' => $existingId, 'culture' => $this->culture],
                    $this->i18nPayload($node)
                );
            DB::table('object')->where('id', $existingId)->update(['updated_at' => $now]);
            return [(int) $existingId, 'update'];
        }

        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitInformationObject',
            'created_at' => $now,
            'updated_at' => $now,
            'source_culture' => $this->culture,
        ]);
        DB::table('information_object')->insert([
            'id' => $objectId,
            'identifier' => $node['identifier'] ?: null,
            'parent_id' => $parentIoId,
            'source_culture' => $this->culture,
            'lft' => 0,
            'rgt' => 0,
        ]);
        DB::table('information_object_i18n')->insert(array_merge(
            ['id' => $objectId, 'culture' => $this->culture],
            $this->i18nPayload($node)
        ));
        return [(int) $objectId, 'create'];
    }

    /**
     * @param  array<string,mixed>  $node
     * @return array<string,mixed>
     */
    private function i18nPayload(array $node): array
    {
        return [
            'title' => $node['title'],
            'scope_and_content' => $node['scope_and_content'],
            'extent_and_medium' => $node['extent_and_medium'],
            'archival_history' => $node['archival_history'],
            'acquisition' => $node['acquisition'],
            'reproduction_conditions' => $node['reproduction_conditions'],
            'access_conditions' => $node['access_conditions'],
            'arrangement' => $node['arrangement'],
            'appraisal' => $node['appraisal'],
            'accruals' => $node['accruals'],
            'physical_characteristics' => $node['physical_characteristics'],
            'finding_aids' => $node['finding_aids'],
            'location_of_originals' => $node['location_of_originals'],
            'location_of_copies' => $node['location_of_copies'],
            'related_units_of_description' => $node['related_units_of_description'],
        ];
    }

    private function matchExisting(string $identifier): ?int
    {
        try {
            if (! Schema::hasTable('information_object')) {
                return null;
            }
            $id = DB::table('information_object')
                ->where('identifier', $identifier)
                ->value('id');
            if ($id) {
                return (int) $id;
            }
            if (ctype_digit($identifier)) {
                $hit = DB::table('information_object')->where('id', (int) $identifier)->value('id');
                if ($hit) {
                    return (int) $hit;
                }
            }
        } catch (Throwable $e) {
            // fall through
        }
        return null;
    }

    private function logAudit(int $ioId, string $action, array $node): ?int
    {
        if ($this->auditLoggerClass === null) {
            return null;
        }
        try {
            $cls = $this->auditLoggerClass;
            $logger = new $cls();
            return $logger->logAction(
                action: 'ead_'.$action,
                entityType: 'information_object',
                entityId: $ioId,
                metadata: [
                    'source' => 'ead-xml-import',
                    'variant' => $this->detectedVariant,
                    'identifier' => $node['identifier'],
                    'title' => $node['title'],
                ],
                entityTitle: $node['title'] ?? null,
            );
        } catch (Throwable $e) {
            return null;
        }
    }

    // ---- DOM helpers --------------------------------------------------------

    private function firstLocal(?DOMNode $parent, string $localName): ?DOMElement
    {
        if (! $parent) {
            return null;
        }
        foreach ($parent->childNodes as $c) {
            if ($c instanceof DOMElement && $c->localName === $localName) {
                return $c;
            }
        }
        return null;
    }

    /** @return array<int, DOMElement> */
    private function localChildren(DOMNode $parent, string $localName): array
    {
        $out = [];
        foreach ($parent->childNodes as $c) {
            if ($c instanceof DOMElement && $c->localName === $localName) {
                $out[] = $c;
            }
        }
        return $out;
    }

    /** Children that look like archival components (<c>, <c01>..<c12>). @return array<int, DOMElement> */
    private function localCComponentChildren(DOMNode $parent): array
    {
        $out = [];
        foreach ($parent->childNodes as $c) {
            if (! ($c instanceof DOMElement)) {
                continue;
            }
            $ln = $c->localName;
            if ($ln === 'c' || (strlen($ln) === 3 && str_starts_with($ln, 'c') && ctype_digit(substr($ln, 1)))) {
                $out[] = $c;
            }
        }
        return $out;
    }

    private function textOfLocal(DOMElement $parent, string $localName): ?string
    {
        $el = $this->firstLocal($parent, $localName);
        return $el ? $this->normaliseText($el->textContent) : null;
    }

    /**
     * Pull physical-description text from a <did> regardless of EAD
     * variant: EAD2002 uses <physdesc><extent>, EAD3 uses
     * <physdescstructured><quantity>/<unittype> or a free-text <physdesc>.
     */
    private function textOfPhysdesc(DOMElement $did): ?string
    {
        // EAD2002 / mixed: <physdesc> direct text or with <extent>
        $physdesc = $this->firstLocal($did, 'physdesc');
        if ($physdesc) {
            $extent = $this->firstLocal($physdesc, 'extent');
            if ($extent) {
                return $this->normaliseText($extent->textContent);
            }
            $txt = $this->normaliseText($physdesc->textContent);
            if ($txt !== '') {
                return $txt;
            }
        }
        // EAD3 structured form
        $structured = $this->firstLocal($did, 'physdescstructured');
        if ($structured) {
            $unittype = $this->firstLocal($structured, 'unittype');
            if ($unittype) {
                return $this->normaliseText($unittype->textContent);
            }
            return $this->normaliseText($structured->textContent);
        }
        return null;
    }

    /**
     * Collect concatenated <p> text from a wrapper element such as
     * <scopecontent>, <accessrestrict>, etc. Returns null when the
     * wrapper is absent.
     */
    private function paraText(DOMElement $node, string $wrapper): ?string
    {
        $wrapEl = $this->firstLocal($node, $wrapper);
        if (! $wrapEl) {
            return null;
        }
        $paras = $this->localChildren($wrapEl, 'p');
        if (empty($paras)) {
            return $this->normaliseText($wrapEl->textContent);
        }
        $parts = [];
        foreach ($paras as $p) {
            $t = $this->normaliseText($p->textContent);
            if ($t !== '') {
                $parts[] = $t;
            }
        }
        return $parts ? implode("\n\n", $parts) : null;
    }

    /**
     * Extract cross-reference <relation> + <ref> elements from an
     * <archdesc> / <c> node. EAD3 puts these inside <relations>; EAD2002
     * uses <ref> inside <relatedmaterial>. Returns a list of
     * ['type' => 'relation'|'ref', 'href' => '#xyz', 'label' => '...'].
     *
     * @return array<int, array<string,?string>>
     */
    private function extractRelations(DOMElement $node): array
    {
        $out = [];
        // EAD3 <relations><relation relationtype="..." href="...">
        $relationsWrap = $this->firstLocal($node, 'relations');
        if ($relationsWrap) {
            foreach ($this->localChildren($relationsWrap, 'relation') as $r) {
                $out[] = [
                    'type' => 'relation',
                    'relationtype' => $r->getAttribute('relationtype') ?: null,
                    'href' => $r->getAttribute('href') ?: $r->getAttributeNS('http://www.w3.org/1999/xlink', 'href') ?: null,
                    'label' => $this->normaliseText($r->textContent),
                ];
            }
        }
        // EAD2002-flavoured <ref> elements inside relatedmaterial
        $rm = $this->firstLocal($node, 'relatedmaterial');
        if ($rm) {
            foreach ($this->localChildren($rm, 'ref') as $r) {
                $out[] = [
                    'type' => 'ref',
                    'relationtype' => null,
                    'href' => $r->getAttribute('href') ?: $r->getAttributeNS('http://www.w3.org/1999/xlink', 'href') ?: null,
                    'label' => $this->normaliseText($r->textContent),
                ];
            }
        }
        return $out;
    }

    private function extractEadid(?DOMElement $root): ?string
    {
        if (! $root) {
            return null;
        }
        // EAD2002 <eadheader><eadid>
        $hdr = $this->firstLocal($root, 'eadheader');
        if ($hdr) {
            $eadid = $this->firstLocal($hdr, 'eadid');
            if ($eadid) {
                return $this->normaliseText($eadid->textContent);
            }
        }
        // EAD3 <control><recordid>
        $ctrl = $this->firstLocal($root, 'control');
        if ($ctrl) {
            $rid = $this->firstLocal($ctrl, 'recordid');
            if ($rid) {
                return $this->normaliseText($rid->textContent);
            }
        }
        return null;
    }

    private function normaliseText(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return trim(preg_replace('/\s+/u', ' ', $value));
    }

    /** @return array<int, string> */
    private function collectLibxmlErrors(): array
    {
        $errors = [];
        foreach (libxml_get_errors() as $err) {
            $errors[] = sprintf('line %d: %s', $err->line, trim($err->message));
        }
        return $errors;
    }
}
