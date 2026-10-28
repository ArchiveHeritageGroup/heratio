<?php

/**
 * DisclosureGateSubtreeTest - a restriction that covers a record's
 * descendants also covers a descendant without nested-set bounds
 * (heratio#1541). The lft/rgt range alone let such a child through.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Services\ClosureMaintenanceService;
use AhgCore\Services\DisclosureGate;
use AhgCore\Support\StatusRow;
use AhgInformationObjectManage\Services\InformationObjectService as Io;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DisclosureGateSubtreeTest extends TestCase
{
    use DatabaseTransactions;

    private int $parent;

    private int $child;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parent = Io::create(['title' => 'ZZ Gate parent'], 'en');
        $this->child = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert(['id' => $this->child, 'parent_id' => $this->parent, 'lft' => null, 'rgt' => null, 'source_culture' => 'en']);
        app(ClosureMaintenanceService::class)->addNode('information_object', $this->child, $this->parent);
        StatusRow::set($this->parent, 158, 160);
        StatusRow::set($this->child, 158, 160);
    }

    public function test_a_protocol_restriction_on_the_parent_withholds_a_child_without_bounds(): void
    {
        DB::table('icip_access_restriction')->insert(['information_object_id' => $this->parent, 'restriction_type' => 'community_only', 'applies_to_descendants' => 1]);

        $gate = new DisclosureGate;
        $this->assertContains($this->child, $gate->icipRestrictedIds());
        $this->assertFalse($gate->allows($this->child));
    }

    public function test_an_inherited_classification_withholds_a_child_without_bounds(): void
    {
        $level = DB::table('security_classification')->where('level', '>', 0)->value('id');
        if ($level === null) {
            $this->markTestSkipped('no classification above public.');
        }
        DB::table('object_security_classification')->insert(['object_id' => $this->parent, 'classification_id' => $level, 'inherit_to_children' => 1, 'active' => 1]);

        $gate = new DisclosureGate;
        $this->assertContains($this->child, $gate->classifiedIds());
        $this->assertFalse($gate->allows($this->child));
    }
}
