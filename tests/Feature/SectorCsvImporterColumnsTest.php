<?php

/**
 * SectorCsvImporterColumnsTest - heratio#1778: every table and column the
 * museum, library, gallery and DAM CSV importers write must exist, and a
 * failed sector write must reach the import report instead of only the log.
 * Reads the schema only; nothing is written.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Services\Import\SectorCsvImporter;
use AhgDam\Services\DamCsvImporter;
use AhgGallery\Services\GalleryCsvImporter;
use AhgLibrary\Services\LibraryCsvImporter;
use AhgMuseum\Services\MuseumCsvImporter;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SectorCsvImporterColumnsTest extends TestCase
{
    public static function importers(): array
    {
        return [
            'museum' => [MuseumCsvImporter::class],
            'library' => [LibraryCsvImporter::class],
            'gallery' => [GalleryCsvImporter::class],
            'dam' => [DamCsvImporter::class],
        ];
    }

    /** A row where every mapped key is filled, once with text and once with a date. */
    private function fullRows(SectorCsvImporter $importer): array
    {
        $keys = array_unique(array_merge(array_values($importer->getColumnMap()), [
            'isbn', 'issn', 'publisher', 'language', 'technique', 'caption', 'headline',
            'license', 'iso', 'focalLength', 'resolution', 'dimensions',
        ]));

        return [
            array_fill_keys($keys, '12'),
            array_fill_keys($keys, '2020-01-31'),
            array_fill_keys($keys, '1920x1080'),
        ];
    }

    private function invokeHidden(object $obj, string $method, ...$args)
    {
        $m = new \ReflectionMethod($obj, $method);
        $m->setAccessible(true);

        return $m->invoke($obj, ...$args);
    }

    #[DataProvider('importers')]
    public function test_metadata_columns_exist(string $class): void
    {
        $importer = new $class();
        $written = [];

        foreach ($this->fullRows($importer) as $data) {
            foreach ($this->invokeHidden($importer, 'metadataRows', $data) as $table => [$key, $values]) {
                $written[$table][$key] = true;
                foreach (array_keys($values) as $column) {
                    $written[$table][$column] = true;
                }
            }
            foreach (array_keys($this->invokeHidden($importer, 'getI18nFieldMap', $data)) as $column) {
                $written['information_object_i18n'][$column] = true;
            }
        }

        $this->assertNotEmpty($written, "{$class} writes no sector metadata");

        $missing = [];
        foreach ($written as $table => $columns) {
            if (! Schema::hasTable($table)) {
                $missing[] = "{$table} (table)";

                continue;
            }
            foreach (array_keys($columns) as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    $missing[] = "{$table}.{$column}";
                }
            }
        }

        $this->assertSame([], $missing, "{$class} writes to columns that do not exist");
    }

    public function test_failed_sector_write_is_reported(): void
    {
        $importer = new MuseumCsvImporter();

        // A SELECT against a missing table fails before any write is attempted.
        $this->invokeHidden($importer, 'upsertMetadata', 'no_such_table_1778', 'object_id', 1, ['x' => 'y']);

        $errors = $importer->getErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('no_such_table_1778', $errors[0]);

        // Counted, so the import command exits non-zero (heratio#1782).
        $this->assertSame(1, $importer->getCounters()['sector_failures']);
        $this->assertSame(0, $importer->getCounters()['errors']);
    }
}
