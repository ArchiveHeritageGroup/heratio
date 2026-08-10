<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgMetadataExport\Services\Exporters\Concerns;

/**
 * Render a CRM node bag to Turtle. renderTurtle + ttlValue were byte-identical
 * in CidocCrmSerializer and CrmRdfRenderer. renderTurtle uses the host's
 * NS_RDF/RDFS/XSD/CRM/ECRM namespace constants.
 */
trait RendersCrmTurtle
{
    private function renderTurtle(array $bag): string
    {
        $culture = $bag['culture'];
        $ttl  = '@prefix rdf: <' . self::NS_RDF . "> .\n";
        $ttl .= '@prefix rdfs: <' . self::NS_RDFS . "> .\n";
        $ttl .= '@prefix xsd: <' . self::NS_XSD . "> .\n";
        $ttl .= '@prefix crm: <' . self::NS_CRM . "> .\n";
        $ttl .= '@prefix ecrm: <' . self::NS_ECRM . "> .\n\n";

        foreach ($bag['nodes'] as [$uri, $typeCurie, $props]) {
            $ttl .= '<' . $uri . '> a ' . $typeCurie;
            foreach ($props as [$pred, $value, $kind]) {
                $ttl .= ' ;' . "\n" . '  ' . $pred . ' ' . $this->ttlValue($value, $kind, $culture);
            }
            $ttl .= " .\n\n";
        }

        return $ttl;
    }

    private function ttlValue(string $value, string $kind, string $culture): string
    {
        switch ($kind) {
            case 'iri':
                return '<' . $value . '>';
            case 'date':
                return '"' . addcslashes($value, "\\\"\n\r") . '"^^xsd:date';
            case 'lang':
                return '"' . addcslashes($value, "\\\"\n\r") . '"@' . $culture;
            case 'plain':
            default:
                return '"' . addcslashes($value, "\\\"\n\r") . '"';
        }
    }
}
