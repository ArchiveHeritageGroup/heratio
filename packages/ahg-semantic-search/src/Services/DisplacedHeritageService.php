<?php

/**
 * DisplacedHeritageService - first slice of the repatriation engine (north-star
 * heratio#1207): DETECTION only.
 *
 * scan() walks museum-catalogued objects and FLAGS, for curatorial review, those
 * whose recorded ORIGIN (where the object was made, found, or whose cultural
 * context places it) appears to differ from where it is now HELD (the current
 * holding repository / geography, or the holding repository's country). It is a
 * conservative, fully heuristic origin-vs-holding mismatch detector - NOT a legal
 * determination, NOT a repatriation claim, and NOT advice. The output is a
 * register of leads a curator can examine; every record is presented as "review
 * required", never "must return".
 *
 * Design principles (deliberately conservative):
 *   - It only ever compares COUNTRY / broad-REGION signals it can confidently
 *     extract from free text (an explicit country name, a nationality/demonym, or
 *     a well-known ancient-culture region). Anything it cannot place is treated as
 *     UNKNOWN and is NEVER flagged - silence beats a false accusation here.
 *   - A record is flagged ONLY when BOTH an origin region AND a holding region are
 *     known AND they differ. Empty/unknown on either side => not flagged.
 *   - Comparison is case-insensitive and trimmed; matching is done on a normalised
 *     country/region key, so "Egypt" vs "ancient egyptian" resolve to the same
 *     "Egypt" key and do NOT produce a spurious flag, while "Egypt" vs
 *     "United Kingdom" do.
 *   - The place/demonym table is intentionally small and well-known; the goal is
 *     high precision (few false positives) over recall. Unrecognised places simply
 *     yield no region and are skipped.
 *
 * Origin fields examined (museum_metadata): creation_place, discovery_place,
 * cultural_context, cultural_group. Holding fields examined:
 * current_location_geography, current_location_repository, current_location, and -
 * when the information_object has a repository - that repository's country_code
 * from contact_information.
 *
 * Read-only. Touches museum_metadata, information_object, information_object_i18n,
 * slug, repository/contact_information - never writes.
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
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

namespace AhgSemanticSearch\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DisplacedHeritageService
{
    /**
     * Default upper bound on flagged records returned. Overridable via
     * $opts['limit']; 0 means "no cap".
     */
    protected int $defaultLimit = 500;

    /**
     * Standing disclaimer surfaced in the data and the UI/CLI. This is a review
     * aid only - never a legal determination or a repatriation claim.
     */
    public const DISCLAIMER = 'This register is a curatorial review aid only. It heuristically flags '
        .'catalogue records whose recorded place of origin appears to differ from where the object is '
        .'now held. A flag is NOT a determination that an object was wrongfully removed, NOT a '
        .'repatriation claim, and NOT legal advice. Origin, ownership and lawful-transfer history must '
        .'be assessed by qualified staff against the relevant evidence and applicable law in every case.';

    /**
     * Map of normalised place / demonym / culture tokens to a canonical region
     * key. Keys are lower-cased. Deliberately small and high-confidence: a token
     * not present here yields NO region (the record is then skipped, never
     * guessed). Both modern nation-states and a few well-known historical-culture
     * regions are included, because museum origin text often uses cultural rather
     * than political labels.
     *
     * @var array<string,string>
     */
    protected array $regionTokens = [
        // --- Africa ---
        'south africa' => 'South Africa', 'south african' => 'South Africa',
        'egypt' => 'Egypt', 'egyptian' => 'Egypt', 'ancient egypt' => 'Egypt', 'ancient egyptian' => 'Egypt',
        'nubia' => 'Sudan', 'nubian' => 'Sudan', 'sudan' => 'Sudan', 'sudanese' => 'Sudan',
        'nigeria' => 'Nigeria', 'nigerian' => 'Nigeria', 'benin' => 'Nigeria', 'yoruba' => 'Nigeria',
        'ethiopia' => 'Ethiopia', 'ethiopian' => 'Ethiopia',
        'ghana' => 'Ghana', 'ghanaian' => 'Ghana', 'asante' => 'Ghana', 'ashanti' => 'Ghana',
        'mali' => 'Mali', 'malian' => 'Mali',
        'zimbabwe' => 'Zimbabwe', 'zimbabwean' => 'Zimbabwe',
        'kenya' => 'Kenya', 'kenyan' => 'Kenya',
        'morocco' => 'Morocco', 'moroccan' => 'Morocco',
        // --- Europe ---
        'united kingdom' => 'United Kingdom', 'great britain' => 'United Kingdom', 'britain' => 'United Kingdom',
        'british' => 'United Kingdom', 'english' => 'United Kingdom', 'england' => 'United Kingdom',
        'scotland' => 'United Kingdom', 'scottish' => 'United Kingdom', 'wales' => 'United Kingdom', 'welsh' => 'United Kingdom',
        'edwardian' => 'United Kingdom', 'victorian' => 'United Kingdom',
        'france' => 'France', 'french' => 'France', 'gaul' => 'France', 'gallic' => 'France',
        'germany' => 'Germany', 'german' => 'Germany',
        'italy' => 'Italy', 'italian' => 'Italy', 'rome' => 'Italy', 'roman' => 'Italy',
        'greece' => 'Greece', 'greek' => 'Greece', 'hellenic' => 'Greece',
        'spain' => 'Spain', 'spanish' => 'Spain',
        'portugal' => 'Portugal', 'portuguese' => 'Portugal',
        'netherlands' => 'Netherlands', 'dutch' => 'Netherlands', 'holland' => 'Netherlands',
        'belgium' => 'Belgium', 'belgian' => 'Belgium',
        'austria' => 'Austria', 'austrian' => 'Austria',
        'russia' => 'Russia', 'russian' => 'Russia',
        // --- Middle East / Asia ---
        'turkey' => 'Turkey', 'turkish' => 'Turkey', 'ottoman' => 'Turkey', 'anatolia' => 'Turkey', 'anatolian' => 'Turkey',
        'iran' => 'Iran', 'iranian' => 'Iran', 'persia' => 'Iran', 'persian' => 'Iran',
        'iraq' => 'Iraq', 'iraqi' => 'Iraq', 'mesopotamia' => 'Iraq', 'mesopotamian' => 'Iraq', 'assyrian' => 'Iraq', 'babylonian' => 'Iraq',
        'india' => 'India', 'indian' => 'India',
        'china' => 'China', 'chinese' => 'China',
        'japan' => 'Japan', 'japanese' => 'Japan',
        'korea' => 'Korea', 'korean' => 'Korea',
        'thailand' => 'Thailand', 'thai' => 'Thailand',
        'cambodia' => 'Cambodia', 'cambodian' => 'Cambodia', 'khmer' => 'Cambodia',
        // --- Americas / Oceania ---
        'united states' => 'United States', 'usa' => 'United States', 'american' => 'United States',
        'mexico' => 'Mexico', 'mexican' => 'Mexico', 'aztec' => 'Mexico', 'maya' => 'Mexico', 'mayan' => 'Mexico',
        'peru' => 'Peru', 'peruvian' => 'Peru', 'inca' => 'Peru', 'incan' => 'Peru',
        'australia' => 'Australia', 'australian' => 'Australia', 'aboriginal' => 'Australia',
        'new zealand' => 'New Zealand', 'maori' => 'New Zealand',
    ];

    /**
     * ISO-3166 alpha-2 country codes (as stored in contact_information.country_code)
     * mapped to the same canonical region keys, so a holding repository's country
     * can be compared against an origin region.
     *
     * @var array<string,string>
     */
    protected array $countryCodes = [
        'ZA' => 'South Africa', 'EG' => 'Egypt', 'SD' => 'Sudan', 'NG' => 'Nigeria',
        'ET' => 'Ethiopia', 'GH' => 'Ghana', 'ML' => 'Mali', 'ZW' => 'Zimbabwe',
        'KE' => 'Kenya', 'MA' => 'Morocco',
        'GB' => 'United Kingdom', 'UK' => 'United Kingdom', 'FR' => 'France', 'DE' => 'Germany',
        'IT' => 'Italy', 'GR' => 'Greece', 'ES' => 'Spain', 'PT' => 'Portugal',
        'NL' => 'Netherlands', 'BE' => 'Belgium', 'AT' => 'Austria', 'RU' => 'Russia',
        'TR' => 'Turkey', 'IR' => 'Iran', 'IQ' => 'Iraq', 'IN' => 'India',
        'CN' => 'China', 'JP' => 'Japan', 'KR' => 'Korea', 'TH' => 'Thailand', 'KH' => 'Cambodia',
        'US' => 'United States', 'MX' => 'Mexico', 'PE' => 'Peru', 'AU' => 'Australia', 'NZ' => 'New Zealand',
    ];

    /**
     * Origin fields (in priority order) examined on museum_metadata. The first
     * field that resolves to a region wins as "the origin signal".
     *
     * @var array<int,array{field:string,label:string}>
     */
    protected array $originFields = [
        ['field' => 'creation_place', 'label' => 'Place of creation'],
        ['field' => 'discovery_place', 'label' => 'Place of discovery'],
        ['field' => 'cultural_group', 'label' => 'Cultural group'],
        ['field' => 'cultural_context', 'label' => 'Cultural context'],
    ];

    /**
     * Holding fields (in priority order) examined on museum_metadata. The first
     * that resolves to a region wins as "the holding signal". The repository
     * country (joined separately) is a fallback after these.
     *
     * @var array<int,array{field:string,label:string}>
     */
    protected array $holdingFields = [
        ['field' => 'current_location_geography', 'label' => 'Current location (geography)'],
        ['field' => 'current_location_repository', 'label' => 'Holding repository'],
        ['field' => 'current_location', 'label' => 'Current location'],
    ];

    /**
     * Scan the museum catalogue for origin-vs-holding mismatches.
     *
     * @param  array{limit?:int}  $opts
     * @return array{
     *     disclaimer: string,
     *     scanned: int,
     *     evaluated: int,
     *     flagged_count: int,
     *     truncated: bool,
     *     limit: int,
     *     records: array<int,array{
     *         id:int, title:?string, slug:?string,
     *         origin_region:string, holding_region:string,
     *         origin:array{field:string,label:string,value:string},
     *         holding:array{field:string,label:string,value:string},
     *         reason:string
     *     }>,
     *     by_origin: array<int,array{region:string,count:int}>
     * }
     */
    public function scan(array $opts = []): array
    {
        $limit = array_key_exists('limit', $opts) ? (int) $opts['limit'] : $this->defaultLimit;
        if ($limit < 0) {
            $limit = 0;
        }

        $empty = [
            'disclaimer' => self::DISCLAIMER,
            'scanned' => 0,
            'evaluated' => 0,
            'flagged_count' => 0,
            'truncated' => false,
            'limit' => $limit,
            'records' => [],
            'by_origin' => [],
        ];

        if (! Schema::hasTable('museum_metadata') || ! Schema::hasTable('information_object')) {
            return $empty;
        }

        $rows = DB::table('museum_metadata')
            ->select([
                'object_id',
                'creation_place', 'creation_place_type',
                'discovery_place', 'discovery_place_type',
                'cultural_context', 'cultural_group',
                'current_location', 'current_location_repository', 'current_location_geography',
            ])
            ->orderBy('object_id')
            ->get();

        $scanned = count($rows);
        if ($scanned === 0) {
            return $empty;
        }

        $hasI18n = Schema::hasTable('information_object_i18n');
        $hasSlug = Schema::hasTable('slug');
        $hasRepoCountry = Schema::hasTable('contact_information');

        $flagged = [];
        $evaluated = 0;
        $byOrigin = [];

        foreach ($rows as $row) {
            $objectId = (int) $row->object_id;
            if ($objectId <= 0) {
                continue;
            }

            $origin = $this->resolveOrigin($row);
            $holding = $this->resolveHolding($row, $objectId, $hasRepoCountry);

            // Conservative gate: skip unless BOTH sides resolved to a known region.
            if ($origin === null || $holding === null) {
                continue;
            }
            $evaluated++;

            // Same region (e.g. "Egypt" origin held in "Egypt") is not displaced.
            if ($origin['region'] === $holding['region']) {
                continue;
            }

            $title = null;
            if ($hasI18n) {
                $title = DB::table('information_object_i18n')->where('id', $objectId)->value('title');
                $title = $title !== null ? (string) $title : null;
            }
            $slug = null;
            if ($hasSlug) {
                $slug = DB::table('slug')->where('object_id', $objectId)->value('slug');
                $slug = $slug !== null ? (string) $slug : null;
            }

            $reason = sprintf(
                'Recorded origin region "%s" (%s: "%s") differs from holding region "%s" (%s: "%s"). '
                    .'Flagged for curatorial review - not a determination of wrongful removal.',
                $origin['region'], $origin['label'], $this->snippet($origin['value']),
                $holding['region'], $holding['label'], $this->snippet($holding['value'])
            );

            $flagged[] = [
                'id' => $objectId,
                'title' => $title,
                'slug' => $slug,
                'origin_region' => $origin['region'],
                'holding_region' => $holding['region'],
                'origin' => [
                    'field' => $origin['field'],
                    'label' => $origin['label'],
                    'value' => $origin['value'],
                ],
                'holding' => [
                    'field' => $holding['field'],
                    'label' => $holding['label'],
                    'value' => $holding['value'],
                ],
                'reason' => $reason,
            ];

            $byOrigin[$origin['region']] = ($byOrigin[$origin['region']] ?? 0) + 1;
        }

        // Stable, useful ordering: largest origin groups first, then region name.
        arsort($byOrigin);
        $byOriginList = [];
        foreach ($byOrigin as $region => $count) {
            $byOriginList[] = ['region' => $region, 'count' => $count];
        }

        // Order flagged records by origin region (matching the summary), then id.
        usort($flagged, function ($a, $b) use ($byOrigin) {
            $ca = $byOrigin[$a['origin_region']] ?? 0;
            $cb = $byOrigin[$b['origin_region']] ?? 0;
            if ($ca !== $cb) {
                return $cb <=> $ca;
            }
            if ($a['origin_region'] !== $b['origin_region']) {
                return strcmp($a['origin_region'], $b['origin_region']);
            }

            return $a['id'] <=> $b['id'];
        });

        $flaggedCount = count($flagged);
        $truncated = false;
        if ($limit > 0 && $flaggedCount > $limit) {
            $flagged = array_slice($flagged, 0, $limit);
            $truncated = true;
        }

        return [
            'disclaimer' => self::DISCLAIMER,
            'scanned' => $scanned,
            'evaluated' => $evaluated,
            'flagged_count' => $flaggedCount,
            'truncated' => $truncated,
            'limit' => $limit,
            'records' => $flagged,
            'by_origin' => $byOriginList,
        ];
    }

    /**
     * Resolve the first origin field that maps to a known region.
     *
     * @return array{field:string,label:string,value:string,region:string}|null
     */
    protected function resolveOrigin(object $row): ?array
    {
        foreach ($this->originFields as $spec) {
            $value = trim((string) ($row->{$spec['field']} ?? ''));
            if ($value === '') {
                continue;
            }
            $region = $this->regionFor($value);
            if ($region !== null) {
                return [
                    'field' => $spec['field'],
                    'label' => $spec['label'],
                    'value' => $value,
                    'region' => $region,
                ];
            }
        }

        return null;
    }

    /**
     * Resolve the first holding signal that maps to a known region: the
     * museum_metadata holding fields first, then (fallback) the holding
     * repository's country code.
     *
     * @return array{field:string,label:string,value:string,region:string}|null
     */
    protected function resolveHolding(object $row, int $objectId, bool $hasRepoCountry): ?array
    {
        foreach ($this->holdingFields as $spec) {
            $value = trim((string) ($row->{$spec['field']} ?? ''));
            if ($value === '') {
                continue;
            }
            $region = $this->regionFor($value);
            if ($region !== null) {
                return [
                    'field' => $spec['field'],
                    'label' => $spec['label'],
                    'value' => $value,
                    'region' => $region,
                ];
            }
        }

        // Fallback: the holding repository's recorded country.
        if ($hasRepoCountry) {
            $repoId = DB::table('information_object')->where('id', $objectId)->value('repository_id');
            if ($repoId) {
                $code = DB::table('contact_information')
                    ->where('actor_id', (int) $repoId)
                    ->whereNotNull('country_code')
                    ->where('country_code', '!=', '')
                    ->value('country_code');
                if ($code) {
                    $code = strtoupper(trim((string) $code));
                    if (isset($this->countryCodes[$code])) {
                        return [
                            'field' => 'repository_country_code',
                            'label' => 'Holding repository country',
                            'value' => $code,
                            'region' => $this->countryCodes[$code],
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Map a free-text place / demonym / culture string to a canonical region key,
     * or null if no confident match. Matching is conservative: case-insensitive,
     * whole-word, longest-token-first so multi-word tokens ("south africa",
     * "ancient egyptian") win over substrings.
     */
    public function regionFor(string $text): ?string
    {
        $norm = $this->normalise($text);
        if ($norm === '') {
            return null;
        }

        // Try longest tokens first so a more specific match wins.
        $tokens = array_keys($this->regionTokens);
        usort($tokens, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($tokens as $token) {
            // Whole-word match: avoids "mali" matching "Somaliland" etc.
            if (preg_match('/(?<![a-z])'.preg_quote($token, '/').'(?![a-z])/', $norm)) {
                return $this->regionTokens[$token];
            }
        }

        return null;
    }

    /**
     * Lower-case, collapse whitespace, strip most punctuation to spaces so
     * "Roman (Gallic workshops)" -> "roman gallic workshops".
     */
    protected function normalise(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Short, single-line snippet of a value for the reason string.
     */
    protected function snippet(string $value, int $max = 80): string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', $value));
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max - 1).'…';
    }
}
