<?php

/**
 * ComplianceControlService - query surface over the Compliance Control Catalog.
 *
 * The catalogue is a vendor- and jurisdiction-agnostic mapping of regulatory
 * obligations to governance/privacy/access controls, with recommended
 * configuration. It backs the legal-mapping annex of the Industry AI for
 * RM/Archives framework: an implementer or procurement team asks "for this
 * regime, which controls apply and how should they be configured?".
 *
 * Read-only and fully fail-soft: every method tolerates a missing table and
 * returns an empty result rather than throwing, so callers degrade cleanly on a
 * fresh / partially-installed database.
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
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

namespace AhgPrivacy\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ComplianceControlService
{
    /** All controls, ordered for display. */
    public function controls(): array
    {
        if (! $this->ready()) {
            return [];
        }

        return DB::table('ahg_compliance_control')
            ->orderBy('sort_order')->orderBy('control_id')
            ->get()->map(fn ($c) => (array) $c)->all();
    }

    /** One control plus the regime obligations mapped to it, or null. */
    public function control(string $controlId): ?array
    {
        if (! $this->ready()) {
            return null;
        }
        $control = DB::table('ahg_compliance_control')->where('control_id', $controlId)->first();
        if ($control === null) {
            return null;
        }
        $row = (array) $control;
        $row['mappings'] = DB::table('ahg_compliance_mapping')
            ->where('control_id', $controlId)
            ->orderBy('regime')->get()->map(fn ($m) => (array) $m)->all();

        return $row;
    }

    /** Distinct regimes present in the catalogue. */
    public function regimes(): array
    {
        if (! $this->ready()) {
            return [];
        }

        return DB::table('ahg_compliance_mapping')
            ->select('regime')->distinct()->orderBy('regime')->pluck('regime')->all();
    }

    /**
     * Mappings for a regime, each enriched with the control name + category, so a
     * procurement team sees obligation -> control -> recommended config in one view.
     */
    public function forRegime(string $regime): array
    {
        if (! $this->ready()) {
            return [];
        }

        return DB::table('ahg_compliance_mapping as m')
            ->leftJoin('ahg_compliance_control as c', 'c.control_id', '=', 'm.control_id')
            ->where('m.regime', $regime)
            ->orderBy('c.sort_order')
            ->select('m.*', 'c.control_name', 'c.category', 'c.objective')
            ->get()->map(fn ($r) => (array) $r)->all();
    }

    /**
     * Free-text filter across controls (id / name / objective / category). Returns
     * the matching control rows; empty array when nothing matches or the table is
     * absent.
     */
    public function search(string $q): array
    {
        if (! $this->ready()) {
            return [];
        }
        $q = trim($q);
        if ($q === '') {
            return $this->controls();
        }
        $like = '%'.$q.'%';

        return DB::table('ahg_compliance_control')
            ->where(function ($w) use ($like) {
                $w->where('control_id', 'like', $like)
                    ->orWhere('control_name', 'like', $like)
                    ->orWhere('objective', 'like', $like)
                    ->orWhere('category', 'like', $like);
            })
            ->orderBy('sort_order')->get()->map(fn ($c) => (array) $c)->all();
    }

    /** The whole catalogue as a structured artefact (controls + their mappings). */
    public function export(): array
    {
        return array_map(function (array $c) {
            $c['mappings'] = DB::table('ahg_compliance_mapping')
                ->where('control_id', $c['control_id'])
                ->orderBy('regime')->get()->map(fn ($m) => (array) $m)->all();

            return $c;
        }, $this->controls());
    }

    private function ready(): bool
    {
        try {
            return Schema::hasTable('ahg_compliance_control') && Schema::hasTable('ahg_compliance_mapping');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
