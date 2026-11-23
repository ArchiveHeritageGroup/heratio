<?php

/**
 * heratio#1495: the hierarchy engine and the closed mapping vocabulary.
 *
 * @license AGPL-3.0-or-later
 */

namespace Tests\Unit;

use AhgDataMigration\SourceMigration\HierarchyRejected;
use AhgDataMigration\SourceMigration\MappingFile;
use AhgDataMigration\SourceMigration\ReferenceHierarchyEngine;
use AhgDataMigration\SourceMigration\TransformVocabulary;
use PHPUnit\Framework\TestCase;

class ReferenceHierarchyEngineTest extends TestCase
{
    private function build(array $refs): array
    {
        $records = [];
        foreach ($refs as $i => $ref) {
            $records[] = is_array($ref) ? ['key' => $i, 'ref' => $ref[0], 'parent' => $ref[1]] : ['key' => $i, 'ref' => $ref];
        }

        return (new ReferenceHierarchyEngine)->build($records);
    }

    private function codes(array $refs): array
    {
        try {
            $this->build($refs);
        } catch (HierarchyRejected $e) {
            return array_column($e->problems, 'code');
        }
        $this->fail('Expected rejection.');
    }

    public function test_parents_come_before_children_whatever_the_file_order(): void
    {
        $tree = $this->build(['A/1/2', 'A/10', 'A', 'A/1', 'A/2']);
        $this->assertSame(['A', 'A/1', 'A/1/2', 'A/2', 'A/10'], array_column($tree['nodes'], 'ref'));
        $this->assertSame([], $tree['synthesised']);
    }

    public function test_missing_levels_are_synthesised(): void
    {
        $tree = $this->build(['GB 123 ABC/1/2/3']);
        $this->assertSame(['GB 123 ABC', 'GB 123 ABC/1', 'GB 123 ABC/1/2'], $tree['synthesised']);
        $this->assertSame([0, 1, 2, 3], array_column($tree['nodes'], 'depth'));
    }

    public function test_spacing_variants_are_one_reference(): void
    {
        $this->assertContains('duplicate_reference', $this->codes(['A / 1', 'A/1']));
    }

    public function test_bad_sets_are_rejected(): void
    {
        $this->assertContains('malformed_reference', $this->codes(['A//1']));
        $this->assertContains('malformed_reference', $this->codes(['']));
        $this->assertContains('duplicate_reference', $this->codes(['A', 'A']));
        $this->assertContains('self_reference', $this->codes([['A/1', 'A/1']]));
    }

    public function test_cycles_are_rejected(): void
    {
        $this->assertSame(['cycle'], $this->codes([['A', 'B'], ['B', 'C'], ['C', 'A'], 'D']));
        $this->assertSame(['cycle'], $this->codes(['A', ['A/1', 'A/1/2'], 'A/1/2']));
    }

    public function test_numeric_references_are_handled(): void
    {
        $this->assertSame(['123', '123/4'], array_column($this->build(['123/4', '123'])['nodes'], 'ref'));
        try {
            $this->build([['1', '2'], ['2', '1'], ['3', '1']]);
            $this->fail('Expected rejection.');
        } catch (HierarchyRejected $e) {
            $this->assertSame('Cycle: 1 > 2', $e->problems[0]['detail']);
        }
    }

    public function test_a_branch_below_a_cycle_reports_only_the_cycle(): void
    {
        $this->assertSame(['cycle'], $this->codes([['A', 'B'], ['B', 'A'], ['C', 'A']]));
    }

    public function test_dates_keep_the_display_expression(): void
    {
        $t = new TransformVocabulary;
        $this->assertSame(['display' => 'circa 1890s', 'start' => '1890-01-01', 'end' => '1899-12-31', 'parsed' => true], $t->parseDate('circa 1890s'));
        $this->assertSame('1901-12-31', $t->parseDate('1899-1901')['end']);
        $this->assertSame('1890-02-28', $t->parseDate('Feb 1890')['end']);
        $this->assertSame('1890-03-04', $t->parseDate('4 March 1890')['start']);
        $ambiguous = $t->parseDate('03/04/1890');
        $this->assertFalse($ambiguous['parsed']);
        $this->assertSame('03/04/1890', $ambiguous['display']);
        $this->assertFalse($t->parseDate('1901-1899')['parsed']);
        $this->assertFalse($t->parseDate('31 Feb 1890')['parsed']);
        foreach (['Decade 1890', 'Marriage 1890', 'Mayday 1890', '1 Decade 1890', '4 Marched 1890', 'Junk 1890'] as $notADate) {
            $this->assertFalse($t->parseDate($notADate)['parsed'], $notADate);
            $this->assertNull($t->parseDate($notADate)['start'], $notADate);
        }
        foreach (['Sept 1890' => '1890-09-01', 'September 1890' => '1890-09-01', 'Sep. 1890' => '1890-09-01', 'may 1890' => '1890-05-01'] as $month => $start) {
            $this->assertSame($start, $t->parseDate($month)['start'], $month);
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsafeMappings')]
    public function test_mapping_files_outside_the_vocabulary_are_refused(array $field): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MappingFile::validate(['source' => 'x', 'reference_field' => 'R', 'delimiter' => '/', 'fields' => ['F' => $field]]);
    }

    public static function unsafeMappings(): array
    {
        return [
            'unknown op' => [['target' => 'title', 'transforms' => [['op' => 'eval', 'code' => 'phpinfo()']]]],
            'inline options' => [['target' => 'level', 'transforms' => [['op' => 'lookup', 'taxonomy' => 'x', 'values' => ['a' => 'b']]]]],
            'unknown target' => [['target' => 'password_hash']],
            'unknown key' => [['target' => 'title', 'expression' => '1+1']],
            'silent drop' => [['target' => null]],
        ];
    }

    public function test_the_calm_mapping_is_valid(): void
    {
        $json = json_decode((string) file_get_contents(MappingFile::directory().'/calm.json'), true);
        MappingFile::validate($json);
        $this->assertSame('calm', $json['source']);
    }
}
