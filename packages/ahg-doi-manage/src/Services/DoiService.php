<?php

/**
 * DoiService - DOI lifecycle integration with DataCite REST API.
 *
 * Wraps the canonical mint/update/verify/deactivate flow plus queue
 * processing, ported from atom-ahg-plugins/ahgDoiPlugin/lib/Services/DoiService.php
 * (1427 lines on the AtoM side; this is a focused Laravel re-implementation
 * covering the operational surface that ahg-core's artisan commands need).
 *
 * Tables:
 *   ahg_doi          - one row per IO with a DOI assigned (state machine)
 *   ahg_doi_config   - DataCite credentials + prefix + suffix pattern (per-repo)
 *   ahg_doi_queue    - async work items (mint/update/verify/etc)
 *   ahg_doi_log      - append-only history of state transitions and errors
 *
 * @copyright 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgDoiManage\Services;

use AhgDoiManage\Mail\DoiFailedMail;
use AhgDoiManage\Mail\DoiMintedMail;
use App\Services\EmailSuppressionGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DoiService
{
    /**
     * Load active config row for a repository (or the global default if
     * repository_id is null in the row). Returns the first active row when
     * no repository-scoped match exists.
     */
    public function configFor(?int $repositoryId): ?object
    {
        $q = DB::table('ahg_doi_config')->where('is_active', 1);
        if ($repositoryId !== null) {
            $row = (clone $q)->where('repository_id', $repositoryId)->first();
            if ($row) {
                return $row;
            }
        }

        return $q->whereNull('repository_id')->first();
    }

    /**
     * Build the DOI suffix from the configured pattern.
     *   {repository_code}/{year}/{object_id}
     */
    public function buildDoiSuffix(object $config, int $objectId, string $repoCode = 'h'): string
    {
        $year = (string) date('Y');
        $suffix = str_replace(
            ['{repository_code}', '{year}', '{object_id}'],
            [$repoCode, $year, (string) $objectId],
            (string) $config->suffix_pattern
        );

        // DataCite requires URL-safe; strip anything else.
        return preg_replace('/[^A-Za-z0-9._\/-]/', '-', $suffix);
    }

    /**
     * Queue a mint (or other action) for an IO. Idempotent: refuses to
     * enqueue duplicate pending work for the same IO+action pair.
     */
    public function enqueue(int $objectId, string $action = 'mint', int $priority = 100): int
    {
        $exists = DB::table('ahg_doi_queue')
            ->where('information_object_id', $objectId)
            ->where('action', $action)
            ->whereIn('status', ['pending', 'in_progress'])
            ->exists();
        if ($exists) {
            return 0;
        }

        return (int) DB::table('ahg_doi_queue')->insertGetId([
            'information_object_id' => $objectId,
            'action' => $action,
            'status' => 'pending',
            'priority' => $priority,
            'created_at' => now(),
            'scheduled_at' => now(),
        ]);
    }

    /**
     * Pull the next batch of pending queue rows, ordered by priority + age.
     * Caller is expected to mark each row as in_progress before processing.
     */
    public function nextBatch(int $limit = 50): \Illuminate\Support\Collection
    {
        return DB::table('ahg_doi_queue')
            ->where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->orderBy('priority')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Build a DataCite Kernel-4 metadata payload (JSON:API form) for an IO.
     *
     * Phase 1 enrichment (#654, 2026-05-25): in addition to the minimum
     * required attributes (title, creator, publisher, year, resourceType,
     * url) we now emit:
     *   - descriptions[]    - scope_and_content as Abstract
     *   - subjects[]        - taxonomy 35 (Subject) access points
     *   - dates[]           - start/end from event table, dateType=Created
     *   - language          - i.source_culture
     *   - publicationYear   - derived from earliest event start_date (falls
     *                          back to current year when no events exist)
     *
     * Phase 2 enrichment (#654, 2026-05-26):
     *   - creators[]            - real actors from event.actor_id with ORCID
     *                              nameIdentifiers from ahg_actor_identifier
     *   - relatedIdentifiers[]  - parent fonds/series (IsPartOf), digital
     *                              derivatives (IsVariantFormOf), exhibition
     *                              placements (IsReferencedBy)
     *   - geoLocations[]        - place-name access points (taxonomy 42)
     *                              + lat/long coordinates when present
     *   - fundingReferences[]   - rows from ahg_io_funding sidecar
     *
     * Follow-up phases (Phase 3) will add the DataCite Events API client +
     * per-collection accuracy work and is tracked separately under #654.
     */
    public function buildMetadata(int $objectId, object $config, string $doi): array
    {
        $row = DB::connection('atom')->table('information_object as i')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('i.id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
            })
            ->where('i.id', $objectId)
            ->select('i.id', 'i.identifier', 'i.source_culture', 'i.parent_id',
                'i18n.title', 'i18n.scope_and_content',
                'i18n.archival_history', 'i18n.acquisition')
            ->first();

        $title = $row->title ?? ('Information object '.$objectId);
        $publisher = $config->default_publisher ?: 'The Archive and Heritage Group';
        $resourceType = $config->default_resource_type ?: 'Text';

        // ----- Phase 1 enrichment -----

        // Descriptions: prefer scope_and_content as Abstract. Add
        // archival_history + acquisition as additional Other descriptions
        // when present (DataCite supports multi-description per record).
        $descriptions = [];
        $stripTagsClean = static function (?string $s): string {
            return trim(preg_replace('/\s+/', ' ', strip_tags((string) $s)));
        };
        if (! empty($row->scope_and_content)) {
            $descriptions[] = [
                'description' => $stripTagsClean($row->scope_and_content),
                'descriptionType' => 'Abstract',
            ];
        }
        if (! empty($row->archival_history)) {
            $descriptions[] = [
                'description' => $stripTagsClean($row->archival_history),
                'descriptionType' => 'Other',
                'descriptionTypeGeneral' => 'CustodialHistory',
            ];
        }
        if (! empty($row->acquisition)) {
            $descriptions[] = [
                'description' => $stripTagsClean($row->acquisition),
                'descriptionType' => 'Other',
                'descriptionTypeGeneral' => 'AcquisitionInfo',
            ];
        }

        // Subjects from taxonomy 35 (Subject access points)
        $subjects = DB::connection('atom')->table('object_term_relation as r')
            ->join('term as t', 'r.term_id', '=', 't.id')
            ->join('term_i18n as ti', function ($j) {
                $j->on('t.id', '=', 'ti.id')->where('ti.culture', '=', 'en');
            })
            ->where('r.object_id', $objectId)
            ->where('t.taxonomy_id', 35)
            ->whereNotNull('ti.name')
            ->where('ti.name', '!=', '')
            ->select('ti.name')
            ->get()
            ->map(function ($s) {
                return [
                    'subject' => (string) $s->name,
                    'subjectScheme' => 'AHG Subjects',
                ];
            })
            ->all();

        // Dates from event table - keep earliest start_date for publicationYear
        $events = DB::connection('atom')->table('event as e')
            ->leftJoin('event_i18n as ei', function ($j) {
                $j->on('e.id', '=', 'ei.id')->where('ei.culture', '=', 'en');
            })
            ->where('e.object_id', $objectId)
            ->whereIn('e.type_id', [111, 114])  // Creation + Publication event types
            ->select('e.type_id', 'e.start_date', 'e.end_date', 'ei.date as date_display')
            ->get();

        $dates = [];
        $earliestYear = null;
        foreach ($events as $ev) {
            $dateValue = null;
            if ($ev->start_date && $ev->end_date && $ev->start_date !== $ev->end_date) {
                $dateValue = $ev->start_date.'/'.$ev->end_date;  // DataCite range syntax
            } elseif ($ev->start_date) {
                $dateValue = (string) $ev->start_date;
            } elseif ($ev->date_display) {
                $dateValue = trim((string) $ev->date_display);
            }
            if ($dateValue) {
                $dateType = ((int) $ev->type_id === 114) ? 'Issued' : 'Created';
                $dates[] = ['date' => $dateValue, 'dateType' => $dateType];
                // Track earliest year for publicationYear
                if ($ev->start_date) {
                    $year = (int) substr((string) $ev->start_date, 0, 4);
                    if ($year > 0 && ($earliestYear === null || $year < $earliestYear)) {
                        $earliestYear = $year;
                    }
                }
            }
        }
        $publicationYear = $earliestYear ?: (int) date('Y');

        // Language - from source_culture (ISO 639-1 2-letter code)
        $language = ! empty($row->source_culture) ? (string) $row->source_culture : null;

        // ----- Phase 2 enrichment -----
        $creators = $this->buildCreators($objectId, $publisher);
        $relatedIdentifiers = $this->buildRelatedIdentifiers($objectId, (int) ($row->parent_id ?? 0));
        $geoLocations = $this->buildGeoLocations($objectId);
        $fundingReferences = $this->buildFundingReferences($objectId);

        // Compose the final attributes block. Only include enrichment keys
        // when their data is non-empty so DataCite doesn't reject the
        // record on a "subjects must be non-empty array" validation.
        $attributes = [
            'doi' => $doi,
            'titles' => [['title' => $title]],
            'creators' => $creators,
            'publisher' => $publisher,
            'publicationYear' => $publicationYear,
            'types' => ['resourceTypeGeneral' => $resourceType],
            'url' => rtrim(config('app.url', 'http://localhost'), '/').'/informationobject/'.$objectId,
            'event' => 'publish',
        ];
        if (! empty($descriptions)) {
            $attributes['descriptions'] = $descriptions;
        }
        if (! empty($subjects)) {
            $attributes['subjects'] = $subjects;
        }
        if (! empty($dates)) {
            $attributes['dates'] = $dates;
        }
        if ($language) {
            $attributes['language'] = $language;
        }
        if (! empty($row->identifier)) {
            $attributes['alternateIdentifiers'] = [[
                'alternateIdentifier' => (string) $row->identifier,
                'alternateIdentifierType' => 'Local',
            ]];
        }
        if (! empty($relatedIdentifiers)) {
            $attributes['relatedIdentifiers'] = $relatedIdentifiers;
        }
        if (! empty($geoLocations)) {
            $attributes['geoLocations'] = $geoLocations;
        }
        if (! empty($fundingReferences)) {
            $attributes['fundingReferences'] = $fundingReferences;
        }

        return [
            'data' => [
                'id' => $doi,
                'type' => 'dois',
                'attributes' => $attributes,
            ],
        ];
    }

    /**
     * Validate ORCID. Returns the canonical 19-character form
     * (NNNN-NNNN-NNNN-NNNX where X is a digit or 'X') when valid,
     * or null when malformed. Silently drops invalid input rather
     * than emitting invalid DataCite XML.
     */
    public static function normaliseOrcid(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        // Tolerate full URI form (https://orcid.org/0000-...)
        $stripped = preg_replace('#^https?://([a-z.]+)?orcid\.org/#i', '', trim((string) $raw));
        $stripped = strtoupper(preg_replace('/[^0-9Xx]/', '', (string) $stripped));
        if (strlen($stripped) !== 16) {
            return null;
        }
        // First 15 chars must be digits; last may be digit or X.
        if (! preg_match('/^[0-9]{15}[0-9X]$/', $stripped)) {
            return null;
        }

        return substr($stripped, 0, 4).'-'.substr($stripped, 4, 4).'-'.substr($stripped, 8, 4).'-'.substr($stripped, 12, 4);
    }

    /**
     * Build the creators[] block. Pulls actors that are linked to the IO
     * via event.actor_id (taxonomy 110 = event-type-actor relationship in
     * AtoM). Falls back to the publisher when no actor is linked.
     *
     * Each Creator gets a nameIdentifiers[] block when the actor has an
     * ORCID in ahg_actor_identifier (identifier_type='orcid').
     */
    public function buildCreators(int $objectId, string $fallbackName): array
    {
        $out = [];
        try {
            $actors = DB::connection('atom')->table('event as e')
                ->join('actor as a', 'a.id', '=', 'e.actor_id')
                ->leftJoin('actor_i18n as ai', function ($j) {
                    $j->on('a.id', '=', 'ai.id')->where('ai.culture', '=', 'en');
                })
                ->where('e.object_id', $objectId)
                ->whereNotNull('e.actor_id')
                ->select('a.id as actor_id', 'a.entity_type_id', 'ai.authorized_form_of_name')
                ->distinct()
                ->get();

            $orcids = [];
            if (! $actors->isEmpty()) {
                $ids = $actors->pluck('actor_id')->all();
                if (Schema::hasTable('ahg_actor_identifier')) {
                    $rows = DB::table('ahg_actor_identifier')
                        ->whereIn('actor_id', $ids)
                        ->where('identifier_type', 'orcid')
                        ->select('actor_id', 'identifier_value')
                        ->get();
                    foreach ($rows as $r) {
                        $orcids[(int) $r->actor_id] = (string) $r->identifier_value;
                    }
                }
            }

            foreach ($actors as $a) {
                $name = trim((string) ($a->authorized_form_of_name ?? ''));
                if ($name === '') {
                    continue;
                }
                // entity_type_id 132 = corporate body in AtoM; everything
                // else (person, family) defaults to Personal.
                $nameType = ((int) ($a->entity_type_id ?? 0) === 132) ? 'Organizational' : 'Personal';
                $creator = ['name' => $name, 'nameType' => $nameType];

                $orcid = self::normaliseOrcid($orcids[(int) $a->actor_id] ?? null);
                if ($orcid !== null) {
                    $creator['nameIdentifiers'] = [[
                        'nameIdentifier' => $orcid,
                        'nameIdentifierScheme' => 'ORCID',
                        'schemeURI' => 'https://orcid.org/',
                    ]];
                }
                $out[] = $creator;
            }
        } catch (Throwable $e) {
            // Database surface missing - fall through to fallback.
        }

        if (empty($out)) {
            $out[] = ['name' => $fallbackName];
        }

        return $out;
    }

    /**
     * Build the relatedIdentifiers[] block:
     *   - IsPartOf:        parent fonds/series via information_object.parent_id
     *                       (DOI if minted, otherwise URL)
     *   - IsVariantFormOf: digital-object derivatives (master -> access copies)
     *                       sharing the same information_object_id
     *   - IsReferencedBy:  exhibition placements (ahg-exhibition / exhibition_object)
     */
    public function buildRelatedIdentifiers(int $objectId, int $parentId): array
    {
        $out = [];

        // --- IsPartOf: hierarchical parent ---
        if ($parentId > 0 && $parentId !== 1) {  // 1 = root node in AtoM nested-set
            $parentDoi = DB::table('ahg_doi')
                ->where('information_object_id', $parentId)
                ->whereIn('status', ['findable', 'registered', 'draft'])
                ->value('doi');
            if ($parentDoi) {
                $out[] = [
                    'relatedIdentifier' => (string) $parentDoi,
                    'relatedIdentifierType' => 'DOI',
                    'relationType' => 'IsPartOf',
                ];
            } else {
                $out[] = [
                    'relatedIdentifier' => rtrim(config('app.url', 'http://localhost'), '/').'/informationobject/'.$parentId,
                    'relatedIdentifierType' => 'URL',
                    'relationType' => 'IsPartOf',
                ];
            }
        }

        // --- IsVariantFormOf: digital-object derivatives ---
        // Master digital objects in AtoM have digital_object.parent_id NULL;
        // access/reference/thumbnail copies have parent_id pointing at the
        // master. If an IO has > 1 digital_object row, emit a single
        // IsVariantFormOf link to the IO itself (placeholder - DataCite
        // expects each derivative to have its own resolvable identifier;
        // we surface the relationship as the IO URL until per-derivative
        // identifiers ship).
        try {
            $derivCount = DB::connection('atom')->table('digital_object')
                ->where('object_id', $objectId)
                ->count();
            if ($derivCount > 1) {
                $out[] = [
                    'relatedIdentifier' => rtrim(config('app.url', 'http://localhost'), '/').'/informationobject/'.$objectId.'/digitalobjects',
                    'relatedIdentifierType' => 'URL',
                    'relationType' => 'IsVariantFormOf',
                ];
            }
        } catch (Throwable $e) {
            // skip
        }

        // --- IsReferencedBy: exhibitions ---
        if (Schema::hasTable('exhibition_object')) {
            try {
                $exhibitions = DB::table('exhibition_object as eo')
                    ->join('exhibition as ex', 'ex.id', '=', 'eo.exhibition_id')
                    ->where('eo.information_object_id', $objectId)
                    ->select('ex.id', 'ex.title')
                    ->distinct()
                    ->get();
                foreach ($exhibitions as $ex) {
                    $out[] = [
                        'relatedIdentifier' => rtrim(config('app.url', 'http://localhost'), '/').'/exhibitions/'.$ex->id,
                        'relatedIdentifierType' => 'URL',
                        'relationType' => 'IsReferencedBy',
                    ];
                }
            } catch (Throwable $e) {
                // skip
            }
        }

        return $out;
    }

    /**
     * Build the geoLocations[] block from taxonomy 42 (Place) access points.
     * Each place name becomes a <geoLocationPlace>. When the underlying
     * term has latitude/longitude (some AtoM installs store these as
     * term properties), emit a <geoLocationPoint> too. Heratio currently
     * has no canonical coordinate column on the term table, so this is
     * defensive: we look for an ahg_place_coords sidecar and skip points
     * when it's missing rather than guessing.
     */
    public function buildGeoLocations(int $objectId): array
    {
        $out = [];
        try {
            $places = DB::connection('atom')->table('object_term_relation as r')
                ->join('term as t', 'r.term_id', '=', 't.id')
                ->join('term_i18n as ti', function ($j) {
                    $j->on('t.id', '=', 'ti.id')->where('ti.culture', '=', 'en');
                })
                ->where('r.object_id', $objectId)
                ->where('t.taxonomy_id', 42)  // Place taxonomy
                ->whereNotNull('ti.name')
                ->where('ti.name', '!=', '')
                ->select('t.id as term_id', 'ti.name')
                ->get();

            $coords = [];
            if (! $places->isEmpty() && Schema::hasTable('ahg_place_coords')) {
                $rows = DB::table('ahg_place_coords')
                    ->whereIn('term_id', $places->pluck('term_id')->all())
                    ->select('term_id', 'latitude', 'longitude',
                        'box_west', 'box_east', 'box_south', 'box_north',
                        'polygon_json')
                    ->get();
                foreach ($rows as $r) {
                    $coords[(int) $r->term_id] = $r;
                }
            }

            foreach ($places as $p) {
                $loc = ['geoLocationPlace' => (string) $p->name];
                $c = $coords[(int) $p->term_id] ?? null;
                if ($c) {
                    if ($c->polygon_json) {
                        $points = json_decode((string) $c->polygon_json, true);
                        if (is_array($points) && count($points) >= 3) {
                            $loc['geoLocationPolygon'] = ['polygonPoints' => array_map(static function ($pt) {
                                return [
                                    'pointLongitude' => (float) ($pt['lng'] ?? $pt[0] ?? 0),
                                    'pointLatitude' => (float) ($pt['lat'] ?? $pt[1] ?? 0),
                                ];
                            }, $points)];
                        }
                    } elseif ($c->box_west !== null && $c->box_east !== null && $c->box_south !== null && $c->box_north !== null) {
                        $loc['geoLocationBox'] = [
                            'westBoundLongitude' => (float) $c->box_west,
                            'eastBoundLongitude' => (float) $c->box_east,
                            'southBoundLatitude' => (float) $c->box_south,
                            'northBoundLatitude' => (float) $c->box_north,
                        ];
                    } elseif ($c->latitude !== null && $c->longitude !== null) {
                        $loc['geoLocationPoint'] = [
                            'pointLongitude' => (float) $c->longitude,
                            'pointLatitude' => (float) $c->latitude,
                        ];
                    }
                }
                $out[] = $loc;
            }
        } catch (Throwable $e) {
            // skip
        }

        return $out;
    }

    /**
     * Build the fundingReferences[] block from the ahg_io_funding sidecar.
     * Each row produces one entry. Funder identifier type defaults to ROR
     * when only an ID is supplied without explicit type.
     */
    public function buildFundingReferences(int $objectId): array
    {
        $out = [];
        if (! Schema::hasTable('ahg_io_funding')) {
            return $out;
        }
        try {
            $rows = DB::table('ahg_io_funding')
                ->where('information_object_id', $objectId)
                ->get();
            foreach ($rows as $r) {
                $entry = ['funderName' => (string) ($r->funder_name ?? '')];
                if ($entry['funderName'] === '') {
                    continue;
                }
                if (! empty($r->funder_identifier)) {
                    $entry['funderIdentifier'] = (string) $r->funder_identifier;
                    $entry['funderIdentifierType'] = (string) ($r->funder_identifier_type ?: 'ROR');
                }
                if (! empty($r->award_number)) {
                    $entry['awardNumber'] = (string) $r->award_number;
                }
                if (! empty($r->award_uri)) {
                    if (! isset($entry['awardNumber'])) {
                        $entry['awardNumber'] = '';
                    }
                    $entry['awardURI'] = (string) $r->award_uri;
                }
                if (! empty($r->award_title)) {
                    $entry['awardTitle'] = (string) $r->award_title;
                }
                $out[] = $entry;
            }
        } catch (Throwable $e) {
            // skip
        }

        return $out;
    }

    /**
     * Serialise a DataCite Kernel-4 metadata payload to XML.
     *
     * Phase 2 (#654, 2026-05-26): emits the four new blocks
     * (nameIdentifiers, relatedIdentifiers, geoLocations, fundingReferences)
     * alongside the Phase 1 blocks. The DataCite REST API accepts JSON:API
     * by default; this XML form is used for repositories that POST raw
     * Kernel-4 XML to the MDS API (legacy/OAI consumers).
     */
    public function buildXml(array $payload): string
    {
        $attrs = $payload['data']['attributes'] ?? [];
        $doi = $attrs['doi'] ?? '';

        $esc = static function ($s): string {
            return htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        };

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<resource xmlns="http://datacite.org/schema/kernel-4" '.
                'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '.
                'xsi:schemaLocation="http://datacite.org/schema/kernel-4 https://schema.datacite.org/meta/kernel-4.5/metadata.xsd">'."\n";

        $xml .= '  <identifier identifierType="DOI">'.$esc($doi).'</identifier>'."\n";

        // creators
        $xml .= '  <creators>'."\n";
        foreach ((array) ($attrs['creators'] ?? []) as $c) {
            $nameType = ! empty($c['nameType']) ? ' nameType="'.$esc($c['nameType']).'"' : '';
            $xml .= '    <creator>'."\n";
            $xml .= '      <creatorName'.$nameType.'>'.$esc($c['name'] ?? '').'</creatorName>'."\n";
            foreach ((array) ($c['nameIdentifiers'] ?? []) as $ni) {
                $xml .= '      <nameIdentifier nameIdentifierScheme="'.$esc($ni['nameIdentifierScheme'] ?? '').'"'.
                        (! empty($ni['schemeURI']) ? ' schemeURI="'.$esc($ni['schemeURI']).'"' : '').
                        '>'.$esc($ni['nameIdentifier'] ?? '').'</nameIdentifier>'."\n";
            }
            $xml .= '    </creator>'."\n";
        }
        $xml .= '  </creators>'."\n";

        // titles
        $xml .= '  <titles>'."\n";
        foreach ((array) ($attrs['titles'] ?? []) as $t) {
            $xml .= '    <title>'.$esc($t['title'] ?? '').'</title>'."\n";
        }
        $xml .= '  </titles>'."\n";

        $xml .= '  <publisher>'.$esc($attrs['publisher'] ?? '').'</publisher>'."\n";
        $xml .= '  <publicationYear>'.$esc($attrs['publicationYear'] ?? '').'</publicationYear>'."\n";

        $rt = $attrs['types']['resourceTypeGeneral'] ?? 'Text';
        $xml .= '  <resourceType resourceTypeGeneral="'.$esc($rt).'"></resourceType>'."\n";

        if (! empty($attrs['subjects'])) {
            $xml .= '  <subjects>'."\n";
            foreach ($attrs['subjects'] as $s) {
                $scheme = ! empty($s['subjectScheme']) ? ' subjectScheme="'.$esc($s['subjectScheme']).'"' : '';
                $xml .= '    <subject'.$scheme.'>'.$esc($s['subject'] ?? '').'</subject>'."\n";
            }
            $xml .= '  </subjects>'."\n";
        }

        if (! empty($attrs['dates'])) {
            $xml .= '  <dates>'."\n";
            foreach ($attrs['dates'] as $d) {
                $xml .= '    <date dateType="'.$esc($d['dateType'] ?? 'Created').'">'.$esc($d['date'] ?? '').'</date>'."\n";
            }
            $xml .= '  </dates>'."\n";
        }

        if (! empty($attrs['language'])) {
            $xml .= '  <language>'.$esc($attrs['language']).'</language>'."\n";
        }

        if (! empty($attrs['alternateIdentifiers'])) {
            $xml .= '  <alternateIdentifiers>'."\n";
            foreach ($attrs['alternateIdentifiers'] as $a) {
                $xml .= '    <alternateIdentifier alternateIdentifierType="'.$esc($a['alternateIdentifierType'] ?? 'Local').'">'.
                        $esc($a['alternateIdentifier'] ?? '').'</alternateIdentifier>'."\n";
            }
            $xml .= '  </alternateIdentifiers>'."\n";
        }

        if (! empty($attrs['relatedIdentifiers'])) {
            $xml .= '  <relatedIdentifiers>'."\n";
            foreach ($attrs['relatedIdentifiers'] as $r) {
                $xml .= '    <relatedIdentifier relatedIdentifierType="'.$esc($r['relatedIdentifierType'] ?? 'URL').'"'.
                        ' relationType="'.$esc($r['relationType'] ?? 'IsPartOf').'">'.
                        $esc($r['relatedIdentifier'] ?? '').'</relatedIdentifier>'."\n";
            }
            $xml .= '  </relatedIdentifiers>'."\n";
        }

        if (! empty($attrs['descriptions'])) {
            $xml .= '  <descriptions>'."\n";
            foreach ($attrs['descriptions'] as $d) {
                $xml .= '    <description descriptionType="'.$esc($d['descriptionType'] ?? 'Abstract').'">'.
                        $esc($d['description'] ?? '').'</description>'."\n";
            }
            $xml .= '  </descriptions>'."\n";
        }

        if (! empty($attrs['geoLocations'])) {
            $xml .= '  <geoLocations>'."\n";
            foreach ($attrs['geoLocations'] as $g) {
                $xml .= '    <geoLocation>'."\n";
                if (! empty($g['geoLocationPlace'])) {
                    $xml .= '      <geoLocationPlace>'.$esc($g['geoLocationPlace']).'</geoLocationPlace>'."\n";
                }
                if (! empty($g['geoLocationPoint'])) {
                    $p = $g['geoLocationPoint'];
                    $xml .= '      <geoLocationPoint>'."\n";
                    $xml .= '        <pointLongitude>'.$esc($p['pointLongitude'] ?? '').'</pointLongitude>'."\n";
                    $xml .= '        <pointLatitude>'.$esc($p['pointLatitude'] ?? '').'</pointLatitude>'."\n";
                    $xml .= '      </geoLocationPoint>'."\n";
                }
                if (! empty($g['geoLocationBox'])) {
                    $b = $g['geoLocationBox'];
                    $xml .= '      <geoLocationBox>'."\n";
                    $xml .= '        <westBoundLongitude>'.$esc($b['westBoundLongitude'] ?? '').'</westBoundLongitude>'."\n";
                    $xml .= '        <eastBoundLongitude>'.$esc($b['eastBoundLongitude'] ?? '').'</eastBoundLongitude>'."\n";
                    $xml .= '        <southBoundLatitude>'.$esc($b['southBoundLatitude'] ?? '').'</southBoundLatitude>'."\n";
                    $xml .= '        <northBoundLatitude>'.$esc($b['northBoundLatitude'] ?? '').'</northBoundLatitude>'."\n";
                    $xml .= '      </geoLocationBox>'."\n";
                }
                if (! empty($g['geoLocationPolygon']['polygonPoints'])) {
                    $xml .= '      <geoLocationPolygon>'."\n";
                    foreach ($g['geoLocationPolygon']['polygonPoints'] as $pt) {
                        $xml .= '        <polygonPoint>'."\n";
                        $xml .= '          <pointLongitude>'.$esc($pt['pointLongitude'] ?? '').'</pointLongitude>'."\n";
                        $xml .= '          <pointLatitude>'.$esc($pt['pointLatitude'] ?? '').'</pointLatitude>'."\n";
                        $xml .= '        </polygonPoint>'."\n";
                    }
                    $xml .= '      </geoLocationPolygon>'."\n";
                }
                $xml .= '    </geoLocation>'."\n";
            }
            $xml .= '  </geoLocations>'."\n";
        }

        if (! empty($attrs['fundingReferences'])) {
            $xml .= '  <fundingReferences>'."\n";
            foreach ($attrs['fundingReferences'] as $f) {
                $xml .= '    <fundingReference>'."\n";
                $xml .= '      <funderName>'.$esc($f['funderName'] ?? '').'</funderName>'."\n";
                if (! empty($f['funderIdentifier'])) {
                    $xml .= '      <funderIdentifier funderIdentifierType="'.$esc($f['funderIdentifierType'] ?? 'ROR').'">'.
                            $esc($f['funderIdentifier']).'</funderIdentifier>'."\n";
                }
                if (isset($f['awardNumber'])) {
                    $awardUri = ! empty($f['awardURI']) ? ' awardURI="'.$esc($f['awardURI']).'"' : '';
                    $xml .= '      <awardNumber'.$awardUri.'>'.$esc($f['awardNumber']).'</awardNumber>'."\n";
                }
                if (! empty($f['awardTitle'])) {
                    $xml .= '      <awardTitle>'.$esc($f['awardTitle']).'</awardTitle>'."\n";
                }
                $xml .= '    </fundingReference>'."\n";
            }
            $xml .= '  </fundingReferences>'."\n";
        }

        $xml .= '</resource>'."\n";

        return $xml;
    }

    /**
     * Mint a DOI for an IO. Reserves the DOI string, calls DataCite, persists
     * the row in ahg_doi, logs the action.
     *
     * @return array{success:bool, doi:?string, error:?string}
     */
    public function mint(int $objectId, ?int $repositoryId = null, bool $dryRun = false): array
    {
        try {
            $config = $this->configFor($repositoryId);
            if (! $config) {
                return ['success' => false, 'doi' => null, 'error' => 'no active ahg_doi_config row'];
            }

            // Idempotency: existing minted DOI is a no-op.
            $existing = DB::table('ahg_doi')->where('information_object_id', $objectId)->first();
            if ($existing && $existing->status !== 'tombstone') {
                return ['success' => true, 'doi' => $existing->doi, 'error' => null];
            }

            $suffix = $this->buildDoiSuffix($config, $objectId);
            $doi = rtrim($config->datacite_prefix, '/').'/'.ltrim($suffix, '/');
            $payload = $this->buildMetadata($objectId, $config, $doi);

            if ($dryRun) {
                return ['success' => true, 'doi' => $doi, 'error' => 'dry-run'];
            }

            $resp = $this->dataciteRequest($config, 'POST', '/dois', $payload);
            if (! $resp['ok']) {
                $this->log($objectId, null, 'mint', null, null, ['error' => $resp['error']]);

                return ['success' => false, 'doi' => null, 'error' => $resp['error']];
            }

            $rowId = DB::table('ahg_doi')->insertGetId([
                'information_object_id' => $objectId,
                'doi' => $doi,
                'status' => 'findable',
                'minted_at' => now(),
                'datacite_response' => json_encode($resp['body']),
                'metadata_json' => json_encode($payload),
                'last_sync_at' => now(),
                'created_at' => now(),
            ]);
            $this->log($objectId, $rowId, 'mint', null, 'findable', ['doi' => $doi]);

            // Phase 3 of #674 - notify the IO owner that a DOI has been
            // minted, and CC the ops mailbox on the operational success.
            $this->dispatchMintedMail($objectId, $doi);

            return ['success' => true, 'doi' => $doi, 'error' => null];
        } catch (Throwable $e) {
            // Phase 3 of #674 - notify on failure too. We send to the IO
            // owner (so they can correct metadata) and to the configurable
            // doi_failure_notify ops mailbox (so the team sees it).
            $this->dispatchFailedMail($objectId, $e->getMessage());

            return ['success' => false, 'doi' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Notify the IO owner + ops mailbox that a DOI was minted.
     * Best-effort; never propagates errors back to the caller.
     */
    protected function dispatchMintedMail(int $objectId, string $doi): void
    {
        try {
            $title = $this->resolveObjectTitle($objectId);
            $appUrl = rtrim((string) config('app.url', ''), '/');
            $context = [
                'doi' => $doi,
                'title' => $title,
                'object_url' => $appUrl.'/informationobject/'.$objectId,
                'resolver_url' => 'https://doi.org/'.$doi,
            ];

            foreach ($this->resolveDoiRecipients($objectId) as $recipient) {
                $ctx = $context + [
                    'recipient_email' => $recipient['email'],
                    'recipient_name' => $recipient['name'] ?? null,
                    'preferred_locale' => $recipient['locale'] ?? null,
                ];
                if (! EmailSuppressionGate::canSend($recipient['email'], DoiMintedMail::class, 'DOI minted: '.$doi)) {
                    continue;
                }
                Mail::to($recipient['email'])->queue(new DoiMintedMail($ctx));
            }
        } catch (Throwable $e) {
            Log::warning('DoiMintedMail dispatch failed: '.$e->getMessage());
        }
    }

    /**
     * Notify the IO owner + ops mailbox that a DOI mint failed.
     */
    protected function dispatchFailedMail(int $objectId, string $error): void
    {
        try {
            $title = $this->resolveObjectTitle($objectId);
            $appUrl = rtrim((string) config('app.url', ''), '/');
            $context = [
                'title' => $title,
                'object_url' => $appUrl.'/informationobject/'.$objectId,
                'error_code' => null,
                'error_message' => $error,
                'attempted_at' => now()->toIso8601String(),
                'retry_url' => $appUrl.'/admin/doi/retry/'.$objectId,
            ];

            foreach ($this->resolveDoiRecipients($objectId) as $recipient) {
                $ctx = $context + [
                    'recipient_email' => $recipient['email'],
                    'recipient_name' => $recipient['name'] ?? null,
                    'preferred_locale' => $recipient['locale'] ?? null,
                ];
                if (! EmailSuppressionGate::canSend($recipient['email'], DoiFailedMail::class, 'DOI mint failed: '.$title)) {
                    continue;
                }
                Mail::to($recipient['email'])->queue(new DoiFailedMail($ctx));
            }
        } catch (Throwable $e) {
            Log::warning('DoiFailedMail dispatch failed: '.$e->getMessage());
        }
    }

    /**
     * Build the recipient list for a DOI lifecycle email:
     *   - IO creator (via information_object.created_by -> user.id)
     *   - Configurable ops mailbox (ahg_settings.doi_failure_notify; supports
     *     comma-separated multiple addresses)
     *
     * Returns deduplicated [['email','name'?,'locale'?], ...].
     */
    protected function resolveDoiRecipients(int $objectId): array
    {
        $out = [];
        $seen = [];
        $push = function (string $email, ?string $name = null, ?string $locale = null) use (&$out, &$seen) {
            $key = strtolower(trim($email));
            if ($key === '' || isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $out[] = ['email' => $key, 'name' => $name, 'locale' => $locale];
        };

        // IO owner / creator
        try {
            $owner = DB::connection('atom')->table('information_object as i')
                ->leftJoin('user as u', 'u.id', '=', 'i.created_by')
                ->where('i.id', $objectId)
                ->select('u.email', 'u.username', 'u.preferred_locale')
                ->first();
            if ($owner && ! empty($owner->email)) {
                $push($owner->email, $owner->username ?? null, $owner->preferred_locale ?? null);
            }
        } catch (Throwable $e) {
            // IO connection missing or no created_by column - fall through
        }

        // Ops mailbox(es) from settings
        if (Schema::hasTable('ahg_settings')) {
            $row = DB::table('ahg_settings')->where('setting_key', 'doi_failure_notify')->first();
            if ($row && trim((string) $row->setting_value) !== '') {
                foreach (preg_split('/[,;\s]+/', (string) $row->setting_value) as $addr) {
                    $addr = trim((string) $addr);
                    if ($addr !== '' && filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                        $push($addr);
                    }
                }
            }
        }

        return $out;
    }

    protected function resolveObjectTitle(int $objectId): string
    {
        try {
            $row = DB::connection('atom')->table('information_object_i18n')
                ->where('id', $objectId)
                ->where('culture', 'en')
                ->value('title');
            if ($row) {
                return (string) $row;
            }
        } catch (Throwable $e) {
            // fall through
        }

        return 'Information object '.$objectId;
    }

    /**
     * Verify a DOI's existence + state on DataCite, refresh ahg_doi.last_sync_at.
     */
    public function verify(string $doi): array
    {
        $row = DB::table('ahg_doi')->where('doi', $doi)->first();
        if (! $row) {
            return ['success' => false, 'error' => 'unknown DOI'];
        }
        $config = $this->configFor(null);
        if (! $config) {
            return ['success' => false, 'error' => 'no config'];
        }

        $resp = $this->dataciteRequest($config, 'GET', '/dois/'.urlencode($doi));
        if (! $resp['ok']) {
            $this->log($row->information_object_id, $row->id, 'verify', $row->status, $row->status, ['error' => $resp['error']]);

            return ['success' => false, 'error' => $resp['error']];
        }
        $state = $resp['body']['data']['attributes']['state'] ?? $row->status;
        DB::table('ahg_doi')->where('id', $row->id)->update([
            'status' => $state,
            'last_sync_at' => now(),
        ]);
        $this->log($row->information_object_id, $row->id, 'verify', $row->status, $state, []);

        return ['success' => true, 'state' => $state];
    }

    /**
     * Update DataCite metadata for an existing DOI.
     */
    public function update(string $doi): array
    {
        $row = DB::table('ahg_doi')->where('doi', $doi)->first();
        if (! $row) {
            return ['success' => false, 'error' => 'unknown DOI'];
        }
        $config = $this->configFor(null);
        if (! $config) {
            return ['success' => false, 'error' => 'no config'];
        }
        $payload = $this->buildMetadata((int) $row->information_object_id, $config, $doi);

        $resp = $this->dataciteRequest($config, 'PUT', '/dois/'.urlencode($doi), $payload);
        if (! $resp['ok']) {
            $this->log($row->information_object_id, $row->id, 'update', $row->status, $row->status, ['error' => $resp['error']]);

            return ['success' => false, 'error' => $resp['error']];
        }
        DB::table('ahg_doi')->where('id', $row->id)->update([
            'metadata_json' => json_encode($payload),
            'last_sync_at' => now(),
        ]);
        $this->log($row->information_object_id, $row->id, 'update', $row->status, $row->status, []);

        return ['success' => true];
    }

    /**
     * Tombstone (deactivate) a DOI - keeps the identifier resolvable but flips
     * its event to "hide". DataCite preserves the metadata as a tombstone page.
     */
    public function deactivate(string $doi, string $reason = 'admin tombstone'): array
    {
        $row = DB::table('ahg_doi')->where('doi', $doi)->first();
        if (! $row) {
            return ['success' => false, 'error' => 'unknown DOI'];
        }
        $config = $this->configFor(null);
        if (! $config) {
            return ['success' => false, 'error' => 'no config'];
        }

        $payload = ['data' => ['type' => 'dois', 'attributes' => ['event' => 'hide']]];
        $resp = $this->dataciteRequest($config, 'PUT', '/dois/'.urlencode($doi), $payload);
        if (! $resp['ok']) {
            return ['success' => false, 'error' => $resp['error']];
        }

        DB::table('ahg_doi')->where('id', $row->id)->update([
            'status' => 'tombstone',
            'last_sync_at' => now(),
        ]);
        $this->log($row->information_object_id, $row->id, 'deactivate', $row->status, 'tombstone', ['reason' => $reason]);

        return ['success' => true];
    }

    /**
     * Process N pending queue rows. Called by ahg:doi-process-queue.
     */
    public function processQueue(int $limit = 50, bool $dryRun = false): array
    {
        $rows = $this->nextBatch($limit);
        $ok = 0;
        $fail = 0;
        foreach ($rows as $r) {
            DB::table('ahg_doi_queue')->where('id', $r->id)->update([
                'status' => 'in_progress',
                'started_at' => now(),
                'attempts' => $r->attempts + 1,
            ]);
            try {
                $result = match ($r->action) {
                    'mint' => $this->mint((int) $r->information_object_id, null, $dryRun),
                    'update' => $this->updateByObject((int) $r->information_object_id),
                    'verify' => $this->verifyByObject((int) $r->information_object_id),
                    'deactivate' => $this->deactivateByObject((int) $r->information_object_id),
                    default => ['success' => false, 'error' => 'unknown action: '.$r->action],
                };
            } catch (Throwable $e) {
                $result = ['success' => false, 'error' => $e->getMessage()];
            }

            if ($result['success']) {
                DB::table('ahg_doi_queue')->where('id', $r->id)->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
                $ok++;
            } else {
                $status = ($r->attempts + 1) >= $r->max_attempts ? 'failed' : 'pending';
                DB::table('ahg_doi_queue')->where('id', $r->id)->update([
                    'status' => $status,
                    'last_error' => substr($result['error'] ?? 'unknown', 0, 65535),
                    'started_at' => null,
                    // Backoff: linear minutes per attempt.
                    'scheduled_at' => now()->addMinutes(5 * ($r->attempts + 1)),
                ]);
                $fail++;
            }
        }

        return ['ok' => $ok, 'fail' => $fail, 'processed' => $rows->count()];
    }

    public function reportSummary(): array
    {
        return [
            'total' => (int) DB::table('ahg_doi')->count(),
            'by_status' => DB::table('ahg_doi')->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status')->toArray(),
            'queue' => DB::table('ahg_doi_queue')->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status')->toArray(),
            'last_log' => DB::table('ahg_doi_log')->orderByDesc('id')->limit(20)->get(),
        ];
    }

    // --- helpers ----------------------------------------------------------

    public function updateByObject(int $oid): array
    {
        $row = DB::table('ahg_doi')->where('information_object_id', $oid)->first();

        return $row ? $this->update($row->doi) : ['success' => false, 'error' => 'no DOI for object'];
    }

    public function verifyByObject(int $oid): array
    {
        $row = DB::table('ahg_doi')->where('information_object_id', $oid)->first();

        return $row ? $this->verify($row->doi) : ['success' => false, 'error' => 'no DOI for object'];
    }

    public function deactivateByObject(int $oid): array
    {
        $row = DB::table('ahg_doi')->where('information_object_id', $oid)->first();

        return $row ? $this->deactivate($row->doi) : ['success' => false, 'error' => 'no DOI for object'];
    }

    protected function dataciteRequest(object $config, string $method, string $path, array $body = []): array
    {
        $url = rtrim($config->datacite_url, '/').$path;
        try {
            $req = Http::withBasicAuth($config->datacite_repo_id, (string) $config->datacite_password)
                ->acceptJson()
                ->timeout(30);
            if ($method === 'GET') {
                $resp = $req->get($url);
            } else {
                $resp = $req->withBody(json_encode($body), 'application/vnd.api+json')->send($method, $url);
            }
            if ($resp->successful()) {
                return ['ok' => true, 'body' => $resp->json(), 'error' => null];
            }

            return ['ok' => false, 'body' => $resp->json(), 'error' => 'HTTP '.$resp->status().': '.$resp->body()];
        } catch (Throwable $e) {
            Log::warning('DataCite request failed: '.$e->getMessage());

            return ['ok' => false, 'body' => null, 'error' => $e->getMessage()];
        }
    }

    protected function log(?int $oid, ?int $doiId, string $action, ?string $before, ?string $after, array $details = []): void
    {
        try {
            DB::table('ahg_doi_log')->insert([
                'doi_id' => $doiId,
                'information_object_id' => $oid,
                'action' => $action,
                'status_before' => $before,
                'status_after' => $after,
                'details' => json_encode($details),
                'performed_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('ahg_doi_log insert failed: '.$e->getMessage());
        }
    }
}
