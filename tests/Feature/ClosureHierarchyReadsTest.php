<?php

/**
 * ClosureHierarchyReadsTest - hierarchy reads use the closure tables (heratio#1541).
 *
 * A record created through RecordCreationService has a closure node but no
 * lft/rgt. Reads that used the nested set left it out of descendant counts,
 * propagation and breadcrumbs. These read sites now go through
 * HierarchyQueryService and include it.
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

namespace Tests\Feature;

use AhgCore\Services\ClosureMaintenanceService;
use AhgInformationObjectManage\Services\InformationObjectService as Io;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClosureHierarchyReadsTest extends TestCase
{
    use DatabaseTransactions;

    private int $parent;

    private int $child;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parent = Io::create(['title' => 'ZZ Closure parent'], 'en');
        // A child with a closure node and NO nested-set bounds.
        $this->child = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert(['id' => $this->child, 'parent_id' => $this->parent, 'lft' => null, 'rgt' => null, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $this->child, 'culture' => 'en', 'title' => 'ZZ Closure child']);
        app(ClosureMaintenanceService::class)->addNode('information_object', $this->child, $this->parent);
    }

    public function test_descendant_counts_include_a_child_without_nested_set_bounds(): void
    {
        $this->assertSame(1, (new \AhgInformationObjectManage\Services\ExtendedRightsService('en'))->getDescendantCount($this->parent));
    }

    public function test_breadcrumbs_include_the_parent_of_a_child_without_bounds(): void
    {
        $bib = app(\AhgResearch\Services\BibliographyService::class);
        $ancestors = (new \ReflectionMethod($bib, 'getObjectAncestors'))->invoke($bib, $this->child);
        $this->assertContains($this->parent, array_map(fn ($r) => (int) $r->id, $ancestors));

        $rules = app(\AhgRecordsManage\Services\ClassificationRuleService::class);
        $path = (new \ReflectionMethod($rules, 'buildAncestorPath'))->invoke($rules, $this->child);
        $this->assertStringContainsString('ZZ Closure parent', $path);
        $this->assertStringContainsString('ZZ Closure child', $path);
    }

    /** heratio#1541 batch 2: the RiC timeline lists a descendant without nested-set bounds. */
    public function test_the_ric_timeline_includes_a_child_without_bounds(): void
    {
        \AhgCore\Support\EventRow::replaceFor($this->child, [['type' => 'creation', 'start_date' => '1901-02-03', 'end_date' => null, 'date_display' => '1901']], 'en');

        $items = $this->getJson('/admin/ric/timeline-data?id='.$this->parent)->assertOk()->json('items');

        $this->assertContains('desc-'.$this->child.'-1901-02-03', array_column($items ?? [], 'id'));
    }

    /** heratio#1541 batch 2: a file-plan node cannot be moved under its own child, bounds or not. */
    public function test_a_file_plan_node_cannot_be_moved_into_its_own_subtree(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('rm_fileplan_node')) {
            $this->markTestSkipped('records management not installed.');
        }
        $node = fn (?int $parent, string $code) => (int) DB::table('rm_fileplan_node')->insertGetId([
            'parent_id' => $parent, 'node_type' => 'series', 'code' => $code, 'title' => 'ZZ '.$code,
            'status' => 'active', 'lft' => null, 'rgt' => null, 'created_by' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $top = $node(null, 'ZZ-A');
        $mid = $node($top, 'ZZ-A1');
        $leaf = $node($mid, 'ZZ-A1a');
        $other = $node(null, 'ZZ-B');
        $plan = app(\AhgRecordsManage\Services\FilePlanService::class);

        $this->assertFalse($plan->moveNode($top, $leaf), 'not under its own grandchild');
        $this->assertFalse($plan->moveNode($top, $top), 'not under itself');
        $this->assertSame($top, (int) DB::table('rm_fileplan_node')->where('id', $mid)->value('parent_id'), 'nothing moved');
        $this->assertTrue($plan->moveNode($mid, $other), 'an ordinary move still works');
    }
}
