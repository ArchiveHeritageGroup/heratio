<?php

/**
 * GlamIdentifierService - Service for Heratio
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


use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GLAM Identifier Service
 *
 * Handles identifier management across all GLAM sectors.
 * Ported from AtoM AtomFramework\Services\GlamIdentifierService.
 */
class GlamIdentifierService
{
    public const TYPE_ISBN13 = 'isbn13';
    public const TYPE_ISBN10 = 'isbn10';
    public const TYPE_ISSN = 'issn';
    public const TYPE_LCCN = 'lccn';
    public const TYPE_DOI = 'doi';
    public const TYPE_REFERENCE_CODE = 'reference_code';
    public const TYPE_IDENTIFIER = 'identifier';
    public const TYPE_ACCESSION = 'accession_number';
    public const TYPE_OBJECT_NUMBER = 'object_number';
    public const TYPE_ARTWORK_ID = 'artwork_id';
    public const TYPE_CATALOGUE_NUMBER = 'catalogue_number';
    public const TYPE_ASSET_ID = 'asset_id';
    public const TYPE_BARCODE = 'barcode';

    public const SECTOR_LIBRARY = 'library';
    public const SECTOR_ARCHIVE = 'archive';
    public const SECTOR_MUSEUM = 'museum';
    public const SECTOR_GALLERY = 'gallery';
    public const SECTOR_DAM = 'dam';

    private array $sectorIdentifiers = [
        self::SECTOR_LIBRARY => [
            self::TYPE_ISBN13 => ['label' => 'ISBN-13', 'icon' => 'barcode', 'primary' => true],
            self::TYPE_ISBN10 => ['label' => 'ISBN-10', 'icon' => 'barcode', 'primary' => false],
            self::TYPE_ISSN => ['label' => 'ISSN', 'icon' => 'newspaper', 'primary' => false],
            self::TYPE_LCCN => ['label' => 'LCCN', 'icon' => 'building-columns', 'primary' => false],
            self::TYPE_DOI => ['label' => 'DOI', 'icon' => 'link', 'primary' => false],
            self::TYPE_BARCODE => ['label' => 'Barcode', 'icon' => 'qrcode', 'primary' => false],
        ],
        self::SECTOR_ARCHIVE => [
            self::TYPE_REFERENCE_CODE => ['label' => 'Reference Code', 'icon' => 'folder-tree', 'primary' => true],
            self::TYPE_IDENTIFIER => ['label' => 'Identifier', 'icon' => 'hashtag', 'primary' => false],
            self::TYPE_BARCODE => ['label' => 'Barcode', 'icon' => 'qrcode', 'primary' => false],
        ],
        self::SECTOR_MUSEUM => [
            self::TYPE_ACCESSION => ['label' => 'Accession Number', 'icon' => 'stamp', 'primary' => true],
            self::TYPE_OBJECT_NUMBER => ['label' => 'Object Number', 'icon' => 'cube', 'primary' => false],
            self::TYPE_BARCODE => ['label' => 'Barcode', 'icon' => 'qrcode', 'primary' => false],
        ],
        self::SECTOR_GALLERY => [
            self::TYPE_ARTWORK_ID => ['label' => 'Artwork ID', 'icon' => 'palette', 'primary' => true],
            self::TYPE_CATALOGUE_NUMBER => ['label' => 'Catalogue Number', 'icon' => 'book', 'primary' => false],
            self::TYPE_BARCODE => ['label' => 'Barcode', 'icon' => 'qrcode', 'primary' => false],
        ],
        self::SECTOR_DAM => [
            self::TYPE_ASSET_ID => ['label' => 'Asset ID', 'icon' => 'photo-film', 'primary' => true],
            self::TYPE_IDENTIFIER => ['label' => 'Identifier', 'icon' => 'hashtag', 'primary' => false],
            self::TYPE_BARCODE => ['label' => 'Barcode', 'icon' => 'qrcode', 'primary' => false],
        ],
    ];

    public function getIdentifierTypesForSector(string $sector): array
    {
        return $this->sectorIdentifiers[$sector] ?? $this->sectorIdentifiers[self::SECTOR_ARCHIVE];
    }

    public function getPrimaryIdentifierType(string $sector): string
    {
        $types = $this->sectorIdentifiers[$sector] ?? [];
        foreach ($types as $type => $config) {
            if ($config['primary'] ?? false) {
                return $type;
            }
        }

        return self::TYPE_IDENTIFIER;
    }

    public function validateIdentifier(string $value, string $type): array
    {
        return match ($type) {
            self::TYPE_ISBN13 => $this->validateIsbn13($value),
            self::TYPE_ISBN10 => $this->validateIsbn10($value),
            self::TYPE_ISSN => $this->validateIssn($value),
            self::TYPE_DOI => $this->validateDoi($value),
            default => [
                'valid' => !empty(trim($value)),
                'message' => !empty(trim($value)) ? 'Valid' : 'Cannot be empty',
                'normalized' => trim($value),
            ]
        };
    }

    public function validateIsbn13(string $isbn): array
    {
        $result = ['valid' => false, 'message' => '', 'normalized' => ''];
        $clean = preg_replace('/[\s-]/', '', $isbn);
        $result['normalized'] = $clean;

        if (strlen($clean) !== 13 || !ctype_digit($clean)) {
            $result['message'] = 'ISBN-13 must be exactly 13 digits';

            return $result;
        }

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $clean[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        $checkDigit = (10 - ($sum % 10)) % 10;

        if ((int) $clean[12] !== $checkDigit) {
            $result['message'] = 'Invalid ISBN-13 check digit';

            return $result;
        }

        $result['valid'] = true;
        $result['message'] = 'Valid ISBN-13';

        return $result;
    }

    public function validateIsbn10(string $isbn): array
    {
        $result = ['valid' => false, 'message' => '', 'normalized' => ''];
        $clean = preg_replace('/[\s-]/', '', strtoupper($isbn));
        $result['normalized'] = $clean;

        if (strlen($clean) !== 10 || !preg_match('/^[0-9]{9}[0-9X]$/', $clean)) {
            $result['message'] = 'ISBN-10 must be 9 digits + check digit';

            return $result;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $clean[$i] * (10 - $i);
        }
        $lastValue = $clean[9] === 'X' ? 10 : (int) $clean[9];
        $sum += $lastValue;

        if ($sum % 11 !== 0) {
            $result['message'] = 'Invalid ISBN-10 check digit';

            return $result;
        }

        $result['valid'] = true;
        $result['message'] = 'Valid ISBN-10';

        return $result;
    }

    public function validateIssn(string $issn): array
    {
        $result = ['valid' => false, 'message' => '', 'normalized' => ''];
        $clean = preg_replace('/[\s-]/', '', strtoupper($issn));
        $result['normalized'] = substr($clean, 0, 4) . '-' . substr($clean, 4);

        if (strlen($clean) !== 8 || !preg_match('/^[0-9]{7}[0-9X]$/', $clean)) {
            $result['message'] = 'ISSN must be 8 characters (NNNN-NNNC)';

            return $result;
        }

        $sum = 0;
        for ($i = 0; $i < 7; $i++) {
            $sum += (int) $clean[$i] * (8 - $i);
        }
        $lastValue = $clean[7] === 'X' ? 10 : (int) $clean[7];
        $checkDigit = (11 - ($sum % 11)) % 11;

        if ($lastValue !== $checkDigit) {
            $result['message'] = 'Invalid ISSN check digit';

            return $result;
        }

        $result['valid'] = true;
        $result['message'] = 'Valid ISSN';

        return $result;
    }

    public function validateDoi(string $doi): array
    {
        $result = ['valid' => false, 'message' => '', 'normalized' => ''];
        $clean = preg_replace('/^(https?:\/\/)?(dx\.)?doi\.org\//', '', trim($doi));
        $clean = preg_replace('/^doi:\s*/i', '', $clean);
        $result['normalized'] = $clean;

        if (!preg_match('/^10\.\d{4,}\/\S+$/', $clean)) {
            $result['message'] = 'DOI must be in format 10.XXXX/identifier';

            return $result;
        }

        $result['valid'] = true;
        $result['message'] = 'Valid DOI';

        return $result;
    }

    public function convertIsbn10ToIsbn13(string $isbn10): ?string
    {
        $validation = $this->validateIsbn10($isbn10);
        if (!$validation['valid']) {
            return null;
        }

        $clean = $validation['normalized'];
        $base = '978' . substr($clean, 0, 9);

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $base[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        $checkDigit = (10 - ($sum % 10)) % 10;

        return $base . $checkDigit;
    }

    public function detectIdentifierType(string $value): ?string
    {
        $clean = preg_replace('/[\s-]/', '', $value);

        if (preg_match('/^97[89]\d{10}$/', $clean)) {
            return self::TYPE_ISBN13;
        }
        if (preg_match('/^\d{9}[\dX]$/i', $clean) && strlen($clean) === 10) {
            return self::TYPE_ISBN10;
        }
        if (preg_match('/^\d{7}[\dX]$/i', $clean) && strlen($clean) === 8) {
            return self::TYPE_ISSN;
        }
        if (preg_match('/^10\.\d{4,}\//', $value)) {
            return self::TYPE_DOI;
        }
        if (preg_match('/^\d{4}\.\d{3}\.\d+$/', $value)) {
            return self::TYPE_ACCESSION;
        }

        return null;
    }

    public function detectObjectSector(int $objectId): string
    {
        try {
            if (Schema::hasTable('display_object_config')) {
                $config = DB::table('display_object_config')
                    ->where('object_id', $objectId)
                    ->value('object_type');
                if ($config) {
                    return $config;
                }
            }

            if (Schema::hasTable('library_item') && DB::table('library_item')->where('object_id', $objectId)->exists()) {
                return self::SECTOR_LIBRARY;
            }
            if (Schema::hasTable('museum_object') && DB::table('museum_object')->where('object_id', $objectId)->exists()) {
                return self::SECTOR_MUSEUM;
            }
            if (Schema::hasTable('gallery_artwork') && DB::table('gallery_artwork')->where('object_id', $objectId)->exists()) {
                return self::SECTOR_GALLERY;
            }
            if (Schema::hasTable('dam_asset') && DB::table('dam_asset')->where('object_id', $objectId)->exists()) {
                return self::SECTOR_DAM;
            }
        } catch (\Throwable $e) {
            // Tables may not exist
        }

        return self::SECTOR_ARCHIVE;
    }

    public function getBestBarcodeIdentifier(int $objectId, ?string $sector = null): ?array
    {
        $object = DB::table('information_object AS io')
            ->leftJoin('information_object_i18n AS i18n', function ($join) {
                $join->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
            })
            ->where('io.id', $objectId)
            ->first(['io.*', 'i18n.title']);

        if (!$object) {
            return null;
        }

        if (!$sector) {
            $sector = $this->detectObjectSector($objectId);
        }

        $priorityOrder = $this->getIdentifierPriority($sector);

        foreach ($priorityOrder as $type) {
            $value = $this->getIdentifierValue($objectId, $type);
            if (!empty($value)) {
                return [
                    'type' => $type,
                    'value' => $value,
                    'label' => $this->sectorIdentifiers[$sector][$type]['label'] ?? $type,
                    'sector' => $sector,
                ];
            }
        }

        if (!empty($object->identifier)) {
            return [
                'type' => self::TYPE_IDENTIFIER,
                'value' => $object->identifier,
                'label' => 'Identifier',
                'sector' => $sector,
            ];
        }

        return null;
    }

    private function getIdentifierPriority(string $sector): array
    {
        return match ($sector) {
            self::SECTOR_LIBRARY => [
                self::TYPE_ISBN13, self::TYPE_ISBN10, self::TYPE_ISSN,
                self::TYPE_BARCODE, self::TYPE_LCCN, self::TYPE_DOI, self::TYPE_IDENTIFIER,
            ],
            self::SECTOR_ARCHIVE => [
                self::TYPE_REFERENCE_CODE, self::TYPE_IDENTIFIER, self::TYPE_BARCODE,
            ],
            self::SECTOR_MUSEUM => [
                self::TYPE_ACCESSION, self::TYPE_OBJECT_NUMBER, self::TYPE_BARCODE, self::TYPE_IDENTIFIER,
            ],
            self::SECTOR_GALLERY => [
                self::TYPE_ARTWORK_ID, self::TYPE_CATALOGUE_NUMBER, self::TYPE_BARCODE, self::TYPE_IDENTIFIER,
            ],
            self::SECTOR_DAM => [
                self::TYPE_ASSET_ID, self::TYPE_IDENTIFIER, self::TYPE_BARCODE,
            ],
            default => [self::TYPE_IDENTIFIER, self::TYPE_BARCODE]
        };
    }

    private function getIdentifierValue(int $objectId, string $type): ?string
    {
        try {
            return match ($type) {
                self::TYPE_ISBN13, self::TYPE_ISBN10 => Schema::hasTable('library_item')
                    ? DB::table('library_item')->where('object_id', $objectId)->value('isbn')
                    : null,
                self::TYPE_ISSN => Schema::hasTable('library_item')
                    ? DB::table('library_item')->where('object_id', $objectId)->value('issn')
                    : null,
                self::TYPE_LCCN => Schema::hasTable('library_item')
                    ? DB::table('library_item')->where('object_id', $objectId)->value('lccn')
                    : null,
                self::TYPE_DOI => $this->getMintedDoi($objectId)
                    ?? (Schema::hasTable('library_item') ? DB::table('library_item')->where('object_id', $objectId)->value('doi') : null),
                self::TYPE_BARCODE => (Schema::hasTable('library_item')
                    ? DB::table('library_item')->where('object_id', $objectId)->value('barcode')
                    : null)
                    ?? (Schema::hasTable('museum_object')
                        ? DB::table('museum_object')->where('object_id', $objectId)->value('barcode')
                        : null),
                self::TYPE_ACCESSION => Schema::hasTable('museum_object')
                    ? DB::table('museum_object')->where('object_id', $objectId)->value('accession_number')
                    : null,
                self::TYPE_OBJECT_NUMBER => Schema::hasTable('museum_object')
                    ? DB::table('museum_object')->where('object_id', $objectId)->value('object_number')
                    : null,
                self::TYPE_IDENTIFIER, self::TYPE_REFERENCE_CODE => DB::table('information_object')
                    ->where('id', $objectId)->value('identifier'),
                default => null
            };
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getMintedDoi(int $objectId): ?string
    {
        try {
            if (!Schema::hasTable('ahg_doi')) {
                return null;
            }
            return DB::table('ahg_doi')
                ->where('information_object_id', $objectId)
                ->whereIn('status', ['findable', 'registered', 'draft'])
                ->value('doi');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function hasMintedDoi(int $objectId): bool
    {
        try {
            if (!Schema::hasTable('ahg_doi')) {
                return false;
            }
            return DB::table('ahg_doi')
                ->where('information_object_id', $objectId)
                ->whereIn('status', ['findable', 'registered', 'draft'])
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getDoiRecord(int $objectId): ?object
    {
        try {
            if (!Schema::hasTable('ahg_doi')) {
                return null;
            }
            $doi = DB::table('ahg_doi')
                ->where('information_object_id', $objectId)
                ->first(['doi', 'status', 'minted_at', 'last_sync_at']);

            if ($doi) {
                $doi->url = 'https://doi.org/' . $doi->doi;
            }

            return $doi;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getDoiDisplayInfo(int $objectId): ?array
    {
        $record = $this->getDoiRecord($objectId);

        if (!$record) {
            return null;
        }

        return [
            'doi' => $record->doi,
            'url' => $record->url,
            'status' => $record->status,
            'minted_at' => $record->minted_at,
            'is_active' => in_array($record->status, ['findable', 'registered']),
        ];
    }

    public function getAllIdentifiers(int $objectId, ?string $sector = null): array
    {
        if (!$sector) {
            $sector = $this->detectObjectSector($objectId);
        }

        $identifiers = [];
        $types = $this->getIdentifierTypesForSector($sector);

        foreach ($types as $type => $config) {
            $value = $this->getIdentifierValue($objectId, $type);
            if (!empty($value)) {
                $identifiers[] = [
                    'type' => $type,
                    'value' => $value,
                    'label' => $config['label'],
                    'icon' => $config['icon'],
                    'primary' => $config['primary'] ?? false,
                ];
            }
        }

        // Always include minted DOI if not already included
        if (!in_array(self::TYPE_DOI, array_column($identifiers, 'type'))) {
            $mintedDoi = $this->getMintedDoi($objectId);
            if ($mintedDoi) {
                $identifiers[] = [
                    'type' => self::TYPE_DOI,
                    'value' => $mintedDoi,
                    'label' => 'DOI',
                    'icon' => 'link',
                    'primary' => false,
                    'minted' => true,
                ];
            }
        }

        return $identifiers;
    }
}
