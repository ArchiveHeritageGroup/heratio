<?php

/**
 * EagSerializer - EAG 3.0 (Encoded Archival Guide) XML Serializer for Heratio
 *
 * Serializes repository/institution description data to EAG 3.0 XML format,
 * aligned with ISDIAH and the SAA EAG 3.0 draft schema.
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

class EagSerializer
{
    /**
     * EAG 3.0 namespace URI (draft).
     */
    public const NS_EAG = 'https://archivists.org/ns/eag/v3';

    /**
     * XSI namespace URI.
     */
    public const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    /**
     * @var \DOMDocument
     */
    protected \DOMDocument $dom;

    /**
     * Return the format identifier.
     */
    public function getFormat(): string
    {
        return 'eag';
    }

    /**
     * Return the human-readable format name.
     */
    public function getFormatName(): string
    {
        return 'EAG 3.0';
    }

    /**
     * Serialize a repository record to EAG 3.0 XML.
     *
     * @param int    $repositoryId The repository ID.
     * @param string $culture      The i18n culture code.
     *
     * @return string The EAG 3.0 XML document.
     */
    public function serializeRepository(int $repositoryId, string $culture = 'en'): string
    {
        $this->dom = new \DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;

        // Fetch repository base record
        $repo = DB::table('repository')
            ->where('id', $repositoryId)
            ->first();

        if (!$repo) {
            return $this->emptyDocument('Repository not found');
        }

        // Fetch actor data (class table inheritance: repository extends actor)
        $actor = DB::table('actor')
            ->where('id', $repositoryId)
            ->first();

        // Fetch actor i18n data
        $actorI18n = DB::table('actor_i18n')
            ->where('id', $repositoryId)
            ->where('culture', $culture)
            ->first();

        if (!$actorI18n) {
            $sourceCulture = $actor->source_culture ?? $repo->source_culture ?? 'en';
            $actorI18n = DB::table('actor_i18n')
                ->where('id', $repositoryId)
                ->where('culture', $sourceCulture)
                ->first();
        }

        if (!$actorI18n) {
            return $this->emptyDocument('Repository actor i18n data not found');
        }

        // Fetch repository i18n data
        $repoI18n = DB::table('repository_i18n')
            ->where('id', $repositoryId)
            ->where('culture', $culture)
            ->first();

        if (!$repoI18n) {
            $repoI18n = DB::table('repository_i18n')
                ->where('id', $repositoryId)
                ->where('culture', $repo->source_culture)
                ->first();
        }

        // Fetch slug
        $slug = DB::table('slug')
            ->where('object_id', $repositoryId)
            ->value('slug');

        // Fetch contact information
        $contacts = DB::table('contact_information')
            ->leftJoin('contact_information_i18n', function ($join) use ($culture) {
                $join->on('contact_information.id', '=', 'contact_information_i18n.id')
                     ->where('contact_information_i18n.culture', '=', $culture);
            })
            ->where('contact_information.actor_id', $repositoryId)
            ->select(
                'contact_information.id',
                'contact_information.contact_person',
                'contact_information.street_address',
                'contact_information.website',
                'contact_information.email',
                'contact_information.telephone',
                'contact_information.fax',
                'contact_information.postal_code',
                'contact_information.country_code',
                'contact_information.longitude',
                'contact_information.latitude',
                'contact_information.primary_contact',
                'contact_information.contact_note',
                'contact_information_i18n.city',
                'contact_information_i18n.region',
                'contact_information_i18n.note'
            )
            ->get();

        // Fetch description status/detail names
        $descStatusName = null;
        if ($repo->desc_status_id) {
            $descStatusName = DB::table('term_i18n')
                ->where('id', $repo->desc_status_id)
                ->where('culture', $culture)
                ->value('name');
        }

        $descDetailName = null;
        if ($repo->desc_detail_id) {
            $descDetailName = DB::table('term_i18n')
                ->where('id', $repo->desc_detail_id)
                ->where('culture', $culture)
                ->value('name');
        }

        // Fetch other names for the repository (as actor)
        $otherNames = DB::table('other_name')
            ->join('other_name_i18n', function ($join) use ($culture) {
                $join->on('other_name.id', '=', 'other_name_i18n.id')
                     ->where('other_name_i18n.culture', '=', $culture);
            })
            ->where('other_name.object_id', $repositoryId)
            ->select('other_name_i18n.name', 'other_name.type_id')
            ->get();

        // Build root <eag> element
        $eag = $this->dom->createElementNS(self::NS_EAG, 'eag');
        $this->dom->appendChild($eag);

        $eag->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', self::NS_XSI);
        $eag->setAttributeNS(
            self::NS_XSI,
            'xsi:schemaLocation',
            self::NS_EAG . ' https://archivists.org/ns/eag/v3/eag3.xsd'
        );

        // <control>
        $control = $this->buildControl($repositoryId, $repo, $actorI18n, $slug, $culture);
        $eag->appendChild($control);

        // <archguide>
        $archguide = $this->buildArchguide(
            $repo,
            $actor,
            $actorI18n,
            $repoI18n,
            $contacts,
            $otherNames,
            $descStatusName,
            $descDetailName,
            $culture
        );
        $eag->appendChild($archguide);

        return $this->dom->saveXML();
    }

    /**
     * Build the <control> element.
     */
    protected function buildControl(int $repositoryId, object $repo, object $actorI18n, ?string $slug, string $culture): \DOMElement
    {
        $control = $this->el('control');

        // <recordId>
        $recordId = $slug ?? ($repo->identifier ?? ('repo-' . $repositoryId));
        $control->appendChild($this->el('recordId', $recordId));

        // <otherRecordId> for the repository identifier
        if (!empty($repo->identifier)) {
            $otherRecordId = $this->el('otherRecordId', $repo->identifier);
            $otherRecordId->setAttribute('localType', 'repositoryIdentifier');
            $control->appendChild($otherRecordId);
        }

        // <maintenanceStatus>
        $maintenanceStatus = $this->el('maintenanceStatus');
        $maintenanceStatus->setAttribute('value', 'derived');
        $control->appendChild($maintenanceStatus);

        // <maintenanceAgency>
        $maintenanceAgency = $this->el('maintenanceAgency');
        $maintenanceAgency->appendChild($this->el('agencyName', $actorI18n->authorized_form_of_name ?? 'Heratio'));
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
        $maintenanceEvent->appendChild($this->el('agent', 'Heratio EAG 3.0 Serializer'));

        $maintenanceHistory->appendChild($maintenanceEvent);
        $control->appendChild($maintenanceHistory);

        // <sources>
        if (!empty($actorI18n->sources)) {
            $sources = $this->el('sources');
            $source = $this->el('source');
            $source->appendChild($this->el('sourceEntry', $actorI18n->sources));
            $sources->appendChild($source);
            $control->appendChild($sources);
        }

        return $control;
    }

    /**
     * Build the <archguide> element.
     */
    protected function buildArchguide(
        object $repo,
        ?object $actor,
        object $actorI18n,
        ?object $repoI18n,
        $contacts,
        $otherNames,
        ?string $descStatusName,
        ?string $descDetailName,
        string $culture
    ): \DOMElement {
        $archguide = $this->el('archguide');

        // <identity>
        $identity = $this->buildIdentity($repo, $actorI18n, $otherNames);
        $archguide->appendChild($identity);

        // <desc>
        $desc = $this->buildDesc($repo, $actor, $actorI18n, $repoI18n, $contacts, $descStatusName, $descDetailName, $culture);
        if ($desc->hasChildNodes()) {
            $archguide->appendChild($desc);
        }

        // <relations>
        $relations = $this->buildRelations($repo->id, $culture);
        if ($relations->hasChildNodes()) {
            $archguide->appendChild($relations);
        }

        return $archguide;
    }

    /**
     * Build the <identity> element.
     */
    protected function buildIdentity(object $repo, object $actorI18n, $otherNames): \DOMElement
    {
        $identity = $this->el('identity');

        // <repositoryType> — corporate body by definition
        $repositoryType = $this->el('repositoryType', 'Archive');
        $identity->appendChild($repositoryType);

        // <nameEntry> authorized
        if (!empty($actorI18n->authorized_form_of_name)) {
            $nameEntry = $this->el('nameEntry');
            $nameEntry->setAttribute('status', 'authorized');

            $part = $this->el('part', $actorI18n->authorized_form_of_name);
            $nameEntry->appendChild($part);
            $identity->appendChild($nameEntry);
        }

        // <nameEntry> for other/parallel names
        foreach ($otherNames as $otherName) {
            if (empty($otherName->name)) {
                continue;
            }

            $nameEntry = $this->el('nameEntry');
            $nameEntry->setAttribute('status', 'alternative');

            $part = $this->el('part', $otherName->name);
            $nameEntry->appendChild($part);
            $identity->appendChild($nameEntry);
        }

        // <repositoryId> from identifier
        if (!empty($repo->identifier)) {
            $repositoryId = $this->el('repositoryId', $repo->identifier);
            $identity->appendChild($repositoryId);
        }

        return $identity;
    }

    /**
     * Build the <desc> element.
     */
    protected function buildDesc(
        object $repo,
        ?object $actor,
        object $actorI18n,
        ?object $repoI18n,
        $contacts,
        ?string $descStatusName,
        ?string $descDetailName,
        string $culture
    ): \DOMElement {
        $desc = $this->el('desc');

        // <repositoryHistory> from actor_i18n.history
        if (!empty($actorI18n->history)) {
            $repositoryHistory = $this->el('repositoryHistory');
            $repositoryHistory->appendChild($this->el('p', $actorI18n->history));
            $desc->appendChild($repositoryHistory);
        }

        // <geoculturalContext> from repository_i18n
        if ($repoI18n && !empty($repoI18n->geocultural_context)) {
            $geoculturalContext = $this->el('geoculturalContext');
            $geoculturalContext->appendChild($this->el('p', $repoI18n->geocultural_context));
            $desc->appendChild($geoculturalContext);
        }

        // <mandates> from actor_i18n
        if (!empty($actorI18n->mandates)) {
            $mandates = $this->el('mandates');
            $mandates->appendChild($this->el('p', $actorI18n->mandates));
            $desc->appendChild($mandates);
        }

        // <buildingDescription> from repository_i18n.buildings
        if ($repoI18n && !empty($repoI18n->buildings)) {
            $buildingDescription = $this->el('buildingDescription');
            $buildingDescription->appendChild($this->el('p', $repoI18n->buildings));
            $desc->appendChild($buildingDescription);
        }

        // <holdingsDescription> from repository_i18n.holdings
        if ($repoI18n && !empty($repoI18n->holdings)) {
            $holdingsDescription = $this->el('holdingsDescription');
            $holdingsDescription->appendChild($this->el('p', $repoI18n->holdings));
            $desc->appendChild($holdingsDescription);
        }

        // <findingAids> from repository_i18n
        if ($repoI18n && !empty($repoI18n->finding_aids)) {
            $findingAids = $this->el('findingAids');
            $findingAids->appendChild($this->el('p', $repoI18n->finding_aids));
            $desc->appendChild($findingAids);
        }

        // <collectingPolicies>
        if ($repoI18n && !empty($repoI18n->collecting_policies)) {
            $collectingPolicies = $this->el('collectingPolicies');
            $collectingPolicies->appendChild($this->el('p', $repoI18n->collecting_policies));
            $desc->appendChild($collectingPolicies);
        }

        // <openingTimes>
        if ($repoI18n && !empty($repoI18n->opening_times)) {
            $openingTimes = $this->el('openingTimes');
            $openingTimes->appendChild($this->el('p', $repoI18n->opening_times));
            $desc->appendChild($openingTimes);
        }

        // <accessConditions>
        if ($repoI18n && !empty($repoI18n->access_conditions)) {
            $accessConditions = $this->el('accessConditions');
            $accessConditions->appendChild($this->el('p', $repoI18n->access_conditions));
            $desc->appendChild($accessConditions);
        }

        // <disabledAccess>
        if ($repoI18n && !empty($repoI18n->disabled_access)) {
            $disabledAccess = $this->el('disabledAccess');
            $disabledAccess->appendChild($this->el('p', $repoI18n->disabled_access));
            $desc->appendChild($disabledAccess);
        }

        // <researchServices>
        if ($repoI18n && !empty($repoI18n->research_services)) {
            $researchServices = $this->el('researchServices');
            $researchServices->appendChild($this->el('p', $repoI18n->research_services));
            $desc->appendChild($researchServices);
        }

        // <reproductionServices>
        if ($repoI18n && !empty($repoI18n->reproduction_services)) {
            $reproductionServices = $this->el('reproductionServices');
            $reproductionServices->appendChild($this->el('p', $repoI18n->reproduction_services));
            $desc->appendChild($reproductionServices);
        }

        // <publicFacilities>
        if ($repoI18n && !empty($repoI18n->public_facilities)) {
            $publicFacilities = $this->el('publicFacilities');
            $publicFacilities->appendChild($this->el('p', $repoI18n->public_facilities));
            $desc->appendChild($publicFacilities);
        }

        // <places> from actor_i18n.places
        if (!empty($actorI18n->places)) {
            $places = $this->el('places');
            $place = $this->el('place');
            $place->appendChild($this->el('placeEntry', $actorI18n->places));
            $places->appendChild($place);
            $desc->appendChild($places);
        }

        // <legalStatus> from actor_i18n.legal_status
        if (!empty($actorI18n->legal_status)) {
            $legalStatus = $this->el('legalStatus');
            $legalStatus->appendChild($this->el('term', $actorI18n->legal_status));
            $desc->appendChild($legalStatus);
        }

        // <functions> from actor_i18n.functions
        if (!empty($actorI18n->functions)) {
            $functions = $this->el('functions');
            $functionEl = $this->el('function');
            $functionEl->appendChild($this->el('term', $actorI18n->functions));
            $functions->appendChild($functionEl);
            $desc->appendChild($functions);
        }

        // <internalStructures> from actor_i18n
        if (!empty($actorI18n->internal_structures)) {
            $internalStructures = $this->el('internalStructures');
            $internalStructures->appendChild($this->el('p', $actorI18n->internal_structures));
            $desc->appendChild($internalStructures);
        }

        // <generalContext> from actor_i18n
        if (!empty($actorI18n->general_context)) {
            $generalContext = $this->el('generalContext');
            $generalContext->appendChild($this->el('p', $actorI18n->general_context));
            $desc->appendChild($generalContext);
        }

        // Contact information as <location> elements
        foreach ($contacts as $contact) {
            $location = $this->el('location');
            $location->setAttribute('localType', $contact->primary_contact ? 'primary' : 'secondary');

            // Address
            $addressParts = [];
            if (!empty($contact->street_address)) {
                $addressParts[] = $contact->street_address;
            }
            if (!empty($contact->city)) {
                $addressParts[] = $contact->city;
            }
            if (!empty($contact->region)) {
                $addressParts[] = $contact->region;
            }
            if (!empty($contact->postal_code)) {
                $addressParts[] = $contact->postal_code;
            }
            if (!empty($contact->country_code)) {
                $addressParts[] = $contact->country_code;
            }

            if (!empty($addressParts)) {
                $address = $this->el('address');

                if (!empty($contact->street_address)) {
                    $address->appendChild($this->el('addressLine', $contact->street_address));
                }
                if (!empty($contact->city)) {
                    $cityEl = $this->el('addressLine', $contact->city);
                    $cityEl->setAttribute('localType', 'city');
                    $address->appendChild($cityEl);
                }
                if (!empty($contact->region)) {
                    $regionEl = $this->el('addressLine', $contact->region);
                    $regionEl->setAttribute('localType', 'region');
                    $address->appendChild($regionEl);
                }
                if (!empty($contact->postal_code)) {
                    $postalEl = $this->el('addressLine', $contact->postal_code);
                    $postalEl->setAttribute('localType', 'postalCode');
                    $address->appendChild($postalEl);
                }
                if (!empty($contact->country_code)) {
                    $countryEl = $this->el('addressLine', $contact->country_code);
                    $countryEl->setAttribute('localType', 'country');
                    $address->appendChild($countryEl);
                }

                $location->appendChild($address);
            }

            // Telephone
            if (!empty($contact->telephone)) {
                $telephone = $this->el('telephone', $contact->telephone);
                $location->appendChild($telephone);
            }

            // Fax
            if (!empty($contact->fax)) {
                $faxEl = $this->el('fax', $contact->fax);
                $location->appendChild($faxEl);
            }

            // Email
            if (!empty($contact->email)) {
                $emailEl = $this->el('email', $contact->email);
                $location->appendChild($emailEl);
            }

            // Website
            if (!empty($contact->website)) {
                $webpage = $this->el('webpage', $contact->website);
                $location->appendChild($webpage);
            }

            // Contact person
            if (!empty($contact->contact_person)) {
                $contactPersonEl = $this->el('contactPerson', $contact->contact_person);
                $location->appendChild($contactPersonEl);
            }

            // Geo coordinates
            if ($contact->latitude && $contact->longitude) {
                $geographicCoordinates = $this->el('geographicCoordinates');
                $geographicCoordinates->appendChild($this->el('latitude', (string) $contact->latitude));
                $geographicCoordinates->appendChild($this->el('longitude', (string) $contact->longitude));
                $location->appendChild($geographicCoordinates);
            }

            // Note
            if (!empty($contact->note)) {
                $noteEl = $this->el('descriptiveNote');
                $noteEl->appendChild($this->el('p', $contact->note));
                $location->appendChild($noteEl);
            }

            if ($location->hasChildNodes()) {
                $desc->appendChild($location);
            }
        }

        // <descriptionControl> metadata
        $descControl = $this->el('descriptionControl');
        $hasDescControl = false;

        if ($descStatusName) {
            $descControl->appendChild($this->el('descriptionStatus', $descStatusName));
            $hasDescControl = true;
        }

        if ($descDetailName) {
            $descControl->appendChild($this->el('descriptionDetail', $descDetailName));
            $hasDescControl = true;
        }

        if (!empty($repo->desc_identifier)) {
            $descControl->appendChild($this->el('descriptionIdentifier', $repo->desc_identifier));
            $hasDescControl = true;
        }

        if ($repoI18n && !empty($repoI18n->desc_institution_identifier)) {
            $descControl->appendChild($this->el('institutionIdentifier', $repoI18n->desc_institution_identifier));
            $hasDescControl = true;
        }

        if ($repoI18n && !empty($repoI18n->desc_rules)) {
            $descControl->appendChild($this->el('rules', $repoI18n->desc_rules));
            $hasDescControl = true;
        }

        if ($repoI18n && !empty($repoI18n->desc_sources)) {
            $descControl->appendChild($this->el('sources', $repoI18n->desc_sources));
            $hasDescControl = true;
        }

        if ($repoI18n && !empty($repoI18n->desc_revision_history)) {
            $descControl->appendChild($this->el('revisionHistory', $repoI18n->desc_revision_history));
            $hasDescControl = true;
        }

        if ($hasDescControl) {
            $desc->appendChild($descControl);
        }

        return $desc;
    }

    /**
     * Build the <relations> element.
     */
    protected function buildRelations(int $repositoryId, string $culture): \DOMElement
    {
        $relations = $this->el('relations');

        // Fetch relations where this repository is subject or object
        $rels = DB::table('relation')
            ->where('subject_id', $repositoryId)
            ->orWhere('object_id', $repositoryId)
            ->select('id', 'subject_id', 'object_id', 'type_id', 'start_date', 'end_date')
            ->get();

        foreach ($rels as $rel) {
            $relatedId = ($rel->subject_id === $repositoryId) ? $rel->object_id : $rel->subject_id;

            // Resolve related entity name
            $relatedName = DB::table('actor_i18n')
                ->where('id', $relatedId)
                ->where('culture', $culture)
                ->value('authorized_form_of_name');

            if (!$relatedName) {
                continue;
            }

            $relTypeName = null;
            if ($rel->type_id) {
                $relTypeName = DB::table('term_i18n')
                    ->where('id', $rel->type_id)
                    ->where('culture', $culture)
                    ->value('name');
            }

            $relation = $this->el('relation');
            $relation->setAttribute('relationType', 'associative');

            $targetEntity = $this->el('targetEntity');

            $relSlug = DB::table('slug')->where('object_id', $relatedId)->value('slug');
            if ($relSlug) {
                $targetEntity->setAttribute('valueURI', $relSlug);
            }

            $targetEntity->appendChild($this->el('part', $relatedName));
            $relation->appendChild($targetEntity);

            if ($rel->start_date || $rel->end_date) {
                $dateRange = $this->el('dateRange');
                if ($rel->start_date) {
                    $fromDate = $this->el('fromDate', (string) $rel->start_date);
                    $fromDate->setAttribute('standardDate', (string) $rel->start_date);
                    $dateRange->appendChild($fromDate);
                }
                if ($rel->end_date) {
                    $toDate = $this->el('toDate', (string) $rel->end_date);
                    $toDate->setAttribute('standardDate', (string) $rel->end_date);
                    $dateRange->appendChild($toDate);
                }
                $relation->appendChild($dateRange);
            }

            if ($relTypeName) {
                $descriptiveNote = $this->el('descriptiveNote');
                $descriptiveNote->appendChild($this->el('p', $relTypeName));
                $relation->appendChild($descriptiveNote);
            }

            $relations->appendChild($relation);
        }

        return $relations;
    }

    /**
     * Create a namespaced element with optional text content.
     */
    protected function el(string $name, ?string $value = null): \DOMElement
    {
        $element = $this->dom->createElementNS(self::NS_EAG, $name);

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

        $root = $dom->createElementNS(self::NS_EAG, 'eag');
        $dom->appendChild($root);

        $comment = $dom->createComment(' ' . $message . ' ');
        $root->appendChild($comment);

        return $dom->saveXML();
    }
}
