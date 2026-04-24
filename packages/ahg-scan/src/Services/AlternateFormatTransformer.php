<?php

/**
 * AlternateFormatTransformer — Heratio ahg-scan (P7)
 *
 * Detects non-heratioScan XML sidecars (EAD, MARC21-XML, MODS, LIDO) and
 * transforms them into the canonical heratioScan envelope via XSLT, so
 * the existing SidecarParser can handle them uniformly.
 *
 * Stylesheets live in packages/ahg-scan/resources/transforms/. Only EAD
 * ships with a working XSLT in the initial P7 release; MARC21 / MODS /
 * LIDO receive "transform pending" stubs so operators can see whether
 * the framework picked up their file at all — actual transforms are
 * back-compat easy to add later.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgScan\Services;

use Illuminate\Support\Facades\Log;

class AlternateFormatTransformer
{
    /** Map of detected format → XSLT filename (in resources/transforms/). */
    protected const STYLESHEETS = [
        'ead' => 'ead-to-heratio.xsl',
        'marc21' => 'marc21-to-heratio.xsl',
        'mods' => 'mods-to-heratio.xsl',
        'lido' => 'lido-to-heratio.xsl',
        'mets' => 'mets-to-heratio.xsl',
    ];

    /**
     * Detect the format of an XML sidecar and, if transformable,
     * produce a heratioScan sibling document in a temp file.
     *
     * Returns ['format' => 'ead'|..., 'transformed_path' => string, 'pending' => bool]
     * when recognised; null when the document is already heratioScan or
     * unrecognised.
     *
     * The pending flag is true when we recognised the format but have no
     * XSLT shipped — caller logs a warning and falls back to sidecar=null.
     */
    public static function detectAndTransform(string $sidecarPath): ?array
    {
        if (!is_file($sidecarPath)) { return null; }

        $format = self::detectFormat($sidecarPath);
        if (!$format || $format === 'heratioScan') {
            return null;
        }

        $stylesheet = self::STYLESHEETS[$format] ?? null;
        if (!$stylesheet) {
            return ['format' => $format, 'transformed_path' => null, 'pending' => true];
        }

        $xslPath = __DIR__ . '/../../resources/transforms/' . $stylesheet;
        if (!is_file($xslPath)) {
            return ['format' => $format, 'transformed_path' => null, 'pending' => true];
        }

        try {
            $xml = new \DOMDocument();
            libxml_use_internal_errors(true);
            $xml->load($sidecarPath);

            $xsl = new \DOMDocument();
            $xsl->load($xslPath);

            $proc = new \XSLTProcessor();
            $proc->importStyleSheet($xsl);

            $out = $proc->transformToXml($xml);
            libxml_clear_errors();

            if ($out === false || trim($out) === '') {
                return ['format' => $format, 'transformed_path' => null, 'pending' => true];
            }

            $tmp = sys_get_temp_dir() . '/heratio-sidecar-' . bin2hex(random_bytes(6)) . '.xml';
            file_put_contents($tmp, $out);
            return ['format' => $format, 'transformed_path' => $tmp, 'pending' => false];
        } catch (\Throwable $e) {
            Log::warning('[ahg-scan] XSLT transform failed for ' . $sidecarPath . ': ' . $e->getMessage());
            return ['format' => $format, 'transformed_path' => null, 'pending' => true];
        }
    }

    /**
     * Peek at the document root to decide which format we're looking at.
     * Keeps the check cheap — just reads the root tag name + default
     * namespace without parsing the whole file.
     */
    protected static function detectFormat(string $path): ?string
    {
        $reader = new \XMLReader();
        if (!$reader->open($path)) { return null; }
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT) {
                $local = $reader->localName;
                $ns = $reader->namespaceURI ?? '';
                $reader->close();
                // heratioScan — already canonical
                if ($local === 'heratioScan' && str_starts_with($ns, 'https://heratio.io/scan')) {
                    return 'heratioScan';
                }
                // EAD 2002 / 3
                if (in_array($local, ['ead', 'archdesc', 'c'], true) &&
                    (str_contains($ns, 'urn:isbn:1-931666-22-9') || str_contains($ns, 'ead3'))) {
                    return 'ead';
                }
                // MARC21 XML
                if ($local === 'record' && str_contains($ns, 'loc.gov/MARC21')) { return 'marc21'; }
                if ($local === 'collection' && str_contains($ns, 'loc.gov/MARC21')) { return 'marc21'; }
                // MODS
                if ($local === 'mods' && str_contains($ns, 'loc.gov/mods')) { return 'mods'; }
                if ($local === 'modsCollection' && str_contains($ns, 'loc.gov/mods')) { return 'mods'; }
                // LIDO
                if ($local === 'lido' && str_contains($ns, 'lido-schema.org')) { return 'lido'; }
                if ($local === 'lidoWrap') { return 'lido'; }
                // METS — wrapper that can carry DC/MODS/etc. inside dmdSec
                if ($local === 'mets' && str_contains($ns, 'loc.gov/METS')) { return 'mets'; }
                return null;
            }
        }
        $reader->close();
        return null;
    }
}
