<?php

/**
 * Ead4Serializer - EAD 4 (draft) XML Serializer for Heratio
 *
 * Serializes information object (archival description) data to EAD 4 XML format,
 * aligned with the SAA EAD 4 draft schema and harmonized with EAC-CPF 2.0 and RiC.
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
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgMetadataExport\Services\Exporters;

use Illuminate\Support\Facades\DB;

class Ead4Serializer
{
    /**
     * EAD 4 namespace URI (draft).
     */
    public const NS_EAD = 'https://archivists.org/ns/ead/v4';

    /**
     * XSI namespace URI.
     */
    public const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    /**
     * XLink namespace URI.
     */
    public const NS_XLINK = 'http://www.w3.org/1999/xlink';

    /**
     * @var \DOMDocument
     */
    protected \DOMDocument $dom;

    /**
     * Level of description mapping from ISAD(G) to EAD.
     */
    protected array $levelMap = [
        'Fonds' => 'fonds',
        'fonds' => 'fonds',
        'Subfonds' => 'subfonds',
        'subfonds' => 'subfonds',
        'Collection' => 'collection',
        'collection' => 'collection',
        'Series' => 'series',
        'series' => 'series',
        'Subseries' => 'subseries',
        'subseries' => 'subseries',
        'File' => 'file',
        'file' => 'file',
        'Item' => 'item',
        'item' => 'item',
        'Part' => 'otherlevel',
        'Record group' => 'recordgrp',
        'recordgrp' => 'recordgrp',
    ];

    /**
     * Return the format identifier.
     */
    public function getFormat(): string
    {
        return 'ead4';
    }

    /**
     * Return the human-readable format name.
     */
    public function getFormatName(): string
    {
        return 'EAD 4';
    }

    /**
     * Serialize an information object record to EAD 4 XML.
     *
     * @param int    $objectId        The information_object ID.
     * @param string $culture         The i18n culture code.
     * @param bool   $includeChildren Whether to include child descriptions recursively.
     *
     * @return string The EAD 4 XML document.
     */
    public function serializeRecord(int $objectId, string $culture = 'en', bool $includeChildren = true): string
    {
        $this->dom = new \DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;

        // Fetch information object base record
        $io = DB::table('information_object')
            ->where('id', $objectId)
            ->first();

        if (!$io) {
            return $this->emptyDocument('Information object not found');
        }

        // Fetch i18n data
        $ioI18n = DB::table('information_object_i18n')
            ->where('id', $objectId)
            ->where('culture', $culture)
            ->first();

        if (!$ioI18n) {
            $ioI18n = DB::table('information_object_i18n')
                ->where('id', $objectId)
                ->where('culture', $io->source_culture)
                ->first();
        }

        if (!$ioI18n) {
            return $this->emptyDocument('Information object i18n data not found');
        }

        // Fetch slug
        $slug = DB::table('slug')
            ->where('object_id', $objectId)
            ->value('slug');

        // Fetch level of description name
        $levelName = null;
        if ($io->level_of_description_id) {
            $levelName = DB::table('term_i18n')
                ->where('id', $io->level_of_description_id)
                ->where('culture', $culture)
                ->value('name');
        }

        // Fetch repository name
        $repoName = null;
        if ($io->repository_id) {
            $repoName = DB::table('actor_i18n')
                ->where('id', $io->repository_id)
                ->where('culture', $culture)
                ->value('authorized_form_of_name');
        }

        // Fetch publication status from status table (type_id = 158)
        $pubStatus = DB::table('status')
            ->join('term_i18n', function ($join) use ($culture) {
                $join->on('status.status_id', '=', 'term_i18n.id')
                     ->where('term_i18n.culture', '=', $culture);
            })
            ->where('status.object_id', $objectId)
            ->where('status.type_id', 158)
            ->value('term_i18n.name');

        // Fetch events (dates, creators)
        $events = DB::table('event')
            ->leftJoin('event_i18n', function ($join) use ($culture) {
                $join->on('event.id', '=', 'event_i18n.id')
                     ->where('event_i18n.culture', '=', $culture);
            })
            ->leftJoin('actor_i18n', function ($join) use ($culture) {
                $join->on('event.actor_id', '=', 'actor_i18n.id')
                     ->where('actor_i18n.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as event_type', function ($join) use ($culture) {
                $join->on('event.type_id', '=', 'event_type.id')
                     ->where('event_type.culture', '=', $culture);
            })
            ->where('event.object_id', $objectId)
            ->select(
                'event.id',
                'event.start_date',
                'event.end_date',
                'event.type_id',
                'event.actor_id',
                'event_i18n.name as event_name',
                'event_i18n.description as event_description',
                'event_i18n.date as date_display',
                'actor_i18n.authorized_form_of_name as actor_name',
                'event_type.name as event_type_name'
            )
            ->get();

        // Fetch digital objects
        $digitalObjects = DB::table('digital_object')
            ->where('object_id', $objectId)
            ->select('id', 'name', 'path', 'mime_type', 'byte_size', 'usage_id')
            ->get();

        // Build root <ead> element
        $ead = $this->dom->createElementNS(self::NS_EAD, 'ead');
        $this->dom->appendChild($ead);

        $ead->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', self::NS_XSI);
        $ead->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xlink', self::NS_XLINK);
        $ead->setAttributeNS(
            self::NS_XSI,
            'xsi:schemaLocation',
            self::NS_EAD . ' https://archivists.org/ns/ead/v4/ead4.xsd'
        );

        // <control>
        $control = $this->buildControl($io, $ioI18n, $slug, $repoName, $culture);
        $ead->appendChild($control);

        // <archdesc>
        $archdesc = $this->buildArchdesc($io, $ioI18n, $levelName, $repoName, $events, $digitalObjects, $pubStatus, $culture, $includeChildren);
        $ead->appendChild($archdesc);

        return $this->dom->saveXML();
    }

    /**
     * Build the <control> element.
     */
    protected function buildControl(object $io, object $ioI18n, ?string $slug, ?string $repoName, string $culture): \DOMElement
    {
        $control = $this->el('control');

        // <recordId>
        $recordId = $slug ?? ($io->identifier ?? ('io-' . $io->id));
        $control->appendChild($this->el('recordId', $recordId));

        // <filedesc>
        $filedesc = $this->el('filedesc');
        $titlestmt = $this->el('titlestmt');
        if (!empty($ioI18n->title)) {
            $titlestmt->appendChild($this->el('titleproper', $ioI18n->title));
        }
        $filedesc->appendChild($titlestmt);

        if ($repoName) {
            $publicationstmt = $this->el('publicationstmt');
            $publicationstmt->appendChild($this->el('publisher', $repoName));
            $filedesc->appendChild($publicationstmt);
        }
        $control->appendChild($filedesc);

        // <maintenanceStatus>
        $maintenanceStatus = $this->el('maintenanceStatus');
        $maintenanceStatus->setAttribute('value', 'derived');
        $control->appendChild($maintenanceStatus);

        // <maintenanceAgency>
        $maintenanceAgency = $this->el('maintenanceAgency');
        $maintenanceAgency->appendChild($this->el('agencyName', $repoName ?? 'Heratio'));
        $control->appendChild($maintenanceAgency);

        // <languageDeclaration>
        $languageDeclaration = $this->el('languageDeclaration');
        $language = $this->el('language');
        $language->setAttribute('languageCode', $culture === 'en' ? 'eng' : $culture);
        $languageDeclaration->appendChild($language);
        $scriptEl = $this->el('script');
        $scriptEl->setAttribute('scriptCode', 'Latn');
        $languageDeclaration->appendChild($scriptEl);
        $control->appendChild($languageDeclaration);

        // <maintenanceHistory>
        $maintenanceHistory = $this->el('maintenanceHistory');
        $maintenanceEvent = $this->el('maintenanceEvent');

        $eventType = $this->el('eventType');
        $eventType->setAttribute('value', 'derived');
        $maintenanceEvent->appendChild($eventType);
        $maintenanceEvent->appendChild($this->el('eventDateTime', date('c')));

        $agentType = $this->el('agentType');
        $agentType->setAttribute('value', 'machine');
        $maintenanceEvent->appendChild($agentType);
        $maintenanceEvent->appendChild($this->el('agent', 'Heratio EAD 4 Serializer'));

        $maintenanceHistory->appendChild($maintenanceEvent);
        $control->appendChild($maintenanceHistory);

        return $control;
    }

    /**
     * Build the <archdesc> element.
     */
    protected function buildArchdesc(
        object $io,
        object $ioI18n,
        ?string $levelName,
        ?string $repoName,
        $events,
        $digitalObjects,
        ?string $pubStatus,
        string $culture,
        bool $includeChildren
    ): \DOMElement {
        $level = $this->mapLevel($levelName);
        $archdesc = $this->el('archdesc');
        $archdesc->setAttribute('level', $level);

        // <did>
        $did = $this->buildDid($io, $ioI18n, $repoName, $events, $culture);
        $archdesc->appendChild($did);

        // <bioghist> from creators
        foreach ($events as $event) {
            if ($event->actor_id) {
                $actorHistory = DB::table('actor_i18n')
                    ->where('id', $event->actor_id)
                    ->where('culture', $culture)
                    ->value('history');
                if (!empty($actorHistory)) {
                    $bioghist = $this->el('bioghist');
                    $bioghist->appendChild($this->el('p', $actorHistory));
                    $archdesc->appendChild($bioghist);
                    break;
                }
            }
        }

        // <scopecontent>
        if (!empty($ioI18n->scope_and_content)) {
            $scopecontent = $this->el('scopecontent');
            $scopecontent->appendChild($this->el('p', $ioI18n->scope_and_content));
            $archdesc->appendChild($scopecontent);
        }

        // <arrangement>
        if (!empty($ioI18n->arrangement)) {
            $arrangement = $this->el('arrangement');
            $arrangement->appendChild($this->el('p', $ioI18n->arrangement));
            $archdesc->appendChild($arrangement);
        }

        // <accessrestrict>
        if (!empty($ioI18n->access_conditions)) {
            $accessrestrict = $this->el('accessrestrict');
            $accessrestrict->appendChild($this->el('p', $ioI18n->access_conditions));
            $archdesc->appendChild($accessrestrict);
        }

        // <userestrict>
        if (!empty($ioI18n->reproduction_conditions)) {
            $userestrict = $this->el('userestrict');
            $userestrict->appendChild($this->el('p', $ioI18n->reproduction_conditions));
            $archdesc->appendChild($userestrict);
        }

        // <phystech>
        if (!empty($ioI18n->physical_characteristics)) {
            $phystech = $this->el('phystech');
            $phystech->appendChild($this->el('p', $ioI18n->physical_characteristics));
            $archdesc->appendChild($phystech);
        }

        // <otherfindaid>
        if (!empty($ioI18n->finding_aids)) {
            $otherfindaid = $this->el('otherfindaid');
            $otherfindaid->appendChild($this->el('p', $ioI18n->finding_aids));
            $archdesc->appendChild($otherfindaid);
        }

        // <originalsloc>
        if (!empty($ioI18n->location_of_originals)) {
            $originalsloc = $this->el('originalsloc');
            $originalsloc->appendChild($this->el('p', $ioI18n->location_of_originals));
            $archdesc->appendChild($originalsloc);
        }

        // <altformavail>
        if (!empty($ioI18n->location_of_copies)) {
            $altformavail = $this->el('altformavail');
            $altformavail->appendChild($this->el('p', $ioI18n->location_of_copies));
            $archdesc->appendChild($altformavail);
        }

        // <relatedmaterial>
        if (!empty($ioI18n->related_units_of_description)) {
            $relatedmaterial = $this->el('relatedmaterial');
            $relatedmaterial->appendChild($this->el('p', $ioI18n->related_units_of_description));
            $archdesc->appendChild($relatedmaterial);
        }

        // <custodhist> (archival history)
        if (!empty($ioI18n->archival_history)) {
            $custodhist = $this->el('custodhist');
            $custodhist->appendChild($this->el('p', $ioI18n->archival_history));
            $archdesc->appendChild($custodhist);
        }

        // <acqinfo> (acquisition)
        if (!empty($ioI18n->acquisition)) {
            $acqinfo = $this->el('acqinfo');
            $acqinfo->appendChild($this->el('p', $ioI18n->acquisition));
            $archdesc->appendChild($acqinfo);
        }

        // <appraisal>
        if (!empty($ioI18n->appraisal)) {
            $appraisal = $this->el('appraisal');
            $appraisal->appendChild($this->el('p', $ioI18n->appraisal));
            $archdesc->appendChild($appraisal);
        }

        // <accruals>
        if (!empty($ioI18n->accruals)) {
            $accruals = $this->el('accruals');
            $accruals->appendChild($this->el('p', $ioI18n->accruals));
            $archdesc->appendChild($accruals);
        }

        // <controlaccess> for subject/place access points
        $this->addControlAccess($archdesc, $io->id, $culture);

        // <daoset> for digital objects
        if ($digitalObjects->isNotEmpty()) {
            $daoset = $this->el('daoset');
            $daoset->setAttribute('label', 'Digital Objects');

            foreach ($digitalObjects as $dobj) {
                $dao = $this->el('dao');
                $dao->setAttribute('daotype', 'derived');

                if (!empty($dobj->path)) {
                    $dao->setAttributeNS(self::NS_XLINK, 'xlink:href', $dobj->path);
                    $dao->setAttributeNS(self::NS_XLINK, 'xlink:actuate', 'onRequest');
                    $dao->setAttributeNS(self::NS_XLINK, 'xlink:show', 'new');
                }

                if (!empty($dobj->mime_type)) {
                    $dao->setAttribute('otherdaotype', $dobj->mime_type);
                }

                $daodesc = $this->el('daodesc');
                $daodesc->appendChild($this->el('p', $dobj->name ?? 'Digital Object'));
                $dao->appendChild($daodesc);

                $daoset->appendChild($dao);
            }

            $archdesc->appendChild($daoset);
        }

        // <dsc> with child components via nested set
        if ($includeChildren) {
            $this->addChildComponents($archdesc, $io, $culture);
        }

        return $archdesc;
    }

    /**
     * Build the <did> element.
     */
    protected function buildDid(object $io, object $ioI18n, ?string $repoName, $events, string $culture): \DOMElement
    {
        $did = $this->el('did');

        // <unitid>
        if (!empty($io->identifier)) {
            $did->appendChild($this->el('unitid', $io->identifier));
        }

        // <unittitle>
        if (!empty($ioI18n->title)) {
            $did->appendChild($this->el('unittitle', $ioI18n->title));
        }

        // <unitdate> from events
        foreach ($events as $event) {
            $unitdate = $this->el('unitdate');

            if (!empty($event->date_display)) {
                $unitdate->appendChild($this->dom->createTextNode($event->date_display));
            }

            $normalDate = $this->formatDateNormal($event->start_date, $event->end_date);
            if ($normalDate) {
                $unitdate->setAttribute('normal', $normalDate);
            }

            if (!empty($event->event_type_name)) {
                $unitdate->setAttribute('datechar', strtolower($event->event_type_name));
            }

            $did->appendChild($unitdate);
        }

        // <physdesc> (extent and medium)
        if (!empty($ioI18n->extent_and_medium)) {
            $physdescstructured = $this->el('physdescstructured');
            $physdescstructured->setAttribute('coverage', 'whole');
            $physdescstructured->setAttribute('physdescstructuredtype', 'materialtype');

            $quantity = $this->el('quantity', '1');
            $physdescstructured->appendChild($quantity);

            $unittype = $this->el('unittype', $ioI18n->extent_and_medium);
            $physdescstructured->appendChild($unittype);

            $did->appendChild($physdescstructured);
        }

        // <origination> from events with actors (creators)
        foreach ($events as $event) {
            if (!empty($event->actor_name)) {
                $origination = $this->el('origination');
                $origination->setAttribute('label', 'Creator');

                $persname = $this->el('persname');
                $part = $this->el('part', $event->actor_name);
                $persname->appendChild($part);
                $origination->appendChild($persname);

                $did->appendChild($origination);
            }
        }

        // <repository>
        if ($repoName) {
            $repository = $this->el('repository');
            $corpname = $this->el('corpname');
            $part = $this->el('part', $repoName);
            $corpname->appendChild($part);
            $repository->appendChild($corpname);
            $did->appendChild($repository);
        }

        // <abstract> from alternate title
        if (!empty($ioI18n->alternate_title)) {
            $did->appendChild($this->el('abstract', $ioI18n->alternate_title));
        }

        return $did;
    }

    /**
     * Add <controlaccess> for subject and place access points.
     */
    protected function addControlAccess(\DOMElement $parent, int $objectId, string $culture): void
    {
        // Subject access points (taxonomy_id = 35)
        $subjects = DB::table('object_term_relation')
            ->join('term_i18n', function ($join) use ($culture) {
                $join->on('object_term_relation.term_id', '=', 'term_i18n.id')
                     ->where('term_i18n.culture', '=', $culture);
            })
            ->where('object_term_relation.object_id', $objectId)
            ->select('term_i18n.name')
            ->get();

        // Place access points (taxonomy_id = 42)
        $places = DB::table('object_term_relation')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->join('term_i18n', function ($join) use ($culture) {
                $join->on('term.id', '=', 'term_i18n.id')
                     ->where('term_i18n.culture', '=', $culture);
            })
            ->where('object_term_relation.object_id', $objectId)
            ->where('term.taxonomy_id', 42)
            ->select('term_i18n.name')
            ->get();

        if ($subjects->isEmpty() && $places->isEmpty()) {
            return;
        }

        $controlaccess = $this->el('controlaccess');

        foreach ($subjects as $subject) {
            if (!empty($subject->name)) {
                $subjectEl = $this->el('subject');
                $subjectEl->appendChild($this->el('part', $subject->name));
                $controlaccess->appendChild($subjectEl);
            }
        }

        foreach ($places as $place) {
            if (!empty($place->name)) {
                $geogname = $this->el('geogname');
                $geogname->appendChild($this->el('part', $place->name));
                $controlaccess->appendChild($geogname);
            }
        }

        $parent->appendChild($controlaccess);
    }

    /**
     * Add child components using nested set traversal.
     */
    protected function addChildComponents(\DOMElement $parent, object $io, string $culture): void
    {
        if (!$io->lft || !$io->rgt) {
            return;
        }

        // Get direct children only (parent_id)
        $children = DB::table('information_object')
            ->leftJoin('information_object_i18n', function ($join) use ($culture) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                     ->where('information_object_i18n.culture', '=', $culture);
            })
            ->where('information_object.parent_id', $io->id)
            ->orderBy('information_object.lft')
            ->select(
                'information_object.id',
                'information_object.identifier',
                'information_object.level_of_description_id',
                'information_object.lft',
                'information_object.rgt',
                'information_object.parent_id',
                'information_object.repository_id',
                'information_object.source_culture',
                'information_object_i18n.title',
                'information_object_i18n.scope_and_content',
                'information_object_i18n.extent_and_medium',
                'information_object_i18n.access_conditions',
                'information_object_i18n.arrangement',
                'information_object_i18n.archival_history',
                'information_object_i18n.acquisition',
                'information_object_i18n.appraisal',
                'information_object_i18n.accruals',
                'information_object_i18n.reproduction_conditions',
                'information_object_i18n.physical_characteristics',
                'information_object_i18n.finding_aids',
                'information_object_i18n.location_of_originals',
                'information_object_i18n.location_of_copies',
                'information_object_i18n.related_units_of_description',
                'information_object_i18n.alternate_title'
            )
            ->get();

        if ($children->isEmpty()) {
            return;
        }

        $dsc = $this->el('dsc');

        foreach ($children as $child) {
            $c = $this->buildComponent($child, $culture, 1);
            if ($c) {
                $dsc->appendChild($c);
            }
        }

        $parent->appendChild($dsc);
    }

    /**
     * Build a <c> component element recursively.
     */
    protected function buildComponent(object $child, string $culture, int $depth): ?\DOMElement
    {
        // Safety: limit depth to 20 levels
        if ($depth > 20) {
            return null;
        }

        // Check publication status
        $pubStatus = DB::table('status')
            ->where('object_id', $child->id)
            ->where('type_id', 158)
            ->value('status_id');

        // status_id 159 = draft in AtoM; skip drafts
        if ($pubStatus && (int) $pubStatus === 159) {
            return null;
        }

        $levelName = null;
        if ($child->level_of_description_id) {
            $levelName = DB::table('term_i18n')
                ->where('id', $child->level_of_description_id)
                ->where('culture', $culture)
                ->value('name');
        }

        $level = $this->mapLevel($levelName);

        $c = $this->el('c');
        $c->setAttribute('level', $level);

        // <did>
        $did = $this->el('did');

        if (!empty($child->identifier)) {
            $did->appendChild($this->el('unitid', $child->identifier));
        }
        if (!empty($child->title)) {
            $did->appendChild($this->el('unittitle', $child->title));
        }

        // Events for this child
        $events = DB::table('event')
            ->leftJoin('event_i18n', function ($join) use ($culture) {
                $join->on('event.id', '=', 'event_i18n.id')
                     ->where('event_i18n.culture', '=', $culture);
            })
            ->where('event.object_id', $child->id)
            ->select('event.start_date', 'event.end_date', 'event_i18n.date as date_display')
            ->get();

        foreach ($events as $event) {
            $unitdate = $this->el('unitdate');
            if (!empty($event->date_display)) {
                $unitdate->appendChild($this->dom->createTextNode($event->date_display));
            }
            $normalDate = $this->formatDateNormal($event->start_date, $event->end_date);
            if ($normalDate) {
                $unitdate->setAttribute('normal', $normalDate);
            }
            $did->appendChild($unitdate);
        }

        if (!empty($child->extent_and_medium)) {
            $physdescstructured = $this->el('physdescstructured');
            $physdescstructured->setAttribute('coverage', 'whole');
            $physdescstructured->setAttribute('physdescstructuredtype', 'materialtype');
            $physdescstructured->appendChild($this->el('quantity', '1'));
            $physdescstructured->appendChild($this->el('unittype', $child->extent_and_medium));
            $did->appendChild($physdescstructured);
        }

        $c->appendChild($did);

        // Optional elements
        if (!empty($child->scope_and_content)) {
            $scopecontent = $this->el('scopecontent');
            $scopecontent->appendChild($this->el('p', $child->scope_and_content));
            $c->appendChild($scopecontent);
        }

        if (!empty($child->arrangement)) {
            $arrangement = $this->el('arrangement');
            $arrangement->appendChild($this->el('p', $child->arrangement));
            $c->appendChild($arrangement);
        }

        if (!empty($child->access_conditions)) {
            $accessrestrict = $this->el('accessrestrict');
            $accessrestrict->appendChild($this->el('p', $child->access_conditions));
            $c->appendChild($accessrestrict);
        }

        if (!empty($child->reproduction_conditions)) {
            $userestrict = $this->el('userestrict');
            $userestrict->appendChild($this->el('p', $child->reproduction_conditions));
            $c->appendChild($userestrict);
        }

        // Digital objects for child
        $digitalObjects = DB::table('digital_object')
            ->where('object_id', $child->id)
            ->select('id', 'name', 'path', 'mime_type')
            ->get();

        if ($digitalObjects->isNotEmpty()) {
            $daoset = $this->el('daoset');
            $daoset->setAttribute('label', 'Digital Objects');

            foreach ($digitalObjects as $dobj) {
                $dao = $this->el('dao');
                $dao->setAttribute('daotype', 'derived');
                if (!empty($dobj->path)) {
                    $dao->setAttributeNS(self::NS_XLINK, 'xlink:href', $dobj->path);
                    $dao->setAttributeNS(self::NS_XLINK, 'xlink:actuate', 'onRequest');
                    $dao->setAttributeNS(self::NS_XLINK, 'xlink:show', 'new');
                }
                $daodesc = $this->el('daodesc');
                $daodesc->appendChild($this->el('p', $dobj->name ?? 'Digital Object'));
                $dao->appendChild($daodesc);
                $daoset->appendChild($dao);
            }
            $c->appendChild($daoset);
        }

        // Recursively add grandchildren
        $grandchildren = DB::table('information_object')
            ->leftJoin('information_object_i18n', function ($join) use ($culture) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                     ->where('information_object_i18n.culture', '=', $culture);
            })
            ->where('information_object.parent_id', $child->id)
            ->orderBy('information_object.lft')
            ->select(
                'information_object.id',
                'information_object.identifier',
                'information_object.level_of_description_id',
                'information_object.lft',
                'information_object.rgt',
                'information_object.parent_id',
                'information_object.repository_id',
                'information_object.source_culture',
                'information_object_i18n.title',
                'information_object_i18n.scope_and_content',
                'information_object_i18n.extent_and_medium',
                'information_object_i18n.access_conditions',
                'information_object_i18n.arrangement',
                'information_object_i18n.archival_history',
                'information_object_i18n.acquisition',
                'information_object_i18n.appraisal',
                'information_object_i18n.accruals',
                'information_object_i18n.reproduction_conditions',
                'information_object_i18n.physical_characteristics',
                'information_object_i18n.finding_aids',
                'information_object_i18n.location_of_originals',
                'information_object_i18n.location_of_copies',
                'information_object_i18n.related_units_of_description',
                'information_object_i18n.alternate_title'
            )
            ->get();

        foreach ($grandchildren as $grandchild) {
            $gc = $this->buildComponent($grandchild, $culture, $depth + 1);
            if ($gc) {
                $c->appendChild($gc);
            }
        }

        return $c;
    }

    /**
     * Map ISAD(G) level name to EAD level attribute value.
     */
    protected function mapLevel(?string $level): string
    {
        if (!$level) {
            return 'otherlevel';
        }

        return $this->levelMap[$level] ?? 'otherlevel';
    }

    /**
     * Format date range for EAD normal attribute.
     */
    protected function formatDateNormal($startDate, $endDate): ?string
    {
        $start = $startDate ? (string) $startDate : null;
        $end = $endDate ? (string) $endDate : null;

        if ($start && $end && $start !== $end) {
            return $start . '/' . $end;
        }

        return $start ?? $end;
    }

    /**
     * Create a namespaced element with optional text content.
     */
    protected function el(string $name, ?string $value = null): \DOMElement
    {
        $element = $this->dom->createElementNS(self::NS_EAD, $name);

        if ($value !== null && $value !== '') {
            $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
            $element->appendChild($this->dom->createTextNode($text));
        }

        return $element;
    }

    /**
     * Return an empty XML document with an error comment.
     */
    protected function emptyDocument(string $message): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS(self::NS_EAD, 'ead');
        $dom->appendChild($root);

        $comment = $dom->createComment(' ' . $message . ' ');
        $root->appendChild($comment);

        return $dom->saveXML();
    }
}
