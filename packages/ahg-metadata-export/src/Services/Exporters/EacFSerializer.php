<?php

/**
 * EacFSerializer - EAC-F (Functions) XML Serializer for Heratio
 *
 * Serializes function (ISDF) data to EAC-F XML format, a new draft standard
 * for encoding function descriptions aligned with ISDF and RiC.
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
use Illuminate\Support\Facades\Schema;

class EacFSerializer
{
    /**
     * EAC-F namespace URI (draft).
     */
    public const NS_EACF = 'https://archivists.org/ns/eac-f/v1';

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
        return 'eac-f';
    }

    /**
     * Return the human-readable format name.
     */
    public function getFormatName(): string
    {
        return 'EAC-F (Functions)';
    }

    /**
     * Serialize a function record to EAC-F XML.
     *
     * @param int    $functionId The function_object ID.
     * @param string $culture    The i18n culture code.
     *
     * @return string The EAC-F XML document.
     */
    public function serializeFunction(int $functionId, string $culture = 'en'): string
    {
        $this->dom = new \DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;

        // Fetch function_object base record
        $func = DB::table('function_object')
            ->where('id', $functionId)
            ->first();

        if (!$func) {
            return $this->emptyDocument('Function not found');
        }

        // Fetch function_object_i18n data
        $funcI18n = DB::table('function_object_i18n')
            ->where('id', $functionId)
            ->where('culture', $culture)
            ->first();

        if (!$funcI18n) {
            $funcI18n = DB::table('function_object_i18n')
                ->where('id', $functionId)
                ->where('culture', $func->source_culture)
                ->first();
        }

        if (!$funcI18n) {
            return $this->emptyDocument('Function i18n data not found');
        }

        // Fetch slug
        $slug = DB::table('slug')
            ->where('object_id', $functionId)
            ->value('slug');

        // Fetch type name
        $typeName = null;
        if ($func->type_id) {
            $typeName = DB::table('term_i18n')
                ->where('id', $func->type_id)
                ->where('culture', $culture)
                ->value('name');
        }

        // Check for related ric_activity records
        $ricActivities = collect();
        if (Schema::hasTable('ric_activity')) {
            // Look for ric_activity records related via the relation table
            $ricActivityIds = DB::table('relation')
                ->where('subject_id', $functionId)
                ->orWhere('object_id', $functionId)
                ->get()
                ->map(function ($rel) use ($functionId) {
                    return $rel->subject_id === $functionId ? $rel->object_id : $rel->subject_id;
                });

            if ($ricActivityIds->isNotEmpty()) {
                $ricActivities = DB::table('ric_activity')
                    ->leftJoin('ric_activity_i18n', function ($join) use ($culture) {
                        $join->on('ric_activity.id', '=', 'ric_activity_i18n.id')
                             ->where('ric_activity_i18n.culture', '=', $culture);
                    })
                    ->whereIn('ric_activity.id', $ricActivityIds->toArray())
                    ->select(
                        'ric_activity.id',
                        'ric_activity.type_id as activity_type',
                        'ric_activity.start_date',
                        'ric_activity.end_date',
                        'ric_activity.place_id',
                        'ric_activity_i18n.name as activity_name',
                        'ric_activity_i18n.description as activity_description',
                        'ric_activity_i18n.date_display'
                    )
                    ->get();
            }
        }

        // Fetch relations
        $relations = DB::table('relation')
            ->where('subject_id', $functionId)
            ->orWhere('object_id', $functionId)
            ->select('id', 'subject_id', 'object_id', 'type_id', 'start_date', 'end_date')
            ->get();

        // Build root <eac-f> element
        $eacf = $this->dom->createElementNS(self::NS_EACF, 'eac-f');
        $this->dom->appendChild($eacf);

        $eacf->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', self::NS_XSI);
        $eacf->setAttributeNS(
            self::NS_XSI,
            'xsi:schemaLocation',
            self::NS_EACF . ' https://archivists.org/ns/eac-f/v1/eac-f.xsd'
        );

        // <control>
        $control = $this->buildControl($functionId, $func, $funcI18n, $slug, $culture);
        $eacf->appendChild($control);

        // <functionDescription>
        $functionDescription = $this->buildFunctionDescription($func, $funcI18n, $typeName, $ricActivities, $relations, $functionId, $culture);
        $eacf->appendChild($functionDescription);

        return $this->dom->saveXML();
    }

    /**
     * Build the <control> element.
     */
    protected function buildControl(int $functionId, object $func, object $funcI18n, ?string $slug, string $culture): \DOMElement
    {
        $control = $this->el('control');

        // <recordId>
        $recordId = $slug ?? ('function-' . $functionId);
        $control->appendChild($this->el('recordId', $recordId));

        // <maintenanceStatus>
        $maintenanceStatus = $this->el('maintenanceStatus');
        $maintenanceStatus->setAttribute('value', 'derived');
        $control->appendChild($maintenanceStatus);

        // <maintenanceAgency>
        $maintenanceAgency = $this->el('maintenanceAgency');
        $maintenanceAgency->appendChild($this->el('agencyName', 'Heratio Metadata Export'));
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
        $maintenanceEvent->appendChild($this->el('agent', 'Heratio EAC-F Serializer'));

        $maintenanceHistory->appendChild($maintenanceEvent);
        $control->appendChild($maintenanceHistory);

        // <sources>
        if (!empty($funcI18n->sources)) {
            $sources = $this->el('sources');
            $source = $this->el('source');
            $source->appendChild($this->el('sourceEntry', $funcI18n->sources));
            $sources->appendChild($source);
            $control->appendChild($sources);
        }

        return $control;
    }

    /**
     * Build the <functionDescription> element.
     */
    protected function buildFunctionDescription(
        object $func,
        object $funcI18n,
        ?string $typeName,
        $ricActivities,
        $relations,
        int $functionId,
        string $culture
    ): \DOMElement {
        $functionDescription = $this->el('functionDescription');

        // <identity>
        $identity = $this->buildIdentity($func, $funcI18n, $typeName);
        $functionDescription->appendChild($identity);

        // <description>
        $description = $this->buildDescription($funcI18n, $ricActivities, $culture);
        if ($description->hasChildNodes()) {
            $functionDescription->appendChild($description);
        }

        // <relations>
        $relationsEl = $this->buildRelations($relations, $functionId, $culture);
        if ($relationsEl->hasChildNodes()) {
            $functionDescription->appendChild($relationsEl);
        }

        return $functionDescription;
    }

    /**
     * Build the <identity> element.
     */
    protected function buildIdentity(object $func, object $funcI18n, ?string $typeName): \DOMElement
    {
        $identity = $this->el('identity');

        // <functionType>
        if ($typeName) {
            $functionType = $this->el('functionType', $typeName);
            $identity->appendChild($functionType);
        }

        // <nameEntry> for authorized form
        if (!empty($funcI18n->authorized_form_of_name)) {
            $nameEntry = $this->el('nameEntry');
            $nameEntry->setAttribute('status', 'authorized');

            $part = $this->el('part', $funcI18n->authorized_form_of_name);
            $nameEntry->appendChild($part);

            $identity->appendChild($nameEntry);
        }

        // <classification>
        if (!empty($funcI18n->classification)) {
            $classification = $this->el('classification', $funcI18n->classification);
            $identity->appendChild($classification);
        }

        // <descriptiveNote> with description identifier
        if (!empty($func->description_identifier)) {
            $descriptiveNote = $this->el('descriptiveNote');
            $p = $this->el('p', 'Identifier: ' . $func->description_identifier);
            $descriptiveNote->appendChild($p);
            $identity->appendChild($descriptiveNote);
        }

        return $identity;
    }

    /**
     * Build the <description> element.
     */
    protected function buildDescription(object $funcI18n, $ricActivities, string $culture): \DOMElement
    {
        $description = $this->el('description');

        // <existDates> from function dates
        if (!empty($funcI18n->dates)) {
            $existDates = $this->el('existDates');
            $dateRange = $this->el('dateRange');
            $fromDate = $this->el('fromDate', $funcI18n->dates);
            $dateRange->appendChild($fromDate);
            $existDates->appendChild($dateRange);
            $description->appendChild($existDates);
        }

        // <functionDescription> textual description
        if (!empty($funcI18n->description)) {
            $descriptionText = $this->el('descriptiveNote');
            $p = $this->el('p', $funcI18n->description);
            $descriptionText->appendChild($p);
            $description->appendChild($descriptionText);
        }

        // <history>
        if (!empty($funcI18n->history)) {
            $history = $this->el('history');
            $p = $this->el('p', $funcI18n->history);
            $history->appendChild($p);
            $description->appendChild($history);
        }

        // <legislation>
        if (!empty($funcI18n->legislation)) {
            $legislation = $this->el('legislation');
            $p = $this->el('p', $funcI18n->legislation);
            $legislation->appendChild($p);
            $description->appendChild($legislation);
        }

        // RiC Activity records as structured activity descriptions
        foreach ($ricActivities as $activity) {
            $activityEl = $this->el('activity');

            if (!empty($activity->activity_name)) {
                $activityEl->appendChild($this->el('activityName', $activity->activity_name));
            }

            if (!empty($activity->activity_description)) {
                $actNote = $this->el('descriptiveNote');
                $actNote->appendChild($this->el('p', $activity->activity_description));
                $activityEl->appendChild($actNote);
            }

            if (!empty($activity->date_display)) {
                $actDates = $this->el('existDates');
                $actDateRange = $this->el('dateRange');
                $actDateRange->appendChild($this->el('fromDate', $activity->date_display));
                $actDates->appendChild($actDateRange);
                $activityEl->appendChild($actDates);
            } elseif ($activity->start_date || $activity->end_date) {
                $actDates = $this->el('existDates');
                $actDateRange = $this->el('dateRange');
                if ($activity->start_date) {
                    $fromDate = $this->el('fromDate', (string) $activity->start_date);
                    $fromDate->setAttribute('standardDate', (string) $activity->start_date);
                    $actDateRange->appendChild($fromDate);
                }
                if ($activity->end_date) {
                    $toDate = $this->el('toDate', (string) $activity->end_date);
                    $toDate->setAttribute('standardDate', (string) $activity->end_date);
                    $actDateRange->appendChild($toDate);
                }
                $actDates->appendChild($actDateRange);
                $activityEl->appendChild($actDates);
            }

            // Place reference
            if ($activity->place_id) {
                $placeName = null;
                if (Schema::hasTable('ric_place_i18n')) {
                    $placeName = DB::table('ric_place_i18n')
                        ->where('id', $activity->place_id)
                        ->where('culture', $culture)
                        ->value('name');
                }
                if ($placeName) {
                    $placeEl = $this->el('place');
                    $placeEl->appendChild($this->el('placeName', $placeName));
                    $activityEl->appendChild($placeEl);
                }
            }

            if ($activityEl->hasChildNodes()) {
                $description->appendChild($activityEl);
            }
        }

        return $description;
    }

    /**
     * Build the <relations> element.
     */
    protected function buildRelations($relations, int $functionId, string $culture): \DOMElement
    {
        $relationsEl = $this->el('relations');

        foreach ($relations as $rel) {
            $relatedId = ($rel->subject_id === $functionId) ? $rel->object_id : $rel->subject_id;

            // Try to resolve the related entity name
            // Could be another function_object, an actor, or an information_object
            $relatedName = null;
            $relatedType = null;

            // Check function_object_i18n
            $funcName = DB::table('function_object_i18n')
                ->where('id', $relatedId)
                ->where('culture', $culture)
                ->value('authorized_form_of_name');

            if ($funcName) {
                $relatedName = $funcName;
                $relatedType = 'function';
            } else {
                // Check actor_i18n
                $actorName = DB::table('actor_i18n')
                    ->where('id', $relatedId)
                    ->where('culture', $culture)
                    ->value('authorized_form_of_name');

                if ($actorName) {
                    $relatedName = $actorName;
                    $relatedType = 'agent';
                } else {
                    // Check information_object_i18n
                    $ioTitle = DB::table('information_object_i18n')
                        ->where('id', $relatedId)
                        ->where('culture', $culture)
                        ->value('title');

                    if ($ioTitle) {
                        $relatedName = $ioTitle;
                        $relatedType = 'recordResource';
                    }
                }
            }

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

            if ($relatedType === 'function') {
                $relation->setAttribute('relationType', 'functionRelation');
            } elseif ($relatedType === 'agent') {
                $relation->setAttribute('relationType', 'agentRelation');
            } else {
                $relation->setAttribute('relationType', 'resourceRelation');
            }

            $targetEntity = $this->el('targetEntity');

            $relSlug = DB::table('slug')->where('object_id', $relatedId)->value('slug');
            if ($relSlug) {
                $targetEntity->setAttribute('valueURI', $relSlug);
            }

            $targetEntity->appendChild($this->el('part', $relatedName));
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
                $descriptiveNote->appendChild($this->el('p', $relTypeName));
                $relation->appendChild($descriptiveNote);
            }

            $relationsEl->appendChild($relation);
        }

        return $relationsEl;
    }

    /**
     * Create a namespaced element with optional text content.
     */
    protected function el(string $name, ?string $value = null): \DOMElement
    {
        $element = $this->dom->createElementNS(self::NS_EACF, $name);

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

        $root = $dom->createElementNS(self::NS_EACF, 'eac-f');
        $dom->appendChild($root);

        $comment = $dom->createComment(' ' . $message . ' ');
        $root->appendChild($comment);

        return $dom->saveXML();
    }
}
