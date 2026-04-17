<?php

/**
 * IsbnLookupService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems.co.za
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



namespace AhgApi\Services;

use Illuminate\Support\Facades\Http;

/**
 * ISBN Lookup Service
 *
 * Scan-to-lookup functionality for library items.
 * Uses Open Library API (free) and Google Books as fallback.
 * Ported from AtoM AtomFramework\Services\IsbnLookupService.
 */
class IsbnLookupService
{
    private const OPEN_LIBRARY_API = 'https://openlibrary.org/api/books';
    private const GOOGLE_BOOKS_API = 'https://www.googleapis.com/books/v1/volumes';

    private GlamIdentifierService $identifierService;
    private ?string $googleApiKey;

    public function __construct(?string $googleApiKey = null)
    {
        $this->identifierService = new GlamIdentifierService();
        $this->googleApiKey = $googleApiKey;
    }

    public function lookupByIsbn(string $isbn): ?array
    {
        $type = $this->identifierService->detectIdentifierType($isbn);

        if (!in_array($type, [GlamIdentifierService::TYPE_ISBN10, GlamIdentifierService::TYPE_ISBN13])) {
            throw new \InvalidArgumentException('Invalid ISBN format');
        }

        $validation = $type === GlamIdentifierService::TYPE_ISBN13
            ? $this->identifierService->validateIsbn13($isbn)
            : $this->identifierService->validateIsbn10($isbn);

        if (!$validation['valid']) {
            throw new \InvalidArgumentException($validation['message']);
        }

        $normalizedIsbn = $validation['normalized'];
        $isbn13 = $type === GlamIdentifierService::TYPE_ISBN10
            ? $this->identifierService->convertIsbn10ToIsbn13($normalizedIsbn)
            : $normalizedIsbn;

        $result = $this->lookupOpenLibrary($isbn13 ?? $normalizedIsbn);

        if (!$result) {
            $result = $this->lookupGoogleBooks($isbn13 ?? $normalizedIsbn);
        }

        if ($result) {
            $result['source_isbn'] = $isbn;
            $result['normalized_isbn'] = $normalizedIsbn;
            $result['isbn13'] = $isbn13;
        }

        return $result;
    }

    private function lookupOpenLibrary(string $isbn): ?array
    {
        try {
            $response = Http::timeout(10)->get(self::OPEN_LIBRARY_API, [
                'bibkeys' => 'ISBN:' . $isbn,
                'format' => 'json',
                'jscmd' => 'data',
            ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();
            $key = 'ISBN:' . $isbn;

            if (empty($data[$key])) {
                return null;
            }

            $book = $data[$key];

            return [
                'source' => 'openlibrary',
                'title' => $book['title'] ?? null,
                'subtitle' => $book['subtitle'] ?? null,
                'authors' => array_map(fn($a) => $a['name'] ?? $a, $book['authors'] ?? []),
                'publishers' => array_map(
                    fn($p) => is_array($p) ? ($p['name'] ?? '') : $p,
                    $book['publishers'] ?? []
                ),
                'publish_date' => $book['publish_date'] ?? null,
                'publish_places' => array_map(
                    fn($p) => is_array($p) ? ($p['name'] ?? '') : $p,
                    $book['publish_places'] ?? []
                ),
                'number_of_pages' => $book['number_of_pages'] ?? null,
                'subjects' => array_map(
                    fn($s) => is_array($s) ? ($s['name'] ?? '') : $s,
                    $book['subjects'] ?? []
                ),
                'cover_url' => $book['cover']['medium'] ?? $book['cover']['small'] ?? null,
                'identifiers' => [
                    'isbn_10' => $book['identifiers']['isbn_10'] ?? [],
                    'isbn_13' => $book['identifiers']['isbn_13'] ?? [],
                    'lccn' => $book['identifiers']['lccn'] ?? [],
                    'oclc' => $book['identifiers']['oclc'] ?? [],
                ],
                'url' => $book['url'] ?? null,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function lookupGoogleBooks(string $isbn): ?array
    {
        try {
            $params = ['q' => 'isbn:' . $isbn];
            if ($this->googleApiKey) {
                $params['key'] = $this->googleApiKey;
            }

            $response = Http::timeout(10)->get(self::GOOGLE_BOOKS_API, $params);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();
            if (empty($data['items'][0])) {
                return null;
            }

            $book = $data['items'][0]['volumeInfo'];
            $identifiers = [];

            foreach ($book['industryIdentifiers'] ?? [] as $id) {
                $type = strtolower(str_replace('_', '', $id['type']));
                $identifiers[$type][] = $id['identifier'];
            }

            return [
                'source' => 'googlebooks',
                'title' => $book['title'] ?? null,
                'subtitle' => $book['subtitle'] ?? null,
                'authors' => $book['authors'] ?? [],
                'publishers' => isset($book['publisher']) ? [$book['publisher']] : [],
                'publish_date' => $book['publishedDate'] ?? null,
                'publish_places' => [],
                'number_of_pages' => $book['pageCount'] ?? null,
                'subjects' => $book['categories'] ?? [],
                'cover_url' => $book['imageLinks']['thumbnail'] ?? null,
                'identifiers' => $identifiers,
                'language' => $book['language'] ?? null,
                'description' => $book['description'] ?? null,
                'url' => $book['infoLink'] ?? null,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function lookupByIssn(string $issn): ?array
    {
        $validation = $this->identifierService->validateIssn($issn);
        if (!$validation['valid']) {
            throw new \InvalidArgumentException($validation['message']);
        }

        try {
            $response = Http::timeout(10)->get('https://openlibrary.org/search.json', [
                'q' => 'issn:' . $validation['normalized'],
            ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();
            if (empty($data['docs'][0])) {
                return null;
            }

            $doc = $data['docs'][0];

            return [
                'source' => 'openlibrary',
                'type' => 'periodical',
                'title' => $doc['title'] ?? null,
                'publishers' => $doc['publisher'] ?? [],
                'first_publish_year' => $doc['first_publish_year'] ?? null,
                'subjects' => $doc['subject'] ?? [],
                'issn' => $validation['normalized'],
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function mapToLibraryFields(array $lookupResult): array
    {
        return [
            'title' => $lookupResult['title'] ?? '',
            'subtitle' => $lookupResult['subtitle'] ?? '',
            'creator' => implode('; ', $lookupResult['authors'] ?? []),
            'publisher' => implode('; ', $lookupResult['publishers'] ?? []),
            'date_of_publication' => $lookupResult['publish_date'] ?? '',
            'place_of_publication' => implode('; ', $lookupResult['publish_places'] ?? []),
            'extent' => $lookupResult['number_of_pages']
                ? $lookupResult['number_of_pages'] . ' pages'
                : '',
            'isbn' => $lookupResult['isbn13'] ?? $lookupResult['normalized_isbn'] ?? '',
            'lccn' => $lookupResult['identifiers']['lccn'][0] ?? '',
            'oclc_number' => $lookupResult['identifiers']['oclc'][0] ?? '',
            'subjects' => $lookupResult['subjects'] ?? [],
            'language' => $lookupResult['language'] ?? '',
            'scope_and_content' => $lookupResult['description'] ?? '',
            'external_url' => $lookupResult['url'] ?? '',
            'cover_url' => $lookupResult['cover_url'] ?? '',
        ];
    }
}
