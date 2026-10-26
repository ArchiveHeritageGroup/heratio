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
     * @param  int  $actorId  The actor ID.
     * @param  string  $culture  The i18n culture code.
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

        if (! $actor) {
            return $this->emptyDocument('Actor not found');
        }

        // Fetch actor i18n data
        $actorI18n = DB::table('actor_i18n')
            ->where('id', $actorId)
            ->where('culture', $culture)
            ->first();

        if (! $actorI18n) {
            // Fallback to source culture
            $actorI18n = DB::table('actor_i18n')
                ->where('id', $actorId)
                ->where('culture', $actor->source_culture)
                ->first();
        }

        if (! $actorI18n) {
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
     * Build the <control> element, in the EAC-CPF 2.0 order: recordId,
     * maintenanceAgency, maintenanceHistory, sources, then the declarations.
     */
    protected function buildControl(int $actorId, object $actor, object $actorI18n, ?string $slug, string $culture): \DOMElement
    {
        $control = $this->el('control');
        $control->setAttribute('maintenanceStatus', 'derived');

        $control->appendChild($this->el('recordId', $slug ?? ('actor-'.$actorId)));

        $maintenanceAgency = $this->el('maintenanceAgency');
        $maintenanceAgency->appendChild($this->el('agencyName', 'Heratio Metadata Export'));
        $control->appendChild($maintenanceAgency);

        $maintenanceHistory = $this->el('maintenanceHistory');
        $maintenanceEvent = $this->el('maintenanceEvent');
        $maintenanceEvent->setAttribute('maintenanceEventType', 'derived');
        $agent = $this->el('agent', 'Heratio EAC-CPF 2.0 Serializer');
        $agent->setAttribute('agentType', 'machine');
        $maintenanceEvent->appendChild($agent);
        $now = date('c');
        $eventDateTime = $this->el('eventDateTime', $now);
        $eventDateTime->setAttribute('standardDateTime', $now);
        $maintenanceEvent->appendChild($eventDateTime);
        $maintenanceHistory->appendChild($maintenanceEvent);
        $control->appendChild($maintenanceHistory);

        if (! empty($actorI18n->sources)) {
            $sources = $this->el('sources');
            $source = $this->el('source');
            $source->appendChild($this->el('reference', $actorI18n->sources));
            $sources->appendChild($source);
            $control->appendChild($sources);
        }

        // ponytail: 'en' maps to ISO 639-2 'eng'; other cultures are written
        // as given (mostly ISO 639-1), which the schema accepts as an NMTOKEN.
        $languageDeclaration = $this->el('languageDeclaration');
        $languageDeclaration->setAttribute('languageCode', $culture === 'en' ? 'eng' : $culture);
        $languageDeclaration->setAttribute('scriptCode', 'Latn');
        $control->appendChild($languageDeclaration);

        // The same person in other authority files (Wikidata, VIAF, ULAN ...).
        if (\Illuminate\Support\Facades\Schema::hasTable('ahg_actor_identifier')) {
            foreach (DB::table('ahg_actor_identifier')->where('actor_id', $actorId)->orderBy('id')->get() as $ident) {
                if (trim((string) $ident->identifier_value) === '') {
                    continue;
                }
                $other = $this->el('otherRecordId', $ident->identifier_value);
                $other->setAttribute('localType', $ident->identifier_type);
                if (! empty($ident->uri)) {
                    $other->setAttribute('valueURI', $ident->uri);
                }
                $control->appendChild($other);
            }
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
        if (! empty($actorI18n->authorized_form_of_name)) {
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
        if (! empty($actor->corporate_body_identifiers)) {
            $descriptiveNote = $this->el('descriptiveNote');
            $p = $this->el('p', 'Identifiers: '.$actor->corporate_body_identifiers);
            $descriptiveNote->appendChild($p);
            $identity->appendChild($descriptiveNote);
        }

        return $identity;
    }

    /**
     * Build the <description> element. EAC-CPF 2.0 fixes the order: functions,
     * legalStatuses, mandates, places, then the narrative elements.
     */
    protected function buildDescription(object $actor, object $actorI18n, string $culture): \DOMElement
    {
        $description = $this->el('description');

        foreach (['functions' => 'function', 'legal_status' => 'legalStatus', 'mandates' => 'mandate'] as $field => $name) {
            if (! empty($actorI18n->{$field})) {
                $wrapper = $this->el($name === 'legalStatus' ? 'legalStatuses' : $name.'s');
                $item = $this->el($name);
                $item->appendChild($this->el('term', $actorI18n->{$field}));
                $wrapper->appendChild($item);
                $description->appendChild($wrapper);
            }
        }

        if (! empty($actorI18n->places)) {
            $places = $this->el('places');
            $place = $this->el('place');
            $place->appendChild($this->el('placeName', $actorI18n->places));
            $places->appendChild($place);
            $description->appendChild($places);
        }

        // Dates of existence are free text in ISAAR, so a plain <date>.
        if (! empty($actorI18n->dates_of_existence)) {
            $existDates = $this->el('existDates');
            $existDates->appendChild($this->el('date', $actorI18n->dates_of_existence));
            $description->appendChild($existDates);
        }

        foreach (['internal_structures' => 'structureOrGenealogy', 'general_context' => 'generalContext', 'history' => 'biogHist'] as $field => $name) {
            if (! empty($actorI18n->{$field})) {
                $el = $this->el($name);
                $el->appendChild($this->el('p', $actorI18n->{$field}));
                $description->appendChild($el);
            }
        }

        return $description;
    }

    /**
     * Build the <relations> element from the actor-to-actor rows in either
     * direction; rows pointing at descriptions or other objects are skipped.
     */
    protected function buildRelations(object $actor, $relationsAsSubject, $relationsAsObject, string $culture): \DOMElement
    {
        $relations = $this->el('relations');
        $rows = [];
        foreach ($relationsAsSubject as $rel) {
            $rows[] = [$rel, $rel->object_id];
        }
        foreach ($relationsAsObject as $rel) {
            $rows[] = [$rel, $rel->subject_id];
        }

        foreach ($rows as [$rel, $otherId]) {
            $other = DB::table('actor')->where('id', $otherId)->first(['id', 'entity_type_id']);
            $otherName = $other ? DB::table('actor_i18n')->where('id', $otherId)->where('culture', $culture)->value('authorized_form_of_name') : null;
            if (! $otherName) {
                continue;
            }

            $relTypeName = $rel->type_id
                ? DB::table('term_i18n')->where('id', $rel->type_id)->where('culture', $culture)->value('name')
                : null;
            $otherType = $other->entity_type_id
                ? DB::table('term_i18n')->where('id', $other->entity_type_id)->where('culture', 'en')->value('name')
                : null;

            $relation = $this->el('relation');

            $targetEntity = $this->el('targetEntity');
            $targetEntity->setAttribute('targetType', $this->entityTypeMap[$otherType] ?? 'agent');
            $relSlug = DB::table('slug')->where('object_id', $otherId)->value('slug');
            if ($relSlug) {
                $targetEntity->setAttribute('valueURI', url('/actor/'.$relSlug));
            }
            $targetEntity->appendChild($this->el('part', $otherName));
            $relation->appendChild($targetEntity);

            if ($rel->start_date || $rel->end_date) {
                $dateRange = $this->el('dateRange');
                foreach (['fromDate' => $rel->start_date, 'toDate' => $rel->end_date] as $name => $date) {
                    if ($date) {
                        $d = $this->el($name, (string) $date);
                        $d->setAttribute('standardDate', (string) $date);
                        $dateRange->appendChild($d);
                    }
                }
                $relation->appendChild($dateRange);
            }

            $relationType = $this->el('relationType', $relTypeName ?: $this->mapRelationType(null));
            $relationType->setAttribute('localType', $this->mapRelationType($relTypeName));
            $relation->appendChild($relationType);

            $relations->appendChild($relation);
        }

        return $relations;
    }

    /**
     * Map AtoM relation type name to EAC-CPF 2.0 relationType attribute value.
     */
    protected function mapRelationType(?string $typeName): string
    {
        if (! $typeName) {
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

        $comment = $dom->createComment(' '.$message.' ');
        $root->appendChild($comment);

        return $dom->saveXML();
    }
}
