<?php

/**
 * MarcMergeApiTest - exercises MarcMergeService (the engine behind
 * POST /api/cataloguing/marc/merge).
 *
 * The "no master match" path is fully DB-free: when the incoming 001 matches
 * no IO, every field is reported as an addition and there are no conflicts.
 * DB-bound master-vs-incoming diffing is covered by the round-trip test in
 * the metadata-export package, which has access to the schema.
 *
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Tests\Feature;

use AhgLibrary\Services\MarcMergeService;
use AhgLibrary\Tests\AhgLibraryTestCase;

class MarcMergeApiTest extends AhgLibraryTestCase
{
    public function test_empty_payload_returns_no_record_warning(): void
    {
        $svc = new MarcMergeService();
        $report = $svc->diff('<collection xmlns="http://www.loc.gov/MARC21/slim"></collection>');
        $this->assertFalse($report['matched']);
        $this->assertFalse($report['has_conflicts']);
        $this->assertNotEmpty($report['warnings']);
    }

    public function test_unmatched_record_reports_all_fields_as_added(): void
    {
        $svc = new MarcMergeService();
        // 001 deliberately unlikely to match anything in the test DB.
        $report = $svc->diff($this->record('ZZZ-NO-MATCH-9999999', 'Brand New Record'));

        $this->assertFalse($report['matched'], 'control number should not match an existing IO');
        $this->assertFalse($report['has_conflicts'], 'an unmatched record has no conflicts, only additions');
        $this->assertSame(0, $report['conflict_count']);
        $this->assertSame('Brand New Record', $report['title']);

        // The 245 title field must be present in the diff and classified 'added'.
        $titleField = null;
        foreach ($report['fields'] as $f) {
            if ($f['field'] === 'title') {
                $titleField = $f;
                break;
            }
        }
        $this->assertNotNull($titleField, 'title field missing from diff report');
        $this->assertSame('added', $titleField['status']);
        $this->assertSame('Brand New Record', $titleField['incoming']);
        $this->assertNull($titleField['master']);
    }

    public function test_report_shape_includes_scalar_and_list_fields(): void
    {
        $svc = new MarcMergeService();
        $report = $svc->diff($this->record('ZZZ-NO-MATCH-8888888', 'Shape Check'));

        $fieldKeys = array_column($report['fields'], 'field');
        // A representative scalar and a representative list field.
        $this->assertContains('scope_and_content', $fieldKeys);
        $this->assertContains('subjects', $fieldKeys);
        $this->assertContains('creators', $fieldKeys);
    }

    private function record(string $controlNumber, string $title): string
    {
        $cn = htmlspecialchars($controlNumber, ENT_XML1);
        $t = htmlspecialchars($title, ENT_XML1);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<collection xmlns="http://www.loc.gov/MARC21/slim">
  <record>
    <leader>00000nam a2200000 i 4500</leader>
    <controlfield tag="001">{$cn}</controlfield>
    <datafield tag="245" ind1="0" ind2="0">
      <subfield code="a">{$t}</subfield>
    </datafield>
    <datafield tag="520" ind1=" " ind2=" ">
      <subfield code="a">A summary statement.</subfield>
    </datafield>
    <datafield tag="650" ind1=" " ind2="4">
      <subfield code="a">A Subject</subfield>
    </datafield>
  </record>
</collection>
XML;
    }
}
