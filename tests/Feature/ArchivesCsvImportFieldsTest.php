<?php

/**
 * ArchivesCsvImportFieldsTest - the archival description CSV import keeps
 * appraisal, accruals, notes, place/name/genre access points, languages,
 * scripts and publication status, and a re-import in update mode replaces
 * them instead of adding duplicates.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Constants\TermId;
use AhgInformationObjectManage\Services\ArchivesCsvImporter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ArchivesCsvImportFieldsTest extends TestCase
{
    use DatabaseTransactions;

    private function csv(array $row): string
    {
        $file = tempnam(sys_get_temp_dir(), 'zzf').'.csv';
        $fh = fopen($file, 'w');
        fputcsv($fh, array_keys($row));
        fputcsv($fh, array_values($row));
        fclose($fh);

        return $file;
    }

    private function import(array $row, string $mode = 'skip'): array
    {
        $file = $this->csv($row);
        $importer = (new ArchivesCsvImporter())->setUpdateMode($mode);
        $counters = $importer->import($file);
        @unlink($file);

        return [$counters, $importer->getErrors()];
    }

    private function notes(int $id, int $type): array
    {
        return DB::table('note as n')->join('note_i18n as ni', 'ni.id', '=', 'n.id')
            ->where('n.object_id', $id)->where('n.type_id', $type)->orderBy('n.id')->pluck('ni.content')->all();
    }

    private function terms(int $id, int $taxonomy): array
    {
        return DB::table('object_term_relation as r')->join('term as t', 't.id', '=', 'r.term_id')
            ->join('term_i18n as ti', fn ($j) => $j->on('ti.id', '=', 't.id')->where('ti.culture', 'en'))
            ->where('r.object_id', $id)->where('t.taxonomy_id', $taxonomy)->orderBy('ti.name')->pluck('ti.name')->all();
    }

    private function property(int $id, string $name): ?array
    {
        $v = DB::table('property as p')->join('property_i18n as pi', 'pi.id', '=', 'p.id')
            ->where('p.object_id', $id)->where('p.name', $name)->value('pi.value');

        return $v === null ? null : unserialize($v, ['allowed_classes' => false]);
    }

    public function test_the_import_keeps_every_field(): void
    {
        $tag = 'zzf'.bin2hex(random_bytes(4));
        $row = [
            'legacyId' => $tag, 'identifier' => strtoupper($tag), 'title' => "ZZ Fields {$tag}",
            'appraisal' => 'Duplicates destroyed', 'accruals' => 'Further deposits expected',
            'generalNote' => 'First note|Second note', 'publicationNote' => 'Published in 1990',
            'archivistNote' => 'Described by the archivist', 'languageNote' => 'Mostly in English',
            'subjectAccessPoints' => "{$tag} Subject",
            'placeAccessPoints' => "{$tag} Place A|{$tag} Place B",
            'nameAccessPoints' => "{$tag} Person, Ann|{$tag} Company",
            'genreAccessPoints' => "{$tag} Photographs",
            'language' => 'en|af', 'script' => 'Latn',
            'languageOfDescription' => 'en', 'scriptOfDescription' => 'Latn',
            'publicationStatus' => 'Published',
        ];

        [$counters, $errors] = $this->import($row);
        $this->assertSame(1, $counters['imported'], implode('; ', $errors));
        $this->assertSame([], $errors);

        $id = (int) DB::table('information_object')->where('identifier', strtoupper($tag))->value('id');
        $i18n = DB::table('information_object_i18n')->where('id', $id)->where('culture', 'en')->first();
        $this->assertSame('Duplicates destroyed', $i18n->appraisal);
        $this->assertSame('Further deposits expected', $i18n->accruals);

        $this->assertSame(['First note', 'Second note'], $this->notes($id, TermId::NOTE_GENERAL));
        $this->assertSame(['Published in 1990'], $this->notes($id, TermId::NOTE_PUBLICATION));
        $this->assertSame(['Described by the archivist'], $this->notes($id, TermId::NOTE_ARCHIVIST));
        $this->assertSame(['Mostly in English'], $this->notes($id, TermId::NOTE_LANGUAGE));

        $this->assertSame(["{$tag} Subject"], $this->terms($id, 35));
        $this->assertSame(["{$tag} Place A", "{$tag} Place B"], $this->terms($id, 42));
        $this->assertSame(["{$tag} Photographs"], $this->terms($id, 78));

        $names = DB::table('relation as r')->join('actor_i18n as a', fn ($j) => $j->on('a.id', '=', 'r.object_id')->where('a.culture', 'en'))
            ->where('r.subject_id', $id)->where('r.type_id', TermId::RELATION_NAME_ACCESS_POINT)
            ->orderBy('a.authorized_form_of_name')->pluck('a.authorized_form_of_name')->all();
        $this->assertSame(["{$tag} Company", "{$tag} Person, Ann"], $names);

        $this->assertSame(['en', 'af'], $this->property($id, 'language'));
        $this->assertSame(['Latn'], $this->property($id, 'script'));
        $this->assertSame(['en'], $this->property($id, 'languageOfDescription'));
        $this->assertSame(['Latn'], $this->property($id, 'scriptOfDescription'));

        $status = DB::table('status')->where('object_id', $id)->where('type_id', TermId::STATUS_TYPE_PUBLICATION)->pluck('status_id')->all();
        $this->assertSame([TermId::PUBLICATION_STATUS_PUBLISHED], array_map('intval', $status));

        // Update mode replaces what the record had; it does not add a second copy.
        [$counters] = $this->import(['legacyId' => $tag, 'identifier' => strtoupper($tag), 'title' => "ZZ Fields {$tag}",
            'generalNote' => 'Replaced note', 'nameAccessPoints' => "{$tag} Company", 'language' => 'fr', 'publicationStatus' => 'draft'], 'update');
        $this->assertSame(1, $counters['updated']);
        $this->assertSame(['Replaced note'], $this->notes($id, TermId::NOTE_GENERAL));
        $this->assertSame(['Published in 1990'], $this->notes($id, TermId::NOTE_PUBLICATION), 'a column left out is left alone');
        $this->assertSame(1, DB::table('relation')->where('subject_id', $id)->where('type_id', TermId::RELATION_NAME_ACCESS_POINT)->count());
        $this->assertSame(['fr'], $this->property($id, 'language'));
        $this->assertSame([TermId::PUBLICATION_STATUS_DRAFT], array_map('intval',
            DB::table('status')->where('object_id', $id)->where('type_id', TermId::STATUS_TYPE_PUBLICATION)->pluck('status_id')->all()));

        // An unknown status is reported and changes nothing.
        [, $errors] = $this->import(['legacyId' => $tag, 'identifier' => strtoupper($tag), 'title' => "ZZ Fields {$tag}", 'publicationStatus' => 'Embargoed'], 'update');
        $this->assertStringContainsString("unknown publication status 'Embargoed'", implode('; ', $errors));
        $this->assertSame(TermId::PUBLICATION_STATUS_DRAFT, (int) DB::table('status')->where('object_id', $id)->where('type_id', TermId::STATUS_TYPE_PUBLICATION)->value('status_id'));
    }
}
