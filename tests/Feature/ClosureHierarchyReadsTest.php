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
}
