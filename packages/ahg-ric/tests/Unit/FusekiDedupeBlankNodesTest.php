<?php

/**
 * FusekiDedupeBlankNodesTest - planner for the blank-node dedupe command
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

namespace Tests\Unit;

use AhgRic\Console\Commands\FusekiDedupeBlankNodesCommand;
use PHPUnit\Framework\TestCase;

/**
 * heratio#1519. The planner must collapse exact copies only: a subtree is a
 * copy when its whole content matches, nested nodes included, and anything
 * that differs is kept.
 */
class FusekiDedupeBlankNodesTest extends TestCase
{
    private const R = 'https://www.ica.org/standards/RiC/ontology#';

    private function agentName(string $entity, string $bnode, string $value): string
    {
        return "<{$entity}> <".self::R."hasAgentName> _:{$bnode} .\n"
            ."_:{$bnode} <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <".self::R."AgentName> .\n"
            ."_:{$bnode} <".self::R."textualValue> \"{$value}\" .\n";
    }

    private function extent(string $entity, string $e, string $c, string $n, string $carrier): string
    {
        return "<{$entity}> <".self::R."hasExtent> _:{$e} .\n"
            ."_:{$e} <".self::R."quantity> \"1\" .\n"
            ."_:{$e} <".self::R."hasCarrierType> _:{$c} .\n"
            ."_:{$c} <".self::R."hasOrHadName> _:{$n} .\n"
            ."_:{$n} <".self::R."textualValue> \"{$carrier}\" .\n";
    }

    public function test_identical_copies_collapse_to_one(): void
    {
        $nt = $this->agentName('https://x/agent/1', 'a', 'Archive Group')
            .$this->agentName('https://x/agent/1', 'b', 'Archive Group')
            .$this->agentName('https://x/agent/1', 'c', 'Archive Group');

        $plan = FusekiDedupeBlankNodesCommand::plan($nt);

        $this->assertSame(3, $plan['links']);
        $this->assertSame(1, $plan['kept']);
        $this->assertSame(9, $plan['triples_before']);
        $this->assertSame(3, $plan['triples_after']);
        $this->assertSame(0, $plan['variants']);
        $this->assertSame(1, substr_count($plan['insert'], 'Archive Group'));
    }

    public function test_different_content_is_kept_and_counted(): void
    {
        $nt = $this->agentName('https://x/agent/1', 'a', 'Old name')
            .$this->agentName('https://x/agent/1', 'b', 'New name')
            .$this->agentName('https://x/agent/1', 'c', 'New name');

        $plan = FusekiDedupeBlankNodesCommand::plan($nt);

        $this->assertSame(2, $plan['kept']);
        $this->assertSame(1, $plan['variants']);
        $this->assertStringContainsString('Old name', $plan['insert']);
        $this->assertStringContainsString('New name', $plan['insert']);
    }

    public function test_nested_subtrees_compare_by_full_content(): void
    {
        $nt = $this->extent('https://x/inst/1', 'e1', 'c1', 'n1', 'Digital')
            .$this->extent('https://x/inst/1', 'e2', 'c2', 'n2', 'Digital')
            .$this->extent('https://x/inst/1', 'e3', 'c3', 'n3', 'Paper');

        $plan = FusekiDedupeBlankNodesCommand::plan($nt);

        // e1 and e2 differ only in blank labels; e3's carrier name differs two levels down.
        $this->assertSame(2, $plan['kept']);
        $this->assertSame(10, $plan['triples_after']);
        $this->assertStringContainsString('_:e1 ', $plan['insert']);
        $this->assertStringNotContainsString('_:e2 ', $plan['insert']);
        $this->assertStringContainsString('"Paper"', $plan['insert']);
    }

    public function test_same_content_under_different_entities_is_not_merged(): void
    {
        $nt = $this->agentName('https://x/agent/1', 'a', 'Same')
            .$this->agentName('https://x/agent/2', 'b', 'Same');

        $this->assertSame(2, FusekiDedupeBlankNodesCommand::plan($nt)['kept']);
    }

    public function test_a_deduplicated_graph_plans_nothing_to_remove(): void
    {
        $once = FusekiDedupeBlankNodesCommand::plan(
            $this->agentName('https://x/agent/1', 'a', 'X').$this->agentName('https://x/agent/1', 'b', 'X')
        );
        $again = FusekiDedupeBlankNodesCommand::plan($once['insert']);

        $this->assertSame($again['links'], $again['kept']);
        $this->assertSame($once['triples_after'], $again['triples_after']);
    }
}
