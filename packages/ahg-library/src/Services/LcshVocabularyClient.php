<?php

/**
 * LcshVocabularyClient - reads one concept from the Library of Congress Linked
 * Data Service as SKOS JSON (heratio#1475 item 3). Read-only. The host is fixed
 * and identifiers are validated, so no user input can steer the request.
 *
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LcshVocabularyClient
{
    public const SCHEME = 'lcsh';
    private const BASE = 'https://id.loc.gov/authorities/subjects/';
    private const SKOS = 'http://www.w3.org/2004/02/skos/core#';
    private const MAX_NEIGHBOURS = 200;

    public static function validId(string $id): bool
    {
        return preg_match('/^sh[0-9]{6,12}$/', $id) === 1;
    }

    /**
     * @return array{external_id:string,uri:string,label:string,broader:array,narrower:array,broader_complete:bool,narrower_complete:bool}
     * @throws RuntimeException when the concept cannot be read
     */
    public function fetch(string $id): array
    {
        if (! self::validId($id)) {
            throw new RuntimeException('Invalid LCSH identifier.');
        }

        try {
            $response = Http::timeout(15)->connectTimeout(5)->acceptJson()
                ->withOptions(['allow_redirects' => false])
                ->get(self::BASE.$id.'.skos.json');
        } catch (ConnectionException) {
            throw new RuntimeException('The vocabulary service could not be reached.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('The vocabulary service returned HTTP '.$response->status().'.');
        }

        $nodes = $response->json();
        if (! is_array($nodes)) {
            throw new RuntimeException('The vocabulary service returned an unreadable response.');
        }

        return $this->parse($id, $nodes);
    }

    private function parse(string $id, array $nodes): array
    {
        $byId = [];
        foreach ($nodes as $node) {
            if (is_array($node) && isset($node['@id']) && is_string($node['@id'])) {
                $byId[$this->canonical($node['@id'])] = $node;
            }
        }

        $uri = 'http://id.loc.gov/authorities/subjects/'.$id;
        $concept = $byId[$uri] ?? null;
        $label = $concept ? $this->label($concept) : null;
        if ($label === null) {
            throw new RuntimeException('The response did not contain this concept.');
        }

        return [
            'external_id' => $id,
            'uri' => $uri,
            'label' => $label,
            'broader' => $this->references($concept, 'broader', $byId),
            'narrower' => $this->references($concept, 'narrower', $byId),
            'broader_complete' => count((array) ($concept[self::SKOS.'broader'] ?? [])) <= self::MAX_NEIGHBOURS,
            'narrower_complete' => count((array) ($concept[self::SKOS.'narrower'] ?? [])) <= self::MAX_NEIGHBOURS,
        ];
    }

    private function references(array $concept, string $property, array $byId): array
    {
        $out = [];
        foreach (array_slice((array) ($concept[self::SKOS.$property] ?? []), 0, self::MAX_NEIGHBOURS) as $ref) {
            $uri = is_array($ref) && is_string($ref['@id'] ?? null) ? $this->canonical($ref['@id']) : '';
            if (! preg_match('#^http://id\.loc\.gov/authorities/subjects/(sh[0-9]{6,12})$#', $uri, $m)) {
                continue;
            }
            $out[] = [
                'external_id' => $m[1],
                'uri' => $uri,
                'label' => isset($byId[$uri]) ? $this->label($byId[$uri]) : null,
            ];
        }

        return $out;
    }

    private function canonical(string $uri): string
    {
        return preg_replace('#^https://#', 'http://', rtrim($uri, '/'));
    }

    private function label(array $node): ?string
    {
        $values = (array) ($node[self::SKOS.'prefLabel'] ?? []);
        $fallback = null;
        foreach ($values as $value) {
            $text = is_array($value) ? ($value['@value'] ?? null) : $value;
            if (! is_string($text) || trim($text) === '') {
                continue;
            }
            if (is_array($value) && str_starts_with((string) ($value['@language'] ?? ''), 'en')) {
                return trim($text);
            }
            $fallback ??= trim($text);
        }

        return $fallback;
    }
}
