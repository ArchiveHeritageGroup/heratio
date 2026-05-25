<?php

/**
 * Ead3Serializer - EAD 3 XML serializer.
 *
 * Produces the <ead>...</ead> element body conforming to the EAD3 schema
 * (https://ead3.archivists.org/schema/). Used for standalone download and
 * for OAI-PMH dissemination (metadataPrefix oai_ead3).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace AhgMetadataExport\Services\Exporters;

use Illuminate\Support\Facades\DB;

class Ead3Serializer
{
    use InformationObjectFetcher;

    public function getFormat(): string
    {
        return 'oai_ead3';
    }

    public function getSchemaUrl(): string
    {
        return 'https://www.loc.gov/ead/ead3.xsd';
    }

    public function getNamespace(): string
    {
        return 'http://ead3.archivists.org/schema/';
    }

    public function serializeRecord(int $objectId, string $culture = 'en', bool $includeChildren = true): string
    {
        $io = $this->fetchIo($objectId, $culture);
        if (! $io) {
            return '';
        }

        $repository = $this->fetchRepository($io, $culture);
        $events = $this->fetchEvents($io, $culture);
        $creators = $this->fetchCreators($io, $culture);
        $subjects = $this->fetchAccessPoints($io, 35, $culture);
        $places = $this->fetchAccessPoints($io, 42, $culture);
        $genres = $this->fetchAccessPoints($io, 78, $culture);
        $levelName = $this->fetchLevelName($io, $culture);
        $children = $includeChildren ? $this->fetchDescendants($io, $culture) : collect();

        $eadLevel = $this->mapLevelToEad($levelName);
        $dateNormal = gmdate('Y-m-d');

        $xml = '<ead xmlns="'.$this->getNamespace().'"';
        $xml .= ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"';
        $xml .= ' xmlns:xlink="http://www.w3.org/1999/xlink"';
        $xml .= ' xsi:schemaLocation="'.$this->getNamespace().' '.$this->getSchemaUrl().'">'."\n";

        // Control element (EAD3 replaces eadheader)
        $xml .= "<control>\n";
        $xml .= '  <recordid>'.$this->escXml($io->identifier ?: $io->slug ?: (string) $io->id)."</recordid>\n";
        $xml .= "  <filedesc>\n";
        $xml .= '    <titlestmt><titleproper>'.$this->escXml($io->title)."</titleproper></titlestmt>\n";
        if ($repository) {
            $xml .= '    <publicationstmt><publisher>'.$this->escXml($repository->name)."</publisher></publicationstmt>\n";
        }
        $xml .= "  </filedesc>\n";
        $xml .= "  <maintenancestatus value=\"derived\"/>\n";
        $xml .= '  <maintenanceagency><agencyname>'.$this->escXml(config('app.name', 'Heratio'))."</agencyname></maintenanceagency>\n";
        $xml .= "  <maintenancehistory>\n";
        $xml .= "    <maintenanceevent>\n";
        $xml .= "      <eventtype value=\"derived\"/>\n";
        $xml .= "      <eventdatetime standarddatetime=\"{$dateNormal}\">{$dateNormal}</eventdatetime>\n";
        $xml .= "      <agenttype value=\"machine\"/>\n";
        $xml .= "      <agent>Heratio EAD3 Export</agent>\n";
        $xml .= "    </maintenanceevent>\n";
        $xml .= "  </maintenancehistory>\n";
        $xml .= "</control>\n";

        // Archdesc
        $xml .= "<archdesc level=\"{$eadLevel}\">\n";
        $xml .= "  <did>\n";
        if ($io->identifier) {
            $xml .= '    <unitid>'.$this->escXml($io->identifier)."</unitid>\n";
        }
        $xml .= '    <unittitle>'.$this->escXml($io->title)."</unittitle>\n";

        foreach ($events as $event) {
            $dateStr = $event->date_display ?? '';
            if (! $event->start_date && ! $event->end_date && ! $dateStr) {
                continue;
            }
            if ($event->start_date && $event->end_date) {
                $xml .= "    <unitdatestructured unitdatetype=\"inclusive\">\n";
                $xml .= '      <daterange><fromdate standarddate="'.$this->escXml($event->start_date).'">'.$this->escXml($event->start_date).'</fromdate>';
                $xml .= '<todate standarddate="'.$this->escXml($event->end_date).'">'.$this->escXml($event->end_date)."</todate></daterange>\n";
                $xml .= "    </unitdatestructured>\n";
            } elseif ($event->start_date) {
                $xml .= "    <unitdatestructured>\n";
                $xml .= '      <datesingle standarddate="'.$this->escXml($event->start_date).'">'.$this->escXml($dateStr ?: $event->start_date)."</datesingle>\n";
                $xml .= "    </unitdatestructured>\n";
            } elseif ($dateStr) {
                $xml .= '    <unitdate>'.$this->escXml($dateStr)."</unitdate>\n";
            }
        }

        foreach ($creators as $creator) {
            $localType = match ((int) ($creator->entity_type_id ?? 0)) {
                132 => 'persname', 130 => 'famname', 131 => 'corpname', default => 'name',
            };
            $xml .= "    <origination><{$localType}><part>".$this->escXml($creator->name)."</part></{$localType}></origination>\n";
        }

        if ($io->extent_and_medium) {
            $xml .= "    <physdescstructured physdescstructuredtype=\"spaceoccupied\" coverage=\"whole\">\n";
            $xml .= '      <quantity>1</quantity><unittype>'.$this->escXml($io->extent_and_medium)."</unittype>\n";
            $xml .= "    </physdescstructured>\n";
        }
        if ($repository) {
            $xml .= '    <repository><corpname><part>'.$this->escXml($repository->name)."</part></corpname></repository>\n";
        }
        $xml .= "  </did>\n";

        if ($io->scope_and_content) {
            $xml .= '  <scopecontent><p>'.$this->escXml($io->scope_and_content)."</p></scopecontent>\n";
        }
        if ($io->arrangement) {
            $xml .= '  <arrangement><p>'.$this->escXml($io->arrangement)."</p></arrangement>\n";
        }
        if ($io->access_conditions) {
            $xml .= '  <accessrestrict><p>'.$this->escXml($io->access_conditions)."</p></accessrestrict>\n";
        }
        if ($io->reproduction_conditions) {
            $xml .= '  <userestrict><p>'.$this->escXml($io->reproduction_conditions)."</p></userestrict>\n";
        }
        if ($io->archival_history) {
            $xml .= '  <custodhist><p>'.$this->escXml($io->archival_history)."</p></custodhist>\n";
        }
        if ($io->acquisition) {
            $xml .= '  <acqinfo><p>'.$this->escXml($io->acquisition)."</p></acqinfo>\n";
        }
        if ($io->appraisal) {
            $xml .= '  <appraisal><p>'.$this->escXml($io->appraisal)."</p></appraisal>\n";
        }
        if ($io->accruals) {
            $xml .= '  <accruals><p>'.$this->escXml($io->accruals)."</p></accruals>\n";
        }
        if ($io->physical_characteristics) {
            $xml .= '  <phystech><p>'.$this->escXml($io->physical_characteristics)."</p></phystech>\n";
        }
        if ($io->finding_aids) {
            $xml .= '  <otherfindaid><p>'.$this->escXml($io->finding_aids)."</p></otherfindaid>\n";
        }
        if ($io->location_of_originals) {
            $xml .= '  <originalsloc><p>'.$this->escXml($io->location_of_originals)."</p></originalsloc>\n";
        }
        if ($io->location_of_copies) {
            $xml .= '  <altformavail><p>'.$this->escXml($io->location_of_copies)."</p></altformavail>\n";
        }
        if ($io->related_units_of_description) {
            $xml .= '  <relatedmaterial><p>'.$this->escXml($io->related_units_of_description)."</p></relatedmaterial>\n";
        }

        if ($subjects->isNotEmpty() || $places->isNotEmpty() || $genres->isNotEmpty()) {
            $xml .= "  <controlaccess>\n";
            foreach ($subjects as $s) {
                $xml .= '    <subject><part>'.$this->escXml($s->name)."</part></subject>\n";
            }
            foreach ($places as $p) {
                $xml .= '    <geogname><part>'.$this->escXml($p->name)."</part></geogname>\n";
            }
            foreach ($genres as $g) {
                $xml .= '    <genreform><part>'.$this->escXml($g->name)."</part></genreform>\n";
            }
            $xml .= "  </controlaccess>\n";
        }

        if ($includeChildren && $children->isNotEmpty()) {
            $xml .= "  <dsc dsctype=\"combined\">\n";
            $nestedRgt = [];
            foreach ($children as $child) {
                while (count($nestedRgt) > 0 && $child->rgt > $nestedRgt[count($nestedRgt) - 1]) {
                    array_pop($nestedRgt);
                    $xml .= "    </c>\n";
                }
                $childLevel = $child->level_of_description_id
                    ? $this->mapLevelToEad(DB::table('term_i18n')->where('id', $child->level_of_description_id)->where('culture', $culture)->value('name'))
                    : 'otherlevel';
                $xml .= "    <c level=\"{$childLevel}\">\n";
                $xml .= "      <did>\n";
                if ($child->identifier) {
                    $xml .= '        <unitid>'.$this->escXml($child->identifier)."</unitid>\n";
                }
                $xml .= '        <unittitle>'.$this->escXml($child->title)."</unittitle>\n";
                if ($child->extent_and_medium) {
                    $xml .= '        <physdesc>'.$this->escXml($child->extent_and_medium)."</physdesc>\n";
                }
                $xml .= "      </did>\n";
                if ($child->scope_and_content) {
                    $xml .= '      <scopecontent><p>'.$this->escXml($child->scope_and_content)."</p></scopecontent>\n";
                }
                if ($child->rgt == $child->lft + 1) {
                    $xml .= "    </c>\n";
                } else {
                    $nestedRgt[] = $child->rgt;
                }
            }
            while (count($nestedRgt) > 0) {
                array_pop($nestedRgt);
                $xml .= "    </c>\n";
            }
            $xml .= "  </dsc>\n";
        }

        $xml .= "</archdesc>\n</ead>";

        return $xml;
    }
}
