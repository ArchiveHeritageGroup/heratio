<?php

/**
 * RicSerializationService - RIC-O JSON-LD serialization
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use AhgCore\Services\SettingHelper;

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

    // Event type to RIC mapping
    private array $eventTypeToRic = [
        'creation' => 'Production',
        'accumulation' => 'Accumulation',
        'contribution' => 'Production',
        'collection' => 'Accumulation',
        'custody' => 'Activity',
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
                self::RICO_NS => self::RICO_NS,
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
        $actor = DB::table('actor as a')
            ->leftJoin('actor_i18n as i18n', 'a.id', '=', 'i18n.id')
            ->where('a.id', $actorId)
            ->first();

        if (!$actor) {
            return ['error' => 'Actor not found'];
        }

        $ricType = $this->actorTypeToRic[$actor->actor_type_id] ?? 'Agent';

        $agent = [
            '@context' => [
                self::RICO_NS => self::RICO_NS,
                ' rico' => self::RICO_NS,
            ],
            '@id' => $this->baseUri . '/actor/' . $actor->id,
            '@type' => self::RICO_NS . $ricType,
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
        $function = DB::table('function as f')
            ->leftJoin('function_i18n as i18n', 'f.id', '=', 'i18n.id')
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
        $repo = DB::table('actor as a')
            ->leftJoin('actor_i18n as i18n', 'a.id', '=', 'i18n.id')
            ->leftJoin('repository_i18n as repo_i18n', 'a.id', '=', 'repo_i18n.id')
            ->where('a.id', $repositoryId)
            ->first();

        if (!$repo) {
            return ['error' => 'Repository not found'];
        }

        $ricRepo = [
            '@context' => [self::RICO_NS => self::RICO_NS],
            '@id' => $this->baseUri . '/repository/' . $repo->id,
            '@type' => self::RICO_NS . 'CorporateBody',
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
     * Serialize with ISCAP compliance (Security/Access)
     */
    public function addIscapCompliance(array $ricEntity, int $entityId, string $entityType): array
    {
        // Security Classification
        $security = DB::table('security_level as sl')
            ->join('security_level_i18n as sl_i18n', 'sl.id', '=', 'sl_i18n.id')
            ->where('sl.id', function ($query) use ($entityType, $entityId) {
                $query->select('security_level_id')
                    ->from($entityType)
                    ->where('id', $entityId);
            })
            ->first();

        if ($security) {
            $ricEntity['rico:hasSecurityClassification'] = [
                '@type' => self::RICO_NS . 'SecurityClassification',
                'rico:securityLevel' => $security->name,
                'rico:securityLevelCode' => $security->code,
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
        return DB::table('language')
            ->where('information_object_id', $ioId)
            ->pluck('language')
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
            ->leftJoin('actor_i18n as i18n', 'r.id', '=', 'i18n.id')
            ->join('information_object', 'information_object.repository_id', '=', 'r.id')
            ->where('information_object.id', $ioId)
            ->select('r.*', 'i18n.authorized_form_of_name')
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
            ->where('otr.type_id', 33) // Subject
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
        return DB::table('relation as r')
            ->join('actor as a', 'r.object_id', '=', 'a.id')
            ->join('actor_i18n as i18n', 'a.id', '=', 'i18n.id')
            ->where('r.subject_id', $ioId)
            ->where('r.type_id', 36) // Creator
            ->select('a.id', 'i18n.authorized_form_of_name', 'a.actor_type_id')
            ->get()
            ->map(fn($actor) => [
                '@id' => $this->baseUri . '/actor/' . $actor->id,
                '@type' => self::RICO_NS . ($this->actorTypeToRic[$actor->actor_type_id] ?? 'Agent'),
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
            ->where('do.information_object_id', $ioId)
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
            ->where('io.parent_id', $parentId)
            ->select('io.id', 'io.slug', 'i18n.title', 'i18n.identifier')
            ->get()
            ->map(fn($child) => [
                '@id' => $this->baseUri . '/informationobject/' . $child->slug,
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
        return DB::table('place')
            ->where('actor_id', $actorId)
            ->get()
            ->map(fn($place) => [
                '@type' => self::RICO_NS . 'Place',
                'rico:placeName' => $place->name,
            ])
            ->toArray();
    }

    /**
     * Get mandates for an actor
     */
    private function getMandatesForActor(int $actorId): array
    {
        return DB::table('mandate')
            ->where('actor_id', $actorId)
            ->get()
            ->map(fn($mandate) => [
                '@type' => self::RICO_NS . 'Mandate',
                'rico:description' => $mandate->description ?? null,
            ])
            ->toArray();
    }

    /**
     * Get functions for an actor
     */
    private function getFunctionsForActor(int $actorId): array
    {
        return DB::table('relation as r')
            ->join('function as f', 'r.object_id', '=', 'f.id')
            ->join('function_i18n as fi', 'f.id', '=', 'fi.id')
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
        return DB::table('function_activity')
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
            ->leftJoin('term as level', 'io.level_of_description_id', '=', 'level.id')
            ->leftJoin('term_i18n as level_i18n', 'level.id', '=', 'level_i18n.id')
            ->where('io.repository_id', $repositoryId)
            ->whereIn('level_i18n.name', ['fonds', 'collection'])
            ->select('io.id', 'io.slug', 'i18n.title', 'level_i18n.name as level')
            ->limit(100)
            ->get();

        return $holdings->map(fn($h) => [
            '@id' => $this->baseUri . '/informationobject/' . $h->slug,
            '@type' => self::RICO_NS . 'RecordSet',
            'rico:name' => $h->title,
        ])->toArray();
    }

    /**
     * Check access restrictions
     */
    private function getAccessRestrictions(string $entityType, int $entityId): array
    {
        $restrictions = DB::table('access_log')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('restriction_type', '!=', null)
            ->get();

        return $restrictions->map(fn($r) => [
            '@type' => self::RICO_NS . 'AccessRestriction',
            'rico:restriction' => $r->restriction_type,
            'rico:reason' => $r->reason ?? null,
        ])->toArray();
    }

    /**
     * Check if entity contains personal data
     */
    private function checkPersonalData(string $entityType, int $entityId): bool
    {
        return DB::table('personal_data_log')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
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
}
