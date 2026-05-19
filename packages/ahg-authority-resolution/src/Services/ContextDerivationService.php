<?php

/**
 * ContextDerivationService - Service for Heratio
 *
 * Pure-PHP analyzer. Given a source text + a mention value + the co-occurring
 * NER entities for the same object, derives the neighbourhood-context packet
 * stored in ahg_mention_context: offsets, surrounding text, co-occurring
 * entities filtered by paragraph proximity, nearby dates/places, and
 * role-language tokens within range.
 *
 * No DB calls. No I/O. Single public method: derive().
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

namespace AhgAuthorityResolution\Services;

class ContextDerivationService
{
    private const SURROUNDING_TEXT_WINDOW = 150;
    private const ROLE_LANGUAGE_CHAR_WINDOW = 120;

    private const PLACE_TYPES = ['GPE', 'LOC', 'PLACE', 'ISAD_PLACE'];
    private const DATE_TYPES = ['DATE', 'ISAD_DATE'];

    /**
     * Derive the full context packet for a mention.
     *
     * @param string $sourceText      Full source text (e.g. IO scope_and_content + adjacent fields, concatenated)
     * @param string $mentionValue    The entity value being resolved (e.g. "Nelson Mandela")
     * @param string $mentionType     Entity type (PERSON / ORG / GPE / etc.)
     * @param list<array{ner_entity_id:int,value:string,type:string}> $otherEntities
     * @param array<string,list<string>> $roleLanguageTokens  e.g. ['kinship' => ['son of', 'daughter of'], ...]
     *
     * @return array{
     *   character_offset_start: int|null,
     *   character_offset_end: int|null,
     *   paragraph_offset_start: int|null,
     *   paragraph_offset_end: int|null,
     *   surrounding_text_before: string|null,
     *   surrounding_text_after: string|null,
     *   co_occurring_entities: list<array>,
     *   nearby_dates: list<array>,
     *   nearby_places: list<array>,
     *   role_language_tokens: list<array>,
     *   ambiguity: array{occurrence_count:int}
     * }
     */
    public function derive(
        string $sourceText,
        string $mentionValue,
        string $mentionType,
        array $otherEntities,
        array $roleLanguageTokens
    ): array {
        $occurrences = $this->findAllOccurrences($sourceText, $mentionValue);

        if (empty($occurrences)) {
            return $this->emptyContext(0);
        }

        // Pick the first occurrence (on-demand backfill convention: lossy when name repeats;
        // ambiguity flagged via occurrence_count for the review UI).
        [$startOffset, $endOffset] = $occurrences[0];

        $paragraph = $this->findEnclosingParagraph($sourceText, $startOffset);
        $surrounding = $this->getSurroundingText($sourceText, $startOffset, $endOffset);
        $coOccurring = $this->findEntitiesInParagraph($paragraph, $otherEntities, $startOffset);

        $nearbyDates = $this->partitionByTypes($coOccurring, self::DATE_TYPES);
        $nearbyPlaces = $this->partitionByTypes($coOccurring, self::PLACE_TYPES);
        $coOccurringFiltered = $this->excludeTypes($coOccurring, array_merge(self::DATE_TYPES, self::PLACE_TYPES));

        $roleTokens = $this->findRoleLanguage($paragraph, $startOffset, $roleLanguageTokens);

        return [
            'character_offset_start' => $startOffset,
            'character_offset_end' => $endOffset,
            'paragraph_offset_start' => $paragraph['start'],
            'paragraph_offset_end' => $paragraph['end'],
            'surrounding_text_before' => $surrounding['before'],
            'surrounding_text_after' => $surrounding['after'],
            'co_occurring_entities' => $coOccurringFiltered,
            'nearby_dates' => $nearbyDates,
            'nearby_places' => $nearbyPlaces,
            'role_language_tokens' => $roleTokens,
            'ambiguity' => [
                'occurrence_count' => count($occurrences),
            ],
        ];
    }

    /** @return list<array{0:int,1:int}> */
    private function findAllOccurrences(string $haystack, string $needle): array
    {
        if ($needle === '' || $haystack === '') {
            return [];
        }
        $found = [];
        $offset = 0;
        $needleLen = strlen($needle);
        while (($pos = stripos($haystack, $needle, $offset)) !== false) {
            $found[] = [$pos, $pos + $needleLen];
            $offset = $pos + $needleLen;
        }
        return $found;
    }

    /** @return array{start:int,end:int,text:string} */
    private function findEnclosingParagraph(string $sourceText, int $offset): array
    {
        $before = substr($sourceText, 0, $offset);
        $after = substr($sourceText, $offset);

        // Paragraph break = two consecutive newlines (with optional whitespace).
        $start = 0;
        if (preg_match_all('/\n\s*\n/', $before, $matches, PREG_OFFSET_CAPTURE)) {
            $lastMatch = end($matches[0]);
            $start = $lastMatch[1] + strlen($lastMatch[0]);
        }
        $end = strlen($sourceText);
        if (preg_match('/\n\s*\n/', $after, $matches, PREG_OFFSET_CAPTURE)) {
            $end = $offset + $matches[0][1];
        }

        return [
            'start' => $start,
            'end' => $end,
            'text' => substr($sourceText, $start, $end - $start),
        ];
    }

    /** @return array{before:string,after:string} */
    private function getSurroundingText(string $sourceText, int $startOffset, int $endOffset): array
    {
        $len = strlen($sourceText);
        // Snap to valid UTF-8 char boundaries so substr() doesn't cut mid-sequence
        // (would produce invalid UTF-8 that MySQL rejects under strict modes).
        $beforeStart = max(0, $startOffset - self::SURROUNDING_TEXT_WINDOW);
        while ($beforeStart < $len && $beforeStart < $startOffset && (ord($sourceText[$beforeStart]) & 0xC0) === 0x80) {
            $beforeStart++;
        }
        $before = substr($sourceText, $beforeStart, $startOffset - $beforeStart);

        $afterEnd = min($len, $endOffset + self::SURROUNDING_TEXT_WINDOW);
        while ($afterEnd > $endOffset && $afterEnd < $len && (ord($sourceText[$afterEnd]) & 0xC0) === 0x80) {
            $afterEnd--;
        }
        $after = substr($sourceText, $endOffset, $afterEnd - $endOffset);

        return ['before' => $before, 'after' => $after];
    }

    /**
     * @param array{start:int,end:int,text:string} $paragraph
     * @param list<array{ner_entity_id:int,value:string,type:string}> $others
     * @return list<array>
     */
    private function findEntitiesInParagraph(array $paragraph, array $others, int $mentionAbsOffset): array
    {
        $hits = [];
        $paragraphText = $paragraph['text'];
        $mentionInPara = $mentionAbsOffset - $paragraph['start'];

        foreach ($others as $other) {
            if (($other['value'] ?? '') === '') {
                continue;
            }
            $pos = stripos($paragraphText, $other['value']);
            if ($pos === false) {
                continue;
            }
            $hits[] = [
                'ner_entity_id' => $other['ner_entity_id'] ?? null,
                'value' => $other['value'],
                'type' => $other['type'],
                'character_offset_start' => $paragraph['start'] + $pos,
                'distance_chars' => abs($pos - $mentionInPara),
            ];
        }
        return $hits;
    }

    /** @return list<array> */
    private function partitionByTypes(array $entities, array $types): array
    {
        return array_values(array_filter(
            $entities,
            fn($e) => in_array($e['type'] ?? '', $types, true)
        ));
    }

    /** @return list<array> */
    private function excludeTypes(array $entities, array $types): array
    {
        return array_values(array_filter(
            $entities,
            fn($e) => !in_array($e['type'] ?? '', $types, true)
        ));
    }

    /**
     * @param array{start:int,end:int,text:string} $paragraph
     * @param array<string,list<string>> $tokenList
     * @return list<array{token:string,kind:string,position_offset:int,distance_chars:int}>
     */
    private function findRoleLanguage(array $paragraph, int $mentionAbsOffset, array $tokenList): array
    {
        $hits = [];
        $paragraphText = $paragraph['text'];
        $mentionInPara = $mentionAbsOffset - $paragraph['start'];

        foreach ($tokenList as $kind => $tokens) {
            if (!is_array($tokens)) {
                continue;
            }
            foreach ($tokens as $token) {
                if (!is_string($token) || $token === '') {
                    continue;
                }
                $offset = 0;
                while (($pos = stripos($paragraphText, $token, $offset)) !== false) {
                    $distance = abs($pos - $mentionInPara);
                    if ($distance <= self::ROLE_LANGUAGE_CHAR_WINDOW) {
                        $hits[] = [
                            'token' => $token,
                            'kind' => (string) $kind,
                            'position_offset' => $paragraph['start'] + $pos,
                            'distance_chars' => $distance,
                        ];
                    }
                    $offset = $pos + strlen($token);
                }
            }
        }
        return $hits;
    }

    private function emptyContext(int $occurrenceCount): array
    {
        return [
            'character_offset_start' => null,
            'character_offset_end' => null,
            'paragraph_offset_start' => null,
            'paragraph_offset_end' => null,
            'surrounding_text_before' => null,
            'surrounding_text_after' => null,
            'co_occurring_entities' => [],
            'nearby_dates' => [],
            'nearby_places' => [],
            'role_language_tokens' => [],
            'ambiguity' => ['occurrence_count' => $occurrenceCount],
        ];
    }
}
