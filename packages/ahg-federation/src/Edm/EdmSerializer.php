<?php

/**
 * EdmSerializer - Europeana Data Model (EDM) RDF/XML serializer.
 *
 * Produces a single EDM document for one Heratio Information Object,
 * shaped per https://pro.europeana.eu/page/edm-documentation. Each
 * record carries the four canonical EDM classes that Europeana's
 * ingestion pipeline requires:
 *
 *   - edm:ProvidedCHO        - the cultural-heritage object itself
 *   - ore:Aggregation        - the providing-aggregation wrapper
 *   - edm:WebResource        - one per digital surrogate (access copy)
 *   - edm:Agent / edm:Place /
 *     edm:TimeSpan           - contextual entities referenced by URI
 *
 * The serializer is read-only and stateless; persistence is handled by
 * the EuropeanaExportService alongside it. The output is canonical
 * RDF/XML 1.1, UTF-8, no DOCTYPE.
 *
 * Phase 4 of #670 (Federation audit, Europeana EDM publish).
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

namespace AhgFederation\Edm;

use Illuminate\Support\Facades\DB;

class EdmSerializer
{
    public const NS_RDF = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
    public const NS_EDM = 'http://www.europeana.eu/schemas/edm/';
    public const NS_ORE = 'http://www.openarchives.org/ore/terms/';
    public const NS_DC = 'http://purl.org/dc/elements/1.1/';
    public const NS_DCTERMS = 'http://purl.org/dc/terms/';
    public const NS_SKOS = 'http://www.w3.org/2004/02/skos/core#';
    public const NS_FOAF = 'http://xmlns.com/foaf/0.1/';
    public const NS_OWL = 'http://www.w3.org/2002/07/owl#';
    public const NS_WGS84 = 'http://www.w3.org/2003/01/geo/wgs84_pos#';

    /**
     * Europeana's edm:type vocabulary is a fixed 5-bucket set. Anything
     * outside this mapping defaults to TEXT (the catch-all for archival
     * description without a digital surrogate).
     */
    public const EDM_TYPES = ['TEXT', 'IMAGE', 'SOUND', 'VIDEO', '3D'];

    /**
     * Serialise one Information Object into an EDM RDF/XML document.
     *
     * Returns an empty string when the IO is missing or not in the
     * caller-supplied culture. Callers (the export service, smoke tests,
     * future REST endpoint) decide whether empty == skip or fail.
     */
    public function serializeRecord(int $objectId, string $culture = 'en'): string
    {
        $io = $this->fetchIo($objectId, $culture);
        if (! $io) {
            return '';
        }

        $repository = $this->fetchRepository($io, $culture);
        $creators = $this->fetchCreators($io, $culture);
        $events = $this->fetchEvents($io, $culture);
        $subjects = $this->fetchAccessPoints($io, 35, $culture);
        $places = $this->fetchAccessPoints($io, 42, $culture);
        $genres = $this->fetchAccessPoints($io, 78, $culture);
        $languages = $this->fetchLanguages($io, $culture);
        $digitals = $this->fetchDigitalObjects((int) $io->id);
        // #1391 — a record with PII visual-redaction regions must not publish
        // its raw master/reference file URLs (edm:isShownBy / thumbnail) to an
        // external aggregator; drop the derivatives (metadata still exports).
        if (app(\AhgCore\Services\DisclosureGate::class)->hasRedactions((int) $io->id)) {
            $digitals = [];
        }
        $ricPlaces = $this->fetchRicPlaces((int) $io->id, $culture);

        $dataProvider = $this->setting('europeana_data_provider', 'The Archive and Heritage Group');
        $providerCountry = $this->setting('europeana_country', 'South Africa');
        $providerLanguage = $this->setting('europeana_language', $culture);

        $ioUrl = $this->ioPublicUrl($io);
        $cho = $ioUrl.'#cho';
        $agg = $ioUrl.'#aggregation';

        $edmType = $this->deriveEdmType($digitals);
        $rights = $this->deriveRightsUri($io);
        $isShownBy = $this->pickReferenceUrl($digitals);
        $thumbUrl = $this->pickThumbnailUrl($digitals);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<rdf:RDF xmlns:rdf="'.self::NS_RDF.'"';
        $xml .= ' xmlns:edm="'.self::NS_EDM.'"';
        $xml .= ' xmlns:ore="'.self::NS_ORE.'"';
        $xml .= ' xmlns:dc="'.self::NS_DC.'"';
        $xml .= ' xmlns:dcterms="'.self::NS_DCTERMS.'"';
        $xml .= ' xmlns:skos="'.self::NS_SKOS.'"';
        $xml .= ' xmlns:foaf="'.self::NS_FOAF.'"';
        $xml .= ' xmlns:owl="'.self::NS_OWL.'"';
        $xml .= ' xmlns:wgs84_pos="'.self::NS_WGS84.'"';
        $xml .= '>'."\n";

        // -------- edm:ProvidedCHO --------
        $xml .= '  <edm:ProvidedCHO rdf:about="'.$this->escAttr($cho).'">'."\n";
        $xml .= '    <dc:title xml:lang="'.$this->escAttr($culture).'">'.$this->escXml($io->title).'</dc:title>'."\n";

        if (! empty($io->identifier)) {
            $xml .= '    <dc:identifier>'.$this->escXml($io->identifier).'</dc:identifier>'."\n";
        }
        if (! empty($io->slug)) {
            $xml .= '    <dc:identifier>'.$this->escXml($ioUrl).'</dc:identifier>'."\n";
        }
        if (! empty($io->scope_and_content)) {
            $xml .= '    <dc:description xml:lang="'.$this->escAttr($culture).'">'.$this->escXml($io->scope_and_content).'</dc:description>'."\n";
        }

        foreach ($creators as $creator) {
            $agentUri = $this->agentUri((int) ($creator->actor_id ?? 0));
            $xml .= '    <dc:creator rdf:resource="'.$this->escAttr($agentUri).'"/>'."\n";
        }

        foreach ($subjects as $s) {
            $xml .= '    <dc:subject xml:lang="'.$this->escAttr($culture).'">'.$this->escXml($s->name).'</dc:subject>'."\n";
        }
        foreach ($genres as $g) {
            $xml .= '    <dc:type xml:lang="'.$this->escAttr($culture).'">'.$this->escXml($g->name).'</dc:type>'."\n";
        }
        foreach ($languages as $lang) {
            $xml .= '    <dc:language>'.$this->escXml($lang->name).'</dc:language>'."\n";
        }

        foreach ($events as $event) {
            $dateVal = $event->date_display ?: ($event->start_date ?? '');
            if ($dateVal) {
                $xml .= '    <dc:date>'.$this->escXml($dateVal).'</dc:date>'."\n";
            }
        }

        // dcterms:spatial via taxonomy 42 (string labels) +
        // ric_place URIs when available
        foreach ($places as $p) {
            $xml .= '    <dcterms:spatial xml:lang="'.$this->escAttr($culture).'">'.$this->escXml($p->name).'</dcterms:spatial>'."\n";
        }
        foreach ($ricPlaces as $rp) {
            $placeUri = $this->placeUri((int) $rp->id);
            $xml .= '    <dcterms:spatial rdf:resource="'.$this->escAttr($placeUri).'"/>'."\n";
        }

        // edm:type - mandatory, fixed vocabulary
        $xml .= '    <edm:type>'.$this->escXml($edmType).'</edm:type>'."\n";

        // dc:rights / access conditions
        if (! empty($io->reproduction_conditions)) {
            $xml .= '    <dc:rights xml:lang="'.$this->escAttr($culture).'">'.$this->escXml($io->reproduction_conditions).'</dc:rights>'."\n";
        } elseif (! empty($io->access_conditions)) {
            $xml .= '    <dc:rights xml:lang="'.$this->escAttr($culture).'">'.$this->escXml($io->access_conditions).'</dc:rights>'."\n";
        }
        if (! empty($io->extent_and_medium)) {
            $xml .= '    <dc:format xml:lang="'.$this->escAttr($culture).'">'.$this->escXml($io->extent_and_medium).'</dc:format>'."\n";
        }
        if ($repository) {
            $xml .= '    <dc:contributor xml:lang="'.$this->escAttr($culture).'">'.$this->escXml($repository->name).'</dc:contributor>'."\n";
        }

        $xml .= '  </edm:ProvidedCHO>'."\n";

        // -------- ore:Aggregation --------
        $xml .= '  <ore:Aggregation rdf:about="'.$this->escAttr($agg).'">'."\n";
        $xml .= '    <edm:aggregatedCHO rdf:resource="'.$this->escAttr($cho).'"/>'."\n";
        $xml .= '    <edm:dataProvider>'.$this->escXml($dataProvider).'</edm:dataProvider>'."\n";
        $xml .= '    <edm:provider>'.$this->escXml($dataProvider).'</edm:provider>'."\n";
        $xml .= '    <edm:isShownAt rdf:resource="'.$this->escAttr($ioUrl).'"/>'."\n";
        if ($isShownBy) {
            $xml .= '    <edm:isShownBy rdf:resource="'.$this->escAttr($isShownBy).'"/>'."\n";
        }
        if ($thumbUrl) {
            $xml .= '    <edm:object rdf:resource="'.$this->escAttr($thumbUrl).'"/>'."\n";
        }
        $xml .= '    <edm:rights rdf:resource="'.$this->escAttr($rights).'"/>'."\n";
        $xml .= '  </ore:Aggregation>'."\n";

        // -------- edm:WebResource (one per digital surrogate) --------
        foreach ($digitals as $do) {
            $doUrl = $this->digitalObjectUrl($do);
            if (! $doUrl) {
                continue;
            }
            $xml .= '  <edm:WebResource rdf:about="'.$this->escAttr($doUrl).'">'."\n";
            if (! empty($do->mime_type)) {
                $xml .= '    <dc:format>'.$this->escXml($do->mime_type).'</dc:format>'."\n";
            }
            $xml .= '    <edm:rights rdf:resource="'.$this->escAttr($rights).'"/>'."\n";
            $xml .= '  </edm:WebResource>'."\n";
        }

        // -------- edm:Agent (one per creator) --------
        foreach ($creators as $creator) {
            $agentUri = $this->agentUri((int) ($creator->actor_id ?? 0));
            $xml .= '  <edm:Agent rdf:about="'.$this->escAttr($agentUri).'">'."\n";
            $xml .= '    <skos:prefLabel xml:lang="'.$this->escAttr($culture).'">'.$this->escXml($creator->name).'</skos:prefLabel>'."\n";
            $xml .= '  </edm:Agent>'."\n";
        }

        // -------- edm:Place (one per ric_place referenced) --------
        foreach ($ricPlaces as $rp) {
            $placeUri = $this->placeUri((int) $rp->id);
            $xml .= '  <edm:Place rdf:about="'.$this->escAttr($placeUri).'">'."\n";
            if (! empty($rp->name)) {
                $xml .= '    <skos:prefLabel xml:lang="'.$this->escAttr($culture).'">'.$this->escXml($rp->name).'</skos:prefLabel>'."\n";
            }
            if (! is_null($rp->latitude)) {
                $xml .= '    <wgs84_pos:lat>'.$this->escXml((string) $rp->latitude).'</wgs84_pos:lat>'."\n";
            }
            if (! is_null($rp->longitude)) {
                $xml .= '    <wgs84_pos:long>'.$this->escXml((string) $rp->longitude).'</wgs84_pos:long>'."\n";
            }
            $xml .= '  </edm:Place>'."\n";
        }

        // -------- edm:TimeSpan (one per event with begin/end) --------
        foreach ($events as $event) {
            if (empty($event->start_date) && empty($event->end_date)) {
                continue;
            }
            $tsUri = $ioUrl.'#ts-'.((int) ($event->id ?? 0));
            $xml .= '  <edm:TimeSpan rdf:about="'.$this->escAttr($tsUri).'">'."\n";
            if (! empty($event->start_date)) {
                $xml .= '    <edm:begin>'.$this->escXml((string) $event->start_date).'</edm:begin>'."\n";
            }
            if (! empty($event->end_date)) {
                $xml .= '    <edm:end>'.$this->escXml((string) $event->end_date).'</edm:end>'."\n";
            }
            if (! empty($event->date_display)) {
                $xml .= '    <skos:prefLabel xml:lang="'.$this->escAttr($culture).'">'.$this->escXml((string) $event->date_display).'</skos:prefLabel>'."\n";
            }
            $xml .= '  </edm:TimeSpan>'."\n";
        }

        $xml .= '</rdf:RDF>'."\n";

        return $xml;
    }

    /**
     * List published IO ids for the bulk export pass. Mirrors the OAI
     * controller's published-record filter (status type 158, status 160)
     * so Europeana never sees a record we wouldn't disseminate via OAI.
     */
    public function listPublishedRecordIds(?string $sinceIso = null): \Illuminate\Support\Collection
    {
        $q = DB::table('information_object as io')
            ->join('object as o', 'io.id', '=', 'o.id')
            ->join('status as st', function ($j) {
                $j->on('io.id', '=', 'st.object_id')
                    ->where('st.type_id', '=', 158);
            })
            ->where('st.status_id', '=', 160)
            ->where('io.id', '>', 1)
            ->whereNotIn('io.id', app(\AhgCore\Services\DisclosureGate::class)->restrictedIds()) // #1384/#1389 ICIP/TK + ODRL
            ->orderBy('io.id');

        if ($sinceIso) {
            $q->where('o.updated_at', '>=', $sinceIso);
        }

        return $q->pluck('io.id');
    }

    // -----------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------

    protected function ioPublicUrl($io): string
    {
        if (! empty($io->slug)) {
            return rtrim((string) url('/'), '/').'/'.$io->slug;
        }
        return rtrim((string) url('/'), '/').'/informationobject/'.((int) $io->id);
    }

    protected function agentUri(int $actorId): string
    {
        return rtrim((string) url('/'), '/').'/actor/'.$actorId;
    }

    protected function placeUri(int $placeId): string
    {
        return rtrim((string) url('/'), '/').'/ric/place/'.$placeId;
    }

    protected function digitalObjectUrl($do): ?string
    {
        if (empty($do->path) || empty($do->name)) {
            return null;
        }
        $rel = rtrim((string) $do->path, '/').'/'.$do->name;
        // Treat absolute http(s) paths as already-URLs, otherwise resolve
        // via the public uploads mount.
        if (preg_match('#^https?://#i', $rel)) {
            return $rel;
        }
        return rtrim((string) url('/'), '/').'/'.ltrim($rel, '/');
    }

    protected function pickReferenceUrl(array $digitals): ?string
    {
        foreach ($digitals as $do) {
            if ((int) ($do->usage_id ?? 0) === 141) {
                return $this->digitalObjectUrl($do);
            }
        }
        foreach ($digitals as $do) {
            if (is_null($do->parent_id) && ! empty($do->path)) {
                return $this->digitalObjectUrl($do);
            }
        }
        return null;
    }

    protected function pickThumbnailUrl(array $digitals): ?string
    {
        foreach ($digitals as $do) {
            if ((int) ($do->usage_id ?? 0) === 142) {
                return $this->digitalObjectUrl($do);
            }
        }
        return $this->pickReferenceUrl($digitals);
    }

    protected function deriveEdmType(array $digitals): string
    {
        foreach ($digitals as $do) {
            $mime = (string) ($do->mime_type ?? '');
            if ($mime === '') {
                continue;
            }
            if (str_starts_with($mime, 'image/')) {
                return 'IMAGE';
            }
            if (str_starts_with($mime, 'audio/')) {
                return 'SOUND';
            }
            if (str_starts_with($mime, 'video/')) {
                return 'VIDEO';
            }
            if (str_contains($mime, 'model/') || str_contains($mime, 'gltf') || str_contains($mime, 'obj')) {
                return '3D';
            }
        }
        return 'TEXT';
    }

    /**
     * Pick the canonical rightsstatements.org URI for the record. Falls
     * back to InC ("In Copyright") when no explicit statement is attached.
     * Europeana's harvester requires a resolvable rights URI on the
     * ore:Aggregation.
     */
    protected function deriveRightsUri($io): string
    {
        $linked = DB::table('object_rights_statement as ors')
            ->join('rights_statement as rs', 'ors.rights_statement_id', '=', 'rs.id')
            ->where('ors.object_id', $io->id)
            ->value('rs.uri');
        if ($linked) {
            return (string) $linked;
        }
        return (string) $this->setting('europeana_default_rights', 'http://rightsstatements.org/vocab/InC/1.0/');
    }

    protected function setting(string $key, $default = null)
    {
        static $cache = null;
        if ($cache === null) {
            try {
                $cache = DB::table('setting as s')
                    ->join('setting_i18n as si', 'si.id', '=', 's.id')
                    ->where('s.scope', 'federation')
                    ->where('si.culture', 'en')
                    ->pluck('si.value', 's.name')
                    ->all();
            } catch (\Throwable $e) {
                $cache = [];
            }
        }
        $v = $cache[$key] ?? null;
        return ($v === null || $v === '') ? $default : $v;
    }

    protected function fetchIo(int $objectId, string $culture)
    {
        return DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', $culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->leftJoin('object as o', 'o.id', '=', 'io.id')
            ->where('io.id', $objectId)
            ->select([
                'io.id', 'io.identifier', 'io.repository_id',
                'io.source_culture',
                'i18n.title', 'i18n.scope_and_content',
                'i18n.extent_and_medium', 'i18n.access_conditions',
                'i18n.reproduction_conditions',
                'o.updated_at', 's.slug',
            ])
            ->first();
    }

    protected function fetchRepository($io, string $culture)
    {
        if (empty($io->repository_id)) {
            return null;
        }
        return DB::table('repository')
            ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
            ->where('repository.id', $io->repository_id)
            ->where('actor_i18n.culture', $culture)
            ->select('repository.id', 'actor_i18n.authorized_form_of_name as name')
            ->first();
    }

    protected function fetchCreators($io, string $culture)
    {
        return DB::table('event')
            ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
            ->join('actor', 'event.actor_id', '=', 'actor.id')
            ->where('event.object_id', $io->id)
            ->where('event.type_id', 111)
            ->where('actor_i18n.culture', $culture)
            ->whereNotNull('event.actor_id')
            ->select('actor_i18n.authorized_form_of_name as name',
                'actor.entity_type_id', 'actor.id as actor_id')
            ->distinct()
            ->get()
            ->all();
    }

    protected function fetchEvents($io, string $culture): array
    {
        return DB::table('event')
            ->leftJoin('event_i18n', function ($j) use ($culture) {
                $j->on('event.id', '=', 'event_i18n.id')
                    ->where('event_i18n.culture', $culture);
            })
            ->where('event.object_id', $io->id)
            ->select('event.id', 'event.type_id', 'event.actor_id',
                'event.start_date', 'event.end_date',
                'event_i18n.date as date_display')
            ->get()
            ->all();
    }

    protected function fetchAccessPoints($io, int $taxonomyId, string $culture): array
    {
        return DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', $taxonomyId)
            ->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')
            ->get()
            ->all();
    }

    protected function fetchLanguages($io, string $culture): array
    {
        return DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', 7)
            ->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')
            ->get()
            ->all();
    }

    /**
     * Digital surrogates attached to this IO. We pull both the master
     * (parent_id IS NULL) and its children (reference + thumbnail) in
     * one query so the serializer can pick what it needs.
     */
    protected function fetchDigitalObjects(int $ioId): array
    {
        return DB::table('digital_object')
            ->where('object_id', $ioId)
            ->select('id', 'parent_id', 'path', 'name', 'mime_type', 'usage_id', 'byte_size')
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * RiC place entities linked to the IO via the generic relation
     * table. We don't constrain by a specific relation.type_id - any
     * relation where the object is a ric_place row counts. Quietly
     * returns [] when ric_place isn't installed.
     */
    protected function fetchRicPlaces(int $ioId, string $culture): array
    {
        try {
            return DB::table('relation as r')
                ->join('ric_place as p', 'r.object_id', '=', 'p.id')
                ->leftJoin('ric_place_i18n as pi', function ($j) use ($culture) {
                    $j->on('p.id', '=', 'pi.id')->where('pi.culture', $culture);
                })
                ->where('r.subject_id', $ioId)
                ->select('p.id', 'p.latitude', 'p.longitude', 'pi.name')
                ->get()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function escXml(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    protected function escAttr(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
