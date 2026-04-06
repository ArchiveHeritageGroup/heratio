<?php

/**
 * EacCpf2Serializer - EAC-CPF 2.0 XML Serializer for Heratio
 *
 * Serializes actor (authority record) data to EAC-CPF 2.0 XML format,
 * aligned with the SAA EAC-CPF 2.0 schema.
 *
 * @see https://eac.staatsbibliothek-berlin.de/schema/v2/eac.xsd
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

class EacCpf2Serializer
{
    /**
     * EAC-CPF 2.0 namespace URI.
     */
    public const NS_EAC = 'https://archivists.org/ns/eac/v2';

    /**
     * XSI namespace URI.
     */
    public const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    /**
     * XLink namespace URI.
     */
    public const NS_XLINK = 'http://www.w3.org/1999/xlink';

    /**
     * Schema location.
     */
    public const SCHEMA_LOCATION = 'https://archivists.org/ns/eac/v2 https://eac.staatsbibliothek-berlin.de/schema/v2/eac.xsd';

    /**
     * @var \DOMDocument
     */
    protected \DOMDocument $dom;

    /**
     * Entity type mapping from AtoM term IDs to EAC-CPF 2.0 values.
     */
    protected array $entityTypeMap = [
        'Person' => 'person',
        'Corporate body' => 'corporateBody',
        'Family' => 'family',
    ];

    /**
     * Return the format identifier.
     */
    public function getFormat(): string
    {
        return 'eac2';
    }

    /**
     * Return the human-readable format name.
     */
    public function getFormatName(): string
    {
        return 'EAC-CPF 2.0';
    }

    /**
     * Serialize an actor record to EAC-CPF 2.0 XML.
     *
     * @param int    $actorId  The actor ID.
     * @param string $culture  The i18n culture code.
     *
     * @return string The EAC-CPF 2.0 XML document.
     */
    public function serializeActor(int $actorId, string $culture = 'en'): string
    {
        $this->dom = new \DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;

        // Fetch actor base record
        $actor = DB::table('actor')
            ->where('id', $actorId)
            ->first();

        if (!$actor) {
            return $this->emptyDocument('Actor not found');
        }

        // Fetch actor i18n data
        $actorI18n = DB::table('actor_i18n')
            ->where('id', $actorId)
            ->where('culture', $culture)
            ->first();

        if (!$actorI18n) {
            // Fallback to source culture
            $actorI18n = DB::table('actor_i18n')
                ->where('id', $actorId)
                ->where('culture', $actor->source_culture)
                ->first();
        }

        if (!$actorI18n) {
            return $this->emptyDocument('Actor i18n data not found');
        }

        // Fetch slug
        $slug = DB::table('slug')
            ->where('object_id', $actorId)
            ->value('slug');

        // Fetch entity type name
        $entityTypeName = null;
        if ($actor->entity_type_id) {
            $entityTypeName = DB::table('term_i18n')
                ->where('id', $actor->entity_type_id)
                ->where('culture', $culture)
                ->value('name');
        }

        // Fetch other names
        $otherNames = DB::table('other_name')
            ->join('other_name_i18n', function ($join) use ($culture) {
                $join->on('other_name.id', '=', 'other_name_i18n.id')
                     ->where('other_name_i18n.culture', '=', $culture);
            })
            ->where('other_name.object_id', $actorId)
            ->select(
                'other_name.id',
                'other_name.type_id',
                'other_name_i18n.name',
                'other_name_i18n.note',
                'other_name_i18n.dates'
            )
            ->get();

        // Fetch relations (subject or object)
        $relationsAsSubject = DB::table('relation')
            ->where('subject_id', $actorId)
            ->select('id', 'subject_id', 'object_id', 'type_id', 'start_date', 'end_date')
            ->get();

        $relationsAsObject = DB::table('relation')
            ->where('object_id', $actorId)
            ->select('id', 'subject_id', 'object_id', 'type_id', 'start_date', 'end_date')
            ->get();

        // Build root element
        $eac = $this->dom->createElementNS(self::NS_EAC, 'eac');
        $this->dom->appendChild($eac);

        $eac->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', self::NS_XSI);
        $eac->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xlink', self::NS_XLINK);
        $eac->setAttributeNS(self::NS_XSI, 'xsi:schemaLocation', self::SCHEMA_LOCATION);

        // Build <control>
        $control = $this->buildControl($actorId, $actor, $actorI18n, $slug, $culture);
        $eac->appendChild($control);

        // Build <cpfDescription>
        $cpfDesc = $this->buildCpfDescription(
            $actor,
            $actorI18n,
            $entityTypeName,
            $otherNames,
            $relationsAsSubject,
            $relationsAsObject,
            $culture
        );
        $eac->appendChild($cpfDesc);

        return $this->dom->saveXML();
    }

    /**
     * Build the <control> element.
     */
    protected function buildControl(int $actorId, object $actor, object $actorI18n, ?string $slug, string $culture): \DOMElement
    {
        $control = $this->el('control');

        // <recordId>
        $recordId = $slug ?? ('actor-' . $actorId);
        $control->appendChild($this->el('recordId', $recordId));

        // <maintenanceStatus>
        $maintenanceStatus = $this->el('maintenanceStatus');
        $maintenanceStatus->setAttribute('value', 'derived');
        $control->appendChild($maintenanceStatus);

        // <maintenanceAgency>
        $maintenanceAgency = $this->el('maintenanceAgency');
        $agencyName = $this->el('agencyName', 'Heratio Metadata Export');
        $maintenanceAgency->appendChild($agencyName);
        $control->appendChild($maintenanceAgency);

        // <languageDeclaration>
        $languageDeclaration = $this->el('languageDeclaration');
        $language = $this->el('language');
        $language->setAttribute('languageCode', $culture === 'en' ? 'eng' : $culture);
        $languageDeclaration->appendChild($language);
        $script = $this->el('script');
        $script->setAttribute('scriptCode', 'Latn');
        $languageDeclaration->appendChild($script);
        $control->appendChild($languageDeclaration);

        // <maintenanceHistory>
        $maintenanceHistory = $this->el('maintenanceHistory');
        $maintenanceEvent = $this->el('maintenanceEvent');

        $eventType = $this->el('eventType');
        $eventType->setAttribute('value', 'derived');
        $maintenanceEvent->appendChild($eventType);

        $eventDateTime = $this->el('eventDateTime', date('c'));
        $maintenanceEvent->appendChild($eventDateTime);

        $agentType = $this->el('agentType');
        $agentType->setAttribute('value', 'machine');
        $maintenanceEvent->appendChild($agentType);

        $agent = $this->el('agent', 'Heratio EAC-CPF 2.0 Serializer');
        $maintenanceEvent->appendChild($agent);

        $maintenanceHistory->appendChild($maintenanceEvent);
        $control->appendChild($maintenanceHistory);

        // <sources> from actor_i18n
        if (!empty($actorI18n->sources)) {
            $sources = $this->el('sources');
            $source = $this->el('source');
            $sourceEntry = $this->el('sourceEntry', $actorI18n->sources);
            $source->appendChild($sourceEntry);
            $sources->appendChild($source);
            $control->appendChild($sources);
        }

        return $control;
    }

    /**
     * Build the <cpfDescription> element.
     */
    protected function buildCpfDescription(
        object $actor,
        object $actorI18n,
        ?string $entityTypeName,
        $otherNames,
        $relationsAsSubject,
        $relationsAsObject,
        string $culture
    ): \DOMElement {
        $cpfDescription = $this->el('cpfDescription');

        // <identity>
        $identity = $this->buildIdentity($actor, $actorI18n, $entityTypeName, $otherNames, $culture);
        $cpfDescription->appendChild($identity);

        // <description>
        $description = $this->buildDescription($actor, $actorI18n, $culture);
        if ($description->hasChildNodes()) {
            $cpfDescription->appendChild($description);
        }

        // <relations>
        $relations = $this->buildRelations($actor, $relationsAsSubject, $relationsAsObject, $culture);
        if ($relations->hasChildNodes()) {
            $cpfDescription->appendChild($relations);
        }

        return $cpfDescription;
    }

    /**
     * Build the <identity> element.
     */
    protected function buildIdentity(object $actor, object $actorI18n, ?string $entityTypeName, $otherNames, string $culture): \DOMElement
    {
        $identity = $this->el('identity');

        // <entityType>
        $eacType = 'person';
        if ($entityTypeName && isset($this->entityTypeMap[$entityTypeName])) {
            $eacType = $this->entityTypeMap[$entityTypeName];
        }
        $entityType = $this->el('entityType');
        $entityType->setAttribute('value', $eacType);
        $identity->appendChild($entityType);

        // <nameEntry> for authorized form
        if (!empty($actorI18n->authorized_form_of_name)) {
            $nameEntry = $this->el('nameEntry');
            $nameEntry->setAttribute('status', 'authorized');
            $nameEntry->setAttribute('languageOfElement', $culture);

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
            $nameEntry->setAttribute('languageOfElement', $culture);

            $part = $this->el('part', $otherName->name);
            $nameEntry->appendChild($part);

            $identity->appendChild($nameEntry);
        }

        // <descriptiveNote> with corporate body identifiers
        if (!empty($actor->corporate_body_identifiers)) {
            $descriptiveNote = $this->el('descriptiveNote');
            $p = $this->el('p', 'Identifiers: ' . $actor->corporate_body_identifiers);
            $descriptiveNote->appendChild($p);
            $identity->appendChild($descriptiveNote);
        }

        return $identity;
    }

    /**
     * Build the <description> element.
     */
    protected function buildDescription(object $actor, object $actorI18n, string $culture): \DOMElement
    {
        $description = $this->el('description');

        // <existDates>
        if (!empty($actorI18n->dates_of_existence)) {
            $existDates = $this->el('existDates');
            $dateRange = $this->el('dateRange');
            $fromDate = $this->el('fromDate', $actorI18n->dates_of_existence);
            $dateRange->appendChild($fromDate);
            $existDates->appendChild($dateRange);
            $description->appendChild($existDates);
        }

        // <places>
        if (!empty($actorI18n->places)) {
            $places = $this->el('places');
            $place = $this->el('place');
            $placeEntry = $this->el('placeEntry', $actorI18n->places);
            $place->appendChild($placeEntry);
            $places->appendChild($place);
            $description->appendChild($places);
        }

        // <legalStatuses>
        if (!empty($actorI18n->legal_status)) {
            $legalStatuses = $this->el('legalStatuses');
            $legalStatus = $this->el('legalStatus');
            $term = $this->el('term', $actorI18n->legal_status);
            $legalStatus->appendChild($term);
            $legalStatuses->appendChild($legalStatus);
            $description->appendChild($legalStatuses);
        }

        // <functions>
        if (!empty($actorI18n->functions)) {
            $functionsEl = $this->el('functions');
            $functionEl = $this->el('function');
            $term = $this->el('term', $actorI18n->functions);
            $functionEl->appendChild($term);
            $functionsEl->appendChild($functionEl);
            $description->appendChild($functionsEl);
        }

        // <mandates>
        if (!empty($actorI18n->mandates)) {
            $mandates = $this->el('mandates');
            $mandate = $this->el('mandate');
            $term = $this->el('term', $actorI18n->mandates);
            $mandate->appendChild($term);
            $mandates->appendChild($mandate);
            $description->appendChild($mandates);
        }

        // <structureOrGenealogy>
        if (!empty($actorI18n->internal_structures)) {
            $structureOrGenealogy = $this->el('structureOrGenealogy');
            $p = $this->el('p', $actorI18n->internal_structures);
            $structureOrGenealogy->appendChild($p);
            $description->appendChild($structureOrGenealogy);
        }

        // <generalContext>
        if (!empty($actorI18n->general_context)) {
            $generalContext = $this->el('generalContext');
            $p = $this->el('p', $actorI18n->general_context);
            $generalContext->appendChild($p);
            $description->appendChild($generalContext);
        }

        // <biogHist>
        if (!empty($actorI18n->history)) {
            $biogHist = $this->el('biogHist');
            $p = $this->el('p', $actorI18n->history);
            $biogHist->appendChild($p);
            $description->appendChild($biogHist);
        }

        return $description;
    }

    /**
     * Build the <relations> element.
     */
    protected function buildRelations(object $actor, $relationsAsSubject, $relationsAsObject, string $culture): \DOMElement
    {
        $relations = $this->el('relations');

        // Relations where this actor is the subject
        foreach ($relationsAsSubject as $rel) {
            $relatedActorI18n = DB::table('actor_i18n')
                ->where('id', $rel->object_id)
                ->where('culture', $culture)
                ->first();

            if (!$relatedActorI18n || empty($relatedActorI18n->authorized_form_of_name)) {
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
            $relation->setAttribute('relationType', $this->mapRelationType($relTypeName));

            $targetEntity = $this->el('targetEntity');

            $relSlug = DB::table('slug')->where('object_id', $rel->object_id)->value('slug');
            if ($relSlug) {
                $targetEntity->setAttribute('valueURI', $relSlug);
            }

            $part = $this->el('part', $relatedActorI18n->authorized_form_of_name);
            $targetEntity->appendChild($part);
            $relation->appendChild($targetEntity);

            // Date range
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
                $p = $this->el('p', $relTypeName);
                $descriptiveNote->appendChild($p);
                $relation->appendChild($descriptiveNote);
            }

            $relations->appendChild($relation);
        }

        // Relations where this actor is the object
        foreach ($relationsAsObject as $rel) {
            $relatedActorI18n = DB::table('actor_i18n')
                ->where('id', $rel->subject_id)
                ->where('culture', $culture)
                ->first();

            if (!$relatedActorI18n || empty($relatedActorI18n->authorized_form_of_name)) {
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
            $relation->setAttribute('relationType', $this->mapRelationType($relTypeName));

            $targetEntity = $this->el('targetEntity');

            $relSlug = DB::table('slug')->where('object_id', $rel->subject_id)->value('slug');
            if ($relSlug) {
                $targetEntity->setAttribute('valueURI', $relSlug);
            }

            $part = $this->el('part', $relatedActorI18n->authorized_form_of_name);
            $targetEntity->appendChild($part);
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
                $p = $this->el('p', $relTypeName);
                $descriptiveNote->appendChild($p);
                $relation->appendChild($descriptiveNote);
            }

            $relations->appendChild($relation);
        }

        return $relations;
    }

    /**
     * Map AtoM relation type name to EAC-CPF 2.0 relationType attribute value.
     */
    protected function mapRelationType(?string $typeName): string
    {
        if (!$typeName) {
            return 'associative';
        }

        $lower = strtolower($typeName);

        if (str_contains($lower, 'hierarchical') || str_contains($lower, 'parent') || str_contains($lower, 'child')) {
            return 'hierarchical-parent';
        }

        if (str_contains($lower, 'temporal') || str_contains($lower, 'successor') || str_contains($lower, 'predecessor')) {
            return 'temporal-earlier';
        }

        if (str_contains($lower, 'family') || str_contains($lower, 'associative')) {
            return 'associative';
        }

        return 'associative';
    }

    /**
     * Create a namespaced element with optional text content.
     */
    protected function el(string $name, ?string $value = null): \DOMElement
    {
        $element = $this->dom->createElementNS(self::NS_EAC, $name);

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

        $root = $dom->createElementNS(self::NS_EAC, 'eac');
        $dom->appendChild($root);

        $comment = $dom->createComment(' ' . $message . ' ');
        $root->appendChild($comment);

        return $dom->saveXML();
    }
}
