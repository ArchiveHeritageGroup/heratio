<?php

/**
 * RicConformanceCommand - Heratio
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

namespace AhgRic\Console\Commands;

use AhgRic\Services\RicSerializationService;
use AhgRic\Services\ShaclValidationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * RiC-O SHACL conformance gate (#1319 / ADR-0003).
 *
 * Serializes a representative sample of each RiC entity type (per the
 * projection manifest) and validates each against the pinned RiC-O SHACL
 * shapes. Exits non-zero on any violation - or when the validator could not
 * actually run (missing pyshacl/rdflib), so a missing validator FAILS the gate
 * instead of silently passing. Wire this into CI as a hard gate.
 */
class RicConformanceCommand extends Command
{
    protected $signature = 'ahg:ric-conformance
                            {--sample=25 : Entities per type to validate (0 = all)}
                            {--soft : Report violations but exit 0 (default: fail)}';

    protected $description = 'Validate serialized RiC entities against the RiC-O SHACL shapes (governance gate, #1319).';

    /** type => [source table, serializer method, skip-root?]. Relations are edges (no node serializer), so excluded. */
    private const TYPES = [
        'record'        => ['information_object', 'serializeRecord', true],
        'agent'         => ['actor', 'serializeAgent', false],
        'place'         => ['ric_place', 'serializePlace', false],
        'rule'          => ['ric_rule', 'serializeRule', false],
        'activity'      => ['ric_activity', 'serializeActivity', false],
        'instantiation' => ['ric_instantiation', 'serializeInstantiation', false],
    ];

    public function handle(RicSerializationService $ser, ShaclValidationService $shacl): int
    {
        $sample = (int) $this->option('sample');
        $shapesPath = $shacl->conformanceShapesPath();
        $checked = 0;
        $nonConformant = 0;
        $notRun = 0;

        foreach (self::TYPES as $type => [$table, $method, $skipRoot]) {
            $q = DB::table($table)->orderBy('id');
            if ($skipRoot) {
                $q->where('id', '>', 1);
            }
            if ($sample > 0) {
                $q->limit($sample);
            }

            foreach ($q->pluck('id') as $id) {
                try {
                    $entity = $ser->{$method}((int) $id);
                } catch (\Throwable $e) {
                    $this->warn("  skip {$type}:{$id} (serialize error: " . $e->getMessage() . ')');
                    continue;
                }
                if (empty($entity)) {
                    continue;
                }

                $r = $shacl->validateAgainstShapes($entity, $shapesPath);
                $checked++;

                if (($r['ran'] ?? true) === false) {
                    $notRun++;
                    if ($notRun === 1) {
                        $this->error('  SHACL validator could not run: ' . ($r['reason'] ?? 'unknown')
                            . ' - install with: pip install pyshacl rdflib');
                    }
                    continue;
                }

                if (! ($r['valid'] ?? true)) {
                    $nonConformant++;
                    $this->error("  non-conformant {$type}:{$id}: "
                        . implode(' | ', array_slice($r['violations'] ?? [], 0, 3)));
                }
            }
        }

        $this->line("[ric-conformance] checked={$checked} non_conformant={$nonConformant} validator_unavailable={$notRun}");

        // A gate that can't validate is worse than none: fail when the validator
        // never ran (pyshacl/rdflib missing), not just on violations.
        if ($notRun > 0) {
            $this->error('SHACL validator unavailable - conformance NOT verified.');
            return $this->option('soft') ? self::SUCCESS : self::FAILURE;
        }
        if ($nonConformant > 0) {
            return $this->option('soft') ? self::SUCCESS : self::FAILURE;
        }

        $this->info('[ric-conformance] all ' . $checked . ' checked entities conform to the RiC-O SHACL shapes.');
        return self::SUCCESS;
    }
}
