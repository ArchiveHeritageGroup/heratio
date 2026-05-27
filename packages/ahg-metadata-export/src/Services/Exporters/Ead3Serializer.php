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
        $relations = $this->fetchIoRelations($io);
        $legalStatus = $this->fetchLegalStatus($io);

        // IPTC fallback (issue #752). Mirrors the EAD2002 wiring - swap in
        // IPTC By-line / Keywords / Copyright Notice when the ISAD(G)
        // canonical fields are empty.
        $iptcResolver = new \AhgMetadataExport\Services\IptcFallbackResolver();
        $canonicalCreators = $creators
            ->pluck('name')
            ->filter()
            ->map(static fn ($v) => (string) $v)
            ->all();
        $iptcCreatorNames = $iptcResolver->resolveCreatorsWithCanonical($objectId, $canonicalCreators);
        if ($creators->isEmpty() && ! empty($iptcCreatorNames)) {
            $creators = collect(array_map(static fn ($n) => (object) [
                'name' => $n,
                'entity_type_id' => null,
                'actor_id' => null,
            ], $iptcCreatorNames));
        }

        $canonicalSubjectNames = $subjects
            ->pluck('name')
            ->filter()
            ->map(static fn ($v) => (string) $v)
            ->all();
        $resolvedSubjects = $iptcResolver->resolveSubjectsWithCanonical($objectId, $canonicalSubjectNames);
        if ($subjects->isEmpty() && ! empty($resolvedSubjects)) {
            $subjects = collect(array_map(static fn ($n) => (object) ['name' => $n], $resolvedSubjects));
        }

        $canonicalRights = ! empty($io->reproduction_conditions)
            ? (string) $io->reproduction_conditions
            : null;
        $resolvedRights = $iptcResolver->resolveRightsWithCanonical($objectId, $canonicalRights);

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
        // <userestrict> = ISAD 3.4.2 with IPTC Copyright Notice fallback (#752).
        if ($resolvedRights !== null && $resolvedRights !== '') {
            $xml .= '  <userestrict><p>'.$this->escXml($resolvedRights)."</p></userestrict>\n";
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

        // <legalstatus> - publication / disclosure status. Mapped from the
        // status table (type_id=158 publication status) when present so
        // EAD3 harvesters can distinguish Published vs Draft. EAD3 schema
        // requires <legalstatus> to live inside <accessrestrict>.
        if ($legalStatus !== null) {
            $xml .= '  <accessrestrict><legalstatus>'.$this->escXml($legalStatus)."</legalstatus></accessrestrict>\n";
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

        // <relations> - EAD3 cross-references between IOs. Sourced from
        // the generic `relation` table (subject_id -> object_id). Each row
        // emits a <relation relationtype="otherrelationtype" href="...">
        // pointing at the slug of the related object.
        if (! empty($relations)) {
            $xml .= "  <relations>\n";
            foreach ($relations as $r) {
                $type = $r->relationtype ?: 'otherrelationtype';
                $href = $r->slug ? '/'.$r->slug : ('#io-'.$r->related_id);
                $label = $r->related_title ?: $r->related_identifier ?: ('IO #'.$r->related_id);
                $xml .= '    <relation relationtype="'.$this->escXml($type).'" href="'.$this->escXml($href).'">';
                $xml .= '<relationentry>'.$this->escXml($label).'</relationentry>';
                $xml .= "</relation>\n";
            }
            $xml .= "  </relations>\n";
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

    /**
     * Fetch IO-to-IO cross-references from the generic `relation` table.
     * Returns rows shaped {related_id, related_title, related_identifier,
     * slug, relationtype}. Empty array when the relation table is missing
     * or holds no IO links for this object.
     *
     * Skipped when the schema isn't present so callers in test envs
     * without a DB still serialize cleanly.
     *
     * @return array<int, object>
     */
    protected function fetchIoRelations($io): array
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('relation')) {
                return [];
            }
            // Both directions: where the IO is the subject OR the object.
            $forward = DB::table('relation as r')
                ->join('information_object as io2', 'io2.id', '=', 'r.object_id')
                ->leftJoin('information_object_i18n as i18n', function ($j) {
                    $j->on('i18n.id', '=', 'io2.id')->where('i18n.culture', 'en');
                })
                ->leftJoin('slug as s', 's.object_id', '=', 'io2.id')
                ->leftJoin('term_i18n as ti', function ($j) {
                    $j->on('ti.id', '=', 'r.type_id')->where('ti.culture', 'en');
                })
                ->where('r.subject_id', $io->id)
                ->select(
                    'io2.id as related_id',
                    'i18n.title as related_title',
                    'io2.identifier as related_identifier',
                    's.slug',
                    'ti.name as relationtype'
                )
                ->get();
            $reverse = DB::table('relation as r')
                ->join('information_object as io2', 'io2.id', '=', 'r.subject_id')
                ->leftJoin('information_object_i18n as i18n', function ($j) {
                    $j->on('i18n.id', '=', 'io2.id')->where('i18n.culture', 'en');
                })
                ->leftJoin('slug as s', 's.object_id', '=', 'io2.id')
                ->leftJoin('term_i18n as ti', function ($j) {
                    $j->on('ti.id', '=', 'r.type_id')->where('ti.culture', 'en');
                })
                ->where('r.object_id', $io->id)
                ->select(
                    'io2.id as related_id',
                    'i18n.title as related_title',
                    'io2.identifier as related_identifier',
                    's.slug',
                    'ti.name as relationtype'
                )
                ->get();
            return $forward->concat($reverse)->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Map this IO's publication status (status table, type_id=158) to a
     * short EAD3 legalstatus string. Returns null when no published-status
     * row exists or the schema isn't reachable.
     */
    protected function fetchLegalStatus($io): ?string
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('status')) {
                return null;
            }
            $row = DB::table('status as s')
                ->leftJoin('term_i18n as ti', function ($j) {
                    $j->on('ti.id', '=', 's.status_id')->where('ti.culture', 'en');
                })
                ->where('s.object_id', $io->id)
                ->where('s.type_id', 158)
                ->select('s.status_id', 'ti.name')
                ->first();
            if (! $row) {
                return null;
            }
            // Status 160 = Published, 159 = Draft. EAD3 legalstatus is
            // free text but harvesters tend to expect a small enumerated
            // vocabulary, so we normalise the two known values.
            $sid = (int) $row->status_id;
            if ($sid === 160) {
                return 'Published';
            }
            if ($sid === 159) {
                return 'Draft';
            }
            return $row->name ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
