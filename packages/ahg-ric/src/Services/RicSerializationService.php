<?php

/**
 * RicSerializationService - RIC-O JSON-LD serialization
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
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgRic\Services;

use AhgCore\Constants\TermId;
use AhgCore\Services\SettingHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for serializing AtoM entities to RiC-O JSON-LD format.
 * 
 * Supports:
 * - ISAAR(CPF) for Agents
 * - ISDF for Functions
 * - ISAD for Records
 * - ISDIAH for Repositories
 * - ISCAP for Security/Access
 * - Spectrum for Conservation
 * - GRAP for Heritage Assets
 */
class RicSerializationService
{
    private string $baseUri;
    private string $instanceId;
    private string $fusekiEndpoint;

    // RIC-O Namespace
    private const RICO_NS = 'https://www.ica.org/standards/RiC/ontology#';
    private const RDF_NS = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
    private const RDFS_NS = 'http://www.w3.org/2000/01/rdf-schema#';
    private const XSD_NS = 'http://www.w3.org/2001/XMLSchema#';

    // Level to RIC mapping
    private array $levelToRic = [
        'fonds' => 'RecordSet',
        'subfonds' => 'RecordSet',
        'collection' => 'RecordSet',
        'series' => 'RecordSet',
        'subseries' => 'RecordSet',
        'file' => 'RecordSet',
        'item' => 'Record',
        'part' => 'RecordPart',
    ];

    // Actor type to RIC mapping
    private array $actorTypeToRic = [
        'corporate body' => 'CorporateBody',
        'person' => 'Person',
        'family' => 'Family',
    ];

    // Thing type to RIC mapping (boxes, containers, etc.)
    private array $thingTypeToRic = [
        'box' => 'Thing',
        'container' => 'Thing',
        'shelf_unit' => 'Thing',
        'cabinet' => 'Thing',
        'vault' => 'Thing',
        'equipment' => 'Thing',
    ];

    // Event type to RIC mapping (per OpenRiC mapping spec §6.5)
    private array $eventTypeToRic = [
        'creation' => 'Production',
        'production' => 'Production',
        'contribution' => 'Production',
        'accumulation' => 'Accumulation',
        'collection' => 'Accumulation',
        'custody' => 'Activity',
        'mandate' => 'Activity',
        'publication' => 'Activity',
        'reproduction' => 'Activity',
    ];

    public function __construct()
    {
        $this->baseUri = config('app.url', 'http://localhost');
        $this->instanceId = SettingHelper::get('ahg_ric_instance_id', 'default');
        $this->fusekiEndpoint = config('heratio.fuseki_endpoint', 'http://localhost:3030/heratio');
    }

    /**
     * Serialize an Information Object (Record) to RIC-O JSON-LD
     */
    public function serializeRecord(int $ioId, array $options = []): array
    {
        $io = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', 'io.id', '=', 'i18n.id')
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->leftJoin('term as level', 'io.level_of_description_id', '=', 'level.id')
            ->leftJoin('term_i18n as level_i18n', 'level.id', '=', 'level_i18n.id')
            ->where('io.id', $ioId)
            ->select([
                'io.*',
                'i18n.*',
                'slug.slug',
                'level_i18n.name as level_name',
            ])
            ->first();

        if (!$io) {
            return ['error' => 'Information Object not found'];
        }

        $ricType = $this->levelToRic[$io->level_name] ?? 'Record';

        $record = [
            '@context' => [
                'rico' => self::RICO_NS,
                'rdf' => self::RDF_NS,
                'rdfs' => self::RDFS_NS,
                'xsd' => self::XSD_NS,
                'skos' => 'http://www.w3.org/2004/02/skos/core#',
                'owl' => 'http://www.w3.org/2002/07/owl#',
            ],
            '@id' => $this->baseUri . '/informationobject/' . $io->slug,
            '@type' => self::RICO_NS . $ricType,
            'rico:type' => $ricType,
        ];

        // Title
        if (!empty($io->title)) {
            $record['rico:title'] = $io->title;
        }

        // Identifier
        if (!empty($io->identifier)) {
            $record['rico:identifier'] = $io->identifier;
        }

        // Description
        if (!empty($io->scope_and_content)) {
            $record['rico:description'] = $io->scope_and_content;
        }

        // Dates
        $dates = $this->getDatesForRecord($ioId);
        if (!empty($dates)) {
            $record['rico:hasDateRangeSet'] = $dates;
        }

        // Language
        $languages = $this->getLanguagesForRecord($ioId);
        if (!empty($languages)) {
            $record['rico:hasLanguage'] = $languages;
        }

        // Extent
        if (!empty($io->extent_and_medium)) {
            $record['rico:hasExtent'] = [
                '@type' => self::RICO_NS . 'Extent',
                'rico:extentType' => $io->extent_and_medium,
            ];
        }

        // Repository
        $repository = $this->getRepositoryForRecord($ioId);
        if ($repository) {
            $record['rico:heldBy'] = [
                '@id' => $this->baseUri . '/repository/' . $repository->slug,
                '@type' => self::RICO_NS . 'CorporateBody',
                'rico:name' => $repository->authorized_form_of_name,
            ];
        }

        // Access conditions
        if (!empty($io->access_conditions)) {
            $record['rico:conditionsOfAccess'] = $io->access_conditions;
        }

        // Subject links
        $subjects = $this->getSubjectsForRecord($ioId);
        if (!empty($subjects)) {
            $record['rico:hasSubject'] = $subjects;
        }

        // Creator agents
        $creators = $this->getCreatorsForRecord($ioId);
        if (!empty($creators)) {
            $record['rico:hasCreator'] = $creators;
        }

        // Digital objects (instantiations)
        $instantiations = $this->getInstantiationsForRecord($ioId);
        if (!empty($instantiations)) {
            $record['rico:hasInstantiation'] = $instantiations;
        }

        // Child records (hierarchy)
        $children = $this->getChildRecords($ioId);
        if (!empty($children) && ($options['include_children'] ?? false)) {
            $record['rico:hasRecordPart'] = $children;
        }

        return $record;
    }

    /**
     * Serialize an Actor (Agent) to RIC-O JSON-LD with ISAAR compliance
     */
    public function serializeAgent(int $actorId, array $options = []): array
    {
        $culture = app()->getLocale() ?: 'en';
        $actor = DB::table('actor as a')
            ->leftJoin('actor_i18n as i18n', function ($j) use ($culture) {
                $j->on('a.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as et_i18n', function ($j) use ($culture) {
                $j->on('a.entity_type_id', '=', 'et_i18n.id')->where('et_i18n.culture', '=', $culture);
            })
            ->leftJoin('slug', 'a.id', '=', 'slug.object_id')
            ->where('a.id', $actorId)
            ->select('a.*', 'i18n.*', 'et_i18n.name as entity_type_name', 'slug.slug')
            ->first();

        if (!$actor) {
            return ['error' => 'Actor not found'];
        }

        $typeKey = strtolower($actor->entity_type_name ?? '');
        $ricType = $this->actorTypeToRic[$typeKey] ?? 'Agent';

        $agent = [
            '@context' => [
                'rico' => self::RICO_NS,
                'rdf' => self::RDF_NS,
                'rdfs' => self::RDFS_NS,
                'xsd' => self::XSD_NS,
            ],
            '@id' => $this->baseUri . '/actor/' . ($actor->slug ?: $actor->id),
            '@type' => 'rico:' . $ricType,
        ];

        // ISAAR mandatory: Authorized Form of Name
        if (!empty($actor->authorized_form_of_name)) {
            $agent['rico:name'] = $actor->authorized_form_of_name;
            $agent['rico:normalizedForm'] = $actor->authorized_form_of_name;
        }

        // ISAAR: Parallel Forms
        if (!empty($actor->parallel_form_of_name)) {
            $agent['rico:alternativeForm'] = $actor->parallel_form_of_name;
        }

        // ISAAR: Other Forms
        if (!empty($actor->other_form_of_name)) {
            $agent['rico:otherName'] = $actor->other_form_of_name;
        }

        // Dates
        if (!empty($actor->dates_of_existence)) {
            $agent['rico:dateOfEstablishment'] = $actor->dates_of_existence;
        }

        // History
        if (!empty($actor->history)) {
            $agent['rico:history'] = $actor->history;
        }

        // Places
        $places = $this->getPlacesForActor($actorId);
        if (!empty($places)) {
            $agent['rico:hasPlace'] = $places;
        }

        // Mandates
        $mandates = $this->getMandatesForActor($actorId);
        if (!empty($mandates)) {
            $agent['rico:hasMandate'] = $mandates;
        }

        // Functions
        $functions = $this->getFunctionsForActor($actorId);
        if (!empty($functions)) {
            $agent['rico:performs'] = $functions;
        }

        // Occupation
        if (!empty($actor->occupation)) {
            $agent['rico:hasOccupation'] = $actor->occupation;
        }

        // Contact
        $contact = $this->getContactInfo($actorId);
        if ($contact) {
            $agent['rico:contact'] = $contact;
        }

        return $agent;
    }

    /**
     * Serialize a Function to RIC-O JSON-LD with ISDF compliance
     */
    public function serializeFunction(int $functionId, array $options = []): array
    {
        $function = DB::table('function_object as f')
            ->leftJoin('function_object_i18n as i18n', 'f.id', '=', 'i18n.id')
            ->where('f.id', $functionId)
            ->first();

        if (!$function) {
            return ['error' => 'Function not found'];
        }

        $ricFunc = [
            '@context' => [self::RICO_NS => self::RICO_NS],
            '@id' => $this->baseUri . '/function/' . $function->id,
            '@type' => self::RICO_NS . 'Function',
        ];

        // ISDF: Name
        if (!empty($function->authorized_form_of_name)) {
            $ricFunc['rico:name'] = $function->authorized_form_of_name;
        }

        // ISDF: Description
        if (!empty($function->description)) {
            $ricFunc['rico:description'] = $function->description;
        }

        // ISDF: Dates
        if (!empty($function->dates)) {
            $ricFunc['rico:hasDateRangeSet'] = [
                '@type' => self::RICO_NS . 'DateRange',
                'rico:startDate' => $function->dates,
            ];
        }

        // ISDF: Activities
        $activities = $this->getActivitiesForFunction($functionId);
        if (!empty($activities)) {
            $ricFunc['rico:hasActivity'] = $activities;
        }

        // ISDF: Performing Agents
        $agents = $this->getAgentsForFunction($functionId);
        if (!empty($agents)) {
            $ricFunc['rico:hasPerformingAgent'] = $agents;
        }

        return $ricFunc;
    }

    /**
     * Serialize a Repository to RIC-O JSON-LD with ISDIAH compliance
     */
    public function serializeRepository(int $repositoryId, array $options = []): array
    {
        $culture = app()->getLocale() ?: 'en';
        $repo = DB::table('actor as a')
            ->leftJoin('actor_i18n as i18n', function ($j) use ($culture) {
                $j->on('a.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('repository_i18n as repo_i18n', function ($j) use ($culture) {
                $j->on('a.id', '=', 'repo_i18n.id')->where('repo_i18n.culture', '=', $culture);
            })
            ->leftJoin('slug', 'a.id', '=', 'slug.object_id')
            ->where('a.id', $repositoryId)
            ->select('a.*', 'i18n.*', 'repo_i18n.*', 'slug.slug')
            ->first();

        if (!$repo) {
            return ['error' => 'Repository not found'];
        }

        $ricRepo = [
            '@context' => [
                'rico' => self::RICO_NS,
                'rdf' => self::RDF_NS,
                'rdfs' => self::RDFS_NS,
                'xsd' => self::XSD_NS,
            ],
            '@id' => $this->baseUri . '/repository/' . ($repo->slug ?: $repo->id),
            '@type' => 'rico:CorporateBody',
        ];

        // ISDIAH: Authorized Form
        if (!empty($repo->authorized_form_of_name)) {
            $ricRepo['rico:name'] = $repo->authorized_form_of_name;
        }

        // ISDIAH: Contact Information
        $contact = $this->getContactInfo($repositoryId);
        if ($contact) {
            $ricRepo['rico:contact'] = $contact;
        }

        // ISDIAH: Access
        if (!empty($repo->access_conditions)) {
            $ricRepo['rico:conditionsOfAccess'] = $repo->access_conditions;
        }

        // ISDIAH: Holdings
        $holdings = $this->getHoldingsForRepository($repositoryId);
        if (!empty($holdings)) {
            $ricRepo['rico:hasHolding'] = $holdings;
        }

        return $ricRepo;
    }

    /**
     * Serialize a RiC-native Place to RIC-O JSON-LD.
     */
    public function serializePlace(int $placeId, array $options = []): array
    {
        $culture = app()->getLocale() ?: 'en';

        $place = DB::table('ric_place as p')
            ->leftJoin('ric_place_i18n as i18n', function ($j) use ($culture) {
                $j->on('p.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('ric_place as parent', 'p.parent_id', '=', 'parent.id')
            ->leftJoin('ric_place_i18n as parent_i18n', function ($j) use ($culture) {
                $j->on('parent.id', '=', 'parent_i18n.id')
                  ->where('parent_i18n.culture', '=', $culture);
            })
            ->where('p.id', $placeId)
            ->select([
                'p.*',
                'i18n.name',
                'i18n.description',
                'i18n.address',
                'parent.id as parent_place_id',
                'parent_i18n.name as parent_name',
            ])
            ->first();

        if (!$place) {
            return ['error' => 'Place not found'];
        }

        $ricPlace = [
            '@context' => [
                'rico' => self::RICO_NS,
                'rdf' => self::RDF_NS,
                'rdfs' => self::RDFS_NS,
                'xsd' => self::XSD_NS,
                'owl' => 'http://www.w3.org/2002/07/owl#',
            ],
            '@id' => $this->baseUri . '/place/' . $place->id,
            '@type' => 'rico:Place',
        ];

        if (!empty($place->name)) {
            $ricPlace['rico:name'] = $place->name;
        }

        if (!empty($place->description)) {
            $ricPlace['rico:description'] = $place->description;
        }

        if (!empty($place->address)) {
            $ricPlace['rico:streetAddress'] = $place->address;
        }

        if (!empty($place->type_id)) {
            $ricPlace['openric:localType'] = $place->type_id;
        }

        if ($place->latitude !== null && $place->longitude !== null) {
            $ricPlace['rico:latitude'] = (float) $place->latitude;
            $ricPlace['rico:longitude'] = (float) $place->longitude;
        }

        if (!empty($place->authority_uri)) {
            $ricPlace['owl:sameAs'] = $place->authority_uri;
        }

        if ($place->parent_place_id) {
            $ricPlace['rico:isOrWasPartOf'] = [
                '@id' => $this->baseUri . '/place/' . $place->parent_place_id,
                '@type' => 'rico:Place',
                'rico:name' => $place->parent_name,
            ];
        }

        return $ricPlace;
    }

    /**
     * Serialize a RiC-native Rule (mandate, law, policy) to RIC-O JSON-LD.
     */
    public function serializeRule(int $ruleId, array $options = []): array
    {
        $culture = app()->getLocale() ?: 'en';

        $rule = DB::table('ric_rule as r')
            ->leftJoin('ric_rule_i18n as i18n', function ($j) use ($culture) {
                $j->on('r.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->where('r.id', $ruleId)
            ->select([
                'r.*',
                'i18n.title',
                'i18n.description',
                'i18n.legislation',
                'i18n.sources',
            ])
            ->first();

        if (!$rule) {
            return ['error' => 'Rule not found'];
        }

        $ricRule = [
            '@context' => [
                'rico' => self::RICO_NS,
                'rdf' => self::RDF_NS,
                'rdfs' => self::RDFS_NS,
                'xsd' => self::XSD_NS,
                'owl' => 'http://www.w3.org/2002/07/owl#',
            ],
            '@id' => $this->baseUri . '/rule/' . $rule->id,
            '@type' => 'rico:Rule',
        ];

        if (!empty($rule->title)) {
            $ricRule['rico:title'] = $rule->title;
            $ricRule['rico:name'] = $rule->title;
        }

        if (!empty($rule->description)) {
            $ricRule['rico:description'] = $rule->description;
        }

        if (!empty($rule->type_id)) {
            $ricRule['rico:ruleType'] = $rule->type_id;
            $ricRule['openric:localType'] = $rule->type_id;
        }

        if (!empty($rule->jurisdiction)) {
            $ricRule['openric:jurisdiction'] = $rule->jurisdiction;
        }

        if (!empty($rule->legislation)) {
            $ricRule['rico:descriptiveNote'] = $rule->legislation;
        }

        if (!empty($rule->sources)) {
            $ricRule['rico:hasSource'] = $rule->sources;
        }

        if (!empty($rule->authority_uri)) {
            $ricRule['owl:sameAs'] = $rule->authority_uri;
        }

        if ($rule->start_date || $rule->end_date) {
            $dateRange = ['@type' => 'rico:DateRange'];
            if ($rule->start_date) {
                $dateRange['rico:beginningDate'] = [
                    '@value' => $rule->start_date,
                    '@type' => 'xsd:date',
                ];
            }
            if ($rule->end_date) {
                $dateRange['rico:endDate'] = [
                    '@value' => $rule->end_date,
                    '@type' => 'xsd:date',
                ];
            }
            $ricRule['rico:hasDateRangeSet'] = $dateRange;
        }

        return $ricRule;
    }

    /**
     * Serialize a RiC-native Activity to RIC-O JSON-LD.
     *
     * Activity.type_id is mapped per mapping spec §6.5:
     *   production / creation / contribution → rico:Production
     *   accumulation / collection            → rico:Accumulation
     *   anything else                        → rico:Activity
     */
    public function serializeActivity(int $activityId, array $options = []): array
    {
        $culture = app()->getLocale() ?: 'en';

        $act = DB::table('ric_activity as a')
            ->leftJoin('ric_activity_i18n as i18n', function ($j) use ($culture) {
                $j->on('a.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('ric_place as p', 'a.place_id', '=', 'p.id')
            ->leftJoin('ric_place_i18n as p_i18n', function ($j) use ($culture) {
                $j->on('p.id', '=', 'p_i18n.id')->where('p_i18n.culture', '=', $culture);
            })
            ->where('a.id', $activityId)
            ->select([
                'a.*',
                'i18n.name',
                'i18n.description',
                'i18n.date_display',
                'p.id as place_ric_id',
                'p_i18n.name as place_name',
            ])
            ->first();

        if (!$act) {
            return ['error' => 'Activity not found'];
        }

        $typeKey = strtolower($act->type_id ?? '');
        $ricType = $this->eventTypeToRic[$typeKey] ?? 'Activity';

        $ricAct = [
            '@context' => [
                'rico' => self::RICO_NS,
                'rdf' => self::RDF_NS,
                'rdfs' => self::RDFS_NS,
                'xsd' => self::XSD_NS,
            ],
            '@id' => $this->baseUri . '/activity/' . $act->id,
            '@type' => 'rico:' . $ricType,
        ];

        if (!empty($act->name)) {
            $ricAct['rico:name'] = $act->name;
        }

        if (!empty($act->description)) {
            $ricAct['rico:description'] = $act->description;
        }

        if (!empty($act->type_id)) {
            $ricAct['openric:localType'] = $act->type_id;
        }

        if ($act->start_date || $act->end_date || !empty($act->date_display)) {
            $dateRange = ['@type' => 'rico:DateRange'];
            if ($act->start_date) {
                $dateRange['rico:beginningDate'] = [
                    '@value' => $act->start_date,
                    '@type' => 'xsd:date',
                ];
            }
            if ($act->end_date) {
                $dateRange['rico:endDate'] = [
                    '@value' => $act->end_date,
                    '@type' => 'xsd:date',
                ];
            }
            if (!empty($act->date_display)) {
                $dateRange['rico:expressedDate'] = $act->date_display;
            }
            $ricAct['rico:isOrWasAssociatedWithDate'] = $dateRange;
        }

        if ($act->place_ric_id) {
            $ricAct['rico:hasOrHadLocation'] = [
                '@id' => $this->baseUri . '/place/' . $act->place_ric_id,
                '@type' => 'rico:Place',
                'rico:name' => $act->place_name,
            ];
        }

        return $ricAct;
    }

    /**
     * Serialize a RiC-native Instantiation (digital or physical manifestation) to RIC-O JSON-LD.
     */
    public function serializeInstantiation(int $instantiationId, array $options = []): array
    {
        $culture = app()->getLocale() ?: 'en';

        $inst = DB::table('ric_instantiation as ri')
            ->leftJoin('ric_instantiation_i18n as i18n', function ($j) use ($culture) {
                $j->on('ri.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('information_object as io', 'ri.record_id', '=', 'io.id')
            ->leftJoin('slug as io_slug', 'io.id', '=', 'io_slug.object_id')
            ->leftJoin('information_object_i18n as io_i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'io_i18n.id')->where('io_i18n.culture', '=', $culture);
            })
            ->where('ri.id', $instantiationId)
            ->select([
                'ri.*',
                'i18n.title',
                'i18n.description',
                'i18n.technical_characteristics',
                'i18n.production_technical_characteristics',
                'io_slug.slug as record_slug',
                'io_i18n.title as record_title',
            ])
            ->first();

        if (!$inst) {
            return ['error' => 'Instantiation not found'];
        }

        $ricInst = [
            '@context' => [
                'rico' => self::RICO_NS,
                'rdf' => self::RDF_NS,
                'rdfs' => self::RDFS_NS,
                'xsd' => self::XSD_NS,
            ],
            '@id' => $this->baseUri . '/instantiation/' . $inst->id,
            '@type' => 'rico:Instantiation',
        ];

        if (!empty($inst->title)) {
            $ricInst['rico:identifier'] = $inst->title;
            $ricInst['rico:title'] = $inst->title;
        }

        if (!empty($inst->description)) {
            $ricInst['rico:description'] = $inst->description;
        }

        if (!empty($inst->mime_type)) {
            $ricInst['rico:hasMimeType'] = $inst->mime_type;
        }

        if (!empty($inst->carrier_type)) {
            $ricInst['rico:hasCarrierType'] = $inst->carrier_type;
        }

        if ($inst->extent_value !== null) {
            $ricInst['rico:hasExtent'] = [
                '@type' => 'rico:Extent',
                'rico:quantity' => (float) $inst->extent_value,
                'rico:extentType' => $inst->extent_unit ?: 'bytes',
            ];
        }

        if (!empty($inst->technical_characteristics)) {
            $ricInst['rico:technicalCharacteristics'] = $inst->technical_characteristics;
        }

        if (!empty($inst->production_technical_characteristics)) {
            $ricInst['rico:productionTechnicalCharacteristics'] =
                $inst->production_technical_characteristics;
        }

        if ($inst->record_id && $inst->record_slug) {
            $ricInst['rico:isInstantiationOf'] = [
                '@id' => $this->baseUri . '/informationobject/' . $inst->record_slug,
                '@type' => 'rico:Record',
                'rico:title' => $inst->record_title,
            ];
        }

        return $ricInst;
    }

    /**
     * Serialize with ISCAP compliance (Security/Access)
     */
    public function addIscapCompliance(array $ricEntity, int $entityId, string $entityType): array
    {
        $culture = app()->getLocale() ?: 'en';

        // ISCAP security tables are optional — only present when the security
        // extension is installed. Skip the join when they're absent (mirrors
        // the guard already in getAccessRestrictions for icip_access_restriction).
        $security = null;
        if (\Illuminate\Support\Facades\Schema::hasTable('security_access_condition_link')
            && \Illuminate\Support\Facades\Schema::hasTable('security_level')) {
            $security = DB::table('security_access_condition_link as sacl')
                ->join('security_level as sl', 'sacl.classification_id', '=', 'sl.id')
                ->leftJoin('security_level_i18n as sl_i18n', function ($j) use ($culture) {
                    $j->on('sl.id', '=', 'sl_i18n.id')->where('sl_i18n.culture', '=', $culture);
                })
                ->where('sacl.object_id', $entityId)
                ->select('sl_i18n.name', 'sl.classification', 'sl.level_value')
                ->first();
        }

        if ($security) {
            $ricEntity['rico:hasSecurityClassification'] = [
                '@type' => self::RICO_NS . 'SecurityClassification',
                'rico:securityLevel' => $security->name,
                'rico:securityLevelCode' => $security->classification,
            ];
        }

        // Access Restriction
        $restrictions = $this->getAccessRestrictions($entityType, $entityId);
        if (!empty($restrictions)) {
            $ricEntity['rico:hasAccessRestriction'] = $restrictions;
        }

        // Personal Data
        $hasPersonalData = $this->checkPersonalData($entityType, $entityId);
        if ($hasPersonalData) {
            $ricEntity['rico:containsPersonalData'] = true;
        }

        return $ricEntity;
    }

    /**
     * Export entire RecordSet (Fonds/Collection) as JSON-LD
     */
    public function exportRecordSet(int $fondsId, array $options = []): array
    {
        $fonds = $this->serializeRecord($fondsId, $options);
        
        // Include all descendants
        $descendants = $this->getAllDescendants($fondsId);
        
        $graph = [
            '@context' => [
                self::RICO_NS => self::RICO_NS,
                'rdf' => self::RDF_NS,
            ],
            '@graph' => array_merge([$fonds], $descendants),
        ];

        // Pretty print if requested
        if ($options['pretty'] ?? false) {
            return json_encode($graph, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return $graph;
    }

    /**
     * Get dates for a record
     */
    private function getDatesForRecord(int $ioId): ?array
    {
        $dates = DB::table('event')
            ->leftJoin('event_i18n', function ($j) {
                $j->on('event.id', '=', 'event_i18n.id')
                   ->where('event_i18n.culture', '=', 'en');
            })
            ->where('event.object_id', $ioId)
            ->select('event.id', 'event.type_id', 'event.start_date', 'event.end_date', 'event_i18n.date as date_display')
            ->get();

        if ($dates->isEmpty()) {
            return null;
        }

        $dateRanges = [];
        foreach ($dates as $date) {
            $dateRanges[] = [
                '@type' => self::RICO_NS . 'DateRange',
                'rico:startDate' => $date->start_date ?? null,
                'rico:endDate' => $date->end_date ?? null,
                'rico:normalizedDate' => $date->date_display ?? null,
                'rico:dateType' => $date->type_id ?? 'existence',
            ];
        }

        return [
            '@type' => self::RICO_NS . 'DateRangeSet',
            'rico:hasDateRange' => $dateRanges,
        ];
    }

    /**
     * Get languages for a record
     */
    private function getLanguagesForRecord(int $ioId): array
    {
        return DB::table('object_term_relation')
            ->join('term_i18n', function ($j) {
                $j->on('object_term_relation.term_id', '=', 'term_i18n.id')
                   ->where('term_i18n.culture', '=', 'en');
            })
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $ioId)
            ->where('term.taxonomy_id', 7)
            ->pluck('term_i18n.name')
            ->map(fn($lang) => [
                '@type' => self::RICO_NS . 'Language',
                'rico:languageCode' => $lang,
            ])
            ->toArray();
    }

    /**
     * Get repository for a record
     */
    private function getRepositoryForRecord(int $ioId): ?object
    {
        return DB::table('repository as r')
            ->leftJoin('actor_i18n as i18n', function ($j) {
                $j->on('r.id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
            })
            ->leftJoin('slug', 'r.id', '=', 'slug.object_id')
            ->join('information_object', 'information_object.repository_id', '=', 'r.id')
            ->where('information_object.id', $ioId)
            ->select('r.*', 'i18n.authorized_form_of_name', 'slug.slug')
            ->first();
    }

    /**
     * Get subjects for a record
     */
    private function getSubjectsForRecord(int $ioId): array
    {
        return DB::table('object_term_relation as otr')
            ->join('term as t', 'otr.term_id', '=', 't.id')
            ->join('term_i18n as ti', 't.id', '=', 'ti.id')
            ->where('otr.object_id', $ioId)
            ->where('t.taxonomy_id', 35) // Subject taxonomy
            ->where('ti.culture', 'en')
            ->pluck('ti.name')
            ->map(fn($name) => [
                '@type' => 'skos:Concept',
                'skos:prefLabel' => $name,
            ])
            ->toArray();
    }

    /**
     * Get creators (agents) for a record
     */
    private function getCreatorsForRecord(int $ioId): array
    {
        return DB::table('event')
            ->join('actor as a', 'event.actor_id', '=', 'a.id')
            ->join('actor_i18n as i18n', function ($j) {
                $j->on('a.id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
            })
            ->where('event.object_id', $ioId)
            ->where('event.type_id', TermId::EVENT_TYPE_CREATION)
            ->whereNotNull('event.actor_id')
            ->select('a.id', 'i18n.authorized_form_of_name', 'a.entity_type_id')
            ->distinct()
            ->get()
            ->map(fn($actor) => [
                '@id' => $this->baseUri . '/actor/' . $actor->id,
                '@type' => self::RICO_NS . ($this->actorTypeToRic[strtolower($actor->entity_type_id ?? '')] ?? 'Agent'),
                'rico:name' => $actor->authorized_form_of_name,
            ])
            ->toArray();
    }

    /**
     * Get instantiations (digital objects) for a record
     */
    private function getInstantiationsForRecord(int $ioId): array
    {
        return DB::table('digital_object as do')
            ->where('do.object_id', $ioId)
            ->get()
            ->map(fn($do) => [
                '@type' => self::RICO_NS . 'Instantiation',
                'rico:identifier' => $do->name,
                'rico:mimeType' => $do->mime_type ?? null,
                'rico:size' => $do->byte_size ?? null,
            ])
            ->toArray();
    }

    /**
     * Get child records
     */
    private function getChildRecords(int $parentId): array
    {
        return DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', 'io.id', '=', 'i18n.id')
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.parent_id', $parentId)
            ->select('io.id', 'slug.slug', 'i18n.title', 'io.identifier')
            ->get()
            ->map(fn($child) => [
                '@id' => $this->baseUri . '/informationobject/' . ($child->slug ?? $child->id),
                '@type' => self::RICO_NS . 'RecordPart',
                'rico:identifier' => $child->identifier,
                'rico:title' => $child->title,
            ])
            ->toArray();
    }

    /**
     * Get all descendants recursively
     */
    private function getAllDescendants(int $parentId, int $depth = 0): array
    {
        if ($depth > 10) {
            return []; // Prevent infinite recursion
        }

        $children = $this->getChildRecords($parentId);
        $allDescendants = [];

        foreach ($children as $child) {
            $childId = $this->extractIdFromUri($child['@id']);
            $allDescendants[] = $this->serializeRecord($childId, ['include_children' => false]);
            $allDescendants = array_merge($allDescendants, $this->getAllDescendants($childId, $depth + 1));
        }

        return $allDescendants;
    }

    /**
     * Get places for an actor
     */
    private function getPlacesForActor(int $actorId): array
    {
        // Places for actors are stored in actor_i18n.places text field
        $placesText = DB::table('actor_i18n')
            ->where('id', $actorId)
            ->where('culture', 'en')
            ->value('places');

        if (empty($placesText)) {
            return [];
        }

        return [
            [
                '@type' => self::RICO_NS . 'Place',
                'rico:placeName' => strip_tags($placesText),
            ],
        ];
    }

    /**
     * Get mandates for an actor
     */
    private function getMandatesForActor(int $actorId): array
    {
        // Mandates stored in actor_i18n.mandates text field; mandate table for structured data
        $mandateText = DB::table('actor_i18n')
            ->where('id', $actorId)
            ->where('culture', 'en')
            ->value('mandates');

        if (empty($mandateText)) {
            // Also check structured mandate table
            $structured = DB::table('mandate')
                ->where('actor_id', $actorId)
                ->get();
            if ($structured->isEmpty()) {
                return [];
            }
            return $structured->map(fn($m) => [
                '@type' => self::RICO_NS . 'Mandate',
                'rico:description' => $m->description ?? null,
            ])->toArray();
        }

        return [
            [
                '@type' => self::RICO_NS . 'Mandate',
                'rico:description' => strip_tags($mandateText),
            ],
        ];
    }

    /**
     * Get functions for an actor
     */
    private function getFunctionsForActor(int $actorId): array
    {
        return DB::table('relation as r')
            ->join('function_object as f', 'r.object_id', '=', 'f.id')
            ->join('function_object_i18n as fi', 'f.id', '=', 'fi.id')
            ->where('r.subject_id', $actorId)
            ->where('r.type_id', 40) // Function relation
            ->select('f.id', 'fi.authorized_form_of_name')
            ->get()
            ->map(fn($func) => [
                '@id' => $this->baseUri . '/function/' . $func->id,
                '@type' => self::RICO_NS . 'Function',
                'rico:name' => $func->authorized_form_of_name,
            ])
            ->toArray();
    }

    /**
     * Get contact info for an actor
     */
    private function getContactInfo(int $actorId): ?array
    {
        $contact = DB::table('contact_information')
            ->where('actor_id', $actorId)
            ->first();

        if (!$contact) {
            return null;
        }

        return [
            '@type' => self::RICO_NS . 'Contact',
            'rico:streetAddress' => $contact->street_address ?? null,
            'rico:postalCode' => $contact->postal_code ?? null,
            'rico:city' => $contact->city ?? null,
            'rico:country' => $contact->country ?? null,
            'rico:telephone' => $contact->telephone ?? null,
            'rico:email' => $contact->email ?? null,
        ];
    }

    /**
     * Get activities for a function
     */
    private function getActivitiesForFunction(int $functionId): array
    {
        return DB::table('ric_activity')
            ->where('function_id', $functionId)
            ->get()
            ->map(fn($act) => [
                '@type' => self::RICO_NS . 'Activity',
                'rico:description' => $act->description ?? null,
            ])
            ->toArray();
    }

    /**
     * Get agents for a function
     */
    private function getAgentsForFunction(int $functionId): array
    {
        return DB::table('relation as r')
            ->join('actor as a', 'r.object_id', '=', 'a.id')
            ->join('actor_i18n as i18n', 'a.id', '=', 'i18n.id')
            ->where('r.subject_id', $functionId)
            ->where('r.type_id', 40) // Performs function
            ->select('a.id', 'i18n.authorized_form_of_name')
            ->get()
            ->map(fn($agent) => [
                '@id' => $this->baseUri . '/actor/' . $agent->id,
                '@type' => self::RICO_NS . 'Agent',
                'rico:name' => $agent->authorized_form_of_name,
            ])
            ->toArray();
    }

    /**
     * Get holdings for a repository
     */
    private function getHoldingsForRepository(int $repositoryId): array
    {
        $holdings = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', 'io.id', '=', 'i18n.id')
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->leftJoin('term as level', 'io.level_of_description_id', '=', 'level.id')
            ->leftJoin('term_i18n as level_i18n', 'level.id', '=', 'level_i18n.id')
            ->where('io.repository_id', $repositoryId)
            ->whereIn('level_i18n.name', ['fonds', 'collection'])
            ->select('io.id', 'slug.slug', 'i18n.title', 'level_i18n.name as level')
            ->limit(100)
            ->get();

        return $holdings->map(fn($h) => [
            '@id' => $this->baseUri . '/informationobject/' . ($h->slug ?? $h->id),
            '@type' => self::RICO_NS . 'RecordSet',
            'rico:name' => $h->title,
        ])->toArray();
    }

    /**
     * Check access restrictions
     */
    private function getAccessRestrictions(string $entityType, int $entityId): array
    {
        // ICIP access restrictions apply only to information objects; other
        // entity types have no access-restriction table today.
        if ($entityType !== 'information_object') {
            return [];
        }
        if (!\Illuminate\Support\Facades\Schema::hasTable('icip_access_restriction')) {
            return [];
        }

        $rows = DB::table('icip_access_restriction')
            ->where('information_object_id', $entityId)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $entry = [
                '@type' => 'rico:AccessRestriction',
                'rico:restriction' => $r->restriction_type,
            ];
            if (!empty($r->custom_restriction_text)) {
                $entry['openric:customText'] = $r->custom_restriction_text;
            }
            if (!empty($r->start_date)) {
                $entry['openric:startDate'] = $r->start_date;
            }
            if (!empty($r->end_date)) {
                $entry['openric:endDate'] = $r->end_date;
            }
            $entry['openric:appliesToDescendants'] = (bool) ($r->applies_to_descendants ?? false);
            $out[] = $entry;
        }
        return $out;
    }

    /**
     * Check if entity contains personal data.
     *
     * Source: personal_data_log is keyed by object.id and contains one row
     * per detected-personal-data event. Entity is flagged if any such row
     * exists. entityType is ignored (all entities share object.id via CTI).
     */
    private function checkPersonalData(string $entityType, int $entityId): bool
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('personal_data_log')) {
            return false;
        }
        return DB::table('personal_data_log')
            ->where('object_id', $entityId)
            ->exists();
    }

    /**
     * Extract ID from URI
     */
    private function extractIdFromUri(string $uri): int
    {
        $parts = explode('/', $uri);
        return (int) end($parts);
    }

    // =========================================================================
    // THING SERIALIZATION (boxes, containers — rico:Thing)
    // =========================================================================

    /**
     * Serialize a ric_thing (box/container) to RIC-O JSON-LD.
     */
    public function serializeThing(int $thingId, array $options = []): array
    {
        $culture = $options['culture'] ?? 'en';

        $thing = DB::table('ric_thing as rt')
            ->leftJoin('ric_thing_i18n as rti', function ($j) use ($culture) {
                $j->on('rt.id', '=', 'rti.id')->where('rti.culture', '=', $culture);
            })
            ->leftJoin('physical_object_extended as poe', 'rt.physical_object_id', '=', 'poe.physical_object_id')
            ->where('rt.id', $thingId)
            ->select([
                'rt.*',
                'rti.name', 'rti.description', 'rti.condition_note',
                'poe.barcode', 'poe.building', 'poe.floor', 'poe.room',
                'poe.aisle', 'poe.bay', 'poe.rack', 'poe.shelf', 'poe.position',
                'poe.total_capacity', 'poe.used_capacity', 'poe.capacity_unit',
                'poe.width', 'poe.height', 'poe.depth',
                'poe.climate_controlled', 'poe.security_level as ext_security_level',
            ])
            ->first();

        if (!$thing) {
            return ['error' => 'Thing not found'];
        }

        $record = [
            '@context' => [
                'rico' => self::RICO_NS,
                'rdf' => self::RDF_NS,
                'rdfs' => self::RDFS_NS,
                'xsd' => self::XSD_NS,
            ],
            '@id' => $this->baseUri . '/thing/' . $thingId,
            '@type' => self::RICO_NS . 'Thing',
            'rico:type' => $thing->type_id ?? 'box',
        ];

        if (!empty($thing->name)) {
            $record['rico:name'] = $thing->name;
        }
        if (!empty($thing->identifier)) {
            $record['rico:identifier'] = $thing->identifier;
        }
        if (!empty($thing->description)) {
            $record['rico:description'] = $thing->description;
        }
        if (!empty($thing->barcode)) {
            $record['rico:identifier'] = [
                ['@type' => 'rico:Identifier', 'rico:identifierType' => 'barcode', 'rico:textualValue' => $thing->barcode],
            ];
        }

        // Physical dimensions
        $dimensions = array_filter([
            'width' => $thing->width ?? null,
            'height' => $thing->height ?? null,
            'depth' => $thing->depth ?? null,
        ]);
        if (!empty($dimensions)) {
            $record['rico:physicalCharacteristics'] = $dimensions;
        }

        // Capacity
        if ($thing->total_capacity) {
            $record['rico:extent'] = [
                'rico:totalCapacity' => (int) $thing->total_capacity,
                'rico:usedCapacity' => (int) ($thing->used_capacity ?? 0),
                'rico:unit' => $thing->capacity_unit ?? 'items',
            ];
        }

        // Current location (from ric_thing_location)
        $currentLocation = DB::table('ric_thing_location as rtl')
            ->join('ric_place_i18n as rpi', function ($j) use ($culture) {
                $j->on('rtl.ric_place_id', '=', 'rpi.id')->where('rpi.culture', '=', $culture);
            })
            ->where('rtl.ric_thing_id', $thingId)
            ->where('rtl.is_current', 1)
            ->select('rtl.ric_place_id', 'rpi.name as place_name', 'rtl.start_date')
            ->first();

        if ($currentLocation) {
            $record['rico:hasOrHadLocation'] = [
                '@id' => $this->baseUri . '/place/' . $currentLocation->ric_place_id,
                '@type' => self::RICO_NS . 'Place',
                'rico:placeName' => $currentLocation->place_name,
            ];
        } elseif ($thing->building || $thing->room) {
            // Fallback to physical_object_extended location
            $locationParts = array_filter([
                $thing->building, $thing->floor ? 'Floor ' . $thing->floor : null,
                $thing->room ? 'Room ' . $thing->room : null,
                $thing->aisle ? 'Aisle ' . $thing->aisle : null,
                $thing->bay ? 'Bay ' . $thing->bay : null,
                $thing->rack ? 'Rack ' . $thing->rack : null,
                $thing->shelf ? 'Shelf ' . $thing->shelf : null,
            ]);
            if (!empty($locationParts)) {
                $record['rico:hasOrHadLocation'] = [
                    '@type' => self::RICO_NS . 'Place',
                    'rico:placeName' => implode(' > ', $locationParts),
                ];
            }
        }

        // Contained instantiations
        $instantiations = DB::table('ric_thing_instantiation as rti2')
            ->join('ric_instantiation as ri', 'rti2.ric_instantiation_id', '=', 'ri.id')
            ->leftJoin('ric_instantiation_i18n as rii', function ($j) use ($culture) {
                $j->on('ri.id', '=', 'rii.id')->where('rii.culture', '=', $culture);
            })
            ->where('rti2.ric_thing_id', $thingId)
            ->select('ri.id', 'ri.record_id', 'rii.title', 'rti2.sequence_number')
            ->orderBy('rti2.sequence_number')
            ->get();

        if ($instantiations->isNotEmpty()) {
            $record['rico:contains'] = $instantiations->map(fn($inst) => [
                '@id' => $this->baseUri . '/instantiation/' . $inst->id,
                '@type' => self::RICO_NS . 'Instantiation',
                'rico:title' => $inst->title,
                'rico:isInstantiationOf' => $inst->record_id ? $this->baseUri . '/informationobject/' . $inst->record_id : null,
            ])->toArray();
        }

        // Parent container
        if ($thing->parent_id) {
            $record['rico:isContainedIn'] = [
                '@id' => $this->baseUri . '/thing/' . $thing->parent_id,
                '@type' => self::RICO_NS . 'Thing',
            ];
        }

        // Environment
        if ($thing->climate_controlled) {
            $record['rico:environmentalConditions'] = ['climateControlled' => true];
        }
        if ($thing->condition_note) {
            $record['rico:conditionNote'] = $thing->condition_note;
        }

        $record['rico:status'] = $thing->status ?? 'active';

        return $record;
    }

    // =========================================================================
    //  #77 phase 3: JSON-LD -> turtle converter
    // =========================================================================
    //
    // Replaces the FusekiSyncService::buildEntityTurtle stub (rico:type +
    // rdfs:label only) with a full RIC-O turtle emission backed by the
    // serialize{Place,Rule,Activity,Instantiation} methods above. The
    // Heratio JSON-LD shape is restricted enough that a focused converter
    // is small + correct - no need to pull in a full RDF library.
    //
    // Supported shapes (the only ones serialize* methods produce):
    //   - @context: prefix map -> @prefix directives
    //   - @id: subject URI -> <URI>
    //   - @type: rdf:type -> <subject> a <type> .
    //   - scalar values (string/int/float/bool) -> typed literals
    //   - nested objects with @id + @type -> two-line block (subject -> nested-id ; nested-id -> nested fields)
    //   - arrays of scalars -> repeated triples with the same predicate

    /**
     * Convert a JSON-LD array (as returned by serialize*) to a turtle
     * body suitable for `INSERT DATA { GRAPH <uri> { ... } }`. Returns
     * an empty string when the input lacks @id or has an `error` key.
     */
    public static function toTurtle(array $jsonLd): string
    {
        if (isset($jsonLd['error']) || empty($jsonLd['@id'])) {
            return '';
        }

        $prefixes = $jsonLd['@context'] ?? [];
        $subject = '<' . $jsonLd['@id'] . '>';
        $lines = [];

        // @prefix directives. Stable order so diffs are quiet.
        $declared = [];
        foreach ($prefixes as $alias => $uri) {
            if (!is_string($uri)) continue;
            $declared[$alias] = $uri;
        }
        // Common openric extension uses a default namespace - declare lazily
        // when we actually emit a property in it.
        $declared['openric'] = $declared['openric'] ?? 'http://openric.theahg.co.za/ns/v0.2/openric#';
        ksort($declared);
        foreach ($declared as $alias => $uri) {
            $lines[] = '@prefix ' . $alias . ': <' . $uri . '> .';
        }
        $lines[] = '';

        // Body. For each property emit `subject predicate object .` Keep
        // the entity self-contained per turtle file - nested objects that
        // carry an @id get inlined as a separate statement block.
        $extraStatements = [];
        $parts = [];
        foreach ($jsonLd as $key => $value) {
            if (str_starts_with($key, '@')) continue;
            $emitted = self::emitProperty($key, $value, $extraStatements);
            if ($emitted !== null) {
                $parts[] = $emitted;
            }
        }

        // rdf:type comes from @type
        $type = $jsonLd['@type'] ?? null;
        if ($type) {
            array_unshift($parts, 'a ' . self::predicateOrUri((string) $type));
        }

        if (empty($parts)) {
            // Nothing to emit (no type, no properties) - bail out so we don't
            // produce a half-empty triple.
            return '';
        }

        $lines[] = $subject;
        foreach ($parts as $i => $p) {
            $suffix = ($i === count($parts) - 1) ? ' .' : ' ;';
            $lines[] = '    ' . $p . $suffix;
        }

        // Inline nested-entity statements (one block per nested @id seen).
        foreach ($extraStatements as $block) {
            $lines[] = '';
            $lines[] = $block;
        }

        return implode("\n", $lines);
    }

    /**
     * Emit a single property (predicate + object pair) for the toTurtle
     * body. Returns null when the value is null/empty so the caller can
     * skip it without leaving a dangling semicolon.
     *
     * @param array<int,string> $extraStatements appended-to ref for nested-entity blocks
     */
    private static function emitProperty(string $key, $value, array &$extraStatements): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        $predicate = self::predicateOrUri($key);

        // Array of scalars / array of objects -> repeat predicate
        if (is_array($value) && !self::isAssoc($value)) {
            $objects = [];
            foreach ($value as $v) {
                $obj = self::emitObject($v, $extraStatements);
                if ($obj !== null) $objects[] = $obj;
            }
            if (empty($objects)) return null;
            return $predicate . ' ' . implode(', ', $objects);
        }

        // Single object (associative array with @id, or scalar)
        $obj = self::emitObject($value, $extraStatements);
        return $obj === null ? null : ($predicate . ' ' . $obj);
    }

    /**
     * Render a single object (literal, URI ref, or nested entity) for the
     * predicate-object slot. Nested entities with @id are rendered as a
     * URI reference here AND added to $extraStatements so their own type
     * + label appear as a separate block in the same turtle body.
     */
    private static function emitObject($value, array &$extraStatements): ?string
    {
        if ($value === null || $value === '') return null;

        if (is_bool($value)) {
            return $value ? '"true"^^xsd:boolean' : '"false"^^xsd:boolean';
        }
        if (is_int($value)) {
            return '"' . $value . '"^^xsd:integer';
        }
        if (is_float($value)) {
            return '"' . $value . '"^^xsd:decimal';
        }
        if (is_string($value)) {
            // Heuristic: a value that looks like an absolute URI -> URI ref;
            // otherwise treat as a literal. owl:sameAs / @id values come in
            // as strings here when they're not nested objects.
            if (preg_match('#^https?://|^urn:#i', $value)) {
                return '<' . $value . '>';
            }
            return self::escapeLiteral($value);
        }
        if (is_array($value) && self::isAssoc($value)) {
            // Nested entity: emit as URI reference in the parent triple +
            // append a separate block of statements about the nested URI.
            if (!empty($value['@id'])) {
                $nestedSubj = '<' . $value['@id'] . '>';
                $nestedLines = [];
                foreach ($value as $k => $v) {
                    if (str_starts_with($k, '@')) continue;
                    $nestedExtras = []; // nested entities don't recurse further
                    $emitted = self::emitProperty($k, $v, $nestedExtras);
                    if ($emitted !== null) $nestedLines[] = '    ' . $emitted;
                }
                if (!empty($value['@type'])) {
                    array_unshift($nestedLines, '    a ' . self::predicateOrUri((string) $value['@type']));
                }
                if (!empty($nestedLines)) {
                    $body = implode(" ;\n", $nestedLines) . ' .';
                    $extraStatements[] = $nestedSubj . "\n" . $body;
                }
                return $nestedSubj;
            }
            // Anonymous nested object - flatten as a literal "key=value" string
            // (no blank-node syntax to avoid gettings tangled with INSERT DATA).
            return self::escapeLiteral(json_encode($value));
        }

        return null;
    }

    /**
     * Render a key as a turtle predicate. Already-prefixed values like
     * `rico:name` pass through; absolute URIs get angle-bracketed.
     */
    private static function predicateOrUri(string $key): string
    {
        if (preg_match('/^[a-z][a-z0-9_]*:[A-Za-z][\w\-]*$/i', $key)) {
            return $key; // already prefixed (e.g. rico:name)
        }
        if (preg_match('#^https?://|^urn:#i', $key)) {
            return '<' . $key . '>';
        }
        // Bare key (rare in our shape) - treat as openric:key by default
        return 'openric:' . $key;
    }

    private static function escapeLiteral(string $value): string
    {
        // Multi-line strings use turtle's """...""" form; escape inner triple
        // quotes by collapsing them. Single-line values use the cheaper "..."
        // form with backslash-escapes for backslash + double-quote.
        if (str_contains($value, "\n") || str_contains($value, "\r")) {
            $body = str_replace(['\\', '"""'], ['\\\\', '\\"\\"\\"'], $value);
            return '"""' . $body . '"""';
        }
        $body = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
        return '"' . $body . '"';
    }

    private static function isAssoc(array $arr): bool
    {
        if ($arr === []) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
