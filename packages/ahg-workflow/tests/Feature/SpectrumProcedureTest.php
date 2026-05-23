<?php

/**
 * SpectrumProcedureTest - heratio Spectrum#A: catalog + service plumbing tests.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgWorkflow\Services\SpectrumProcedureCatalog;
use AhgWorkflow\Services\WorkflowService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SpectrumProcedureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_catalog_has_exactly_21_procedures(): void
    {
        $this->assertCount(21, SpectrumProcedureCatalog::all());
        $this->assertCount(21, SpectrumProcedureCatalog::codes());
    }

    public function test_catalog_codes_are_unique(): void
    {
        $codes = SpectrumProcedureCatalog::codes();
        $this->assertSame($codes, array_values(array_unique($codes)));
    }

    public function test_catalog_codes_follow_snake_case_convention(): void
    {
        foreach (SpectrumProcedureCatalog::codes() as $code) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*[a-z0-9]$/', $code, "Code '$code' should be lowercase snake_case");
        }
    }

    public function test_catalog_labels_are_non_empty(): void
    {
        foreach (SpectrumProcedureCatalog::all() as $code => $label) {
            $this->assertNotEmpty($label, "Code '$code' must have a non-empty label");
        }
    }

    public function test_label_returns_label_for_valid_code(): void
    {
        $this->assertSame('Object entry', SpectrumProcedureCatalog::label('object_entry'));
        $this->assertSame('Cataloguing', SpectrumProcedureCatalog::label('cataloguing'));
    }

    public function test_label_returns_empty_string_for_null_or_empty(): void
    {
        $this->assertSame('', SpectrumProcedureCatalog::label(null));
        $this->assertSame('', SpectrumProcedureCatalog::label(''));
    }

    public function test_label_falls_back_to_code_for_unknown(): void
    {
        $this->assertSame('legacy_code_no_longer_in_catalog', SpectrumProcedureCatalog::label('legacy_code_no_longer_in_catalog'));
    }

    public function test_normalize_returns_null_for_blank_or_invalid(): void
    {
        $this->assertNull(SpectrumProcedureCatalog::normalize(null));
        $this->assertNull(SpectrumProcedureCatalog::normalize(''));
        $this->assertNull(SpectrumProcedureCatalog::normalize('   '));
        $this->assertNull(SpectrumProcedureCatalog::normalize('not_a_real_code'));
    }

    public function test_normalize_returns_valid_code_unchanged(): void
    {
        $this->assertSame('object_entry', SpectrumProcedureCatalog::normalize('object_entry'));
        $this->assertSame('cataloguing', SpectrumProcedureCatalog::normalize('cataloguing'));
        $this->assertSame('loans_in', SpectrumProcedureCatalog::normalize('  loans_in  '));   // trims
    }

    public function test_workflow_service_persists_valid_spectrum_procedure(): void
    {
        $svc = new WorkflowService();
        $id = $svc->createWorkflow([
            'name' => 'Spectrum: Cataloguing test',
            'spectrum_procedure' => 'cataloguing',
        ]);

        $row = DB::table('ahg_workflow')->where('id', $id)->first(['spectrum_procedure']);
        $this->assertSame('cataloguing', $row->spectrum_procedure);
    }

    public function test_workflow_service_normalises_unknown_spectrum_to_null(): void
    {
        $svc = new WorkflowService();
        $id = $svc->createWorkflow([
            'name' => 'Bogus spectrum',
            'spectrum_procedure' => 'parsecs',   // invalid
        ]);

        $row = DB::table('ahg_workflow')->where('id', $id)->first(['spectrum_procedure']);
        $this->assertNull($row->spectrum_procedure);
    }

    public function test_get_workflows_filter_by_spectrum_procedure(): void
    {
        $svc = new WorkflowService();
        $idA = $svc->createWorkflow(['name' => 'WF-A', 'spectrum_procedure' => 'object_entry']);
        $idB = $svc->createWorkflow(['name' => 'WF-B', 'spectrum_procedure' => 'cataloguing']);
        $idC = $svc->createWorkflow(['name' => 'WF-C', 'spectrum_procedure' => 'object_entry']);
        $idN = $svc->createWorkflow(['name' => 'WF-no-spectrum']);   // no procedure set

        $all = $svc->getWorkflows();
        $ids = array_map(fn ($w) => (int) $w->id, $all);
        $this->assertContains($idA, $ids);
        $this->assertContains($idB, $ids);
        $this->assertContains($idC, $ids);
        $this->assertContains($idN, $ids);

        $entryOnly = $svc->getWorkflows('object_entry');
        $entryIds = array_map(fn ($w) => (int) $w->id, $entryOnly);
        $this->assertContains($idA, $entryIds);
        $this->assertContains($idC, $entryIds);
        $this->assertNotContains($idB, $entryIds);
        $this->assertNotContains($idN, $entryIds);
    }
}
