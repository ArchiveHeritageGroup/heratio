<?php

/**
 * BibframeSerializer — BIBFRAME 2.0 RDF/XML serializer.
 *
 * Maps Heratio information object fields to the Library of Congress BIBFRAME 2.0
 * vocabulary (https://id.loc.gov/ontologies/bibframe.html). Each serialised
 * record produces a minimal-graph BIBFRAME CHO (Cultural Heritage Object)
 * using bf:Instance / bf:Item / bf:Work / bf:Agent and associated properties.
 *
 * Serialization shape mirrors CrmSerializer + ModsSerializer — the same
 * InformationObjectFetcher trait is re-used so field coverage is complete.
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

namespace AhgBiblioBf\Services;

use AhgMetadataExport\Services\Exporters\InformationObjectFetcher;
use Illuminate\Support\Facades\DB;

class BibframeSerializer
{
    use InformationObjectFetcher;

    /** BIBFRAME 2.0 namespace URI. */
    public const NS_BF = 'http://id.loc.gov/ontologies/bibframe/';

    /** BF Elements namespace (used for local properties). */
    public const NS_BFE = 'http://id.loc.gov/ontologies/bfe/';

    /** RDF namespace. */
    public const NS_RDF = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';

    /** RDFS namespace. */
    public const NS_RDFS = 'http://www.w3.org/2000/01/rdf-schema#';

    /** MARC Relators code -> label map (subset used in Heratio event types). */
    private const MARC_RELATOR_MAP = [
        'cre' => 'creator',
        'ctb' => 'contributor',
        'edt' => 'editor',
        'ill' => 'illustrator',
        'trl' => 'translator',
        'pht' => 'photographer',
        'pro' => 'producer',
        'dsr' => 'designer',
        'com' => 'composer',
        'arr' => 'arranger',
        'aut' => 'author',
        'cmp' => 'composer',
        'nrt' => 'narrator',
    ];

    /**
     * Entity-type ID -> BIBFRAME agent type string.
     * AtoM entity_type_id mappings (taken from ModsSerializer analysis):
     *   132 = Person, 130 = Family, 131 = Corporate body.
     */
    private const ENTITY_TYPE_MAP = [
        132 => 'Person',
        130 => 'Family',
        131 => 'CorporateBody',
    ];

    /**
     * Serialise one information object as a BIBFRAME 2.0 RDF/XML document.
     *
     * Graph structure (minimal, following LoC BIBFRAMEPrimer guidance):
     *   @prefix declarations
     *   <$workUri> a bf:Work ;
     *     bf:title <$title> ;
     *     bf:contributor|bf:creator <$agentUri> ;
     *     bf:subject <$agentUri|$conceptUri> ;
     *     bf:language <$langUri> ;
     *     bf:classification <$classUri> .
     *   <$instanceUri> a bf:Instance ;
     *     bf:instanceOf <$workUri> ;
     *     bf:title <$title> ;
     *     bf:carrier|bf:media <$carUri> ;
     *     bf:originActivity <$activityUri> .
     *   <$itemUri> a bf:Item ;
     *     bf:itemOf <$instanceUri> ;
     *     bf:shelfMark <$shelfMark> .
     *
     * Returns empty string on miss — caller (controller, OAI-PMH, bulk export)
     * decides what to do with a blank response.
     */
    public function serializeRecord(int $objectId, string $culture = 'en'): string
    {
        $io = $this->fetchIo($objectId, $culture);
        if (! $io) {
            return '';
        }

        $uri = $this->ioPublicUrl($io);
        $workUri = $uri.'/work';
        $instanceUri = $uri.'/instance';
        $itemUri = $uri.'/item';

        $events = $this->fetchEvents($io, $culture);
        $creators = $this->fetchCreators($io, $culture);
        $subjects = $this->fetchAccessPoints($io, 35, $culture);  // taxonomy 35 = Subject
        $genres = $this->fetchAccessPoints($io, 78, $culture);   // taxonomy 78 = Genre
        $places = $this->fetchAccessPoints($io, 42, $culture);  // taxonomy 42 = Place
        $languages = $this->fetchLanguages($io, $culture);

        $xml = $this->xmlHeader();
        $xml .= $this->prefixDeclarations();

        // ----------------------------------------------------------------
        // bf:Work
        // ----------------------------------------------------------------
        $xml .= "<{$workUri}> a bf:Work .\n";
        $xml .= $this->emitTitleStatement($workUri, (string) $io->title);

        // Creators — from event type 111 / entity_type_id 132/130/131
        foreach ($creators as $creator) {
            $agentUri = $this->agentUri((int) $creator->actor_id, (int) ($creator->entity_type_id ?? 0));
            $xml .= "{$workUri} bf:contributor <{$agentUri}> .\n";
            if ($agentUri !== null) {
                $xml .= $this->emitAgent($agentUri, (int) ($creator->entity_type_id ?? 0), (string) $creator->name);
            }
        }

        // Additional event roles (edt, ctb, etc.) from non-111 events
        foreach ($events as $event) {
            $role = $this->mapEventTypeToMarcRelator((int) $event->type_id);
            if ($role && (int) $event->type_id !== 111 && ! empty($event->actor_id)) {
                $actorRow = $this->fetchActor($event->actor_id, $culture);
                if ($actorRow) {
                    $agentUri = $this->agentUri((int) $event->actor_id, (int) ($actorRow->entity_type_id ?? 132));
                    $xml .= "{$workUri} bf:contributor <{$agentUri}> .\n";
                    $xml .= $this->emitAgent($agentUri, (int) ($actorRow->entity_type_id ?? 132), (string) $actorRow->name);
                }
            }
        }

        // Subjects
        foreach ($subjects as $subject) {
            $conceptUri = $uri.'/subject/'.urlencode($subject->name);
            $xml .= "{$conceptUri} a bf:Topic .\n";
            $xml .= "{$conceptUri} rdfs:label \"{$this->esc($subject->name)}\" .\n";
            $xml .= "{$workUri} bf:subject <{$conceptUri}> .\n";
        }

        // Genre / form
        foreach ($genres as $genre) {
            $conceptUri = $uri.'/topic/'.urlencode($genre->name);
            $xml .= "{$conceptUri} a bf:Topic .\n";
            $xml .= "{$conceptUri} rdfs:label \"{$this->esc($genre->name)}\" .\n";
            $xml .= "{$workUri} bf:genre <{$conceptUri}> .\n";
        }

        // Language
        foreach ($languages as $lang) {
            $langUri = $this->langUri($lang->name);
            $xml .= "{$workUri} bf:language <{$langUri}> .\n";
        }

        // Extent — from extent_and_medium (RDA carrier mapped via carrierType term)
        if (! empty($io->extent_and_medium)) {
            $xml .= "{$workUri} bf:extent <<rdfs:label \"{$this->esc($io->extent_and_medium)}\">> .\n";
        }

        // Content description (scope_and_content -> bf:summary)
        if (! empty($io->scope_and_content)) {
            $summaryUri = $uri.'/summary';
            $xml .= "{$summaryUri} a bf:Summary .\n";
            $xml .= "{$summaryUri} rdfs:label \"{$this->esc(strip_tags($io->scope_and_content))}\" .\n";
            $xml .= "{$workUri} bf:summary <{$summaryUri}> .\n";
        }

        // ----------------------------------------------------------------
        // bf:Instance
        // ----------------------------------------------------------------
        $xml .= "\n<{$instanceUri}> a bf:Instance .\n";
        $xml .= "{$instanceUri} bf:instanceOf <{$workUri}> .\n";
        $xml .= $this->emitTitleStatement($instanceUri, (string) $io->title);

        // Carrier — RDA carrier type term from extent_and_medium (best-effort)
        $carrierUri = $this->carrierUriFromExtent((string) ($io->extent_and_medium ?? ''));
        if ($carrierUri !== null) {
            $xml .= "{$instanceUri} bf:carrier <{$carrierUri}> .\n";
        }

        // Origin activity — publication / creation events
        $pubEvents = array_filter($events->all(), fn ($e) => (int) $e->type_id === 114);
        foreach ($pubEvents as $pub) {
            $actUri = $uri.'/activity/'.$pub->id;
            $xml .= "{$actUri} a bf:Activity .\n";
            if (! empty($pub->start_date)) {
                $xml .= "{$actUri} bf:date \"{$this->esc($pub->start_date)}\" .\n";
            }
            $actor = null;
            if (! empty($pub->actor_id)) {
                $actor = $this->fetchActor((int) $pub->actor_id, $culture);
            }
            if ($actor) {
                $agentUri = $this->agentUri((int) $pub->actor_id, (int) ($actor->entity_type_id ?? 132));
                $xml .= "{$actUri} bf:agent <{$agentUri}> .\n";
                $xml .= $this->emitAgent($agentUri, (int) ($actor->entity_type_id ?? 132), (string) $actor->name);
            }
            $xml .= "{$instanceUri} bf:originActivity <{$actUri}> .\n";
        }

        // Identifier — institutional identifier (io.identifier)
        if (! empty($io->identifier)) {
            $xml .= "{$instanceUri} bf:identifiedBy << a bf:Local ; bf:source << a bf:Source ; rdfs:label \"{$this->esc($io->identifier)}\">>>> .\n";
        }

        // Access conditions -> bf:usage
        if (! empty($io->access_conditions)) {
            $xml .= "{$instanceUri} bf:usage << a bf:UsageNote ; rdfs:label \"{$this->esc($io->access_conditions)}\">> .\n";
        }

        // ----------------------------------------------------------------
        // bf:Item
        // ----------------------------------------------------------------
        $xml .= "\n<{$itemUri}> a bf:Item .\n";
        $xml .= "{$itemUri} bf:itemOf <{$instanceUri}> .\n";
        if (! empty($io->slug)) {
            $xml .= "{$itemUri} bf:shelfMark \"{$this->esc($io->slug)}\" .\n";
        }

        return $xml;
    }

    /**
     * Return the format key used by the export controller / serializer registry.
     */
    public function getFormat(): string
    {
        return 'bibframe';
    }

    public function getFormatName(): string
    {
        return 'BIBFRAME 2.0 (RDF/Turtle)';
    }

    public function getSchemaUrl(): string
    {
        return 'https://id.loc.gov/ontologies/bibframe.html';
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /** RDF/XML document header with encoding declaration. */
    private function xmlHeader(): string
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    }

    /** Turtle prefix block — shared across Work / Instance / Item graphs. */
    private function prefixDeclarations(): string
    {
        return $this->indent(
            "@prefix bf: <" . self::NS_BF . "> .\n" .
            "@prefix bfe: <" . self::NS_BFE . "> .\n" .
            "@prefix rdf: <" . self::NS_RDF . "> .\n" .
            "@prefix rdfs: <" . self::NS_RDFS . "> .\n" .
            "@prefix skos: <http://www.w3.org/2004/02/skos/core#> .\n\n"
        );
    }

    /**
     * Emit a bf:title statement for a resource URI.
     * Uses the bf:Title blank-node pattern (LoC recommended way) when
     * a non-blank title URI is available; otherwise a direct rdfs:label.
     */
    private function emitTitleStatement(string $subjectUri, string $title): string
    {
        if (trim($title) === '') {
            return '';
        }

        return "{$subjectUri} bf:title << a bf:Title ; rdfs:label \"{$this->esc($title)}\" >> .\n";
    }

    /**
     * Emit the bf:Agent block for a uri/actor combination.
     * Skipped when agentUri is null (no actor_id, subject purely textual).
     */
    private function emitAgent(string $agentUri, int $entityTypeId, string $name): string
    {
        $bfType = self::ENTITY_TYPE_MAP[$entityTypeId] ?? 'Person';

        return "\n<{$agentUri}> a bf:{$bfType} .\n" .
               "<{$agentUri}> rdfs:label \"{$this->esc($name)}\" .\n";
    }

    /**
     * Build agent URI from actor id. Returns null when $actorId is null/0.
     * Uses the same URL convention as CrmSerializer: /actor/{id}.
     */
    private function agentUri(?int $actorId, int $entityTypeId): ?string
    {
        if ($actorId === null || $actorId <= 0) {
            return null;
        }

        // Separate namespaces per entity type so SPARQL can filter cleanly.
        $typeKey = strtolower(self::ENTITY_TYPE_MAP[$entityTypeId] ?? 'person');

        return config('app.url', 'http://localhost') . "/actor/{$actorId}#{$typeKey}";
    }

    /** Language URI — use ISO 639-1 code via loc.gov. */
    private function langUri(string $langName): string
    {
        static $map = [
            'English' => 'en', 'Afrikaans' => 'af', 'Zulu' => 'zu',
            'Xhosa' => 'xh', 'Sotho' => 'st', 'Tswana' => 'tn',
            'Portuguese' => 'pt', 'German' => 'de', 'French' => 'fr',
            'Spanish' => 'es', 'Dutch' => 'nl', 'Italian' => 'it',
        ];
        $code = $map[$langName] ?? 'en';

        return "https://id.loc.gov/vocabulary/languages/{$code}";
    }

    /**
     * Best-effort RDA carrier type URI from extent_and_medium text.
     * Maps common keywords to loc.gov concept URIs. Returns null when no
     * keyword recognised so the instance still serialises fully.
     */
    private function carrierUriFromExtent(string $extent): ?string
    {
        $extent = strtolower($extent);
        $mappings = [
            'online resource' => 'https://id.loc.gov/vocabulary/mcarriertypes/online',
            'electronic' => 'https://id.loc.gov/vocabulary/mcarriertypes/online',
            'computer' => 'https://id.loc.gov/vocabulary/mcarriertypes/online',
            'paper' => 'https://id.loc.gov/vocabulary/mcarriertypes/papers',
            'bound' => 'https://id.loc.gov/vocabulary/mcarriertypes/papers',
            'microfilm' => 'https://id.loc.gov/vocabulary/mcarriertypes/microfilm',
            'microfiche' => 'https://id.loc.gov/vocabulary/mcarriertypes/microfiche',
            'video' => 'https://id.loc.gov/vocabulary/mcarriertypes/video',
            'audio' => 'https://id.loc.gov/vocabulary/mcarriertypes/audio',
            'photograph' => 'https://id.loc.gov/vocabulary/mcarriertypes/photograph',
            'map' => 'https://id.loc.gov/vocabulary/mcarriertypes/map',
            'globe' => 'https://id.loc.gov/vocabulary/mcarriertypes/globe',
        ];
        foreach ($mappings as $token => $uri) {
            if (str_contains($extent, $token)) {
                return $uri;
            }
        }

        return null;
    }

    /**
     * Map a Heratio event type ID to a MARC Relator code string.
     * 111 = Creation, 113 = Contribution, 114 = Publication, 116 = Production,
     * 117 = Performance, 118 = Style/Technique.
     * Others: 248 = Photograph, 250 = Recording, 251 = Film production.
     */
    private function mapEventTypeToMarcRelator(int $typeId): ?string
    {
        return match ($typeId) {
            111 => 'cre',
            113 => 'ctb',
            114 => 'pbl',
            248 => 'pht',
            250 => 'prd',
            251 => 'pro',
            252 => 'ctb',
            default => null,
        };
    }

    /** Fetch a single actor row by id, with entity type. Returns null on miss. */
    private function fetchActor(int $actorId, string $culture): ?object
    {
        return DB::table('actor')
            ->join('actor_i18n', 'actor.id', '=', 'actor_i18n.id')
            ->where('actor.id', $actorId)
            ->where('actor_i18n.culture', $culture)
            ->select('actor.id', 'actor.entity_type_id', 'actor_i18n.authorized_form_of_name as name')
            ->first();
    }

    /** Return the public URL for an information object. Mirrors CrmSerializer convention. */
    private function ioPublicUrl(object $io): string
    {
        $base = rtrim(config('app.url', 'http://localhost'), '/');

        return ! empty($io->slug)
            ? "{$base}/".$io->slug
            : "{$base}/informationobject/{$io->id}";
    }

    /** Indent a multiline string by 2 spaces. */
    private function indent(string $s): string
    {
        $lines = explode("\n", $s);
        foreach ($lines as &$line) {
            if ($line !== '') {
                $line = '  ' . $line;
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /** Escape a string for Turtle — backslash-escape quotes and backslashes. */
    private function esc(string $s): string
    {
        return str_replace(['\\', '"', "\n", "\r", "\t"], ['\\\\', '\\"', '\\n', '\\r', '\\t'], $s);
    }
}
